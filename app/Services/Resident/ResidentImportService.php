<?php

namespace App\Services\Resident;

use App\Enums\Resident\HouseResidentStatus;
use App\Enums\Resident\OccupancyStatus;
use App\Enums\Resident\RelationshipType;
use App\Models\Block;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\Resident;
use App\Services\Audit\AuditService;
use App\Support\Phone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Import rumah & penghuni dari CSV spreadsheet lama (Dok 07 Fase 2).
 *
 * Format kolom (judul tidak peka huruf besar/kecil, urutan bebas; pemisah
 * koma atau titik koma seperti ekspor Excel Indonesia):
 *
 *   blok*, nomor*, nama, no_hp, email, hubungan, tanggal_mulai, status_hunian, catatan
 *
 * - Blok yang belum ada dibuat otomatis; rumah dicocokkan per blok+nomor.
 * - Baris tanpa nama hanya mendaftarkan rumah.
 * - Penghuni dicocokkan lewat nomor HP; bila tidak ada, lewat nama yang
 *   sama di rumah itu. Penghuni yang sudah tercatat aktif dilewati, jadi
 *   file yang sama aman diimport ulang.
 * - Semua baris divalidasi dulu; bila ada satu kesalahan, TIDAK ADA yang
 *   disimpan.
 */
class ResidentImportService
{
    /** Alias judul kolom → nama kolom baku. */
    private const HEADER_ALIASES = [
        'blok' => 'blok', 'block' => 'blok',
        'nomor' => 'nomor', 'no' => 'nomor', 'no_rumah' => 'nomor', 'nomor_rumah' => 'nomor', 'number' => 'nomor',
        'nama' => 'nama', 'nama_pemilik' => 'nama', 'nama_penghuni' => 'nama', 'name' => 'nama',
        'no_hp' => 'no_hp', 'hp' => 'no_hp', 'telepon' => 'no_hp', 'phone' => 'no_hp', 'no_telp' => 'no_hp',
        'email' => 'email',
        'hubungan' => 'hubungan', 'status_kepemilikan' => 'hubungan', 'relationship' => 'hubungan',
        'tanggal_mulai' => 'tanggal_mulai', 'mulai_menghuni' => 'tanggal_mulai', 'start_date' => 'tanggal_mulai',
        'status_hunian' => 'status_hunian', 'occupancy' => 'status_hunian',
        'catatan' => 'catatan', 'keterangan' => 'catatan', 'notes' => 'catatan',
    ];

    private const OCCUPANCY_ALIASES = [
        'dihuni' => OccupancyStatus::Occupied, 'occupied' => OccupancyStatus::Occupied, 'isi' => OccupancyStatus::Occupied,
        'kosong' => OccupancyStatus::Vacant, 'vacant' => OccupancyStatus::Vacant,
        'renovasi' => OccupancyStatus::UnderRenovation, 'under_renovation' => OccupancyStatus::UnderRenovation,
    ];

    public function __construct(
        private readonly HouseService $houses,
        private readonly ResidencyService $residency,
        private readonly AuditService $audit,
    ) {}

    /**
     * Baca & validasi file tanpa menyimpan apa pun.
     *
     * @return array{rows: list<array<string, mixed>>, errors: list<string>}
     */
    public function parse(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content; // BOM Excel
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];

        if ($lines === [] || trim($lines[0]) === '') {
            return ['rows' => [], 'errors' => ['File kosong.']];
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => self::HEADER_ALIASES[Str::snake(Str::lower(trim((string) $h)))] ?? null, str_getcsv($lines[0], $delimiter));

        if (! in_array('blok', $header, true) || ! in_array('nomor', $header, true)) {
            return ['rows' => [], 'errors' => ['Baris judul wajib memuat kolom "blok" dan "nomor".']];
        }

        $rows = [];
        $errors = [];
        $seen = [];

        foreach (array_slice($lines, 1) as $index => $line) {
            $lineNumber = $index + 2;

            if (trim(str_replace($delimiter, '', $line)) === '') {
                continue;
            }

            $values = str_getcsv($line, $delimiter);
            $row = [];
            foreach ($header as $position => $column) {
                if ($column !== null) {
                    $row[$column] = trim((string) ($values[$position] ?? ''));
                }
            }

            [$normalized, $rowErrors] = $this->normalizeRow($row);

            $key = $normalized['house_code'] ?? null;
            if ($key && blank($normalized['nama']) && isset($seen[$key])) {
                $rowErrors[] = "rumah {$key} sudah muncul di baris {$seen[$key]}";
            }
            if ($key) {
                $seen[$key] ??= $lineNumber;
            }

            foreach ($rowErrors as $error) {
                $errors[] = "Baris {$lineNumber}: {$error}.";
            }

            $rows[] = ['line' => $lineNumber, ...$normalized];
        }

