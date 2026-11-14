<?php

namespace App\Http\Requests\Resident;

use App\Enums\Resident\RelationshipType;
use App\Support\CurrentCommunity;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Penghuni masuk ke rumah: pilih penghuni yang sudah terdaftar
 * (`resident_id`) ATAU isi data penghuni baru (`full_name`, `phone`).
 */
class MoveInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('residents.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Phone::normalize($this->input('phone')),
            'is_primary_contact' => $this->boolean('is_primary_contact'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resident_id' => [
                'nullable', 'uuid', 'required_without:full_name',
                Rule::exists('residents', 'id')
                    ->where('community_id', app(CurrentCommunity::class)->id())
                    ->whereNull('deleted_at'),
            ],
            'full_name' => ['nullable', 'required_without:resident_id', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'min:9', 'max:15'],
            'relationship_type' => ['required', Rule::enum(RelationshipType::class)],
            'is_primary_contact' => ['boolean'],
            'start_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'resident_id' => 'penghuni',
            'full_name' => 'nama penghuni baru',
            'relationship_type' => 'hubungan',
            'start_date' => 'tanggal mulai menghuni',
        ];
    }
}
