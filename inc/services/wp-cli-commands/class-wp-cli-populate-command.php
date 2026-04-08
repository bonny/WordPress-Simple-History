<?php

namespace Simple_History\Services\WP_CLI_Commands;

use WP_CLI;
use WP_CLI_Command;
use Simple_History\Simple_History;
use Simple_History\Log_Initiators;
use Simple_History\Event;

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
	 *   - showcase
	 * ---
	 *
	 * [--days=<number>]
	 * : Spread events over this many days into the past. Default 90.
	 * ---
	 * default: 90
	 * ---
	 *
	 * [--reactions]
	 * : Add 1-10 random reactions to each event.
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
	 *     # Generate a curated set of specific events for UI testing
	 *     wp simple-history dev populate --type=showcase
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function populate( $args, $assoc_args ) {
		global $wpdb;

		$count         = (int) ( $assoc_args['count'] ?? 1000 );
		$type          = $assoc_args['type'] ?? 'mixed';
		$days          = (int) ( $assoc_args['days'] ?? 90 );
		$add_reactions = \WP_CLI\Utils\get_flag_value( $assoc_args, 'reactions', false );

		if ( $count < 1 ) {
			WP_CLI::error( 'Count must be at least 1.' );
		}

		$simple_history    = Simple_History::get_instance();
		$events_table_name = $simple_history->get_events_table_name();

		// Showcase creates a curated set of specific events, ignoring --count.
		if ( $type === 'showcase' ) {
			$this->create_showcase_events( $simple_history, $add_reactions );
			return;
		}

		WP_CLI::log(
			sprintf( 'Generating %d %s events spread over %d days...', $count, $type, $days )
		);

		// Get all user IDs to randomly assign as event initiators.
		$user_ids = get_users(
			[
				'fields' => 'ID',
				'number' => 50,
			] 
		);

		if ( empty( $user_ids ) ) {
			$user_ids = [ 1 ];
		}

		// Pre-generate backdated timestamps with realistic variation.
		$timestamps = $this->generate_realistic_timestamps( $count, $days );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Creating events', $count );

		for ( $i = 0; $i < $count; $i++ ) {
			// Pick a random initiator for this event.
			$initiator = $this->get_random_initiator();

			// Set user context based on initiator type.
			if ( $initiator === Log_Initiators::WP_USER ) {
				$random_user_id = $user_ids[ wp_rand( 0, count( $user_ids ) - 1 ) ];
				wp_set_current_user( $random_user_id );
			} else {
				wp_set_current_user( 0 );
			}

			// Get max ID before insert so we can find the newly created event.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$max_id_before = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id), 0) FROM {$events_table_name}" );

			switch ( $type ) {
				case 'plugins':
					$this->create_plugin_event( $simple_history, $initiator );
					break;
				case 'posts':
					$this->create_post_event( $simple_history, $initiator );
					break;
				case 'users':
					$this->create_user_event( $simple_history, $initiator );
					break;
				case 'simple':
					$this->create_simple_event( $simple_history, $initiator );
					break;
				case 'large':
					$this->create_large_event( $simple_history, $initiator );
					break;
				case 'showcase':
					// Showcase creates its own curated set, handled before the loop.
					break;
				case 'mixed':
				default:
					$this->create_mixed_event( $simple_history, $i, $initiator );
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

			// Add random reactions to the newly created event.
			if ( $add_reactions ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$new_event_id = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id), 0) FROM {$events_table_name}" );
				if ( $new_event_id > $max_id_before ) {
					$this->add_random_reactions( $new_event_id, $user_ids );
				}
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
			$day_count = (int) round( $weight / $total_weight * $count );
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
		$timestamp_count = count( $timestamps );
		while ( $timestamp_count < $count ) {
			$timestamps[] = $now - wp_rand( 0, $days * DAY_IN_SECONDS );
			++$timestamp_count;
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
	 * @param string         $initiator Log initiator constant.
	 */
	private function create_mixed_event( $simple_history, $index, $initiator ) {
		$rand = $index % 20;

		if ( $rand < 8 ) {
			$this->create_post_event( $simple_history, $initiator );
		} elseif ( $rand < 13 ) {
			$this->create_plugin_event( $simple_history, $initiator );
		} elseif ( $rand < 16 ) {
			$this->create_user_event( $simple_history, $initiator );
		} elseif ( $rand < 18 ) {
			$this->create_option_event( $simple_history, $initiator );
		} else {
			$this->create_simple_event( $simple_history, $initiator );
		}
	}

	/**
	 * Create a plugin event.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 * @param string         $initiator Log initiator constant.
	 */
	private function create_plugin_event( $simple_history, $initiator ) {
		$logger = $simple_history->get_instantiated_logger_by_slug( 'SimplePluginLogger' );

		if ( ! $logger ) {
			return;
		}

		$plugins = [
			[
				'name' => 'WooCommerce',
				'slug' => 'woocommerce',
			],
			[
				'name' => 'Yoast SEO',
				'slug' => 'wordpress-seo',
			],
			[
				'name' => 'Contact Form 7',
				'slug' => 'contact-form-7',
			],
			[
				'name' => 'Akismet Anti-spam',
				'slug' => 'akismet',
			],
			[
				'name' => 'Elementor',
				'slug' => 'elementor',
			],
			[
				'name' => 'Wordfence Security',
				'slug' => 'wordfence',
			],
			[
				'name' => 'Jetpack',
				'slug' => 'jetpack',
			],
			[
				'name' => 'Simple History Premium',
				'slug' => 'simple-history-premium',
			],
			[
				'name' => 'Advanced Custom Fields',
				'slug' => 'advanced-custom-fields',
			],
			[
				'name' => 'WP Super Cache',
				'slug' => 'wp-super-cache',
			],
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
			'_initiator'          => $initiator,
			'plugin_name'         => $plugin['name'],
			'plugin_slug'         => $plugin['slug'],
			'plugin_version'      => wp_rand( 1, 9 ) . '.' . wp_rand( 0, 20 ) . '.' . wp_rand( 0, 10 ),
			'plugin_prev_version' => wp_rand( 1, 9 ) . '.' . wp_rand( 0, 20 ) . '.' . wp_rand( 0, 10 ),
			'plugin_author'       => 'Test Author',
		];

		$context = $this->maybe_add_ip_address( $context );
		$logger->info_message( $action, $context );
	}

	/**
	 * Create a post event.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 * @param string         $initiator Log initiator constant.
	 */
	private function create_post_event( $simple_history, $initiator ) {
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
			'_initiator' => $initiator,
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

		$context = $this->maybe_add_ip_address( $context );
		$logger->info_message( $action, $context );
	}

	/**
	 * Create a user event.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 * @param string         $initiator Log initiator constant.
	 */
	private function create_user_event( $simple_history, $initiator ) {
		$logger = $simple_history->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

		if ( ! $logger ) {
			return;
		}

		$users = [
			[
				'login' => 'johndoe',
				'email' => 'john@example.com',
				'name'  => 'John Doe',
			],
			[
				'login' => 'janedoe',
				'email' => 'jane@example.com',
				'name'  => 'Jane Doe',
			],
			[
				'login' => 'admin',
				'email' => 'admin@example.com',
				'name'  => 'Admin User',
			],
			[
				'login' => 'editor1',
				'email' => 'editor@example.com',
				'name'  => 'Sarah Editor',
			],
			[
				'login' => 'author1',
				'email' => 'author@example.com',
				'name'  => 'Mike Author',
			],
			[
				'login' => 'subscriber1',
				'email' => 'subscriber@example.com',
				'name'  => 'Tom Subscriber',
			],
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
			'_initiator' => $initiator,
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

		$context = $this->maybe_add_ip_address( $context );
		$logger->info_message( $action, $context );
	}

	/**
	 * Create an options event.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 * @param string         $initiator Log initiator constant.
	 */
	private function create_option_event( $simple_history, $initiator ) {
		$logger = $simple_history->get_instantiated_logger_by_slug( 'SimpleOptionsLogger' );

		if ( ! $logger ) {
			return;
		}

		// Template: 'Updated setting "{option}" on the "{option_page}" settings page'.
		$options = [
			[
				'option' => 'blogname',
				'page'   => 'general',
			],
			[
				'option' => 'blogdescription',
				'page'   => 'general',
			],
			[
				'option' => 'admin_email',
				'page'   => 'general',
			],
			[
				'option' => 'default_role',
				'page'   => 'general',
			],
			[
				'option' => 'posts_per_page',
				'page'   => 'reading',
			],
			[
				'option' => 'timezone_string',
				'page'   => 'general',
			],
			[
				'option' => 'date_format',
				'page'   => 'general',
			],
			[
				'option' => 'permalink_structure',
				'page'   => 'permalink',
			],
		];

		$entry = $options[ wp_rand( 0, count( $options ) - 1 ) ];

		$context = [
			'_initiator'  => $initiator,
			'option'      => $entry['option'],
			'option_page' => $entry['page'],
			'old_value'   => 'old_value_' . wp_rand( 1, 100 ),
			'new_value'   => 'new_value_' . wp_rand( 1, 100 ),
		];

		$context = $this->maybe_add_ip_address( $context );
		$logger->info_message( 'option_updated', $context );
	}

	/**
	 * Create a SimpleLogger event with ad-hoc placeholders.
	 *
	 * These events use raw message text and context, simulating
	 * third-party code using do_action('simple_history_log', ...).
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 * @param string         $initiator Log initiator constant.
	 */
	private function create_simple_event( $simple_history, $initiator ) {
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
					'job_name' => [ 'daily_cleanup', 'email_digest', 'cache_purge', 'sync_inventory' ][ wp_rand( 0, 3 ) ],
					'runtime'  => wp_rand( 1, 60 ) . 's',
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

		$entry['context']['_initiator'] = $initiator;
		$entry['context']               = $this->maybe_add_ip_address( $entry['context'] );

		$logger->info( $entry['message'], $entry['context'] );
	}

	/**
	 * Create a large event simulating a REST API response being logged.
	 *
	 * Generates ~2MB of JSON-like context data, mimicking real-world cases
	 * where plugins log full API responses.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 * @param string         $initiator Log initiator constant.
	 */
	private function create_large_event( $simple_history, $initiator ) {
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
					[
						'key'   => '_stock_status',
						'value' => [ 'instock', 'outofstock', 'onbackorder' ][ wp_rand( 0, 2 ) ],
					],
					[
						'key'   => '_weight',
						'value' => (string) wp_rand( 1, 500 ),
					],
					[
						'key'   => '_dimensions',
						'value' => wp_rand( 10, 100 ) . 'x' . wp_rand( 10, 100 ) . 'x' . wp_rand( 10, 100 ),
					],
					[
						'key'   => '_custom_field_' . wp_rand( 1, 10 ),
						'value' => str_repeat( 'data', wp_rand( 50, 200 ) ),
					],
				],
			];
		}

		$response_body = wp_json_encode( $items );

		$context = [
			'_initiator'    => $initiator,
			'endpoint'      => $endpoint,
			'status_code'   => (string) $status_code,
			'response_body' => $response_body,
			'response_size' => strlen( $response_body ),
		];

		$context = $this->maybe_add_ip_address( $context );
		$logger->info( 'REST API response from {endpoint} (HTTP {status_code})', $context );
	}

	/**
	 * Get a random initiator with weighted distribution.
	 *
	 * Distribution:
	 * ~60% WP_USER, ~15% WP_CLI, ~15% WordPress, ~5% WEB_USER, ~5% OTHER.
	 *
	 * @return string Log initiator constant.
	 */
	private function get_random_initiator() {
		$rand = wp_rand( 1, 100 );

		if ( $rand <= 60 ) {
			return Log_Initiators::WP_USER;
		}

		if ( $rand <= 75 ) {
			return Log_Initiators::WP_CLI;
		}

		if ( $rand <= 90 ) {
			return Log_Initiators::WORDPRESS;
		}

		if ( $rand <= 95 ) {
			return Log_Initiators::WEB_USER;
		}

		return Log_Initiators::OTHER;
	}

	/**
	 * Maybe add a public IP address to the event context.
	 *
	 * ~40% of events get an IP address from a pool of known public IPs
	 * representing different geolocations and providers.
	 *
	 * @param array $context Event context array.
	 * @return array Modified context with optional IP address.
	 */
	private function maybe_add_ip_address( $context ) {
		// ~40% of events get an IP.
		if ( wp_rand( 1, 100 ) > 40 ) {
			return $context;
		}

		$ip_addresses = [
			// Google DNS (US).
			'8.8.8.8',
			'8.8.4.4',
			// Cloudflare DNS (US).
			'1.1.1.1',
			'1.0.0.1',
			// European IPs.
			'81.2.69.142',    // London, UK.
			'77.111.247.18',  // Stockholm, Sweden.
			'185.86.151.11',  // Amsterdam, Netherlands.
			'91.198.174.192', // Wikimedia, Netherlands.
			// Asian IPs.
			'203.0.113.50',   // APNIC test range.
			'1.34.56.78',     // Taiwan.
			// South American IPs.
			'200.160.2.3',    // Brazil.
			// Common bot/crawler IPs.
			'66.249.81.222',  // Googlebot.
			'40.77.167.23',   // Bingbot.
			'17.58.98.180',   // Apple.
		];

		$context['_server_remote_addr'] = $ip_addresses[ wp_rand( 0, count( $ip_addresses ) - 1 ) ];

		// ~20% of IP events also get an X-Forwarded-For header.
		if ( wp_rand( 1, 5 ) === 1 ) {
			$proxy_ip                                  = $ip_addresses[ wp_rand( 0, count( $ip_addresses ) - 1 ) ];
			$context['_server_http_x_forwarded_for_0'] = $proxy_ip;
		}

		return $context;
	}

	/**
	 * Add random reactions to an event.
	 *
	 * Adds 1-10 reactions from random users using random reaction types.
	 *
	 * @param int   $event_id Event ID.
	 * @param array $user_ids Available user IDs.
	 */
	private function add_random_reactions( $event_id, $user_ids ) {
		$reaction_types = [ 'thumbsup', 'heart', 'smile', 'tada', 'eyes', 'rocket', 'clap', 'fire' ];
		$reaction_count = wp_rand( 1, 10 );

		$event = new Event( $event_id );

		for ( $r = 0; $r < $reaction_count; $r++ ) {
			$type    = $reaction_types[ wp_rand( 0, count( $reaction_types ) - 1 ) ];
			$user_id = (int) $user_ids[ wp_rand( 0, count( $user_ids ) - 1 ) ];
			$event->add_reaction( $type, $user_id );
		}
	}

	/**
	 * Create a curated set of specific events for UI testing.
	 *
	 * Unlike other types, showcase ignores --count and creates
	 * a fixed set of hand-picked events that cover common scenarios.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 * @param bool          $add_reactions  Whether to add random reactions.
	 */
	private function create_showcase_events( $simple_history, $add_reactions = false ) {
		global $wpdb;
		$user_logger   = $simple_history->get_instantiated_logger_by_slug( 'SimpleUserLogger' );
		$plugin_logger = $simple_history->get_instantiated_logger_by_slug( 'SimplePluginLogger' );
		$post_logger   = $simple_history->get_instantiated_logger_by_slug( 'SimplePostLogger' );

		wp_set_current_user( 1 );
		$events_created = 0;

		// 1. Successful login.
		if ( $user_logger ) {
			$user_logger->info_message(
				'user_logged_in',
				[
					'_initiator'             => Log_Initiators::WP_USER,
					'_server_remote_addr'    => '192.168.1.42',
					'server_http_user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
				]
			);
			++$events_created;
			WP_CLI::log( 'Created: successful login' );
		}

		// 2. Failed login (known user, wrong password).
		if ( $user_logger ) {
			$user_logger->warning_message(
				'user_login_failed',
				[
					'_initiator'             => Log_Initiators::WEB_USER,
					'login'                  => 'admin',
					'server_http_user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
					'_server_remote_addr'    => '203.0.113.50',
					'_occasionsID'           => 'SimpleUserLogger/failed_user_login',
				]
			);
			++$events_created;
			WP_CLI::log( 'Created: failed login (known user)' );
		}

		// 3. Failed login (unknown user).
		if ( $user_logger ) {
			$user_logger->warning_message(
				'user_unknown_login_failed',
				[
					'_initiator'             => Log_Initiators::WEB_USER,
					'failed_username'        => 'hacker123',
					'server_http_user_agent' => 'python-requests/2.28.0',
					'_server_remote_addr'    => '45.33.32.156',
					'_occasionsID'           => 'SimpleUserLogger/failed_user_login',
				]
			);
			++$events_created;
			WP_CLI::log( 'Created: failed login (unknown user)' );
		}

		// 4. Updated plugin (with prev and new version context).
		if ( $plugin_logger ) {
			$plugin_logger->info_message(
				'plugin_updated',
				[
					'_initiator'          => Log_Initiators::WP_USER,
					'plugin_name'         => 'WooCommerce',
					'plugin_slug'         => 'woocommerce',
					'plugin_version'      => '9.5.1',
					'plugin_prev_version' => '9.4.3',
					'plugin_author'       => 'Automattic',
					'plugin_url'          => 'https://woocommerce.com/',
				]
			);
			++$events_created;
			WP_CLI::log( 'Created: plugin updated (WooCommerce 9.4.3 → 9.5.1)' );
		}

		// 5. Installed plugin (with full plugin details).
		if ( $plugin_logger ) {
			$plugin_logger->info_message(
				'plugin_installed',
				[
					'_initiator'          => Log_Initiators::WP_USER,
					'plugin_name'         => 'Query Monitor',
					'plugin_slug'         => 'query-monitor',
					'plugin_version'      => '3.16.4',
					'plugin_author'       => 'John Blackbourn',
					'plugin_url'          => 'https://querymonitor.com/',
					'plugin_description'  => 'The developer tools panel for WordPress.',
				]
			);
			++$events_created;
			WP_CLI::log( 'Created: plugin installed (Query Monitor)' );
		}

		// 6. Updated page with content diff context.
		if ( $post_logger ) {
			$post_logger->info_message(
				'post_updated',
				[
					'_initiator'                 => Log_Initiators::WP_USER,
					'post_id'                    => 2,
					'post_type'                  => 'page',
					'post_title'                 => 'About Us',
					'post_prev_post_title'       => 'About Us',
					'post_new_post_title'        => 'About Us',
					'post_prev_status'           => 'publish',
					'post_new_status'            => 'publish',
					'post_prev_post_content'     => "<!-- wp:paragraph -->\n<p>We are a small team of passionate developers building tools for the WordPress community.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Founded in 2018, our mission is to make WordPress better for everyone.</p>\n<!-- /wp:paragraph -->",
					'post_new_post_content'      => "<!-- wp:paragraph -->\n<p>We are a growing team of passionate developers building tools for the WordPress community.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Founded in 2018, our mission is to make WordPress better for everyone.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>We now serve over 100,000 active users worldwide.</p>\n<!-- /wp:paragraph -->",
					'post_prev_post_excerpt'     => '',
					'post_new_post_excerpt'       => 'Learn about our team and mission.',
				]
			);
			++$events_created;
			WP_CLI::log( 'Created: page updated with content diff (About Us)' );
		}

		// 7. Created blog post.
		if ( $post_logger ) {
			$post_logger->info_message(
				'post_created',
				[
					'_initiator'      => Log_Initiators::WP_USER,
					'post_id'         => wp_rand( 100, 9999 ),
					'post_type'       => 'post',
					'post_title'      => 'Announcing Our New Features for 2026',
					'post_new_status' => 'publish',
					'post_prev_status' => 'auto-draft',
				]
			);
			++$events_created;
			WP_CLI::log( 'Created: blog post published' );
		}

		// 8. Trashed a post.
		if ( $post_logger ) {
			$post_logger->info_message(
				'post_trashed',
				[
					'_initiator' => Log_Initiators::WP_USER,
					'post_id'    => wp_rand( 100, 9999 ),
					'post_type'  => 'post',
					'post_title' => 'Old Draft: Marketing Ideas 2024',
				]
			);
			++$events_created;
			WP_CLI::log( 'Created: post trashed' );
		}

		// 9. Activated plugin.
		if ( $plugin_logger ) {
			$plugin_logger->info_message(
				'plugin_activated',
				[
					'_initiator'     => Log_Initiators::WP_USER,
					'plugin_name'    => 'Yoast SEO',
					'plugin_slug'    => 'wordpress-seo',
					'plugin_version' => '24.1',
					'plugin_author'  => 'Team Yoast',
				]
			);
			++$events_created;
			WP_CLI::log( 'Created: plugin activated (Yoast SEO)' );
		}

		// 10. Deactivated plugin.
		if ( $plugin_logger ) {
			$plugin_logger->info_message(
				'plugin_deactivated',
				[
					'_initiator'     => Log_Initiators::WP_USER,
					'plugin_name'    => 'Hello Dolly',
					'plugin_slug'    => 'hello-dolly',
					'plugin_version' => '1.7.2',
					'plugin_author'  => 'Matt Mullenweg',
				]
			);
			++$events_created;
			WP_CLI::log( 'Created: plugin deactivated (Hello Dolly)' );
		}

		// Add reactions to all showcase events.
		if ( $add_reactions ) {
			$events_table_name = $simple_history->get_events_table_name();
			$user_ids          = get_users(
				[
					'fields' => 'ID',
					'number' => 50,
				]
			);

			if ( empty( $user_ids ) ) {
				$user_ids = [ 1 ];
			}

			// Get the most recent showcase event IDs.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$recent_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$events_table_name} ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$events_created
				)
			);

			foreach ( $recent_ids as $event_id ) {
				$this->add_random_reactions( (int) $event_id, $user_ids );
			}

			WP_CLI::log( sprintf( 'Added reactions to %d events.', count( $recent_ids ) ) );
		}

		WP_CLI::success(
			sprintf( 'Created %d showcase events.', $events_created )
		);
	}
}
