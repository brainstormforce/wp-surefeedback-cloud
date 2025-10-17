<?php

namespace SureFeedback\Http\Requests\Settings;

use SureFeedback\Http\Requests\Request;

/**
 * Settings Update Request
 *
 * Validates requests for updating general settings.
 *
 * @package SureFeedback\App\Http\Requests\Settings
 * @author Anurag Singh <anurags@bsf.io>
 */
class UpdateSettingsRequest extends Request
{
    /**
     * Get validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'plugin_name' => 'string|max:255',
            'parent_url' => 'url',
            'access_token' => 'string|min:10',
            'signature' => 'string|min:32',
            'roles' => 'array',
            'roles.*' => 'string',
            'guest_comments' => 'boolean',
            'admin_dashboard_comments' => 'boolean',
            'auto_approve' => 'boolean',
            'notification_email' => 'email',
            'webhook_url' => 'url',
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
            'plugin_name.max' => 'Plugin name may not be longer than 255 characters.',
            'parent_url.url' => 'Parent URL must be a valid URL.',
            'access_token.min' => 'Access token must be at least 10 characters.',
            'signature.min' => 'Signature must be at least 32 characters.',
            'roles.array' => 'Roles must be an array.',
            'roles.*.string' => 'Each role must be a string.',
            'notification_email.email' => 'Notification email must be a valid email address.',
            'webhook_url.url' => 'Webhook URL must be a valid URL.',
        ];
    }

    /**
     * Get custom attribute names
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'plugin_name' => 'Plugin Name',
            'parent_url' => 'Parent URL',
            'access_token' => 'Access Token',
            'guest_comments' => 'Guest Comments',
            'admin_dashboard_comments' => 'Admin Dashboard Comments',
            'auto_approve' => 'Auto Approve',
            'notification_email' => 'Notification Email',
            'webhook_url' => 'Webhook URL',
        ];
    }
}