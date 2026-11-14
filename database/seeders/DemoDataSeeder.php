<?php

namespace Database\Seeders;

use App\Enums\Communication\ComplaintCategory;
use App\Enums\Communication\ComplaintStatus;
use App\Enums\CommunityRole;
use App\Enums\Resident\AssignmentType;
use App\Models\BillingExemption;
use App\Models\CashAccount;
use App\Models\FinanceCategory;
use App\Models\House;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WasteSubscription;
use App\Services\Billing\BillingService;
use App\Services\Communication\CommunityService;
use App\Services\Contribution\ContributionService;
use App\Services\Finance\CashService;
use App\Services\Payment\PaymentService;
use App\Services\Resident\HouseService;
use App\Services\Resident\OfficerAssignmentService;
use App\Services\Resident\ResidencyService;
use App\Services\Waste\WasteService;
use App\Services\Water\MeterReadingService;
use App\Services\Water\WaterCustomerService;
use App\Services\Water\WaterTariffService;
use App\Services\Water\WaterUsageService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Data demo De Java Valley Residence: ±5 bulan operasional (Jun–Nov 2026)
 * yang dibuat LEWAT SERVICE aplikasi — sama persis dengan pemakaian nyata,
 * sehingga audit log, nomor dokumen, snapshot, dan saldo kas konsisten.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Nama penghuni fiktif. Angka tarif & pola mengikuti sampel (S-1..S-10).
 * Hanya berjalan bila belum ada rumah (aman dari menimpa data asli).
 */
class DemoDataSeeder extends Seeder
{
    private const NAMES = [
        'Agus Setiawan', 'Budi Hartono', 'Cecep Supriatna', 'Dedi Kurniawan', 'Euis Rahmawati', 'Fajar Nugraha',
        'Gita Permata', 'Hendra Wijaya', 'Ida Farida', 'Joko Susilo', 'Kurnia Sari', 'Lina Marlina', 'Maman Suherman',
        'Nina Herlina', 'Oki Firmansyah', 'Putri Ayu', 'Rudi Hidayat', 'Siti Nurjanah', 'Tatang Sutisna', 'Ujang Rohman',
        'Vina Oktaviani', 'Wawan Gunawan', 'Yayan Sopyan', 'Zaenal Arifin', 'Asep Saepudin', 'Dewi Lestari',
    ];

    private User $bendahara;

    private User $ketua;

    private User $petugasAir;

    private User $petugasSampah;

    private User $warga;

    public function run(): void
    {
        if (House::exists()) {
            $this->command?->warn('Sudah ada data rumah — DemoDataSeeder dilewati supaya data asli tidak tertimpa.');

            return;
        }

        $this->bendahara = $this->demo(CommunityRole::Bendahara);
        $this->ketua = $this->demo(CommunityRole::KetuaRt);
        $this->petugasAir = $this->demo(CommunityRole::PetugasLapangan);
        $this->petugasSampah = $this->demo(CommunityRole::PetugasSampah);
        $this->warga = $this->demo(CommunityRole::Warga);
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $realNow = Carbon::now();

        try {
            $this->at('2026-05-25 09:00', $admin);
            $houses = $this->houses();
            $this->setup($houses);

            foreach (['2026-06', '2026-07', '2026-08', '2026-09', '2026-10'] as $month) {
                $this->operateMonth($houses, Carbon::createFromFormat('!Y-m', $month));
            }

            $this->extras($houses, $realNow);
        } finally {
            Carbon::setTestNow();
            Auth::logout();
        }

        $this->command?->info('Data demo dibuat: '.House::count().' rumah, '.Invoice::count().' tagihan. Login: bendahara@ / ketua@ / petugas.air@ / petugas.sampah@ / warga@example.com (password: password).');
    }

