<?php

namespace Simple_History;

/**
 * Class to contain logic for exporting events.
 *
 * Used by core export module and export searched events feature in premium plugin.
 */
class Export {
	/** @var array $query_args Query arguments. Will be passed to log query class. */
	protected $query_args = [];

	/** @var string $format Export format. "json", "csv", or "html".  */
	protected $format = 'json';

	/** @var Simple_History $simple_history Simple History instance. */
	protected $simple_history;

	/** @var array $options Export options. */
	protected $options = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->simple_history = Simple_History::get_instance();
	}

	/**
	 * Set the query arguments.
	 *
	 * @param array $args Query arguments.
	 * @return self Chainable method.
	 */
	public function set_query_args( $args ) {
		$this->query_args = $args;

		return $this;
	}

	/**
	 * Set the export format.
	 *
	 * @param string $format Export format. "csv", "json", or "html.
	 * @return self Chainable method.
	 */
	public function set_download_format( $format ) {
		$this->format = $format;

		return $this;
	}

	/**
	 * Set export options.
	 *
	 * @param array $options Export options.
	 * @return self Chainable method.
	 */
	public function set_options( $options ) {
		$this->options = $options;

		return $this;
	}

	/**
	 * Get an export option value.
	 *
	 * @param string $key Option key.
	 * @param mixed  $default_value Default value if option not set.
	 * @return mixed Option value or default if not set.
	 */
	protected function get_option( $key, $default_value = null ) {
		return $this->options[ $key ] ?? $default_value;
	}

	/**
	 * Add hooks that modify the output of the events.
	 */
	protected function add_hooks() {
		// Disable relative time output in header.
		add_filter( 'simple_history/header_time_ago_max_time', '__return_zero' );
		add_filter( 'simple_history/header_just_now_max_time', '__return_zero' );

		// Don't use "You" if event is initiated by the same user that does the export.
		add_filter( 'simple_history/header_initiator_use_you', '__return_false' );
	}

	/**
	 * Download events that match query args.
	 *
	 * Events are fetched in batches, with each batch being output to the export file.
	 *
	 * The export file is output to the browser and then the script exits.
	 *
	 * @return never
	 */
	public function download() {
		$this->add_hooks();

		$export_format = $this->format;

		$query = new Log_Query();

		$download_query_args = $this->query_args;

		$query_result = $query->query( $download_query_args );

		if ( is_wp_error( $query_result ) ) {
			wp_die( esc_html( $query_result->get_error_message() ) );
		}

		$pages_count  = $query_result['pages_count'];
		$page_current = $query_result['page_current'];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Does work, see https://github.com/WordPress/WordPress-Coding-Standards/issues/295
		$fp = fopen( 'php://output', 'w' );

		$attachment_header_template = 'Content-Disposition: attachment; filename="%1$s"';

		if ( 'csv' === $export_format ) {
			$filename = 'simple-history-export-' . time() . '.csv';
			header( 'Content-Type: text/plain' );
			header( sprintf( $attachment_header_template, $filename ) );
		} elseif ( 'json' === $export_format ) {
			$filename = 'simple-history-export-' . time() . '.json';
			header( 'Content-Type: application/json' );
			header( sprintf( $attachment_header_template, $filename ) );
		} elseif ( 'html' === $export_format ) {
			$filename = 'simple-history-export-' . time() . '.html';
			header( 'Content-Type: text/html' );
			header( sprintf( $attachment_header_template, $filename ) );
		}

		// Some formats need to output some stuff before the actual loops.
		if ( 'json' === $export_format ) {
			$json_row = '[';
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite -- This is a known file pointer and it's not writing to a physical file.
			fwrite( $fp, $json_row );
		} elseif ( 'html' === $export_format ) {
			$html = sprintf(
				'
			<!doctype html>
			<meta charset="utf-8">
			<title>Simple History export</title>
			<ul>
			'
			);
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite -- This is a known file pointer and it's not writing to a physical file.
			fwrite( $fp, $html );
		}

		// Paginate through all pages and all their rows.
		$row_loop = 0;
		while ( $page_current <= $pages_count + 1 ) {

			foreach ( $query_result['log_rows'] as $one_row ) {

				set_time_limit( 30 );

				if ( 'csv' === $export_format ) {
					$this->output_csv_row( $fp, $one_row );
				} elseif ( 'json' === $export_format ) {
					$this->output_json_row( $fp, $one_row, $row_loop );
				} elseif ( 'html' === $export_format ) {
					$this->output_html_row( $fp, $one_row );
				}

				++$row_loop;
			}

			flush();

			// Fetch next page.
			++$page_current;
			$download_query_args['paged'] = $page_current;
			$query_result                 = $query->query( $download_query_args );

			if ( is_wp_error( $query_result ) ) {
				break;
			}
		}

		if ( 'json' === $export_format ) {
			$json_row = ']';
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite -- This is a known file pointer and it's not writing to a physical file.
			fwrite( $fp, $json_row );
		} elseif ( 'html' === $export_format ) {
			$html = sprintf( '</ul>' );
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite -- This is a known file pointer and it's not writing to a physical file.
			fwrite( $fp, $html );
		}

		fclose( $fp );
		flush();

		exit;
	}

	/**
	 * Get CSV headers.
	 *
	 * @return array Array of CSV column headers.
	 */
	protected function get_csv_headers() {
		return array(
			_x( 'Date (UTC)', 'CSV export header', 'simple-history' ),
			_x( 'Date (local)', 'CSV export header', 'simple-history' ),
			_x( 'Logger', 'CSV export header', 'simple-history' ),
			_x( 'Level', 'CSV export header', 'simple-history' ),
			_x( 'Initiator', 'CSV export header', 'simple-history' ),
			_x( 'Message Key', 'CSV export header', 'simple-history' ),
			_x( 'User Email', 'CSV export header', 'simple-history' ),
			_x( 'User Login', 'CSV export header', 'simple-history' ),
			_x( 'User Roles', 'CSV export header', 'simple-history' ),
			_x( 'Header Message', 'CSV export header', 'simple-history' ),
			_x( 'Details', 'CSV export header', 'simple-history' ),
			_x( 'Occasions', 'CSV export header', 'simple-history' ),
		);
	}

	/**
	 * Output a CSV row.
	 *
	 * @param resource $fp File pointer.
	 * @param object   $one_row Log row.
	 */
	protected function output_csv_row( $fp, $one_row ) {
		static $headers_outputted = false;

		$header_output = wp_strip_all_tags( html_entity_decode( $this->simple_history->get_log_row_header_output( $one_row ), ENT_QUOTES, 'UTF-8' ) );
		$header_output = trim( preg_replace( '/\s\s+/', ' ', $header_output ) );

		$message_output = wp_strip_all_tags( html_entity_decode( $this->simple_history->get_log_row_plain_text_output( $one_row ), ENT_QUOTES, 'UTF-8' ) );

		$user_email = empty( $one_row->context['_user_email'] ) ? null : $one_row->context['_user_email'];
		$user_login = empty( $one_row->context['_user_login'] ) ? null : $one_row->context['_user_login'];
		$user_roles = [];

		if ( $user_email ) {
			$user       = get_user_by( 'email', $user_email );
			$user_roles = $user->roles ?? array();
		}

		$user_roles_comma_separated = implode( ', ', $user_roles );

		$date_local = wp_date( 'Y-m-d H:i:s', strtotime( $one_row->date ) );

		// Output headers if this is the first row and headers are enabled.
		if ( ! $headers_outputted && $this->get_option( 'include_headers', false ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
			fputcsv( $fp, $this->get_csv_headers() );
			$headers_outputted = true;
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv
		fputcsv(
			$fp,
			array(
				Helpers::esc_csv_field( $one_row->date ),
				Helpers::esc_csv_field( $date_local ),
				Helpers::esc_csv_field( $one_row->logger ),
				Helpers::esc_csv_field( $one_row->level ),
				Helpers::esc_csv_field( $one_row->initiator ),
				Helpers::esc_csv_field( $one_row->context_message_key ),
				Helpers::esc_csv_field( $user_email ),
				Helpers::esc_csv_field( $user_login ),
				Helpers::esc_csv_field( $user_roles_comma_separated ),
				Helpers::esc_csv_field( $header_output ),
				Helpers::esc_csv_field( $message_output ),
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				Helpers::esc_csv_field( $one_row->subsequentOccasions ),
			)
		);
	}

	/**
	 * Output a JSON row.
	 *
	 * @param resource $fp File pointer.
	 * @param object   $one_row Log row.
	 * @param int      $row_loop Row loop counter.
	 */
	protected function output_json_row( $fp, $one_row, $row_loop ) {
		$comma    = $row_loop === 0 ? "\n" : ",\n";
		$json_row = $comma . Helpers::json_encode( $one_row );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
		fwrite( $fp, $json_row );
	}

	/**
	 * Output an HTML row.
	 *
	 * @param resource $fp File pointer.
	 * @param object   $one_row Log row.
	 */
	protected function output_html_row( $fp, $one_row ) {
		$html = sprintf(
			'
			<li>
				<div>%1$s</div>
				<div>%2$s</div>
				<div>%3$s</div>
			</li>
			',
			$this->simple_history->get_log_row_header_output( $one_row ),
			$this->simple_history->get_log_row_plain_text_output( $one_row ),
			$this->simple_history->get_log_row_details_output( $one_row )
		);

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
		fwrite( $fp, $html );
	}
}
