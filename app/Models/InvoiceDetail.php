<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rincian invoice + snapshot perhitungan (FR-BIL-002, BR-006).
 */
class InvoiceDetail extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['invoice_id', 'component_type', 'source_type', 'source_id', 'description', 'quantity', 'unit_price', 'subtotal', 'calculation_snapshot'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'calculation_snapshot' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
