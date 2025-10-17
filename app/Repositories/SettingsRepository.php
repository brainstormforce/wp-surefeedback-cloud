<?php

namespace SureFeedback\Repositories;

/**
 * Settings Repository
 *
 * Handles all settings-related data operations.
 *
 * @package SureFeedback\App\Repositories
 * @author Anurag Singh <anurags@bsf.io>
 */
class SettingsRepository extends BaseRepository
{
    /**
     * Default settings structure
     *
     * @var array
     */
    protected $defaultSettings = [
        'plugin_name' => 'SureFeedback',
        'roles' => ['administrator', 'editor'],
        'guest_comments' => false,
        'admin_dashboard_comments' => true,
        'auto_approve' => false,
        'notification_email' => '',
        'webhook_url' => '',
        'widget_enabled' => true,
        'debug_mode' => false,
    ];

    /**
     * Default white label settings
     *
     * @var array
     */
    protected $defaultWhiteLabelSettings = [
        'plugin_name' => 'SureFeedback',
        'plugin_description' => 'Collect note-style feedback from your clients.',
        'company_name' => 'Brainstorm Force',
        'company_url' => 'https://www.brainstormforce.com',
        'support_url' => 'https://surefeedback.com/support',
        'documentation_url' => 'https://surefeedback.com/docs',
        'logo_url' => '',
        'primary_color' => '#3b82f6',
        'secondary_color' => '#6b7280',
        'hide_branding' => false,
        'custom_css' => '',
        'custom_footer_text' => '',
    ];

    /**
     * Get all general settings
     *
     * @return array
     */
    public function getGeneralSettings(): array
    {
        return [
            'surefeedback_role_can_comment' => get_option('surefeedback_role_can_comment', ['administrator']),
            'surefeedback_guest_comments_enabled' => (bool) get_option('surefeedback_guest_comments', false),
            'surefeedback_admin' => (bool) get_option('surefeedback_admin_can_comment', true),
        ];
    }

    /**
     * Get specific setting value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->getGeneralSettings();
        return $settings[$key] ?? $default;
    }

    /**
     * Update general settings
     *
     * @param array $settings
     * @return array Updated settings
     */
    public function updateGeneralSettings(array $settings): array
    {
        $updated = [];
        
        // Update surefeedback_role_can_comment
        if (isset($settings['surefeedback_role_can_comment'])) {
            $roles = is_array($settings['surefeedback_role_can_comment']) 
                ? array_map('sanitize_text_field', $settings['surefeedback_role_can_comment'])
                : [];
            update_option('surefeedback_role_can_comment', $roles);
            $updated['surefeedback_role_can_comment'] = $roles;
        }
        
        // Update surefeedback_guest_comments_enabled
        if (isset($settings['surefeedback_guest_comments_enabled'])) {
            $guest_comments = (bool) $settings['surefeedback_guest_comments_enabled'];
            update_option('surefeedback_guest_comments', $guest_comments);
            $updated['surefeedback_guest_comments_enabled'] = $guest_comments;
        }
        
        // Update surefeedback_admin
        if (isset($settings['surefeedback_admin'])) {
            $admin_comments = (bool) $settings['surefeedback_admin'];
            update_option('surefeedback_admin_can_comment', $admin_comments);
            $updated['surefeedback_admin'] = $admin_comments;
        }
        
        return $updated;
    }

    /**
     * Update specific setting
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function updateSetting(string $key, $value): bool
    {
        $settings = $this->getGeneralSettings();
        $settings[$key] = $this->sanitizeData($value);
        
        return $this->setOption('settings', $settings);
    }

    /**
     * Get white label settings
     *
     * @return array
     */
    public function getWhiteLabelSettings(): array
    {
        $settings = $this->getOption('white_label_settings', []);
        return array_merge($this->defaultWhiteLabelSettings, $settings);
    }

    /**
     * Update white label settings
     *
     * @param array $settings
     * @return bool
     */
    public function updateWhiteLabelSettings(array $settings): bool
    {
        $currentSettings = $this->getWhiteLabelSettings();
        $newSettings = array_merge($currentSettings, $this->sanitizeData($settings));
        
        return $this->setOption('white_label_settings', $newSettings);
    }

    /**
     * Get plugin roles
     *
     * @return array
     */
    public function getPluginRoles(): array
    {
        return (array) $this->getSetting('roles', $this->defaultSettings['roles']);
    }

    /**
     * Set plugin roles
     *
     * @param array $roles
     * @return bool
     */
    public function setPluginRoles(array $roles): bool
    {
        $sanitizedRoles = array_map('sanitize_text_field', $roles);
        return $this->updateSetting('roles', $sanitizedRoles);
    }

    /**
     * Check if guest comments are enabled
     *
     * @return bool
     */
    public function areGuestCommentsEnabled(): bool
    {
        return (bool) $this->getSetting('guest_comments', false);
    }

