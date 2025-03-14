<?php
/**
 * Template for the Help & Support tab.
 *
 * @package SimpleHistory
 */

namespace Simple_History;

defined( 'ABSPATH' ) || die();

?>

<div class="wrap">
	<h2><?php echo esc_html_x( 'Help & Support', 'settings page title', 'simple-history' ); ?></h2>

	<div class="sh-help-section">
		<h3><?php echo esc_html_x( 'Support', 'help page section title', 'simple-history' ); ?></h3>
		<p>
			<?php
			printf(
				/* translators: 1: Link to support forum. */
				wp_kses_post( __( 'For support with the free version, please visit the <a href="%1$s" target="_blank">WordPress.org support forum</a>.', 'simple-history' ) ),
				'https://wordpress.org/support/plugin/simple-history/'
			);
			?>
		</p>
		<p>
			<?php
			printf(
				/* translators: 1: Link to premium support. */
				wp_kses_post( __( 'If you\'re using Simple History Premium or any add-ons, you can access <a href="%1$s" target="_blank">premium support</a>.', 'simple-history' ) ),
				'https://simple-history.com/support/'
			);
			?>
		</p>
	</div>

	<div class="sh-help-section">
		<h3><?php echo esc_html_x( 'Documentation', 'help page section title', 'simple-history' ); ?></h3>
		<p>
			<?php
			printf(
				/* translators: 1: Link to documentation website. */
				wp_kses_post( __( 'Visit the <a href="%1$s" target="_blank">Simple History documentation website</a> for detailed information about features, settings, and how to get the most out of Simple History.', 'simple-history' ) ),
				'https://simple-history.com/docs/'
			);
			?>
		</p>
	</div>

	<div class="sh-help-section">
		<h3><?php echo esc_html_x( 'Get Involved', 'help page section title', 'simple-history' ); ?></h3>
		<p>
			<?php
			printf(
				/* translators: 1: Link to GitHub repository. */
				wp_kses_post( __( 'Simple History is open source! Visit our <a href="%1$s" target="_blank">GitHub repository</a> to contribute, report issues, or explore the code.', 'simple-history' ) ),
				'https://github.com/bonny/WordPress-Simple-History'
			);
			?>
		</p>
	</div>
</div>

<style>
.sh-help-section {
	background: #fff;
	border: 1px solid #ccd0d4;
	padding: 20px;
	margin-bottom: 20px;
	border-radius: 4px;
}

.sh-help-section h3 {
	margin-top: 0;
	margin-bottom: 15px;
	padding-bottom: 10px;
	border-bottom: 1px solid #eee;
}

.sh-help-section p:last-child {
	margin-bottom: 0;
}
</style>
