<?php

namespace Simple_History;

use Simple_History\Loggers\Post_Logger;
use Simple_History\Loggers\User_Logger;

/**
 * Imports existing WordPress data into Simple History.
 *
 * This class handles importing historical data from WordPress
 * that exists before the plugin was activated, such as:
 * - Posts and pages (creation and modification dates)
 * - Users (registration dates)
 */
class Existing_Data_Importer {
	/** @var Simple_History */
	private $simple_history;

	/** @var array Import results */
	private $results = [];

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
	 * @return array Import results.
	 */
	public function import_all( $options = [] ) {
		$defaults = [
			'post_types' => [ 'post', 'page' ],
			'import_users' => false,
			'limit' => 100,
		];

		$options = wp_parse_args( $options, $defaults );

		$this->results = [
			'posts_imported' => 0,
			'users_imported' => 0,
			'posts_skipped_imported' => 0,
			'posts_skipped_logged' => 0,
			'users_skipped_imported' => 0,
			'users_skipped_logged' => 0,
			'posts_details' => [],
			'users_details' => [],
			'skipped_details' => [],
			'errors' => [],
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
			$logger = $this->simple_history->get_instantiated_logger_by_slug( 'SimpleMediaLogger' );
			$logger_type = 'media';
		} else {
			$logger = $this->simple_history->get_instantiated_logger_by_slug( 'SimplePostLogger' );
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
			'post_type' => $post_type,
			'post_status' => $post_statuses,
			'posts_per_page' => $limit,
			'orderby' => 'date',
			'order' => 'ASC',
		];

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

		$imported_count = 0;
		$skipped_imported_count = 0;
		$skipped_logged_count = 0;

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
				'id' => $post->ID,
				'title' => $post->post_title,
				'type' => $post->post_type,
				'status' => $post->post_status,
				'created_date' => $post_date_gmt,
				'modified_date' => $post_modified_gmt,
				'events_logged' => [],
			];

			$has_created = isset( $already_logged_created[ (string) $post->ID ] );
			$has_updated = isset( $already_logged_updated[ (string) $post->ID ] );
			$post_has_updates = $post_date_gmt !== $post_modified_gmt;

			// Skip if both events already exist (or just created if no updates).
			if ( $has_created && ( ! $post_has_updates || $has_updated ) ) {
				// Determine if this was imported or naturally logged.
				// If created event is imported, count as imported. Otherwise naturally logged.
				$is_imported = $already_logged_created[ (string) $post->ID ]['is_imported'];
				$skip_reason = $is_imported ? 'already_imported' : 'already_logged';

				$this->results['skipped_details'][] = [
					'type' => 'post',
					'id' => $post->ID,
					'title' => $post->post_title,
					'post_type' => $post->post_type,
					'reason' => $skip_reason,
				];

				if ( $is_imported ) {
					$skipped_imported_count++;
				} else {
					$skipped_logged_count++;
				}
				continue;
			}

			// Log post creation if not already imported.
			if ( ! $has_created ) {
				// Get post author for initiator.
				$post_author = get_user_by( 'id', $post->post_author );

				// Media Logger uses different context keys and message.
				if ( 'attachment' === $post_type ) {
					$file = get_attached_file( $post->ID );
					$file_size = false;

					if ( $file && file_exists( $file ) ) {
						$file_size = filesize( $file );
					}

					$context = [
						'attachment_id' => $post->ID,
						'attachment_title' => $post->post_title,
						'attachment_filename' => basename( $file ),
						'attachment_filesize' => $file_size,
						'_date' => $post_date_gmt,
						'_imported_event' => '1',
					];
					$message_key = 'attachment_created';
				} else {
					$context = [
						'post_id' => $post->ID,
						'post_type' => $post->post_type,
						'post_title' => $post->post_title,
						'_date' => $post_date_gmt,
						'_imported_event' => '1',
					];
					$message_key = 'post_created';
				}

				// Set initiator to post author if available.
				if ( $post_author ) {
					$context['_initiator'] = Log_Initiators::WP_USER;
					$context['_user_id'] = $post_author->ID;
					$context['_user_login'] = $post_author->user_login;
					$context['_user_email'] = $post_author->user_email;
				} else {
					$context['_initiator'] = Log_Initiators::OTHER;
				}

				$logger->info_message( $message_key, $context );

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
					$file = get_attached_file( $post->ID );
					$file_size = false;

					if ( $file && file_exists( $file ) ) {
						$file_size = filesize( $file );
					}

					$context = [
						'attachment_id' => $post->ID,
						'attachment_title' => $post->post_title,
						'attachment_filename' => basename( $file ),
						'attachment_filesize' => $file_size,
						'_date' => $post_modified_gmt,
						'_imported_event' => '1',
					];
					$message_key = 'attachment_updated';
				} else {
					$context = [
						'post_id' => $post->ID,
						'post_type' => $post->post_type,
						'post_title' => $post->post_title,
						'_date' => $post_modified_gmt,
						'_imported_event' => '1',
					];
					$message_key = 'post_updated';
				}

				// Set initiator to post author if available.
				if ( $post_author ) {
					$context['_initiator'] = Log_Initiators::WP_USER;
					$context['_user_id'] = $post_author->ID;
					$context['_user_login'] = $post_author->user_login;
					$context['_user_email'] = $post_author->user_email;
				} else {
					$context['_initiator'] = Log_Initiators::OTHER;
				}

