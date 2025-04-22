<?php
namespace Simple_History;

use Simple_History\Dropins\Sidebar_Add_Ons_Dropin;
use Simple_History\Simple_History;

defined( 'ABSPATH' ) || die();

/**
 * This is the output of the general settings page.
 */
?>
<div class="wrap sh-Page-content">
	<div class="sh-grid sh-grid-cols-2/3">
		<form method="post" action="options.php">
			<?php
			// Prints out all settings sections added to a particular settings page.
			do_settings_sections( Simple_History::SETTINGS_MENU_SLUG );

			// Output nonce, action, and option_page fields.
			settings_fields( Simple_History::SETTINGS_GENERAL_OPTION_GROUP );

			submit_button();
			?>
		</form>

		<!-- 
		This is currently placed placed wrong due to bug in do_settings_sections() that is
		causing it to not output after_section-html.
		Related track tickets:
		https://core.trac.wordpress.org/ticket/62746
		https://core.trac.wordpress.org/changeset/59564

		Or wait, I solved it by adding an empty settings section to the support settings section.
		-->
		<?php
		if ( ! Helpers::is_premium_add_on_active() ) {
			?>
			<div style="margin-top: 2rem;">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Sidebar_Add_Ons_Dropin::get_premium_features_postbox_html();
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Sidebar_Add_Ons_Dropin::get_woocommerce_logger_features_postbox_html();
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Sidebar_Add_Ons_Dropin::get_debug_and_monitor_features_postbox_html();
				?>
			</div>
			<?php
		}
		?>
	</div>
</div>
