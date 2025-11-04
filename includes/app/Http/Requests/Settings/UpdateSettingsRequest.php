<?php

namespace SureFeedback\Http\Requests\Settings;

defined('ABSPATH') || exit;

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
            'roles' => 'array',
            'roles.*' => 'string',
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
            'roles.array' => 'Roles must be an array.',
            'roles.*.string' => 'Each role must be a string.',
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
            'roles' => 'User Roles',
        ];
    }
}
