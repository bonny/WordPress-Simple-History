<?php
namespace Simple_History\Dropins;

use Simple_History\Simple_History;
use Simple_History\Export;
use Simple_History\Helpers;
use Simple_History\Services\Admin_Pages;

/**
 * Dropin Name: Export
 * Dropin Description: Adds a tab with export options
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class Export_Dropin extends Dropin {
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'load-simple-history_page_simple_history_export_history', array( $this, 'download_export' ) );
	}

	/**
	 * Add submenu page for export
	 */
	public function add_submenu() {
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		add_submenu_page(
			Simple_History::MENU_PAGE_SLUG,
			__( 'Export', 'simple-history' ),
			__( 'Export', 'simple-history' ),
			'manage_options',
			'simple_history_export_history',
			array( $this, 'output_export_page' ),
			50
		);
	}

	/**
	 * Download export file.
	 */
	public function download_export() {
		$action = sanitize_key( wp_unslash( $_POST['simple-history-action'] ?? '' ) );

		// Bail if not export action.
		if ( $action !== 'export-history' ) {
			return;
		}

		// Will die if nonce not valid.
		check_admin_referer( self::class . '-action-export' );

		$export_format = sanitize_text_field( wp_unslash( $_POST['format'] ?? 'json' ) );

		$export = new Export();
		$export->set_query_args(
			[
				'paged' => 1,
				// 3000 is batch size.
				'posts_per_page' => 3000,
			]
		);
		$export->set_download_format( $export_format );
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

				<h3><?php echo esc_html_x( 'Choose format to export to', 'Export dropin: format', 'simple-history' ); ?></h3>

				<p>
					<label>
						<input type="radio" name="format" value="csv" checked>
						<?php echo esc_html_x( 'CSV', 'Export dropin: export format', 'simple-history' ); ?>
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
