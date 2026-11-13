<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailLogController extends Controller
{
    /**
     * GET /email-logs
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:sent,failed'],
            'type' => ['nullable', 'string', 'max:50'],
        ]);

        $logs = EmailLog::query()
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('recipient_email', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('email_type', $type))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $types = EmailLog::query()->whereNotNull('email_type')->distinct()->orderBy('email_type')->pluck('email_type');

        return view('settings.email-logs', compact('logs', 'types'));
    }
}
