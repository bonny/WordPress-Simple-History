<?php

namespace Simple_History\Channels;

/**
 * Alert evaluator using JsonLogic.
 *
 * Thin wrapper around the JsonLogic library for evaluating alert rules
 * against event data. All rule logic (AND, OR, nested groups, negation,
 * comparisons) is handled by JsonLogic.
 *
 * @since 5.0.0
 * @see https://jsonlogic.com/
 */
class Alert_Evaluator {

	/**
	 * Whether JsonLogic library is loaded.
	 *
	 * @var bool
	 */
	private static bool $library_loaded = false;

	/**
	 * Load the JsonLogic library.
	 *
	 * @return bool True if library is available.
	 */
	private static function load_library(): bool {
		if ( self::$library_loaded ) {
			return true;
		}

		if ( class_exists( '\JWadhams\JsonLogic' ) ) {
			self::$library_loaded = true;
			return true;
		}

		$library_path = __DIR__ . '/../libraries/JsonLogic.php';
		if ( file_exists( $library_path ) ) {
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Path is safely constructed from __DIR__.
			require_once $library_path;
			self::$library_loaded = true;
			return true;
		}

		return false;
	}

	/**
	 * Check if JsonLogic is available.
	 *
	 * @return bool True if JsonLogic can be used.
	 */
	public static function is_available(): bool {
		return self::load_library();
	}

	/**
	 * Evaluate a JsonLogic rule against event data.
	 *
	 * @param array|object|null $rule The JsonLogic rule. Empty/null means all events pass.
	 * @param array             $event_data The event data to evaluate against.
	 * @return bool True if rule matches (alert should trigger), false otherwise.
	 */
	public static function evaluate( $rule, array $event_data ): bool {
		// No rule means all events pass.
		if ( empty( $rule ) ) {
			return true;
		}

		// Ensure library is loaded.
		if ( ! self::load_library() ) {
			// Library not available - fail open (allow event).
			return true;
		}

		try {
			// Convert object to array if needed.
			if ( is_object( $rule ) ) {
				$rule = json_decode( wp_json_encode( $rule ), true );
			}

			// Flatten event data for easier access in rules.
			$data = self::prepare_event_data( $event_data );

			// Apply JsonLogic rule.
			$result = \JWadhams\JsonLogic::apply( $rule, $data );

			// Ensure boolean return.
			return (bool) $result;
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Fail open on error.
			return true;
		}
	}

	/**
	 * Prepare event data for JsonLogic evaluation.
	 *
	 * Flattens nested context data for easier access in rules.
	 * E.g., context._user_id becomes accessible as both context._user_id and user_id.
	 *
	 * @param array $event_data Raw event data.
	 * @return array Prepared data for JsonLogic.
	 */
	private static function prepare_event_data( array $event_data ): array {
		$data = $event_data;

		// Flatten context fields for convenience.
		if ( isset( $event_data['context'] ) && is_array( $event_data['context'] ) ) {
			foreach ( $event_data['context'] as $key => $value ) {
				// Remove underscore prefix for cleaner access.
				$clean_key = ltrim( $key, '_' );
				if ( ! isset( $data[ $clean_key ] ) ) {
					$data[ $clean_key ] = $value;
				}
			}
		}

		return $data;
	}

	/**
	 * Evaluate an alert configuration against event data.
	 *
	 * @param array $alert_config Alert configuration with 'rule' key containing JsonLogic.
	 * @param array $event_data Event data to evaluate.
	 * @return bool True if alert should trigger.
	 */
	public static function evaluate_alert( array $alert_config, array $event_data ): bool {
		$rule = $alert_config['rule'] ?? $alert_config['jsonlogic_rule'] ?? null;
		return self::evaluate( $rule, $event_data );
	}

	/**
	 * Validate a JsonLogic rule structure.
	 *
	 * Basic validation to catch obvious errors before storage.
	 *
	 * @param mixed $rule The rule to validate.
	 * @return array Array with 'valid' boolean and 'errors' array.
	 */
	public static function validate_rule( $rule ): array {
		$errors = [];

		if ( empty( $rule ) ) {
			// Empty rule is valid (matches all).
			return [
				'valid'  => true,
				'errors' => [],
			];
		}

		if ( ! is_array( $rule ) && ! is_object( $rule ) ) {
			$errors[] = __( 'Rule must be a JsonLogic object.', 'simple-history' );
			return [
				'valid'  => false,
				'errors' => $errors,
			];
		}

		// Convert to array for validation.
		if ( is_object( $rule ) ) {
			$rule = json_decode( wp_json_encode( $rule ), true );
		}

		// Try to apply with empty data to catch syntax errors.
		if ( self::load_library() ) {
			try {
				\JWadhams\JsonLogic::apply( $rule, [] );
			} catch ( \Exception $e ) {
				$errors[] = sprintf(
					/* translators: %s: Error message */
					__( 'Invalid JsonLogic rule: %s', 'simple-history' ),
					$e->getMessage()
				);
			}
		}

		return [
			'valid'  => empty( $errors ),
			'errors' => $errors,
		];
	}

	/**
	 * Get a human-readable description of a JsonLogic rule.
	 *
	 * @param array|null $rule The JsonLogic rule.
	 * @return string Human-readable description.
	 */
	public static function get_rule_description( $rule ): string {
		if ( empty( $rule ) ) {
			return __( 'All events (no filter)', 'simple-history' );
		}

		// For complex rules, just return a generic description.
		// A full JsonLogic-to-text converter would be complex.
		$operator = is_array( $rule ) ? array_key_first( $rule ) : null;

		if ( $operator === 'and' ) {
			$count = is_array( $rule['and'] ) ? count( $rule['and'] ) : 0;
			return sprintf(
				/* translators: %d: Number of conditions */
				_n( '%d condition (all must match)', '%d conditions (all must match)', $count, 'simple-history' ),
				$count
			);
		}

		if ( $operator === 'or' ) {
			$count = is_array( $rule['or'] ) ? count( $rule['or'] ) : 0;
			return sprintf(
				/* translators: %d: Number of conditions */
				_n( '%d condition (any must match)', '%d conditions (any must match)', $count, 'simple-history' ),
				$count
			);
		}

		return __( 'Custom filter', 'simple-history' );
	}
}
