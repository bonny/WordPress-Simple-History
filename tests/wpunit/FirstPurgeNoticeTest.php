<?php

use Simple_History\Simple_History;
use Simple_History\Services\First_Purge_Notice_Service;

/**
 * Issue 324: one-time notice shortly before a new install starts removing the
 * events it has logged itself.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit:FirstPurgeNoticeTest
 */
class FirstPurgeNoticeTest extends \Codeception\TestCase\WPTestCase {
	/** @var callable */
	private $retention_filter;

	/** @var callable|null */
	private $day_of_week_filter;

	public function setUp(): void {
		parent::setUp();

		$admin_id = self::factory()->user->create(
			[
				'role'       => 'administrator',
				'user_email' => 'admin-purge@example.com',
			]
		);

		wp_set_current_user( $admin_id );

		$this->retention_filter = function () {
			return 30;
		};

		add_filter( 'simple_history/db_purge_days_interval', $this->retention_filter );

		// Be on the Simple History log page. The menu pages are only registered on
		// admin_menu, and the page check compares against their slugs.
		global $pagenow;
		$pagenow = 'admin.php';
		set_current_screen( 'toplevel_page_' . Simple_History::MENU_PAGE_SLUG );
		do_action( 'admin_menu' );
		$_GET['page'] = Simple_History::MENU_PAGE_SLUG;

		update_option( 'simple_history_email_report_enabled', false );
		First_Purge_Notice_Service::set_pending();
	}

	public function tearDown(): void {
		remove_filter( 'simple_history/db_purge_days_interval', $this->retention_filter );

		if ( $this->day_of_week_filter ) {
			remove_filter( 'simple_history/day_of_week_to_purge_db', $this->day_of_week_filter );
		}

		unset( $_GET['page'] );

		global $pagenow;
		$pagenow = 'index.php';

		delete_option( First_Purge_Notice_Service::OPTION_NAME );
		delete_option( 'simple_history_email_report_enabled' );

		parent::tearDown();
	}

	/**
	 * Set up the install date and purge weekday so the first purge day is this
	 * many days from today, whatever weekday today is.
	 *
	 * @param int $days Days from today, negative for the past.
	 */
	private function first_purge_day_in( $days ) {
		$purge_day = strtotime( gmdate( 'Y-m-d' ) . ' 00:00:00 UTC' ) + ( $days * DAY_IN_SECONDS );

		$this->day_of_week_filter = function () use ( $purge_day ) {
			return (int) gmdate( 'N', $purge_day );
		};

		add_filter( 'simple_history/day_of_week_to_purge_db', $this->day_of_week_filter );

		// Events logged at install expire one hour into the purge day.
		$this->set_install_date( $purge_day + HOUR_IN_SECONDS - ( 30 * DAY_IN_SECONDS ) );
	}

	/**
	 * @param int $timestamp Install timestamp.
	 */
	private function set_install_date( $timestamp ) {
		update_option( 'simple_history_install_date_gmt', gmdate( 'Y-m-d H:i:s', $timestamp ) );
	}

	/**
	 * Run the notice and return its output.
	 *
	 * @return string
	 */
	private function render_notice() {
		$service = Simple_History::get_instance()->get_service( First_Purge_Notice_Service::class );

		$this->assertInstanceOf( First_Purge_Notice_Service::class, $service );

		ob_start();
		$service->maybe_show_notice();

		return ob_get_clean();
	}

	public function test_first_purge_is_the_first_sunday_after_retention_ends() {
		// Monday noon. Thirty days later is Wednesday 2026-09-09, and the purge runs on Sundays.
		$this->set_install_date( strtotime( '2026-08-10 12:00:00 UTC' ) );

		$this->assertSame( strtotime( '2026-09-13 00:00:00 UTC' ), First_Purge_Notice_Service::get_first_purge_day_timestamp( 30 ) );
		$this->assertSame( 4, First_Purge_Notice_Service::get_days_until_first_purge( 30, strtotime( '2026-09-09 12:00:00 UTC' ) ) );
		$this->assertSame( 5, First_Purge_Notice_Service::get_days_until_first_purge( 30, strtotime( '2026-09-08 00:00:00 UTC' ) ) );
	}

