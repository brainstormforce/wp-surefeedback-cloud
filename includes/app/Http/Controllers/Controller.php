<?php

namespace SureFeedback\Http\Controllers;

use SureFeedback\Application;
use SureFeedback\Contracts\Config_Interface;
use SureFeedback\Contracts\Logger_Interface;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Base Controller class
 */
abstract class Controller {

	protected $app;
	protected $config;
	protected $logger;

	public function __construct( ?Application $app = null, ?Config_Interface $config = null, ?Logger_Interface $logger = null ) {
		$this->app    = $app;
		$this->config = $config;
		$this->logger = $logger;
	}

	/**
	 * Return a successful response.
	 *
	 * @param mixed  $data    Response data.
	 * @param string $message Optional message.
	 * @param int    $status  HTTP status.
	 * @return WP_REST_Response
	 */
	protected function success( $data = null, string $message = '', int $status = 200 ): WP_REST_Response {
		$response = array( 'success' => true );

		if ( is_array( $data ) ) {
			$response = array_merge( $response, $data );
		} elseif ( $data !== null ) {
			$response['data'] = $data;
		}

		if ( $message ) {
			$response['message'] = $message;
		}

		// Log important successful events only.
		if ( $status === 200 && isset( $data['webhook_processed'] ) && $data['webhook_processed'] && $this->logger ) {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$context     = array(
				'success_message' => $message,
				'status_code'     => $status,
				'response_keys'   => array_keys( $response ),
				'request_uri'     => $request_uri,
				'timestamp'       => current_time( 'mysql' ),
			);
			$this->logger->log( 'info', $message, $context );
		}

		return new WP_REST_Response( $response, $status );
	}

	/**
	 * Return an error response.
	 *
	 * @param string|array $message Error message or array.
	 * @param mixed        $data    Extra data.
	 * @param int          $status  HTTP status.
	 * @return WP_Error
	 */
	protected function error( $message, $data = null, int $status = 400 ): WP_Error {
		if ( is_array( $message ) ) {
			$error_message = $message['message'] ?? 'An error occurred';
			$error_data    = array_merge( array( 'status' => $status ), $message );
			unset( $error_data['message'] );
		} else {
			$error_message = $message;
			$error_data    = array( 'status' => $status );
		}

		if ( $data !== null ) {
			$error_data['data'] = $data;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$user_agent  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$context = array(
			'error_message' => $error_message,
			'status_code'   => $status,
			'error_data'    => $error_data,
			'request_uri'   => $request_uri,
			'user_agent'    => $user_agent,
			'timestamp'     => current_time( 'mysql' ),
		);

		if ( $this->logger ) {
			$this->logger->log( 'error', $error_message, $context );
		}

		return new WP_Error( 'rest_error', $error_message, $error_data );
	}

	/**
	 * Validate request data based on rules.
	 */
	protected function validate( WP_REST_Request $request, array $rules ) {
		$data   = $request->get_json_params() ?: $request->get_params();
		$errors = array();

		foreach ( $rules as $field => $rule ) {
			$value = $data[ $field ] ?? null;

			if ( ! $this->validateField( $field, $value, $rule, $data ) ) {
				$errors[ $field ] = $this->getValidationMessage( $field, $rule );
			}
		}

		if ( ! empty( $errors ) ) {
			return $this->error( __( 'Validation failed', 'surefeedback' ), $errors, 422 );
		}

		return $this->sanitizeData( $data, $rules );
	}

	/**
	 * Validate individual fields.
	 */
	protected function validateField( string $field, $value, string $rule, array $data ): bool {
		$rules = explode( '|', $rule );

		foreach ( $rules as $singleRule ) {
			$ruleParts = explode( ':', $singleRule, 2 );
			$ruleName  = $ruleParts[0];
			$ruleParam = $ruleParts[1] ?? null;

			switch ( $ruleName ) {
				case 'required':
					if ( empty( $value ) && $value !== '0' && $value !== 0 ) {
						return false;
					}
					break;
				case 'string':
					if ( ! is_string( $value ) ) {
						return false;
					}
					break;
				case 'numeric':
					if ( ! is_numeric( $value ) ) {
						return false;
					}
					break;
				case 'email':
					if ( ! is_email( $value ) ) {
						return false;
					}
					break;
				case 'url':
					if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
						return false;
					}
					break;
				case 'min':
					if ( is_string( $value ) && strlen( $value ) < (int) $ruleParam ) {
						return false;
					}
					if ( is_numeric( $value ) && $value < (int) $ruleParam ) {
						return false;
					}
					break;
				case 'max':
					if ( is_string( $value ) && strlen( $value ) > (int) $ruleParam ) {
						return false;
					}
					if ( is_numeric( $value ) && $value > (int) $ruleParam ) {
						return false;
					}
					break;
				case 'in':
					$validValues = explode( ',', $ruleParam );
					if ( ! in_array( $value, $validValues, true ) ) {
						return false;
					}
					break;
				case 'boolean':
					if ( ! is_bool( $value ) && ! in_array( $value, array( 0, 1, '0', '1', 'true', 'false' ), true ) ) {
						return false;
					}
					break;
				case 'array':
					if ( ! is_array( $value ) ) {
						return false;
					}
					break;
			}
		}
		return true;
	}

