<?php

namespace App\Services\Water;

use App\Enums\Water\MeterReadingStatus;
use App\Enums\Water\WaterCustomerStatus;
use App\Enums\Water\WaterMeterStatus;
use App\Models\House;
use App\Models\WaterCustomer;
use App\Models\WaterMeter;
use App\Models\WaterMeterReading;
use App\Services\Audit\AuditService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Langganan air & meteran per rumah (FR-WAT-001, D-02, S-3).
 */
class WaterCustomerService
{
    public function __construct(private readonly AuditService $audit) {}

    public function openCustomer(House $house): ?WaterCustomer
    {
        return WaterCustomer::where('house_id', $house->getKey())->open()->first();
    }

    public function activeMeter(House $house): ?WaterMeter
    {
        return WaterMeter::where('house_id', $house->getKey())->where('status', WaterMeterStatus::Active)->first();
    }

    /**
     * Daftarkan rumah sebagai pelanggan air. Meter dipasang sekalian bila
     * rumah belum punya meter aktif.
     *
     * @param  array{start_date: string, meter_number?: ?string, initial_reading?: string|int|null, notes?: ?string}  $data
     */
    public function subscribe(House $house, array $data): WaterCustomer
    {
        return DB::transaction(function () use ($house, $data) {
            House::whereKey($house->getKey())->lockForUpdate()->first();

            if ($this->openCustomer($house)) {
                throw new RuntimeException("Rumah {$house->house_code} sudah terdaftar sebagai pelanggan air.");
            }

            $customer = WaterCustomer::create([
                'house_id' => $house->getKey(),
                'customer_number' => $this->nextCustomerNumber($house),
                'status' => WaterCustomerStatus::Active,
                'start_date' => $data['start_date'],
                'notes' => $data['notes'] ?? null,
            ]);
            $this->audit->created('water_customers.subscribed', $customer, ['house_code' => $house->house_code]);

            if (! $this->activeMeter($house)) {
                $this->installMeter($house, [
                    'meter_number' => $data['meter_number'] ?? null,
                    'installed_at' => $data['start_date'],
                    'initial_reading' => $data['initial_reading'] ?? 0,
                ]);
            }

            return $customer;
        });
    }

    /**
     * Berhenti berlangganan. Riwayat pemakaian tetap ada.
     */
    public function unsubscribe(WaterCustomer $customer, string $endDate, ?string $reason = null): WaterCustomer
    {
        if ($customer->end_date !== null) {
            throw new RuntimeException('Langganan ini sudah berakhir.');
        }

        if (Carbon::parse($endDate)->lt($customer->start_date)) {
            throw new RuntimeException('Tanggal berhenti tidak boleh sebelum tanggal mulai langganan.');
        }

        return DB::transaction(function () use ($customer, $endDate, $reason) {
            $customer->fill(['end_date' => $endDate, 'status' => WaterCustomerStatus::Inactive]);
            $this->audit->changed('water_customers.unsubscribed', $customer, $reason);
            $customer->save();

            return $customer;
        });
    }

    /**
     * Putus sementara / sambung kembali (mis. tunggakan 3 bulan, S-7).
     */
    public function setSuspended(WaterCustomer $customer, bool $suspended, ?string $reason = null): WaterCustomer
    {
        if ($customer->end_date !== null) {
            throw new RuntimeException('Langganan ini sudah berakhir.');
        }

        return DB::transaction(function () use ($customer, $suspended, $reason) {
            $customer->status = $suspended ? WaterCustomerStatus::Suspended : WaterCustomerStatus::Active;
            $this->audit->changed($suspended ? 'water_customers.suspended' : 'water_customers.resumed', $customer, $reason);
            $customer->save();

            return $customer;
        });
    }

    /**
     * @param  array{meter_number?: ?string, installed_at: string, initial_reading?: string|int|null, carried_volume?: string|null}  $data
     */
    public function installMeter(House $house, array $data): WaterMeter
    {
        $meter = WaterMeter::create([
            'house_id' => $house->getKey(),
            'meter_number' => $data['meter_number'] ?? null,
            'installed_at' => $data['installed_at'],
            'initial_reading' => (string) Money::quantity($data['initial_reading'] ?? 0),
            'carried_volume' => (string) Money::quantity($data['carried_volume'] ?? 0),
            'status' => WaterMeterStatus::Active,
        ]);
        $this->audit->created('water_meters.installed', $meter, ['house_code' => $house->house_code]);

        return $meter;
    }

    /**
     * Ganti meter (rusak/diganti). Sisa pemakaian meter lama sejak
     * pembacaan terakhir (final_reading − pembacaan terakhir) dibawa ke meter
     * baru supaya tetap tertagih (D-02).
     *
     * @param  array{final_reading: string|int, meter_number?: ?string, initial_reading?: string|int|null, replaced_at: string, old_status?: string, notes?: ?string}  $data
     */
    public function replaceMeter(House $house, array $data): WaterMeter
    {
        return DB::transaction(function () use ($house, $data) {
            $old = WaterMeter::where('house_id', $house->getKey())
                ->where('status', WaterMeterStatus::Active)
                ->lockForUpdate()
                ->first();

            if (! $old) {
                throw new RuntimeException("Rumah {$house->house_code} belum punya meter aktif.");
            }

            if (WaterMeterReading::where('water_meter_id', $old->getKey())->where('status', MeterReadingStatus::Pending)->exists()) {
                throw new RuntimeException('Masih ada pembacaan meter lama yang menunggu pemeriksaan. Selesaikan dulu sebelum mengganti meter.');
            }

            $last = WaterMeterReading::where('water_meter_id', $old->getKey())
                ->where('status', MeterReadingStatus::Validated)
                ->orderByDesc('reading_period')
                ->first();
            $lastValue = Money::quantity($last?->reading_value ?? $old->initial_reading);
            $final = Money::quantity($data['final_reading']);

            if ($final->isLessThan($lastValue)) {
                throw new RuntimeException("Angka akhir meter lama ({$final}) lebih kecil dari pembacaan terakhir ({$lastValue}).");
            }

            $carried = $final->minus($lastValue)->plus(Money::quantity($old->carried_volume ?? 0));
            // carried_volume meter lama hanya relevan bila belum pernah dibaca.
            if ($last !== null) {
                $carried = $final->minus($lastValue);
            }

            $oldBefore = $old->only(['status', 'removed_at', 'final_reading']);
            $old->fill([
                'status' => WaterMeterStatus::tryFrom($data['old_status'] ?? '') ?? WaterMeterStatus::Replaced,
                'removed_at' => $data['replaced_at'],
                'final_reading' => (string) $final,
                'notes' => $data['notes'] ?? $old->notes,
            ]);
            $this->audit->changed('water_meters.replaced', $old, $data['notes'] ?? null, $oldBefore);
            $old->save();

            return $this->installMeter($house, [
                'meter_number' => $data['meter_number'] ?? null,
                'installed_at' => $data['replaced_at'],
                'initial_reading' => $data['initial_reading'] ?? 0,
                'carried_volume' => (string) $carried,
            ]);
        });
    }

    private function nextCustomerNumber(House $house): string
    {
        $base = config('water.customer_prefix').'-'.$house->house_code;
        $number = $base;
        $suffix = 2;

        while (WaterCustomer::where('customer_number', $number)->exists()) {
            $number = $base.'-'.$suffix++;
        }

        return $number;
    }
}
