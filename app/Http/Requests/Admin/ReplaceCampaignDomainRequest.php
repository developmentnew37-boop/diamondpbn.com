<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceCampaignDomainRequest extends FormRequest
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
            'new_domain_name' => ['required', 'string', 'max:255'],
            'expected_old_domain_id' => ['required', 'integer', 'exists:domains,id'],
            'request_uuid' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
