<?php

namespace SureFeedback\Http\Requests\Connection;

use SureFeedback\Http\Requests\Request;

/**
 * Connection Verify Request
 *
 * Validates requests for verifying connections.
 *
 * @package SureFeedback\App\Http\Requests\Connection
 * @author Anurag Singh <anurags@bsf.io>
 */
class VerifyRequest extends Request
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
            'access_token' => 'required|string|min:10',
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
            'parent_url.required' => 'Parent URL is required for verification.',
            'parent_url.url' => 'Parent URL must be a valid URL.',
            'access_token.required' => 'Access token is required for verification.',
            'access_token.min' => 'Access token must be at least 10 characters.',
            'timeout.integer' => 'Timeout must be a number.',
            'timeout.min' => 'Timeout must be at least 5 seconds.',
            'timeout.max' => 'Timeout may not exceed 60 seconds.',
        ];
    }
}