        if ($rows === [] && $errors === []) {
            $errors[] = 'Tidak ada baris data di bawah judul kolom.';
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Simpan hasil parse() dalam satu transaksi.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{blocks: int, houses: int, residents: int, occupancies: int, skipped: int}
     */
    public function import(array $rows, ?string $fileName = null): array
    {
        $summary = ['blocks' => 0, 'houses' => 0, 'residents' => 0, 'occupancies' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, &$summary, $fileName) {
            foreach ($rows as $row) {
                $block = Block::firstWhere('code', $row['blok']);
                if (! $block) {
                    $block = $this->houses->createBlock(['code' => $row['blok']]);
                    $summary['blocks']++;
                }

                $house = House::withTrashed()->where('block_id', $block->getKey())->where('house_number', $row['nomor'])->first();
                if ($house?->trashed()) {
                    $house->restore();
                }
                if (! $house) {
                    $house = $this->houses->createHouse([
                        'block_id' => $block->getKey(),
                        'house_number' => $row['nomor'],
                        'notes' => $row['catatan'] ?: null,
                    ]);
                    $summary['houses']++;
                }

                if (blank($row['nama'])) {
                    $this->applyOccupancyOverride($house, $row['status_hunian']);

                    continue;
                }

                $resident = $this->findResident($house, $row);
                if (! $resident) {
                    $resident = $this->residency->createResident([
                        'full_name' => $row['nama'],
                        'phone' => $row['no_hp'] ?: null,
                        'email' => $row['email'] ?: null,
                    ]);
                    $summary['residents']++;
                }

                $alreadyActive = HouseResident::where('house_id', $house->getKey())
                    ->where('resident_id', $resident->getKey())
                    ->where('status', HouseResidentStatus::Active)
                    ->exists();

                if ($alreadyActive) {
                    $summary['skipped']++;
                } else {
                    $this->residency->moveIn($house, $resident, [
                        'relationship_type' => $row['hubungan'],
                        'start_date' => $row['tanggal_mulai'],
                    ]);
                    $summary['occupancies']++;
                }

                $this->applyOccupancyOverride($house, $row['status_hunian']);
            }

            $this->audit->record('residents.imported', null, ['file' => $fileName, ...$summary]);
        });

        return $summary;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function normalizeRow(array $row): array
    {
        $errors = [];
        $blok = strtoupper($row['blok'] ?? '');
        $nomor = strtoupper($row['nomor'] ?? '');

        if ($blok === '') {
            $errors[] = 'kolom blok kosong';
        } elseif (mb_strlen($blok) > 30) {
            $errors[] = 'kode blok terlalu panjang (maks. 30 karakter)';
        }

        if ($nomor === '') {
            $errors[] = 'kolom nomor kosong';
        } elseif (mb_strlen($nomor) > 30) {
            $errors[] = 'nomor rumah terlalu panjang (maks. 30 karakter)';
        }

        $nama = $row['nama'] ?? '';
        if (mb_strlen($nama) > 150) {
            $errors[] = 'nama terlalu panjang (maks. 150 karakter)';
        }

        $phone = Phone::normalize($row['no_hp'] ?? null);
        if (($row['no_hp'] ?? '') !== '' && ($phone === null || strlen($phone) < 9 || strlen($phone) > 15)) {
            $errors[] = "nomor HP \"{$row['no_hp']}\" tidak valid";
        }

        $email = Str::lower($row['email'] ?? '');
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "email \"{$row['email']}\" tidak valid";
        }

        $startDate = today()->toDateString();
        if (($row['tanggal_mulai'] ?? '') !== '') {
            $startDate = $this->parseDate($row['tanggal_mulai']);
            if ($startDate === null) {
                $errors[] = "tanggal_mulai \"{$row['tanggal_mulai']}\" tidak dikenali (pakai DD/MM/YYYY atau YYYY-MM-DD)";
            }
        }

        $occupancy = null;
        if (($row['status_hunian'] ?? '') !== '') {
            $occupancy = self::OCCUPANCY_ALIASES[Str::lower($row['status_hunian'])] ?? null;
            if ($occupancy === null) {
                $errors[] = "status_hunian \"{$row['status_hunian']}\" tidak dikenali (dihuni/kosong/renovasi)";
            }
        }

        return [[
            'blok' => $blok,
            'nomor' => $nomor,
            'house_code' => $blok !== '' && $nomor !== '' ? House::codeFor($blok, $nomor) : null,
            'nama' => $nama,
            'no_hp' => $phone,
            'email' => $email,
            'hubungan' => RelationshipType::fromInput($row['hubungan'] ?? null)->value,
            'tanggal_mulai' => $startDate,
            'status_hunian' => $occupancy?->value,
            'catatan' => $row['catatan'] ?? '',
        ], $errors];
    }

    private function parseDate(string $value): ?string
    {
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date !== false && $date->format($format) === $value) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                // coba format berikutnya
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function findResident(House $house, array $row): ?Resident
    {
        if ($row['no_hp']) {
            $byPhone = Resident::where('phone', $row['no_hp'])->first();
            if ($byPhone) {
                return $byPhone;
            }
        }

        return Resident::query()
            ->whereIn('id', HouseResident::select('resident_id')->where('house_id', $house->getKey()))
            ->whereRaw('LOWER(full_name) = ?', [Str::lower($row['nama'])])
            ->first();
    }

    private function applyOccupancyOverride(House $house, ?string $status): void
    {
        if ($status === OccupancyStatus::UnderRenovation->value) {
            $house->update(['occupancy_status' => OccupancyStatus::UnderRenovation]);
        }
    }
}
