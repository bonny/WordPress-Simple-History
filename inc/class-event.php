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
		if ( $cached_data !== false ) {
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
	 * @return array|false Array with event details on success, false if event doesn't exist.
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
	 * Get event details as plain text.
	 *
	 * Suitable for plain text emails, log files, Slack messages, and other
	 * text-based outputs. Key-value pairs become "Label: Value" lines,
	 * changes become "old → new", screen-reader text is dropped.
	 * See Details_Text for the rules.
	 *
	 * @return string Plain text formatted details, empty string if event doesn't exist or has no details.
	 */
	public function get_details_text(): string {
		return Details_Text::from_html( $this->get_details_html() );
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
		if ( $cached_data !== false ) {
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
			if ( $row->key === null ) {
				continue;
			}

			$events_data[ $event_id ]['context'][ $row->key ] = $row->value;

			// Move up _message_key from context to main data.
			if ( $row->key !== '_message_key' ) {
				continue;
			}

			$events_data[ $event_id ]['context_message_key'] = $row->value;
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

	/**
	 * Get reactions for this event.
	 *
	 * @return array Associative array of reaction types to user ID arrays, e.g. {"thumbsup": [1, 2]}.
	 */
	public function get_reactions(): array {
		if ( ! isset( $this->context['_reactions'] ) ) {
			return [];
		}

		$reactions = json_decode( $this->context['_reactions'], true );

		return is_array( $reactions ) ? $reactions : [];
	}

	/**
	 * Check if a user has reacted with a specific type.
	 *
	 * @param string $type    Reaction type, e.g. "thumbsup".
	 * @param int    $user_id User ID.
	 * @return bool True if user has reacted.
	 */
	public function has_user_reacted( string $type, int $user_id ): bool {
		$reactions = $this->get_reactions();

		return isset( $reactions[ $type ] ) && in_array( $user_id, $reactions[ $type ], true );
	}

	/**
	 * Add a reaction from a user.
	 *
	 * @param string $type    Reaction type, e.g. "thumbsup".
	 * @param int    $user_id User ID.
	 * @return bool True on success.
	 */
	public function add_reaction( string $type, int $user_id ): bool {
		$reactions = $this->get_reactions();

		if ( ! isset( $reactions[ $type ] ) ) {
			$reactions[ $type ] = [];
		}

		// Already reacted.
		if ( in_array( $user_id, $reactions[ $type ], true ) ) {
			return true;
		}

		$reactions[ $type ][] = $user_id;

		return $this->save_reactions( $reactions );
	}

	/**
	 * Remove a reaction from a user.
	 *
	 * @param string $type    Reaction type, e.g. "thumbsup".
	 * @param int    $user_id User ID.
	 * @return bool True on success.
	 */
	public function remove_reaction( string $type, int $user_id ): bool {
		$reactions = $this->get_reactions();

		if ( ! isset( $reactions[ $type ] ) ) {
			return true;
		}

		$reactions[ $type ] = array_values(
			array_filter(
				$reactions[ $type ],
				function ( $id ) use ( $user_id ) {
					return $id !== $user_id;
				}
			)
		);

		// Remove empty reaction types.
		if ( empty( $reactions[ $type ] ) ) {
			unset( $reactions[ $type ] );
		}

		return $this->save_reactions( $reactions );
	}

	/**
	 * Longest an annotation may be, in characters.
	 *
	 * @since 5.34.0
	 */
	const ANNOTATION_MAX_LENGTH = 1000;

	/**
	 * How many previous versions of an annotation are kept.
	 *
	 * @since 5.34.0
	 */
	const ANNOTATION_MAX_HISTORY = 20;

	/**
	 * This event's annotation, if it has one.
	 *
	 * @since 5.34.0
	 * @return array|null { text, user_id, updated_at, history } or null.
	 */
	public function get_annotation() {
		$raw = $this->context['_annotation'] ?? null;

		if ( ! $raw ) {
			return null;
		}

		$annotation = json_decode( $raw, true );

		return is_array( $annotation ) ? $annotation : null;
	}

	/**
	 * Attach a note to this event, or replace the one it has.
	 *
	 * An annotation is the first thing a human writes into the log — every
	 * other field was written by a logger. Two consequences follow, and both
	 * are handled here rather than left to callers:
	 *
	 * Edits append. The previous text is kept in `history` with who wrote it
	 * and when, so an annotation cannot be used to quietly rewrite what a
	 * record said — which is the exact thing an audit log exists to prevent.
	 * The list is capped, oldest dropped first, so one event cannot grow
	 * without bound.
	 *
	 * Removing a note appends too. It stores an empty note rather than
	 * deleting the row, because deleting it would take the history with it —
	 * write A, edit to B, remove, write C, and the event would show C with no
	 * trace that A or B ever existed. That is the rewrite this design exists
	 * to prevent, reached by a longer route. An event that never had a note
	 * stores nothing at all.
	 *
	 * The text is sanitised at the point of storage. Newlines and tabs are
	 * kept, because people write notes in paragraphs; every other control
	 * character goes, because this value ends up in CSV exports, webhook
	 * payloads and syslog frames, and the ones that matter there are the
	 * invisible ones.
	 *
	 * Capability is the caller's business — see the premium REST route, which
	 * requires manage_options to match sticky.
	 *
	 * @since 5.34.0
	 * @param string $text    The note. An empty string removes the annotation.
	 * @param int    $user_id Who wrote it.
	 * @return bool True on success.
	 */
	public function annotate( string $text, int $user_id ): bool {
		$text = self::sanitize_annotation( $text );

		if ( $text === '' ) {
			return $this->remove_annotation( $user_id );
		}

		$existing = $this->get_annotation();

		// Read the history first and unconditionally. A removed note leaves
		// an entry behind with empty text and its whole history intact, so
		// reading it only when there is current text would let "remove, then
		// write a new note" drop everything that came before — the exact
		// erasure remove_annotation() exists to prevent.
		$history = isset( $existing['history'] ) && is_array( $existing['history'] )
			? $existing['history']
			: [];

		if ( $existing !== null && ! empty( $existing['text'] ) ) {
			// Unchanged text is not a new version, so re-saving the same note
			// does not fill the history with copies of itself.
			if ( $existing['text'] === $text ) {
				return true;
			}

			$history[] = [
				'text'       => $existing['text'],
				'user_id'    => (int) ( $existing['user_id'] ?? 0 ),
				'updated_at' => (string) ( $existing['updated_at'] ?? '' ),
			];
		}

		$history = array_slice( $history, -self::ANNOTATION_MAX_HISTORY );

		return $this->save_annotation(
			[
				'text'       => $text,
				'user_id'    => $user_id,
				'updated_at' => current_time( 'mysql', true ),
				'history'    => $history,
			]
		);
	}

	/**
	 * Remove this event's note, keeping the record of what it said.
	 *
	 * Stores an empty note rather than deleting the annotation, so the
	 * previous versions survive — see annotate() for why that matters. The
	 * row is only deleted when there is nothing to preserve, which keeps an
	 * event that never had a note from gaining an empty one.
	 *
	 * @since 5.34.0
	 * @param int $user_id Who removed it.
	 * @return bool True on success.
	 */
	private function remove_annotation( int $user_id ): bool {
		$existing = $this->get_annotation();

		$history = isset( $existing['history'] ) && is_array( $existing['history'] )
			? $existing['history']
			: [];

		$had_text = $existing !== null && ! empty( $existing['text'] );

		// Nothing was ever written here, or it is already empty with no
		// history behind it. Leave no row.
		if ( ! $had_text && $history === [] ) {
			return $this->save_annotation( null );
		}

		if ( $had_text ) {
			$history[] = [
				'text'       => $existing['text'],
				'user_id'    => (int) ( $existing['user_id'] ?? 0 ),
				'updated_at' => (string) ( $existing['updated_at'] ?? '' ),
			];

			$history = array_slice( $history, -self::ANNOTATION_MAX_HISTORY );
		}

		return $this->save_annotation(
			[
				'text'       => '',
				'user_id'    => $user_id,
				'updated_at' => current_time( 'mysql', true ),
				'history'    => $history,
			]
		);
	}

	/**
	 * Clean annotation text for storage.
	 *
	 * @since 5.34.0
	 * @param string $text Raw text.
	 * @return string Text safe to store, capped in length.
	 */
	public static function sanitize_annotation( string $text ): string {
		// Everything in the C0 range except tab and newline. A carriage
		// return goes too: it is invisible, it survives every export, and it
		// is the half of a line ending that breaks a syslog frame.
		$text = preg_replace( '/[\x00-\x08\x0B-\x1F\x7F]/', '', $text );

		// Tags go; the text is otherwise stored as it was typed.
		//
		// This was sanitize_textarea_field(), which was wrong twice over. It
		// strips every percent-encoded octet, so
		// `example.com/?s=foo%20bar` was stored as `example.com/?s=foobar` —
		// and pasting an encoded URL into a note about a request is an
		// ordinary thing to do. It also HTML-escapes, so `A < B` became
		// `A &lt; B` in the database, which the reader then saw literally,
		// with no way back to what they typed. Both were silent.
		//
		// Escaping belongs at each output instead, which is the convention
		// every other logged string here already follows: store what was
		// written, escape on the way out.
		//
		// Traced rather than assumed, because leaving markup in the stored
		// value moves the obligation onto whoever reads it. As of this
		// commit the note text has exactly one output — the REST
		// `annotation` field, rendered by React in premium's
		// TableViewAnnotation, which escapes. Nothing else emits it: core's
		// Export writes the rendered message, header and details rather than
		// context values, premium's export is that same class, and the JSON
		// feed outputs rendered HTML too. `_annotation` is also kept out of
		// the REST `context` field and out of `metadata_search`.
		//
		// Anything that starts emitting a note has to escape it. That is the
		// deal this change makes, and it is worth knowing before writing the
		// next output path.
		//
		// wp_strip_all_tags() rather than nothing at all: it takes
		// `<script>…</script>` out whole, while leaving `%20`, a lone `<`
		// in `A < B`, `&` and the newlines people write notes in.
		$text = wp_strip_all_tags( $text );

		// Characters, not bytes: substr() would cut a multi-byte character in
		// half and leave an invalid sequence in the middle of an audit
		// record. Applied after the tags come out, so the cap counts what is
		// actually stored — when this escaped first, a note the editor
		// accepted at exactly 1000 characters could arrive here at 1004 and
		// lose its tail, and a cut landing inside an escape left `&l`. WordPress declares mb_substr() itself in
		// wp-includes/compat.php when the extension is missing, so this does
		// not actually depend on mbstring being installed — which is what the
		// sniff below is guarding against.
		// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
		return trim( mb_substr( $text, 0, self::ANNOTATION_MAX_LENGTH ) );
	}

	/**
	 * Write or remove the annotation context row.
	 *
	 * @since 5.34.0
	 * @param array|null $annotation The annotation, or null to remove it.
	 * @return bool True on success.
	 */
	private function save_annotation( $annotation ): bool {
		global $wpdb;

		$simple_history = Simple_History::get_instance();
		$contexts_table = $simple_history->get_contexts_table_name();

		// Removing the note is the one case that deletes first, because
		// there is nothing to put in its place.
		if ( $annotation === null ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = $wpdb->delete(
				$contexts_table,
				[
					'history_id' => $this->id,
					'key'        => '_annotation',
				],
				[ '%d', '%s' ]
			);

			Helpers::clear_cache();
			$this->reload_data();

			// $wpdb->delete() returns false on error and a row count
			// otherwise, and zero rows is a success here — it means there was
			// nothing to remove. Returning true unconditionally reported a
			// database error as a completed removal, and the caller went on
			// to log that the note had been taken away while it was still on
			// the event.
			return $deleted !== false;
		}

		// The new row goes down BEFORE the old one comes out.
		//
		// Written the other way round, a failed insert left neither — taking
		// the note and all twenty of its previous versions with it, which is
		// precisely the record annotate() exists to keep. There is no unique
		// key on (history_id, key) in this table, so $wpdb->replace() cannot
		// collapse the two into one statement; the old row has to be deleted
		// explicitly, and only once the new one is safely stored.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert(
			$contexts_table,
			[
				'history_id' => $this->id,
				'key'        => '_annotation',
				'value'      => wp_json_encode( $annotation ),
			],
			[ '%d', '%s', '%s' ]
		);

		if ( $result === false ) {
			// The previous annotation is still there, untouched.
			return false;
		}

		$inserted_id = (int) $wpdb->insert_id;

		// Every EARLIER row for this key, of which there is normally exactly
		// one. Matched on the primary key rather than by value so a row
		// written by an older version, or a duplicate left by one, goes too.
		//
		// Strictly less-than, not `<> $inserted_id`. With `<>`, two saves
		// racing each other destroyed the note outright: A inserts row 100,
		// B inserts row 101, A deletes everything that is not 100 — taking
		// 101 — and B deletes everything that is not 101 — taking 100. No
		// rows left, the note and all twenty previous versions gone, and
		// both requests returning true to two people who each saw a save
		// succeed. Two administrators on one event, or one administrator in
		// two tabs, was enough.
		//
		// `<` cannot do that. Nobody deletes a row with an id above their
		// own, so the highest id written always survives; the worst outcome
		// is the ordinary lost update, where the slower writer's text is
		// replaced by the faster one's. A note can be overwritten. It can no
		// longer be erased.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$contexts_table} WHERE history_id = %d AND `key` = '_annotation' AND context_id < %d",
				$this->id,
				$inserted_id
			)
		);

		Helpers::clear_cache();
		$this->reload_data();

		return true;
	}

	/**
	 * Save reactions to the context table.
	 *
	 * @param array $reactions Reactions array to persist.
	 * @return bool True on success.
	 */
	private function save_reactions( array $reactions ): bool {
		global $wpdb;

		$simple_history = Simple_History::get_instance();
		$contexts_table = $simple_history->get_contexts_table_name();

		// Remove existing reactions context row.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$contexts_table,
			[
				'history_id' => $this->id,
				'key'        => '_reactions',
			],
			[ '%d', '%s' ]
		);

		// Clear cache so reloads get fresh data.
		Helpers::clear_cache();

		// If no reactions left, we're done.
		if ( empty( $reactions ) ) {
			$this->reload_data();
			return true;
		}

		// Insert updated reactions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert(
			$contexts_table,
			[
				'history_id' => $this->id,
				'key'        => '_reactions',
				'value'      => wp_json_encode( $reactions ),
			],
			[ '%d', '%s', '%s' ]
		);

		if ( $result ) {
			$this->reload_data();
		}

		return (bool) $result;
	}
}
