<?php

use Simple_History\Abilities_Event_Presenter;

/**
 * Shaping REST event data for AI agents.
 *
 * The events REST response is built for the React admin UI. An agent needs the
 * facts and none of the markup, and every dropped field is context budget the
 * agent gets to spend on actual events instead.
 *
 * @coversDefaultClass Simple_History\Abilities_Event_Presenter
 */
class AbilitiesEventPresenterTest extends \Codeception\TestCase\WPTestCase {
	/**
	 * A REST event response, field-for-field as the API returns one.
	 *
	 * @return array
	 */
	private function rest_event() {
		return array(
			'id'                         => '42',
			'date_gmt'                   => '2026-07-28 08:02:19',
			'date_local'                 => '2026-07-28 10:02:19',
			'message'                    => 'Updated post "Hello world"',
			'message_html'               => '<span>Updated post <a href="#">Hello world</a></span>',
			'message_key'                => 'post_updated',
			'message_uninterpolated'     => 'Updated post "{post_title}"',
			'logger'                     => 'SimplePostLogger',
			'loglevel'                   => 'info',
			'initiator'                  => 'wp_user',
			'initiator_data'             => array(
				'user_id'           => '4',
				'user_login'        => 'claude',
				'user_email'        => 'claude@example.com',
				'user_display_name' => 'Claude',
				'user_avatar_url'   => 'https://secure.gravatar.com/avatar/abc',
				'user_image'        => '',
				'user_profile_url'  => 'http://example.test/wp-admin/profile.php',
			),
			'ip_addresses'               => array( '_server_remote_addr' => '192.0.2.1' ),
			'occasions_id'               => 'abc123',
			'subsequent_occasions_count' => 3,
			'permalink'                  => 'http://example.test/wp-admin/admin.php?page=simple_history_admin_menu_page#item/42',
			'link'                       => 'http://example.test/wp-json/simple-history/v1/events/42',
			'context'                    => array( 'post_title' => 'Hello world' ),
			'details_html'               => '<table><tr><td>markup</td></tr></table>',
			'details_data'               => array( 'some' => 'structure' ),
			'action_links'               => array( array( 'label' => 'View' ) ),
			'reactions'                  => array( 'thumbsup' => 2 ),
			'sticky'                     => false,
			'sticky_appended'            => false,
			'via'                        => '',
			'backfilled'                 => false,
			'ai_origin'                  => null,
		);
	}

	/**
	 * @covers ::present
	 */
	public function test_keeps_only_the_agent_relevant_fields() {
		$presented = Abilities_Event_Presenter::present( $this->rest_event() );

		$this->assertSame(
			array( 'id', 'date_gmt', 'message', 'logger', 'level', 'initiator', 'user', 'ip_addresses', 'occasions', 'permalink' ),
			array_keys( $presented ),
			'Presenter should emit exactly the agent-facing fields, in a stable order.'
		);
	}

	/**
	 * Rendered markup is the bulk of the payload and useless to an agent.
	 *
	 * @covers ::present
	 */
	public function test_drops_ui_only_fields() {
		$presented = Abilities_Event_Presenter::present( $this->rest_event() );

		foreach ( array( 'message_html', 'details_html', 'details_data', 'action_links', 'reactions', 'sticky', 'sticky_appended', 'via', 'backfilled', 'link' ) as $dropped ) {
			$this->assertArrayNotHasKey( $dropped, $presented, "Field {$dropped} is UI chrome and should not reach an agent." );
		}
	}

	/**
	 * Context is the densest PII in an event, so list calls opt in rather than out.
	 *
	 * @covers ::present
	 */
	public function test_context_is_excluded_unless_requested() {
		$without = Abilities_Event_Presenter::present( $this->rest_event() );
		$this->assertArrayNotHasKey( 'context', $without );

		$with = Abilities_Event_Presenter::present( $this->rest_event(), true );
		$this->assertSame( array( 'post_title' => 'Hello world' ), $with['context'] );
	}

	/**
	 * Who acted is useful. Their email address and gravatar are not.
	 *
	 * @covers ::present
	 */
	public function test_user_is_reduced_to_identity_without_pii() {
		$presented = Abilities_Event_Presenter::present( $this->rest_event() );

		$this->assertSame(
			array(
				'id'    => 4,
				'login' => 'claude',
				'name'  => 'Claude',
			),
			$presented['user']
		);
	}

