<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCredentialRequest extends FormRequest
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
            'type' => [
                'required',
                'string',
                'in:oauth2,api_key,basic_auth,bearer_token,custom'
            ],
            'organization_id' => [
                'required',
                'exists:organizations,id'
            ],
            'data' => [
                'required',
                'array'
            ],
            'is_shared' => [
                'boolean'
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after:now'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Credential name is required.',
            'name.min' => 'Credential name must be at least :min character.',
            'name.max' => 'Credential name cannot exceed :max characters.',
            'type.required' => 'Credential type is required.',
            'type.in' => 'Invalid credential type selected.',
            'organization_id.required' => 'Organization is required.',
            'organization_id.exists' => 'Selected organization does not exist.',
            'data.required' => 'Credential data is required.',
            'data.array' => 'Credential data must be a valid array.',
            'is_shared.boolean' => 'Shared status must be true or false.',
            'expires_at.date' => 'Expiration date must be a valid date.',
            'expires_at.after' => 'Expiration date must be in the future.',
        ];
    }
}
