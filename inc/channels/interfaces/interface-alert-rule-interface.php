<?php

namespace Simple_History\Channels\Interfaces;

/**
 * Interface for alert rules.
 *
 * Defines the contract that all alert rules must implement.
 * Alert rules determine whether an event should trigger notifications
 * based on various criteria.
 *
 * @since 4.4.0
 */
interface Alert_Rule_Interface {
	/**
	 * Get the unique type identifier for this rule.
	 *
	 * @return string The rule type.
	 */
	public function get_type();

	/**
	 * Get the display name for this rule type.
	 *
	 * @return string The rule display name.
	 */
	public function get_name();

	/**
	 * Get the description for this rule type.
	 *
	 * @return string The rule description.
	 */
	public function get_description();

	/**
	 * Get the configuration fields for this rule.
	 *
	 * @return array Array of configuration fields.
	 */
	public function get_config_fields();

	/**
	 * Evaluate whether this rule matches the given event.
	 *
	 * @param array $event_data The event data to evaluate.
	 * @param array $rule_config The rule configuration.
	 * @return bool True if rule matches, false otherwise.
	 */
	public function evaluate( $event_data, $rule_config );

	/**
	 * Validate the rule configuration.
	 *
	 * @param array $rule_config The rule configuration to validate.
	 * @return array Array with 'valid' boolean and optional 'errors' array.
	 */
	public function validate_config( $rule_config );

	/**
	 * Get a human-readable description of the rule with given config.
	 *
	 * @param array $rule_config The rule configuration.
	 * @return string Human-readable rule description.
	 */
	public function get_readable_description( $rule_config );
}
