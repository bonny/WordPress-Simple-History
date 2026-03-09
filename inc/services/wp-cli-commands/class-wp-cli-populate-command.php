<?php

namespace Simple_History\Services\WP_CLI_Commands;

use WP_CLI;
use WP_CLI_Command;
use Simple_History\Simple_History;
use Simple_History\Log_Initiators;

/**
 * Populate the log with test events for benchmarking and development.
 *
 * Only available when SIMPLE_HISTORY_DEV constant is true.
 */
class WP_CLI_Populate_Command extends WP_CLI_Command {
	/**
	 * Populate the log with test events.
	 *
	 * Generates a large number of realistic events across different loggers
	 * for benchmarking search performance and testing.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<number>]
	 * : Number of events to create.
	 * ---
	 * default: 1000
	 * ---
	 *
	 * [--type=<type>]
	 * : Type of events to generate. 'mixed' creates a realistic mix across loggers.
	 * ---
	 * default: mixed
	 * options:
	 *   - mixed
	 *   - plugins
	 *   - posts
	 *   - users
	 *   - simple
	 *   - large
	 * ---
	 *
	 * [--days=<number>]
	 * : Spread events over this many days into the past. Default 90.
	 * ---
	 * default: 90
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate 1000 mixed events spread over 90 days
	 *     wp simple-history dev populate
	 *
	 *     # Generate 5000 mixed events for heavy benchmarking
	 *     wp simple-history dev populate --count=5000
	 *
	 *     # Generate events spread over 1 year
	 *     wp simple-history dev populate --count=5000 --days=365
	 *
	 *     # Generate 500 plugin events only
	 *     wp simple-history dev populate --count=500 --type=plugins
	 *
	 *     # Generate SimpleLogger events (for testing fallback search)
	 *     wp simple-history dev populate --count=200 --type=simple
	 *
	 *     # Generate large events (2MB context, one per hour for 60 days)
	 *     wp simple-history dev populate --count=1440 --type=large --days=60
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function populate( $args, $assoc_args ) {
		global $wpdb;

		$count = (int) ( $assoc_args['count'] ?? 1000 );
		$type  = $assoc_args['type'] ?? 'mixed';
		$days  = (int) ( $assoc_args['days'] ?? 90 );

		if ( $count < 1 ) {
			WP_CLI::error( 'Count must be at least 1.' );
		}

		$simple_history    = Simple_History::get_instance();
		$events_table_name = $simple_history->get_events_table_name();

		WP_CLI::log(
			sprintf( 'Generating %d %s events spread over %d days...', $count, $type, $days )
		);

		// Get all user IDs to randomly assign as event initiators.
		$user_ids = get_users( [
			'fields' => 'ID',
			'number' => 50,
		] );

		if ( empty( $user_ids ) ) {
			$user_ids = [ 1 ];
		}

		// Pre-generate backdated timestamps with realistic variation.
		$timestamps = $this->generate_realistic_timestamps( $count, $days );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Creating events', $count );

		for ( $i = 0; $i < $count; $i++ ) {
			// Set a random user as the current user so the logger
			// records realistic user context for each event.
			$random_user_id = $user_ids[ wp_rand( 0, count( $user_ids ) - 1 ) ];
			wp_set_current_user( $random_user_id );

			// Get max ID before insert so we can find the newly created event.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$max_id_before = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id), 0) FROM {$events_table_name}" );

			switch ( $type ) {
				case 'plugins':
					$this->create_plugin_event( $simple_history );
					break;
				case 'posts':
					$this->create_post_event( $simple_history );
					break;
				case 'users':
					$this->create_user_event( $simple_history );
					break;
				case 'simple':
					$this->create_simple_event( $simple_history );
					break;
				case 'large':
					$this->create_large_event( $simple_history );
					break;
				case 'mixed':
				default:
					$this->create_mixed_event( $simple_history, $i );
					break;
			}

			// Backdate immediately so events are safe even if the process is interrupted.
			if ( $days > 0 && isset( $timestamps[ $i ] ) ) {
				$backdated = gmdate( 'Y-m-d H:i:s', $timestamps[ $i ] );

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$events_table_name} SET date = %s WHERE id > %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$backdated,
						$max_id_before
					)
				);
			}

			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success(
			sprintf( 'Created %d test events over %d days.', $count, $days )
		);
	}

	/**
	 * Generate realistic timestamps spread over a number of days.
	 *
	 * Simulates real-world patterns:
	 * - Weekdays have more activity than weekends (~3x)
	 * - Each day has random variation (0.5x to 2x of base)
	 * - ~10% of days get a random spike (3x-5x)
	 * - Events within a day are biased toward business hours (8am-6pm)
	 *
	 * @param int $count Total number of timestamps to generate.
	 * @param int $days Number of days to spread over.
	 * @return array<int> Array of Unix timestamps, sorted newest first.
	 */
	private function generate_realistic_timestamps( $count, $days ) {
		if ( $days <= 0 ) {
			return [];
		}

		$now = time();

		// Assign a weight to each day.
		$day_weights = [];
		for ( $d = 0; $d < $days; $d++ ) {
			$day_timestamp = $now - ( $d * DAY_IN_SECONDS );
			$day_of_week   = (int) gmdate( 'N', $day_timestamp );

			// Weekdays (1-5) get base weight 3, weekends get 1.
			$base_weight = $day_of_week <= 5 ? 3.0 : 1.0;

			// Random daily variation: 0.5x to 2.0x.
			$variation = wp_rand( 50, 200 ) / 100;
			$weight    = $base_weight * $variation;

			// ~10% chance of a spike day (3x-5x multiplier).
			if ( wp_rand( 1, 10 ) === 1 ) {
				$weight *= wp_rand( 30, 50 ) / 10;
			}

			$day_weights[ $d ] = $weight;
		}

		// Normalize weights to distribute $count events proportionally.
		$total_weight = array_sum( $day_weights );
		$timestamps   = [];

		foreach ( $day_weights as $d => $weight ) {
			$day_count = (int) round( ( $weight / $total_weight ) * $count );
			$day_start = $now - ( ( $d + 1 ) * DAY_IN_SECONDS );

			for ( $e = 0; $e < $day_count; $e++ ) {
				// Bias toward business hours: 70% chance of 8am-6pm, 30% other hours.
				if ( wp_rand( 1, 10 ) <= 7 ) {
					$hour = wp_rand( 8, 17 );
				} else {
					$hour = wp_rand( 0, 23 );
				}

				$minute = wp_rand( 0, 59 );
				$second = wp_rand( 0, 59 );

				$timestamps[] = $day_start + ( $hour * HOUR_IN_SECONDS ) + ( $minute * MINUTE_IN_SECONDS ) + $second;
			}
		}

		// Adjust count if rounding caused a mismatch.
		while ( count( $timestamps ) < $count ) {
			$timestamps[] = $now - wp_rand( 0, $days * DAY_IN_SECONDS );
		}

		// Sort newest first so event $i=0 gets the most recent timestamp.
		rsort( $timestamps );

		return array_slice( $timestamps, 0, $count );
	}

