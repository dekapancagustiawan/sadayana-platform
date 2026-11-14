<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Services\Finance\CashService;
use App\Services\Reporting\ReportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan pengurus (FR-RPT-002/003, BRD §8). Setiap laporan bisa dicetak
 * (?print=1) atau diunduh CSV (?format=csv) — BC-7: tanpa bantuan developer.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly CashService $cash,
    ) {}

    public function index(): View
    {
        return view('reports.index');
    }

    public function billing(Request $request): View|StreamedResponse
    {
        $month = $this->month($request, now()->subMonthNoOverflow());
        $data = $this->reports->billingList($month);

        if ($request->input('format') === 'csv') {
            $header = ['Rumah', 'Nama', 'Meter awal', 'Meter akhir', 'm3', 'Air', 'Abodemen', 'Sampah'];
            foreach ($data['contributionNames'] as $name) {
                $header[] = $name;
            }
            array_push($header, 'Tagihan bulan ini', 'Tunggakan sebelumnya', 'Jumlah', 'Sisa belum dibayar');

            return $this->csv("daftar-tagihan-{$month->format('Y-m')}.csv", $header, $data['rows']->map(function ($r) use ($data) {
                $row = [
                    $r['house']->house_code, $r['name'],
                    $r['usage']?->previous_value, $r['usage']?->current_value, $r['usage']?->usage_volume,
                    $r['water_usage_amount'] ? (string) $r['water_usage_amount'] : '', $r['water_base_fee'] ? (string) $r['water_base_fee'] : '', $r['waste'] ? (string) $r['waste'] : '',
                ];
                foreach ($data['contributionNames']->keys() as $id) {
                    $row[] = $r['contributions'][$id] ?? '';
                }
                array_push($row, (string) $r['current'], (string) $r['arrears'], (string) $r['grand'], (string) $r['outstanding']);

                return $row;
            }));
        }

        return view('reports.billing', ['month' => $month, ...$data, 'print' => $request->boolean('print')]);
    }

    public function arrears(Request $request): View|StreamedResponse
    {
        $rows = $this->reports->arrears();

        if ($request->input('format') === 'csv') {
            return $this->csv('tunggakan-'.now()->format('Y-m-d').'.csv', ['Rumah', 'Nama', 'Jumlah tagihan', 'Bulan lewat jatuh tempo', 'Sejak', 'Total tunggakan'], $rows->map(fn ($r) => [
                $r['house']->house_code, $r['name'], $r['invoices'], $r['overdue_months'], $r['oldest']?->format('Y-m'), (string) $r['total'],
            ]));
        }

        return view('reports.arrears', ['rows' => $rows, 'print' => $request->boolean('print'), 'threshold' => config('billing.disconnect_after_months')]);
    }

    public function cashBook(Request $request): View|StreamedResponse
    {
        $account = CashAccount::find($request->input('account')) ?? $this->cash->ensureDefaults();
        $month = $this->month($request, now());
        $book = $this->reports->cashBook($account, $month);

        if ($request->input('format') === 'csv') {
            $rows = collect([['', $month->format('Y-m-01'), 'Saldo awal', '', '', (string) $book['opening']]])
                ->concat($book['rows']->values()->map(fn ($r, $i) => [
                    $i + 1, $r['tx']->transaction_date->format('Y-m-d'), $r['tx']->description.' ('.$r['tx']->category->name.')',
                    $r['tx']->transaction_type === 'in' ? (string) Money::of($r['tx']->amount) : '',
                    $r['tx']->transaction_type === 'out' ? (string) Money::of($r['tx']->amount) : '',
                    (string) $r['balance'],
                ]))
                ->push(['', '', 'Jumlah', (string) $book['in'], (string) $book['out'], (string) $book['closing']]);

            return $this->csv("buku-kas-{$account->account_code}-{$month->format('Y-m')}.csv", ['No', 'Tanggal', 'Keterangan', 'Debet', 'Kredit', 'Saldo'], $rows);
        }

        return view('reports.cash-book', [
            'account' => $account,
            'accounts' => CashAccount::orderBy('account_name')->get(),
            'month' => $month,
            'book' => $book,
            'print' => $request->boolean('print'),
        ]);
    }

    public function recap(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);

        return view('reports.recap', ['year' => $year, ...$this->reports->yearlyRecap($year)]);
    }

    private function month(Request $request, Carbon $default): Carbon
    {
        $input = (string) $request->input('month');

        return preg_match('/^\d{4}-\d{2}$/', $input) ? Carbon::createFromFormat('!Y-m', $input) : $default->copy()->startOfMonth();
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<array<int, mixed>>  $rows
     */
    private function csv(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => $v === null ? '' : (string) $v, $row), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
