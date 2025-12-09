<?php

namespace Simple_History\Channels;

use Simple_History\Log_Levels;
use Simple_History\Simple_History;

/**
 * Registry of available fields for alert rules.
 *
 * Provides field definitions for the React Query Builder UI.
 * These fields describe what event data can be filtered on.
 *
 * @since 5.0.0
 */
class Alert_Field_Registry {

	/**
	 * Get all available fields for alert rules.
	 *
	 * Returns field definitions in React Query Builder format.
	 *
	 * @return array Array of field definitions.
	 */
	public static function get_fields(): array {
		$fields = [
			self::get_logger_field(),
			self::get_level_field(),
			self::get_message_key_field(),
			self::get_initiator_field(),
			self::get_user_id_field(),
			self::get_user_role_field(),
			self::get_message_field(),
		];

		/**
		 * Filter available alert rule fields.
		 *
		 * @since 5.0.0
		 * @param array $fields Array of field definitions.
		 */
		return apply_filters( 'simple_history/alerts/fields', $fields );
	}

	/**
	 * Get field definitions as associative array keyed by field name.
	 *
	 * @return array Field definitions keyed by name.
	 */
	public static function get_fields_by_name(): array {
		$fields  = self::get_fields();
		$by_name = [];

		foreach ( $fields as $field ) {
			$by_name[ $field['name'] ] = $field;
		}

		return $by_name;
	}

	/**
	 * Get logger field definition.
	 *
	 * @return array Field definition.
	 */
	private static function get_logger_field(): array {
		return [
			'name'            => 'logger',
			'label'           => __( 'Logger', 'simple-history' ),
			'inputType'       => 'select',
			'valueEditorType' => 'multiselect',
			'operators'       => self::get_list_operators(),
			'values'          => self::get_logger_values(),
			'placeholder'     => __( 'Select loggers...', 'simple-history' ),
		];
	}

	/**
	 * Get level field definition.
	 *
	 * @return array Field definition.
	 */
	private static function get_level_field(): array {
		return [
			'name'            => 'level',
			'label'           => __( 'Severity Level', 'simple-history' ),
			'inputType'       => 'select',
			'valueEditorType' => 'multiselect',
			'operators'       => self::get_list_operators(),
			'values'          => self::get_level_values(),
			'placeholder'     => __( 'Select levels...', 'simple-history' ),
		];
	}

	/**
	 * Get message key field definition.
	 *
	 * @return array Field definition.
	 */
	private static function get_message_key_field(): array {
		return [
			'name'            => 'message_key',
			'label'           => __( 'Event Type', 'simple-history' ),
			'inputType'       => 'text',
			'valueEditorType' => 'text',
			'operators'       => self::get_text_operators(),
			'placeholder'     => __( 'e.g., user_logged_in', 'simple-history' ),
		];
	}

	/**
	 * Get initiator field definition.
	 *
	 * @return array Field definition.
	 */
	private static function get_initiator_field(): array {
		return [
			'name'            => 'initiator',
			'label'           => __( 'Initiator', 'simple-history' ),
			'inputType'       => 'select',
			'valueEditorType' => 'multiselect',
			'operators'       => self::get_list_operators(),
			'values'          => self::get_initiator_values(),
			'placeholder'     => __( 'Select initiators...', 'simple-history' ),
		];
	}

	/**
	 * Get user ID field definition.
	 *
	 * @return array Field definition.
	 */
	private static function get_user_id_field(): array {
		return [
			'name'            => 'user_id',
			'label'           => __( 'User ID', 'simple-history' ),
			'inputType'       => 'number',
			'valueEditorType' => 'text',
			'operators'       => self::get_numeric_operators(),
			'placeholder'     => __( 'Enter user ID...', 'simple-history' ),
		];
	}

	/**
	 * Get user role field definition.
	 *
	 * @return array Field definition.
	 */
	private static function get_user_role_field(): array {
		return [
			'name'            => 'user_role',
			'label'           => __( 'User Role', 'simple-history' ),
			'inputType'       => 'select',
			'valueEditorType' => 'multiselect',
			'operators'       => self::get_list_operators(),
			'values'          => self::get_role_values(),
			'placeholder'     => __( 'Select roles...', 'simple-history' ),
		];
	}

	/**
	 * Get message field definition (for keyword search).
	 *
	 * @return array Field definition.
	 */
	private static function get_message_field(): array {
		return [
			'name'            => 'message',
			'label'           => __( 'Message Contains', 'simple-history' ),
			'inputType'       => 'text',
			'valueEditorType' => 'text',
			'operators'       => [
				[
					'name'  => 'contains',
					'label' => __( 'contains', 'simple-history' ),
				],
				[
					'name'  => 'doesNotContain',
					'label' => __( 'does not contain', 'simple-history' ),
				],
				[
					'name'  => 'beginsWith',
					'label' => __( 'begins with', 'simple-history' ),
				],
				[
					'name'  => 'endsWith',
					'label' => __( 'ends with', 'simple-history' ),
				],
			],
			'placeholder'     => __( 'Enter keyword...', 'simple-history' ),
		];
	}

