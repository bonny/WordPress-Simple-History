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
			'posts_details' => [],
			'users_details' => [],
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
	 * @param int    $limit Max number of posts to import.
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
		$imported_count = 0;

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

			// Log post creation with original creation date.
			$post_logger->info_message(
				'post_created',
				[
					'post_id' => $post->ID,
					'post_type' => $post->post_type,
					'post_title' => $post->post_title,
					'_date' => $post->post_date_gmt,
					'_initiator' => Log_Initiators::OTHER,
				]
			);

			$post_detail['events_logged'][] = [
				'type' => 'created',
				'date' => $post->post_date_gmt,
			];

			// If post has been modified after creation, also log an update.
			if ( $post->post_date_gmt !== $post->post_modified_gmt ) {
				$post_logger->info_message(
					'post_updated',
					[
						'post_id' => $post->ID,
						'post_type' => $post->post_type,
						'post_title' => $post->post_title,
						'_date' => $post->post_modified_gmt,
						'_initiator' => Log_Initiators::OTHER,
					]
				);

				$post_detail['events_logged'][] = [
					'type' => 'updated',
					'date' => $post->post_modified_gmt,
				];
			}

			$this->results['posts_details'][] = $post_detail;
			$imported_count++;
		}

		$this->results['posts_imported'] += $imported_count;

		return $imported_count;
	}

	/**
	 * Import users.
	 *
	 * @param int $limit Max number of users to import.
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
		$imported_count = 0;

		foreach ( $users as $user ) {
			// Log user registration with original registration date.
			// Note: We need to check if this message key exists in User_Logger.
			// For now, we'll use a generic info log.
			$user_logger->info(
				sprintf(
					'User "%s" was registered',
					$user->user_login
				),
				[
					'user_id' => $user->ID,
					'user_login' => $user->user_login,
					'user_email' => $user->user_email,
					'_date' => get_date_from_gmt( $user->user_registered ),
					'_initiator' => Log_Initiators::OTHER,
				]
			);

			$this->results['users_details'][] = [
				'id' => $user->ID,
				'login' => $user->user_login,
				'email' => $user->user_email,
				'registered_date' => $user->user_registered,
			];

			$imported_count++;
		}

		$this->results['users_imported'] += $imported_count;

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
}