	/**
	 * Create a mixed event based on weighted distribution.
	 *
	 * Distribution mirrors a typical WordPress site:
	 * ~40% post events, ~25% plugin events, ~15% user events,
	 * ~10% option events, ~10% simple/custom events.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 * @param int            $index Current event index.
	 */
	private function create_mixed_event( $simple_history, $index ) {
		$rand = $index % 20;

		if ( $rand < 8 ) {
			$this->create_post_event( $simple_history );
		} elseif ( $rand < 13 ) {
			$this->create_plugin_event( $simple_history );
		} elseif ( $rand < 16 ) {
			$this->create_user_event( $simple_history );
		} elseif ( $rand < 18 ) {
			$this->create_option_event( $simple_history );
		} else {
			$this->create_simple_event( $simple_history );
		}
	}

	/**
	 * Create a plugin event.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 */
	private function create_plugin_event( $simple_history ) {
		$logger = $simple_history->get_instantiated_logger_by_slug( 'SimplePluginLogger' );

		if ( ! $logger ) {
			return;
		}

		$plugins = [
			[ 'name' => 'WooCommerce', 'slug' => 'woocommerce' ],
			[ 'name' => 'Yoast SEO', 'slug' => 'wordpress-seo' ],
			[ 'name' => 'Contact Form 7', 'slug' => 'contact-form-7' ],
			[ 'name' => 'Akismet Anti-spam', 'slug' => 'akismet' ],
			[ 'name' => 'Elementor', 'slug' => 'elementor' ],
			[ 'name' => 'Wordfence Security', 'slug' => 'wordfence' ],
			[ 'name' => 'Jetpack', 'slug' => 'jetpack' ],
			[ 'name' => 'Simple History Premium', 'slug' => 'simple-history-premium' ],
			[ 'name' => 'Advanced Custom Fields', 'slug' => 'advanced-custom-fields' ],
			[ 'name' => 'WP Super Cache', 'slug' => 'wp-super-cache' ],
		];

		$actions = [
			'plugin_activated',
			'plugin_deactivated',
			'plugin_updated',
			'plugin_installed',
		];

		$plugin = $plugins[ wp_rand( 0, count( $plugins ) - 1 ) ];
		$action = $actions[ wp_rand( 0, count( $actions ) - 1 ) ];

		$context = [
			'_initiator'          => Log_Initiators::WP_USER,
			'plugin_name'         => $plugin['name'],
			'plugin_slug'         => $plugin['slug'],
			'plugin_version'      => wp_rand( 1, 9 ) . '.' . wp_rand( 0, 20 ) . '.' . wp_rand( 0, 10 ),
			'plugin_prev_version' => wp_rand( 1, 9 ) . '.' . wp_rand( 0, 20 ) . '.' . wp_rand( 0, 10 ),
			'plugin_author'       => 'Test Author',
		];

		$logger->info_message( $action, $context );
	}

