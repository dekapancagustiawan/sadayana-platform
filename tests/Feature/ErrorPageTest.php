<?php

namespace Tests\Feature;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman error harus mengarahkan orang kembali ke tempat yang memang boleh ia
 * buka: staf ke dashboardnya, tamu ke halaman utama publik. Tombol yang salah
 * arah membuat orang berputar-putar di 403.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'MenuAccessPermissionsSeeder']);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }

    public function test_guest_sees_the_public_landing_button_on_404(): void
    {
        $response = $this->get('/halaman-yang-tidak-pernah-ada');

        $response->assertNotFound()
            ->assertSee('Halamannya Tidak Ada Di Sini')
            ->assertSee('Kembali ke Halaman Utama')
            ->assertDontSee('Kembali ke Dashboard');
    }

    public function test_error_page_does_not_depend_on_the_vite_build(): void
    {
        // Halaman ini harus tampil benar justru ketika asetnya yang rusak.
        $this->get('/halaman-yang-tidak-pernah-ada')
            ->assertDontSee('/build/assets', false);
    }

    /**
     * Pengunjung yang belum masuk tidak dialihkan diam-diam ke form login —
     * pengalihan itu memberi tahu penebak URL bahwa halamannya memang ada.
     */
    public function test_guest_hitting_an_internal_url_gets_the_401_page_not_a_redirect(): void
    {
        $response = $this->get(route('users.index'));

        $response->assertUnauthorized()
            ->assertSee('Halaman Ini Tidak Tersedia')
            ->assertSee('Kembali ke Halaman Utama')
            // Halaman publik tidak boleh menunjukkan di mana pintu masuk staf.
            ->assertDontSee('Masuk ke Sistem')
            ->assertDontSee(route('login'), false);
    }

    public function test_the_intended_url_survives_so_login_still_lands_there(): void
    {
        $this->get(route('audit-logs.index'))->assertUnauthorized();

        $this->assertSame(
            route('audit-logs.index'),
            session('url.intended'),
            'Tujuan semula tetap disimpan: staf yang menuju halaman dalam lalu masuk sendiri lewat alamat internal harus mendarat di sana, bukan di dashboard.'
        );

        $admin = $this->userWithRole(RoleType::Admin->value);

        $this->post(route('login.submit'), [
            'login' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('audit-logs.index'));
    }

    public function test_api_requests_still_get_json_not_the_error_page(): void
    {
        $this->getJson('/api/users')
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error');
    }

    public function test_user_is_sent_back_to_the_dashboard(): void
    {
        $response = $this->actingAs($this->userWithRole(RoleType::User->value))
            ->get(route('users.index'));

        $response->assertForbidden()
            ->assertSee('Halaman Ini Di Luar Wewenang Anda')
            ->assertSee('Kembali ke Dashboard')
            ->assertSee(route('dashboard'), false);
    }

    public function test_signed_in_user_name_is_shown_so_the_wrong_account_is_obvious(): void
    {
        $user = $this->userWithRole(RoleType::User->value);

        $this->actingAs($user)
            ->get(route('audit-logs.index'))
            ->assertForbidden()
            ->assertSee($user->name)
            ->assertSee($user->email);
    }
}
