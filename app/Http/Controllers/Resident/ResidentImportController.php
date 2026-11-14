<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Services\Resident\ResidentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import rumah & penghuni dari CSV (izin residents.import, di route).
 */
class ResidentImportController extends Controller
{
    public function __construct(private readonly ResidentImportService $importer) {}

    public function create(): View
    {
        return view('resident.import.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [], ['file' => 'file CSV']);

        $file = $request->file('file');
        $parsed = $this->importer->parse($file->getRealPath());

        if ($parsed['errors'] !== []) {
            return back()->with('import_errors', array_slice($parsed['errors'], 0, 100))
                ->with('error', 'File belum diimport: perbaiki '.count($parsed['errors']).' kesalahan berikut lalu unggah ulang.');
        }

        $summary = $this->importer->import($parsed['rows'], $file->getClientOriginalName());

        return redirect()->route('houses.index')->with('success', sprintf(
            'Import selesai: %d blok baru, %d rumah baru, %d penghuni baru, %d hunian dicatat, %d baris dilewati (sudah tercatat).',
            $summary['blocks'], $summary['houses'], $summary['residents'], $summary['occupancies'], $summary['skipped'],
        ));
    }

    /**
     * Contoh file CSV (pemisah titik koma — langsung terbuka rapi di Excel).
     */
    public function template(): StreamedResponse
    {
        $rows = [
            ['blok', 'nomor', 'nama', 'no_hp', 'email', 'hubungan', 'tanggal_mulai', 'status_hunian', 'catatan'],
            ['J', '12', 'Budi Santoso', '081234567890', '', 'pemilik', '01/01/2024', '', ''],
            ['J', '13', 'Siti Aminah', '081298765432', 'siti@example.com', 'penyewa', '15/03/2025', '', ''],
            ['J', '14', '', '', '', '', '', 'kosong', 'Belum ada data penghuni'],
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, 'template-import-rumah-penghuni.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
