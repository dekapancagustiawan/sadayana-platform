<?php

namespace App\Services\Reporting;

use App\Enums\Billing\InvoiceStatus;
use App\Enums\Finance\CashTransactionStatus;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Resident\AssignmentType;
use App\Enums\Water\MeterReadingStatus;
use App\Models\Announcement;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Complaint;
use App\Models\House;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterMeterReading;
use App\Services\Finance\CashService;
use App\Services\Payment\PaymentService;
use App\Services\Resident\HouseAccessService;
use App\Services\Water\MeterReadingService;
use App\Support\Money;

/**
 * Ringkasan dashboard per hak akses (FR-RPT-001, BRD §8 Management
 * Dashboard). Setiap blok hanya dihitung bila user memegang izinnya.
 */
class DashboardWidgetService
{
    public function __construct(
        private readonly CashService $cash,
        private readonly PaymentService $payments,
        private readonly HouseAccessService $access,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $widgets = [];

        if ($user->can('houses.view')) {
            $counts = House::selectRaw('occupancy_status, COUNT(*) as total')->groupBy('occupancy_status')->pluck('total', 'occupancy_status');
            $widgets['community'] = [
                'houses' => $counts->sum(),
                'occupied' => $counts['occupied'] ?? 0,
                'vacant' => $counts['vacant'] ?? 0,
            ];
        }

        if ($user->can('invoices.view')) {
            $open = Invoice::whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Overdue, InvoiceStatus::PartiallyPaid]);
            $widgets['billing'] = [
                'outstanding' => Money::of((clone $open)->sum('outstanding_amount')),
                'overdue_houses' => Invoice::where('status', InvoiceStatus::Overdue)->distinct('house_id')->count('house_id'),
                'drafts' => Invoice::where('status', InvoiceStatus::Draft)->count(),
                'paid_this_month' => Money::of(Payment::where('status', PaymentStatus::Verified)->whereBetween('payment_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('amount')),
            ];
        }

        if ($user->can('payments.view')) {
            $widgets['pending_payments'] = Payment::where('status', PaymentStatus::PendingVerification)->count();
        }

        if ($user->can('cash.view')) {
            $this->cash->ensureDefaults();
            $widgets['cash'] = [
                'balance' => Money::sum(CashAccount::where('status', 'active')->get()->map(fn ($a) => $this->cash->balance($a))),
                'pending_expenses' => CashTransaction::where('status', CashTransactionStatus::PendingApproval)->count(),
            ];
        }

        if ($user->can('meter-readings.view')) {
            $widgets['pending_readings'] = WaterMeterReading::whereHas('house')->where('status', MeterReadingStatus::Pending)->count();
        }

        if ($user->can('complaints.manage')) {
            $widgets['open_complaints'] = Complaint::whereIn('status', ['submitted', 'in_progress'])->count();
        }

        if ($user->can('meter-readings.record')) {
            $period = MeterReadingService::defaultPeriod();
            $houseIds = $this->access->officerHouseIds($user, AssignmentType::Water);
            $widgets['recording'] = [
                'period' => $period,
                'total' => $houseIds->count(),
                'done' => WaterMeterReading::whereIn('house_id', $houseIds)->whereDate('reading_period', $period->toDateString())->where('status', '!=', 'rejected')->count(),
            ];
        }

        if ($user->can('access-my-bills') && ! $user->can('invoices.view')) {
            $houses = $this->access->housesFor($user);
            $widgets['resident'] = $houses->map(fn (House $house) => [
                'house' => $house,
                'outstanding' => Money::sum($this->payments->payableInvoices($house)->pluck('outstanding_amount')),
                'pending' => Payment::where('house_id', $house->id)->where('status', PaymentStatus::PendingVerification)->count(),
            ]);
        }

        if ($user->can('access-announcements')) {
            $widgets['announcements'] = Announcement::visible()->latest('publish_at')->limit(3)->get();
        }

        $widgets['unread'] = $user->unreadNotifications()->latest()->limit(5)->get();

        return $widgets;
    }
}
