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
	 * Event data object.
	 *
	 * Object containing event data loaded from the database, or null if not loaded or not found.
	 *
	 * @var object{
	 *     id: int,
	 *     date: string,
	 *     logger: string,
	 *     level: string,
	 *     message: string,
	 *     occasionsID: string,
	 *     initiator: string,
	 *     repeatCount: int,
	 *     subsequentOccasions: int,
	 *     maxId: int,
	 *     minId: int,
	 *     context_message_key: mixed
	 * }|null
	 */
	private $data = null;

	/**
	 * Event context.
	 *
	 * Array of context data, where each key is a string and each value can be of any type (mixed).
	 * Null if event is not loaded or not found.
	 *
	 * @var array{string: mixed}|null
	 */
	private ?array $context = null;

	/**
	 * Whether this is a new event (not yet saved).
	 *
	 * @var bool
	 */
	private bool $is_new = false;

	/**
	 * Load status.
	 *
	 * @var string 'NOT_LOADED', 'LOADED_FROM_CACHE', 'LOADED_FROM_DB', 'NOT_FOUND'
	 */
	private string $load_status = 'NOT_LOADED';

	/**
	 * Constructor for existing events.
	 *
	 * @param int|null $event_id Event ID. If null, creates an empty event instance.
	 */
	public function __construct( ?int $event_id = null ) {
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
		$event          = new Event();
		$event->is_new  = true;
		$event->data    = (object) $event_data;
		$event->context = $event_data['context'] ?? [];
		return $event;
	}

	/**
	 * Get an existing event with null safety.
	 *
	 * Example:
	 *
	 * ```php
	 * $event = Event::get( 123 );
	 * ```
	 *
	 * @param int $event_id Event ID to get.
	 * @return Event|null Event instance if exists and is valid, null otherwise.
	 */
	public static function get( int $event_id ): ?Event {
		$event        = new Event();
		$event->id    = $event_id;
		$event_exists = $event->load_data();

		if ( ! $event_exists ) {
			return null;
		}

		return $event;
	}

	/**
	 * Get multiple existing events efficiently using a single query.
	 *
	 * Example:
	 *
	 * ```php
	 * $events = Event::get_many( [123, 456, 789] );
	 * ```
	 *
	 * @param array $event_ids Array of event IDs to get.
	 * @return array Array of Event objects, indexed by event ID. Missing events are not included.
	 */
	public static function get_many( array $event_ids ): array {
		// Convert to ints, remove duplicates, remove empty values, then check if empty.
		$event_ids = array_map( 'intval', $event_ids );
		$event_ids = array_unique( $event_ids );
		$event_ids = array_filter( $event_ids );

		if ( empty( $event_ids ) ) {
			return [];
		}

		// Create cache key based on event IDs.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$cache_key   = md5( __METHOD__ . serialize( [ 'event_ids' => $event_ids ] ) );
		$cache_group = Helpers::get_cache_group();

		// Try to get cached data first.
		$cached_data = wp_cache_get( $cache_key, $cache_group );

		// Use cached data if it exists.
		if ( false !== $cached_data ) {
			$events = [];
			foreach ( $cached_data as $event_id => $event_data ) {
				$events[ $event_id ] = self::from_object( $event_data );
			}
			return $events;
		}

		// No cached data, so load from database using the shared query method.
		$events_data = self::query_db_for_events( $event_ids );

		if ( empty( $events_data ) ) {
			// Cache empty result to avoid repeated DB queries.
			wp_cache_set( $cache_key, [], $cache_group );
			return [];
		}

		// Create Event objects using from_object().
		$events = [];
		foreach ( $events_data as $event_id => $event_data ) {
			$events[ $event_id ] = self::from_object( $event_data, 'LOADED_FROM_DB' );
		}

		// Cache the results.
		wp_cache_set( $cache_key, $events_data, $cache_group );

		return $events;
	}

	/**
	 * Create an Event object from Log_Query result object.
	 *
	 * Useful for creating Event objects from Log_Query results.
	 *
	 * @param object $event_data Log_Query result object with context as a property.
	 * @param string $load_status Optional load status. Defaults to 'LOADED_FROM_CACHE'.
	 * @return Event Event instance.
	 */
	public static function from_object( object $event_data, string $load_status = 'LOADED_FROM_CACHE' ): Event {
		$event              = new Event();
		$event->id          = $event_data->id ?? null;
		$event->data        = $event_data;
		$event->context     = $event_data->context ?? [];
		$event->load_status = $load_status;
		return $event;
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
	 * @return bool True if event has a valid ID and exists in database, false otherwise.
	 */
	public function exists(): bool {
		// If no ID is set, event doesn't exist.
		if ( $this->id === null ) {
			return false;
		}

		// Event exists if it was found in database (not NOT_FOUND).
		return $this->load_status !== 'NOT_FOUND';
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
	 * Get the current load status of the event.
	 *
	 * @return string Current load status: 'NOT_LOADED', 'LOADED_FROM_CACHE', 'LOADED_FROM_DB', 'NOT_FOUND'
	 */
	public function get_load_status(): string {
		return $this->load_status;
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
		return isset( $this->context['_sticky'] );
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
		$message        = $simple_history->get_log_row_plain_text_output( $data );
		$message        = html_entity_decode( $message );
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
		$this->data        = null;
		$this->context     = null;
		$this->load_status = 'NOT_LOADED';
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
	 * Loads the main event data and associated context from the database using a single JOIN query.
	 * Sets $this->data to false if event doesn't exist.
	 *
	 * Uses WordPress object cache to avoid repeated database queries for the same event.
	 *
	 * @return bool True if event exists and data was loaded, false if event does not exist.
	 */
	private function load_data(): bool {
		// Create cache key based on event ID.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$cache_key   = md5( __METHOD__ . serialize( [ 'event_id' => $this->id ] ) );
		$cache_group = Helpers::get_cache_group();

		// Try to get cached data first.
		$cached_data = wp_cache_get( $cache_key, $cache_group );

		// Use cached data if it exists.
		if ( false !== $cached_data ) {
			$this->data        = $cached_data['data'];
			$this->context     = $cached_data['context'];
			$this->load_status = 'LOADED_FROM_CACHE';

			return ( $this->data !== null );
		}

		// No cached data, so load from database using the shared query method.
		$events_data = self::query_db_for_events( $this->id );

		// No event found.
		if ( empty( $events_data ) ) {
			$this->clear_data();
			$this->load_status = 'NOT_FOUND';

			// Cache the result even if event doesn't exist to avoid repeated DB queries.
			wp_cache_set(
				$cache_key,
				[
					'data'    => null,
					'context' => null,
				],
				$cache_group
			);

			// Return false to indicate that event does not exist.
			return false;
		}

		// Get the event data (should only be one since we queried for a single ID).
		$event_data    = reset( $events_data );
		$this->data    = $event_data;
		$this->context = $event_data->context;

		// Add context to event data.
		$this->data->context = $this->context;

		$this->load_status = 'LOADED_FROM_DB';

		// Cache the result.
		wp_cache_set(
			$cache_key,
			[
				'data'    => $this->data,
				'context' => $this->context,
			],
			$cache_group
		);

		return true;
	}

	/**
	 * Query database for events and their contexts.
	 *
	 * @param int|array $event_ids Single event ID or array of event IDs.
	 * @return array Array of event data grouped by event ID, or empty array if no events found.
	 */
	private static function query_db_for_events( $event_ids ): array {
		global $wpdb;
		$simple_history = Simple_History::get_instance();
		$table_name     = $simple_history->get_events_table_name();
		$contexts_table = $simple_history->get_contexts_table_name();

		// Normalize to array and ensure all are integers.
		$ids = is_array( $event_ids ) ? $event_ids : [ $event_ids ];
		$ids = array_map( 'intval', $ids );

		if ( empty( $ids ) ) {
			return [];
		}

		// Query for events using IN clause (works for both single and multiple IDs).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT 
					e.*,
					c.key,
					c.value
				FROM %i e
				LEFT JOIN %i c ON e.id = c.history_id
				WHERE e.id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')
				ORDER BY e.id, c.context_id',
				array_merge( [ $table_name, $contexts_table ], $ids )
			)
		);

		if ( empty( $results ) ) {
			return [];
		}

		// Group results by event ID.
		$events_data = [];
		foreach ( $results as $row ) {
			$event_id = $row->id;

			// Initialize event data if not exists.
			if ( ! isset( $events_data[ $event_id ] ) ) {
				$events_data[ $event_id ] = [
					'id'                  => $row->id,
					'date'                => $row->date,
					'logger'              => $row->logger,
					'level'               => $row->level,
					'message'             => $row->message,
                    // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					'occasionsID'         => $row->occasionsID,
					'initiator'           => $row->initiator,
                    // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					'repeatCount'         => '1',
                    // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					'subsequentOccasions' => '1',
                    // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					'maxId'               => $row->id,
                    // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					'minId'               => $row->id,
					'context_message_key' => null,
					'context'             => [],
				];
			}

			// Add context data if exists.
			if ( $row->key !== null ) {
				$events_data[ $event_id ]['context'][ $row->key ] = $row->value;

				// Move up _message_key from context to main data.
				if ( $row->key === '_message_key' ) {
					$events_data[ $event_id ]['context_message_key'] = $row->value;
				}
			}
		}

		// Convert to object.
		foreach ( $events_data as $event_id => $event_data ) {
			$events_data[ $event_id ] = (object) $event_data;
		}

		return $events_data;
	}

	/**
	 * Magic method to get properties of the event data object.
	 *
	 * @param string $name Property name.
	 * @return mixed Property value, null if property does not exist.
	 */
	public function __get( string $name ) {
		return $this->data->$name ?? null;
	}

	/**
	 * Magic method to check if a property exists in the event data object.
	 *
	 * @param string $name Property name.
	 * @return bool True if property exists, false otherwise.
	 */
	public function __isset( string $name ): bool {
		return isset( $this->data->$name );
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->delete(
			$contexts_table,
			[
				'history_id' => $this->id,
				'key'        => '_sticky',
			],
			[ '%d', '%s' ]
		);

		// Add the sticky context.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
			// Clear cache to ensure all related data is fresh.
			Helpers::clear_cache();

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->delete(
			$contexts_table,
			[
				'history_id' => $this->id,
				'key'        => '_sticky',
			],
			[ '%d', '%s' ]
		);

		if ( $result ) {
			// Clear cache to ensure all related data is fresh.
			Helpers::clear_cache();

			// Reload data to reflect changes.
			$this->reload_data();
		}

		return (bool) $result;
	}
}
