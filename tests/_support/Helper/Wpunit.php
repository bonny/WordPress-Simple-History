<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class Wpunit extends \Codeception\Module
{
	/**
	 * Leave wp-content/upgrade writable for the acceptance suite.
	 *
	 * WP_Upgrader creates that directory the first time a test installs a
	 * plugin or theme zip. The php-cli container runs as root while Apache
	 * runs as www-data, so it is left root-owned in the shared `wordpress`
	 * volume. `npm test` runs wpunit before acceptance, so every later
	 * acceptance test that installs a plugin then fails with
	 * "Could not create directory. /var/www/html/wp-content/upgrade/<slug>".
	 * The volume outlives `docker compose down`, so the breakage persists
	 * until the next `down -v`.
	 */
	public function _afterSuite()
	{
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			return;
		}

		$upgrade_dir = WP_CONTENT_DIR . '/upgrade';

		if ( ! is_dir( $upgrade_dir ) ) {
			return;
		}

		@chmod( $upgrade_dir, 0777 );
	}
}