    /**
     * @return array<string, House>
     */
    private function houses(): array
    {
        $houseService = app(HouseService::class);
        $residency = app(ResidencyService::class);
        $blockJ = $houseService->createBlock(['code' => 'J', 'name' => 'Blok J']);
        $blockC = $houseService->createBlock(['code' => 'C', 'name' => 'Blok C']);

        $empty = ['J-12', 'J-13', 'J-15', 'J-20', 'J-22', 'C-9'];
        $houses = [];
        $names = self::NAMES;

        foreach ([[$blockC, range(1, 10)], [$blockJ, range(12, 30)]] as [$block, $numbers]) {
            foreach ($numbers as $number) {
                $house = $houseService->createHouse(['block_id' => $block->id, 'house_number' => (string) $number, 'house_type' => '36/72']);
                $houses[$house->house_code] = $house;

                if (! in_array($house->house_code, $empty, true) && $house->house_code !== 'J-16') {
                    $name = array_shift($names) ?? 'Warga '.$house->house_code;
                    $resident = $residency->createResident([
                        'full_name' => $name,
                        'phone' => '0812'.str_pad((string) (10000000 + crc32($name) % 89999999), 8, '0', STR_PAD_LEFT),
                    ]);
                    $residency->moveIn($house, $resident, ['start_date' => '2023-0'.random_int(1, 9).'-01', 'relationship_type' => random_int(1, 5) === 1 ? 'tenant' : 'owner']);

                    if ($house->house_code === 'J-24') {
                        $residency->linkAccount($resident, $this->warga);
                        $residency->moveIn($house, $residency->createResident(['full_name' => 'Rina Arifin']), ['start_date' => '2023-03-01', 'relationship_type' => 'family']);
                    }
                }
            }
        }

        // Penghuni lama J-23 yang sudah pindah (riwayat).
        $old = $residency->createResident(['full_name' => 'Hadi Pranoto']);
        $residency->moveOut($residency->moveIn($houses['J-23'], $old, ['start_date' => '2021-01-01']), '2026-03-31', 'Pindah ke Bandung');

        return $houses;
    }

    /**
     * @param  array<string, House>  $houses
     */
    private function setup(array $houses): void
    {
        $assignments = app(OfficerAssignmentService::class);
        $blocks = [];
        foreach ($houses as $house) {
            $blocks[$house->block_id] = true;
        }
        foreach (array_keys($blocks) as $blockId) {
            $assignments->assign(['user_id' => $this->petugasAir->id, 'assignment_type' => AssignmentType::Water->value, 'block_id' => $blockId, 'assigned_from' => '2026-05-01']);
            $assignments->assign(['user_id' => $this->petugasSampah->id, 'assignment_type' => AssignmentType::Waste->value, 'block_id' => $blockId, 'assigned_from' => '2026-05-01']);
        }

        app(WaterTariffService::class)->addPeriod(['effective_from' => '2026-01-01', 'base_fee' => 5000, 'rate_per_unit' => 5000, 'notes' => 'Sesuai daftar tagihan paguyuban']);
        app(WasteService::class)->addFeePeriod('2026-01-01', 30000, 'Iuran sampah bulanan');

        $contributions = app(ContributionService::class);
        $contributions->createType(['name' => 'Iuran Perelek', 'code' => 'PERELEK', 'description' => 'Kas warga bulanan', 'contribution_category' => 'routine', 'applies_to' => 'all_houses', 'amount' => 5000, 'effective_from' => '2026-01-01']);
        $contributions->createType(['name' => 'Iuran HUT RI ke-81', 'code' => 'HUTRI81', 'description' => 'Kegiatan 17 Agustus', 'contribution_category' => 'incidental', 'applies_to' => 'all_houses', 'amount' => 25000, 'effective_from' => '2026-08-01']);

        BillingExemption::create(['house_id' => $houses['J-16']->id, 'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31', 'reason' => 'BM — belum menempati s/d Des 2026']);

        $water = app(WaterCustomerService::class);
        $waste = app(WasteService::class);
        foreach ($houses as $code => $house) {
            if (! in_array($code, ['C-9', 'C-10'], true)) {
                $water->subscribe($house, ['start_date' => '2026-05-01', 'meter_number' => 'M-'.str_replace('-', '', $code), 'initial_reading' => random_int(0, 300)]);
            }
            if (! in_array($code, ['C-7', 'C-8', 'J-12', 'J-13', 'J-29'], true)) {
                $waste->subscribe($house, '2026-06-01');
            }
        }

        $cash = app(CashService::class);
        $cash->ensureDefaults()->update(['opening_balance' => '1000000.00', 'opening_date' => '2026-06-01']);
        CashAccount::create(['account_code' => 'REK', 'account_name' => 'Rekening Paguyuban', 'account_type' => 'bank', 'opening_balance' => '0.00', 'opening_date' => '2026-06-01', 'status' => 'active']);
    }

