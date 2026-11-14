<?php

namespace App\Services\Resident;

use App\Enums\Resident\BlockStatus;
use App\Enums\Resident\OccupancyStatus;
use App\Models\Block;
use App\Models\House;
use App\Models\HouseResident;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Master blok & rumah (FR-RES-001, FR-RES-002).
 *
 * Rumah yang sudah punya riwayat hunian tidak boleh dihapus — nonaktifkan
 * saja (FR-RES-002: tidak dihapus fisik tanpa prosedur khusus).
 */
class HouseService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{code: string, name?: ?string, description?: ?string, status?: string}  $data
     */
    public function createBlock(array $data): Block
    {
        return DB::transaction(function () use ($data) {
            $block = Block::create([
                ...$data,
                'code' => strtoupper(trim($data['code'])),
                'status' => $data['status'] ?? BlockStatus::Active->value,
            ]);
            $this->audit->created('blocks.created', $block);

            return $block;
        });
    }

    /**
     * Ubah blok. Mengganti kode blok ikut memperbarui house_code semua rumah
     * di blok itu.
     *
     * @param  array{code: string, name?: ?string, description?: ?string, status?: string}  $data
     */
    public function updateBlock(Block $block, array $data): Block
    {
        return DB::transaction(function () use ($block, $data) {
            $block->fill([...$data, 'code' => strtoupper(trim($data['code']))]);
            $codeChanged = $block->isDirty('code');

            $this->audit->changed('blocks.updated', $block);
            $block->save();

            if ($codeChanged) {
                $block->houses()->withTrashed()->each(function (House $house) use ($block) {
                    $house->update(['house_code' => House::codeFor($block->code, $house->house_number)]);
                });
            }

            return $block;
        });
    }

    public function deleteBlock(Block $block): void
    {
        if ($block->houses()->withTrashed()->exists()) {
            throw new RuntimeException('Blok masih memiliki rumah. Pindahkan/hapus rumahnya dulu, atau nonaktifkan blok.');
        }

        DB::transaction(function () use ($block) {
            $this->audit->record('blocks.deleted', $block, ['code' => $block->code]);
            $block->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data  block_id, house_number, …
     */
    public function createHouse(array $data): House
    {
        return DB::transaction(function () use ($data) {
            $block = Block::findOrFail($data['block_id']);
            $number = strtoupper(trim((string) $data['house_number']));

            $house = House::create([
                ...$data,
                'house_number' => $number,
                'house_code' => House::codeFor($block->code, $number),
                'occupancy_status' => $data['occupancy_status'] ?? OccupancyStatus::Vacant->value,
            ]);
            $this->audit->created('houses.created', $house);

            return $house;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateHouse(House $house, array $data): House
    {
        return DB::transaction(function () use ($house, $data) {
            if (isset($data['house_number'])) {
                $data['house_number'] = strtoupper(trim((string) $data['house_number']));
            }

            $house->fill($data);

            if ($house->isDirty(['block_id', 'house_number'])) {
                $block = Block::findOrFail($house->block_id);
                $house->house_code = House::codeFor($block->code, $house->house_number);
            }

            $this->audit->changed('houses.updated', $house);
            $house->save();

            return $house;
        });
    }

    public function deleteHouse(House $house): void
    {
        if (HouseResident::where('house_id', $house->getKey())->exists()) {
            throw new RuntimeException('Rumah sudah memiliki riwayat penghuni sehingga tidak dapat dihapus. Nonaktifkan rumah ini sebagai gantinya.');
        }

        DB::transaction(function () use ($house) {
            $this->audit->record('houses.deleted', $house, ['house_code' => $house->house_code]);
            $house->delete();
        });
    }
}
