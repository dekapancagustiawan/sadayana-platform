<?php

use App\Enums\Billing\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Communication\NotificationService;
use App\Support\CurrentCommunity;
use App\Support\Money;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hapus token API yang sudah kedaluwarsa lebih dari 24 jam (lihat sanctum.expiration).
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Data demo: hapus SEMUA data domain (rumah s.d. kas) lalu isi ulang
// DemoDataSeeder. Settings, role, dan akun demo dipertahankan. Hanya untuk
// lingkungan non-production.
Artisan::command('sadayana:demo-reset {--force : Lewati konfirmasi}', function () {
    if (app()->isProduction()) {
        $this->error('Tidak boleh dijalankan di production.');

        return 1;
    }
    if (! $this->option('force') && ! $this->confirm('Hapus semua data rumah, air, tagihan, pembayaran, dan kas lalu isi ulang data demo?')) {
        return 0;
    }

    $db = DB::connection();
    $db->table('cash_transactions')->update(['reversal_of_id' => null]);
    foreach ([
        'notifications', 'payment_verifications', 'payment_allocations', 'cash_transactions', 'payments', 'invoice_details', 'invoices',
        'billing_exemptions', 'billing_periods', 'cash_accounts', 'finance_categories', 'complaints', 'announcements',
        'waste_service_records', 'waste_service_subscriptions', 'waste_fee_periods', 'contribution_assignments', 'contribution_periods',
        'contribution_types', 'water_usage_records', 'water_reading_corrections', 'water_meter_readings', 'water_meters', 'water_customers',
        'water_tariff_periods', 'water_tariffs', 'officer_assignments', 'resident_user_accounts', 'house_residents', 'residents', 'houses',
        'blocks', 'files', 'code_sequences',
    ] as $table) {
        $db->table($table)->delete();
    }
    $db->table('audit_logs')->where(fn ($q) => $q->whereNotNull('model_type')->where('model_type', 'not like', '%User%'))->delete();
    User::where('email', 'like', 'warga.%@example.com')->get()->each->delete();
    Storage::disk(config('uploads.disk'))->deleteDirectory('meter_photo');
    Storage::disk(config('uploads.disk'))->deleteDirectory('payment_proof');

    $this->call('db:seed', ['--class' => 'CommunityRoleSeeder', '--force' => true]);
    $this->call('db:seed', ['--class' => 'DemoDataSeeder', '--force' => true]);

    return 0;
})->purpose('Isi ulang data demo De Java Valley (non-production)');

// Fase 10 / NFR-003: backup database PostgreSQL (pg_dump, format custom)
// ke storage/app/private/backups, simpan N file terakhir. Restore:
//   pg_restore --clean --if-exists -d <db> storage/app/private/backups/<file>.dump
Artisan::command('sadayana:backup {--keep=14 : Jumlah file backup yang disimpan}', function () {
    $connection = config('database.connections.'.config('database.default'));
    if (($connection['driver'] ?? null) !== 'pgsql') {
        $this->error('Backup otomatis hanya untuk PostgreSQL.');

        return 1;
    }

    $dir = storage_path('app/private/backups');
    File::ensureDirectoryExists($dir);
    $file = $dir.'/'.$connection['database'].'-'.now()->format('Ymd-His').'.dump';
    $binary = env('PG_DUMP_PATH', 'pg_dump');

    $result = Process::env(['PGPASSWORD' => (string) $connection['password']])
        ->timeout(600)
        ->run([$binary, '-h', $connection['host'], '-p', (string) $connection['port'], '-U', $connection['username'], '-F', 'c', '-f', $file, $connection['database']]);

    if (! $result->successful()) {
        $this->error('Backup gagal: '.trim($result->errorOutput()));
        Log::error('sadayana:backup gagal', ['error' => $result->errorOutput()]);

        return 1;
    }

    collect(File::glob($dir.'/*.dump'))->sort()->reverse()->slice((int) $this->option('keep'))->each(fn ($old) => File::delete($old));
    $this->info('Backup tersimpan: '.$file.' ('.round(filesize($file) / 1024).' KB)');

    return 0;
})->purpose('Backup database PostgreSQL (NFR-003)');
Schedule::command('sadayana:backup')->dailyAt('02:00');

// Fase 5: tandai invoice lewat jatuh tempo (FR-BIL-004).
Artisan::command('invoices:mark-overdue', function (BillingService $billing) {
    app(CurrentCommunity::class)->get();
    $this->info($billing->markOverdue().' tagihan ditandai lewat jatuh tempo.');
})->purpose('Tandai tagihan yang lewat jatuh tempo');
Schedule::command('invoices:mark-overdue')->dailyAt('00:30');

// Fase 8: pengingat jatuh tempo ke warga (FR-NOT-001).
Artisan::command('invoices:send-reminders', function (NotificationService $notifications) {
    $date = today()->addDays(config('billing.reminder_days_before'))->toDateString();
    $sent = 0;

    Invoice::with('house')
        ->where('status', InvoiceStatus::Issued)
        ->whereDate('due_date', $date)
        ->get()
        ->groupBy('house_id')
        ->each(function ($invoices) use ($notifications, &$sent) {
            $house = $invoices->first()->house;
            $notifications->notifyHouse(
                $house,
                'Pengingat jatuh tempo',
                $invoices->count().' tagihan '.$house->house_code.' sebesar '.Money::format(Money::sum($invoices->pluck('outstanding_amount'))).' jatuh tempo '.$invoices->first()->due_date->translatedFormat('d F Y').'.',
                route('my-bills.index'),
                'fas fa-clock',
                'invoice_reminder',
            );
            $sent++;
        });

    $this->info("Pengingat dikirim ke {$sent} rumah.");
})->purpose('Kirim pengingat tagihan yang segera jatuh tempo');
Schedule::command('invoices:send-reminders')->dailyAt('08:00');
