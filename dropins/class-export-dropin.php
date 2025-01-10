<?php
namespace Simple_History\Dropins;

use Simple_History\Export;
use Simple_History\Helpers;
use Simple_History\Simple_History;

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
		$this->simple_history->register_settings_tab(
			array(
				'slug' => 'export',
				'name' => _x( 'Export', 'Export dropin: Tab name on settings page', 'simple-history' ),
				'icon' => 'download',
				'order' => 50,
				'function' => array( $this, 'output' ),
			)
		);

		add_action( 'load-settings_page_simple_history_settings_menu_slug', array( $this, 'download_export' ) );
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
	public function output() {
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
						<input type="radio" name="format" value="json" checked>
						<?php echo esc_html_x( 'JSON', 'Export dropin: export format', 'simple-history' ); ?>
					</label>
				</p>

				<p>
					<label>
						<input type="radio" name="format" value="csv">
						<?php echo esc_html_x( 'CSV', 'Export dropin: export format', 'simple-history' ); ?>
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
