<?php

namespace Simple_History;

/**
 * Imports existing WordPress data into Simple History.
 *
 * This class handles importing historical data from WordPress
 * that exists before the plugin was activated, such as:
 * - Posts and pages (creation and modification dates)
 * - Users (registration dates)
 */
class Existing_Data_Importer {
	/**
	 * Context key used to identify backfilled events.
	 */
	const BACKFILLED_CONTEXT_KEY = '_backfilled_event';

	/** @var Simple_History */
	private $simple_history;

	/** @var array Import results */
	private $results = [];

	/** @var int|null Number of days back to import (null = use retention setting) */
	private $days_back = null;

	/**
	 * Constructor.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 */
	public function __construct( $simple_history ) {
		$this->simple_history = $simple_history;
	}

	/**
	 * Import all existing data.
	 *
	 * @param array $options Import options.
	 *                       - post_types: Array of post types to import.
	 *                       - import_users: Whether to import users.
	 *                       - limit: Max number of items to import per type.
	 *                       - days_back: Number of days back to import (null = use retention setting).
	 * @return array Import results.
	 */
	public function import_all( $options = [] ) {
		$defaults = [
			'post_types'   => [ 'post', 'page' ],
			'import_users' => false,
			'limit'        => 100,
			'days_back'    => null,
		];

		$options = wp_parse_args( $options, $defaults );

		// Set days_back for use in import methods.
		// If not specified, use the retention setting (same as purge interval).
		$this->days_back = $options['days_back'] ?? Helpers::get_clear_history_interval();

		$this->results = [
			'posts_imported'         => 0,
			'users_imported'         => 0,
			'post_events_created'    => 0,
			'user_events_created'    => 0,
			'posts_skipped_imported' => 0,
			'posts_skipped_logged'   => 0,
			'users_skipped_imported' => 0,
			'users_skipped_logged'   => 0,
			'posts_details'          => [],
			'users_details'          => [],
			'skipped_details'        => [],
			'errors'                 => [],
		];

		// Import posts and pages.
		foreach ( $options['post_types'] as $post_type ) {
			$this->import_posts( $post_type, $options['limit'] );
		}

		// Import users.
		if ( $options['import_users'] ) {
			$this->import_users( $options['limit'] );
		}

		return $this->results;
	}

