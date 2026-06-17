<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Article Store/Update Request
 *
 * Validates data for creating or updating articles.
 */
class ArticleRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:' . config('campaign.articles.max_title_length', 255),
            ],
            'description' => [
                'required',
                'string',
                'max:' . config('campaign.articles.max_body_length', 65535),
            ],
            'article_category_id' => [
                'required',
                'integer',
                'exists:article_categories,id',
            ],
            'article_language_id' => [
                'required',
                'integer',
                'exists:article_languages,id',
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
            'name.required' => 'Article title is required.',
            'name.max' => 'Article title cannot exceed :max characters.',
            'description.required' => 'Article content is required.',
            'description.max' => 'Article content cannot exceed :max characters.',
            'article_category_id.required' => 'Please select an article category.',
            'article_category_id.exists' => 'The selected category does not exist.',
            'article_language_id.required' => 'Please select an article language.',
            'article_language_id.exists' => 'The selected language does not exist.',
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
            'name' => 'article title',
            'description' => 'article content',
            'article_category_id' => 'category',
            'article_language_id' => 'language',
        ];
    }
}
