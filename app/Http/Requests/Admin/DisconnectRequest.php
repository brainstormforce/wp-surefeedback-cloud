<?php

namespace SureFeedback\Http\Requests\Admin;

use SureFeedback\Http\Requests\Request;

/**
 * Admin Disconnect Request
 *
 * Validates requests for disconnecting site.
 *
 * @package SureFeedback\App\Http\Requests\Admin
 * @author Anurag Singh <anurags@bsf.io>
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
