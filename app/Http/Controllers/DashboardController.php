<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\Reporting\DashboardWidgetService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
    ) {}

    /**
     * Show the main dashboard.
     *
     * GET /dashboard
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'stats' => $this->dashboardService->stats($user),
            'activities' => $this->dashboardService->recentActivity($user),
            'widgets' => app(DashboardWidgetService::class)->for($user),
        ]);
    }
}
