<?php

namespace App\Http\Requests\Resident;

use App\Enums\Resident\OccupancyStatus;
use App\Models\Block;
use App\Models\House;
use App\Support\CurrentCommunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('houses.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'house_number' => strtoupper(trim((string) $this->input('house_number'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var House|null $house */
        $house = $this->route('house');

        return [
            'block_id' => [
                'required', 'uuid',
                Rule::exists('blocks', 'id')
                    ->where('community_id', app(CurrentCommunity::class)->id())
                    ->whereNull('deleted_at'),
            ],
            // FR-RES-002: nomor rumah unik dalam lingkup blok.
            'house_number' => [
                'required', 'string', 'max:30',
                Rule::unique('houses', 'house_number')
                    ->where('block_id', $this->input('block_id'))
                    ->ignore($house?->getKey()),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'house_type' => ['nullable', 'string', 'max:30'],
            'area_size' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'occupancy_status' => ['nullable', Rule::enum(OccupancyStatus::class)],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $block = Block::find($this->input('block_id'));

        return [
            'house_number.unique' => 'Nomor rumah ini sudah ada di '.($block?->label() ?? 'blok tersebut').'.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['block_id' => 'blok', 'house_number' => 'nomor rumah', 'area_size' => 'luas'];
    }
}
