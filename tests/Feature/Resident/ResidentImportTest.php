<?php

namespace Tests\Feature\Resident;

use App\Models\Block;
use App\Models\House;
use App\Models\HouseResident;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ResidentImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function upload(string $csv)
    {
        return $this->actingAs($this->admin)->post(route('houses.import.store'), [
            'file' => UploadedFile::fake()->createWithContent('rumah.csv', $csv),
        ]);
    }

    public function test_semicolon_csv_from_excel_is_imported(): void
    {
        $csv = "\xEF\xBB\xBFBlok;Nomor;Nama Pemilik;No HP;Hubungan;Tanggal Mulai\n"
            ."J;12;Budi Santoso;0812-3456-7890;pemilik;01/01/2024\n"
            ."J;12;Ani Santoso;;keluarga;01/01/2024\n"
            ."J;13;;;;\n"
            ."C;4;Siti;+62 857 1111 2222;penyewa;\n";

        $this->upload($csv)->assertRedirect(route('houses.index'))->assertSessionHas('success');

        $this->assertSame(['C', 'J'], Block::orderBy('code')->pluck('code')->all());
        $this->assertSame(['C-4', 'J-12', 'J-13'], House::orderBy('house_code')->pluck('house_code')->all());
        $this->assertSame(3, Resident::count());
        $this->assertSame('6281234567890', Resident::firstWhere('full_name', 'Budi Santoso')->phone);

        $j12 = House::firstWhere('house_code', 'J-12');
        $this->assertSame(2, $j12->activeOccupancies()->count());
        $this->assertSame('Budi Santoso', $j12->primaryResident()->full_name);
        $this->assertSame('occupied', $j12->occupancy_status->value);
        $this->assertSame('vacant', House::firstWhere('house_code', 'J-13')->occupancy_status->value);
        $this->assertSame('tenant', HouseResident::first()->newQuery()->whereHas('resident', fn ($q) => $q->where('full_name', 'Siti'))->first()->relationship_type->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'residents.imported']);
    }

    public function test_reimporting_same_file_does_not_duplicate(): void
    {
        $csv = "blok,nomor,nama,no_hp\nJ,12,Budi,081234567890\nJ,14,,\n";

        $this->upload($csv)->assertSessionHas('success');
        $this->upload($csv)->assertSessionHas('success');

        $this->assertSame(2, House::count());
        $this->assertSame(1, Resident::count());
        $this->assertSame(1, HouseResident::count());
    }

    public function test_any_invalid_row_aborts_whole_import(): void
    {
        $csv = "blok,nomor,nama,no_hp,email,tanggal_mulai\n"
            ."J,12,Budi,081234567890,,\n"
            .",13,Tanpa Blok,,,\n"
            ."J,14,Ani,12,bukan-email,31/02/2025\n";

        $this->upload($csv)
            ->assertSessionHas('error')
            ->assertSessionHas('import_errors', function (array $errors) {
                $text = implode(' ', $errors);

                return str_contains($text, 'Baris 3: kolom blok kosong')
                    && str_contains($text, 'nomor HP "12" tidak valid')
                    && str_contains($text, 'email "bukan-email" tidak valid')
                    && str_contains($text, 'tanggal_mulai "31/02/2025"');
            });

        $this->assertSame(0, House::count());
        $this->assertSame(0, Resident::count());
    }

    public function test_missing_required_columns_is_reported(): void
    {
        $this->upload("nama,no_hp\nBudi,0812\n")
            ->assertSessionHas('import_errors', fn ($errors) => str_contains($errors[0], '"blok" dan "nomor"'));
    }

    public function test_template_download(): void
    {
        $response = $this->actingAs($this->admin)->get(route('houses.import.template'))->assertOk();

        $this->assertStringContainsString('blok;nomor;nama;no_hp', $response->streamedContent());
    }
}
