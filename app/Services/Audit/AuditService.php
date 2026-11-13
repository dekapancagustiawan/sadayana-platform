<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * Pencatatan audit untuk proses domain (FR-AUD-001, NFR-008).
 *
 * Format `payload` untuk perubahan data: `{"old": {...}, "new": {...},
 * "reason": "...", ...konteks}`. Atribut `$hidden` model dan kolom teknis
 * (timestamp, audit fields) tidak ikut dicatat.
 *
 * Panggil DI DALAM `DB::transaction` yang sama dengan perubahan datanya,
 * supaya audit ikut di-rollback bila transaksi gagal.
 */
class AuditService
{
    /** @var list<string> */
    private const IGNORED_ATTRIBUTES = [
        'created_at', 'updated_at', 'deleted_at',
        'created_by', 'updated_by', 'deleted_by',
        'remember_token', 'password',
    ];

    /**
     * Catat aksi apa pun, opsional terkait satu model.
     *
     * @param  array<string, mixed>  $context
     */
    public function record(string $action, ?Model $model = null, array $context = []): AuditLog
    {
        return AuditLog::record(
            action: $action,
            userId: Auth::id(),
            payload: $context === [] ? null : $context,
            modelType: $model?->getMorphClass(),
            modelId: $model?->getKey(),
        );
    }

    /**
     * Catat model yang baru dibuat beserta nilainya.
     *
     * @param  array<string, mixed>  $context
     */
    public function created(string $action, Model $model, array $context = []): AuditLog
    {
        return $this->record($action, $model, [
            'new' => $this->snapshot($model, $model->getAttributes()),
            ...$context,
        ]);
    }

    /**
     * Catat perubahan model. Panggil SEBELUM `save()` (memakai dirty
     * attributes) atau berikan `$old` secara eksplisit sesudahnya.
     *
     * @param  array<string, mixed>|null  $old  nilai lama; null = ambil dari getOriginal() untuk atribut dirty
     * @param  array<string, mixed>  $context
     */
    public function changed(string $action, Model $model, ?string $reason = null, ?array $old = null, array $context = []): AuditLog
    {
        if ($old === null) {
            $dirty = $model->getDirty();
            $old = Arr::only($model->getOriginal(), array_keys($dirty));
            $new = $dirty;
        } else {
            $new = Arr::only($model->getAttributes(), array_keys($old));
        }

        return $this->record($action, $model, array_filter([
            'old' => $this->snapshot($model, $old),
            'new' => $this->snapshot($model, $new),
            'reason' => $reason,
            ...$context,
        ], fn ($value) => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function snapshot(Model $model, array $attributes): array
    {
        $values = Arr::except($attributes, [...self::IGNORED_ATTRIBUTES, ...$model->getHidden()]);

        return array_map(
            fn ($value) => $value instanceof \BackedEnum ? $value->value : $value,
            $values,
        );
    }
}