	/**
	 * Create a post event.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 */
	private function create_post_event( $simple_history ) {
		$logger = $simple_history->get_instantiated_logger_by_slug( 'SimplePostLogger' );

		if ( ! $logger ) {
			return;
		}

		$post_titles = [
			'Getting Started with WordPress',
			'How to Optimize Your Website',
			'10 Tips for Better SEO',
			'Annual Report 2024',
			'Product Launch Announcement',
			'Company Newsletter Q4',
			'Privacy Policy Update',
			'Terms of Service',
			'Contact Us',
			'About Our Team',
			'Summer Sale Campaign',
			'New Feature Release Notes',
			'Customer Success Stories',
			'Developer Documentation',
			'API Integration Guide',
		];

		$actions = [
			'post_updated',
			'post_created',
			'post_trashed',
			'post_restored',
		];

		$post_types = [ 'post', 'page', 'product' ];

		$title     = $post_titles[ wp_rand( 0, count( $post_titles ) - 1 ) ];
		$action    = $actions[ wp_rand( 0, count( $actions ) - 1 ) ];
		$post_type = $post_types[ wp_rand( 0, count( $post_types ) - 1 ) ];

		$context = [
			'_initiator' => Log_Initiators::WP_USER,
			'post_id'    => wp_rand( 1, 9999 ),
			'post_type'  => $post_type,
			'post_title' => $title,
		];

		if ( $action === 'post_updated' ) {
			$context['post_prev_post_title'] = $title;
			$context['post_new_post_title']  = $title . ' (Revised)';
			$context['post_prev_status']     = 'publish';
			$context['post_new_status']      = 'publish';
		}

		if ( $action === 'post_created' ) {
			$context['post_new_status']  = 'publish';
			$context['post_prev_status'] = 'auto-draft';
		}

		$logger->info_message( $action, $context );
	}

