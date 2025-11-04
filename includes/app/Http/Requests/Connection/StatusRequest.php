<?php

namespace SureFeedback\Http\Requests\Connection;

defined('ABSPATH') || exit;

use SureFeedback\Http\Requests\Request;

/**
 * Connection Status Request
 *
 * Validates requests for checking connection status.
 *
 * @package SureFeedback\App\Http\Requests\Connection
 * @author Anurag Singh <anurags@bsf.io>
 */
class StatusRequest extends Request
{
    /**
     * Get validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'force_refresh' => 'boolean',
            'include_details' => 'boolean',
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
            'force_refresh.boolean' => 'Force refresh must be true or false.',
            'include_details.boolean' => 'Include details must be true or false.',
        ];
    }
}
