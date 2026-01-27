<?php

namespace Simple_History;

use Simple_History\Services\Admin_Pages;

defined( 'ABSPATH' ) || die();

/**
 * @var array{
 *      tables_info:array
 * } $args
 */
// phpcs:ignore SlevomatCodingStandard.ControlStructures.RequireNullCoalesceEqualOperator.RequiredNullCoalesceEqualOperator -- Intentional defensive fallback for template.
$args = $args ?? [];

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo Admin_Pages::header_output();

?>
<div class="wrap sh-DebugPage">
	<?php

	/**
	 * Check that required tables exists.
	 * Some users have had issues with tables not being created after
	 * moving site from one server to another. Probably because the history tables
	 * are not moved with the rest of the database, but the options table are and that
	 * confuses Simple History.
	 */
	foreach ( $args['tables_info'] as $table_info ) {
		if ( $table_info['table_exists'] ) {
			continue;
		}

		echo '<div class="notice notice-error">';
		echo '<p>';
		printf(
			/* translators: %s table name. */
			esc_html_x( 'Required table "%s" does not exist.', 'debug dropin', 'simple-history' ),
			esc_html( $table_info['table_name'] )
		);
		echo '</p>';
		echo '</div>';
	}

	echo wp_kses(
		Helpers::get_settings_section_title_output( __( 'Debug', 'simple-history' ), 'build' ),
		[
			'span' => [
				'class' => [],
			],
		]
	);

	/**
	 * REST API Status section.
	 */
	?>
	<div class="sh-DebugPage-section">
		<h3><?php echo esc_html_x( 'REST API Status', 'debug dropin', 'simple-history' ); ?></h3>
		<div class="sh-DebugPage-restApiStatus" id="sh-rest-api-status">
			<span class="sh-DebugPage-restApiStatus-checking">
				<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>
				<?php echo esc_html_x( 'Checking...', 'debug dropin', 'simple-history' ); ?>
			</span>
		</div>
	</div>

	<div class="sh-DebugPage-section">
		<h3><?php echo esc_html_x( 'Support Information', 'debug dropin', 'simple-history' ); ?></h3>
		<p class="description">
			<?php
			printf(
				/* translators: %s: link to support forum */
				esc_html_x( 'Copy this information and include it when posting in the %s.', 'debug dropin', 'simple-history' ),
				'<a href="https://wordpress.org/support/plugin/simple-history/" target="_blank">' . esc_html_x( 'support forum', 'debug dropin', 'simple-history' ) . '</a>'
			);
			?>
		</p>
		<div id="sh-warnings-container"></div>
		<div id="sh-support-info-container" style="display: none;">
			<textarea
				id="sh-support-info-textarea"
				class="sh-DebugPage-supportInfoTextarea"
				readonly
				rows="25"
			></textarea>
			<p class="sh-DebugPage-buttons">
				<button type="button" class="button button-primary" id="sh-copy-support-info">
					<?php echo esc_html_x( 'Copy to Clipboard', 'debug dropin', 'simple-history' ); ?>
				</button>
				<button type="button" class="button" id="sh-gather-support-info">
					<?php echo esc_html_x( 'Reload Data', 'debug dropin', 'simple-history' ); ?>
				</button>
				<span class="spinner" id="sh-gather-support-info-spinner" style="float: none; margin-top: 0;"></span>
			</p>
			<p class="sh-DebugPage-copyStatus" id="sh-copy-status"></p>
		</div>
	</div>
</div>
