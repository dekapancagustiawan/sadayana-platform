<?php

namespace App\Http\Controllers\Resident;

use App\Enums\Resident\BlockStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Resident\BlockRequest;
use App\Models\Block;
use App\Services\Resident\HouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/**
 * Master blok (FR-RES-001). Lihat: houses.view; ubah: houses.manage.
 */
class BlockController extends Controller
{
    public function __construct(private readonly HouseService $houses) {}

    public function index(): View
    {
        Gate::authorize('houses.view');

        $blocks = Block::query()
            ->withCount([
                'houses',
                'houses as occupied_count' => fn ($q) => $q->where('occupancy_status', 'occupied'),
            ])
            ->orderBy('code')
            ->get();

        return view('resident.blocks.index', compact('blocks'));
    }

    public function create(): View
    {
        Gate::authorize('houses.manage');

        return view('resident.blocks.form', ['block' => new Block(['status' => BlockStatus::Active])]);
    }

    public function store(BlockRequest $request): RedirectResponse
    {
        $block = $this->houses->createBlock($request->validated());

        return redirect()->route('blocks.index')->with('success', "Blok {$block->code} ditambahkan.");
    }

    public function edit(Block $block): View
    {
        Gate::authorize('houses.manage');

        return view('resident.blocks.form', compact('block'));
    }

    public function update(BlockRequest $request, Block $block): RedirectResponse
    {
        $this->houses->updateBlock($block, $request->validated());

        return redirect()->route('blocks.index')->with('success', "Blok {$block->code} diperbarui.");
    }

    public function destroy(Block $block): RedirectResponse
    {
        Gate::authorize('houses.manage');

        try {
            $this->houses->deleteBlock($block);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('blocks.index')->with('success', "Blok {$block->code} dihapus.");
    }
}
