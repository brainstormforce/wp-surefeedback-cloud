<?php

namespace SureFeedback\Http\Requests\Admin;

defined('ABSPATH') || exit;

use SureFeedback\Http\Requests\Request;

/**
 * Admin Verification Request
 *
 * Validates requests for admin verification operations.
 *
 * @package SureFeedback\App\Http\Requests\Admin
 * @author Anurag Singh <anurags@bsf.io>
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