    /**
     * Satu bulan operasional: catat meter (awal bulan berikutnya), sampah
     * mingguan, terbitkan tagihan, terima pembayaran, catat kas.
     *
     * @param  array<string, House>  $houses
     */
    private function operateMonth(array $houses, Carbon $month): void
    {
        $next = $month->copy()->addMonthNoOverflow();
        $isLast = $month->format('Y-m') === '2026-10';
        $readings = app(MeterReadingService::class);
        $billing = app(BillingService::class);

        // Sampah: pengambilan tiap Sabtu.
        $this->at($month->copy()->endOfMonth()->setTime(17, 0)->toDateTimeString(), $this->petugasSampah);
        $subscribed = WasteSubscription::whereNull('end_date')->pluck('house_id')->all();
        for ($day = $month->copy()->startOfMonth(); $day->lte($month->copy()->endOfMonth()); $day->addDay()) {
            if ($day->isSaturday()) {
                $statuses = array_fill_keys($subscribed, 'completed');
                if ($day->day > 20) {
                    $statuses[$houses['C-4']->id] = 'missed';
                }
                app(WasteService::class)->recordCollection($day->toDateString(), $statuses, $this->petugasSampah);
            }
        }

        // Meter: dicatat tanggal 2–4 bulan berikutnya. Oktober sengaja belum
        // lengkap (Blok C) supaya alur Catat Meter bisa dicoba.
        $this->at($next->copy()->day(3)->setTime(8, 0)->toDateTimeString(), $this->petugasAir);
        foreach ($houses as $code => $house) {
            $meter = app(WaterCustomerService::class)->activeMeter($house);
            if (! $meter || ($isLast && str_starts_with($code, 'C-'))) {
                continue;
            }
            $baseline = app(WaterUsageService::class)->baseline($meter, $month);
            $usage = $code === 'J-16' ? 0 : random_int(0, 16);
            $value = (string) $baseline['value']->plus($usage);
            if ($isLast && $code === 'J-14') {
                $value = (string) $baseline['value']->minus(3); // angka turun → perlu diperiksa
            }
            $reading = $readings->record($house, $month, $value, $this->photo(), $this->petugasAir);

            // Lonjakan acak divalidasi Ketua RT; angka turun J-14 Oktober
            // sengaja dibiarkan menunggu pemeriksaan.
            if ($reading->status->value === 'pending' && ! ($isLast && $code === 'J-14')) {
                $readings->validate($reading, $this->ketua, null, 'Sudah dicek ke lokasi, pemakaian memang tinggi.');
            }
        }

        // Tagihan: sampah & iuran terbit akhir bulan, air awal bulan berikutnya.
        // Oktober: air BELUM dibuatkan tagihan (pembacaan Blok C belum
        // lengkap) — supaya alur Catat Meter → Buat draft → Terbitkan bisa dicoba.
        $this->at($next->copy()->day(5)->setTime(10, 0)->toDateTimeString(), $this->bendahara);
        $types = $isLast ? ['waste', 'contribution'] : ['water', 'waste', 'contribution'];
        $billing->generate($month, $types);
        $billing->issue($billing->period($month), $this->bendahara);

        // Pembayaran (tunai ke bendahara / transfer), sebagian menunggak.
        $payments = app(PaymentService::class);
        $bank = CashAccount::where('account_code', 'REK')->first();
        $chronic = ['J-21', 'C-3'];           // menunggak sejak Juni (≥ 3 bulan → putus sementara)
        $late = in_array($month->format('Y-m'), ['2026-09', '2026-10'], true) ? ['J-24', 'J-27', 'C-5', 'J-18', 'C-6'] : [];

        foreach ($houses as $code => $house) {
            if (in_array($code, $chronic, true) || in_array($code, $late, true)) {
                continue;
            }
            $open = $payments->payableInvoices($house);
            if ($open->isEmpty()) {
                continue;
            }

            $this->at($next->copy()->day(random_int(6, 9))->setTime(19, 0)->toDateTimeString(), $this->bendahara);
            if (crc32($code) % 3 === 0) {
                $payments->recordCash($house, $open->pluck('id')->all(), ['payment_date' => now()->toDateString(), 'notes' => 'Tunai, diparaf di kartu iuran'], $this->bendahara);
            } else {
                $payment = $payments->submit($house, $open->pluck('id')->all(), ['payment_date' => now()->toDateString(), 'external_reference' => 'TRF'.strtoupper(substr(md5($code.$month), 0, 8))], $this->photo('bukti.jpg'), $this->residentUser($house));
                $payments->verify($payment, $this->bendahara, null, $bank->id);
            }
        }

        // Setelah tagihan Oktober: beberapa transfer masih menunggu verifikasi.
        if ($isLast) {
            $this->at('2026-11-10 20:00', $this->warga);
            foreach (['J-27' => null, 'C-5' => null, 'J-18' => 'correction'] as $code => $mode) {
                $open = $payments->payableInvoices($houses[$code]);
                $payment = $payments->submit($houses[$code], $open->pluck('id')->all(), ['payment_date' => '2026-11-10', 'external_reference' => 'TRF-SEP-'.$code], $this->photo('bukti.jpg'), $this->residentUser($houses[$code]));
                if ($mode === 'correction') {
                    $this->at('2026-11-11 09:00', $this->bendahara);
                    $payments->requestCorrection($payment, $this->bendahara, 'Foto bukti terpotong, nominal tidak terbaca. Mohon unggah ulang.');
                }
            }
        }

        $this->cashBook($month);
    }

