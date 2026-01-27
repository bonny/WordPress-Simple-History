<?php
/**
 * Template for the Help & Support page.
 *
 * @package SimpleHistory
 */

namespace Simple_History;

use Simple_History\Dropins\Sidebar_Add_Ons_Dropin;

defined( 'ABSPATH' ) || die();

/**
 * @var array{
 *      tables_info:array
 * } $args
 */
// phpcs:ignore SlevomatCodingStandard.ControlStructures.RequireNullCoalesceEqualOperator.RequiredNullCoalesceEqualOperator -- Intentional defensive fallback for template.
$args = $args ?? [];

// FAQ items from https://simple-history.com/docs/faq-frequently-asked-questions/.
$faq_items = [
	[
		'question' => 'How do I control what is logged?',
	],
	[
		'question' => 'Can I log when a user does something on my website, like visits a page?',
	],
	[
		'question' => 'Can we track things that happen on the frontend of our website?',
	],
	[
		'question' => 'Can the RSS feed show more than 10 items?',
	],
	[
		'question' => 'Can the log be shown on the front of my website?',
	],
	[
		'question' => 'How do I clear the log?',
	],
	[
		'question' => 'Can I keep items longer than 60 days?',
	],
	[
		'question' => 'How do I see more details about an event?',
	],
	[
		'question' => 'How can I see the IP address of an event?',
	],
	[
		'question' => 'Why is the IP address always end with a zero (0)',
	],
	[
		'question' => 'Who is the "other" user that sometimes is responsible for logged events?',
	],
	[
		'question' => 'Is the plugin GDPR compliant?',
	],
	[
		'question' => 'My question in not answered in this FAQ',
	],
];
$faq_url   = 'https://simple-history.com/docs/faq-frequently-asked-questions/';

?>

<div class="wrap sh-HelpAndSupportPage">

	<?php
	echo wp_kses(
		Helpers::get_settings_section_title_output( __( 'Help & Support', 'simple-history' ), 'help' ),
		[
			'span' => [
				'class' => [],
			],
		]
	);

	// Check that required tables exist and show warnings if not.
	foreach ( $args['tables_info'] as $table_info ) {
		if ( $table_info['table_exists'] ) {
			continue;
		}

		echo '<div class="notice notice-error">';
		echo '<p>';
		printf(
			/* translators: %s table name. */
			esc_html_x( 'Required table "%s" does not exist.', 'help page', 'simple-history' ),
			esc_html( $table_info['table_name'] )
		);
		echo '</p>';
		echo '</div>';
	}
	?>

	<!-- Status Bar -->
	<div class="sh-StatusBar" id="sh-status-bar">
		<div class="sh-StatusBar-item sh-StatusBar-status" id="sh-status-bar-status">
			<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>
			<?php echo esc_html_x( 'Checking...', 'help page', 'simple-history' ); ?>
		</div>
	</div>

	<div class="sh-HelpPage-grid">

		<!-- Get Help card with system information (spans 2 rows) -->
		<div class="postbox sh-PremiumFeaturesPostbox sh-HelpPage-getHelp">
			<div class="inside">
				<h3 class="sh-PremiumFeaturesPostbox-title"><?php echo esc_html_x( 'Get Help', 'help page section title', 'simple-history' ); ?></h3>

				<p>
					<?php
					printf(
						/* translators: 1: Link to support forum. */
						wp_kses_post( __( 'For support with the free version, please visit the <a href="%1$s" target="_blank">WordPress.org support forum</a>.', 'simple-history' ) ),
						'https://simple-history.com/support/'
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

				<!-- Warnings container -->
				<div id="sh-warnings-container"></div>

				<!-- System Information -->
				<h4><?php echo esc_html_x( 'System Information', 'help page section title', 'simple-history' ); ?></h4>
				<p class="description">
					<?php echo esc_html_x( 'Include this when requesting support:', 'help page', 'simple-history' ); ?>
				</p>
				<div id="sh-support-info-container">
					<textarea
						id="sh-support-info-textarea"
						class="sh-HelpPage-supportInfoTextarea"
						readonly
						rows="12"
					><?php echo esc_textarea( _x( 'Gathering data...', 'help page', 'simple-history' ) ); ?></textarea>
					<p class="sh-HelpPage-buttons">
						<button type="button" class="button button-primary" id="sh-copy-support-info">
							<?php echo esc_html_x( 'Copy to Clipboard', 'help page', 'simple-history' ); ?>
						</button>
						<button type="button" class="button" id="sh-gather-support-info">
							<?php echo esc_html_x( 'Refresh', 'help page', 'simple-history' ); ?>
						</button>
						<span class="spinner" id="sh-gather-support-info-spinner" style="float: none; margin-top: 0;"></span>
					</p>
					<p class="sh-HelpPage-copyStatus" id="sh-copy-status"></p>
				</div>
			</div>
		</div>

		<!-- Documentation card -->
		<div class="postbox sh-PremiumFeaturesPostbox">
			<div class="inside">
				<h3 class="sh-PremiumFeaturesPostbox-title"><?php echo esc_html_x( 'Documentation', 'help page section title', 'simple-history' ); ?></h3>
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
		</div>

		<!-- FAQ card -->
		<div class="postbox sh-PremiumFeaturesPostbox">
			<div class="inside">
				<h3 class="sh-PremiumFeaturesPostbox-title"><?php echo esc_html_x( 'Frequently Asked Questions', 'help page section title', 'simple-history' ); ?></h3>

				<ul>
					<?php
					$faq_num_items_to_show = 5;

					for ( $i = 0; $i < $faq_num_items_to_show; $i++ ) {
						$faq_item = $faq_items[ $i ];
						?>
						<li>
							<a href="<?php echo esc_url( $faq_url ); ?>"><?php echo esc_html( $faq_item['question'] ); ?></a>
						</li>
						<?php
					}
					?>
				</ul>

				<p><a href="<?php echo esc_url( $faq_url ); ?>">&raquo; <?php esc_html_e( 'View all Frequently Asked Questions', 'simple-history' ); ?></a>.</p>
			</div>
		</div>
	</div>

	<!-- Grid with premium features. -->
	<div class="sh-grid sh-grid-cols-1/3">
		<?php
		if ( Helpers::show_promo_boxes() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Sidebar_Add_Ons_Dropin::get_premium_features_postbox_html();
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Sidebar_Add_Ons_Dropin::get_woocommerce_logger_features_postbox_html();
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Sidebar_Add_Ons_Dropin::get_debug_and_monitor_features_postbox_html();
		}
		?>
	</div>

	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo Sidebar_Add_Ons_Dropin::get_hosting_sponsor_postbox_html();
	?>
</div>
