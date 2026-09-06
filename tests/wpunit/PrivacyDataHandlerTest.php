<?php

use Simple_History\Simple_History;
use Simple_History\Services\Privacy_Data_Handler;

/**
 * Tests for the WordPress privacy export/erasure integration.
 */
class PrivacyDataHandlerTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * The service must be registered and loaded by Simple History.
	 */
	public function test_service_is_loaded() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );

		$this->assertInstanceOf(
			Privacy_Data_Handler::class,
			$service,
			'Privacy_Data_Handler should be loaded as a core service.'
		);
	}

	/**
	 * Helper: log an event as a specific user with explicit PII context.
	 *
	 * @param int    $user_id Initiator user id.
	 * @param string $message Event message.
	 * @return int New event id.
	 */
	private function log_event_as_user( $user_id, $message ) {
		wp_set_current_user( $user_id );

		$logger = SimpleLogger()->info(
			$message,
			[
				'_server_remote_addr'    => '203.0.113.45',
				'server_http_user_agent' => 'Mozilla/5.0 (TestAgent)',
			]
		);

		return $logger->last_insert_id;
	}

	/**
	 * Returns initiated events for the matching user by email.
	 */
	public function test_get_user_event_rows_returns_users_events() {
		$email   = 'erika-' . uniqid() . '@example.com';
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => $email ] );
		$this->log_event_as_user( $user_id, 'Erika did a thing' );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'get_user_event_rows' );
		$method->setAccessible( true );

		$rows = $method->invoke( $service, $email, 1 );

		$this->assertNotEmpty( $rows, 'Should return the user\'s events.' );
		$this->assertSame( (string) $user_id, (string) $rows[0]->context['_user_id'] );
	}

	/**
	 * Unknown email yields no rows.
	 */
	public function test_get_user_event_rows_unknown_email_returns_empty() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'get_user_event_rows' );
		$method->setAccessible( true );

		$rows = $method->invoke( $service, 'nobody-' . uniqid() . '@example.com', 1 );

		$this->assertSame( [], $rows, 'Unknown email should return an empty array.' );
	}

	/**
	 * Repeated (occasion-grouped) events are each returned individually, so
	 * none are silently excluded from export/erasure.
	 */
	public function test_get_user_event_rows_returns_each_repeated_event() {
		$email   = 'repeat-' . uniqid() . '@example.com';
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => $email ] );
		wp_set_current_user( $user_id );

		// Three events that would normally collapse into one occasion group.
		for ( $i = 0; $i < 3; $i++ ) {
			SimpleLogger()->info( 'Repeated privacy event', [ '_occasionsID' => 'privacy_test_occasion' ] );
		}

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'get_user_event_rows' );
		$method->setAccessible( true );

		$rows = $method->invoke( $service, $email, 1 );

		$this->assertGreaterThanOrEqual( 3, count( $rows ), 'All three repeated events must be returned individually (ungrouped).' );
	}

	/**
	 * Registering the exporter adds our group to the exporters array.
	 */
	public function test_register_exporter_adds_group() {
		$service   = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$exporters = $service->register_exporter( [] );

		$this->assertArrayHasKey( 'simple-history', $exporters );
		$this->assertIsCallable( $exporters['simple-history']['callback'] );
		$this->assertSame(
			'Simple History activity log',
			$exporters['simple-history']['exporter_friendly_name']
		);
	}

	/**
	 * Export returns the user's events with the expected fields and a done flag.
	 */
	public function test_export_user_data_returns_events() {
		$email   = 'export-' . uniqid() . '@example.com';
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => $email ] );
		$this->log_event_as_user( $user_id, 'Exportable event' );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( $email, 1 );

		$this->assertTrue( $result['done'] );
		$this->assertNotEmpty( $result['data'] );

		$first = $result['data'][0];
		$this->assertSame( 'simple-history', $first['group_id'] );
		$this->assertStringStartsWith( 'sh-event-', $first['item_id'] );

		$field_names = wp_list_pluck( $first['data'], 'name' );
		$this->assertContains( 'Date', $field_names );
		$this->assertContains( 'IP address', $field_names );
		$this->assertContains( 'User agent', $field_names );
		$this->assertContains( 'Logger', $field_names );
		$this->assertContains( 'Level', $field_names );
		$this->assertContains( 'Message', $field_names );
		$this->assertSame( 'Simple History activity log', $first['group_label'] );
	}

	/**
	 * Export for an unknown email is empty and done.
	 */
	public function test_export_user_data_unknown_email_is_done() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( 'nobody-' . uniqid() . '@example.com', 1 );

		$this->assertSame( [], $result['data'] );
		$this->assertTrue( $result['done'] );
	}

	/**
	 * Helper: read an event's context as an associative array straight from the DB.
	 *
	 * @param int $history_id Event id.
	 * @return array<string,string>
	 */
	private function read_context( $history_id ) {
		global $wpdb;
		$table = Simple_History::get_instance()->get_contexts_table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT `key`, value FROM {$table} WHERE history_id = %d", $history_id ),
			ARRAY_A
		);

		$out = [];
		foreach ( $rows as $r ) {
			$out[ $r['key'] ] = $r['value'];
		}
		return $out;
	}

	/**
	 * Scrubbing removes/masks PII keys but keeps the event and non-PII context.
	 */
	public function test_anonymize_event_scrubs_pii_keeps_rest() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => 'scrub-' . uniqid() . '@example.com' ] );
		wp_set_current_user( $user_id );

		$logger = SimpleLogger()->info(
			'Scrub me',
			[
				'_server_remote_addr'            => '203.0.113.45',
				'_server_http_x_forwarded_for_0' => '198.51.100.7',
				'server_http_user_agent'         => 'Mozilla/5.0 (TestAgent)',
				'_server_http_referer'           => 'https://example.com/secret?token=abc',
				'object_subtype'                 => 'post',
			]
		);
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'anonymize_event' );
		$method->setAccessible( true );
		$method->invoke( $service, $event_id );

		$context = $this->read_context( $event_id );

		// Identity removed / zeroed.
		$this->assertSame( '0', $context['_user_id'] );
		$this->assertArrayNotHasKey( '_user_login', $context );
		$this->assertArrayNotHasKey( '_user_email', $context );
		$this->assertArrayNotHasKey( '_user_role', $context );
		$this->assertArrayNotHasKey( 'server_http_user_agent', $context );
		$this->assertArrayNotHasKey( '_server_http_referer', $context );

		// All IP keys fully anonymized.
		$this->assertSame( '0.0.0.x', $context['_server_remote_addr'] );
		$this->assertSame( '0.0.0.x', $context['_server_http_x_forwarded_for_0'] );

		// Non-PII context preserved.
		$this->assertSame( 'post', $context['object_subtype'] );

		// Event row itself still exists.
		global $wpdb;
		$events_table = Simple_History::get_instance()->get_events_table_name();
		$still_there  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE id = %d", $event_id ) );
		$this->assertSame( '1', (string) $still_there, 'Event row must NOT be deleted.' );
	}

	/**
	 * Running the scrub twice is a stable no-op.
	 */
	public function test_anonymize_event_is_idempotent() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => 'idem-' . uniqid() . '@example.com' ] );
		wp_set_current_user( $user_id );

		$logger   = SimpleLogger()->info( 'Idempotent', [ '_server_remote_addr' => '203.0.113.45' ] );
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'anonymize_event' );
		$method->setAccessible( true );

		$method->invoke( $service, $event_id );
		$method->invoke( $service, $event_id );

		$context = $this->read_context( $event_id );
		$this->assertSame( '0', $context['_user_id'] );
		$this->assertSame( '0.0.0.x', $context['_server_remote_addr'] );
		$this->assertArrayNotHasKey( '_user_login', $context );
		$this->assertArrayNotHasKey( '_user_email', $context );
	}

	/**
	 * Registering the eraser adds our group with a callable.
	 */
	public function test_register_eraser_adds_group() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$erasers = $service->register_eraser( [] );

		$this->assertArrayHasKey( 'simple-history', $erasers );
		$this->assertIsCallable( $erasers['simple-history']['callback'] );
		$this->assertSame(
			'Simple History activity log',
			$erasers['simple-history']['eraser_friendly_name']
		);
	}

	/**
	 * Erasing scrubs the user's events and reports the WP-shaped result.
	 */
	public function test_erase_user_data_scrubs_and_reports() {
		$email   = 'erase-' . uniqid() . '@example.com';
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => $email ] );
		wp_set_current_user( $user_id );
		$logger   = SimpleLogger()->info( 'Erase me', [ '_server_remote_addr' => '203.0.113.45' ] );
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->erase_user_data( $email, 1 );

		$this->assertTrue( $result['items_removed'] );
		$this->assertTrue( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
		$this->assertNotEmpty( $result['messages'] );

		$context = $this->read_context( $event_id );
		$this->assertSame( '0.0.0.x', $context['_server_remote_addr'] );
		$this->assertSame( '0', $context['_user_id'] );
	}

	/**
	 * The settings-page service is loaded.
	 */
	public function test_privacy_settings_page_service_is_loaded() {
		$service = Simple_History::get_instance()->get_service( \Simple_History\Services\Privacy_Settings_Page::class );

		$this->assertInstanceOf(
			\Simple_History\Services\Privacy_Settings_Page::class,
			$service,
			'Privacy_Settings_Page should be loaded as a core service.'
		);
	}

	/**
	 * The Compliance section output always mentions the export tool, and only
	 * mentions erasure when experimental features are enabled.
	 */
	public function test_compliance_section_text_is_conditional() {
		$service = Simple_History::get_instance()->get_service( \Simple_History\Services\Privacy_Settings_Page::class );

		// Experimental OFF — export mentioned, erasure not.
		add_filter( 'simple_history/experimental_features_enabled', '__return_false', 99 );
		ob_start();
		$service->render_compliance_section();
		$off = ob_get_clean();
		remove_filter( 'simple_history/experimental_features_enabled', '__return_false', 99 );

		$this->assertStringContainsString( 'export', strtolower( $off ) );
		$this->assertStringNotContainsString( 'erasure', strtolower( $off ) );

		// Experimental ON — erasure also mentioned.
		add_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );
		ob_start();
		$service->render_compliance_section();
		$on = ob_get_clean();
		remove_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );

		$this->assertStringContainsString( 'erasure', strtolower( $on ) );
		$this->assertStringContainsString( 'export', strtolower( $on ) );
	}

	/**
	 * Erasing for an unknown email is a clean, empty, done result.
	 */
	public function test_erase_user_data_unknown_email_is_done() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->erase_user_data( 'nobody-' . uniqid() . '@example.com', 1 );

		$this->assertFalse( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertSame( array(), $result['messages'] );
		$this->assertTrue( $result['done'] );
	}

	/**
	 * Helper: log an event as actor $actor_id with arbitrary context (e.g. a
	 * "user edited" event targeting another user).
	 *
	 * @param int    $actor_id Initiator user id.
	 * @param string $message  Event message.
	 * @param array  $context  Extra context (subject keys, etc.).
	 * @return int Event id.
	 */
	private function log_event_as_actor( $actor_id, $message, $context ) {
		wp_set_current_user( $actor_id );
		$logger = SimpleLogger()->info( $message, $context );
		return $logger->last_insert_id;
	}

	/**
	 * Events where the requester is the SUBJECT (not the actor) are included,
	 * grouped separately, with the actor's identity/IP/user-agent removed.
	 */
	public function test_export_includes_subject_events_redacted() {
		$this->enable_experimental_features();

		$actor_login = 'actor-' . uniqid();
		$actor_id    = $this->factory->user->create( array( 'role' => 'administrator', 'user_login' => $actor_login, 'user_email' => $actor_login . '@example.com' ) );

		$subject_login = 'subject-' . uniqid();
		$subject_email = $subject_login . '@example.com';
		$subject_id    = $this->factory->user->create( array( 'role' => 'subscriber', 'user_login' => $subject_login, 'user_email' => $subject_email ) );

		// Actor edits the subject's profile. Initiator context (_user_id etc.) is
		// auto-added for the actor; subject keys identify the target.
		$event_id = $this->log_event_as_actor(
			$actor_id,
			'Edited the profile for user ' . $subject_login,
			array(
				'edited_user_id'    => $subject_id,
				'edited_user_login' => $subject_login,
				'edited_user_email' => $subject_email,
			)
		);

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( $subject_email, 1 );

		// The subject event is present.
		$ids = wp_list_pluck( $result['data'], 'item_id' );
		$this->assertContains( 'sh-event-' . $event_id, $ids, 'Subject event must be in the export.' );

		// Find the subject item and verify redaction.
		$subject_item = null;
		foreach ( $result['data'] as $item ) {
			if ( $item['item_id'] === 'sh-event-' . $event_id ) {
				$subject_item = $item;
			}
		}
		$this->assertNotNull( $subject_item );

		// It is in a separate "concerning you" group.
		$this->assertSame( 'simple-history-subject', $subject_item['group_id'] );

		// No IP / User agent field for subject events (those belong to the actor).
		$field_names = wp_list_pluck( $subject_item['data'], 'name' );
		$this->assertNotContains( 'IP address', $field_names );
		$this->assertNotContains( 'User agent', $field_names );

		// The actor's login/email must NOT appear anywhere in the subject item.
		$blob = wp_json_encode( $subject_item );
		$this->assertStringNotContainsString( $actor_login, $blob, 'Actor login must be redacted from subject event.' );
		$this->assertStringNotContainsString( $actor_login . '@example.com', $blob, 'Actor email must be redacted.' );

		// The subject's own login may appear (it is their data).
	}

	/**
	 * With experimental features OFF (the default), the export is initiator-only:
	 * the requester's own events are exported, but events ABOUT them performed by
	 * others (subject events) are excluded. The subject-export path — and its
	 * third-party redaction surface — is gated behind experimental features.
	 */
	public function test_subject_events_excluded_from_export_when_experimental_off() {
		// Do NOT enable experimental features — assert the default-off behavior.
		$actor_id = $this->factory->user->create( array( 'role' => 'administrator', 'user_email' => 'actoroff-' . uniqid() . '@example.com' ) );

		$subject_login = 'subjoff-' . uniqid();
		$subject_email = $subject_login . '@example.com';
		$subject_id    = $this->factory->user->create( array( 'role' => 'subscriber', 'user_login' => $subject_login, 'user_email' => $subject_email ) );

		// An event the subject performed themselves (initiator).
		$own_event_id = $this->log_event_as_user( $subject_id, 'Subject did their own thing' );

		// An event the actor performed ON the subject (subject event).
		$subject_event_id = $this->log_event_as_actor(
			$actor_id,
			'Edited the profile for user ' . $subject_login,
			array(
				'edited_user_id'    => $subject_id,
				'edited_user_login' => $subject_login,
				'edited_user_email' => $subject_email,
			)
		);

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( $subject_email, 1 );

		$ids = wp_list_pluck( $result['data'], 'item_id' );

		$this->assertContains( 'sh-event-' . $own_event_id, $ids, 'Initiator events must always export.' );
		$this->assertNotContains( 'sh-event-' . $subject_event_id, $ids, 'Subject events must be excluded when experimental features are off.' );
	}

	/**
	 * An event the requester both initiated and is subject of appears once,
	 * as an initiator event (full detail).
	 */
	public function test_export_does_not_double_count_initiator_subject_event() {
		$login = 'self-' . uniqid();
		$email = $login . '@example.com';
		$uid   = $this->factory->user->create( array( 'role' => 'administrator', 'user_login' => $login, 'user_email' => $email ) );

		// User edits their own profile: they are both initiator and subject.
		$event_id = $this->log_event_as_actor(
			$uid,
			'Edited own profile',
			array( 'edited_user_id' => $uid, 'edited_user_login' => $login, 'edited_user_email' => $email )
		);

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( $email, 1 );

		$matches = array_filter( $result['data'], function ( $i ) use ( $event_id ) {
			return $i['item_id'] === 'sh-event-' . $event_id;
		} );
		$this->assertCount( 1, $matches, 'Event must appear exactly once.' );
		$item = array_values( $matches )[0];
		$this->assertSame( 'simple-history', $item['group_id'], 'Self-initiated event is an initiator event.' );
	}

	/**
	 * Failed-login events targeting the user (by username) are included as subject events.
	 */
	public function test_export_includes_failed_login_subject_event() {
		$this->enable_experimental_features();

		$login = 'victim-' . uniqid();
		$email = $login . '@example.com';
		$uid   = $this->factory->user->create( array( 'role' => 'subscriber', 'user_login' => $login, 'user_email' => $email ) );

		// A failed login attempt against this username (no current user / anonymous).
		wp_set_current_user( 0 );
		$logger   = SimpleLogger()->warning( 'Failed to login with username "' . $login . '"', array( 'login' => $login, 'failed_username' => $login ) );
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( $email, 1 );

		$ids = wp_list_pluck( $result['data'], 'item_id' );
		$this->assertContains( 'sh-event-' . $event_id, $ids, 'Failed-login subject event must be included.' );
	}

	/**
	 * A third party named in a subject-event message is redacted (in raw and
	 * HTML-entity-encoded forms), while the requester's own identifier remains.
	 */
	public function test_subject_message_redacts_third_party_identity() {
		$this->enable_experimental_features();

		$req_login = 'reqto-' . uniqid();
		$req_email = $req_login . '@example.com';
		$this->factory->user->create( array( 'role' => 'subscriber', 'user_login' => $req_login, 'user_email' => $req_email ) );

		// A third party whose identifier contains an HTML-special char (&).
		$third_party = 'a&b-' . uniqid();

		// Anonymous actor; message interpolates both the requester (to) and the
		// third party (from). `user_login_to` / `user_login_from` are subject keys.
		wp_set_current_user( 0 );
		$logger = SimpleLogger()->info(
			'Switched to user "{user_login_to}" from "{user_login_from}"',
			array(
				'user_login_to'   => $req_login,
				'user_login_from' => $third_party,
			)
		);
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( $req_email, 1 );

		$item = null;
		foreach ( $result['data'] as $i ) {
			if ( $i['item_id'] === 'sh-event-' . $event_id ) {
				$item = $i;
			}
		}
		$this->assertNotNull( $item, 'Subject event must be exported.' );

		// Find the message field value.
		$message = '';
		foreach ( $item['data'] as $field ) {
			if ( __( 'Action concerning you', 'simple-history' ) === $field['name'] ) {
				$message = $field['value'];
			}
		}

		// Third party redacted (raw AND entity-encoded forms absent).
		$this->assertStringNotContainsString( $third_party, $message, 'Third-party identifier must be redacted (raw).' );
		$this->assertStringNotContainsString( 'a&amp;b', $message, 'Third-party identifier must not survive entity-encoded.' );
		$this->assertStringContainsString( '[redacted]', $message, 'Redaction marker should be present.' );

		// Requester's own login preserved (it is their data).
		$this->assertStringContainsString( $req_login, $message, 'Requester own identifier must remain.' );
	}

	/**
	 * A third party's DISPLAY NAME interpolated into a subject-event message is
	 * redacted — not just their login/email. (Display names are PII and were
	 * previously leaked because redaction only covered login/email keys.)
	 */
	public function test_subject_message_redacts_third_party_display_name() {
		$this->enable_experimental_features();

		$req_login = 'reqdn-' . uniqid();
		$req_email = $req_login . '@example.com';
		$this->factory->user->create( array( 'role' => 'subscriber', 'user_login' => $req_login, 'user_email' => $req_email ) );

		$third_party_name = 'Jane ThirdParty ' . uniqid();

		// Anonymous actor. Requester is the subject (matched via edited_user_login);
		// a third party's display name is interpolated into the message.
		wp_set_current_user( 0 );
		$logger   = SimpleLogger()->info(
			'Profile of "{edited_user_login}" was changed by "{user_display_name}"',
			array(
				'edited_user_login' => $req_login,
				'user_display_name' => $third_party_name,
			)
		);
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( $req_email, 1 );

		$message = $this->find_subject_message( $result, $event_id );
		$this->assertNotNull( $message, 'Subject event must be exported.' );

		$this->assertStringNotContainsString( $third_party_name, $message, 'Third-party display name must be redacted.' );
		$this->assertStringContainsString( '[redacted]', $message, 'Redaction marker should be present.' );
		$this->assertStringContainsString( $req_login, $message, 'Requester own identifier must remain.' );
	}

	/**
	 * Redaction must not corrupt unrelated text that merely contains a short
	 * third-party login as a substring (the old blind str_ireplace did).
	 */
	public function test_subject_message_does_not_corrupt_unrelated_text() {
		$this->enable_experimental_features();

		$req_login = 'reqsub-' . uniqid();
		$req_email = $req_login . '@example.com';
		$this->factory->user->create( array( 'role' => 'subscriber', 'user_login' => $req_login, 'user_email' => $req_email ) );

		// Short, common third-party login that is a substring of "latest".
		wp_set_current_user( 0 );
		$logger   = SimpleLogger()->info(
			'Switched to "{user_login_to}" from "{user_login_from}" in the latest session',
			array(
				'user_login_to'   => $req_login,
				'user_login_from' => 'test',
			)
		);
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( $req_email, 1 );

		$message = $this->find_subject_message( $result, $event_id );
		$this->assertNotNull( $message, 'Subject event must be exported.' );

		// The third-party placeholder is redacted...
		$this->assertStringContainsString( '[redacted]', $message );
		// ...but the unrelated word "latest" (which contains "test") is intact.
		$this->assertStringContainsString( 'latest session', $message, 'Unrelated text must not be corrupted by substring redaction.' );
	}

	/**
	 * Helper: locate the "Action concerning you" message for a subject event.
	 *
	 * @param array $result   Export result.
	 * @param int   $event_id Event id.
	 * @return string|null
	 */
	private function find_subject_message( $result, $event_id ) {
		foreach ( $result['data'] as $item ) {
			if ( $item['item_id'] !== 'sh-event-' . $event_id ) {
				continue;
			}

			foreach ( $item['data'] as $field ) {
				if ( __( 'Action concerning you', 'simple-history' ) === $field['name'] ) {
					return $field['value'];
				}
			}
		}

		return null;
	}

	/**
	 * Helper: id of the most recent event with the given exact message.
	 *
	 * @param string $message Event message.
	 * @return int Event id, or 0.
	 */
	private function get_latest_event_id_by_message( $message ) {
		global $wpdb;
		$events = Simple_History::get_instance()->get_events_table_name();

		$id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$events} WHERE message = %s ORDER BY id DESC LIMIT 1", $message )
		);

		return $id ? (int) $id : 0;
	}

	/**
	 * Helper: initiator value stored for an event.
	 *
	 * @param int $event_id Event id.
	 * @return string
	 */
	private function get_event_initiator( $event_id ) {
		global $wpdb;
		$events = Simple_History::get_instance()->get_events_table_name();

		return (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT initiator FROM {$events} WHERE id = %d", $event_id )
		);
	}

	/**
	 * When an admin runs an erasure, the summary event is attributed to that
	 * admin (initiator wp_user + their _user_id).
	 */
	public function test_erasure_summary_attributed_to_running_admin() {
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator', 'user_email' => 'admin-' . uniqid() . '@example.com' ) );

		$subject_email = 'subj-' . uniqid() . '@example.com';
		$subject_id    = $this->factory->user->create( array( 'role' => 'subscriber', 'user_email' => $subject_email ) );
		$this->log_event_as_user( $subject_id, 'Subject event to erase' );

		// The admin (not the subject) runs the erasure.
		wp_set_current_user( $admin_id );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$service->erase_user_data( $subject_email, 1 );

		$summary_id = $this->get_latest_event_id_by_message( 'Anonymized personal data in Simple History for a privacy erasure request' );
		$this->assertGreaterThan( 0, $summary_id, 'Summary event must be logged.' );

		$this->assertSame( 'wp_user', $this->get_event_initiator( $summary_id ) );

		$context = $this->read_context( $summary_id );
		$this->assertSame( (string) $admin_id, (string) ( $context['_user_id'] ?? '' ), 'Summary must be attributed to the admin who ran the erasure.' );
	}

	/**
	 * When an erasure runs with no current user (wp-cron/async), the summary
	 * event is attributed to WordPress — not a phantom wp_user with no id.
	 */
	public function test_erasure_summary_in_no_user_context_is_attributed_to_wordpress() {
		$subject_email = 'cron-' . uniqid() . '@example.com';
		$subject_id    = $this->factory->user->create( array( 'role' => 'subscriber', 'user_email' => $subject_email ) );
		$this->log_event_as_user( $subject_id, 'Subject event to erase in cron' );

		// No current user — simulates wp-cron processing.
		wp_set_current_user( 0 );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$service->erase_user_data( $subject_email, 1 );

		$summary_id = $this->get_latest_event_id_by_message( 'Anonymized personal data in Simple History for a privacy erasure request' );
		$this->assertGreaterThan( 0, $summary_id, 'Summary event must be logged.' );

		$this->assertSame( 'wp', $this->get_event_initiator( $summary_id ), 'No-user erasure must be attributed to WordPress, not a phantom user.' );

		$context = $this->read_context( $summary_id );
		$this->assertArrayNotHasKey( '_user_id', $context, 'No phantom _user_id should be stored.' );
	}

	/**
	 * Pagination: a page past the end of the result set is empty and done, and
	 * does not error (exercises the SQL offset path).
	 */
	public function test_export_pagination_past_end_is_empty_and_done() {
		$email   = 'page-' . uniqid() . '@example.com';
		$user_id = $this->factory->user->create( array( 'role' => 'administrator', 'user_email' => $email ) );
		$this->log_event_as_user( $user_id, 'Only event' );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );

		$page1 = $service->export_user_data( $email, 1 );
		$this->assertNotEmpty( $page1['data'] );
		$this->assertTrue( $page1['done'] );

		$page2 = $service->export_user_data( $email, 2 );
		$this->assertSame( array(), $page2['data'], 'Page past the end must be empty.' );
		$this->assertTrue( $page2['done'] );
	}

	/**
	 * Crossing the page-size boundary: page 1 returns a full page and reports
	 * not-done; page 2 returns the remainder and reports done. The two pages
	 * together cover every event exactly once.
	 */
	public function test_export_paginates_across_page_boundary() {
		$email   = 'bulk-' . uniqid() . '@example.com';
		$user_id = $this->factory->user->create( array( 'role' => 'administrator', 'user_email' => $email ) );
		wp_set_current_user( $user_id );

		// One more than a full page, so the result spans exactly two pages.
		$total = 101;
		for ( $i = 0; $i < $total; $i++ ) {
			SimpleLogger()->info( 'Bulk privacy event ' . $i );
		}

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );

		$page1 = $service->export_user_data( $email, 1 );
		$page2 = $service->export_user_data( $email, 2 );

		// Page 1 is a full page and reports more to come; page 2 is the last.
		$this->assertCount( 100, $page1['data'], 'Page 1 must return a full page of 100 events.' );
		$this->assertFalse( $page1['done'], 'Page 1 must not be the last page when more remain.' );
		$this->assertTrue( $page2['done'], 'Page 2 must be the last page.' );

		$ids1 = wp_list_pluck( $page1['data'], 'item_id' );
		$ids2 = wp_list_pluck( $page2['data'], 'item_id' );
		$all  = array_merge( $ids1, $ids2 );

		// At least our 101 logged events are covered (the account-creation event
		// counts as a subject event too, so the real total is a little higher).
		$this->assertGreaterThanOrEqual( $total, count( $all ), 'All logged events must be covered across the two pages.' );

		// No overlap between pages, and every event appears exactly once.
		$this->assertEmpty( array_intersect( $ids1, $ids2 ), 'Pages must not overlap.' );
		$this->assertCount( count( $all ), array_unique( $all ), 'Each event must appear exactly once across pages.' );

		// Page 2 holds exactly the events beyond the first full page.
		$this->assertCount( count( $all ) - 100, $ids2, 'Page 2 must hold exactly the remainder past page 1.' );
	}

	/**
	 * Remove any experimental-feature filters added by the gating tests so that
	 * a failing assertion cannot leak state to subsequent tests.
	 */
	public function tearDown(): void {
		remove_filter( 'simple_history/experimental_features_enabled', '__return_false', 99 );
		remove_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );

		// The subject-key guardrail tests register a filter and pollute the
		// service's memoized key cache. Clear both so they can't leak into other
		// tests (or into the next test's view of the production default keys).
		remove_all_filters( 'simple_history/privacy/subject_context_keys' );
		$this->reset_subject_key_cache();

		parent::tearDown();
	}

	/**
	 * Helper: null out the Privacy_Data_Handler's memoized subject-key cache so a
	 * freshly-added `simple_history/privacy/subject_context_keys` filter is seen.
	 * The cache is per-request and memoized on the singleton service, so without
	 * this reset a filter added mid-test would be ignored.
	 *
	 * @return void
	 */
	private function reset_subject_key_cache() {
		$service  = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$property = new ReflectionProperty( $service, 'subject_context_keys_cache' );
		$property->setAccessible( true );
		$property->setValue( $service, null );
	}

	/**
	 * Helper: turn experimental features ON for the current test. Subject-event
	 * export (activity about the user performed by others) is gated behind this
	 * flag — only initiator events export when it is off. The priority-99 filter
	 * is removed in tearDown.
	 *
	 * @return void
	 */
	private function enable_experimental_features() {
		add_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );
	}

	/**
	 * Export must be complete regardless of who (or what context) runs it —
	 * it must not be filtered by the current user's logger-read permissions.
	 */
	public function test_export_is_complete_without_current_user() {
		$email   = 'nocurrent-' . uniqid() . '@example.com';
		$user_id = $this->factory->user->create( array( 'role' => 'administrator', 'user_email' => $email ) );
		$this->log_event_as_user( $user_id, 'Event one' );
		$this->log_event_as_user( $user_id, 'Event two' );

		// Simulate a cron / no-admin context.
		wp_set_current_user( 0 );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( $email, 1 );

		$this->assertGreaterThanOrEqual( 2, count( $result['data'] ), 'Export must include the user events even with no current user set.' );
	}

	/**
	 * The eraser is NOT registered when experimental features are off.
	 */
	public function test_eraser_not_registered_when_experimental_off() {
		add_filter( 'simple_history/experimental_features_enabled', '__return_false', 99 );

		// Re-run loaded() to re-evaluate the gate with the filter applied.
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		remove_filter( 'wp_privacy_personal_data_erasers', [ $service, 'register_eraser' ] );
		$service->loaded();

		$this->assertFalse(
			has_filter( 'wp_privacy_personal_data_erasers', [ $service, 'register_eraser' ] ),
			'Eraser must not be registered when experimental features are off.'
		);

		remove_filter( 'simple_history/experimental_features_enabled', '__return_false', 99 );
	}

	/**
	 * The eraser IS registered when experimental features are on.
	 */
	public function test_eraser_registered_when_experimental_on() {
		add_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$service->loaded();

		$this->assertNotFalse(
			has_filter( 'wp_privacy_personal_data_erasers', [ $service, 'register_eraser' ] ),
			'Eraser must be registered when experimental features are on.'
		);

		remove_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );
		remove_filter( 'wp_privacy_personal_data_erasers', [ $service, 'register_eraser' ] );
	}

	/**
	 * GUARDRAIL (structural): every context key that can match a *third party's*
	 * event into a person's subject export by a person-identifying value — i.e.
	 * the `login` and `email` subject-key groups — MUST also be in the redaction
	 * key list. Otherwise an event pulled in via that key would render the third
	 * party's login/email into the "concerning you" message un-redacted.
	 *
	 * This reads the live, filtered key maps (the same the exporter uses), so a
	 * future logger or premium add-on that registers a new subject login/email key
	 * via `simple_history/privacy/subject_context_keys` is covered automatically —
	 * and this test fails the moment such a key is added without redaction support.
	 *
	 * The `id` group is intentionally excluded: numeric ids are used for match and
	 * for edit-link URLs (stripped by wp_strip_all_tags), not rendered as identity.
	 */
	public function test_all_subject_login_and_email_keys_are_redactable() {
		$this->reset_subject_key_cache();

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );

		$subject_method = new ReflectionMethod( $service, 'get_subject_context_keys' );
		$subject_method->setAccessible( true );
		$identity_method = new ReflectionMethod( $service, 'get_identity_context_keys' );
		$identity_method->setAccessible( true );

		$subject  = $subject_method->invoke( $service );
		$identity = $identity_method->invoke( $service );

		$match_identity_keys = array_merge( $subject['login'], $subject['email'] );
		$not_redactable      = array_values( array_diff( $match_identity_keys, $identity ) );

		$this->assertSame(
			array(),
			$not_redactable,
			'Every subject login/email match key must also be a redaction key, or the third party it names leaks. Missing from redaction: ' . implode( ', ', $not_redactable )
		);
	}

	/**
	 * GUARDRAIL (behavioral): a subject-match key registered *after the fact* via
	 * the `simple_history/privacy/subject_context_keys` filter — i.e. how a new
	 * logger or premium add-on extends matching — is redacted when it holds a
	 * third party, end to end through the real export path.
	 *
	 * This proves the redaction key list is genuinely derived from the (filtered)
	 * subject-key map, not a stale hardcoded copy: register a brand-new login key,
	 * have a third party occupy it, and assert it is scrubbed from the exported
	 * "concerning you" message while the requester's own identifier survives.
	 */
	public function test_subject_key_registered_via_filter_is_redacted() {
		$this->enable_experimental_features();

		$req_login = 'filtreq-' . uniqid();
		$req_email = $req_login . '@example.com';
		$this->factory->user->create( array( 'role' => 'subscriber', 'user_login' => $req_login, 'user_email' => $req_email ) );

		$third_party = 'tpfilter-' . uniqid();
		$custom_key  = 'sh_test_custom_subject_login';

		// Simulate a future logger / premium add-on registering a new subject key.
		add_filter(
			'simple_history/privacy/subject_context_keys',
			static function ( $keys ) use ( $custom_key ) {
				$keys['login'][] = $custom_key;
				return $keys;
			}
		);

		// The service memoizes the key map; clear it so the new filter is seen.
		$this->reset_subject_key_cache();

		// Requester is matched as subject via a stock key (edited_user_login); the
		// third party occupies the newly-registered key and is named in the message.
		wp_set_current_user( 0 );
		$logger   = SimpleLogger()->info(
			'Profile of "{edited_user_login}" touched by "{' . $custom_key . '}"',
			array(
				'edited_user_login' => $req_login,
				$custom_key         => $third_party,
			)
		);
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( $req_email, 1 );

		$ids = wp_list_pluck( $result['data'], 'item_id' );
		$this->assertContains( 'sh-event-' . $event_id, $ids, 'Subject event must be exported.' );

		$message = $this->find_subject_message( $result, $event_id );
		$this->assertNotNull( $message, 'Subject event must carry a "concerning you" message.' );

		$this->assertStringNotContainsString( $third_party, $message, 'A third party named via a filter-registered subject key must be redacted.' );
		$this->assertStringContainsString( '[redacted]', $message, 'Redaction marker should be present.' );
		$this->assertStringContainsString( $req_login, $message, 'Requester own identifier must remain.' );
	}
}
