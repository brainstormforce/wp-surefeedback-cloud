<?php

namespace SureFeedback\App\Http\Middleware;

use WP_REST_Request;
use WP_Error;
use SureFeedback\App\Http\Requests\Request;

/**
 * Validation Middleware
 *
 * Handles request validation using Request classes.
 *
 * @package SureFeedback\App\Http\Middleware
 */
class ValidationMiddleware extends Middleware
{
    /**
     * Request class mappings for different endpoints
     *
     * @var array
     */
    protected $requestMappings = [
        // Connection endpoints
        'GET:/surefeedback/v1/connection/status' => 'SureFeedback\App\Http\Requests\Connection\StatusRequest',
        'POST:/surefeedback/v1/connection/connect' => 'SureFeedback\App\Http\Requests\Connection\ConnectRequest',
        'POST:/surefeedback/v1/connection/verify' => 'SureFeedback\App\Http\Requests\Connection\VerifyRequest',
        
        // Settings endpoints
        'PUT:/surefeedback/v1/settings' => 'SureFeedback\App\Http\Requests\Settings\UpdateSettingsRequest',
        'PUT:/surefeedback/v1/settings/general' => 'SureFeedback\App\Http\Requests\Settings\UpdateSettingsRequest',
        'PUT:/surefeedback/v1/settings/white-label' => 'SureFeedback\App\Http\Requests\Settings\WhiteLabelRequest',
        
        // Dashboard endpoints
        'GET:/surefeedback/v1/dashboard/stats' => 'SureFeedback\App\Http\Requests\Dashboard\StatsRequest',
        'GET:/surefeedback/v1/dashboard/activity' => 'SureFeedback\App\Http\Requests\Dashboard\ActivityRequest',
        
        // Admin endpoints
        'POST:/surefeedback/v1/admin/verify' => 'SureFeedback\App\Http\Requests\Admin\VerificationRequest',
        'POST:/surefeedback/v1/admin/test-parent-site' => 'SureFeedback\App\Http\Requests\Admin\TestParentSiteRequest',
        'DELETE:/surefeedback/v1/admin/disconnect' => 'SureFeedback\App\Http\Requests\Admin\DisconnectRequest',
    ];

    /**
     * Handle validation middleware
     *
     * @param WP_REST_Request $request
     * @param callable $next
     * @return mixed
     */
    public function handle(WP_REST_Request $request, callable $next)
    {
        $method = $request->get_method();
        $route = $request->get_route();
        $key = $method . ':' . $route;

        // Check if we have a request class for this endpoint
        if (!isset($this->requestMappings[$key])) {
            // No specific validation required, proceed
            return $next($request);
        }

        $requestClass = $this->requestMappings[$key];

        // Validate that the request class exists
        if (!class_exists($requestClass)) {
            $this->log('validation_error', 'Request class not found', [
                'class' => $requestClass,
                'endpoint' => $key
            ]);
            
            return $this->error(
                'Internal validation error. Request class not found.',
                500
            );
        }

        // Create request instance and validate
        try {
            /** @var Request $requestInstance */
            $requestInstance = $requestClass::createFromWpRequest($request);
            
            if ($requestInstance->fails()) {
                $errors = $requestInstance->errors();
                
                $this->log('validation_failed', 'Request validation failed', [
                    'endpoint' => $key,
                    'errors' => $errors
                ]);
                
                return $this->error(
                    'Validation failed.',
                    422,
                    [
                        'validation_errors' => $errors,
                        'fields' => array_keys($errors)
                    ]
                );
            }

            // Add validated data to the request for use in controllers
            $request->set_attributes([
                'validated_data' => $requestInstance->validated(),
                'request_instance' => $requestInstance
            ]);

            $this->log('validation_success', 'Request validation passed', [
                'endpoint' => $key,
                'validated_fields' => array_keys($requestInstance->validated())
            ]);

        } catch (\Exception $e) {
            $this->log('validation_exception', 'Validation exception occurred', [
                'endpoint' => $key,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->error(
                'Validation error: ' . $e->getMessage(),
                500
            );
        }

        return $next($request);
    }

    /**
     * Get validated data from request
     *
     * @param WP_REST_Request $request
     * @return array
     */
    public static function getValidatedData(WP_REST_Request $request): array
    {
        $attributes = $request->get_attributes();
        return $attributes['validated_data'] ?? [];
    }

    /**
     * Get request instance from request
     *
     * @param WP_REST_Request $request
     * @return Request|null
     */
    public static function getRequestInstance(WP_REST_Request $request): ?Request
    {
        $attributes = $request->get_attributes();
        return $attributes['request_instance'] ?? null;
    }

    /**
     * Manually validate data using a request class
     *
     * @param string $requestClass
     * @param array $data
     * @return array Returns ['valid' => bool, 'errors' => array, 'data' => array]
     */
    public function validateData(string $requestClass, array $data): array
    {
        if (!class_exists($requestClass)) {
            return [
                'valid' => false,
                'errors' => ['class' => ['Request class not found.']],
                'data' => []
            ];
        }

        try {
            /** @var Request $requestInstance */
            $requestInstance = new $requestClass($data);
            
            $valid = !$requestInstance->fails();
            
            return [
                'valid' => $valid,
                'errors' => $requestInstance->errors(),
                'data' => $valid ? $requestInstance->validated() : []
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'errors' => ['exception' => [$e->getMessage()]],
                'data' => []
            ];
        }
    }

    /**
     * Add or update request mapping
     *
     * @param string $method
     * @param string $route
     * @param string $requestClass
     * @return void
     */
    public function addRequestMapping(string $method, string $route, string $requestClass): void
    {
        $key = strtoupper($method) . ':' . $route;
        $this->requestMappings[$key] = $requestClass;
    }

    /**
     * Remove request mapping
     *
     * @param string $method
     * @param string $route
     * @return void
     */
    public function removeRequestMapping(string $method, string $route): void
    {
        $key = strtoupper($method) . ':' . $route;
        unset($this->requestMappings[$key]);
    }

    /**
     * Get all request mappings
     *
     * @return array
     */
    public function getRequestMappings(): array
    {
        return $this->requestMappings;
    }

    /**
     * Validate file uploads
     *
     * @param array $files
     * @param array $allowedTypes
     * @param int $maxSize
     * @return array
     */
    protected function validateFiles(array $files, array $allowedTypes = [], int $maxSize = 2097152): array
    {
        $errors = [];

        foreach ($files as $key => $file) {
            if (!is_array($file) || !isset($file['tmp_name'])) {
                continue;
            }

            // Check if file was uploaded successfully
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[$key][] = 'File upload failed.';
                continue;
            }

            // Check file size
            if ($file['size'] > $maxSize) {
                $errors[$key][] = sprintf(
                    'File size exceeds maximum allowed size of %s.',
                    size_format($maxSize)
                );
            }

            // Check file type
            if (!empty($allowedTypes)) {
                $fileType = wp_check_filetype($file['name']);
                if (!in_array($fileType['ext'], $allowedTypes)) {
                    $errors[$key][] = sprintf(
                        'File type not allowed. Allowed types: %s.',
                        implode(', ', $allowedTypes)
                    );
                }
            }

            // Check if file is actually uploaded
            if (!is_uploaded_file($file['tmp_name'])) {
                $errors[$key][] = 'File was not uploaded properly.';
            }
        }

        return $errors;
    }
}