	/**
	 * Import posts of a specific post type.
	 *
	 * @param string $post_type Post type to import.
	 * @param int    $limit Max number of posts to import. Use -1 for no limit.
	 * @return int Number of posts imported.
	 */
	public function import_posts( $post_type = 'post', $limit = 100 ) {
		// Use Media Logger for attachments, Post Logger for everything else.
		if ( 'attachment' === $post_type ) {
			$logger      = $this->simple_history->get_instantiated_logger_by_slug( 'SimpleMediaLogger' );
			$logger_type = 'media';
		} else {
			$logger      = $this->simple_history->get_instantiated_logger_by_slug( 'SimplePostLogger' );
			$logger_type = 'post';
		}

		if ( ! $logger ) {
			$this->results['errors'][] = ucfirst( $logger_type ) . ' logger not found';
			return 0;
		}

		// Get posts, ordered by date (oldest first for chronological import).
		$post_statuses = [ 'publish', 'draft', 'pending', 'private' ];

		// Attachments use 'inherit' status, not 'publish'.
		if ( 'attachment' === $post_type ) {
			$post_statuses = [ 'inherit', 'private' ];
		}

		$args = [
			'post_type'      => $post_type,
			'post_status'    => $post_statuses,
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'ASC',
		];

		// Add date filtering if days_back is set.
		// Only import posts created/modified within the retention period.
		if ( $this->days_back !== null && $this->days_back > 0 ) {
			$cutoff_timestamp = Date_Helper::get_last_n_days_start_timestamp( $this->days_back );
			$cutoff_date      = gmdate( 'Y-m-d H:i:s', $cutoff_timestamp );

			$args['date_query'] = [
				[
					'after'     => $cutoff_date,
					'inclusive' => true,
				],
			];
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- Admin-only import, caching not needed.
		$posts = get_posts( $args );

		// Get all post IDs to check for duplicates.
		$post_ids = wp_list_pluck( $posts, 'ID' );

		// Check which posts have already been logged (imported or naturally).
		// Attachments use different message keys.
		if ( 'attachment' === $post_type ) {
			$already_logged_created = $this->get_already_logged_post_ids( $post_ids, 'attachment_created' );
			$already_logged_updated = $this->get_already_logged_post_ids( $post_ids, 'attachment_updated' );
		} else {
			$already_logged_created = $this->get_already_logged_post_ids( $post_ids, 'post_created' );
			$already_logged_updated = $this->get_already_logged_post_ids( $post_ids, 'post_updated' );
		}

		$imported_count         = 0;
		$skipped_imported_count = 0;
		$skipped_logged_count   = 0;

		foreach ( $posts as $post ) {
			// Validate GMT dates. WordPress uses '0000-00-00 00:00:00' for drafts and scheduled posts.
			// If GMT date is invalid, use the local date converted to GMT.
			$post_date_gmt = $post->post_date_gmt;
			if ( $post_date_gmt === '0000-00-00 00:00:00' || empty( $post_date_gmt ) ) {
				$post_date_gmt = get_gmt_from_date( $post->post_date );
			}

			$post_modified_gmt = $post->post_modified_gmt;
			if ( $post_modified_gmt === '0000-00-00 00:00:00' || empty( $post_modified_gmt ) ) {
				$post_modified_gmt = get_gmt_from_date( $post->post_modified );
			}

			$post_detail = [
				'id'            => $post->ID,
				'title'         => $post->post_title,
				'type'          => $post->post_type,
				'status'        => $post->post_status,
				'created_date'  => $post_date_gmt,
				'modified_date' => $post_modified_gmt,
				'events_logged' => [],
			];

			$has_created      = isset( $already_logged_created[ (string) $post->ID ] );
			$has_updated      = isset( $already_logged_updated[ (string) $post->ID ] );
			$post_has_updates = $post_date_gmt !== $post_modified_gmt;

			// Skip if both events already exist (or just created if no updates).
			if ( $has_created && ( ! $post_has_updates || $has_updated ) ) {
				// Determine if this was imported or naturally logged.
				// If created event is imported, count as imported. Otherwise naturally logged.
				$is_imported = $already_logged_created[ (string) $post->ID ]['is_imported'];
				$skip_reason = $is_imported ? 'already_imported' : 'already_logged';

				$this->results['skipped_details'][] = [
					'type'      => 'post',
					'id'        => $post->ID,
					'title'     => $post->post_title,
					'post_type' => $post->post_type,
					'reason'    => $skip_reason,
				];

				if ( $is_imported ) {
					++$skipped_imported_count;
				} else {
					++$skipped_logged_count;
				}
				continue;
			}

			// Log post creation if not already imported.
			if ( ! $has_created ) {
				// Get post author for initiator.
				$post_author = get_user_by( 'id', $post->post_author );

				// Media Logger uses different context keys and message.
				if ( 'attachment' === $post_type ) {
					$file      = get_attached_file( $post->ID );
					$file_size = false;

					if ( $file && file_exists( $file ) ) {
						$file_size = filesize( $file );
					}

					$context     = [
						'post_type'                  => get_post_type( $post ),
						'attachment_id'              => $post->ID,
						'attachment_title'           => $post->post_title,
						'attachment_filename'        => basename( $file ),
						'attachment_mime'            => get_post_mime_type( $post ),
						'attachment_filesize'        => $file_size,
						'_date'                      => $post_date_gmt,
						self::BACKFILLED_CONTEXT_KEY => '1',
					];
					$message_key = 'attachment_created';
				} else {
					$context     = [
						'post_id'                    => $post->ID,
						'post_type'                  => $post->post_type,
						'post_title'                 => $post->post_title,
						'_date'                      => $post_date_gmt,
						self::BACKFILLED_CONTEXT_KEY => '1',
					];
					$message_key = 'post_created';
				}

				// Set initiator to post author if available.
				if ( $post_author ) {
					$context['_initiator']  = Log_Initiators::WP_USER;
					$context['_user_id']    = $post_author->ID;
					$context['_user_login'] = $post_author->user_login;
					$context['_user_email'] = $post_author->user_email;
				} else {
					$context['_initiator'] = Log_Initiators::OTHER;
				}

				$logger->info_message( $message_key, $context );
				++$this->results['post_events_created'];

				$post_detail['events_logged'][] = [
					'type' => 'created',
					'date' => $post_date_gmt,
				];
			}

			// If post has been modified after creation, also log an update (if not already imported).
			if ( $post_has_updates && ! $has_updated ) {
				// Get post author for initiator.
				$post_author = get_user_by( 'id', $post->post_author );

				// Media Logger uses different context keys and message.
				if ( 'attachment' === $post_type ) {
					$file      = get_attached_file( $post->ID );
					$file_size = false;

					if ( $file && file_exists( $file ) ) {
						$file_size = filesize( $file );
					}

					$context     = [
						'post_type'                  => get_post_type( $post ),
						'attachment_id'              => $post->ID,
						'attachment_title'           => $post->post_title,
						'attachment_filename'        => basename( $file ),
						'attachment_mime'            => get_post_mime_type( $post ),
						'attachment_filesize'        => $file_size,
						'_date'                      => $post_modified_gmt,
						self::BACKFILLED_CONTEXT_KEY => '1',
					];
					$message_key = 'attachment_updated';
				} else {
					$context     = [
						'post_id'                    => $post->ID,
						'post_type'                  => $post->post_type,
						'post_title'                 => $post->post_title,
						'_date'                      => $post_modified_gmt,
						self::BACKFILLED_CONTEXT_KEY => '1',
					];
					$message_key = 'post_updated';
				}

				// Set initiator to post author if available.
				if ( $post_author ) {
					$context['_initiator']  = Log_Initiators::WP_USER;
					$context['_user_id']    = $post_author->ID;
					$context['_user_login'] = $post_author->user_login;
					$context['_user_email'] = $post_author->user_email;
				} else {
					$context['_initiator'] = Log_Initiators::OTHER;
				}

				$logger->info_message( $message_key, $context );
				++$this->results['post_events_created'];

				$post_detail['events_logged'][] = [
					'type' => 'updated',
					'date' => $post_modified_gmt,
				];
			}

			// Only add to imported if we logged at least one event.
			if ( ! empty( $post_detail['events_logged'] ) ) {
				$this->results['posts_details'][] = $post_detail;
				++$imported_count;
			}
		}

		$this->results['posts_imported']         += $imported_count;
		$this->results['posts_skipped_imported'] += $skipped_imported_count;
		$this->results['posts_skipped_logged']   += $skipped_logged_count;

		return $imported_count;
	}

	/**
	 * Import users.
	 *
	 * @param int $limit Max number of users to import. Use -1 for no limit.
	 * @return int Number of users imported.
	 */
	public function import_users( $limit = 100 ) {
		$user_logger = $this->simple_history->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

		if ( ! $user_logger ) {
			$this->results['errors'][] = 'User logger not found';
			return 0;
		}

		// Get users, ordered by registration date.
		$args = [
			'number'  => $limit,
			'orderby' => 'registered',
			'order'   => 'ASC',
		];

		// Add date filtering if days_back is set.
		// Only import users registered within the retention period.
		if ( $this->days_back !== null && $this->days_back > 0 ) {
			$cutoff_timestamp = Date_Helper::get_last_n_days_start_timestamp( $this->days_back );
			$cutoff_date      = gmdate( 'Y-m-d H:i:s', $cutoff_timestamp );

			// Use date_query for users (available since WP 4.1).
			$args['date_query'] = [
				[
					'after'     => $cutoff_date,
					'inclusive' => true,
				],
			];
		}

		$users = get_users( $args );

		// Get all user IDs to check for duplicates.
		$user_ids = wp_list_pluck( $users, 'ID' );

		// Check which users have already been logged (imported or naturally).
		$already_logged = $this->get_already_logged_user_ids( $user_ids );

		$imported_count         = 0;
		$skipped_imported_count = 0;
		$skipped_logged_count   = 0;

		foreach ( $users as $user ) {
			// Skip if already logged (imported or naturally).
			if ( isset( $already_logged[ (string) $user->ID ] ) ) {
				$is_imported = $already_logged[ (string) $user->ID ]['is_imported'];
				$skip_reason = $is_imported ? 'already_imported' : 'already_logged';

				$this->results['skipped_details'][] = [
					'type'   => 'user',
					'id'     => $user->ID,
					'login'  => $user->user_login,
					'reason' => $skip_reason,
				];

				if ( $is_imported ) {
					++$skipped_imported_count;
				} else {
					++$skipped_logged_count;
				}
				continue;
			}

			// Log user registration with original registration date.
			// Only store immutable data (user_id and user_login).
			// Don't store email, names, URL, or role as these may have changed since registration.
			$user_logger->info_message(
				'user_created',
				[
					'created_user_id'            => $user->ID,
					'created_user_login'         => $user->user_login,
					// user_registered is stored in GMT by WordPress (using gmdate).
					// Pass it directly to logger, which expects GMT dates.
					'_date'                      => $user->user_registered,
					'_initiator'                 => Log_Initiators::OTHER,
					self::BACKFILLED_CONTEXT_KEY => '1',
				]
			);
			++$this->results['user_events_created'];

			$this->results['users_details'][] = [
				'id'              => $user->ID,
				'login'           => $user->user_login,
				'email'           => $user->user_email,
				'registered_date' => $user->user_registered,
				'roles'           => (array) $user->roles,
			];

			++$imported_count;
		}

		$this->results['users_imported']         += $imported_count;
		$this->results['users_skipped_imported'] += $skipped_imported_count;
		$this->results['users_skipped_logged']   += $skipped_logged_count;

		return $imported_count;
	}

	/**
	 * Get import results.
	 *
	 * @return array Import results.
	 */
	public function get_results() {
		return $this->results;
	}

	/**
	 * Get post IDs that have already been logged (imported or naturally).
	 *
	 * Returns array with post_id as key and info about whether it was imported.
	 *
	 * @param array  $post_ids Post IDs to check.
	 * @param string $message_key Message key (post_created or post_updated).
	 * @return array Array with post_id => ['is_imported' => 0|1].
	 */
	private function get_already_logged_post_ids( $post_ids, $message_key ) {
		global $wpdb;

		if ( empty( $post_ids ) ) {
			return [];
		}

		$contexts_table = $this->simple_history->get_contexts_table_name();

		// Determine context key based on message key.
		// Attachments use 'attachment_id', regular posts use 'post_id'.
		$context_key = in_array( $message_key, [ 'attachment_created', 'attachment_updated' ], true ) ? 'attachment_id' : 'post_id';

		// Find events with matching post_id/attachment_id and message_key.
		// Use LEFT JOIN to detect if backfilled event exists (1 = imported, 0 = naturally logged).
		$backfill_key = self::BACKFILLED_CONTEXT_KEY;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare(
			"SELECT DISTINCT
				c1.value as post_id,
				MAX(CASE WHEN c2.key = %s THEN 1 ELSE 0 END) as is_imported
			FROM {$contexts_table} c1
			LEFT JOIN {$contexts_table} c2 ON c1.history_id = c2.history_id AND c2.key = %s
			INNER JOIN {$contexts_table} c3 ON c1.history_id = c3.history_id
			WHERE c1.key = %s
			  AND c1.value IN (" . implode( ',', array_map( 'intval', $post_ids ) ) . ')
			  AND c3.key = %s
			  AND c3.value = %s
			GROUP BY c1.value',
			$backfill_key,
			$backfill_key,
			$context_key,
			'_message_key',
			$message_key
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Convert to associative array: post_id => ['is_imported' => 0|1].
		$post_status = [];
		foreach ( $results as $row ) {
			$post_status[ $row['post_id'] ] = [ 'is_imported' => (int) $row['is_imported'] ];
		}

		return $post_status;
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get user IDs that have already been logged (imported or naturally).
	 *
	 * Returns array with user_id as key and info about whether it was imported.
	 *
	 * @param array $user_ids User IDs to check.
	 * @return array Array with user_id => ['is_imported' => 0|1].
	 */
	private function get_already_logged_user_ids( $user_ids ) {
		global $wpdb;

		if ( empty( $user_ids ) ) {
			return [];
		}

		$contexts_table = $this->simple_history->get_contexts_table_name();

		// Find events with matching created_user_id.
		// Use LEFT JOIN to detect if backfilled event exists (1 = imported, 0 = naturally logged).
		$backfill_key = self::BACKFILLED_CONTEXT_KEY;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare(
			"SELECT DISTINCT
				c1.value as user_id,
				MAX(CASE WHEN c2.key = %s THEN 1 ELSE 0 END) as is_imported
			FROM {$contexts_table} c1
			LEFT JOIN {$contexts_table} c2 ON c1.history_id = c2.history_id AND c2.key = %s
			INNER JOIN {$contexts_table} c3 ON c1.history_id = c3.history_id
			WHERE c1.key = %s
			  AND c1.value IN (" . implode( ',', array_map( 'intval', $user_ids ) ) . ')
			  AND c3.key = %s
			  AND c3.value = %s
			GROUP BY c1.value',
			$backfill_key,
			$backfill_key,
			'created_user_id',
			'_message_key',
			'user_created'
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Convert to associative array: user_id => ['is_imported' => 0|1].
		$user_status = [];
		foreach ( $results as $row ) {
			$user_status[ $row['user_id'] ] = [ 'is_imported' => (int) $row['is_imported'] ];
		}

		return $user_status;
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get preview counts for posts and users.
	 *
	 * Uses lightweight COUNT queries for fast performance.
	 *
	 * @return array Preview counts with 'post_types' and 'users' keys.
	 */
	public function get_preview_counts() {
		global $wpdb;

		$counts = [
			'post_types' => [],
			'users'      => 0,
		];

		// Get all public post types.
		$post_types = get_post_types( [ 'public' => true ], 'objects' );

		// Add attachment post type (not public by default, but has historical data we can import).
		$attachment_post_type = get_post_type_object( 'attachment' );
		if ( $attachment_post_type ) {
			$post_types['attachment'] = $attachment_post_type;
		}

		// Count posts for each post type using a single query.
		foreach ( $post_types as $post_type ) {
			// Attachments use 'inherit' status, not 'publish'.
			if ( 'attachment' === $post_type->name ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts}
						WHERE post_type = %s
						AND post_status IN ('inherit', 'private')",
						$post_type->name
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts}
						WHERE post_type = %s
						AND post_status IN ('publish', 'draft', 'pending', 'private')",
						$post_type->name
					)
				);
			}

			$counts['post_types'][ $post_type->name ] = (int) $count;
		}

		// Count users.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users -- Simple count query, get_users() would be inefficient for large user bases
		$counts['users'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );

		return $counts;
	}

	/**
	 * Count all backfilled events.
	 *
	 * Counts events that have the _backfilled_event context key,
	 * which indicates they were created during a backfill operation.
	 *
	 * @return int Number of backfilled events.
	 */
	public function get_backfilled_events_count() {
		global $wpdb;

		$context_table_name = $this->simple_history->get_contexts_table_name();

		// Count events with the _backfilled_event context key.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(DISTINCT history_id) FROM {$context_table_name} WHERE `key` = %s",
				self::BACKFILLED_CONTEXT_KEY
			)
		);

		return (int) $count;
	}

	/**
	 * Get a preview of what would be imported.
	 *
	 * Provides accurate counts by checking what's already logged in Simple History.
	 * Supports both limited (auto-backfill) and unlimited (manual backfill) modes.
	 *
	 * @param array $options Preview options.
	 *                       - post_types: Array of post types to check.
	 *                       - include_users: Whether to include users.
	 *                       - limit: Max number of items per type (-1 for unlimited).
	 *                       - days_back: Number of days back to check (null for all time).
	 * @return array Preview data with counts per type.
	 */
	public function get_auto_backfill_preview( $options = [] ) {
		$defaults = [
			'post_types'    => [ 'post', 'page' ],
			'include_users' => true,
			'limit'         => 100,
			'days_back'     => Helpers::get_clear_history_interval(),
		];

		$options = wp_parse_args( $options, $defaults );

		$preview = [
			'post_types'     => [],
			'users'          => 0,
			'total'          => 0,
			'days_back'      => $options['days_back'],
			'limit_per_type' => $options['limit'],
		];

		// Calculate cutoff date if days_back is set and > 0 (null or 0 = all time).
		$cutoff_date = null;
		if ( $options['days_back'] !== null && $options['days_back'] > 0 ) {
			$cutoff_timestamp = Date_Helper::get_last_n_days_start_timestamp( $options['days_back'] );
			$cutoff_date      = gmdate( 'Y-m-d H:i:s', $cutoff_timestamp );
		}

		// Count posts for each post type.
		foreach ( $options['post_types'] as $post_type ) {
			$post_type_obj = get_post_type_object( $post_type );
			if ( ! $post_type_obj ) {
				continue;
			}

			// Check if the required logger is available (same check as actual import).
			if ( 'attachment' === $post_type ) {
				$logger = $this->simple_history->get_instantiated_logger_by_slug( 'SimpleMediaLogger' );
			} else {
				$logger = $this->simple_history->get_instantiated_logger_by_slug( 'SimplePostLogger' );
			}

			// Skip post type if logger not available.
			if ( ! $logger ) {
				continue;
			}

			// Attachments use 'inherit' status, not 'publish'.
			if ( 'attachment' === $post_type ) {
				$post_statuses = [ 'inherit', 'private' ];
			} else {
				$post_statuses = [ 'publish', 'draft', 'pending', 'private' ];
			}

			$args = [
				'post_type'      => $post_type,
				'post_status'    => $post_statuses,
				'posts_per_page' => $options['limit'],
				'orderby'        => 'date',
				'order'          => 'ASC',
			];

			// Only add date query if we have a cutoff date.
			if ( $cutoff_date !== null ) {
				$args['date_query'] = [
					[
						'after'     => $cutoff_date,
						'inclusive' => true,
					],
				];
			}

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_posts_get_posts -- Admin-only import, caching not needed.
			$posts    = get_posts( $args );
			$post_ids = wp_list_pluck( $posts, 'ID' );
			$count    = count( $posts );

			// Check how many are already logged.
			$message_key_created    = ( 'attachment' === $post_type ) ? 'attachment_created' : 'post_created';
			$message_key_updated    = ( 'attachment' === $post_type ) ? 'attachment_updated' : 'post_updated';
			$already_logged_created = [];
			$already_logged_updated = [];

			if ( ! empty( $post_ids ) ) {
				$already_logged_created = $this->get_already_logged_post_ids( $post_ids, $message_key_created );
				$already_logged_updated = $this->get_already_logged_post_ids( $post_ids, $message_key_updated );
			}

			// Count events that would be created.
			// Only posts NOT already logged (created) will be processed.
			// Each processed post gets a created event, plus an updated event if modified.
			// Note: get_already_logged_post_ids() returns array with post_id as keys.
			// This logic must match the actual import logic in import_posts().
			$would_create_events = 0;
			foreach ( $posts as $post ) {
				// Skip if already has a created event.
				if ( isset( $already_logged_created[ $post->ID ] ) ) {
					continue;
				}

				// This post would get a created event.
				++$would_create_events;

				// Validate GMT dates - same logic as import_posts().
				// WordPress uses '0000-00-00 00:00:00' for drafts and scheduled posts.
				$post_date_gmt = $post->post_date_gmt;
				if ( $post_date_gmt === '0000-00-00 00:00:00' || empty( $post_date_gmt ) ) {
					$post_date_gmt = get_gmt_from_date( $post->post_date );
				}

				$post_modified_gmt = $post->post_modified_gmt;
				if ( $post_modified_gmt === '0000-00-00 00:00:00' || empty( $post_modified_gmt ) ) {
					$post_modified_gmt = get_gmt_from_date( $post->post_modified );
				}

				// Check if post has updates - uses string comparison like import_posts().
				$has_updates = $post_date_gmt !== $post_modified_gmt;

				if ( $has_updates && ! isset( $already_logged_updated[ $post->ID ] ) ) {
					++$would_create_events;
				}
			}

			$preview['post_types'][ $post_type ] = [
				'label'          => $post_type_obj->labels->name,
				'available'      => $count,
				'already_logged' => count( $already_logged_created ),
				'would_import'   => $would_create_events,
			];

			$preview['total'] += $would_create_events;
		}

		// Count users if included.
		if ( $options['include_users'] ) {
			// Check if the user logger is available (same check as actual import).
			$user_logger = $this->simple_history->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

			if ( $user_logger ) {
				$user_args = [
					'number'  => $options['limit'],
					'orderby' => 'registered',
					'order'   => 'ASC',
					'fields'  => 'ID',
				];

				// Only add date query if we have a cutoff date.
				if ( $cutoff_date !== null ) {
					$user_args['date_query'] = [
						[
							'after'     => $cutoff_date,
							'inclusive' => true,
						],
					];
				}

				$user_ids = get_users( $user_args );
				$count    = count( $user_ids );

				// Check how many are already logged.
				$already_logged_count = 0;
				if ( ! empty( $user_ids ) ) {
					$already_logged       = $this->get_already_logged_user_ids( $user_ids );
					$already_logged_count = count( $already_logged );
				}

				$would_import = max( 0, $count - $already_logged_count );

				$preview['users'] = [
					'available'      => $count,
					'already_logged' => $already_logged_count,
					'would_import'   => $would_import,
				];

				$preview['total'] += $would_import;
			}
		}

		return $preview;
	}

	/**
	 * Delete all imported events.
	 *
	 * This is useful for testing - allows you to clear imported data
	 * and re-run the import to verify changes.
	 *
	 * @return array Delete results with 'events_deleted' count.
	 */
	public function delete_all_imported() {
		global $wpdb;

		$table_name         = $this->simple_history->get_events_table_name();
		$context_table_name = $this->simple_history->get_contexts_table_name();

		// First, get all history IDs that have the _backfilled_event context.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$history_ids = $wpdb->get_col(
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT DISTINCT c.history_id
				FROM {$context_table_name} AS c
				WHERE c.key = %s",
				self::BACKFILLED_CONTEXT_KEY
			)
		);      

		if ( empty( $history_ids ) ) {
			return [
				'events_deleted' => 0,
				'success'        => true,
			];
		}

		$placeholders = implode( ',', array_fill( 0, count( $history_ids ), '%d' ) );

		// Delete from contexts table.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$context_table_name}
				WHERE history_id IN ({$placeholders})", // Dynamic placeholders in $placeholders variable matched with spread operator.
				...$history_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// Delete from history table.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$deleted_count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name}
				WHERE id IN ({$placeholders})", // Dynamic placeholders in $placeholders variable matched with spread operator.
				...$history_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		return [
			'events_deleted' => (int) $deleted_count,
			'success'        => true,
		];
	}
}