    private function cashBook(Carbon $month): void
    {
        $cash = app(CashService::class);
        $kas = CashAccount::where('account_code', 'KAS')->first();
        $cat = fn (string $code) => FinanceCategory::where('code', $code)->value('id');

        $entries = match ($month->format('Y-m')) {
            '2026-08' => [
                ['in', '2026-08-03', 'SUMBANGAN', 1000000, 'Sumbangan dari donatur untuk HUT RI', 'Donatur'],
                ['out', '2026-08-09', 'KEGIATAN', 8000, '1 tiner — hiasan gerbang 17 Agustus', 'Toko bangunan'],
                ['out', '2026-08-09', 'KEGIATAN', 5000, '1/4 kg paku', 'Toko bangunan'],
                ['out', '2026-08-09', 'KEGIATAN', 35000, '5 tambang', 'Toko bangunan'],
                ['out', '2026-08-09', 'KEGIATAN', 120000, '6 buah niru + 3 kuas', 'Pasar'],
                ['out', '2026-08-15', 'KEGIATAN', 1500000, 'Ke panitia Agustusan', 'Panitia HUT RI'],
                ['out', '2026-08-15', 'SOSIAL', 300000, 'Santunan yatim & warga yang sakit', null],
            ],
            '2026-09' => [
                ['out', '2026-09-06', 'PETUGAS', 400000, 'Honor petugas sampah September', 'Petugas sampah'],
                ['out', '2026-09-20', 'PEMELIHARAAN', 175000, 'Perbaikan pipa air dekat J-19', 'Tukang'],
            ],
            '2026-10' => [
                ['out', '2026-10-06', 'PETUGAS', 400000, 'Honor petugas sampah Oktober', 'Petugas sampah'],
                ['in', '2026-10-12', 'LAIN_MASUK', 150000, 'Sewa tenda paguyuban', 'Warga J-27'],
            ],
            default => [],
        };

        foreach ($entries as [$type, $date, $code, $amount, $description, $counterparty]) {
            $this->at($date.' 16:00', $this->bendahara);
            $data = ['cash_account_id' => $kas->id, 'finance_category_id' => $cat($code), 'transaction_date' => $date, 'amount' => $amount, 'description' => $description, 'counterparty' => $counterparty];
            if ($type === 'in') {
                $cash->recordIncome($data, $this->bendahara);
            } else {
                $tx = $cash->recordExpense($data, $this->bendahara);
                $this->at($date.' 20:00', $this->ketua);
                $cash->approve($tx, $this->ketua);
            }
        }
    }

