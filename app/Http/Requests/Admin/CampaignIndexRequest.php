<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Campaign Index Request
 *
 * Validates search and filtering parameters for campaign listing pages.
 * Used across all campaign types for consistent validation.
 */
class CampaignIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:' . config('campaign.validation.search_max_length', 150),
            ],
            'filter_user' => [
                'nullable',
                'string',
                'max:' . config('campaign.validation.filter_user_max_length', 20),
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'search.max' => 'The search term cannot exceed :max characters.',
            'filter_user.max' => 'The filter value is too long.',
        ];
    }
}
