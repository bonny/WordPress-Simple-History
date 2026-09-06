<?php

use Simple_History\Services\Admin_Pages;
use Simple_History\Simple_History;

/**
 * Tests for Admin_Pages::on_admin_page_access_denied_redirect_prev_menu_location().
 *
 * Reproduces issue #639 — the bail condition uses `&&` where it needs `||`,
 * causing the legacy-URL redirect to fire on any `admin_page_access_denied`
 * event that lands on `index.php` (the dashboard) OR carries
 * `?page=simple_history_page`, instead of only when both match. For users
 * who can't access the redirect target, this loops until the browser stops it.
 */
class AdminPagesAccessDeniedRedirectTest extends \Codeception\TestCase\WPTestCase {

	/** @var Admin_Pages */
	private $admin_pages;

	/** @var string|null */
	private $captured_redirect;

	/** @var array */
	private $original_get;

	/** @var string|null */
	private $original_pagenow;

	/** @var int */
	private $admin_user_id;

	public function setUp(): void {
		parent::setUp();

		foreach ( Simple_History::get_instance()->get_instantiated_services() as $service ) {
			if ( $service instanceof Admin_Pages ) {
				$this->admin_pages = $service;
				break;
			}
		}

		$this->assertInstanceOf(
			Admin_Pages::class,
			$this->admin_pages,
			'Admin_Pages service must be loaded.'
		);

		$this->admin_user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_user_id );

		$this->original_get     = $_GET;
		$this->original_pagenow = $GLOBALS['pagenow'] ?? null;

		$this->captured_redirect = null;
		add_filter( 'wp_redirect', [ $this, 'capture_redirect' ], 99, 1 );
	}

	public function tearDown(): void {
		remove_filter( 'wp_redirect', [ $this, 'capture_redirect' ], 99 );

		$_GET = $this->original_get;

		if ( $this->original_pagenow === null ) {
			unset( $GLOBALS['pagenow'] );
		} else {
			$GLOBALS['pagenow'] = $this->original_pagenow;
		}

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Filter callback that records the redirect target and throws, so the
	 * `exit;` after `wp_safe_redirect()` in the handler never runs.
	 */
	public function capture_redirect( $location ) {
		$this->captured_redirect = $location;
		throw new \RuntimeException( 'redirect captured: ' . (string) $location );
	}

	/**
	 * Invoke the handler with a given ?page= and $pagenow, return the
	 * captured redirect target (or null if the handler bailed).
	 */
	private function call_handler( $page, $pagenow ) {
		$_GET['page']       = $page;
		$GLOBALS['pagenow'] = $pagenow;

		try {
			$this->admin_pages->on_admin_page_access_denied_redirect_prev_menu_location();
		} catch ( \RuntimeException $e ) {
			// Expected when the handler actually tries to redirect.
		}

		return $this->captured_redirect;
	}

	/**
	 * Legacy URL — page=simple_history_page on index.php — should redirect.
	 * This is the one case the handler is supposed to act on.
	 */
	public function test_legacy_url_redirects() {
		$redirect = $this->call_handler( 'simple_history_page', 'index.php' );

		$this->assertNotNull(
			$redirect,
			'Legacy URL (page=simple_history_page on index.php) should trigger the redirect.'
		);
	}

	/**
	 * Plain dashboard access-denied event (no Simple History page slug).
	 *
	 * Reproduces issue #639: the buggy `&&` falls through to the redirect
	 * here, which then loops for users lacking the view capability.
	 */
	public function test_plain_dashboard_does_not_redirect() {
		$redirect = $this->call_handler( '', 'index.php' );

		$this->assertNull(
			$redirect,
			'An admin_page_access_denied event on the dashboard with no legacy page slug must not trigger the legacy redirect.'
		);
	}

	/**
	 * Some other admin page with ?page=simple_history_page somehow set —
	 * not the legacy URL pattern, must not redirect.
	 */
	public function test_admin_php_with_legacy_slug_does_not_redirect() {
		$redirect = $this->call_handler( 'simple_history_page', 'admin.php' );

		$this->assertNull(
			$redirect,
			'A non-dashboard pagenow must not trigger the legacy redirect even if ?page=simple_history_page is present.'
		);
	}

	/**
	 * Control case: completely unrelated admin page — must not redirect.
	 */
	public function test_unrelated_page_does_not_redirect() {
		$redirect = $this->call_handler( 'some_other_plugin', 'admin.php' );

		$this->assertNull(
			$redirect,
			'Unrelated admin pages must not trigger the legacy redirect.'
		);
	}

	/**
	 * Defense-in-depth: even on the legacy URL, don't redirect a user who
	 * can't access the target page — otherwise the same access-denied event
	 * would fire again and could loop.
	 */
	public function test_legacy_url_does_not_redirect_user_without_capability() {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $subscriber_id );

		$redirect = $this->call_handler( 'simple_history_page', 'index.php' );

		$this->assertNull(
			$redirect,
			'A user lacking the view-history capability must not be redirected — that would loop.'
		);
	}
}