	public function test_first_purge_is_the_same_day_when_retention_ends_on_a_purge_day() {
		// Thirty days later is Sunday 2026-09-13.
		$this->set_install_date( strtotime( '2026-08-14 10:00:00 UTC' ) );

		$this->assertSame( strtotime( '2026-09-13 00:00:00 UTC' ), First_Purge_Notice_Service::get_first_purge_day_timestamp( 30 ) );
	}

	public function test_first_purge_follows_the_purge_day_filter() {
		$this->set_install_date( strtotime( '2026-08-10 12:00:00 UTC' ) );

		// Wednesday, the day retention ends.
		$this->day_of_week_filter = function () {
			return 3;
		};

		add_filter( 'simple_history/day_of_week_to_purge_db', $this->day_of_week_filter );

		$this->assertSame( strtotime( '2026-09-09 00:00:00 UTC' ), First_Purge_Notice_Service::get_first_purge_day_timestamp( 30 ) );
	}

	public function test_not_shown_before_the_window() {
		$this->first_purge_day_in( 10 );

		$this->assertSame( '', $this->render_notice() );
		$this->assertSame( 'pending', get_option( First_Purge_Notice_Service::OPTION_NAME ) );
	}

	public function test_shown_once_in_the_days_before_the_purge() {
		$this->first_purge_day_in( 4 );

		$output = $this->render_notice();

		$this->assertStringContainsString( 'In 4 days, Simple History starts removing', $output );
		$this->assertStringContainsString( 'Once a week, events older than 30 days are removed', $output );
		$this->assertStringContainsString( 'utm_campaign=premium_retention_first_purge', $output );
		$this->assertStringContainsString( 'Email me a weekly summary', $output );
		$this->assertSame( 'shown', get_option( First_Purge_Notice_Service::OPTION_NAME ) );

		$this->assertSame( '', $this->render_notice() );
	}

	public function test_reworded_when_the_purge_has_started() {
		$this->first_purge_day_in( -2 );

		$this->assertStringContainsString( 'Simple History has started removing', $this->render_notice() );
	}

	public function test_expires_when_too_late() {
		$this->first_purge_day_in( -10 );

		$this->assertSame( '', $this->render_notice() );
		$this->assertSame( 'expired', get_option( First_Purge_Notice_Service::OPTION_NAME ) );
	}

	public function test_not_shown_on_the_wordpress_dashboard() {
		$this->first_purge_day_in( 4 );

		global $pagenow;
		$pagenow = 'index.php';
		unset( $_GET['page'] );

		$this->assertSame( '', $this->render_notice() );
		$this->assertSame( 'pending', get_option( First_Purge_Notice_Service::OPTION_NAME ) );
	}

	public function test_shown_when_the_menu_is_placed_under_dashboard() {
		$this->first_purge_day_in( 4 );

		// With the menu under Dashboard, the log page is index.php?page=simple_history_admin_menu_page.
		global $pagenow;
		$pagenow = 'index.php';

		$this->assertStringContainsString( 'In 4 days', $this->render_notice() );
	}

	public function test_not_shown_to_sites_without_the_flag() {
		delete_option( First_Purge_Notice_Service::OPTION_NAME );
		$this->first_purge_day_in( 4 );

		$this->assertSame( '', $this->render_notice() );
	}

	public function test_not_shown_when_events_are_kept_forever() {
		$this->first_purge_day_in( 4 );

		add_filter( 'simple_history/db_purge_days_interval', '__return_zero', 20 );

		$output = $this->render_notice();

		remove_filter( 'simple_history/db_purge_days_interval', '__return_zero', 20 );

		$this->assertSame( '', $output );
		$this->assertSame( 'pending', get_option( First_Purge_Notice_Service::OPTION_NAME ) );
	}

	public function test_not_shown_to_users_who_can_not_manage_options() {
		$this->first_purge_day_in( 4 );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$this->assertSame( '', $this->render_notice() );
		$this->assertSame( 'pending', get_option( First_Purge_Notice_Service::OPTION_NAME ) );
	}

	public function test_email_offer_is_left_out_when_the_user_already_gets_the_email() {
		$this->first_purge_day_in( 4 );

		update_option( 'simple_history_email_report_enabled', '1' );
		update_option( 'simple_history_email_report_recipients', 'admin-purge@example.com' );

		$output = $this->render_notice();

		delete_option( 'simple_history_email_report_recipients' );

		$this->assertStringContainsString( 'In 4 days', $output );
		$this->assertStringNotContainsString( 'Email me a weekly summary', $output );
	}
}