	/**
	 * Get operators for list/select fields.
	 *
	 * @return array Operator definitions.
	 */
	private static function get_list_operators(): array {
		return [
			[
				'name'  => 'in',
				'label' => __( 'is one of', 'simple-history' ),
			],
			[
				'name'  => 'notIn',
				'label' => __( 'is not one of', 'simple-history' ),
			],
		];
	}

	/**
	 * Get operators for text fields.
	 *
	 * @return array Operator definitions.
	 */
	private static function get_text_operators(): array {
		return [
			[
				'name'  => '=',
				'label' => __( 'equals', 'simple-history' ),
			],
			[
				'name'  => '!=',
				'label' => __( 'does not equal', 'simple-history' ),
			],
			[
				'name'  => 'contains',
				'label' => __( 'contains', 'simple-history' ),
			],
			[
				'name'  => 'doesNotContain',
				'label' => __( 'does not contain', 'simple-history' ),
			],
			[
				'name'  => 'beginsWith',
				'label' => __( 'begins with', 'simple-history' ),
			],
			[
				'name'  => 'endsWith',
				'label' => __( 'ends with', 'simple-history' ),
			],
		];
	}

	/**
	 * Get operators for numeric fields.
	 *
	 * @return array Operator definitions.
	 */
	private static function get_numeric_operators(): array {
		return [
			[
				'name'  => '=',
				'label' => __( 'equals', 'simple-history' ),
			],
			[
				'name'  => '!=',
				'label' => __( 'does not equal', 'simple-history' ),
			],
			[
				'name'  => '>',
				'label' => __( 'greater than', 'simple-history' ),
			],
			[
				'name'  => '>=',
				'label' => __( 'greater than or equal to', 'simple-history' ),
			],
			[
				'name'  => '<',
				'label' => __( 'less than', 'simple-history' ),
			],
			[
				'name'  => '<=',
				'label' => __( 'less than or equal to', 'simple-history' ),
			],
		];
	}

	/**
	 * Get available logger values.
	 *
	 * @return array Logger options for select field.
	 */
	private static function get_logger_values(): array {
		$values = [];

		$simple_history = Simple_History::get_instance();
		$loggers        = $simple_history->get_instantiated_loggers();

		foreach ( $loggers as $logger_info ) {
			$logger = $logger_info['instance'] ?? null;
			if ( ! $logger ) {
				continue;
			}

			$logger_name = $logger->get_info_value_by_key( 'name' );
			$values[]    = [
				'name'  => $logger->get_slug(),
				'label' => $logger_name ? $logger_name : $logger->get_slug(),
			];
		}

		// Sort alphabetically by label.
		usort( $values, fn( $a, $b ) => strcasecmp( $a['label'], $b['label'] ) );

		return $values;
	}

	/**
	 * Get available log level values.
	 *
	 * @return array Level options for select field.
	 */
	private static function get_level_values(): array {
		$log_levels = Log_Levels::get_valid_log_levels();
		$values     = [];

		foreach ( $log_levels as $level ) {
			$values[] = [
				'name'  => $level,
				'label' => ucfirst( Log_Levels::get_log_level_translated( $level ) ),
			];
		}

		return $values;
	}

	/**
	 * Get available initiator values.
	 *
	 * @return array Initiator options for select field.
	 */
	private static function get_initiator_values(): array {
		return [
			[
				'name'  => 'wp_user',
				'label' => __( 'WordPress User', 'simple-history' ),
			],
			[
				'name'  => 'wp',
				'label' => __( 'WordPress', 'simple-history' ),
			],
			[
				'name'  => 'wp_cli',
				'label' => __( 'WP-CLI', 'simple-history' ),
			],
			[
				'name'  => 'web_user',
				'label' => __( 'Anonymous Web User', 'simple-history' ),
			],
			[
				'name'  => 'other',
				'label' => __( 'Other', 'simple-history' ),
			],
		];
	}

	/**
	 * Get available user role values.
	 *
	 * @return array Role options for select field.
	 */
	private static function get_role_values(): array {
		$roles  = wp_roles()->roles;
		$values = [];

		foreach ( $roles as $role_slug => $role_data ) {
			$values[] = [
				'name'  => $role_slug,
				'label' => translate_user_role( $role_data['name'] ),
			];
		}

		// Sort alphabetically by label.
		usort( $values, fn( $a, $b ) => strcasecmp( $a['label'], $b['label'] ) );

		return $values;
	}

	/**
	 * Get fields formatted for REST API response.
	 *
	 * @return array Fields in format ready for JavaScript consumption.
	 */
	public static function get_fields_for_api(): array {
		$fields = self::get_fields();

		// React Query Builder expects specific format.
		return array_map(
			function ( $field ) {
				$api_field = [
					'name'            => $field['name'],
					'label'           => $field['label'],
					'inputType'       => $field['inputType'] ?? 'text',
					'valueEditorType' => $field['valueEditorType'] ?? 'text',
					'operators'       => $field['operators'] ?? [],
				];

				if ( isset( $field['values'] ) ) {
					$api_field['values'] = $field['values'];
				}

				if ( isset( $field['placeholder'] ) ) {
					$api_field['placeholder'] = $field['placeholder'];
				}

				return $api_field;
			},
			$fields
		);
	}
}
