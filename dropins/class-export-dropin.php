<?php
namespace Simple_History\Dropins;

use Simple_History\Simple_History;
use Simple_History\Export;
use Simple_History\Helpers;
use Simple_History\Menu_Page;
use Simple_History\Services\Admin_Pages;

/**
 * Dropin Name: Export
 * Dropin Description: Adds a tab with export options under the Tools menu
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class Export_Dropin extends Dropin {
	/** @var string Slug for the export menu tab. */
	const MENU_SLUG = 'simple_history_tools_export';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 30 );
		add_action( 'admin_init', array( $this, 'download_export' ) );
	}

	/**
	 * Add export subtab under Tools > Tools tab.
	 */
	public function add_menu() {
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		$admin_page_location = Helpers::get_menu_page_location();

		// Determine parent based on location.
		// When location is 'top' or 'bottom', use the Tools main tab object.
		// When inside dashboard/tools, use the Tools menu page slug directly.
		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			$tools_parent = Tools_Menu_Dropin::get_tools_main_tab();
			if ( ! $tools_parent ) {
				$tools_parent = Tools_Menu_Dropin::TOOLS_TAB_SLUG;
			}
		} else {
			$tools_parent = Tools_Menu_Dropin::MENU_SLUG;
		}

		( new Menu_Page() )
			->set_page_title( _x( 'Export History', 'export subtab title', 'simple-history' ) )
			->set_menu_title( _x( 'Export', 'export subtab name', 'simple-history' ) )
			->set_menu_slug( self::MENU_SLUG )
			->set_callback( [ $this, 'output_export_page' ] )
			->set_order( 2 )
			->set_parent( $tools_parent )
			->add();
	}

	/**
	 * Download export file.
	 */
	public function download_export() {
		$page   = sanitize_key( wp_unslash( $_GET['page'] ?? '' ) );
		$action = sanitize_key( wp_unslash( $_POST['simple-history-action'] ?? '' ) );

		// Bail if not correct page.
		// Check for the tools page, export tab, and settings page (when tools is shown as a tab).
		// Examples:
		// - Main menu: http://example.com/wp-admin/admin.php?page=simple_history_tools&selected-tab=simple_history_tools_export
		// - Settings tab: http://example.com/wp-admin/options-general.php?page=simple_history_settings_page&selected-tab=simple_history_tools.
		if ( $page !== Tools_Menu_Dropin::MENU_SLUG && $page !== Simple_History::SETTINGS_MENU_PAGE_SLUG ) {
			return;
		}

		// Bail if not export action.
		if ( $action !== 'export-history' ) {
			return;
		}

		// Will die if nonce not valid.
		check_admin_referer( self::class . '-action-export' );

		$export_format = sanitize_text_field( wp_unslash( $_POST['format'] ?? 'json' ) );

		$csv_include_headers = isset( $_POST['csv_include_headers'] ) ? true : false;

		$export = new Export();
		$export->set_query_args(
			[
				'paged'          => 1,
				// 3000 is batch size.
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- This is rarely used and only used on demand.
				'posts_per_page' => 3000,
			]
		);
		$export->set_download_format( $export_format );
		$export->set_options(
			[
				'include_headers' => $csv_include_headers,
			]
		);
		$export->download();
	}

	/**
	 * Output for the export tab on the settings page.
	 */
	public function output_export_page() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Admin_Pages::header_output();
		?>

		<div class="wrap">
			<?php
			echo wp_kses(
				Helpers::get_settings_section_title_output(
					__( 'Export history', 'simple-history' ),
					'download'
				),
				array(
					'span' => array(
						'class' => array(),
					),
				)
			);
			?>
			
			<p><?php echo esc_html_x( 'The export function will export the full history.', 'Export dropin: introtext', 'simple-history' ); ?></p>

			<form method="post">

				<h3>
					<?php echo esc_html_x( 'Choose format to export to', 'Export dropin: format', 'simple-history' ); ?>
				</h3>

				<p>
					<label>
						<input type="radio" name="format" value="csv" checked>
						<?php echo esc_html_x( 'CSV', 'Export dropin: export format', 'simple-history' ); ?>
					</label>
				</p>

				<p style="margin-left: 1rem;">
					<label>
						<input type="checkbox" name="csv_include_headers" value="1">
						<?php echo esc_html_x( 'Include headers', 'Export dropin: include headers', 'simple-history' ); ?>
					</label>
				</p>

				<p>
					<label>
						<input type="radio" name="format" value="json">
						<?php echo esc_html_x( 'JSON', 'Export dropin: export format', 'simple-history' ); ?>
					</label>
				</p>

				<p>
					<label>
						<input type="radio" name="format" value="html">
						<?php echo esc_html_x( 'HTML', 'Export dropin: export format', 'simple-history' ); ?>
					</label>
				</p>

				<p>
					<button type="submit" class="button button-primary">
						<?php echo esc_html_x( 'Download Export File', 'Export dropin: submit button', 'simple-history' ); ?>
					</button>
					<input type="hidden" name="simple-history-action" value="export-history">
				</p>

				<?php
				wp_nonce_field( self::class . '-action-export' );
				?>

			</form>
		
		</div>
		<?php
	}
}
