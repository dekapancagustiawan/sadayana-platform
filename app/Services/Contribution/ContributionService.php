<?php

namespace App\Services\Contribution;

use App\Models\ContributionAssignment;
use App\Models\ContributionPeriod;
use App\Models\ContributionType;
use App\Models\House;
use App\Models\Invoice;
use App\Services\Audit\AuditService;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Iuran warga yang dapat dikonfigurasi (FR-COM-001..003, Dok 05 §11.2).
 *
 * - routine    : ditagih setiap bulan selama periode nominal berlaku.
 * - incidental : ditagih SEKALI pada bulan `effective_from` periodenya.
 * - applies_to : all_houses (semua rumah aktif) atau assigned (rumah tertentu,
 *                boleh dengan nominal khusus).
 */
class ContributionService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{name: string, code?: ?string, description?: ?string, contribution_category: string, applies_to: string, amount: string|int, effective_from: string}  $data
     */
    public function createType(array $data): ContributionType
    {
        return DB::transaction(function () use ($data) {
            $type = ContributionType::create([
                'code' => strtoupper($data['code'] ?? '') ?: strtoupper(Str::slug($data['name'], '_')),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'contribution_category' => $data['contribution_category'],
                'applies_to' => $data['applies_to'],
                'status' => 'active',
            ]);
            $this->audit->created('contributions.type_created', $type);

            $this->addPeriod($type, $data['effective_from'], $data['amount']);

            return $type;
        });
    }

    public function addPeriod(ContributionType $type, string $effectiveFrom, string|int $amount, ?string $notes = null): ContributionPeriod
    {
        $from = Carbon::parse($effectiveFrom)->startOfMonth();

        return DB::transaction(function () use ($type, $from, $amount, $notes) {
            $latest = $type->periods()->lockForUpdate()->first();

            if ($latest && $from->lte($latest->effective_from)) {
                throw new RuntimeException('Tanggal berlaku harus setelah '.$latest->effective_from->format('d/m/Y').'.');
            }

            $billed = Invoice::where('contribution_type_id', $type->getKey())
                ->where('status', '!=', 'cancelled')
                ->whereHas('period', fn ($q) => $q->whereDate('period_start', '>=', $from->toDateString()))
                ->exists();
            if ($billed) {
                throw new RuntimeException("Sudah ada tagihan {$type->name} untuk ".$from->translatedFormat('F Y').' atau sesudahnya.');
            }

            if ($latest && $type->contribution_category === 'routine') {
                $latest->update(['effective_until' => $from->copy()->subDay()->toDateString()]);
            }

            $period = ContributionPeriod::create([
                'contribution_type_id' => $type->getKey(),
                'effective_from' => $from->toDateString(),
                'effective_until' => $type->contribution_category === 'incidental' ? $from->copy()->endOfMonth()->toDateString() : null,
                'amount' => Money::toDb($amount),
                'notes' => $notes,
            ]);
            $this->audit->created('contributions.period_added', $period);

            return $period;
        });
    }

    public function setStatus(ContributionType $type, string $status): void
    {
        $type->status = $status;
        $this->audit->changed('contributions.status_changed', $type);
        $type->save();
    }

    /**
     * Periode nominal yang ditagihkan pada bulan tertentu (null = tidak ditagih).
     */
    public function periodFor(ContributionType $type, CarbonInterface $month): ?ContributionPeriod
    {
        $start = $month->copy()->startOfMonth()->toDateString();

        if ($type->contribution_category === 'incidental') {
            return $type->periods()->whereDate('effective_from', $start)->first();
        }

        return ContributionPeriod::query()
            ->where('contribution_type_id', $type->getKey())
            ->whereDate('effective_from', '<=', $start)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $start))
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * Rumah yang wajib membayar iuran ini pada bulan tertentu beserta
     * nominalnya.
     *
     * @return Collection<string, BigDecimal> house_id => nominal
     */
    public function chargesFor(ContributionType $type, CarbonInterface $month): Collection
    {
        $period = $this->periodFor($type, $month);
        if (! $period || $type->status !== 'active') {
            return collect();
        }

        $amount = Money::of($period->amount);

        if ($type->applies_to === 'all_houses') {
            return House::where('is_active', true)->pluck('id')->mapWithKeys(fn ($id) => [$id => $amount]);
        }

        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        return ContributionAssignment::query()
            ->where('contribution_type_id', $type->getKey())
            ->whereDate('assigned_from', '<=', $end)
            ->where(fn ($q) => $q->whereNull('assigned_until')->orWhereDate('assigned_until', '>=', $start))
            ->whereHas('house', fn ($q) => $q->where('is_active', true))
            ->get()
            ->mapWithKeys(fn (ContributionAssignment $a) => [$a->house_id => $a->custom_amount !== null ? Money::of($a->custom_amount) : $amount]);
    }

    /**
     * @param  list<string>  $houseIds
     */
    public function assign(ContributionType $type, array $houseIds, string $from, string|int|null $customAmount = null): int
    {
        if ($type->applies_to !== 'assigned') {
            throw new RuntimeException('Iuran ini berlaku untuk semua rumah; penetapan per rumah tidak diperlukan.');
        }

        return DB::transaction(function () use ($type, $houseIds, $from, $customAmount) {
            foreach ($houseIds as $houseId) {
                ContributionAssignment::create([
                    'contribution_type_id' => $type->getKey(),
                    'house_id' => $houseId,
                    'assigned_from' => $from,
                    'custom_amount' => $customAmount !== null && $customAmount !== '' ? Money::toDb($customAmount) : null,
                ]);
            }
            $this->audit->record('contributions.assigned', $type, ['houses' => count($houseIds)]);

            return count($houseIds);
        });
    }
}
