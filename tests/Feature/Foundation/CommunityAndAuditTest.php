<?php

namespace Tests\Feature\Foundation;

use App\Models\AuditLog;
use App\Models\Community;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Support\CurrentCommunity;
use App\Support\Database\Constraints;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommunityAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_community_is_created_once(): void
    {
        $first = app(CurrentCommunity::class)->id();
        $this->app->forgetScopedInstances();
        $second = app(CurrentCommunity::class)->id();

        $this->assertSame($first, $second);
        $this->assertSame(1, Community::count());
        $this->assertSame(config('community.default_code'), Community::first()->code);
    }

    public function test_community_scope_hides_other_communities_rows(): void
    {
        $user = User::factory()->create();
        $other = Community::create(['code' => 'other', 'name' => 'Other', 'timezone' => 'Asia/Jakarta', 'status' => 'active']);

        $mine = StoredFile::create($this->fileAttributes($user));

        app(CurrentCommunity::class)->set($other);
        $theirs = StoredFile::create($this->fileAttributes($user));

        $this->assertSame($other->id, $theirs->community_id);
        $this->assertSame([$theirs->id], StoredFile::pluck('id')->all());

        $this->app->forgetScopedInstances();
        $this->assertSame([$mine->id], StoredFile::pluck('id')->all());
    }

    public function test_audit_service_records_changes_on_uuid_model(): void
    {
        $this->actingAs($user = User::factory()->create());
        $community = app(CurrentCommunity::class)->get();

        $community->name = 'Perumahan Baru';
        $log = app(AuditService::class)->changed('communities.updated', $community, 'Ganti nama');
        $community->save();

        $log->refresh();
        $this->assertSame($community->id, $log->model_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(['name' => config('community.default_name')], $log->payload['old']);
        $this->assertSame(['name' => 'Perumahan Baru'], $log->payload['new']);
        $this->assertSame('Ganti nama', $log->payload['reason']);
    }

    public function test_legacy_integer_model_ids_still_recorded(): void
    {
        $log = AuditLog::record('users.updated', null, null, User::class, 42);

        $this->assertSame('42', $log->fresh()->model_id);
    }

    public function test_partial_unique_index_applies_only_to_matching_rows(): void
    {
        Schema::create('partial_unique_probe', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('status');
        });
        Constraints::partialUnique('partial_unique_probe', 'probe_code_active_unique', ['code'], "status <> 'cancelled'");

        DB::table('partial_unique_probe')->insert(['code' => 'A', 'status' => 'cancelled']);
        DB::table('partial_unique_probe')->insert(['code' => 'A', 'status' => 'cancelled']);
        DB::table('partial_unique_probe')->insert(['code' => 'A', 'status' => 'issued']);

        $this->expectException(QueryException::class);
        DB::table('partial_unique_probe')->insert(['code' => 'A', 'status' => 'issued']);
    }

    /**
     * @return array<string, mixed>
     */
    private function fileAttributes(User $user): array
    {
        return [
            'uploaded_by' => $user->id,
            'category' => 'test',
            'storage_disk' => 'private',
            'storage_path' => 'test/x.jpg',
            'original_name' => 'x.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 10,
        ];
    }
}
