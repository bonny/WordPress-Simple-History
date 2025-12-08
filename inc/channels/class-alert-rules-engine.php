<?php

namespace Simple_History\Channels;

/**
 * Simple alert rules engine.
 *
 * Thin service layer that uses Alert_Evaluator for JsonLogic evaluation
 * and Alert_Field_Registry for UI field definitions.
 *
 * @since 4.4.0
 */
class Alert_Rules_Engine {

	/**
	 * Evaluate an alert configuration against event data.
	 *
	 * @param array $alert_config Alert configuration with 'rule' or 'jsonlogic_rule' key.
	 * @param array $event_data The event data to evaluate.
	 * @return bool True if alert should trigger, false otherwise.
	 */
	public function evaluate_alert( array $alert_config, array $event_data ): bool {
		return Alert_Evaluator::evaluate_alert( $alert_config, $event_data );
	}

	/**
	 * Evaluate a JsonLogic rule against event data.
	 *
	 * @param array|object|null $rule The JsonLogic rule.
	 * @param array             $event_data The event data to evaluate.
	 * @return bool True if rule matches, false otherwise.
	 */
	public function evaluate( $rule, array $event_data ): bool {
		return Alert_Evaluator::evaluate( $rule, $event_data );
	}

	/**
	 * Validate a JsonLogic rule.
	 *
	 * @param mixed $rule The rule to validate.
	 * @return array Array with 'valid' boolean and 'errors' array.
	 */
	public function validate_rule( $rule ): array {
		return Alert_Evaluator::validate_rule( $rule );
	}

	/**
	 * Check if JsonLogic is available.
	 *
	 * @return bool True if JsonLogic can be used.
	 */
	public function is_available(): bool {
		return Alert_Evaluator::is_available();
	}

	/**
	 * Get available fields for rule building UI.
	 *
	 * @return array Field definitions for React Query Builder.
	 */
	public function get_fields(): array {
		return Alert_Field_Registry::get_fields();
	}

	/**
	 * Get fields formatted for REST API.
	 *
	 * @return array Fields ready for JavaScript consumption.
	 */
	public function get_fields_for_api(): array {
		return Alert_Field_Registry::get_fields_for_api();
	}

	/**
	 * Get human-readable description of a rule.
	 *
	 * @param array|null $rule The JsonLogic rule.
	 * @return string Human-readable description.
	 */
	public function get_rule_description( $rule ): string {
		return Alert_Evaluator::get_rule_description( $rule );
	}
}
