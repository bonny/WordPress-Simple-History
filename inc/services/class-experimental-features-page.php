<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Menu_Page;
use Simple_History\Services\Service;
use Simple_History\Simple_History;

/**
 * Service that adds an experimental features admin page.
 *
 * This page provides access to experimental features and tools
 * for testing and development purposes.
 */
class Experimental_Features_Page extends Service {
	/**
	 * Slug for the experimental features page.
	 */
	const PAGE_SLUG = 'simple_history_experimental_features';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Only add experimental features page if experimental features are enabled.
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		add_action( 'admin_menu', [ $this, 'add_experimental_features_page' ], 100 );
	}

	/**
	 * Add experimental features admin page.
	 */
	public function add_experimental_features_page() {
		$admin_page_location = Helpers::get_menu_page_location();

		$experimental_page = ( new Menu_Page() )
			->set_page_title( __( 'Experimental Features - Simple History', 'simple-history' ) )
			->set_menu_slug( self::PAGE_SLUG )
			->set_capability( 'manage_options' )
			->set_callback( [ $this, 'render_page' ] )
			->set_icon( 'experiment' )
			->set_order( 5 );

		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			$experimental_page
				->set_menu_title( __( 'Experimental', 'simple-history' ) )
				->set_parent( Simple_History::MENU_PAGE_SLUG )
				->set_location( 'submenu' );
		} else {
			// When inside dashboard/tools, add as a tab on the settings page.
			// Don't set location - it will be determined by the parent.
			$experimental_page
				->set_menu_title( __( 'Experimental features', 'simple-history' ) )
				->set_parent( Simple_History::SETTINGS_MENU_PAGE_SLUG );
		}

		$experimental_page->add();
	}

	/**
	 * Render the experimental features page.
	 */
	public function render_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Experimental Features', 'simple-history' ); ?></h1>

			<div class="card">
				<h2><?php esc_html_e( 'About Experimental Features', 'simple-history' ); ?></h2>

				<p>
					<?php
					esc_html_e(
						'This page provides access to features that are still under development or testing. These features may change or be removed in future versions.',
						'simple-history'
					);
					?>
				</p>

				<p>
					<?php esc_html_e( 'Experimental features should be used with caution on production sites. Always test on a staging environment first.', 'simple-history' ); ?>
				</p>
			</div>

			<?php
			/**
			 * Hook for experimental features to render their UI.
			 *
			 * Each feature can hook into this action to display its own card/section.
			 *
			 * @since 2.x
			 *
			 * @param Simple_History $simple_history Simple History instance.
			 */
			do_action( 'simple_history/experimental_features/render', $this->simple_history );
			?>
		</div>
		<?php
	}
}
