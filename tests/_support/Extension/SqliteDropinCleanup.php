<?php

namespace Extension;

use Codeception\Event\SuiteEvent;
use Codeception\Events;
use Codeception\Extension;

/**
 * Remove the SQLite drop-in that a `--env sqlite` run leaves behind.
 *
 * WPLoader copies wp-browser's SQLite drop-in to wp-content/db.php in the
 * shared `wordpress` volume and never removes it. Until someone does, every
 * other suite talks to an SQLite file instead of MariaDB: WP-CLI reports
 * "The site you have requested is not installed", the functional and
 * acceptance suites fail, and wpunit quietly runs on SQLite. CI gets a fresh
 * volume per job, so this only happens on a development machine.
 *
 * So the drop-in is removed after an SQLite suite, and, in case that run
 * crashed or was interrupted before it got there, before any other suite.
 *
 * Only a file that is the SQLite drop-in is removed, never another db.php.
 */
class SqliteDropinCleanup extends Extension {
	/**
	 * Events this extension listens to.
	 *
	 * @var array<string,string>
	 */
	public static $events = [
		Events::SUITE_BEFORE => 'before_suite',
		Events::SUITE_AFTER  => 'after_suite',
	];

	/**
	 * Remove a leftover drop-in before a suite that does not use SQLite.
	 *
	 * @param SuiteEvent $event Suite event.
	 */
	public function before_suite( SuiteEvent $event ) {
		if ( $this->is_sqlite_run( $event ) ) {
			return;
		}

		if ( $this->remove_dropin( $event ) ) {
			$this->writeln( 'Removed a leftover SQLite drop-in (wp-content/db.php) from an earlier --env sqlite run.' );
		}
	}

	/**
	 * Remove the drop-in once an SQLite suite is done with it.
	 *
	 * @param SuiteEvent $event Suite event.
	 */
	public function after_suite( SuiteEvent $event ) {
		if ( ! $this->is_sqlite_run( $event ) ) {
			return;
		}

		$this->remove_dropin( $event );
	}

	/**
	 * Whether this run uses the sqlite environment.
	 *
	 * @param SuiteEvent $event Suite event.
	 * @return bool
	 */
	private function is_sqlite_run( SuiteEvent $event ) {
		$environment = (string) ( $event->getSettings()['current_environment'] ?? '' );

		return in_array( 'sqlite', explode( ',', $environment ), true );
	}

	/**
	 * Remove wp-content/db.php if it is the SQLite drop-in.
	 *
	 * @param SuiteEvent $event Suite event.
	 * @return bool True if a drop-in was removed.
	 */
	private function remove_dropin( SuiteEvent $event ) {
		$dropin = rtrim( $this->get_wp_root_folder( $event ), '/' ) . '/wp-content/db.php';

		if ( ! is_file( $dropin ) ) {
			return false;
		}

		$contents = (string) file_get_contents( $dropin );

		if ( strpos( $contents, 'SQLITE_DB_DROPIN_VERSION' ) === false ) {
			return false;
		}

		return unlink( $dropin );
	}

	/**
	 * Get the WordPress root folder the suite runs against.
	 *
	 * Read from the suite's own module config, where the %WP_ROOT_FOLDER%
	 * param has already been resolved.
	 *
	 * @param SuiteEvent $event Suite event.
	 * @return string
	 */
	private function get_wp_root_folder( SuiteEvent $event ) {
		$modules_config = $event->getSettings()['modules']['config'] ?? [];

		foreach ( $modules_config as $module_name => $module_config ) {
			if ( ! empty( $module_config['wpRootFolder'] ) ) {
				return $module_config['wpRootFolder'];
			}

			if ( str_ends_with( $module_name, 'WPCLI' ) && ! empty( $module_config['path'] ) ) {
				return $module_config['path'];
			}
		}

		return '/wordpress';
	}
}