	/**
	 * Create a user event.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 */
	private function create_user_event( $simple_history ) {
		$logger = $simple_history->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

		if ( ! $logger ) {
			return;
		}

		$users = [
			[ 'login' => 'johndoe', 'email' => 'john@example.com', 'name' => 'John Doe' ],
			[ 'login' => 'janedoe', 'email' => 'jane@example.com', 'name' => 'Jane Doe' ],
			[ 'login' => 'admin', 'email' => 'admin@example.com', 'name' => 'Admin User' ],
			[ 'login' => 'editor1', 'email' => 'editor@example.com', 'name' => 'Sarah Editor' ],
			[ 'login' => 'author1', 'email' => 'author@example.com', 'name' => 'Mike Author' ],
			[ 'login' => 'subscriber1', 'email' => 'subscriber@example.com', 'name' => 'Tom Subscriber' ],
		];

		$actions = [
			'user_logged_in',
			'user_logged_out',
			'user_updated_profile',
			'user_created',
		];

		$user   = $users[ wp_rand( 0, count( $users ) - 1 ) ];
		$action = $actions[ wp_rand( 0, count( $actions ) - 1 ) ];
		$role   = [ 'administrator', 'editor', 'author', 'subscriber' ][ wp_rand( 0, 3 ) ];

		// Context keys must match the placeholders in logger message templates.
		// "Logged in" and "Logged out" have no placeholders.
		// "Edited the profile for user "{edited_user_login}" ({edited_user_email})".
		// "Created user {created_user_login} ({created_user_email}) with role {created_user_role}".
		$context = [
			'_initiator' => Log_Initiators::WP_USER,
		];

		if ( $action === 'user_updated_profile' ) {
			$context['edited_user_id']    = wp_rand( 1, 100 );
			$context['edited_user_login'] = $user['login'];
			$context['edited_user_email'] = $user['email'];
		} elseif ( $action === 'user_created' ) {
			$context['created_user_id']    = wp_rand( 1, 100 );
			$context['created_user_login'] = $user['login'];
			$context['created_user_email'] = $user['email'];
			$context['created_user_role']  = $role;
		}

		$logger->info_message( $action, $context );
	}

	/**
	 * Create an options event.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 */
	private function create_option_event( $simple_history ) {
		$logger = $simple_history->get_instantiated_logger_by_slug( 'SimpleOptionsLogger' );

		if ( ! $logger ) {
			return;
		}

		// Template: 'Updated setting "{option}" on the "{option_page}" settings page'.
		$options = [
			[ 'option' => 'blogname', 'page' => 'general' ],
			[ 'option' => 'blogdescription', 'page' => 'general' ],
			[ 'option' => 'admin_email', 'page' => 'general' ],
			[ 'option' => 'default_role', 'page' => 'general' ],
			[ 'option' => 'posts_per_page', 'page' => 'reading' ],
			[ 'option' => 'timezone_string', 'page' => 'general' ],
			[ 'option' => 'date_format', 'page' => 'general' ],
			[ 'option' => 'permalink_structure', 'page' => 'permalink' ],
		];

		$entry = $options[ wp_rand( 0, count( $options ) - 1 ) ];

		$context = [
			'_initiator'  => Log_Initiators::WP_USER,
			'option'      => $entry['option'],
			'option_page' => $entry['page'],
			'old_value'   => 'old_value_' . wp_rand( 1, 100 ),
			'new_value'   => 'new_value_' . wp_rand( 1, 100 ),
		];

		$logger->info_message( 'option_updated', $context );
	}

