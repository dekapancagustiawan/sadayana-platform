<?php

namespace App\Http\Requests\Resident;

use App\Enums\Resident\AssignmentType;
use App\Support\CurrentCommunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfficerAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('officer-assignments.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $community = app(CurrentCommunity::class)->id();

        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'assignment_type' => ['required', Rule::enum(AssignmentType::class)],
            'block_id' => [
                'nullable', 'uuid', 'required_without:house_id', 'prohibits:house_id',
                Rule::exists('blocks', 'id')->where('community_id', $community)->whereNull('deleted_at'),
            ],
            'house_id' => [
                'nullable', 'uuid', 'required_without:block_id',
                Rule::exists('houses', 'id')->where('community_id', $community)->whereNull('deleted_at'),
            ],
            'assigned_from' => ['required', 'date'],
            'assigned_until' => ['nullable', 'date', 'after_or_equal:assigned_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'block_id.prohibits' => 'Pilih salah satu: blok ATAU rumah.',
            'block_id.required_without' => 'Pilih blok atau rumah yang ditugaskan.',
            'house_id.required_without' => 'Pilih blok atau rumah yang ditugaskan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'petugas',
            'assignment_type' => 'jenis tugas',
            'assigned_from' => 'mulai',
            'assigned_until' => 'sampai',
        ];
    }
}
