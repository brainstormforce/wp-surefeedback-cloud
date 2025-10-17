<?php

namespace SureFeedback\Exceptions;

use Exception;

/**
 * Base SureFeedback Exception
 *
 * Base exception class for all SureFeedback-specific exceptions.
 * Provides additional context and logging capabilities.
 *
 * @package SureFeedback\App\Exceptions
 * @author Anurag Singh <anurags@bsf.io>
 */
class SureFeedbackException extends Exception
{
    /**
     * Error context data
     *
     * @var array
     */
    protected $context = [];

    /**
     * Error severity level
     *
     * @var string
     */
    protected $severity = 'error';

    /**
     * Whether this exception should be logged
     *
     * @var bool
     */
    protected $should_log = true;

    /**
     * Create a new SureFeedback exception
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     * @param array $context Additional context data
     */
    public function __construct(string $message = '', int $code = 0, Exception $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        
        $this->context = $context;
        
        if ($this->should_log) {
            $this->logException();
        }
    }

    /**
     * Get error context data
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set error context data
     *
     * @param array $context Context data
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context data
     *
     * @param string $key Context key
     * @param mixed $value Context value
     * @return self
     */
    public function addContext(string $key, $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Get severity level
     *
     * @return string
     */
    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * Set severity level
     *
     * @param string $severity Severity level
     * @return self
     */
    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;
        return $this;
    }

    /**
     * Check if exception should be logged
     *
     * @return bool
     */
    public function shouldLog(): bool
    {
        return $this->should_log;
    }

    /**
     * Set logging preference
     *
     * @param bool $should_log Whether to log this exception
     * @return self
     */
    public function setShouldLog(bool $should_log): self
    {
        $this->should_log = $should_log;
        return $this;
    }

    /**
     * Log the exception
     *
     * @return void
     */
    protected function logException(): void
    {
        // Exception logging disabled
    }

    /**
     * Convert exception to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'severity' => $this->severity,
            'context' => $this->context
        ];
    }

    /**
     * Convert exception to WordPress Error
     *
     * @return \WP_Error
     */
    public function toWpError(): \WP_Error
    {
        return new \WP_Error(
            'surefeedback_exception',
            $this->getMessage(),
            $this->toArray()
        );
    }

    /**
     * Create exception from WordPress Error
     *
     * @param \WP_Error $wp_error WordPress error object
     * @return static
     */
    public static function fromWpError(\WP_Error $wp_error): self
    {
        $message = $wp_error->get_error_message();
        $code = $wp_error->get_error_code();
        $data = $wp_error->get_error_data();
        
        $context = is_array($data) ? $data : ['error_data' => $data];
        
        return new static($message, 0, null, $context);
    }

    /**
     * Create a new exception for invalid configuration
     *
     * @param string $message Error message
     * @param array $context Context data
     * @return static
     */
    public static function invalidConfiguration(string $message, array $context = []): self
    {
        return new static($message, 1001, null, $context);
    }

    /**
     * Create a new exception for API errors
     *
     * @param string $message Error message
     * @param int $http_code HTTP status code
     * @param array $context Context data
     * @return static
     */
    public static function apiError(string $message, int $http_code = 500, array $context = []): self
    {
        $context['http_code'] = $http_code;
        return new static($message, 2000 + $http_code, null, $context);
    }

    /**
     * Create a new exception for authentication errors
     *
     * @param string $message Error message
     * @param array $context Context data
     * @return static
     */
    public static function authenticationError(string $message, array $context = []): self
    {
        return new static($message, 3001, null, $context);
    }

    /**
     * Create a new exception for authorization errors
     *
     * @param string $message Error message
     * @param array $context Context data
     * @return static
     */
    public static function authorizationError(string $message, array $context = []): self
    {
        return new static($message, 3002, null, $context);
    }

    /**
     * Create a new exception for validation errors
     *
     * @param string $message Error message
     * @param array $context Context data
     * @return static
     */
    public static function validationError(string $message, array $context = []): self
    {
        return new static($message, 4001, null, $context);
    }

    /**
     * Create a new exception for rate limiting
     *
     * @param string $message Error message
     * @param array $context Context data
     * @return static
     */
    public static function rateLimitError(string $message, array $context = []): self
    {
        return new static($message, 4029, null, $context);
    }

    /**
     * Create a new exception for not found errors
     *
     * @param string $message Error message
     * @param array $context Context data
     * @return static
     */
    public static function notFoundError(string $message, array $context = []): self
    {
        return new static($message, 4004, null, $context);
    }

    /**
     * Create a new exception for service unavailable
     *
     * @param string $message Error message
     * @param array $context Context data
     * @return static
     */
    public static function serviceUnavailable(string $message, array $context = []): self
    {
        return new static($message, 5003, null, $context);
    }
}