				$logger->info_message( $message_key, $context );

				$post_detail['events_logged'][] = [
					'type' => 'updated',
					'date' => $post_modified_gmt,
				];
			}

			// Only add to imported if we logged at least one event.
			if ( ! empty( $post_detail['events_logged'] ) ) {
				$this->results['posts_details'][] = $post_detail;
				$imported_count++;
			}
		}

		$this->results['posts_imported'] += $imported_count;
		$this->results['posts_skipped_imported'] += $skipped_imported_count;
		$this->results['posts_skipped_logged'] += $skipped_logged_count;

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
			'number' => $limit,
			'orderby' => 'registered',
			'order' => 'ASC',
		];

		$users = get_users( $args );

		// Get all user IDs to check for duplicates.
		$user_ids = wp_list_pluck( $users, 'ID' );

		// Check which users have already been logged (imported or naturally).
		$already_logged = $this->get_already_logged_user_ids( $user_ids );

		$imported_count = 0;
		$skipped_imported_count = 0;
		$skipped_logged_count = 0;

		foreach ( $users as $user ) {
			// Skip if already logged (imported or naturally).
			if ( isset( $already_logged[ (string) $user->ID ] ) ) {
				$is_imported = $already_logged[ (string) $user->ID ]['is_imported'];
				$skip_reason = $is_imported ? 'already_imported' : 'already_logged';

				$this->results['skipped_details'][] = [
					'type' => 'user',
					'id' => $user->ID,
					'login' => $user->user_login,
					'reason' => $skip_reason,
				];

				if ( $is_imported ) {
					$skipped_imported_count++;
				} else {
					$skipped_logged_count++;
				}
				continue;
			}

			// Log user registration with original registration date.
			// Only store immutable data (user_id and user_login).
			// Don't store email, names, URL, or role as these may have changed since registration.
			$user_logger->info_message(
				'user_created',
				[
					'created_user_id' => $user->ID,
					'created_user_login' => $user->user_login,
					'_date' => get_date_from_gmt( $user->user_registered ),
					'_initiator' => Log_Initiators::OTHER,
					'_imported_event' => '1',
				]
			);

			$this->results['users_details'][] = [
				'id' => $user->ID,
				'login' => $user->user_login,
				'email' => $user->user_email,
				'registered_date' => $user->user_registered,
				'roles' => (array) $user->roles,
			];

			$imported_count++;
		}

		$this->results['users_imported'] += $imported_count;
		$this->results['users_skipped_imported'] += $skipped_imported_count;
		$this->results['users_skipped_logged'] += $skipped_logged_count;

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
		// Use LEFT JOIN to detect if _imported_event exists (1 = imported, 0 = naturally logged).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare(
			"SELECT DISTINCT
				c1.value as post_id,
				MAX(CASE WHEN c2.key = '_imported_event' THEN 1 ELSE 0 END) as is_imported
			FROM {$contexts_table} c1
			LEFT JOIN {$contexts_table} c2 ON c1.history_id = c2.history_id AND c2.key = '_imported_event'
			INNER JOIN {$contexts_table} c3 ON c1.history_id = c3.history_id
			WHERE c1.key = %s
			  AND c1.value IN (" . implode( ',', array_map( 'intval', $post_ids ) ) . ")
			  AND c3.key = '_message_key'
			  AND c3.value = %s
			GROUP BY c1.value",
			$context_key,
			$message_key
		);

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
		// Use LEFT JOIN to detect if _imported_event exists (1 = imported, 0 = naturally logged).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql = "SELECT DISTINCT
				c1.value as user_id,
				MAX(CASE WHEN c2.key = '_imported_event' THEN 1 ELSE 0 END) as is_imported
			FROM {$contexts_table} c1
			LEFT JOIN {$contexts_table} c2 ON c1.history_id = c2.history_id AND c2.key = '_imported_event'
			INNER JOIN {$contexts_table} c3 ON c1.history_id = c3.history_id
			WHERE c1.key = 'created_user_id'
			  AND c1.value IN (" . implode( ',', array_map( 'intval', $user_ids ) ) . ")
			  AND c3.key = '_message_key'
			  AND c3.value = 'user_created'
			GROUP BY c1.value";

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
			'users' => 0,
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
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->posts}
						WHERE post_type = %s
						AND post_status IN ('inherit', 'private')",
						$post_type->name
					)
				);
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
		$counts['users'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );

		return $counts;
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

		$table_name = $this->simple_history->get_events_table_name();
		$context_table_name = $this->simple_history->get_contexts_table_name();

		// First, get all history IDs that have the _imported_event context.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$history_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT c.history_id
				FROM {$context_table_name} AS c
				WHERE c.key = %s",
				'_imported_event'
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $history_ids ) ) {
			return [
				'events_deleted' => 0,
				'success' => true,
			];
		}

		$placeholders = implode( ',', array_fill( 0, count( $history_ids ), '%d' ) );

		// Delete from contexts table.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$context_table_name}
				WHERE history_id IN ({$placeholders})",
				...$history_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Delete from history table.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted_count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name}
				WHERE id IN ({$placeholders})",
				...$history_ids
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return [
			'events_deleted' => (int) $deleted_count,
			'success' => true,
		];
	}
}
