<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:1',
                'max:255'
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000'
            ],
            'organization_id' => [
                'required',
                'exists:organizations,id'
            ],
            'color' => [
                'nullable',
                'string',
                'regex:/^#[a-fA-F0-9]{6}$/'
            ],
            'settings' => [
                'nullable',
                'array'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Team name is required.',
            'name.min' => 'Team name must be at least :min character.',
            'name.max' => 'Team name cannot exceed :max characters.',
            'description.max' => 'Description cannot exceed :max characters.',
            'organization_id.required' => 'Organization is required.',
            'organization_id.exists' => 'Selected organization does not exist.',
            'color.regex' => 'Color must be a valid hex color code (e.g., #FF5733).',
            'settings.array' => 'Settings must be a valid array.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'team name',
            'description' => 'team description',
            'organization_id' => 'organization',
            'color' => 'team color',
            'settings' => 'team settings',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim($this->name),
            'description' => $this->description ? trim($this->description) : null,
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check if user can manage the organization
            if ($this->organization_id) {
                $user = auth()->user();
                $organization = \App\Models\Organization::find($this->organization_id);

                if ($organization && !$user->canManageOrganization($organization)) {
                    $validator->errors()->add('organization_id', 'You do not have permission to create teams in this organization.');
                }
            }
        });
    }
}
