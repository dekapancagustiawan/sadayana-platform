<?php

namespace Tests\Feature\Resident;

use App\Models\User;
use App\Services\Resident\HouseService;
use App\Services\Resident\ResidencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test: semua layar Fase 2 bisa dirender oleh Admin.
 */
class ResidentPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_resident_pages_render_for_admin(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $houses = app(HouseService::class);
        $residency = app(ResidencyService::class);
        $block = $houses->createBlock(['code' => 'J', 'name' => 'Cluster Jati']);
        $house = $houses->createHouse(['block_id' => $block->id, 'house_number' => '12']);
        $resident = $residency->createResident(['full_name' => 'Budi', 'phone' => '081234567890', 'identity_number' => '3201']);
        $stay = $residency->moveIn($house, $resident, ['start_date' => '2024-01-01']);
        $old = $residency->createResident(['full_name' => 'Penghuni Lama']);
        $residency->moveOut($residency->moveIn($house, $old, ['start_date' => '2020-01-01']), '2023-12-31', 'Dijual');

        $this->actingAs($admin);

        foreach ([
            route('blocks.index'),
            route('blocks.create'),
            route('blocks.edit', $block),
            route('houses.index'),
            route('houses.index', ['search' => 'budi', 'block' => $block->id, 'occupancy' => 'occupied']),
            route('houses.create'),
            route('houses.show', $house),
            route('houses.edit', $house),
            route('houses.import'),
            route('residents.index'),
            route('residents.index', ['search' => '0812']),
            route('residents.create'),
            route('residents.show', $resident),
            route('residents.edit', $resident),
            route('officer-assignments.index'),
            route('officer-assignments.index', ['ended' => 1]),
            route('my-house.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get(route('houses.show', $house))->assertSee('Penghuni Lama')->assertSee('Dijual');
        $this->get(route('residents.show', $resident))->assertSee('3201');
        $this->get(route('houses.index', ['search' => 'budi']))->assertSee('J-12');
        $this->assertNotNull($stay);
    }

    public function test_identity_is_hidden_without_permission(): void
    {
        $this->seed();
        $resident = app(ResidencyService::class)->createResident(['full_name' => 'Budi', 'identity_number' => '3201999']);
        $ketua = User::where('email', 'ketua@example.com')->firstOrFail();

        $this->actingAs($ketua)->get(route('residents.show', $resident))
            ->assertOk()
            ->assertDontSee('3201999')
            ->assertSee('dibatasi');
    }
}