	/**
	 * ip_addresses arrives as a keyed map; an agent wants a plain list.
	 *
	 * @covers ::present
	 */
	public function test_ip_addresses_are_flattened_to_a_list() {
		$presented = Abilities_Event_Presenter::present( $this->rest_event() );

		$this->assertSame( array( '192.0.2.1' ), $presented['ip_addresses'] );
	}

	/**
	 * Numeric fields arrive as strings from the database layer.
	 *
	 * @covers ::present
	 */
	public function test_numeric_fields_are_cast() {
		$presented = Abilities_Event_Presenter::present( $this->rest_event() );

		$this->assertSame( 42, $presented['id'] );
		$this->assertSame( 3, $presented['occasions'] );
	}

	/**
	 * Events without a resolvable user (WP_CLI, cron, anonymous) must not fatal.
	 *
	 * @covers ::present
	 */
	public function test_missing_initiator_data_yields_null_user() {
		$event = $this->rest_event();
		unset( $event['initiator_data'] );

		$presented = Abilities_Event_Presenter::present( $event );

		$this->assertNull( $presented['user'] );
	}

	/**
	 * An event not initiated by a logged-in user must never carry one.
	 *
	 * The controller derives initiator_data from the context's _user_id, which
	 * the logger records whenever anyone is logged in during the request. So a
	 * failed login submitted while an administrator has a session open arrives
	 * carrying that administrator's identity, with initiator still web_user.
	 * Returning it would have an agent name the wrong person when asked who
	 * attempted a break-in.
	 *
	 * @covers ::present
	 */
	public function test_non_user_initiator_yields_null_user() {
		$event                   = $this->rest_event();
		$event['initiator']      = 'web_user';
		$event['message']        = 'Failed to login with username "intruder"';
		$event['initiator_data'] = array(
			'user_id'           => 4,
			'user_login'        => 'admin_with_a_tab_open',
			'user_email'        => 'admin@example.com',
			'user_display_name' => 'Admin',
			'user_avatar_url'   => '',
			'user_image'        => '',
			'user_profile_url'  => '',
		);

		$presented = Abilities_Event_Presenter::present( $event );

		$this->assertNull(
			$presented['user'],
			'A web_user event must not be attributed to whoever happened to be logged in.'
		);
	}

	/**
	 * The REST controller always emits a 7-key initiator_data array, falling
	 * back to null or empty-string values rather than omitting the key. This
	 * is the shape that reaches the presenter when nobody is logged in; the
	 * guard in test_missing_initiator_data_yields_null_user() above pins a
	 * shape the REST API never produces. Note that empty identity fields are
	 * not what makes the user null in practice — see
	 * test_non_user_initiator_yields_null_user() for the case that matters.
	 *
	 * @covers ::present
	 */
	public function test_all_keys_present_but_empty_yields_null_user() {
		$event                    = $this->rest_event();
		$event['initiator_data'] = array(
			'user_id'           => null,
			'user_login'        => '',
			'user_email'        => null,
			'user_display_name' => '',
			'user_avatar_url'   => 'https://secure.gravatar.com/avatar/anonymous',
			'user_image'        => '',
			'user_profile_url'  => '',
		);

		$presented = Abilities_Event_Presenter::present( $event );

		$this->assertNull( $presented['user'] );
	}

	/**
	 * Guards against a string initiator_data, which would otherwise reach
	 * isset( $data['user_id'] ) and be silently treated as "no id".
	 *
	 * @covers ::present
	 */
	public function test_string_initiator_data_yields_null_user() {
		$event                    = $this->rest_event();
		$event['initiator_data'] = 'not-an-array';

		$presented = Abilities_Event_Presenter::present( $event );

		$this->assertNull( $presented['user'] );
	}

	/**
	 * An object initiator_data must not fatal with "Cannot use object of type
	 * stdClass as array" when read with array syntax.
	 *
	 * @covers ::present
	 */
	public function test_object_initiator_data_yields_null_user() {
		$event                    = $this->rest_event();
		$event['initiator_data'] = new stdClass();

		$presented = Abilities_Event_Presenter::present( $event );

		$this->assertNull( $presented['user'] );
	}

	/**
	 * A scalar ip_addresses must still come out as an array, so a future
	 * change to the cast breaks visibly instead of silently.
	 *
	 * @covers ::present
	 */
	public function test_scalar_ip_addresses_is_cast_to_an_array() {
		$event                  = $this->rest_event();
		$event['ip_addresses'] = '192.0.2.1';

		$presented = Abilities_Event_Presenter::present( $event );

		$this->assertSame( array( '192.0.2.1' ), $presented['ip_addresses'] );
	}
}
