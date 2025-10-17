<?php

namespace SureFeedback\Repositories;

/**
 * Base Repository Class
 *
 * Provides base functionality for data access operations
 * in WordPress environment using WordPress options API.
 *
 * @package SureFeedback\App\Repositories
 * @author Anurag Singh <anurags@bsf.io>
 */
abstract class BaseRepository
{
    /**
     * The option prefix for this repository
     *
     * @var string
     */
    protected $prefix = 'surefeedback_';

    /**
     * Cache for loaded options
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Get option with caching
     *
     * @param string $key
     * @param mixed $default
     * @param bool $useCache
     * @return mixed
     */
    protected function getOption(string $key, $default = null, bool $useCache = true)
    {
        $fullKey = $this->prefix . $key;

        if ($useCache && isset($this->cache[$fullKey])) {
            return $this->cache[$fullKey];
        }

        $value = get_option($fullKey, $default);
        
        if ($useCache) {
            $this->cache[$fullKey] = $value;
        }

        return $value;
    }

    /**
     * Set option with cache update
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    protected function setOption(string $key, $value): bool
    {
        $fullKey = $this->prefix . $key;
        
        $result = update_option($fullKey, $value);
        
        if ($result) {
            $this->cache[$fullKey] = $value;
        }

        return $result;
    }

    /**
     * Delete option with cache removal
     *
     * @param string $key
     * @return bool
     */
    protected function deleteOption(string $key): bool
    {
        $fullKey = $this->prefix . $key;
        
        $result = delete_option($fullKey);
        
        if ($result) {
            unset($this->cache[$fullKey]);
        }

        return $result;
    }

    /**
     * Get multiple options with a pattern
     *
     * @param string $pattern
     * @return array
     */
    protected function getOptionsWithPattern(string $pattern): array
    {
        global $wpdb;
        
        $fullPattern = $this->prefix . $pattern;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $fullPattern
            ),
            ARRAY_A
        );

        $options = [];
        foreach ($results as $row) {
            $key = str_replace($this->prefix, '', $row['option_name']);
            $options[$key] = maybe_unserialize($row['option_value']);
        }

        return $options;
    }

    /**
     * Set multiple options at once
     *
     * @param array $options
     * @return bool
     */
    protected function setMultipleOptions(array $options): bool
    {
        $success = true;
        
        foreach ($options as $key => $value) {
            if (!$this->setOption($key, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Clear cache for this repository
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Get transient with caching
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getTransient(string $key, $default = null)
    {
        $fullKey = $this->prefix . $key;
        
        $value = get_transient($fullKey);
        
        return $value !== false ? $value : $default;
    }

    /**
     * Set transient
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return bool
     */
    protected function setTransient(string $key, $value, int $expiration = 3600): bool
    {
        $fullKey = $this->prefix . $key;
        
        return set_transient($fullKey, $value, $expiration);
    }

    /**
     * Delete transient
     *
     * @param string $key
     * @return bool
     */
    protected function deleteTransient(string $key): bool
    {
        $fullKey = $this->prefix . $key;
        
        return delete_transient($fullKey);
    }

    /**
     * Sanitize data before storage
     *
     * @param mixed $data
     * @return mixed
     */
    protected function sanitizeData($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeData'], $data);
        }

        if (is_string($data)) {
            return sanitize_text_field($data);
        }

        return $data;
    }

    /**
     * Validate data structure
     *
     * @param array $data
     * @param array $required
     * @return bool
     */
    protected function validateDataStructure(array $data, array $required): bool
    {
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }

        return true;
    }
}