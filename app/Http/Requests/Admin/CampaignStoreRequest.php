<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Campaign Store Request
 *
 * Validates data for creating a new campaign.
 * Handles validation for campaign number, domain/article categories,
 * and keyword/URL pairs.
 */
class CampaignStoreRequest extends FormRequest
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
            'campaign_no' => [
                'required',
                'string',
                'max:' . config('campaign.validation.campaign_no_max_length', 150),
            ],
            'domain_category_id' => [
                'required',
                'integer',
                'exists:domain_categories,id',
            ],
            'article_category_id' => [
                'nullable',
                'integer',
                'exists:article_categories,id',
            ],
            'article_type' => [
                'required',
                'string',
                Rule::in(['category', 'set', 'language']),
            ],
            'article_set_id' => [
                'required_if:article_type,set',
                'nullable',
                'integer',
                'exists:article_sets,id',
            ],
            'article_language_id' => [
                'required_if:article_type,language',
                'nullable',
                'integer',
                'exists:article_languages,id',
            ],
            'domain_selection_type' => [
                'required',
                'string',
                Rule::in(['category', 'set', 'custom']),
            ],
            'domain_set_id' => [
                'required_if:domain_selection_type,set',
                'nullable',
                'integer',
                'exists:domain_sets,id',
            ],
            'custom_domains' => [
                'required_if:domain_selection_type,custom',
                'nullable',
                'array',
            ],
            'custom_domains.*' => [
                'integer',
                'exists:domains,id',
            ],
            'keywordsDataHolder' => [
                'required',
                'json',
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
            'campaign_no.required' => 'Campaign number is required.',
            'campaign_no.max' => 'Campaign number cannot exceed :max characters.',
            'domain_category_id.required' => 'Please select a domain category.',
            'domain_category_id.exists' => 'The selected domain category does not exist.',
            'article_category_id.exists' => 'The selected article category does not exist.',
            'article_type.required' => 'Please select an article type.',
            'article_type.in' => 'Invalid article type selected.',
            'domain_selection_type.required' => 'Please select a domain selection method.',
            'keywordsDataHolder.required' => 'At least one keyword/URL pair is required.',
            'keywordsDataHolder.json' => 'Invalid keyword data format.',
        ];
    }

    /**
     * Get custom attribute names for error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'campaign_no' => 'campaign number',
            'domain_category_id' => 'domain category',
            'article_category_id' => 'article category',
            'article_type' => 'article type',
            'article_set_id' => 'article set',
            'article_language_id' => 'article language',
            'domain_selection_type' => 'domain selection method',
            'domain_set_id' => 'domain set',
            'custom_domains' => 'custom domains',
            'keywordsDataHolder' => 'keywords data',
        ];
    }
}