	/**
	 * Create a SimpleLogger event with ad-hoc placeholders.
	 *
	 * These events use raw message text and context, simulating
	 * third-party code using do_action('simple_history_log', ...).
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 */
	private function create_simple_event( $simple_history ) {
		$logger = $simple_history->get_instantiated_logger_by_slug( 'SimpleLogger' );

		if ( ! $logger ) {
			return;
		}

		$messages = [
			[
				'message' => 'Backup completed for site "{site_name}"',
				'context' => [
					'site_name'   => 'example.com',
					'backup_size' => wp_rand( 100, 9999 ) . 'MB',
					'duration'    => wp_rand( 10, 300 ) . 's',
				],
			],
			[
				'message' => 'Cron job "{job_name}" executed successfully',
				'context' => [
					'job_name'  => [ 'daily_cleanup', 'email_digest', 'cache_purge', 'sync_inventory' ][ wp_rand( 0, 3 ) ],
					'runtime'   => wp_rand( 1, 60 ) . 's',
				],
			],
			[
				'message' => 'API request to {endpoint} returned {status_code}',
				'context' => [
					'endpoint'    => [ '/api/orders', '/api/products', '/api/users', '/api/webhooks' ][ wp_rand( 0, 3 ) ],
					'status_code' => [ '200', '201', '400', '500' ][ wp_rand( 0, 3 ) ],
				],
			],
			[
				'message' => 'Form submission received from {form_name}',
				'context' => [
					'form_name' => [ 'Contact Form', 'Newsletter Signup', 'Support Request', 'Job Application' ][ wp_rand( 0, 3 ) ],
					'email'     => 'user' . wp_rand( 1, 999 ) . '@example.com',
				],
			],
			[
				'message' => 'Custom event logged for debugging purposes',
				'context' => [
					'debug_data' => 'Some debug information ' . wp_rand( 1, 9999 ),
				],
			],
		];

		$entry = $messages[ wp_rand( 0, count( $messages ) - 1 ) ];

		$entry['context']['_initiator'] = Log_Initiators::WP_USER;

		$logger->info( $entry['message'], $entry['context'] );
	}

	/**
	 * Create a large event simulating a REST API response being logged.
	 *
	 * Generates ~2MB of JSON-like context data, mimicking real-world cases
	 * where plugins log full API responses.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 */
	private function create_large_event( $simple_history ) {
		$logger = $simple_history->get_instantiated_logger_by_slug( 'SimpleLogger' );

		if ( ! $logger ) {
			return;
		}

		$endpoints = [
			'/wp-json/wc/v3/orders',
			'/wp-json/wc/v3/products',
			'/wp-json/wp/v2/posts',
			'/wp-json/custom/v1/sync',
		];

		$endpoint    = $endpoints[ wp_rand( 0, count( $endpoints ) - 1 ) ];
		$status_code = [ 200, 201, 400, 500 ][ wp_rand( 0, 3 ) ];

		// Build ~2MB of realistic JSON response data.
		$items = [];
		for ( $i = 0; $i < 700; $i++ ) {
			$items[] = [
				'id'          => wp_rand( 1000, 99999 ),
				'name'        => 'Product Item #' . wp_rand( 1, 9999 ),
				'sku'         => 'SKU-' . wp_rand( 10000, 99999 ),
				'price'       => number_format( wp_rand( 100, 99999 ) / 100, 2 ),
				'description' => str_repeat( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. ', wp_rand( 5, 30 ) ),
				'categories'  => [ 'Category ' . wp_rand( 1, 20 ), 'Category ' . wp_rand( 1, 20 ) ],
				'tags'        => [ 'tag-' . wp_rand( 1, 50 ), 'tag-' . wp_rand( 1, 50 ), 'tag-' . wp_rand( 1, 50 ) ],
				'meta_data'   => [
					[ 'key' => '_stock_status', 'value' => [ 'instock', 'outofstock', 'onbackorder' ][ wp_rand( 0, 2 ) ] ],
					[ 'key' => '_weight', 'value' => (string) wp_rand( 1, 500 ) ],
					[ 'key' => '_dimensions', 'value' => wp_rand( 10, 100 ) . 'x' . wp_rand( 10, 100 ) . 'x' . wp_rand( 10, 100 ) ],
					[ 'key' => '_custom_field_' . wp_rand( 1, 10 ), 'value' => str_repeat( 'data', wp_rand( 50, 200 ) ) ],
				],
			];
		}

		$response_body = wp_json_encode( $items );

		$logger->info(
			'REST API response from {endpoint} (HTTP {status_code})',
			[
				'_initiator'    => Log_Initiators::WP_USER,
				'endpoint'      => $endpoint,
				'status_code'   => (string) $status_code,
				'response_body' => $response_body,
				'response_size' => strlen( $response_body ),
			]
		);
	}
}
