<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsService;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    public function home(): View
    {
        return view('landing.index', [
            'site' => $this->settings->publicProfile(),
            'sections' => $this->sections(),
        ]);
    }

    /**
     * Section aktif dari config/landing.php, hanya yang partial-nya ada —
     * salah ketik nama section tidak boleh membuat halaman utama error 500.
     *
     * @return list<string>
     */
    private function sections(): array
    {
        return array_values(array_filter(
            (array) config('landing.sections', []),
            fn ($section) => is_string($section) && view()->exists("landing.sections.{$section}")
        ));
    }
}
