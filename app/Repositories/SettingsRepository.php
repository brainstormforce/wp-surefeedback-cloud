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
    ];

    /**
     * Get all general settings
     *
     * @return array
     */
    public function getGeneralSettings(): array
    {
        return [];
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
        return [];
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
     * @return bool
     */
    public function resetSettings(): bool
    {
        return $this->setOption('settings', $this->defaultSettings);
    }

    /**
     * Export settings
     *
     * @return array
     */
    public function exportSettings(): array
    {
        return [
            'timestamp' => current_time('mysql'),
            'version' => SUREFEEDBACK_VERSION ?? '1.0.0',
            'general' => $this->getGeneralSettings(),
        ];
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
        if (isset($data['general'])) {
            if ($merge) {
                return $this->updateGeneralSettings($data['general']);
            } else {
                return $this->setOption('settings', $this->sanitizeData($data['general']));
            }
        }

        return false;
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
     * @return bool
     */
    public function validateSettings(array $settings): bool
    {
        // Check if all provided keys exist in defaults (no unknown keys)
        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, $this->defaultSettings)) {
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