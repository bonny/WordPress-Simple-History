<?php
namespace Simple_History\Dropins;

use Simple_History\Simple_History;
use Simple_History\Helpers;
use Simple_History\Menu_Page;
use Simple_History\Services\Admin_Pages;

/**
 * Dropin Name: Tools Menu
 * Dropin Description: Adds the Export & Tools menu page with tabbed interface for various tools
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class Tools_Menu_Dropin extends Dropin {
	/** @var string Slug for the tools menu. */
	const MENU_SLUG = 'simple_history_tools';

	/** @var string Slug for the main tools tab. */
	const TOOLS_TAB_SLUG = 'simple_history_tools_tab_main';

	/** @var Menu_Page|null The main Tools tab menu page object. */
	private static $tools_main_tab = null;

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 25 );
		add_action( 'admin_menu', array( $this, 'add_tools_tabs' ), 26 );
		// Hook very early to redirect before WordPress processes the admin page.
		add_action( 'admin_init', array( $this, 'redirect_old_export_url' ), 0 );
	}

	/**
	 * Add Tools menu page.
	 */
	public function add_menu() {
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		// Add page using new menu manager.
		$admin_page_location = Helpers::get_menu_page_location();

		$tools_menu_page = ( new Menu_Page() )
			->set_page_title( _x( 'Simple History Export & Tools', 'tools page title', 'simple-history' ) )
			->set_menu_slug( self::MENU_SLUG )
			->set_callback( [ $this, 'output_tools_page' ] )
			->set_icon( 'build' )
			->set_order( 3 )
			->set_redirect_to_first_child_on_load();

		// Set different options depending on location.
		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			$tools_menu_page
				->set_menu_title( _x( 'Export & Tools', 'tools menu name', 'simple-history' ) )
				->set_parent( Simple_History::MENU_PAGE_SLUG )
				->set_location( 'submenu' );
		} elseif ( in_array( $admin_page_location, [ 'inside_dashboard', 'inside_tools' ], true ) ) {
			// If main page is shown as child to tools or dashboard then tools page is shown as a tab on the settings page.
			$tools_menu_page
				->set_menu_title( _x( 'Export & Tools', 'tools menu name', 'simple-history' ) )
				->set_parent( Simple_History::SETTINGS_MENU_PAGE_SLUG );
		}

		$tools_menu_page->add();

		// Add a hidden redirect page for backwards compatibility with old Export URLs.
		// This prevents permission errors when accessing the old slug.
		// Using empty string instead of null to avoid PHP 8.x deprecation warnings.
		add_submenu_page(
			'', // Empty string parent makes it hidden from menus.
			'Export (Redirect)',
			'Export (Redirect)',
			Helpers::get_view_history_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Filterable, defaults to 'read'.
			'simple_history_export_history',
			[ $this, 'redirect_old_export_url' ]
		);
	}

	/**
	 * Get the main Tools tab menu page object.
	 *
	 * @return Menu_Page|null
	 */
	public static function get_tools_main_tab() {
		return self::$tools_main_tab;
	}

	/**
	 * Add tabs to Tools menu.
	 */
	public function add_tools_tabs() {
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		$admin_page_location = Helpers::get_menu_page_location();

		// Only add the intermediate "Tools" tab when location is 'top' or 'bottom'.
		// When inside dashboard/tools, subtabs are added directly to the Tools page.
		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			// Add main "Tools" tab that will redirect to first subtab.
			self::$tools_main_tab = ( new Menu_Page() )
				->set_menu_slug( self::TOOLS_TAB_SLUG )
				->set_parent( self::MENU_SLUG )
				->set_redirect_to_first_child_on_load()
				->set_order( 1 )
				->add();

			// Add "Overview" subtab with welcome text.
			( new Menu_Page() )
				->set_menu_title( _x( 'Overview', 'tools overview subtab name', 'simple-history' ) )
				->set_page_title( _x( 'Tools Overview', 'tools overview subtab title', 'simple-history' ) )
				->set_menu_slug( 'simple_history_tools_subtab_overview' )
				->set_parent( self::$tools_main_tab )
				->set_callback( [ $this, 'output_tools_intro' ] )
				->set_order( 1 )
				->add();
		} else {
			// When inside dashboard/tools, add subtabs directly to the Tools menu page.
			( new Menu_Page() )
				->set_menu_title( _x( 'Overview', 'tools overview subtab name', 'simple-history' ) )
				->set_page_title( _x( 'Tools Overview', 'tools overview subtab title', 'simple-history' ) )
				->set_menu_slug( 'simple_history_tools_subtab_overview' )
				->set_parent( self::MENU_SLUG )
				->set_callback( [ $this, 'output_tools_intro' ] )
				->set_order( 1 )
				->add();
		}
	}

	/**
	 * Output for the Tools intro subtab.
	 */
	public function output_tools_intro() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Admin_Pages::header_output();
		?>
		<div class="wrap">
			<?php
			echo wp_kses(
				Helpers::get_settings_section_title_output(
					__( 'Tools Overview', 'simple-history' ),
					'dashboard'
				),
				array(
					'span' => array(
						'class' => array(),
					),
				)
			);
			?>

			<p><?php echo esc_html_x( 'Here you can access various tools to manage and work with your history data.', 'tools intro', 'simple-history' ); ?></p>

			<h3><?php echo esc_html__( 'Available Tools', 'simple-history' ); ?></h3>
			<ul>
				<li><strong><?php echo esc_html__( 'Export', 'simple-history' ); ?></strong> - <?php echo esc_html__( 'Export your history data to CSV, JSON, or HTML format.', 'simple-history' ); ?></li>
				<li><strong><?php echo esc_html__( 'Backfill', 'simple-history' ); ?></strong> - <?php echo esc_html__( 'Generate history entries from existing WordPress data like posts, pages, and users.', 'simple-history' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Output for the tools page.
	 * This will automatically redirect to the first child tab.
	 * This callback should rarely be called due to redirect_to_first_child_on_load().
	 */
	public function output_tools_page() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Admin_Pages::header_output();
		// No content needed here - redirect_to_first_child_on_load() should handle navigation.
	}

	/**
	 * Redirect old Export menu URLs to new Tools > Tools tab > Export subtab.
	 * Provides backwards compatibility for bookmarks and old links.
	 * Used as both an admin_init hook and a page callback for the hidden redirect page.
	 */
	public function redirect_old_export_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe: nonce not required for this redirect.
		$page = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );

		// Check for old export page slug.
		if ( $page === 'simple_history_export_history' ) {
			$new_url = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&selected-tab=' . self::TOOLS_TAB_SLUG . '&selected-sub-tab=simple_history_tools_export' );
			wp_safe_redirect( $new_url );
			exit;
		}
	}
}
