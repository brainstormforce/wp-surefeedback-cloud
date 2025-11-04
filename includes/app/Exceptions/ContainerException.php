<?php

namespace SureFeedback\Exceptions;

defined('ABSPATH') || exit;

/**
 * Container Exception
 *
 * Exception thrown when dependency injection container operations fail.
 * Used for service resolution, binding issues, etc.
 *
 * @package SureFeedback\App\Exceptions
 * @author Anurag Singh <anurags@bsf.io>
 */
class ContainerException extends SureFeedbackException
{
    /**
     * Service identifier that caused the error
     *
     * @var string
     */
    protected $service_id;

    /**
     * Container operation type
     *
     * @var string
     */
    protected $operation;

    /**
     * Create a new container exception
     *
     * @param string $message Exception message
     * @param string $service_id Service identifier
     * @param string $operation Container operation
     */
    public function __construct(string $message, string $service_id = '', string $operation = '')
    {
        $this->service_id = $service_id;
        $this->operation = $operation;

        $context = [
            'service_id' => $service_id,
            'operation' => $operation
        ];

        parent::__construct($message, 2001, null, $context);
    }

    /**
     * Get service identifier
     *
     * @return string
     */
    public function getServiceId(): string
    {
        return $this->service_id;
    }

    /**
     * Get container operation
     *
     * @return string
     */
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * Create exception for service not found
     *
     * @param string $service_id Service identifier
     * @return static
     */
    public static function serviceNotFound(string $service_id): self
    {
        return new static(
            "Service '{$service_id}' not found in container",
            $service_id,
            'resolve'
        );
    }

    /**
     * Create exception for circular dependency
     *
     * @param string $service_id Service identifier
     * @param array $dependency_chain Dependency chain
     * @return static
     */
    public static function circularDependency(string $service_id, array $dependency_chain = []): self
    {
        $chain_str = implode(' -> ', $dependency_chain);
        
        return new static(
            "Circular dependency detected for service '{$service_id}'. Chain: {$chain_str}",
            $service_id,
            'resolve'
        );
    }

    /**
     * Create exception for invalid service binding
     *
     * @param string $service_id Service identifier
     * @param string $reason Reason for invalid binding
     * @return static
     */
    public static function invalidBinding(string $service_id, string $reason = ''): self
    {
        $message = "Invalid binding for service '{$service_id}'";
        
        if (!empty($reason)) {
            $message .= ": {$reason}";
        }

        return new static(
            $message,
            $service_id,
            'bind'
        );
    }

    /**
     * Create exception for non-instantiable service
     *
     * @param string $service_id Service identifier
     * @return static
     */
    public static function nonInstantiable(string $service_id): self
    {
        return new static(
            "Service '{$service_id}' is not instantiable",
            $service_id,
            'instantiate'
        );
    }

    /**
     * Create exception for missing dependencies
     *
     * @param string $service_id Service identifier
     * @param array $missing_dependencies Missing dependencies
     * @return static
     */
    public static function missingDependencies(string $service_id, array $missing_dependencies): self
    {
        $deps = implode(', ', $missing_dependencies);
        
        return new static(
            "Service '{$service_id}' has missing dependencies: {$deps}",
            $service_id,
            'resolve'
        );
    }

    /**
     * Create exception for invalid constructor parameters
     *
     * @param string $service_id Service identifier
     * @param string $parameter Parameter name
     * @return static
     */
    public static function invalidParameter(string $service_id, string $parameter): self
    {
        return new static(
            "Service '{$service_id}' has invalid constructor parameter '{$parameter}'",
            $service_id,
            'instantiate'
        );
    }

    /**
     * Create exception for already bound service
     *
     * @param string $service_id Service identifier
     * @return static
     */
    public static function alreadyBound(string $service_id): self
    {
        return new static(
            "Service '{$service_id}' is already bound in container",
            $service_id,
            'bind'
        );
    }

    /**
     * Create exception for singleton violation
     *
     * @param string $service_id Service identifier
     * @return static
     */
    public static function singletonViolation(string $service_id): self
    {
        return new static(
            "Service '{$service_id}' is a singleton and cannot be re-instantiated",
            $service_id,
            'instantiate'
        );
    }

    /**
     * Create exception for invalid factory
     *
     * @param string $service_id Service identifier
     * @param string $factory_type Factory type
     * @return static
     */
    public static function invalidFactory(string $service_id, string $factory_type): self
    {
        return new static(
            "Service '{$service_id}' has invalid factory of type '{$factory_type}'",
            $service_id,
            'factory'
        );
    }

    /**
     * Create exception for method not found
     *
     * @param string $service_id Service identifier
     * @param string $method Method name
     * @return static
     */
    public static function methodNotFound(string $service_id, string $method): self
    {
        return new static(
            "Method '{$method}' not found in service '{$service_id}'",
            $service_id,
            'method_call'
        );
    }
}
