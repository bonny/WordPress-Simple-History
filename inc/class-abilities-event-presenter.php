<?php

namespace Simple_History;

/**
 * Shapes a REST event response into the form an AI agent should see.
 *
 * A single event from the events REST API is about 1.6 KB, and most of that is
 * rendered markup built for the React admin UI. An agent cannot use the markup
 * but still pays for it in context, so a hundred-event answer would spend
 * roughly 40k tokens to convey about 10k tokens of facts.
 *
 * This class is deliberately free of WordPress dependencies so it can be tested
 * on any WordPress version, including ones without the Abilities API.
 */
class Abilities_Event_Presenter {
	/**
	 * Reduce a REST event to its agent-facing fields.
	 *
	 * @param array $event           One event as returned by WP_REST_Events_Controller.
	 * @param bool  $include_context Whether to include the context array.
	 * @return array
	 */
	public static function present( array $event, bool $include_context = false ): array {
		$presented = [
			'id'           => isset( $event['id'] ) ? (int) $event['id'] : null,
			'date_gmt'     => $event['date_gmt'] ?? '',
			'message'      => $event['message'] ?? '',
			'logger'       => $event['logger'] ?? '',
			'level'        => $event['loglevel'] ?? '',
			'initiator'    => $event['initiator'] ?? '',
			'user'         => self::present_user( $event ),
			'ip_addresses' => array_values( (array) ( $event['ip_addresses'] ?? [] ) ),
			'occasions'    => isset( $event['subsequent_occasions_count'] ) ? (int) $event['subsequent_occasions_count'] : 1,
			'permalink'    => $event['permalink'] ?? '',
		];

		if ( $include_context ) {
			$presented['context'] = (array) ( $event['context'] ?? [] );
		}

		return $presented;
	}

	/**
	 * Reduce initiator data to identity only.
	 *
	 * Email addresses, gravatar URLs, and profile links are dropped: they are
	 * PII or chrome, and neither helps an agent answer "who did this".
	 *
	 * WP_REST_Events_Controller::prepare_item_for_response() always emits a
	 * 7-key initiator_data array, falling back to null/empty values rather
	 * than omitting the key — so the is_array()/[] guard below only catches a
	 * shape the REST API never actually produces. It stays as defence for
	 * direct callers, but the real "no resolvable user" signal is that the
	 * identity fields themselves are all empty, e.g. a `wp`-initiated event
	 * or a failed login with an unrecognized username.
	 *
	 * @param array $event One event as returned by WP_REST_Events_Controller.
	 * @return array|null Null when the event has no resolvable user.
	 */
	private static function present_user( array $event ): ?array {
		$data = $event['initiator_data'] ?? null;

		if ( ! is_array( $data ) || $data === [] ) {
			return null;
		}

		$id    = isset( $data['user_id'] ) ? (int) $data['user_id'] : null;
		$login = $data['user_login'] ?? '';
		$name  = $data['user_display_name'] ?? '';

		if ( $id === null && $login === '' && $name === '' ) {
			return null;
		}

		return [
			'id'    => $id,
			'login' => $login,
			'name'  => $name,
		];
	}
}
