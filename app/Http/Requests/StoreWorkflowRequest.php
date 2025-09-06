<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowRequest extends FormRequest
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
            'team_id' => [
                'nullable',
                'exists:teams,id'
            ],
            'workflow_data' => [
                'nullable',
                'array'
            ],
            'workflow_data.nodes' => [
                'nullable',
                'array'
            ],
            'workflow_data.connections' => [
                'nullable',
                'array'
            ],
            'settings' => [
                'nullable',
                'array'
            ],
            'tags' => [
                'nullable',
                'array'
            ],
            'tags.*' => [
                'string',
                'max:50'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Workflow name is required.',
            'name.min' => 'Workflow name must be at least :min character.',
            'name.max' => 'Workflow name cannot exceed :max characters.',
            'description.max' => 'Description cannot exceed :max characters.',
            'organization_id.required' => 'Organization is required.',
            'organization_id.exists' => 'Selected organization does not exist.',
            'team_id.exists' => 'Selected team does not exist.',
            'workflow_data.array' => 'Workflow data must be a valid array.',
            'workflow_data.nodes.array' => 'Workflow nodes must be a valid array.',
            'workflow_data.connections.array' => 'Workflow connections must be a valid array.',
            'settings.array' => 'Settings must be a valid array.',
            'tags.array' => 'Tags must be a valid array.',
            'tags.*.string' => 'Each tag must be a string.',
            'tags.*.max' => 'Each tag cannot exceed :max characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'workflow name',
            'description' => 'workflow description',
            'organization_id' => 'organization',
            'team_id' => 'team',
            'workflow_data' => 'workflow data',
            'settings' => 'workflow settings',
            'tags' => 'tags',
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
            // Additional validation logic can be added here
            // For example, checking if user belongs to the organization
            if ($this->organization_id) {
                $user = auth()->user();
                $organization = \App\Models\Organization::find($this->organization_id);

                if ($organization && !$organization->isMember($user)) {
                    $validator->errors()->add('organization_id', 'You do not have access to this organization.');
                }

                // Check team belongs to organization
                if ($this->team_id) {
                    $team = \App\Models\Team::find($this->team_id);
                    if ($team && $team->organization_id !== (int)$this->organization_id) {
                        $validator->errors()->add('team_id', 'Selected team does not belong to the selected organization.');
                    }
                }
            }
        });
    }
}
