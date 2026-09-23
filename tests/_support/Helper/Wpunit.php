<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class Wpunit extends \Codeception\Module
{
	/**
	 * Per-request logger caches, and what an untouched one holds.
	 *
	 * Only properties that hooks fill *during* a request belong here. A property
	 * that loaded() sets must not be listed: loaded() runs once per process, so
	 * clearing it would leave the logger broken for every later test rather than
	 * fresh. A new cache on a logger needs a line here, and its absence shows up
	 * as a test that only fails on SQLite.
	 *
	 * @var array<string, mixed>
	 */
	private const LOGGER_REQUEST_CACHES = [
		// Logger: the event this logger wrote last.
		'last_insert_id'             => null,
		'last_insert_context'        => null,
		'last_insert_data'           => null,
		// Post_Logger: revisions seen, and posts looked up while rendering.
		'post_revision_ids'          => [],
		'events_given_a_revision_id' => [],
		'looked_up_posts'            => [],
		// Post_Logger: the pre-update snapshot a save is diffed against.
		'old_post_data'              => [],
		// Media_Logger: alt-text edits queued for shutdown, and ids already done.
		'pending_alt_text_changes'   => [],
		'attachment_updated_logged'  => [],
		// Media_Logger: the pre-update snapshot an attachment edit is diffed
		// against. Cleared only by the shutdown handler above, which is
		// registered just when an alt-text edit is pending, so a test that
		// edits an attachment any other way leaves this behind.
		'prev_attachment_values'     => [],
		// User_Logger: the profile diff held between the filter and the commit.
		'user_profile_update_modified_context' => [],
		'app_password_failure_logged'          => false,
		'wp_cli_changes'                       => [
			'user_roles_added'   => [],
			'user_roles_removed' => [],
		],
	];

	/**
	 * Give every test a logger that has not seen a previous request.
	 *
	 * Loggers cache things that only make sense within one HTTP request: the id
	 * and context of the event they last wrote, which attachments they have
	 * already logged, posts they have looked up. Simple_History instantiates each
	 * logger once, so in a test run that state carries from one test into the
	 * next while the database is rolled back underneath it.
	 *
	 * On MySQL that is invisible, because a rollback leaves the auto-increment
	 * where it was and no post id is ever reused. SQLite hands the same ids out
	 * again, so every test creates post id 4 — and a logger holding "I already
	 * logged post 4" from the previous test acts on it. Nine tests failed on
	 * SQLite for this reason, across three loggers.
	 *
	 * Production never hits this: one request, one logger, ids that do not repeat.
	 *
	 * @param \Codeception\TestInterface $test Test about to run.
	 */
	public function _before( \Codeception\TestInterface $test )
	{
		if ( ! class_exists( '\Simple_History\Simple_History' ) ) {
			return;
		}

		foreach ( \Simple_History\Simple_History::get_instance()->get_instantiated_loggers() as $logger ) {
			$instance = $logger['instance'] ?? $logger;

			if ( ! is_object( $instance ) ) {
				continue;
			}

			$class = new \ReflectionClass( $instance );

			foreach ( self::LOGGER_REQUEST_CACHES as $name => $empty ) {
				if ( ! $class->hasProperty( $name ) ) {
					continue;
				}

				$property = $class->getProperty( $name );
				$property->setAccessible( true );
				$property->setValue( $instance, $empty );
			}
		}
	}

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
