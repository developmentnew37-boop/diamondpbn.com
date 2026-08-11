<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkReplaceCampaignDomainsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) auth('admin')->user()?->canCreateCampaigns();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'failed_domains' => ['required', 'string', 'max:50000'],
            'replacement_domains' => ['nullable', 'string', 'max:50000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
