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

		$settings = [
			[
				'slug' => 'show_on_dashboard',
				'name' => __( 'Show on dashboard', 'simple-history' ),
				// For on-off values then 1 = on and 0 = off.
				'number_yes_no' => true,
			],
			[
				'slug' => 'show_as_page',
				'name' => __( 'Show as a page', 'simple-history' ),
				'number_yes_no' => true,
			],
			[
				'slug' => 'pager_size',
				'name' => __( 'Items on page', 'simple-history' ),
			],
			[
				'slug' => 'pager_size_dashboard',
				'name' => __( 'Items on dashboard', 'simple-history' ),
			],
			[
				'slug' => 'enable_rss_feed',
				'name' => __( 'RSS feed enabled', 'simple-history' ),
				'number_yes_no' => true,
			],
		];

		// Find prev and new values for each setting,
		// e.g. the slug + "_new" or "_prev".
		foreach ( $settings as $key => $setting ) {
			$slug = $setting['slug'];

			$prev_value = $context[ "{$slug}_prev" ] ?? null;
			$new_value = $context[ "{$slug}_new" ] ?? null;

			$settings[ $key ]['changed'] = false;
			$settings[ $key ]['added'] = false;
			$settings[ $key ]['removed'] = false;

			// If both prev and new are null then no change was made.
			if ( is_null( $prev_value ) && is_null( $new_value ) ) {
				continue;
			}

			// If both prev and new are the same then no change was made.
			if ( $prev_value === $new_value ) {
				continue;
			}

			if ( is_null( $prev_value ) ) {
				// If prev is null then it was added.
				$prev_value = '<em>' . __( 'Not set', 'simple-history' ) . '</em>';
				$settings[ $key ]['added'] = true;
			} else if ( is_null( $new_value ) ) {
				// If new is null then it was removed.
				$new_value = '<em>' . __( 'Not set', 'simple-history' ) . '</em>';
				$settings[ $key ]['removed'] = true;
			} else {
				$settings[ $key ]['changed'] = true;
			}

			$settings[ $key ]['prev_value'] = $prev_value;
			$settings[ $key ]['new_value'] = $new_value;
		}

		$output = '';

		// Generate table output from $settings array with all required info.
		$table = '<table class="SimpleHistoryLogitem__keyValueTable"><tbody>';
		foreach ( $settings as $setting ) {
			if ( $setting['changed'] ) {

				$new_value_to_show = $setting['new_value'];
				$prev_value_to_show = $setting['prev_value'];

				if ( $setting['number_yes_no'] ?? false ) {
					$new_value_to_show = $setting['new_value'] === '1' ? 'Yes' : 'No';
					$prev_value_to_show = $setting['prev_value'] === '1' ? 'Yes' : 'No';
				}

				$table .= sprintf(
					'
					<tr>
						<td>%1$s</td>
						<td>
							<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%2$s</ins>
							<del class="SimpleHistoryLogitem__keyValueTable__removedThing">%3$s</del>
						</td>
					</tr>
					',
					esc_html( $setting['name'] ),
					esc_html( $new_value_to_show ),
					esc_html( $prev_value_to_show ),
				);
			}
		}
		$table .= '</tbody></table>';

		$output .= $table;

		return $output;
	}
}
