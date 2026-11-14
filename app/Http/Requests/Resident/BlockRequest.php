<?php

namespace App\Http\Requests\Resident;

use App\Enums\Resident\BlockStatus;
use App\Models\Block;
use App\Support\CurrentCommunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('houses.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Block|null $block */
        $block = $this->route('block');

        return [
            'code' => [
                'required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9 .\-]*$/',
                Rule::unique('blocks', 'code')
                    ->where('community_id', app(CurrentCommunity::class)->id())
                    ->ignore($block?->getKey()),
            ],
            'name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(BlockStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'kode blok', 'name' => 'nama blok'];
    }
}
