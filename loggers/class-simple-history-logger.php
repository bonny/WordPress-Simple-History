<?php

namespace Simple_History\Loggers;

use Simple_History\Helpers;

/**
 * Logs changes made on the Simple History settings page.
 */
class Simple_History_Logger extends Logger {
	protected $slug = 'SimpleHistoryLogger';

	/** @var array<int,array<string,string>> Found changes */
	private $arr_found_changes = [];

	public function get_info() {
		return [
			'name'        => _x( 'Simple History Logger', 'Logger: SimpleHistoryLogger', 'simple-history' ),
			'name_via'   => _x( 'Using plugin Simple History', 'Logger: SimpleHistoryLogger', 'simple-history' ),
			'description' => __( 'Logs changes made on the Simple History settings page.', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'modified_settings' => _x( 'Modified settings', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'regenerated_rss_feed_secret' => _x( 'Regenerated RSS feed secret', 'Logger: SimpleHistoryLogger', 'simple-history' ),
				'cleared_log' => _x( 'Cleared the log for Simple History ({num_rows_deleted} rows were removed)', 'Logger: SimpleHistoryLogger', 'simple-history' ),
			),
		];
	}

	public function loaded() {
		add_action( 'load-options.php', [ $this, 'on_load_options_page' ] );
		add_action( 'simple_history/rss_feed/secret_updated', [ $this, 'on_rss_feed_secret_updated' ] );
		add_action( 'simple_history/settings/log_cleared', [ $this, 'on_log_cleared' ] );
	}

	/**
	 * Log when the log is cleared.
	 *
	 * @param int $num_rows_deleted Number of rows deleted.
	 * @return void
	 */
	public function on_log_cleared( $num_rows_deleted ) {
		$this->info_message(
			'cleared_log',
			[
				'num_rows_deleted' => $num_rows_deleted,
			]
		);
	}

	/**
	 * When Simple History settings is saved a POST request is made to
	 * options.php. We hook into that request and log the changes.
	 *
	 * @return void
	 */
	public function on_load_options_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $_POST['option_page'] === $this->simple_history::SETTINGS_GENERAL_OPTION_GROUP ) {
			// Save all changes.
			add_action( 'updated_option', array( $this, 'on_updated_option' ), 10, 3 );

			// Finally, before redirecting back to Simple History options page, log the changes.
			add_filter( 'wp_redirect', [ $this, 'commit_log_on_wp_redirect' ], 10, 2 );
		}
	}

	/**
	 * Log when the RSS feed secret is updated.
	 *
	 * @return void
	 */
	public function on_rss_feed_secret_updated() {
		$this->info_message( 'regenerated_rss_feed_secret' );
	}

	/**
	 * Log found changes made on the Simple History settings page.
	 *
	 * @param string $location
	 * @param int $status
	 * @return string
	 */
	public function commit_log_on_wp_redirect( $location, $status ) {
		if ( count( $this->arr_found_changes ) === 0 ) {
			return $location;
		}

		$context = [];

		foreach ( $this->arr_found_changes as $change ) {
			$option = $change['option'];

			// Remove 'simple_history_' from beginning of string.
			$option = preg_replace( '/^simple_history_/', '', $option );

			$context[ "{$option}_prev" ] = $change['old_value'];
			$context[ "{$option}_new" ] = $change['new_value'];
		}

		$this->info_message( 'modified_settings', $context );

		return $location;
	}

	/**
	 * Store all changed options in one array.
	 *
	 * @param string $option
	 * @param mixed $old_value
	 * @param mixed $new_value
	 * @return void
	 */
	public function on_updated_option( $option, $old_value, $new_value ) {
		$this->arr_found_changes[] = [
			'option'    => $option,
			'old_value' => $old_value,
			'new_value' => $new_value,
		];
	}


	/**
	 * Return formatted list of changes made.
	 *
	 * @param object $row
	 */
	public function get_log_row_details_output( $row ) {
		$context = $row->context;

		$context_config = new Context_Output_Config_DTO();

		$context_config->add_context_items(
			[
				new Context_Output_Config_Item_DTO(
					'show_on_dashboard',
					__( 'Show on dashboard', 'simple-history' ),
					[ 'number_yes_no' => true ]
				),
				new Context_Output_Config_Item_DTO(
					'show_as_page',
					__( 'Show as a page', 'simple-history' ),
					[
						'number_yes_no' => true,
					]
				),
				new Context_Output_Config_Item_DTO(
					'pager_size',
					__( 'Items on page', 'simple-history' ),
				),
				new Context_Output_Config_Item_DTO(
					'pager_size_dashboard',
					__( 'Items on dashboard', 'simple-history' ),
				),
				new Context_Output_Config_Item_DTO(
					'enable_rss_feed',
					__( 'RSS feed enabled', 'simple-history' ),
					[
						'number_yes_no' => true,
					]
				),
			]
		);

		return Helpers::generate_added_removed_table_from_context_output_config_array( $context, $context_config );
	}
}

class Context_Output_Config_DTO {
	/** @var array<Context_Output_Config_Item_DTO> */
	public array $items;

	/**
	 * @param array<Context_Output_Config_Item_DTO> $context_items
	 */
	public function __construct( $context_items = [] ) {
		$this->items = $context_items;
	}

	/**
	 * @param Context_Output_Config_Item_DTO $context_item
	 * @return void
	 */
	public function add_context_item( $context_item ) {
		$this->items[] = $context_item;
	}

	/**
	 * @param array<Context_Output_Config_Item_DTO> $context_items
	 * @return void
	 */
	public function add_context_items( $context_items ) {
		foreach ( $context_items as $context_item ) {
			$this->add_context_item( $context_item );
		}
	}
}

class Context_Output_Config_Item_DTO {
	/** @var string Human readable name of setting */
	public string $name;

	/** @var string Slug of setting */
	public string $slug;

	public string $new_value = '';
	public string $prev_value = '';

	public bool $is_changed = false;
	public bool $is_added = false;
	public bool $is_removed = false;

	public bool $number_yes_no = false;

	/**
	 * @param string $slug
	 * @param string $name
	 * @param array<string,mixed> $additional_args
	 */
	public function __construct( $slug, $name, $additional_args = [] ) {
		$this->name = $name;
		$this->slug = $slug;

		if ( isset( $additional_args['number_yes_no'] ) ) {
			$this->number_yes_no = $additional_args['number_yes_no'];
		}
	}
}
