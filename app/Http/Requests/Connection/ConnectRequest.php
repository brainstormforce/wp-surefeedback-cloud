<?php

namespace SureFeedback\Http\Requests\Connection;

use SureFeedback\Http\Requests\Request;

/**
 * Connection Connect Request
 *
 * Validates requests for establishing connections to parent site.
 *
 * @package SureFeedback\App\Http\Requests\Connection
 * @author Anurag Singh <anurags@bsf.io>
 */
class ConnectRequest extends Request
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
            'signature' => 'required|string|min:32',
            'user_id' => 'integer',
            'user_email' => 'email',
            'site_name' => 'string|max:255',
            'verify_ssl' => 'boolean',
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
            'parent_url.required' => 'Parent URL is required.',
            'parent_url.url' => 'Parent URL must be a valid URL.',
            'access_token.required' => 'Access token is required.',
            'access_token.min' => 'Access token must be at least 10 characters.',
            'signature.required' => 'Signature is required.',
            'signature.min' => 'Signature must be at least 32 characters.',
            'user_email.email' => 'User email must be a valid email address.',
            'site_name.max' => 'Site name may not be longer than 255 characters.',
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
            'parent_url' => 'Parent URL',
            'access_token' => 'Access Token',
            'user_id' => 'User ID',
            'user_email' => 'User Email',
            'site_name' => 'Site Name',
            'verify_ssl' => 'Verify SSL',
        ];
    }
}