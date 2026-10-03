<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Helpers;

/**
 * Logs WordPress exports
 */
class Export_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'SimpleExportLogger';

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {
		return array(
			'name'        => __( 'Export Logger', 'simple-history' ),
			'description' => __( 'Logs updates to WordPress export', 'simple-history' ),
			'capability'  => 'export',
			'messages'    => array(
				'created_export' => __( 'Created XML export', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'   => _x( 'Export', 'Export logger: search', 'simple-history' ),
					'options' => array(
						_x( 'Created exports', 'Export logger: search', 'simple-history' ) => array(
							'created_export',
						),
					),
				),
			),
		);
	}

	/**
	 * Called when logger is loaded
	 */
	public function loaded() {
		add_action( 'export_wp', array( $this, 'on_export_wp' ), 10, 1 );
	}

	/**
	 * Called when export is created.
	 * Fired from filter "export_wp".
	 *
	 * Names are stored next to the IDs, so the event still reads right
	 * after an author, category or post type is gone.
	 *
	 * @param array $args Arguments passed to export_wp().
	 */
	public function on_export_wp( $args ) {
		$args    = is_array( $args ) ? $args : array();
		$content = $args['content'] ?? '';
		$filters = self::get_readable_filters( $args );

		$context = array(
			'export_content'       => $content,
			'export_content_label' => self::get_content_label( $content ),
			'export_args'          => Helpers::json_encode( $args ),
		);

		foreach ( $filters as $key => $value ) {
			$context[ 'export_' . $key ] = $value;
		}

		$this->info_message( 'created_export', $context );
	}

	/**
	 * Name what was exported: "all content" or a post type name.
	 *
	 * @param string $content The "content" export argument, "all" or a post type slug.
	 * @return string
	 */
	private static function get_content_label( $content ) {
		if ( $content === '' || $content === 'all' ) {
			return '';
		}

		$post_type_object = get_post_type_object( $content );

		if ( $post_type_object && ! empty( $post_type_object->labels->name ) ) {
			return $post_type_object->labels->name;
		}

		return $content;
	}

	/**
	 * Turn the export arguments into readable filter values.
	 *
	 * @param array $args Arguments passed to export_wp().
	 * @return array<string, string> Keys author_name, category_name, start_date, end_date and status, for the filters that were used.
	 */
	private static function get_readable_filters( $args ) {
		$filters = array();

		if ( ! empty( $args['author'] ) ) {
			$user                   = get_userdata( (int) $args['author'] );
			$filters['author_name'] = $user ? $user->display_name : '#' . (int) $args['author'];
		}

		if ( ! empty( $args['category'] ) ) {
			$term                     = get_term( (int) $args['category'], 'category' );
			$filters['category_name'] = $term instanceof \WP_Term ? $term->name : '#' . (int) $args['category'];
		}

		if ( ! empty( $args['start_date'] ) && is_string( $args['start_date'] ) ) {
			$filters['start_date'] = $args['start_date'];
		}

		if ( ! empty( $args['end_date'] ) && is_string( $args['end_date'] ) ) {
			$filters['end_date'] = $args['end_date'];
		}

		if ( ! empty( $args['status'] ) && is_string( $args['status'] ) ) {
			$status_object     = get_post_status_object( $args['status'] );
			$filters['status'] = $status_object && ! empty( $status_object->label ) ? $status_object->label : $args['status'];
		}

		return $filters;
	}

	/**
	 * Say what the export contained in the event message.
	 *
	 * @param object $row Log row.
	 * @return string
	 */
	public function get_log_row_plain_text_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'] ?? null;

		if ( $message_key !== 'created_export' ) {
			return parent::get_log_row_plain_text_output( $row );
		}

		$content = $context['export_content'] ?? '';

		if ( $content === 'all' ) {
			return esc_html__( 'Created XML export of all content', 'simple-history' );
		}

		// Events from before the content name was stored.
		if ( $content === '' ) {
			return parent::get_log_row_plain_text_output( $row );
		}

		$content_label = ! empty( $context['export_content_label'] ) ? $context['export_content_label'] : self::get_content_label( $content );

		return esc_html(
			sprintf(
				/* translators: %s: post type name, for example "Pages" */
				__( 'Created XML export of %s', 'simple-history' ),
				$content_label
			)
		);
	}

	/**
	 * Show the filters the export used.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group|null
	 */
	public function get_log_row_details_output( $row ) {
		$context = $row->context;

		if ( ( $context['_message_key'] ?? null ) !== 'created_export' ) {
			return null;
		}

		$filters = self::get_filters_from_context( $context );

		$items = array(
			'author_name'   => __( 'Author', 'simple-history' ),
			'category_name' => __( 'Category', 'simple-history' ),
			'start_date'    => __( 'Start date', 'simple-history' ),
			'end_date'      => __( 'End date', 'simple-history' ),
			'status'        => __( 'Status', 'simple-history' ),
		);

		$group = new Event_Details_Group();

		foreach ( $items as $key => $label ) {
			if ( empty( $filters[ $key ] ) ) {
				continue;
			}

			$value = in_array( $key, array( 'start_date', 'end_date' ), true ) ? self::format_month( $filters[ $key ] ) : $filters[ $key ];

			$group->add_item(
				( new Event_Details_Item( null, $label ) )->set_new_value( $value )
			);
		}

		return $group;
	}

	/**
	 * Get the readable filters of an export event.
	 *
	 * Events from before the filters were stored one by one only have the
	 * raw arguments, so those are read and resolved here.
	 *
	 * @param array $context Event context.
	 * @return array<string, string>
	 */
	private static function get_filters_from_context( $context ) {
		$keys    = array( 'author_name', 'category_name', 'start_date', 'end_date', 'status' );
		$filters = array();

		foreach ( $keys as $key ) {
			if ( empty( $context[ 'export_' . $key ] ) ) {
				continue;
			}

			$filters[ $key ] = (string) $context[ 'export_' . $key ];
		}

		if ( ! empty( $filters ) || empty( $context['export_args'] ) ) {
			return $filters;
		}

		$args = json_decode( $context['export_args'], true );

		return is_array( $args ) ? self::get_readable_filters( $args ) : array();
	}

	/**
	 * Format a "YYYY-MM" export date as month and year.
	 *
	 * @param string $date Date from the export form.
	 * @return string
	 */
	private static function format_month( $date ) {
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $date ) ) {
			return $date;
		}

		$timestamp = strtotime( $date . '-01' );

		return $timestamp ? date_i18n( 'F Y', $timestamp ) : $date;
	}
}
