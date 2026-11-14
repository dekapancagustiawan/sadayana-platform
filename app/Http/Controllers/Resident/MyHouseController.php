<?php

namespace App\Http\Controllers\Resident;

use App\Http\Controllers\Controller;
use App\Services\Resident\HouseAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Portal warga: rumah yang sedang dihuni (FR-RES-005). Hanya rumah dengan
 * hunian AKTIF — lewat HouseAccessService, bukan query sendiri.
 */
class MyHouseController extends Controller
{
    public function __construct(private readonly HouseAccessService $access) {}

    public function index(Request $request): View
    {
        $houses = $this->access->housesFor($request->user())
            ->load(['activeOccupancies.resident']);

        return view('resident.my-house.index', compact('houses'));
    }
}
