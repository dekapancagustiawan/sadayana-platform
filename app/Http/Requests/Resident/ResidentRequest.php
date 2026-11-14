<?php

namespace App\Http\Requests\Resident;

use App\Enums\Resident\ResidentStatus;
use App\Support\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('residents.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Phone::normalize($this->input('phone')),
            'email' => filled($this->input('email')) ? Str::lower(trim((string) $this->input('email'))) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'full_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'min:9', 'max:15'],
            'email' => ['nullable', 'email', 'max:150'],
            'resident_number' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'status' => ['sometimes', Rule::enum(ResidentStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        // Data identitas hanya boleh diisi pemegang izin khusus.
        if ($this->user()->can('residents.view-identity')) {
            $rules['identity_number'] = ['nullable', 'string', 'max:50'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'nama lengkap',
            'phone' => 'nomor HP',
            'birth_date' => 'tanggal lahir',
            'identity_number' => 'nomor identitas',
        ];
    }
}
