<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled in the controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $workflowId = $this->route('workflow')->id ?? null;

        return [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255|unique:workflows,slug,' . $workflowId,
            'description' => 'nullable|string|max:1000',
            'organization_id' => 'nullable|exists:organizations,id',
            'team_id' => 'nullable|exists:teams,id',
            'workflow_data' => 'nullable|array',
            'workflow_data.nodes' => 'nullable|array',
            'workflow_data.connections' => 'nullable|array',
            'workflow_data.settings' => 'nullable|array',
            'settings' => 'nullable|array',
            'status' => 'nullable|in:draft,published,archived',
            'is_active' => 'nullable|boolean',
            'is_template' => 'nullable|boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Workflow name is required',
            'name.max' => 'Workflow name cannot exceed 255 characters',
            'slug.unique' => 'This workflow slug is already taken',
            'organization_id.exists' => 'Selected organization does not exist',
            'team_id.exists' => 'Selected team does not exist',
            'status.in' => 'Status must be one of: draft, published, archived',
            'tags.*.max' => 'Each tag cannot exceed 50 characters',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-generate slug if name is being updated and slug is not provided
        if ($this->has('name') && !$this->has('slug')) {
            $this->merge([
                'slug' => \Illuminate\Support\Str::slug($this->name) . '-' . time(),
            ]);
        }
    }
}
