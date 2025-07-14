<?php

namespace Simple_History;

use Simple_History\Helpers;
use Simple_History\Simple_History;

/**
 * Event class for managing Simple History events.
 *
 * This class provides methods to load, manipulate, and retrieve information
 * about Simple History events. It handles both existing events loaded from
 * the database and new events that haven't been saved yet.
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
	 * Constructor for existing events.
	 *
	 * @param int|null $event_id Event ID. If null, creates an empty event instance.
	 */
	public function __construct( int $event_id = null ) {
		if ( empty( $event_id ) ) {
			return;
		}

		$this->id = $event_id;

		// Load data immediately to validate event exists.
		$this->load_data();
	}

	/**
	 * Create a new event instance.
	 * Untested so far - not used yet.
	 *
	 * @param array $event_data Event data for new event. Should include 'context' key for context data.
	 * @return Event New event instance.
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
	 * Example:
	 *
	 * ```php
	 * $event = Event::load( 123 );
	 * ```
	 *
	 * @param int $event_id Event ID to load.
	 * @return Event|null Event instance if exists and is valid, null otherwise.
	 */
	public static function load( int $event_id ): ?Event {
		$event = new Event( $event_id );
		return $event->exists() ? $event : null;
	}

	/**
	 * Get event ID.
	 *
	 * @return int|null Event ID if set, null for new events.
	 */
	public function get_id(): ?int {
		return $this->id;
	}

	/**
	 * Check if event exists.
	 *
	 * @return bool True if event has a valid ID, false otherwise.
	 */
	public function exists(): bool {
		return $this->id !== null;
	}

	/**
	 * Check if this is a new event (not yet saved).
	 *
	 * @return bool True if this is a new event, false if loaded from database.
	 */
	public function is_new(): bool {
		return $this->is_new;
	}

	/**
	 * Get event data.
	 *
	 * @return object|false Event data object on success, false if event doesn't exist or failed to load.
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Check if event is sticky.
	 *
	 * @return bool True if event has sticky context, false otherwise.
	 */
	public function is_sticky(): bool {
		$context = $this->get_context();
		return isset( $context['_sticky'] );
	}

	/**
	 * Make event sticky.
	 *
	 * @return bool True if sticky context was successfully added, false on database error.
	 */
	public function stick(): bool {
		global $wpdb;

		$simple_history = Simple_History::get_instance();
		$contexts_table = $simple_history->get_contexts_table_name();

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
			$this->reload_data();
		}

		return (bool) $result;
	}

	/**
	 * Remove sticky status from event.
	 *
	 * @return bool True if sticky context was successfully removed, false on database error.
	 */
	public function unstick(): bool {
		global $wpdb;

		$simple_history = Simple_History::get_instance();
		$contexts_table = $simple_history->get_contexts_table_name();

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
			$this->reload_data();
		}

		return (bool) $result;
	}

	/**
	 * Get event message.
	 *
	 * @return string Plain text message, empty string if event doesn't exist.
	 */
	public function get_message(): string {
		$data = $this->get_data();
		if ( ! $data ) {
			return '';
		}
		$simple_history = Simple_History::get_instance();
		$message = $simple_history->get_log_row_plain_text_output( $data );
		$message = html_entity_decode( $message );
		return wp_strip_all_tags( $message );
	}

	/**
	 * Get event message with HTML.
	 *
	 * @return string HTML formatted message, empty string if event doesn't exist.
	 */
	public function get_message_html(): string {
		$data = $this->get_data();
		if ( ! $data ) {
			return '';
		}
		$simple_history = Simple_History::get_instance();
		return $simple_history->get_log_row_html_output( $data, [] );
	}

	/**
	 * Get event details as HTML.
	 *
	 * @return string HTML formatted details, empty string if event doesn't exist.
	 */
	public function get_details_html(): string {
		$data = $this->get_data();
		if ( ! $data ) {
			return '';
		}
		$simple_history = Simple_History::get_instance();
		return $simple_history->get_log_row_details_output( $data )->to_html();
	}

	/**
	 * Get event details as JSON.
	 *
	 * @return object|false JSON object with event details on success, false if event doesn't exist.
	 */
	public function get_details_json() {
		$data = $this->get_data();
		if ( ! $data ) {
			return false;
		}
		$simple_history = Simple_History::get_instance();
		return $simple_history->get_log_row_details_output( $data )->to_json();
	}

	/**
	 * Get event context.
	 *
	 * @return array Context data as key-value pairs, empty array if no context.
	 */
	public function get_context(): array {
		return $this->context;
	}

	/**
	 * Get event logger.
	 *
	 * @return string Logger name, empty string if event doesn't exist.
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
	 * @return string Log level (e.g., 'info', 'warning', 'error'), empty string if event doesn't exist.
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
	 * @return string Initiator type (e.g., 'wp_user', 'wp_cli', 'other'), empty string if event doesn't exist.
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
	 * @return string Date in local timezone format, empty string if event doesn't exist.
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
	 * @return string Date in GMT format, empty string if event doesn't exist.
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
	 * @return string URL to the event in admin interface, empty string if event doesn't exist.
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
	 * Clear cached data and context.
	 */
	private function clear_data(): void {
		$this->data = null;
		$this->context = [];
	}

	/**
	 * Reload event data from database.
	 *
	 * Clears cached data and reloads from database.
	 */
	private function reload_data(): void {
		$this->clear_data();
		$this->load_data();
	}

	/**
	 * Load event data from database.
	 *
	 * Loads the main event data and associated context from the database.
	 * Sets $this->data to false if event doesn't exist.
	 *
	 * Uses WordPress object cache to avoid repeated database queries for the same event.
	 */
	private function load_data(): void {
		global $wpdb;

		// Create cache key based on event ID.
		$cache_key = md5( __METHOD__ . serialize( [ 'event_id' => $this->id ] ) );
		$cache_group = Helpers::get_cache_group();

		// Try to get cached data first.
		$cached_data = wp_cache_get( $cache_key, $cache_group );

		// Use cached data if it exists.
		if ( false !== $cached_data ) {
			$this->data = $cached_data['data'];
			$this->context = $cached_data['context'];
			return;
		}

		// No cached data, so load from database.
		$simple_history = Simple_History::get_instance();
		$table_name = $simple_history->get_events_table_name();
		$contexts_table = $simple_history->get_contexts_table_name();

		// Get main event data.
		$event_data = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$table_name,
				$this->id
			)
		);

		// No event found.
		if ( ! $event_data ) {
			$this->data = false;
			$this->context = [];

			// Cache the result even if event doesn't exist to avoid repeated DB queries.
			wp_cache_set(
				$cache_key,
				[
					'data' => false,
					'context' => [],
				],
				$cache_group
			);
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

		// Cache the result.
		wp_cache_set(
			$cache_key,
			[
				'data' => $this->data,
				'context' => $this->context,
			],
			$cache_group
		);
	}
}
