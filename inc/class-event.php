<?php

namespace Simple_History;

use stdClass;
use WP_Error;
use Simple_History\Helpers;
use Simple_History\Simple_History;

/**
 * Event class for managing Simple History events.
 */
class Event {
	/**
	 * Event ID.
	 *
	 * @var int|null
	 */
	private ?int $id = null;

	/**
	 * Event data.
	 *
	 * @var object|false|null
	 */
	private $data = null;

	/**
	 * Event context.
	 *
	 * @var array
	 */
	private array $context = [];

	/**
	 * Whether this is a new event (not yet saved).
	 *
	 * @var bool
	 */
	private bool $is_new = false;

	/**
	 * Simple History instance.
	 *
	 * @var Simple_History
	 */
	private Simple_History $simple_history;

	/**
	 * Constructor for existing events.
	 *
	 * @param int|null $event_id Event ID.
	 */
	public function __construct( int $event_id = null ) {
		if ( empty( $event_id ) ) {
			return;
		}

		$this->id = $event_id;
		$this->simple_history = Simple_History::get_instance();

		// Load data immediately to validate event exists.
		$this->load_data();
	}

	/**
	 * Create a new event instance.
	 * Untested so far - not used yet.
	 *
	 * @param array $event_data Event data for new event.
	 * @return Event
	 */
	public static function create( array $event_data = [] ): Event {
		$event = new Event();
		$event->is_new = true;
		$event->data = (object) $event_data;
		$event->context = $event_data['context'] ?? [];
		return $event;
	}

	/**
	 * Load an existing event with null safety.
	 *
	 * @param int $event_id Event ID.
	 * @return Event|null Event instance if exists, null otherwise.
	 */
	public static function load( int $event_id ): ?Event {
		$event = new Event( $event_id );
		return $event->exists() ? $event : null;
	}

	/**
	 * Get event ID.
	 *
	 * @return int|null
	 */
	public function get_id(): ?int {
		return $this->id;
	}

	/**
	 * Check if event exists.
	 *
	 * @return bool
	 */
	public function exists(): bool {
		return $this->id !== null;
	}

	/**
	 * Check if this is a new event (not yet saved).
	 *
	 * @return bool
	 */
	public function is_new(): bool {
		return $this->is_new;
	}

	/**
	 * Get event data.
	 *
	 * @return object|false Event data on success, false on failure.
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Check if event is sticky.
	 *
	 * @return bool
	 */
	public function is_sticky(): bool {
		$context = $this->get_context();
		return isset( $context['_sticky'] );
	}

	/**
	 * Make event sticky.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function stick(): bool {
		global $wpdb;

		$contexts_table = $this->simple_history->get_contexts_table_name();

		// First remove any existing sticky context.
		$wpdb->delete(
			$contexts_table,
			[
				'history_id' => $this->id,
				'key'        => '_sticky',
			],
			[ '%d', '%s' ]
		);

		// Add the sticky context.
		$result = $wpdb->insert(
			$contexts_table,
			[
				'history_id' => $this->id,
				'key'        => '_sticky',
				'value'      => '{}',
			],
			[ '%d', '%s', '%s' ]
		);

		if ( $result ) {
			// Reload data to reflect changes.
			$this->data = null;
			$this->context = [];
			$this->load_data();
		}

		return (bool) $result;
	}

	/**
	 * Remove sticky status from event.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function unstick(): bool {
		global $wpdb;

		$contexts_table = $this->simple_history->get_contexts_table_name();

		$result = $wpdb->delete(
			$contexts_table,
			[
				'history_id' => $this->id,
				'key'        => '_sticky',
			],
			[ '%d', '%s' ]
		);

		if ( $result ) {
			// Reload data to reflect changes.
			$this->data = null;
			$this->context = [];
			$this->load_data();
		}

		return (bool) $result;
	}

	/**
	 * Get event message.
	 *
	 * @return string
	 */
	public function get_message(): string {
		$data = $this->get_data();
		if ( ! $data ) {
			return '';
		}
		$message = $this->simple_history->get_log_row_plain_text_output( $data );
		$message = html_entity_decode( $message );
		return wp_strip_all_tags( $message );
	}

	/**
	 * Get event message with HTML.
	 *
	 * @return string
	 */
	public function get_message_html(): string {
		$data = $this->get_data();
		if ( ! $data ) {
			return '';
		}
		return $this->simple_history->get_log_row_html_output( $data, [] );
	}

	/**
	 * Get event details as HTML.
	 *
	 * @return string
	 */
	public function get_details_html(): string {
		$data = $this->get_data();
		if ( ! $data ) {
			return '';
		}
		return $this->simple_history->get_log_row_details_output( $data )->to_html();
	}

	/**
	 * Get event details as JSON.
	 *
	 * @return object|false Event details on success, false on failure.
	 */
	public function get_details_json() {
		$data = $this->get_data();
		if ( ! $data ) {
			return false;
		}
		return $this->simple_history->get_log_row_details_output( $data )->to_json();
	}

	/**
	 * Get event context.
	 *
	 * @return array
	 */
	public function get_context(): array {
		return $this->context;
	}

	/**
	 * Get event logger.
	 *
	 * @return string
	 */
	public function get_logger(): string {
		$data = $this->get_data();
		if ( ! $data ) {
			return '';
		}
		return $data->logger;
	}

	/**
	 * Get event log level.
	 *
	 * @return string
	 */
	public function get_log_level(): string {
		$data = $this->get_data();
		if ( ! $data ) {
			return '';
		}
		return $data->level;
	}

	/**
	 * Get event initiator.
	 *
	 * @return string
	 */
	public function get_initiator(): string {
		$data = $this->get_data();
		if ( ! $data ) {
			return '';
		}
		return $data->initiator;
	}

	/**
	 * Get event date in local timezone.
	 *
	 * @return string
	 */
	public function get_date_local(): string {
		$data = $this->get_data();
		if ( ! $data ) {
			return '';
		}
		return get_date_from_gmt( $data->date );
	}

	/**
	 * Get event date in GMT.
	 *
	 * @return string
	 */
	public function get_date_gmt(): string {
		$data = $this->get_data();
		if ( ! $data ) {
			return '';
		}
		return $data->date;
	}

	/**
	 * Get event permalink.
	 *
	 * @return string
	 */
	public function get_permalink(): string {
		if ( ! $this->exists() ) {
			return '';
		}
		return sprintf(
			'%s#simple-history/event/%d',
			Helpers::get_history_admin_url(),
			$this->id
		);
	}



	/**
	 * Load event data from database.
	 */
	private function load_data(): void {
		global $wpdb;

		$table_name = $this->simple_history->get_table_name();
		$contexts_table = $this->simple_history->get_contexts_table_name();

		// Get main event data.
		$event_data = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table_name,
				$this->id
			)
		);

		if ( ! $event_data ) {
			$this->data = false;
			return;
		}

		// Get context data.
		$context_data = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT `key`, value FROM %i WHERE history_id = %d',
				$contexts_table,
				$this->id
			)
		);

		// Build context array.
		$this->context = [];
		foreach ( $context_data as $context_row ) {
			$this->context[ $context_row->key ] = $context_row->value;
		}

		// Add context to event data.
		$event_data->context = $this->context;

		// Move up _message_key from context row to main row as context_message_key.
		// This is because that's the way it was before SQL was rewritten
		// to support FULL_GROUP_BY in December 2023.
		$event_data->context_message_key = null;
		if ( isset( $this->context['_message_key'] ) ) {
			$event_data->context_message_key = $this->context['_message_key'];
		}

		$this->data = $event_data;
	}
}
