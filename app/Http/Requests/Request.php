<?php

namespace SureFeedback\App\Http\Requests;

/**
 * Base Request Class
 *
 * Provides base functionality for form request validation
 * and data handling in WordPress environment.
 *
 * @package SureFeedback\App\Http\Requests
 */
abstract class Request
{
    /**
     * The request data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Validation errors
     *
     * @var array
     */
    protected $errors = [];

    /**
     * Create a new request instance
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Get validation rules
     *
     * @return array
     */
    abstract public function rules(): array;

    /**
     * Get custom error messages
     *
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Get custom attribute names
     *
     * @return array
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Validate the request data
     *
     * @return bool
     */
    public function validate(): bool
    {
        $this->errors = [];
        $rules = $this->rules();

        foreach ($rules as $field => $rule) {
            $value = $this->get($field);
            $fieldRules = is_string($rule) ? explode('|', $rule) : $rule;

            foreach ($fieldRules as $singleRule) {
                if (!$this->validateRule($field, $value, $singleRule)) {
                    break; // Stop on first failure for this field
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Validate a single rule
     *
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @return bool
     */
    protected function validateRule(string $field, $value, string $rule): bool
    {
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $ruleParam = $ruleParts[1] ?? null;

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, $this->getErrorMessage($field, 'required'));
                    return false;
                }
                break;

            case 'string':
                if (!is_string($value) && !is_null($value)) {
                    $this->addError($field, $this->getErrorMessage($field, 'string'));
                    return false;
                }
                break;

            case 'email':
                if (!empty($value) && !is_email($value)) {
                    $this->addError($field, $this->getErrorMessage($field, 'email'));
                    return false;
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, $this->getErrorMessage($field, 'url'));
                    return false;
                }
                break;

            case 'min':
                if (!empty($value) && strlen($value) < (int)$ruleParam) {
                    $this->addError($field, $this->getErrorMessage($field, 'min', ['min' => $ruleParam]));
                    return false;
                }
                break;

            case 'max':
                if (!empty($value) && strlen($value) > (int)$ruleParam) {
                    $this->addError($field, $this->getErrorMessage($field, 'max', ['max' => $ruleParam]));
                    return false;
                }
                break;

            case 'boolean':
                if (!is_null($value) && !is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                    $this->addError($field, $this->getErrorMessage($field, 'boolean'));
                    return false;
                }
                break;

            case 'array':
                if (!is_null($value) && !is_array($value)) {
                    $this->addError($field, $this->getErrorMessage($field, 'array'));
                    return false;
                }
                break;

            case 'integer':
                if (!is_null($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, $this->getErrorMessage($field, 'integer'));
                    return false;
                }
                break;
        }

        return true;
    }

    /**
     * Add validation error
     *
     * @param string $field
     * @param string $message
     */
    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * Get error message for field and rule
     *
     * @param string $field
     * @param string $rule
     * @param array $params
     * @return string
     */
    protected function getErrorMessage(string $field, string $rule, array $params = []): string
    {
        $messages = $this->messages();
        $attributes = $this->attributes();
        
        $fieldName = $attributes[$field] ?? ucfirst(str_replace('_', ' ', $field));
        
        if (isset($messages["{$field}.{$rule}"])) {
            return $messages["{$field}.{$rule}"];
        }

        $defaultMessages = [
            'required' => "{$fieldName} is required.",
            'string' => "{$fieldName} must be a string.",
            'email' => "{$fieldName} must be a valid email address.",
            'url' => "{$fieldName} must be a valid URL.",
            'min' => "{$fieldName} must be at least {$params['min']} characters.",
            'max' => "{$fieldName} may not be greater than {$params['max']} characters.",
            'boolean' => "{$fieldName} must be true or false.",
            'array' => "{$fieldName} must be an array.",
            'integer' => "{$fieldName} must be an integer.",
        ];

        return $defaultMessages[$rule] ?? "{$fieldName} is invalid.";
    }

    /**
     * Get request data value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get all request data
     *
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get validated data only
     *
     * @return array
     */
    public function validated(): array
    {
        if (!$this->validate()) {
            return [];
        }

        $rules = $this->rules();
        $validated = [];

        foreach ($rules as $field => $rule) {
            if (isset($this->data[$field])) {
                $validated[$field] = $this->data[$field];
            }
        }

        return $validated;
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Check if validation failed
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !$this->validate();
    }

    /**
     * Set request data
     *
     * @param array $data
     * @return self
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Create request from WP_REST_Request
     *
     * @param \WP_REST_Request $request
     * @return static
     */
    public static function createFromWpRequest(\WP_REST_Request $request): self
    {
        $data = array_merge(
            $request->get_params(),
            $request->get_body_params(),
            $request->get_file_params()
        );

        return new static($data);
    }
}