    /**
     * Set guest comments enabled status
     *
     * @param bool $enabled
     * @return bool
     */
    public function setGuestCommentsEnabled(bool $enabled): bool
    {
        return $this->updateSetting('guest_comments', $enabled);
    }

    /**
     * Check if admin dashboard comments are enabled
     *
     * @return bool
     */
    public function areAdminDashboardCommentsEnabled(): bool
    {
        return (bool) $this->getSetting('admin_dashboard_comments', true);
    }

    /**
     * Set admin dashboard comments enabled status
     *
     * @param bool $enabled
     * @return bool
     */
    public function setAdminDashboardCommentsEnabled(bool $enabled): bool
    {
        return $this->updateSetting('admin_dashboard_comments', $enabled);
    }

    /**
     * Get notification email
     *
     * @return string|null
     */
    public function getNotificationEmail(): ?string
    {
        $email = $this->getSetting('notification_email', '');
        return empty($email) ? null : $email;
    }

    /**
     * Set notification email
     *
     * @param string $email
     * @return bool
     */
    public function setNotificationEmail(string $email): bool
    {
        $sanitizedEmail = sanitize_email($email);
        return $this->updateSetting('notification_email', $sanitizedEmail);
    }

    /**
     * Get webhook URL
     *
     * @return string|null
     */
    public function getWebhookUrl(): ?string
    {
        $url = $this->getSetting('webhook_url', '');
        return empty($url) ? null : $url;
    }

    /**
     * Set webhook URL
     *
     * @param string $url
     * @return bool
     */
    public function setWebhookUrl(string $url): bool
    {
        $sanitizedUrl = esc_url_raw($url);
        return $this->updateSetting('webhook_url', $sanitizedUrl);
    }

    /**
     * Reset settings to default
     *
     * @param string $type 'general', 'white_label', or 'all'
     * @return bool
     */
    public function resetSettings(string $type = 'all'): bool
    {
        $success = true;

        switch ($type) {
            case 'general':
                $success = $this->setOption('settings', $this->defaultSettings);
                break;
            
            case 'white_label':
                $success = $this->setOption('white_label_settings', $this->defaultWhiteLabelSettings);
                break;
            
            case 'all':
                $success = $this->setOption('settings', $this->defaultSettings);
                $success = $success && $this->setOption('white_label_settings', $this->defaultWhiteLabelSettings);
                break;
        }

        return $success;
    }

    /**
     * Export settings
     *
     * @param array $sections Sections to export ('general', 'white_label')
     * @return array
     */
    public function exportSettings(array $sections = ['general', 'white_label']): array
    {
        $export = [
            'timestamp' => current_time('mysql'),
            'version' => SUREFEEDBACK_VERSION ?? '1.0.0',
        ];

        if (in_array('general', $sections)) {
            $export['general'] = $this->getGeneralSettings();
        }

        if (in_array('white_label', $sections)) {
            $export['white_label'] = $this->getWhiteLabelSettings();
        }

        return $export;
    }

    /**
     * Import settings
     *
     * @param array $data
     * @param bool $merge Whether to merge with existing settings or replace
     * @return bool
     */
    public function importSettings(array $data, bool $merge = true): bool
    {
        $success = true;

        if (isset($data['general'])) {
            if ($merge) {
                $success = $success && $this->updateGeneralSettings($data['general']);
            } else {
                $success = $success && $this->setOption('settings', $this->sanitizeData($data['general']));
            }
        }

        if (isset($data['white_label'])) {
            if ($merge) {
                $success = $success && $this->updateWhiteLabelSettings($data['white_label']);
            } else {
                $success = $success && $this->setOption('white_label_settings', $this->sanitizeData($data['white_label']));
            }
        }

        return $success;
    }

    /**
     * Get all settings for backup
     *
     * @return array
     */
    public function getAllSettings(): array
    {
        return [
            'general' => $this->getGeneralSettings(),
            'white_label' => $this->getWhiteLabelSettings(),
        ];
    }

    /**
     * Backup current settings
     *
     * @return bool
     */
    public function backupSettings(): bool
    {
        $backup = [
            'timestamp' => current_time('mysql'),
            'settings' => $this->getAllSettings(),
        ];

        return $this->setOption('settings_backup', $backup);
    }

    /**
     * Restore settings from backup
     *
     * @return bool
     */
    public function restoreFromBackup(): bool
    {
        $backup = $this->getOption('settings_backup');
        
        if (!$backup || !isset($backup['settings'])) {
            return false;
        }

        return $this->importSettings($backup['settings'], false);
    }

    /**
     * Validate settings data structure
     *
     * @param array $settings
     * @param string $type
     * @return bool
     */
    public function validateSettings(array $settings, string $type = 'general'): bool
    {
        $defaults = $type === 'white_label' ? $this->defaultWhiteLabelSettings : $this->defaultSettings;
        
        // Check if all provided keys exist in defaults (no unknown keys)
        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, $defaults)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all settings (alias for getAllSettings)
     *
     * @return array
     */
    public function getSettings(): array
    {
        return $this->getAllSettings();
    }
}