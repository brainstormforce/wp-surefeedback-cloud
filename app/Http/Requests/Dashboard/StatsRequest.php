<?php

namespace SureFeedback\Http\Requests\Dashboard;

use SureFeedback\Http\Requests\Request;

/**
 * Dashboard Stats Request
 *
 * Validates requests for dashboard statistics.
 *
 * @package SureFeedback\App\Http\Requests\Dashboard
 * @author Anurag Singh <anurags@bsf.io>
 */
class StatsRequest extends Request
{
    /**
     * Get validation rules
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'period' => 'string|in:day,week,month,year,all',
            'refresh' => 'boolean',
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
            'period.in' => 'Period must be one of: day, week, month, year, all.',
        ];
    }
}
