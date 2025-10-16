<?php

/**
 * Base Controller - Laravel-inspired controller
 *
 * @package SureFeedback\App\Http\Controllers
 */

namespace SureFeedback\Http\Controllers;

use SureFeedback\Application;
use SureFeedback\Contracts\Config_Interface;
use SureFeedback\Contracts\Logger_Interface;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Base Controller class
 */
abstract class Controller
{
    /**
     * Application instance
     *
     * @var Application
     */
    protected $app;

    /**
     * Configuration instance
     *
     * @var Config_Interface
     */
    protected $config;

    /**
     * Logger instance
     *
     * @var Logger_Interface
     */
    protected $logger;

    /**
     * Create a new controller instance
     *
     * @param Application|null      $app    Application instance.
     * @param Config_Interface|null $config Configuration instance.
     * @param Logger_Interface|null $logger Logger instance.
     */
    public function __construct(?Application $app = null, ?Config_Interface $config = null, ?Logger_Interface $logger = null)
    {
        $this->app = $app;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Return a successful response
     *
     * @param mixed  $data    Response data.
     * @param string $message Response message.
     * @param int    $status  HTTP status code.
     * @return WP_REST_Response
     */
    protected function success($data = null, string $message = '', int $status = 200): WP_REST_Response
    {
        $response = [
            'success' => true,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message) {
            $response['message'] = $message;
        }

        return new WP_REST_Response($response, $status);
    }

    /**
     * Return an error response
     *
     * @param string $message Error message.
     * @param mixed  $data    Error data.
     * @param int    $status  HTTP status code.
     * @return WP_Error
     */
    protected function error(string $message, $data = null, int $status = 400): WP_Error
    {
        $error_data = ['status' => $status];
        
        if ($data !== null) {
            $error_data['data'] = $data;
        }

        return new WP_Error('rest_error', $message, $error_data);
    }

    /**
     * Validate request data
     *
     * @param WP_REST_Request $request Request object.
     * @param array           $rules   Validation rules.
     * @return array|WP_Error
     */
    protected function validate(WP_REST_Request $request, array $rules)
    {
        $data = $request->get_json_params() ?: $request->get_params();
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            if (! $this->validateField($field, $value, $rule, $data)) {
                $errors[$field] = $this->getValidationMessage($field, $rule);
            }
        }

        if (! empty($errors)) {
            return $this->error(__('Validation failed', 'surefeedback'), $errors, 422);
        }

        return $this->sanitizeData($data, $rules);
    }