	/**
	 * Generate translated validation messages.
	 */
	protected function getValidationMessage( string $field, string $rule ): string {
		$rules       = explode( '|', $rule );
		$primaryRule = $rules[0];

		$messages = array(
			/* translators: %s: Field name */
			'required' => sprintf( __( 'The %s field is required.', 'surefeedback' ), $field ),
			/* translators: %s: Field name */
			'string'   => sprintf( __( 'The %s field must be a string.', 'surefeedback' ), $field ),
			/* translators: %s: Field name */
			'numeric'  => sprintf( __( 'The %s field must be numeric.', 'surefeedback' ), $field ),
			/* translators: %s: Field name */
			'email'    => sprintf( __( 'The %s field must be a valid email.', 'surefeedback' ), $field ),
			/* translators: %s: Field name */
			'url'      => sprintf( __( 'The %s field must be a valid URL.', 'surefeedback' ), $field ),
			/* translators: %s: Field name */
			'boolean'  => sprintf( __( 'The %s field must be true or false.', 'surefeedback' ), $field ),
			/* translators: %s: Field name */
			'array'    => sprintf( __( 'The %s field must be an array.', 'surefeedback' ), $field ),
		);

		/* translators: %s: Field name */
		return $messages[ $primaryRule ] ?? sprintf( __( 'The %s field is invalid.', 'surefeedback' ), $field );
	}

	/**
	 * Sanitize data securely.
	 */
	protected function sanitizeData( array $data, array $rules ): array {
		$sanitized = array();

		foreach ( $rules as $field => $rule ) {
			if ( ! isset( $data[ $field ] ) ) {
				continue;
			}

			$value = $data[ $field ];
			$rules = explode( '|', $rule );

			foreach ( $rules as $singleRule ) {
				$ruleParts = explode( ':', $singleRule, 2 );
				$ruleName  = $ruleParts[0];

				switch ( $ruleName ) {
					case 'string':
						$value = sanitize_text_field( $value );
						break;
					case 'email':
						$value = sanitize_email( $value );
						break;
					case 'url':
						$value = esc_url_raw( $value );
						break;
					case 'numeric':
						$value = is_float( $value + 0 ) ? (float) $value : (int) $value;
						break;
					case 'boolean':
						$value = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
						break;
					case 'array':
						if ( is_array( $value ) ) {
							$value = $this->sanitizeArraySecurely( $value );
						}
						break;
				}
			}

			$sanitized[ $field ] = $value;
		}

		return $sanitized;
	}

	protected function user(): ?\WP_User {
		$user = wp_get_current_user();
		return $user->exists() ? $user : null;
	}

	protected function isAuthenticated(): bool {
		return is_user_logged_in();
	}

	protected function can( string $capability ): bool {
		return current_user_can( $capability );
	}

	protected function log( string $message, array $context = array(), string $level = 'info' ): void {
		if ( $this->logger ) {
			$this->logger->log( $level, $message, $context );
		}
	}

	protected function validateNonce( \WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_param( '_wpnonce' );
		if ( ! $nonce ) {
			return $this->error( __( 'Nonce not provided', 'surefeedback' ), null, 403 );
		}

		$nonce_valid = wp_verify_nonce( $nonce, 'wp_rest' ) || wp_verify_nonce( $nonce, 'wp_json' );
		if ( ! $nonce_valid ) {
			return $this->error( __( 'Invalid nonce', 'surefeedback' ), null, 403 );
		}

		return true;
	}

	protected function validateCapability( string $capability ) {
		if ( ! current_user_can( $capability ) ) {
			return $this->error( __( 'Insufficient permissions', 'surefeedback' ), null, 403 );
		}
		return true;
	}

	protected function handleException( \Exception $e ): WP_Error {
		if ( $this->logger ) {
			$this->logger->log(
				'error',
				$e->getMessage(),
				array(
					'exception' => get_class( $e ),
					'file'      => $e->getFile(),
					'line'      => $e->getLine(),
				)
			);
		}

		return $this->error(
			$this->app && $this->app->isEnvironment( 'development' )
				? $e->getMessage()
				: __( 'An error occurred while processing your request.', 'surefeedback' ),
			null,
			500
		);
	}

	/**
	 * Sanitize nested arrays safely.
	 */
	private function sanitizeArraySecurely( $value, int $depth = 0, int &$count = 0 ): array {
		if ( $depth > 5 || $count > 1000 || ! is_array( $value ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $value as $key => $item ) {
			++$count;
			if ( $count > 1000 ) {
				break;
			}

			$clean_key = sanitize_key( $key );

			if ( is_array( $item ) ) {
				$sanitized[ $clean_key ] = $this->sanitizeArraySecurely( $item, $depth + 1, $count );
			} else {
				$sanitized[ $clean_key ] = sanitize_text_field( $item );
			}
		}

		return $sanitized;
	}
}
