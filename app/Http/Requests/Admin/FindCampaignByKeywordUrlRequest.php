<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FindCampaignByKeywordUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'keyword_url' => ['required', 'string', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword_url.required' => 'Enter a target URL from your keyword–URL pairs.',
            'keyword_url.string' => 'Enter a valid target URL.',
            'keyword_url.max' => 'The URL is too long to search.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->view('admin.reports.find-campaign', [
                'keywordLookupFailed' => true,
                'keywordUrlInput' => $this->input('keyword_url'),
            ], 422)
        );
    }
}
