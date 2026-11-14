<?php

namespace App\Services\Resident;

use App\Models\OfficerAssignment;
use App\Services\Audit\AuditService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penugasan petugas lapangan / sampah ke blok atau rumah (D-05). Penugasan
 * tidak dihapus — diakhiri dengan `assigned_until` supaya riwayat siapa
 * mencatat apa tetap terbaca.
 */
class OfficerAssignmentService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{user_id: int, assignment_type: string, block_id?: ?string, house_id?: ?string, assigned_from: string, assigned_until?: ?string}  $data
     */
    public function assign(array $data): OfficerAssignment
    {
        if (blank($data['block_id'] ?? null) && blank($data['house_id'] ?? null)) {
            throw new RuntimeException('Pilih blok atau rumah yang ditugaskan.');
        }

        return DB::transaction(function () use ($data) {
            $duplicate = OfficerAssignment::query()
                ->where('user_id', $data['user_id'])
                ->where('assignment_type', $data['assignment_type'])
                ->where('block_id', $data['block_id'] ?? null)
                ->where('house_id', $data['house_id'] ?? null)
                ->current($data['assigned_from'])
                ->exists();

            if ($duplicate) {
                throw new RuntimeException('Petugas tersebut sudah memiliki penugasan yang sama pada tanggal itu.');
            }

            $assignment = OfficerAssignment::create($data);
            $this->audit->created('officer_assignments.created', $assignment);

            return $assignment;
        });
    }

    public function end(OfficerAssignment $assignment, ?string $date = null): OfficerAssignment
    {
        $until = Carbon::parse($date ?? today())->toDateString();

        if ($assignment->assigned_until !== null && $assignment->assigned_until->toDateString() <= $until) {
            throw new RuntimeException('Penugasan ini sudah berakhir.');
        }

        if ($until < $assignment->assigned_from->toDateString()) {
            throw new RuntimeException('Tanggal akhir tidak boleh sebelum tanggal mulai penugasan.');
        }

        return DB::transaction(function () use ($assignment, $until) {
            $assignment->assigned_until = $until;
            $this->audit->changed('officer_assignments.ended', $assignment);
            $assignment->save();

            return $assignment;
        });
    }
}
