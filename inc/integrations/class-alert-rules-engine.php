<?php

namespace Simple_History\Integrations;

use Simple_History\Integrations\Interfaces\Alert_Rule_Interface;

/**
 * Engine for evaluating alert rules.
 *
 * This class handles the evaluation of alert rules to determine
 * whether events should be sent to integrations.
 *
 * @since 4.4.0
 */
class Alert_Rules_Engine {
	/**
	 * Array of registered rule types.
	 *
	 * @var Alert_Rule_Interface[]
	 */
	private array $rule_types = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_core_rule_types();
	}

	/**
	 * Register core rule types that come with the free plugin.
	 */
	private function register_core_rule_types() {
		// TODO: Register core rule types like Logger_Rule, Level_Rule, etc.
		// For now, we'll leave this empty and implement rule types as needed.
	}

	/**
	 * Register a rule type.
	 *
	 * @param Alert_Rule_Interface $rule_type The rule type to register.
	 * @return bool True on success, false if already registered.
	 */
	public function register_rule_type( Alert_Rule_Interface $rule_type ) {
		$type = $rule_type->get_type();

		if ( isset( $this->rule_types[ $type ] ) ) {
			return false; // Already registered.
		}

		$this->rule_types[ $type ] = $rule_type;

		/**
		 * Fired when a rule type is registered.
		 *
		 * @param Alert_Rule_Interface $rule_type The registered rule type.
		 * @param Alert_Rules_Engine $engine This engine instance.
		 */
		do_action( 'simple_history/alert_rules/rule_type_registered', $rule_type, $this );

		return true;
	}

	/**
	 * Get a registered rule type by type identifier.
	 *
	 * @param string $type The rule type identifier.
	 * @return Alert_Rule_Interface|null The rule type or null if not found.
	 */
	public function get_rule_type( $type ) {
		return $this->rule_types[ $type ] ?? null;
	}

	/**
	 * Get all registered rule types.
	 *
	 * @return Alert_Rule_Interface[] Array of rule types.
	 */
	public function get_rule_types() {
		return $this->rule_types;
	}

	/**
	 * Evaluate a set of rules against event data.
	 *
	 * @param array $rules Array of rule configurations.
	 * @param array $event_data The event data to evaluate.
	 * @param string $operator The logical operator ('AND' or 'OR') for combining rules.
	 * @return bool True if rules match, false otherwise.
	 */
	public function evaluate_rules( $rules, $event_data, $operator = 'AND' ) {
		if ( empty( $rules ) ) {
			return true; // No rules means all events pass.
		}

		$results = [];

		foreach ( $rules as $rule_config ) {
			$result = $this->evaluate_single_rule( $rule_config, $event_data );
			$results[] = $result;

			// Short-circuit evaluation for performance.
			if ( 'OR' === $operator && $result ) {
				return true; // At least one rule matched for OR.
			} elseif ( 'AND' === $operator && ! $result ) {
				return false; // One rule failed for AND.
			}
		}

		// Return final result based on operator.
		return 'AND' === $operator ? ! in_array( false, $results, true ) : in_array( true, $results, true );
	}

	/**
	 * Evaluate a single rule against event data.
	 *
	 * @param array $rule_config The rule configuration.
	 * @param array $event_data The event data to evaluate.
	 * @return bool True if rule matches, false otherwise.
	 */
	public function evaluate_single_rule( $rule_config, $event_data ) {
		if ( empty( $rule_config['type'] ) ) {
			return false; // Invalid rule configuration.
		}

		$rule_type = $this->get_rule_type( $rule_config['type'] );
		
		if ( ! $rule_type ) {
			// Unknown rule type, log warning and return false.
			error_log( 'Simple History: Unknown alert rule type: ' . $rule_config['type'] );
			return false;
		}

		try {
			return $rule_type->evaluate( $event_data, $rule_config );
		} catch ( \Exception $e ) {
			// Log the error and return false to be safe.
			error_log( 'Simple History: Error evaluating alert rule: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Validate a rule configuration.
	 *
	 * @param array $rule_config The rule configuration to validate.
	 * @return array Array with 'valid' boolean and optional 'errors' array.
	 */
	public function validate_rule( $rule_config ) {
		if ( empty( $rule_config['type'] ) ) {
			return [
				'valid' => false,
				'errors' => [ __( 'Rule type is required.', 'simple-history' ) ],
			];
		}

		$rule_type = $this->get_rule_type( $rule_config['type'] );
		
		if ( ! $rule_type ) {
			return [
				'valid' => false,
				'errors' => [ 
					sprintf(
						/* translators: %s: Rule type */
						__( 'Unknown rule type: %s', 'simple-history' ), 
						$rule_config['type'] 
					),
				],
			];
		}

		return $rule_type->validate_config( $rule_config );
	}

	/**
	 * Validate an array of rule configurations.
	 *
	 * @param array $rules Array of rule configurations to validate.
	 * @return array Array with 'valid' boolean and optional 'errors' array.
	 */
	public function validate_rules( $rules ) {
		$all_errors = [];
		$all_valid = true;

		foreach ( $rules as $index => $rule_config ) {
			$validation = $this->validate_rule( $rule_config );
			
			if ( ! $validation['valid'] ) {
				$all_valid = false;
				
				if ( ! empty( $validation['errors'] ) ) {
					foreach ( $validation['errors'] as $error ) {
						$all_errors[] = sprintf(
							/* translators: 1: Rule index, 2: Error message */
							__( 'Rule %1$d: %2$s', 'simple-history' ),
							$index + 1,
							$error
						);
					}
				}
			}
		}

		$result = [ 'valid' => $all_valid ];
		
		if ( ! empty( $all_errors ) ) {
			$result['errors'] = $all_errors;
		}

		return $result;
	}

	/**
	 * Get a human-readable description of rules.
	 *
	 * @param array $rules Array of rule configurations.
	 * @param string $operator The logical operator ('AND' or 'OR') for combining rules.
	 * @return string Human-readable description.
	 */
	public function get_rules_description( $rules, $operator = 'AND' ) {
		if ( empty( $rules ) ) {
			return __( 'Send all events (no filters)', 'simple-history' );
		}

		$descriptions = [];

		foreach ( $rules as $rule_config ) {
			$rule_type = $this->get_rule_type( $rule_config['type'] ?? '' );
			
			if ( $rule_type ) {
				$descriptions[] = $rule_type->get_readable_description( $rule_config );
			}
		}

		if ( empty( $descriptions ) ) {
			return __( 'No valid rules configured', 'simple-history' );
		}

		$operator_text = 'AND' === $operator ? 
			/* translators: Used to join multiple rule descriptions with AND logic */
			__( ' AND ', 'simple-history' ) : 
			/* translators: Used to join multiple rule descriptions with OR logic */  
			__( ' OR ', 'simple-history' );

		return implode( $operator_text, $descriptions );
	}

	/**
	 * Create a rule configuration array.
	 *
	 * This is a helper method for creating properly formatted rule configurations.
	 *
	 * @param string $type The rule type.
	 * @param array $config The rule-specific configuration.
	 * @return array The complete rule configuration.
	 */
	public static function create_rule( $type, $config = [] ) {
		return array_merge( [ 'type' => $type ], $config );
	}

	/**
	 * Create a simple logger filter rule.
	 *
	 * @param array $loggers Array of logger names to include.
	 * @return array The rule configuration.
	 */
	public static function create_logger_rule( $loggers ) {
		return self::create_rule( 'logger', [ 'loggers' => $loggers ] );
	}

	/**
	 * Create a simple level filter rule.
	 *
	 * @param array $levels Array of log levels to include.
	 * @return array The rule configuration.
	 */
	public static function create_level_rule( $levels ) {
		return self::create_rule( 'level', [ 'levels' => $levels ] );
	}

	/**
	 * Create a simple user filter rule.
	 *
	 * @param array $users Array of user IDs to include.
	 * @return array The rule configuration.
	 */
	public static function create_user_rule( $users ) {
		return self::create_rule( 'user', [ 'users' => $users ] );
	}
}