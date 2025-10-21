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
			'posts_skipped' => 0,
			'users_skipped' => 0,
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
		$post_logger = $this->simple_history->get_instantiated_logger_by_slug( 'SimplePostLogger' );

		if ( ! $post_logger ) {
			$this->results['errors'][] = 'Post logger not found';
			return 0;
		}

		// Get posts, ordered by date (oldest first for chronological import).
		$args = [
			'post_type' => $post_type,
			'post_status' => [ 'publish', 'draft', 'pending', 'private' ],
			'posts_per_page' => $limit,
			'orderby' => 'date',
			'order' => 'ASC',
		];

		$posts = get_posts( $args );

		// Get all post IDs to check for duplicates.
		$post_ids = wp_list_pluck( $posts, 'ID' );

		// Check which posts have already been imported.
		$already_imported_created = $this->get_already_imported_post_ids( $post_ids, 'post_created' );
		$already_imported_updated = $this->get_already_imported_post_ids( $post_ids, 'post_updated' );

		$imported_count = 0;
		$skipped_count = 0;

		foreach ( $posts as $post ) {
			$post_detail = [
				'id' => $post->ID,
				'title' => $post->post_title,
				'type' => $post->post_type,
				'status' => $post->post_status,
				'created_date' => $post->post_date_gmt,
				'modified_date' => $post->post_modified_gmt,
				'events_logged' => [],
			];

			$has_created = in_array( (string) $post->ID, $already_imported_created, true );
			$has_updated = in_array( (string) $post->ID, $already_imported_updated, true );
			$post_has_updates = $post->post_date_gmt !== $post->post_modified_gmt;

			// Skip if both events already exist (or just created if no updates).
			if ( $has_created && ( ! $post_has_updates || $has_updated ) ) {
				$this->results['skipped_details'][] = [
					'type' => 'post',
					'id' => $post->ID,
					'title' => $post->post_title,
					'post_type' => $post->post_type,
					'reason' => 'already_imported',
				];
				$skipped_count++;
				continue;
			}

			// Log post creation if not already imported.
			if ( ! $has_created ) {
				// Get post author for initiator.
				$post_author = get_user_by( 'id', $post->post_author );

				$context = [
					'post_id' => $post->ID,
					'post_type' => $post->post_type,
					'post_title' => $post->post_title,
					'_date' => $post->post_date_gmt,
					'_imported_event' => true,
				];

				// Set initiator to post author if available.
				if ( $post_author ) {
					$context['_initiator'] = Log_Initiators::WP_USER;
					$context['_user_id'] = $post_author->ID;
					$context['_user_login'] = $post_author->user_login;
					$context['_user_email'] = $post_author->user_email;
				} else {
					$context['_initiator'] = Log_Initiators::OTHER;
				}

				$post_logger->info_message( 'post_created', $context );

				$post_detail['events_logged'][] = [
					'type' => 'created',
					'date' => $post->post_date_gmt,
				];
			}

			// If post has been modified after creation, also log an update (if not already imported).
			if ( $post_has_updates && ! $has_updated ) {
				// Get post author for initiator.
				$post_author = get_user_by( 'id', $post->post_author );

				$context = [
					'post_id' => $post->ID,
					'post_type' => $post->post_type,
					'post_title' => $post->post_title,
					'_date' => $post->post_modified_gmt,
					'_imported_event' => true,
				];

				// Set initiator to post author if available.
				if ( $post_author ) {
					$context['_initiator'] = Log_Initiators::WP_USER;
					$context['_user_id'] = $post_author->ID;
					$context['_user_login'] = $post_author->user_login;
					$context['_user_email'] = $post_author->user_email;
				} else {
					$context['_initiator'] = Log_Initiators::OTHER;
				}

				$post_logger->info_message( 'post_updated', $context );

				$post_detail['events_logged'][] = [
					'type' => 'updated',
					'date' => $post->post_modified_gmt,
				];
			}

			// Only add to imported if we logged at least one event.
			if ( ! empty( $post_detail['events_logged'] ) ) {
				$this->results['posts_details'][] = $post_detail;
				$imported_count++;
			}
		}

		$this->results['posts_imported'] += $imported_count;
		$this->results['posts_skipped'] += $skipped_count;

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

		// Check which users have already been imported.
		$already_imported = $this->get_already_imported_user_ids( $user_ids );

		$imported_count = 0;
		$skipped_count = 0;

		foreach ( $users as $user ) {
			// Skip if already imported.
			if ( in_array( (string) $user->ID, $already_imported, true ) ) {
				$this->results['skipped_details'][] = [
					'type' => 'user',
					'id' => $user->ID,
					'login' => $user->user_login,
					'reason' => 'already_imported',
				];
				$skipped_count++;
				continue;
			}

			// Log user registration with original registration date.
			// Use the same message key and context format as User_Logger.
			$user_logger->info_message(
				'user_created',
				[
					'created_user_id' => $user->ID,
					'created_user_email' => $user->user_email,
					'created_user_login' => $user->user_login,
					'created_user_first_name' => $user->first_name,
					'created_user_last_name' => $user->last_name,
					'created_user_url' => $user->user_url,
					'created_user_role' => implode( ', ', (array) $user->roles ),
					'_date' => get_date_from_gmt( $user->user_registered ),
					'_initiator' => Log_Initiators::OTHER,
					'_imported_event' => true,
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
		$this->results['users_skipped'] += $skipped_count;

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
	 * Get post IDs that have already been imported.
	 *
	 * @param array  $post_ids Post IDs to check.
	 * @param string $message_key Message key (post_created or post_updated).
	 * @return array Post IDs that were already imported.
	 */
	private function get_already_imported_post_ids( $post_ids, $message_key ) {
		global $wpdb;

		if ( empty( $post_ids ) ) {
			return [];
		}

		$contexts_table = $this->simple_history->get_contexts_table_name();

		// Find events with:
		// - _imported_event = true (imported marker).
		// - post_id in our list.
		// - _message_key = post_created or post_updated.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare(
			"SELECT DISTINCT c1.value as post_id
			FROM {$contexts_table} c1
			INNER JOIN {$contexts_table} c2 ON c1.history_id = c2.history_id
			INNER JOIN {$contexts_table} c3 ON c1.history_id = c3.history_id
			WHERE c1.key = 'post_id'
			  AND c1.value IN (" . implode( ',', array_map( 'intval', $post_ids ) ) . ")
			  AND c2.key = '_imported_event'
			  AND c2.value = 'true'
			  AND c3.key = '_message_key'
			  AND c3.value = %s",
			$message_key
		);

		return $wpdb->get_col( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get user IDs that have already been imported.
	 *
	 * @param array $user_ids User IDs to check.
	 * @return array User IDs that were already imported.
	 */
	private function get_already_imported_user_ids( $user_ids ) {
		global $wpdb;

		if ( empty( $user_ids ) ) {
			return [];
		}

		$contexts_table = $this->simple_history->get_contexts_table_name();

		// Find events with:
		// - _imported_event = true (imported marker).
		// - created_user_id in our list.
		// - _message_key = user_created.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$sql = "SELECT DISTINCT c1.value as user_id
			FROM {$contexts_table} c1
			INNER JOIN {$contexts_table} c2 ON c1.history_id = c2.history_id
			INNER JOIN {$contexts_table} c3 ON c1.history_id = c3.history_id
			WHERE c1.key = 'created_user_id'
			  AND c1.value IN (" . implode( ',', array_map( 'intval', $user_ids ) ) . ")
			  AND c2.key = '_imported_event'
			  AND c2.value = 'true'
			  AND c3.key = '_message_key'
			  AND c3.value = 'user_created'";

		return $wpdb->get_col( $sql );
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

		// Count posts for each post type using a single query.
		foreach ( $post_types as $post_type ) {
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->posts}
					WHERE post_type = %s
					AND post_status IN ('publish', 'draft', 'pending', 'private')",
					$post_type->name
				)
			);

			$counts['post_types'][ $post_type->name ] = (int) $count;
		}

		// Count users.
		$counts['users'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );

		return $counts;
	}
}