    /**
     * @param  array<string, House>  $houses
     */
    private function extras(array $houses, Carbon $realNow): void
    {
        $community = app(CommunityService::class);

        $this->at('2026-11-01 08:00', $this->ketua);
        $community->saveAnnouncement(['title' => 'Kerja bakti bersih saluran air', 'content' => "Minggu, 23 November 2026 pukul 07.00.\nBerkumpul di pos ronda Blok J. Mohon membawa cangkul dan karung. Silih asih, silih asah, silih asuh!", 'publish_at' => '2026-11-01 08:00', 'status' => 'published']);
        $community->saveAnnouncement(['title' => 'Jadwal pengambilan sampah', 'content' => 'Sampah diambil setiap hari Sabtu pagi. Bila sampah belum diambil, laporkan lewat menu Pengaduan → "Sampah belum diambil".', 'publish_at' => '2026-10-15 08:00', 'status' => 'published']);
        $community->saveAnnouncement(['title' => 'Pembayaran iuran via transfer', 'content' => "Iuran air & kas warga dapat dibayar tunai ke bendahara atau transfer ke rekening paguyuban, lalu unggah bukti di menu Tagihan Saya.\nTunggakan 3 bulan berturut-turut: saluran air diputus sementara.", 'publish_at' => '2026-09-01 08:00', 'status' => 'published']);

        $this->at('2026-11-08 10:00', $this->warga);
        $complaint = $community->submitComplaint($this->warga, $houses['J-24'], ComplaintCategory::WasteMissed, 'Sampah di depan J-24 belum diambil sejak Sabtu kemarin.');
        $this->at('2026-11-09 09:00', $this->ketua);
        $community->updateComplaint($complaint, ComplaintStatus::InProgress, 'Sudah diteruskan ke petugas sampah, diambil hari ini.', $this->ketua, $this->petugasSampah->id);
        $this->at('2026-11-12 19:00', $this->warga);
        $community->submitComplaint($this->warga, $houses['J-24'], ComplaintCategory::Facility, 'Lampu jalan di depan J-25 mati sejak 2 hari.');

        // Pengeluaran menunggu persetujuan Ketua RT.
        $this->at('2026-11-13 15:00', $this->bendahara);
        app(CashService::class)->recordExpense([
            'cash_account_id' => CashAccount::where('account_code', 'KAS')->value('id'),
            'finance_category_id' => FinanceCategory::where('code', 'PENGADAAN')->value('id'),
            'transaction_date' => '2026-11-13', 'amount' => 250000, 'description' => 'Lampu jalan LED 2 buah', 'counterparty' => 'Toko listrik',
        ], $this->bendahara);

        // Tandai yang lewat jatuh tempo per "hari ini".
        Carbon::setTestNow($realNow->lt(Carbon::parse('2026-11-14')) ? Carbon::parse('2026-11-14 08:00') : $realNow);
        app(BillingService::class)->markOverdue();
    }

    /**
     * Akun warga untuk kontak utama rumah (dibuat sekali, password "password").
     */
    private function residentUser(House $house): User
    {
        $resident = $house->fresh()->primaryResident();
        if (! $resident) {
            return $this->bendahara;
        }

        $existing = $resident->users()->first();
        if ($existing) {
            return $existing;
        }

        $user = User::create([
            'name' => $resident->full_name,
            'email' => 'warga.'.strtolower(str_replace('-', '', $house->house_code)).'@example.com',
            'phone' => $resident->phone,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->assignRole(CommunityRole::Warga->value);
        $resident->users()->attach($user->id);

        return $user;
    }

    private function at(string $datetime, User $user): void
    {
        Carbon::setTestNow(Carbon::parse($datetime));
        Auth::setUser($user);
    }

    private function photo(string $name = 'meter.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 480, 360);
    }

    private function demo(CommunityRole $role): User
    {
        return User::where('email', $role->demoEmail())->firstOrFail();
    }
}
