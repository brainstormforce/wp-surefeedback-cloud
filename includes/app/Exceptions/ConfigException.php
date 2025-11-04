<?php

namespace SureFeedback\Exceptions;

defined('ABSPATH') || exit;

/**
 * Configuration Exception
 *
 * Exception thrown when configuration-related operations fail.
 * Used for invalid settings, missing configuration, etc.
 *
 * @package SureFeedback\App\Exceptions
 * @author Anurag Singh <anurags@bsf.io>
 */
class ConfigException extends SureFeedbackException
{
    /**
     * Configuration key that caused the error
     *
     * @var string
     */
    protected $config_key;

    /**
     * Expected configuration value type
     *
     * @var string
     */
    protected $expected_type;

    /**
     * Actual configuration value
     *
     * @var mixed
     */
    protected $actual_value;

    /**
     * Create a new configuration exception
     *
     * @param string $message Exception message
     * @param string $config_key Configuration key
     * @param string $expected_type Expected value type
     * @param mixed $actual_value Actual value
     */
    public function __construct(string $message, string $config_key = '', string $expected_type = '', $actual_value = null)
    {
        $this->config_key = $config_key;
        $this->expected_type = $expected_type;
        $this->actual_value = $actual_value;

        $context = [
            'config_key' => $config_key,
            'expected_type' => $expected_type,
            'actual_value' => $actual_value,
            'actual_type' => gettype($actual_value)
        ];

        parent::__construct($message, 1001, null, $context);
    }

    /**
     * Get configuration key
     *
     * @return string
     */
    public function getConfigKey(): string
    {
        return $this->config_key;
    }

    /**
     * Get expected type
     *
     * @return string
     */
    public function getExpectedType(): string
    {
        return $this->expected_type;
    }

    /**
     * Get actual value
     *
     * @return mixed
     */
    public function getActualValue()
    {
        return $this->actual_value;
    }

    /**
     * Create exception for missing configuration
     *
     * @param string $config_key Configuration key
     * @return static
     */
    public static function missing(string $config_key): self
    {
        return new static(
            "Required configuration '{$config_key}' is missing",
            $config_key,
            'any',
            null
        );
    }

    /**
     * Create exception for invalid type
     *
     * @param string $config_key Configuration key
     * @param string $expected_type Expected type
     * @param mixed $actual_value Actual value
     * @return static
     */
    public static function invalidType(string $config_key, string $expected_type, $actual_value): self
    {
        $actual_type = gettype($actual_value);
        return new static(
            "Configuration '{$config_key}' must be of type {$expected_type}, {$actual_type} given",
            $config_key,
            $expected_type,
            $actual_value
        );
    }

    /**
     * Create exception for invalid value
     *
     * @param string $config_key Configuration key
     * @param mixed $actual_value Actual value
     * @param array $valid_values Valid values
     * @return static
     */
    public static function invalidValue(string $config_key, $actual_value, array $valid_values = []): self
    {
        $message = "Configuration '{$config_key}' has invalid value";
        
        if (!empty($valid_values)) {
            $message .= ". Valid values are: " . implode(', ', $valid_values);
        }

        return new static(
            $message,
            $config_key,
            'valid_option',
            $actual_value
        );
    }

    /**
     * Create exception for invalid URL
     *
     * @param string $config_key Configuration key
     * @param string $url Invalid URL
     * @return static
     */
    public static function invalidUrl(string $config_key, string $url): self
    {
        return new static(
            "Configuration '{$config_key}' must be a valid URL, '{$url}' given",
            $config_key,
            'url',
            $url
        );
    }

    /**
     * Create exception for invalid email
     *
     * @param string $config_key Configuration key
     * @param string $email Invalid email
     * @return static
     */
    public static function invalidEmail(string $config_key, string $email): self
    {
        return new static(
            "Configuration '{$config_key}' must be a valid email address, '{$email}' given",
            $config_key,
            'email',
            $email
        );
    }

    /**
     * Create exception for out of range value
     *
     * @param string $config_key Configuration key
     * @param mixed $value Actual value
     * @param mixed $min Minimum value
     * @param mixed $max Maximum value
     * @return static
     */
    public static function outOfRange(string $config_key, $value, $min = null, $max = null): self
    {
        $message = "Configuration '{$config_key}' value '{$value}' is out of range";
        
        if ($min !== null && $max !== null) {
            $message .= " (valid range: {$min} - {$max})";
        } elseif ($min !== null) {
            $message .= " (minimum: {$min})";
        } elseif ($max !== null) {
            $message .= " (maximum: {$max})";
        }

        return new static(
            $message,
            $config_key,
            'numeric',
            $value
        );
    }

    /**
     * Create exception for readonly configuration
     *
     * @param string $config_key Configuration key
     * @return static
     */
    public static function readonly(string $config_key): self
    {
        return new static(
            "Configuration '{$config_key}' is readonly and cannot be modified",
            $config_key,
            'readonly',
            null
        );
    }

    /**
     * Create exception for deprecated configuration
     *
     * @param string $config_key Configuration key
     * @param string $replacement Replacement configuration key
     * @return static
     */
    public static function deprecated(string $config_key, string $replacement = ''): self
    {
        $message = "Configuration '{$config_key}' is deprecated";
        
        if (!empty($replacement)) {
            $message .= ". Use '{$replacement}' instead";
        }

        return new static(
            $message,
            $config_key,
            'deprecated',
            null
        );
    }
}
