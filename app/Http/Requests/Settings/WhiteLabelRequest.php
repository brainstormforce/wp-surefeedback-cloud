<?php

namespace SureFeedback\App\Http\Requests\Settings;

use SureFeedback\App\Http\Requests\Request;

/**
 * White Label Settings Request
 *
 * Validates requests for updating white label settings.
 *
 * @package SureFeedback\App\Http\Requests\Settings
 */
class WhiteLabelRequest extends Request
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
            'plugin_description' => 'string|max:500',
            'company_name' => 'string|max:255',
            'company_url' => 'url',
            'support_url' => 'url',
            'documentation_url' => 'url',
            'logo_url' => 'url',
            'primary_color' => 'string|max:7', // Hex color
            'secondary_color' => 'string|max:7',
            'hide_branding' => 'boolean',
            'custom_css' => 'string',
            'custom_footer_text' => 'string|max:500',
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
            'plugin_description.max' => 'Plugin description may not be longer than 500 characters.',
            'company_name.max' => 'Company name may not be longer than 255 characters.',
            'company_url.url' => 'Company URL must be a valid URL.',
            'support_url.url' => 'Support URL must be a valid URL.',
            'documentation_url.url' => 'Documentation URL must be a valid URL.',
            'logo_url.url' => 'Logo URL must be a valid URL.',
            'primary_color.max' => 'Primary color must be a valid hex color.',
            'secondary_color.max' => 'Secondary color must be a valid hex color.',
            'custom_footer_text.max' => 'Custom footer text may not be longer than 500 characters.',
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
            'plugin_description' => 'Plugin Description',
            'company_name' => 'Company Name',
            'company_url' => 'Company URL',
            'support_url' => 'Support URL',
            'documentation_url' => 'Documentation URL',
            'logo_url' => 'Logo URL',
            'primary_color' => 'Primary Color',
            'secondary_color' => 'Secondary Color',
            'hide_branding' => 'Hide Branding',
            'custom_css' => 'Custom CSS',
            'custom_footer_text' => 'Custom Footer Text',
        ];
    }
}