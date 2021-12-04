<?php
/**
 * Show an admin message if old PHP version.
 */
function simple_history_old_version_admin_notice() {
	$ok_wp_version = version_compare( $GLOBALS['wp_version'], '5.2', '>=' );
	$ok_php_version = version_compare( phpversion(), '5.6', '>=' );
	?>
	<div class="updated error">
		<?php
		if ( ! $ok_php_version ) {
			echo '<p>';
			printf(
				/* translators: 1: PHP version */
				esc_html(
					__(
						'Simple History is a great plugin, but to use it your server must have at least PHP 5.6 installed (you have version %s).',
						'simple-history'
					)
				),
				esc_html( phpversion() ) // 1
			);
			echo '</p>';
		}

		if ( ! $ok_wp_version ) {
			echo '<p>';
			printf(
				/* translators: 1: WordPress version */
				esc_html(
					__(
						'Simple History requires WordPress version 5.2 or higher (you have version %s).',
						'simple-history'
					)
				),
				esc_html( $GLOBALS['wp_version'] ) // 1
			);
			echo '</p>';
		}
		?>
	</div>
	<?php
}
