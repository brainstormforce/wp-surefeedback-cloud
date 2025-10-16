<?php

namespace SureFeedback\Http\Requests\Admin;

use SureFeedback\Http\Requests\Request;

/**
 * Admin Verification Request
 *
 * Validates requests for admin verification operations.
 *
 * @package SureFeedback\App\Http\Requests\Admin
 */
class VerificationRequest extends Request
{
    /**
     * Get validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'force_check' => 'boolean',
            'include_details' => 'boolean',
        ];
    }
}

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

/**
 * Admin Disconnect Request
 *
 * Validates requests for disconnecting site.
 *
 * @package SureFeedback\App\Http\Requests\Admin
 */
class DisconnectRequest extends Request
{
    /**
     * Get validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'force' => 'boolean',
            'backup_settings' => 'boolean',
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
            'force.boolean' => 'Force must be true or false.',
            'backup_settings.boolean' => 'Backup settings must be true or false.',
        ];
    }
}