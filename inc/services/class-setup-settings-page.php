<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;
use Simple_History\Helpers;
use Simple_History\Menu_Manager;
use Simple_History\Menu_Page;

/**
 * Setup settings page.
 */
class Setup_Settings_Page extends Service {
	public const SETTINGS_GENERAL_SUBTAB_SLUG = 'general_settings_subtab_general';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'admin_menu', [ $this, 'add_settings_admin_page' ], 10 );
		add_action( 'admin_menu', [ $this, 'add_settings_tabs' ] );

		add_action( 'admin_menu', [ $this, 'add_settings' ], 10 );
		add_action( 'admin_page_access_denied', [ $this, 'on_admin_page_access_denied' ] );

		add_action( 'admin_init', [ $this, 'trigger_actions_for_old_add_ons' ] );
	}

	/**
	 * Trigger actions for old version of Simple History Extended Settings
	 * and Simple History Premium.
	 */
	public function trigger_actions_for_old_add_ons() {
		// Only trigger if selected-sub-tab=message-control.
		$menu_manager = $this->simple_history->get_menu_manager();
		$subtab_slug  = $menu_manager->get_current_sub_tab_slug();

		if ( $subtab_slug !== 'message-control' ) {
			return;
		}

		// This is the action name used in add-ons.
		$action_to_trigger = 'load-settings_page_' . Simple_History::SETTINGS_MENU_SLUG;

		// Bail if action already fired.
		if ( did_action( $action_to_trigger ) ) {
			return;
		}

		/**
		 * Fires on admin_init to trigger actions for old add-ons.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		do_action( $action_to_trigger );
	}

	/**
	 * If users changes setting from showing main page on dashboard or tools to top level
	 * menu item, user will get an error due to the fact that the setting screen they are
	 * trying to access is not registered anymore. This function will redirect the user to
	 * the new location of the settings page.
	 *
	 * We use hook 'admin_page_access_denied' because that's right above where the error
	 * "Sorry, you are not allowed to access this page." is thrown.
	 */
	public function on_admin_page_access_denied() {
		$wp_referer = wp_get_referer();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page                    = sanitize_text_field( wp_unslash( $_GET['page'] ?? '' ) );
		$settings_menu_page_slug = Simple_History::SETTINGS_MENU_PAGE_SLUG;

		// Get the currently registered settings page URL.
		$current_settings_url = Menu_Manager::get_admin_url_by_slug( Simple_History::SETTINGS_MENU_PAGE_SLUG );

		// Get the currently requested URL.
		$current_request_url = sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );

		// Return early if required args are missing.
		if ( ! $current_request_url || ! $wp_referer || ! $page ) {
			return;
		}

		// Return early if current page is not trying to access our settings page.
		if ( $page !== $settings_menu_page_slug ) {
			return;
		}

		// Return early if referer is same as the current settings URL.
		if ( $wp_referer === $current_settings_url ) {
			return;
		}

		// Pass on ?settings-updated if exists in requested URL.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['settings-updated'] ) ) {
			$current_settings_url = add_query_arg( 'settings-updated', 'true', $current_settings_url );
		}

		// All conditions met, redirect to correct settings page URL.
		wp_safe_redirect( $current_settings_url );
		exit;
	}

	/**
	 * Output for the general settings tab.
	 */
	public function settings_output_general() {
		include SIMPLE_HISTORY_PATH . 'templates/settings-general.php';
	}

	/**
	 * Add menu page for settings.
	 *
	 * Added as one of:
	 * - inside Simple History main menu item
	 * - as it's own menu item under Settings.
	 */
	public function add_settings_admin_page() {
		// Add a settings page.
		$show_settings_page = apply_filters( 'simple_history_show_settings_page', true );
		$show_settings_page = apply_filters( 'simple_history/show_settings_page', $show_settings_page );

		// Can't show settings page if user can't view main menu item.
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		if ( ! $show_settings_page ) {
			return;
		}

		// Add a settings page using new menu manager.
		$admin_page_location = Helpers::get_menu_page_location();

		$settings_menu_page = ( new Menu_Page() )
				->set_page_title( _x( 'Simple History Settings', 'settings title name', 'simple-history' ) )
				->set_menu_slug( Simple_History::SETTINGS_MENU_PAGE_SLUG )
				->set_capability( Helpers::get_view_settings_capability() )
				->set_callback( [ $this, 'settings_page_output' ] )
				->set_redirect_to_first_child_on_load()
				->set_order( 4 );

		// Different setting depending on where main page is shown.
		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			// Add as a subpage to the main page if location is top or bottom in the main menu.
			$settings_menu_page
				->set_menu_title( _x( 'Settings', 'settings menu name', 'simple-history' ) )
				->set_parent( Simple_History::MENU_PAGE_SLUG )
				->set_location( 'submenu' );
		} elseif ( in_array( $admin_page_location, [ 'inside_dashboard', 'inside_tools' ], true ) ) {
			// If main page is shown as child to tools or dashboard then settings page is shown as child to settings main menu.
			$settings_menu_page
				->set_menu_title( _x( 'Simple History', 'settings menu name', 'simple-history' ) )
				->set_location( 'settings' );
		}

		$settings_menu_page->add();
	}

	/**
	 * Adds tabs to settings.
	 */
	public function add_settings_tabs() {
		$menu_manager = $this->simple_history->get_menu_manager();

		// Bail if parent settings page does not exists (due to Stealth Mode or similar).
		if ( ! $menu_manager->page_exists( Simple_History::SETTINGS_MENU_PAGE_SLUG ) ) {
			return;
		}

		// Register tab using new method using Menu_Manager and Menu_Page.
		// This is the tab at <simple history settings location> » General.
		// This will be hidden when there is only one tab.
		$settings_menu_page_main_tab = ( new Menu_Page() )
			->set_menu_title( __( 'Settings', 'simple-history' ) )
			->set_page_title( __( 'Settings', 'simple-history' ) )
			->set_menu_slug( self::SETTINGS_GENERAL_SUBTAB_SLUG )
			->set_icon( 'settings' )
			->set_parent( Simple_History::SETTINGS_MENU_PAGE_SLUG )
			->set_callback( [ $this, 'settings_output_general' ] )
			->set_redirect_to_first_child_on_load()
			->add();

		// In settings page is in options page then add subtab for general settings.
		// so user will come to Settings » Simple History » Settings (tab) » General (subtab).
		// This is the first tab under the settings page added above.
		( new Menu_Page() )
			->set_menu_title( __( 'General', 'simple-history' ) )
			->set_page_title( __( 'General settings', 'simple-history' ) )
			->set_parent( $settings_menu_page_main_tab )
			->set_callback( [ $this, 'settings_output_general' ] )
			->set_menu_slug( 'general_settings_subtab_settings_general' )
			->add();
	}

	/**
	 * Add setting sections and settings for the settings page.
	 *
	 * Also save some settings before outputting them.
	 */
	public function add_settings() {
		$this->clear_log_from_url_request();

		$settings_section_general_id   = $this->simple_history::SETTINGS_SECTION_GENERAL_ID;
		$settings_menu_slug            = $this->simple_history::SETTINGS_MENU_SLUG;
		$settings_general_option_group = $this->simple_history::SETTINGS_GENERAL_OPTION_GROUP;

		Helpers::add_settings_section(
			$settings_section_general_id,
			[ __( 'General', 'simple-history' ), 'tune' ],
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

		// Setting for showing as page under dashboard.
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

		// Setting for showing in admin bar.
		register_setting(
			$settings_general_option_group,
			'simple_history_show_in_admin_bar',
			array(
				'sanitize_callback' => array(
					Helpers::class,
					'sanitize_checkbox_input',
				),
			)
		);

		// Setting for menu page location.
		register_setting(
			$settings_general_option_group,
			'simple_history_menu_page_location',
			array(
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Output for where to show history, in dashboard, admin bar.
		add_settings_field(
			'simple_history_show_where',
			Helpers::get_settings_field_title_output( __( 'Show history', 'simple-history' ), 'visibility' ),
			array( $this, 'settings_field_where_to_show' ),
			$settings_menu_slug,
			$settings_section_general_id
		);

		add_settings_field(
			'simple_history_menu_page_location',
			Helpers::get_settings_field_title_output( __( 'History menu position', 'simple-history' ), 'overview' ),
			array( $this, 'settings_field_menu_page_location' ),
			$settings_menu_slug,
			$settings_section_general_id
		);

		// Output for number if items to show on the history page.
		add_settings_field(
			'simple_history_number_of_items',
			Helpers::get_settings_field_title_output( __( 'Items per page', 'simple-history' ), 'filter_list' ),
			array( $this, 'settings_field_number_of_items' ),
			$settings_menu_slug,
			$settings_section_general_id
		);

		// Nonces for number of items inputs.
		register_setting( $settings_general_option_group, 'simple_history_pager_size' );

		// Nonces for number of items inputs.
		register_setting( $settings_general_option_group, 'simple_history_pager_size_dashboard' );

		// Link/button to clear log.
		if ( Helpers::user_can_clear_log() ) {
			add_settings_field(
				'simple_history_clear_log',
				Helpers::get_settings_field_title_output( __( 'Clear log', 'simple-history' ), 'auto-delete' ),
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
		 * Can be used to output content in the general settings section.
		 */
		do_action( 'simple_history/settings_page/general_section_output' );
	}

	/**
	 * Settings field output for menu page location
	 */
	public function settings_field_menu_page_location() {
		$location    = Helpers::get_menu_page_location();
		$option_slug = 'simple_history_menu_page_location';

		$location_options = [
			[
				'slug' => 'top',
				'text' => __( 'Top of main menu', 'simple-history' ),
			],
			[
				'slug' => 'bottom',
				'text' => __( 'Bottom of main menu', 'simple-history' ),
			],
			[
				'slug' => 'inside_dashboard',
				'text' => __( 'Inside dashboard menu item', 'simple-history' ),
			],
			[
				'slug' => 'inside_tools',
				'text' => __( 'Inside tools menu item', 'simple-history' ),
			],
		];
		?>
		<fieldset>
			<?php foreach ( $location_options as $option ) { ?>
				<label>
					<input 
						type="radio"
						name="<?php echo esc_attr( $option_slug ); ?>"
						value="<?php echo esc_attr( $option['slug'] ); ?>"
						<?php checked( $location === $option['slug'] ); ?>
					/>
					
					<?php echo esc_html( $option['text'] ); ?>
				</label>
				<br />
			<?php } ?>
		</fieldset>
		<?php
	}

	/**
	 * Settings field for where to show the log, page or dashboard
	 */
	public function settings_field_where_to_show() {
		$show_on_dashboard = Helpers::setting_show_on_dashboard();
		$show_in_admin_bar = Helpers::setting_show_in_admin_bar();
		?>

		<input <?php checked( $show_on_dashboard ); ?> type="checkbox" value="1" name="simple_history_show_on_dashboard" id="simple_history_show_on_dashboard" class="simple_history_show_on_dashboard" />
		<label for="simple_history_show_on_dashboard">
			<?php esc_html_e( 'on the dashboard', 'simple-history' ); ?>
		</label>
		
		<br />

		<input <?php checked( $show_in_admin_bar ); ?> type="checkbox" value="1" name="simple_history_show_in_admin_bar" id="simple_history_show_in_admin_bar" class="simple_history_show_in_admin_bar" />
		<label for="simple_history_show_in_admin_bar">
			<?php esc_html_e( 'in the admin bar', 'simple-history' ); ?>
		</label>

		<?php
	}

	/**
	 * Settings field for how many rows/items to show in log on the log page
	 */
	public function settings_field_number_of_items() {
		$this->settings_field_number_of_items_on_log_page();
		echo '<br /><br />';
		$this->settings_field_number_of_items_dashboard();
	}

	/**
	 * Settings field for how many rows/items to show in log on the log page
	 */
	private function settings_field_number_of_items_on_log_page() {
		$current_pager_size        = Helpers::get_pager_size();
		$pager_size_default_values = array( 5, 10, 15, 20, 25, 30, 40, 50, 75, 100 );

		echo '<p>' . esc_html__( 'Number of items per page on the log page', 'simple-history' ) . '</p>';

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
	private function settings_field_number_of_items_dashboard() {
		$current_pager_size        = Helpers::get_pager_size_dashboard();
		$pager_size_default_values = array( 5, 10, 15, 20, 25, 30, 40, 50, 75, 100 );

		echo '<p>' . esc_html__( 'Number of items per page on the dashboard', 'simple-history' ) . '</p>';

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
		// Will be like "/wordpress/wp-admin/admin.php?page=<main-menu-page-slug>".
		$clear_link = add_query_arg( '', '' );

		// Append nonce to URL.
		$clear_link = wp_nonce_url( $clear_link, 'simple_history_clear_log', 'simple_history_clear_log_nonce' );

		$clear_days = Helpers::get_clear_history_interval();

		// Wrap in a div with id "simple_history_clear_log_info" so we can target it with link and CSS.
		echo '<div id="simple_history_clear_log_info">';

		echo '<p>';

		if ( $clear_days > 0 ) {
			printf(
				// translators: %1$s is number of days.
				esc_html__( 'Items in the database are automatically removed after %1$s days.', 'simple-history' ),
				esc_html( $clear_days )
			);
			echo '<br>';
		} else {
			esc_html_e( 'Items in the database are kept forever.', 'simple-history' );
		}

		echo '</p>';

		// View Premium add-on information, if not already installed.
		if ( Helpers::show_promo_boxes() ) {
			?>
			<p>
				<a href="<?php echo esc_url( Helpers::get_tracking_url( 'https://simple-history.com/premium/', 'premium_settings_purge' ) ); ?>" target="_blank" class="sh-ExternalLink">
					<?php esc_html_e( 'Upgrade to Simple History Premium to set this to any number of days.', 'simple-history' ); ?>
				</a>
			</p>
			<?php
		}

		printf(
			'<p><a class="button js-SimpleHistory-Settings-ClearLog" href="%2$s">%1$s</a></p>',
			esc_html__( 'Clear log now', 'simple-history' ),
			esc_url( $clear_link )
		);

		echo '</div>';
	}


	/**
	 * Output HTML for the settings page.
	 * Called from add_options_page.
	 */
	public function settings_page_output() {
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Admin_Pages::header_output();
	}

	/**
	 * Get HTML for the main navigation.
	 *
	 * @deprecated 5.7.0 No longer used.
	 * @return string
	 */
	public static function get_main_nav_html() {
		ob_start();

		$simple_history = Simple_History::get_instance();

		$arr_settings_tabs = $simple_history->get_settings_tabs();

		?>
		<nav class="sh-PageNav">
			<?php
			$active_tab = self::get_active_tab_slug();

			foreach ( $arr_settings_tabs as $one_tab ) {
				$tab_slug = $one_tab['slug'];

				$icon_html = '';
				if ( ! is_null( $one_tab['icon'] ?? null ) ) {
					$icon_html = sprintf(
						'<span class="sh-PageNav-icon sh-Icon--%1$s"></span>',
						esc_attr( $one_tab['icon'] )
					);
				}

				$icon_html_allowed_html = [
					'span' => [
						'class' => [],
					],
				];

				printf(
					'<a href="%3$s" class="sh-PageNav-tab %4$s">%5$s%1$s</a>',
					esc_html( $one_tab['name'] ), // 1
					esc_html( $tab_slug ), // 2
					esc_url( Helpers::get_settings_page_tab_url( $tab_slug ) ), // 3
					$active_tab === $tab_slug ? 'is-active' : '', // 4
					wp_kses( $icon_html, $icon_html_allowed_html ) // 5
				);
			}
			?>
		</nav>
		<?php

		return ob_get_clean();
	}


	/**
	 * Get HTML for the sub navigation.
	 *
	 * @deprecated 5.7.0 No longer used.
	 * @return string
	 */
	public static function get_subnav_html() {
		ob_start();

		$simple_history = Simple_History::get_instance();

		$arr_settings_tabs     = $simple_history->get_settings_tabs();
		$arr_settings_tabs_sub = $simple_history->get_settings_tabs( 'sub' );

		// Begin subnav.
		$sub_tab_found = false;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_sub_tab = sanitize_text_field( wp_unslash( $_GET['selected-sub-tab'] ?? '' ) );
		$active_tab     = self::get_active_tab_slug();

		// Get sub tabs for currently active tab.
		$subtabs_for_active_tab = wp_filter_object_list(
			$arr_settings_tabs_sub,
			array(
				'parent_slug' => $active_tab,
			)
		);

		// Re-index array, so 0 is first sub tab.
		$subtabs_for_active_tab = array_values( $subtabs_for_active_tab );

		// If sub tabs are found but no active sub tab, then
		// make first sub tab automatically active.
		if ( count( $subtabs_for_active_tab ) > 0 && empty( $active_sub_tab ) ) {
			$active_sub_tab = $subtabs_for_active_tab[0]['slug'];
		}

		if ( count( $subtabs_for_active_tab ) > 0 ) {

			// Output subnav tabs if number of tabs are more than 1.
			// If only one tab then no need to output subnav.
			if ( count( $subtabs_for_active_tab ) > 1 ) {
				?>
				<nav class="sh-SettingsTabs">
					<ul class="sh-SettingsTabs-tabs">
						<?php
						foreach ( $subtabs_for_active_tab as $one_sub_tab ) {
							$is_active             = $active_sub_tab === $one_sub_tab['slug'];
							$is_active_class       = $is_active ? 'is-active' : '';
							$plug_settings_tab_url = Helpers::get_settings_page_sub_tab_url( $one_sub_tab['slug'] );
							?>
							<li class="sh-SettingsTabs-tab">
								<a class="sh-SettingsTabs-link <?php echo esc_attr( $is_active_class ); ?>" href="<?php echo esc_url( $plug_settings_tab_url ); ?>">
									<?php echo esc_html( $one_sub_tab['name'] ); ?>
								</a>
							</li>
							<?php
						}
						?>
					</ul>
				</nav>
				<?php
			}

			// Get the active sub tab and call its output function.
			$active_sub_tabs = wp_filter_object_list(
				$arr_settings_tabs_sub,
				array(
					'parent_slug' => $active_tab,
					'slug'        => $active_sub_tab,
				)
			);

			$active_sub_tab = reset( $active_sub_tabs );
			$sub_tab_found  = is_array( $active_sub_tab );

			if ( $sub_tab_found ) {
				if ( is_callable( $active_sub_tab['function'] ) ) {
					call_user_func( $active_sub_tab['function'] );
				} else {
					echo esc_html(
						sprintf(
							/* translators: %s is the slug of the sub tab */
							__( 'Function not found for sub tab "%1$s".', 'simple-history' ),
							$active_sub_tab['slug']
						)
					);
				}
			}
		}

		// Output contents for selected main tab,
		// if no sub tab outputted content.
		if ( ! $sub_tab_found ) {
			$arr_active_tab = wp_filter_object_list(
				$arr_settings_tabs,
				array(
					'slug' => $active_tab,
				)
			);

			$arr_active_tab = current( $arr_active_tab );

			// We must have found an active tab and it must have a callable function.
			if ( ! $arr_active_tab || ! is_callable( $arr_active_tab['function'] ) ) {
				_doing_it_wrong( __METHOD__, 'Get subnav html: No valid callback found', '5.7.0' );
				return '';
			}

			call_user_func_array( $arr_active_tab['function'], [] );
		}

		return ob_get_clean();
	}

	/**
	 * Get the slug of the active tab.
	 *
	 * @return string
	 */
	public static function get_active_tab_slug() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return sanitize_text_field( wp_unslash( $_GET['selected-tab'] ?? 'settings' ) );
	}

	/**
	 * Detect clear log query arg and clear log if it is set and valid.
	 */
	public function clear_log_from_url_request() {
		// Clear the log if clear button was clicked in settings
		// and redirect user to show message.
		if (
			isset( $_GET['simple_history_clear_log_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['simple_history_clear_log_nonce'] ) ), 'simple_history_clear_log' )
		) {
			if ( Helpers::user_can_clear_log() ) {
				$num_rows_deleted = Helpers::clear_log();

				/**
				 * Fires after the log has been cleared using
				 * the "Clear log now" button on the settings page.
				 *
				 * @param int $num_rows_deleted Number of rows deleted.
				 */
				do_action( 'simple_history/settings/log_cleared', $num_rows_deleted );
			}

			$msg = __( 'Cleared database', 'simple-history' );

			add_settings_error(
				'simple_history_settings_clear_log',
				'simple_history_settings_clear_log',
				$msg,
				'updated'
			);

			set_transient( 'settings_errors', get_settings_errors(), 30 );

			$goback = esc_url_raw( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );

			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			wp_redirect( $goback );
			exit();
		}
	}
}
