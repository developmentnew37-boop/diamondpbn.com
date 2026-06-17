<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk Update Campaign Keywords Request
 *
 * Validates data for bulk updating keywords/URLs across campaign posts.
 */
class BulkUpdateCampaignKeywordsRequest extends FormRequest
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
            'keywords' => [
                'required',
                'array',
                'min:1',
            ],
            'keywords.*.keyword' => [
                'required',
                'string',
                'max:' . config('campaign.validation.keyword_max_length', 255),
            ],
            'keywords.*.url' => [
                'required',
                'url',
                'max:' . config('campaign.validation.url_max_length', 2048),
            ],
            'keywords.*.nofollow' => [
                'nullable',
                'boolean',
            ],
            'keywords.*.sponsored' => [
                'nullable',
                'boolean',
            ],
            'keywords.*.ugc' => [
                'nullable',
                'boolean',
            ],
            'keywords.*.noopener' => [
                'nullable',
                'boolean',
            ],
            'keywords.*.noreferrer' => [
                'nullable',
                'boolean',
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
            'keywords.required' => 'At least one keyword/URL pair is required.',
            'keywords.*.keyword.required' => 'Keyword is required for all entries.',
            'keywords.*.keyword.max' => 'Keyword cannot exceed :max characters.',
            'keywords.*.url.required' => 'URL is required for all entries.',
            'keywords.*.url.url' => 'Please provide a valid URL.',
            'keywords.*.url.max' => 'URL cannot exceed :max characters.',
        ];
    }
}