    /**
     * Validate a single field
     *
     * @param string $field Field name.
     * @param mixed  $value Field value.
     * @param string $rule  Validation rule.
     * @param array  $data  All data.
     * @return bool
     */
    protected function validateField(string $field, $value, string $rule, array $data): bool
    {
        $rules = explode('|', $rule);

        foreach ($rules as $singleRule) {
            $ruleParts = explode(':', $singleRule, 2);
            $ruleName = $ruleParts[0];
            $ruleParam = $ruleParts[1] ?? null;

            switch ($ruleName) {
                case 'required':
                    if (empty($value) && $value !== '0' && $value !== 0) {
                        return false;
                    }
                    break;

                case 'string':
                    if (! is_string($value)) {
                        return false;
                    }
                    break;

                case 'numeric':
                    if (! is_numeric($value)) {
                        return false;
                    }
                    break;

                case 'email':
                    if (! is_email($value)) {
                        return false;
                    }
                    break;

                case 'url':
                    if (! filter_var($value, FILTER_VALIDATE_URL)) {
                        return false;
                    }
                    break;

                case 'min':
                    if (is_string($value) && strlen($value) < (int) $ruleParam) {
                        return false;
                    }
                    if (is_numeric($value) && $value < (int) $ruleParam) {
                        return false;
                    }
                    break;

                case 'max':
                    if (is_string($value) && strlen($value) > (int) $ruleParam) {
                        return false;
                    }
                    if (is_numeric($value) && $value > (int) $ruleParam) {
                        return false;
                    }
                    break;

                case 'in':
                    $validValues = explode(',', $ruleParam);
                    if (! in_array($value, $validValues, true)) {
                        return false;
                    }
                    break;

                case 'boolean':
                    if (! is_bool($value) && ! in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                        return false;
                    }
                    break;

                case 'array':
                    if (! is_array($value)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Get validation error message
     *
     * @param string $field Field name.
     * @param string $rule  Validation rule.
     * @return string
     */
    protected function getValidationMessage(string $field, string $rule): string
    {
        $rules = explode('|', $rule);
        $primaryRule = $rules[0];

        $messages = [
            'required' => sprintf(__('The %s field is required.', 'surefeedback'), $field),
            'string' => sprintf(__('The %s field must be a string.', 'surefeedback'), $field),
            'numeric' => sprintf(__('The %s field must be numeric.', 'surefeedback'), $field),
            'email' => sprintf(__('The %s field must be a valid email.', 'surefeedback'), $field),
            'url' => sprintf(__('The %s field must be a valid URL.', 'surefeedback'), $field),
            'boolean' => sprintf(__('The %s field must be true or false.', 'surefeedback'), $field),
            'array' => sprintf(__('The %s field must be an array.', 'surefeedback'), $field),
        ];

        return $messages[$primaryRule] ?? sprintf(__('The %s field is invalid.', 'surefeedback'), $field);
    }

    /**
     * Sanitize data based on validation rules
     *
     * @param array $data  Data to sanitize.
     * @param array $rules Validation rules.
     * @return array
     */
    protected function sanitizeData(array $data, array $rules): array
    {
        $sanitized = [];

        foreach ($rules as $field => $rule) {
            if (! isset($data[$field])) {
                continue;
            }

            $value = $data[$field];
            $rules = explode('|', $rule);

            foreach ($rules as $singleRule) {
                $ruleParts = explode(':', $singleRule, 2);
                $ruleName = $ruleParts[0];

                switch ($ruleName) {
                    case 'string':
                        $value = sanitize_text_field($value);
                        break;

                    case 'email':
                        $value = sanitize_email($value);
                        break;

                    case 'url':
                        $value = esc_url_raw($value);
                        break;

                    case 'numeric':
                        $value = is_float($value + 0) ? (float) $value : (int) $value;
                        break;

                    case 'boolean':
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;

                    case 'array':
                        if (is_array($value)) {
                            $value = array_map('sanitize_text_field', $value);
                        }
                        break;
                }
            }

            $sanitized[$field] = $value;
        }

        return $sanitized;
    }

    /**
     * Get current user
     *
     * @return \WP_User|null
     */
    protected function user(): ?\WP_User
    {
        $user = wp_get_current_user();
        return $user->exists() ? $user : null;
    }

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    protected function isAuthenticated(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Check if user has capability
     *
     * @param string $capability Capability to check.
     * @return bool
     */
    protected function can(string $capability): bool
    {
        return current_user_can($capability);
    }

    /**
     * Log activity
     *
     * @param string $message Log message.
     * @param array  $context Log context.
     * @param string $level   Log level.
     * @return void
     */
    protected function log(string $message, array $context = [], string $level = 'info'): void
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * Log info message
     *
     * @param string $message Log message.
     * @param array  $context Log context.
     * @return void
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->log($message, $context, 'info');
    }

    /**
     * Log error message
     *
     * @param string $message Log message.
     * @param array  $context Log context.
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log('error', $message, $context);
        }
    }

    /**
     * Validate WordPress nonce
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    protected function validateNonce(\WP_REST_Request $request)
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            $nonce = $request->get_param('_wpnonce');
        }

        if (!$nonce) {
            return $this->error(__('Nonce not provided', 'surefeedback'), null, 403);
        }
        
        // Try verifying with different nonce actions
        $nonce_valid = wp_verify_nonce($nonce, 'wp_rest');
        
        // If wp_rest fails, try other common nonce actions
        if (!$nonce_valid) {
            $nonce_valid = wp_verify_nonce($nonce, 'wp_json');
        }

        if (!$nonce_valid) {
            return $this->error(__('Invalid nonce', 'surefeedback'), null, 403);
        }

        return true;
    }

    /**
     * Validate user capability
     *
     * @param string $capability Capability to check.
     * @return bool|WP_Error
     */
    protected function validateCapability(string $capability)
    {
        if (!current_user_can($capability)) {
            return $this->error(__('Insufficient permissions', 'surefeedback'), null, 403);
        }

        return true;
    }

    /**
     * Get configuration value
     *
     * @param string $key     Configuration key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    protected function config(string $key, $default = null)
    {
        return $this->config->get($key, $default);
    }

    /**
     * Resolve service from container
     *
     * @param string $abstract Service identifier.
     * @return mixed
     */
    protected function resolve(string $abstract)
    {
        return $this->app->resolve($abstract);
    }

    /**
     * Transform data for response
     *
     * @param mixed $data Data to transform.
     * @return mixed
     */
    protected function transform($data)
    {
        // Override in child classes for data transformation
        return $data;
    }

    /**
     * Get paginated data
     *
     * @param \WP_Query $query    Query object.
     * @param array     $items    Items array.
     * @param int       $per_page Items per page.
     * @return array
     */
    protected function paginate(\WP_Query $query, array $items, int $per_page): array
    {
        return [
            'data' => $items,
            'current_page' => max(1, $query->get('paged', 1)),
            'per_page' => $per_page,
            'total' => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'has_more' => $query->get('paged', 1) < $query->max_num_pages,
        ];
    }

    /**
     * Handle exceptions
     *
     * @param \Exception $e Exception to handle.
     * @return WP_Error
     */
    protected function handleException(\Exception $e): WP_Error
    {
        $this->logger->error($e->getMessage(), [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return $this->error(
            $this->app->isEnvironment('development') 
                ? $e->getMessage() 
                : __('An error occurred while processing your request.', 'surefeedback'),
            null,
            500
        );
    }
}