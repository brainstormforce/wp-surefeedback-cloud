<?php

namespace SureFeedback\Exceptions;

/**
 * API Exception
 *
 * Exception thrown when API operations fail.
 * Includes HTTP status codes and response handling.
 *
 * @package SureFeedback\App\Exceptions
 */
class ApiException extends SureFeedbackException
{
    /**
     * HTTP status code
     *
     * @var int
     */
    protected $http_code;

    /**
     * API response data
     *
     * @var array
     */
    protected $response_data = [];

    /**
     * Create a new API exception
     *
     * @param string $message Exception message
     * @param int $http_code HTTP status code
     * @param array $response_data API response data
     * @param array $context Additional context
     */
    public function __construct(string $message = '', int $http_code = 500, array $response_data = [], array $context = [])
    {
        $this->http_code = $http_code;
        $this->response_data = $response_data;
        
        $context['http_code'] = $http_code;
        $context['response_data'] = $response_data;
        
        parent::__construct($message, $http_code, null, $context);
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->http_code;
    }

    /**
     * Get API response data
     *
     * @return array
     */
    public function getResponseData(): array
    {
        return $this->response_data;
    }

    /**
     * Set API response data
     *
     * @param array $response_data Response data
     * @return self
     */
    public function setResponseData(array $response_data): self
    {
        $this->response_data = $response_data;
        $this->context['response_data'] = $response_data;
        return $this;
    }

    /**
     * Convert to REST response
     *
     * @return \WP_REST_Response
     */
    public function toRestResponse(): \WP_REST_Response
    {
        $response_data = [
            'code' => 'surefeedback_api_error',
            'message' => $this->getMessage(),
            'data' => [
                'status' => $this->http_code,
                'details' => $this->response_data
            ]
        ];

        return new \WP_REST_Response($response_data, $this->http_code);
    }

    /**
     * Create exception for connection timeout
     *
     * @param string $endpoint API endpoint
     * @param int $timeout Timeout duration
     * @return static
     */
    public static function connectionTimeout(string $endpoint, int $timeout): self
    {
        return new static(
            "Connection to {$endpoint} timed out after {$timeout} seconds",
            408,
            ['endpoint' => $endpoint, 'timeout' => $timeout]
        );
    }

    /**
     * Create exception for invalid response
     *
     * @param string $endpoint API endpoint
     * @param mixed $response Invalid response
     * @return static
     */
    public static function invalidResponse(string $endpoint, $response): self
    {
        return new static(
            "Invalid response from {$endpoint}",
            502,
            ['endpoint' => $endpoint, 'response' => $response]
        );
    }

    /**
     * Create exception for unauthorized access
     *
     * @param string $endpoint API endpoint
     * @return static
     */
    public static function unauthorized(string $endpoint): self
    {
        return new static(
            "Unauthorized access to {$endpoint}",
            401,
            ['endpoint' => $endpoint]
        );
    }

    /**
     * Create exception for forbidden access
     *
     * @param string $endpoint API endpoint
     * @return static
     */
    public static function forbidden(string $endpoint): self
    {
        return new static(
            "Forbidden access to {$endpoint}",
            403,
            ['endpoint' => $endpoint]
        );
    }

    /**
     * Create exception for not found
     *
     * @param string $endpoint API endpoint
     * @return static
     */
    public static function notFound(string $endpoint): self
    {
        return new static(
            "Endpoint {$endpoint} not found",
            404,
            ['endpoint' => $endpoint]
        );
    }

    /**
     * Create exception for rate limiting
     *
     * @param string $endpoint API endpoint
     * @param int $retry_after Retry after seconds
     * @return static
     */
    public static function rateLimited(string $endpoint, int $retry_after = 60): self
    {
        return new static(
            "Rate limited on {$endpoint}. Retry after {$retry_after} seconds",
            429,
            ['endpoint' => $endpoint, 'retry_after' => $retry_after]
        );
    }

    /**
     * Create exception for server error
     *
     * @param string $endpoint API endpoint
     * @param int $status_code HTTP status code
     * @return static
     */
    public static function serverError(string $endpoint, int $status_code = 500): self
    {
        return new static(
            "Server error on {$endpoint}",
            $status_code,
            ['endpoint' => $endpoint]
        );
    }
}