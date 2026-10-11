<?php

use Simple_History\Log_Query;
use Simple_History\Simple_History;

/**
 * The occasions query must only return events from loggers the current user
 * can read. Occasion IDs are predictable (an md5 of the logger slug and a
 * known string), so without the logger filter an Editor could read failed
 * logins and other admin-only events by guessing one.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit OccasionsLoggerCapabilityTest
 */
class OccasionsLoggerCapabilityTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * Log three events with the same occasions ID through the User logger,
	 * which needs `edit_users` to read.
	 *
	 * @return string The occasions ID the events were stored with.
	 */
	private function log_user_logger_events() {
		$logger = Simple_History::get_instance()->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

		for ( $i = 0; $i < 3; $i++ ) {
			$logger->notice( 'Occasions capability test ' . $i, [ '_occasionsID' => 'occasions_capability_test' ] );
		}

		return md5(
			json_encode(
				[
					'_occasionsID' => 'occasions_capability_test',
					'_loggerSlug'  => 'SimpleUserLogger',
				]
			)
		);
	}

	private function query_occasions( $occasions_id, $extra_args = [] ) {
		return ( new Log_Query() )->query(
			array_merge(
				[
					'type'           => 'occasions',
					'logRowID'       => PHP_INT_MAX,
					'occasionsID'    => $occasions_id,
					'occasionsCount' => 1000,
				],
				$extra_args
			)
		);
	}

	public function test_editor_gets_no_occasions_from_admin_only_logger() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		$occasions_id = $this->log_user_logger_events();

		$this->assertCount( 3, $this->query_occasions( $occasions_id )['log_rows'], 'Administrator sees the occasions.' );

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'editor' ] ) );

		$this->assertCount( 0, $this->query_occasions( $occasions_id )['log_rows'], 'Editor must not see occasions from the User logger.' );
	}

	public function test_ignore_logger_capabilities_still_returns_occasions() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		$occasions_id = $this->log_user_logger_events();

		wp_set_current_user( 0 );

		$result = $this->query_occasions( $occasions_id, [ 'ignore_logger_capabilities' => true ] );

		$this->assertCount( 3, $result['log_rows'] );
	}

	public function test_missing_occasions_count_does_not_give_sql_error() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		$occasions_id = $this->log_user_logger_events();

		$result = ( new Log_Query() )->query(
			[
				'type'        => 'occasions',
				'logRowID'    => PHP_INT_MAX,
				'occasionsID' => $occasions_id,
			]
		);

		$this->assertIsArray( $result );
		$this->assertCount( 0, $result['log_rows'] );
	}

	public function test_occasions_id_with_quote_is_treated_as_a_value() {
		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );
		$this->log_user_logger_events();

		$result = $this->query_occasions( "x' OR '1'='1" );

		$this->assertIsArray( $result );
		$this->assertCount( 0, $result['log_rows'] );
	}
}
