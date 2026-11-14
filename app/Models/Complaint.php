<?php

namespace App\Models;

use App\Enums\Communication\ComplaintCategory;
use App\Enums\Communication\ComplaintStatus;
use App\Traits\BelongsToCommunity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengaduan warga, termasuk "sampah belum diambil" (FR-NOT-003, S-3).
 */
class Complaint extends Model
{
    use BelongsToCommunity, HasUuids;

    /** @var list<string> */
    protected $fillable = ['complaint_number', 'house_id', 'submitted_by', 'category', 'description', 'attachment_file_id', 'status', 'assigned_to', 'resolution_notes', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'category' => ComplaintCategory::class,
            'status' => ComplaintStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class)->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
