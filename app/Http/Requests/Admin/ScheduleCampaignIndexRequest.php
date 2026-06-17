<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Schedule Campaign Index Request
 *
 * Validates search, filtering, and date range parameters for scheduled campaign listings.
 * Extends the base campaign index validation with schedule-specific fields.
 */
class ScheduleCampaignIndexRequest extends FormRequest
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
            'status' => [
                'nullable',
                'string',
                'in:queued,running,paused,completed,failed',
            ],
            'from' => [
                'nullable',
                'date',
                'before_or_equal:to',
            ],
            'to' => [
                'nullable',
                'date',
                'after_or_equal:from',
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
            'status.in' => 'Invalid status filter selected.',
            'from.date' => 'Please provide a valid start date.',
            'from.before_or_equal' => 'Start date must be before or equal to end date.',
            'to.date' => 'Please provide a valid end date.',
            'to.after_or_equal' => 'End date must be after or equal to start date.',
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
            'from' => 'start date',
            'to' => 'end date',
            'filter_user' => 'user filter',
        ];
    }
}
