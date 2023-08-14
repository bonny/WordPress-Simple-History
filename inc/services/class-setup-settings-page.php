<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

class Setup_Settings_Page extends Service {
	public function loaded() {
		add_action( 'after_setup_theme', array( $this, 'add_default_settings_tabs' ) );
		add_action( 'admin_menu', array( $this, 'add_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
	}

	/**
	 * Adds default tabs to settings
	 */
	public function add_default_settings_tabs() {
		$settings_tabs = $this->simple_history->get_settings_tabs();

		// Add default settings tabs.
		$settings_tabs[] = [
			'slug' => 'settings',
			'name' => __( 'Settings', 'simple-history' ),
			'order' => 100,
			'function' => [ $this, 'settings_output_general' ],
		];

		// Append dev tabs if SIMPLE_HISTORY_DEV is defined and true.
		if ( defined( 'SIMPLE_HISTORY_DEV' ) && constant( 'SIMPLE_HISTORY_DEV' ) ) {
			$arr_dev_tabs = [
				[
					'slug' => 'log',
					'name' => __( 'Log (debug)', 'simple-history' ),
					'function' => [ $this, 'settings_output_log' ],
				],
				[
					'slug' => 'styles-example',
					'name' => __( 'Styles example (debug)', 'simple-history' ),
					'function' => [ $this, 'settings_output_styles_example' ],
				],
			];

			$settings_tabs = [ ...$settings_tabs, ...$arr_dev_tabs ];
		}

		$this->simple_history->set_settings_tabs( $settings_tabs );
	}

	public function settings_output_log() {
		include SIMPLE_HISTORY_PATH . 'templates/settings-log.php';
	}

	public function settings_output_general() {
		include SIMPLE_HISTORY_PATH . 'templates/settings-general.php';
	}

	public function settings_output_styles_example() {
		include SIMPLE_HISTORY_PATH . 'templates/settings-style-example.php';
	}

	public function add_admin_pages() {
		// Add a settings page
		$show_settings_page = true;
		$show_settings_page = apply_filters( 'simple_history_show_settings_page', $show_settings_page );
		$show_settings_page = apply_filters( 'simple_history/show_settings_page', $show_settings_page );

		if ( $show_settings_page ) {
			add_options_page(
				__( 'Simple History Settings', 'simple-history' ),
				_x( 'Simple History', 'Options page menu title', 'simple-history' ),
				$this->simple_history->get_view_settings_capability(),
				$this->simple_history::SETTINGS_MENU_SLUG,
				array( $this, 'settings_page_output' )
			);
		}
	}

	/**
	 * Add setting sections and settings for the settings page
	 * Also maybe save some settings before outputting them
	 */
	public function add_settings() {
		$this->simple_history->clear_log_from_url_request();

		$settings_section_general_id = $this->simple_history::SETTINGS_SECTION_GENERAL_ID;
		$settings_menu_slug = $this->simple_history::SETTINGS_MENU_SLUG;
		$settings_general_option_group = $this->simple_history::SETTINGS_GENERAL_OPTION_GROUP;

		add_settings_section(
			$settings_section_general_id,
			__( 'General', 'simple-history' ),
			[ $this, 'settings_section_output' ],
			$settings_menu_slug // Same slug as for options menu page.
		);

		// Checkboxes for where to show simple history.
		register_setting(
			$settings_general_option_group,
			'simple_history_show_on_dashboard',
			array(
				'sanitize_callback' => array(
					Helpers::class,
					'sanitize_checkbox_input',
				),
			)
		);

		register_setting(
			$settings_general_option_group,
			'simple_history_show_as_page',
			array(
				'sanitize_callback' => array(
					Helpers::class,
					'sanitize_checkbox_input',
				),
			)
		);

		add_settings_field(
			'simple_history_show_where',
			__( 'Show history', 'simple-history' ),
			array( $this, 'settings_field_where_to_show' ),
			$settings_menu_slug,
			$settings_section_general_id
		);

		// Number if items to show on the history page.
		add_settings_field(
			'simple_history_number_of_items',
			__( 'Number of items per page on the log page', 'simple-history' ),
			array( $this, 'settings_field_number_of_items' ),
			$settings_menu_slug,
			$settings_section_general_id
		);

		// Nonces for number of items inputs.
		register_setting( $settings_general_option_group, 'simple_history_pager_size' );

		// Number if items to show on dashboard.
		add_settings_field(
			'simple_history_number_of_items_dashboard',
			__( 'Number of items per page on the dashboard', 'simple-history' ),
			array( $this, 'settings_field_number_of_items_dashboard' ),
			$settings_menu_slug,
			$settings_section_general_id
		);

		// Nonces for number of items inputs.
		register_setting( $settings_general_option_group, 'simple_history_pager_size_dashboard' );

		// Link/button to clear log.
		if ( $this->simple_history->user_can_clear_log() ) {
			add_settings_field(
				'simple_history_clear_log',
				__( 'Clear log', 'simple-history' ),
				[ $this, 'settings_field_clear_log' ],
				$settings_menu_slug,
				$settings_section_general_id
			);
		}
	}

	/**
	 * Content for general section intro.
	 */
	public function settings_section_output() {
		/**
		 * Fires before the general settings section output.
		 * Can be used to output content before the general settings section.
		 */
		do_action( 'simple_history/settings_page/general_section_output' );
	}

		/**
	 * Settings field for where to show the log, page or dashboard
	 */
	public function settings_field_where_to_show() {
		$show_on_dashboard = $this->simple_history->setting_show_on_dashboard();
		$show_as_page = $this->simple_history->setting_show_as_page();
		?>

		<input
			<?php checked( $show_on_dashboard ); ?>
			type="checkbox" value="1" name="simple_history_show_on_dashboard" id="simple_history_show_on_dashboard" class="simple_history_show_on_dashboard" />
		<label for="simple_history_show_on_dashboard"><?php esc_html_e( 'on the dashboard', 'simple-history' ); ?></label>

		<br />

		<input
			<?php checked( $show_as_page ); ?>
			type="checkbox" value="1" name="simple_history_show_as_page" id="simple_history_show_as_page" class="simple_history_show_as_page" />
		<label for="simple_history_show_as_page">
			<?php esc_html_e( 'as a page under the dashboard menu', 'simple-history' ); ?>
		</label>

		<?php
	}

	/**
	 * Settings field for how many rows/items to show in log on the log page
	 */
	public function settings_field_number_of_items() {
		$current_pager_size = $this->simple_history->get_pager_size();
		$pager_size_default_values = array( 5, 10, 15, 20, 25, 30, 40, 50, 75, 100 );

		// If number of items is controlled via filter then return early.
		if ( has_filter( 'simple_history/pager_size' ) ) {
			printf(
				'<input type="text" readonly value="%1$s" />',
				esc_html( $current_pager_size ),
			);

			return;
		}

		?>
		<select name="simple_history_pager_size">
			<?php
			foreach ( $pager_size_default_values as $one_value ) {
				$selected = selected( $current_pager_size, $one_value, false );

				printf(
					'<option %1$s value="%2$s">%2$s</option>',
					esc_html( $selected ),
					esc_html( $one_value )
				);
			}

			// If current pager size is not among array values then manually output selected value here.
			// This can happen if user has set a value that is not in the array.
			if ( ! in_array( $current_pager_size, $pager_size_default_values, true ) ) {
				printf(
					'<option selected="selected" value="%1$s">%1$s</option>',
					esc_html( $current_pager_size )
				);
			}
			?>
		</select>

		<?php
	}

	/**
	 * Settings field for how many rows/items to show in log on the dashboard
	 */
	public function settings_field_number_of_items_dashboard() {
		$current_pager_size = $this->simple_history->get_pager_size_dashboard();
		$pager_size_default_values = array( 5, 10, 15, 20, 25, 30, 40, 50, 75, 100 );

		// If number of items is controlled via filter then return early.
		if ( has_filter( 'simple_history_pager_size_dashboard' ) || has_filter( 'simple_history/dashboard_pager_size' ) ) {
			printf(
				'<input type="text" readonly value="%1$s" />',
				esc_html( $current_pager_size ),
			);

			return;
		}

		?>
		<select name="simple_history_pager_size_dashboard">
			<?php
			foreach ( $pager_size_default_values as $one_value ) {
				$selected = selected( $current_pager_size, $one_value, false );

				printf(
					'<option %1$s value="%2$s">%2$s</option>',
					esc_html( $selected ),
					esc_html( $one_value )
				);
			}

			// If current pager size is not among array values then manually output selected value here.
			// This can happen if user has set a value that is not in the array.
			if ( ! in_array( $current_pager_size, $pager_size_default_values, true ) ) {
				printf(
					'<option selected="selected" value="%1$s">%1$s</option>',
					esc_html( $current_pager_size )
				);
			}
			?>
		</select>
		<?php
	}

	/**
	 * Settings section to clear database.
	 */
	public function settings_field_clear_log() {
		// Get base URL to current page.
		// Will be like "/wordpress/wp-admin/options-general.php?page=simple_history_settings_menu_slug&"
		$clear_link = add_query_arg( '', '' );

		// Append nonce to URL.
		$clear_link = wp_nonce_url( $clear_link, 'simple_history_clear_log', 'simple_history_clear_log_nonce' );

		$clear_days = $this->simple_history->get_clear_history_interval();

		echo '<p>';

		if ( $clear_days > 0 ) {
			echo sprintf(
				// translators: %1$s is number of days.
				esc_html__( 'Items in the database are automatically removed after %1$s days.', 'simple-history' ),
				esc_html( $clear_days )
			);
		} else {
			esc_html_e( 'Items in the database are kept forever.', 'simple-history' );
		}

		echo '</p>';

		printf(
			'<p><a class="button js-SimpleHistory-Settings-ClearLog" href="%2$s">%1$s</a></p>',
			esc_html__( 'Clear log now', 'simple-history' ),
			esc_url( $clear_link )
		);
	}

	/**
	 * Output HTML for the settings page.
	 * Called from add_options_page.
	 */
	public function settings_page_output() {
		$arr_settings_tabs = $this->simple_history->get_settings_tabs();

		?>
		<div class="wrap">

			<h1 class="SimpleHistoryPageHeadline">
				<div class="dashicons dashicons-backup SimpleHistoryPageHeadline__icon"></div>
				<?php esc_html_e( 'Simple History Settings', 'simple-history' ); ?>
			</h1>

			<?php
			$active_tab = $_GET['selected-tab'] ?? 'settings';
			$settings_base_url = menu_page_url( $this->simple_history::SETTINGS_MENU_SLUG, 0 );
			?>

			<h2 class="nav-tab-wrapper">
				<?php
				foreach ( $arr_settings_tabs as $one_tab ) {
					$tab_slug = $one_tab['slug'];

					printf(
						'<a href="%3$s" class="nav-tab %4$s">%1$s</a>',
						$one_tab['name'], // 1
						$tab_slug, // 2
						esc_url( add_query_arg( 'selected-tab', $tab_slug, $settings_base_url ) ), // 3
						$active_tab == $tab_slug ? 'nav-tab-active' : '' // 4
					);
				}
				?>
			</h2>

			<?php
			// Output contents for selected tab.
			$arr_active_tab = wp_filter_object_list(
				$arr_settings_tabs,
				array(
					'slug' => $active_tab,
				)
			);
			$arr_active_tab = current( $arr_active_tab );

			// We must have found an active tab and it must have a callable function
			if ( ! $arr_active_tab || ! is_callable( $arr_active_tab['function'] ) ) {
				wp_die( esc_html__( 'No valid callback found', 'simple-history' ) );
			}

			$args = array(
				'arr_active_tab' => $arr_active_tab,
			);

			call_user_func_array( $arr_active_tab['function'], array_values( $args ) );
			?>

		</div>
		<?php
	}
}
