<?php

namespace Simple_History\Services;

use Simple_History\Dropins\Sidebar_Add_Ons_Dropin;
use Simple_History\Helpers;
use Simple_History\Simple_History;
use Simple_History\Menu_Page;

/**
 * Service for handling the premium promo page.
 */
class Admin_Page_Premium_Promo extends Service {
	/** @inheritdoc */
	public function loaded() {
		add_action( 'admin_menu', array( $this, 'add_promo_upsell_page' ), 50 );
	}

	/**
	 * Add promo upsell page.
	 */
	public function add_promo_upsell_page() {
		// Hide if premium is active.
		if ( ! Helpers::show_promo_boxes() ) {
			return;
		}

		$admin_page_location = Helpers::get_menu_page_location();

		$upsell_page = ( new Menu_Page() )
			->set_page_title( _x( 'Get more features with Simple History add-ons', 'promo upsell page title', 'simple-history' ) )
			->set_menu_slug( 'simple_history_promo_upsell' )
			->set_callback( [ $this, 'promo_upsell_page_output' ] )
			->set_icon( 'workspace_premium' )
			->set_order( 6 );

		// Set different options depending on location.
		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			$upsell_page
				->set_menu_title( _x( 'Get Premium', 'settings menu name', 'simple-history' ) )
				->set_parent( Simple_History::MENU_PAGE_SLUG )
				->set_location( 'submenu' );
		} elseif ( in_array( $admin_page_location, [ 'inside_dashboard', 'inside_tools' ], true ) ) {
			// If main page is shown as child to tools or dashboard then export page is shown as a tab on the settings page.
			$upsell_page
				->set_menu_title( _x( 'Upgrade to Premium for more features', 'settings menu name', 'simple-history' ) )
				->set_parent( Simple_History::SETTINGS_MENU_PAGE_SLUG );
		}

		$upsell_page->add();
	}

	/**
	 * Output for promo upsell page.
	 */
	public function promo_upsell_page_output() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Admin_Pages::header_output();

		?>
		<div class="wrap">
			<?php
			echo wp_kses(
				Helpers::get_settings_section_title_output(
					__( 'Get more features with Simple History Premium', 'simple-history' ),
					'workspace_premium'
				),
				[
					'span' => [
						'class' => [],
					],
				]
			);
			?>

			<p>
				<?php esc_html_e( 'Simple History add-ons give you more features to your WordPress site.', 'simple-history' ); ?>	
			</p>

			<!-- Grid with premium features.	 -->
			<div class="sh-grid sh-grid-cols-1/3">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Sidebar_Add_Ons_Dropin::get_premium_features_postbox_html();
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Sidebar_Add_Ons_Dropin::get_woocommerce_logger_features_postbox_html();
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo Sidebar_Add_Ons_Dropin::get_debug_and_monitor_features_postbox_html();
				?>
			</div>
				
		</div>
		<?php
	}
}
