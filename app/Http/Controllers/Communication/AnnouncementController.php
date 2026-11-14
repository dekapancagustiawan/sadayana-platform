<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Communication\CommunityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pengumuman (FR-NOT-002): dibaca semua warga, dikelola Ketua RT.
 */
class AnnouncementController extends Controller
{
    public function __construct(private readonly CommunityService $community) {}

    public function index(Request $request): View
    {
        $manage = $request->user()->can('announcements.manage');

        return view('communication.announcements.index', [
            'announcements' => Announcement::with('author:id,name')
                ->when(! $manage, fn ($q) => $q->visible())
                ->latest('publish_at')
                ->paginate(20),
            'manage' => $manage,
        ]);
    }

    public function show(Request $request, Announcement $announcement): View
    {
        abort_unless($request->user()->can('announcements.manage') || Announcement::visible()->whereKey($announcement->getKey())->exists(), 404);

        return view('communication.announcements.show', compact('announcement'));
    }

    public function create(): View
    {
        Gate::authorize('announcements.manage');

        return view('communication.announcements.form', ['announcement' => new Announcement(['publish_at' => now(), 'status' => 'published'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('announcements.manage');
        $announcement = $this->community->saveAnnouncement($this->validated($request));

        return redirect()->route('announcements.show', $announcement)->with('success', 'Pengumuman disimpan.');
    }

    public function edit(Announcement $announcement): View
    {
        Gate::authorize('announcements.manage');

        return view('communication.announcements.form', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        Gate::authorize('announcements.manage');
        $this->community->saveAnnouncement($this->validated($request), $announcement);

        return redirect()->route('announcements.show', $announcement)->with('success', 'Pengumuman diperbarui.');
    }

    /**
     * @return array{title: string, content: string, publish_at: string, expires_at: ?string, status: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:10000'],
            'publish_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:publish_at'],
            'status' => ['required', 'in:draft,published,archived'],
        ], [], ['title' => 'judul', 'content' => 'isi', 'publish_at' => 'tanggal tayang', 'expires_at' => 'berakhir']);
    }
}
