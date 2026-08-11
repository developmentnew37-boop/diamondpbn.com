<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FindCampaignByReportUrlRequest extends FormRequest
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
            'report_url' => ['required', 'string', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'report_url.required' => 'Campaign not found or you do not have permission to view it.',
            'report_url.string' => 'Campaign not found or you do not have permission to view it.',
            'report_url.max' => 'Campaign not found or you do not have permission to view it.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->view('admin.reports.find-campaign', [
                'lookupFailed' => true,
            ], 422)
        );
    }
}
