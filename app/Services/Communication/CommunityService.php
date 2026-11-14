<?php

namespace App\Services\Communication;

use App\Enums\Communication\ComplaintCategory;
use App\Enums\Communication\ComplaintStatus;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Complaint;
use App\Models\House;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\CodeGeneratorService;
use App\Services\Files\FileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Pengumuman (FR-NOT-002) & pengaduan warga (FR-NOT-003), termasuk laporan
 * "sampah belum diambil" (S-3).
 */
class CommunityService
{
    public const ATTACHMENT_CATEGORY = 'complaint_attachment';

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly CodeGeneratorService $codes,
        private readonly FileService $files,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array{title: string, content: string, publish_at: string, expires_at?: ?string, status: string}  $data
     */
    public function saveAnnouncement(array $data, ?Announcement $announcement = null): Announcement
    {
        $wasPublished = $announcement?->status === 'published';

        $announcement = DB::transaction(function () use ($data, $announcement) {
            if ($announcement) {
                $announcement->fill($data);
                $this->audit->changed('announcements.updated', $announcement);
                $announcement->save();

                return $announcement;
            }

            $announcement = Announcement::create($data);
            $this->audit->created('announcements.created', $announcement);

            return $announcement;
        });

        if (! $wasPublished && $announcement->status === 'published') {
            $this->notifications->notifyUsers(
                User::where('status', UserStatus::Active)->get(),
                'Pengumuman: '.$announcement->title,
                str($announcement->content)->limit(120)->toString(),
                route('announcements.show', $announcement),
                'fas fa-bullhorn',
                'announcement',
            );
        }

        return $announcement;
    }

    public function submitComplaint(User $user, ?House $house, ComplaintCategory $category, string $description, ?UploadedFile $attachment = null): Complaint
    {
        $complaint = DB::transaction(function () use ($user, $house, $category, $description, $attachment) {
            $file = $attachment ? $this->files->store($attachment, self::ATTACHMENT_CATEGORY) : null;

            $complaint = Complaint::create([
                'complaint_number' => $this->codes->generate('ADU', 'complaints', 'complaint_number'),
                'house_id' => $house?->getKey(),
                'submitted_by' => $user->getKey(),
                'category' => $category,
                'description' => $description,
                'attachment_file_id' => $file?->getKey(),
                'status' => ComplaintStatus::Submitted,
            ]);
            $this->audit->created('complaints.submitted', $complaint);

            return $complaint;
        });

        $this->notifications->notifyPermission(
            'complaints.manage',
            'Pengaduan baru: '.$category->label(),
            ($house ? $house->house_code.' — ' : '').str($description)->limit(100),
            route('complaints.show', $complaint),
            'fas fa-comment-dots',
            'complaint',
        );

        return $complaint;
    }

    public function updateComplaint(Complaint $complaint, ComplaintStatus $status, ?string $notes, User $user, ?int $assignTo = null): Complaint
    {
        $complaint = DB::transaction(function () use ($complaint, $status, $notes, $assignTo) {
            if ($complaint->status !== $status) {
                $complaint->status->assertCanTransitionTo($status);
            }

            $complaint->fill([
                'status' => $status,
                'resolution_notes' => $notes ?? $complaint->resolution_notes,
                'assigned_to' => $assignTo ?? $complaint->assigned_to,
                'resolved_at' => in_array($status, [ComplaintStatus::Resolved, ComplaintStatus::Closed], true) ? ($complaint->resolved_at ?? now()) : null,
            ]);
            $this->audit->changed('complaints.updated', $complaint, $notes);
            $complaint->save();

            return $complaint;
        });

        if ($complaint->submitter) {
            $this->notifications->notifyUsers(
                [$complaint->submitter],
                "Pengaduan {$complaint->complaint_number}: ".$status->label(),
                $notes ?: 'Status pengaduan Anda diperbarui.',
                route('complaints.show', $complaint),
                'fas fa-comment-dots',
                'complaint',
            );
        }

        return $complaint;
    }
}
