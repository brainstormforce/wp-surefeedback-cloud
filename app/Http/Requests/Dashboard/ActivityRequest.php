<?php

namespace SureFeedback\Http\Requests\Dashboard;

use SureFeedback\Http\Requests\Request;

/**
 * Dashboard Activity Request
 *
 * Validates requests for recent activity.
 *
 * @package SureFeedback\App\Http\Requests\Dashboard
 */
class ActivityRequest extends Request
{
    /**
     * Get validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'limit' => 'integer|min:1|max:100',
            'offset' => 'integer|min:0',
            'type' => 'string|in:comment,connection,setting,all',
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
            'limit.min' => 'Limit must be at least 1.',
            'limit.max' => 'Limit may not exceed 100.',
            'offset.min' => 'Offset must be 0 or greater.',
            'type.in' => 'Type must be one of: comment, connection, setting, all.',
        ];
    }
}
