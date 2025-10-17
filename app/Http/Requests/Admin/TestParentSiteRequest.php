<?php

namespace SureFeedback\Http\Requests\Admin;

use SureFeedback\Http\Requests\Request;

/**
 * Admin Test Parent Site Request
 *
 * Validates requests for testing parent site connection.
 *
 * @package SureFeedback\App\Http\Requests\Admin
 */
class TestParentSiteRequest extends Request
{
    /**
     * Get validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'parent_url' => 'required|url',
            'timeout' => 'integer|min:5|max:60',
        ];
    }

    /**
     * Get custom error messages
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'parent_url.required' => 'Parent URL is required for testing.',
            'parent_url.url' => 'Parent URL must be a valid URL.',
            'timeout.min' => 'Timeout must be at least 5 seconds.',
            'timeout.max' => 'Timeout may not exceed 60 seconds.',
        ];
    }
}
