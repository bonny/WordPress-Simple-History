<?php
/**
 * Populates a clean WordPress Playground install with the curated events for
 * the wordpress.org marketing screenshot: failed login, content diff with
 * inline title/content changes, plugin update, media upload, post publish,
 * and a WP-CLI user_created event.
 *
 * NOT A PLUGIN — this is a one-off script required by blueprint.json's
 * runPHP step inside the screenshot WordPress Playground sandbox. It writes
 * directly to wp_simple_history / wp_simple_history_contexts and assumes a
 * disposable install. Never load this outside the playground.
 *
 * Order matters — events surface newest-first, so the first info_message()
 * call ends up at the bottom of the rendered log and the last call at the
 * top. See `.claude/skills/wp-org-screenshots/SKILL.md` for the full
 * pipeline + event-mix rationale.
 */

require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$simple_history = \Simple_History\Simple_History::get_instance();

// Start from a clean slate. The silence mu-plugin should keep most noise out
// already, but TRUNCATE guarantees only the four curated events surface.
global $wpdb;
$events_table  = $simple_history->get_events_table_name();
$context_table = $simple_history->get_contexts_table_name();
$wpdb->query( "TRUNCATE TABLE {$events_table}" );
$wpdb->query( "TRUNCATE TABLE {$context_table}" );

// Auto-backfill is scheduled on first plugin activation and fires on the next
// admin_init — which is Playwright's first page hit. Kill the pending flag so
// the screenshot only shows the curated events created below.
delete_option( 'simple_history_auto_backfill_pending' );
update_option( 'simple_history_auto_backfill_status', [ 'completed' => true ] );

// ---------------------------------------------------------------------------
// Users — Sally (editor) and Alex (administrator). Skip if they already exist.
// ---------------------------------------------------------------------------
$sally_id = username_exists( 'sally' );
if ( ! $sally_id ) {
	$sally_id = wp_insert_user(
		[
			'user_login'   => 'sally',
			'user_email'   => 'sally@example.com',
			'user_pass'    => wp_generate_password( 20, false ),
			'first_name'   => 'Sally',
			'last_name'    => 'Admin',
			'display_name' => 'Sally Admin',
			'role'         => 'editor',
		]
	);
}

$alex_id = username_exists( 'alex' );
if ( ! $alex_id ) {
	$alex_id = wp_insert_user(
		[
			'user_login'   => 'alex',
			'user_email'   => 'alex@example.com',
			'user_pass'    => wp_generate_password( 20, false ),
			'first_name'   => 'Alex',
			'last_name'    => 'Admin',
			'display_name' => 'Alex Admin',
			'role'         => 'administrator',
		]
	);
}

$robin_id = username_exists( 'robin' );
if ( ! $robin_id ) {
	$robin_id = wp_insert_user(
		[
			'user_login'   => 'robin',
			'user_email'   => 'robin@example.com',
			'user_pass'    => wp_generate_password( 20, false ),
			'first_name'   => 'Robin',
			'last_name'    => 'Editor',
			'display_name' => 'Robin Editor',
			'role'         => 'editor',
		]
	);
}

// Dee Dee Ramone — Ramones-themed fixture for the user create + edit
// screenshot. Created in events.php as `deedee` with Douglas Colvin's birth
// name + URL etc, then a user_updated_profile event below upgrades the
// fields to her stage persona. Real WP user so the renderer's
// get_user_by(edited_user_id) call resolves and the row gets a profile link.
$deedee_id = username_exists( 'deedee' );
if ( ! $deedee_id ) {
	$deedee_id = wp_insert_user(
		[
			'user_login'   => 'deedee',
			'user_email'   => 'deedee@ramones.net',
			'user_pass'    => wp_generate_password( 20, false ),
			'first_name'   => 'Dee Dee',
			'last_name'    => 'Ramone',
			'user_url'     => 'http://www.deedeeramone.com/',
			'display_name' => 'Dee Dee Ramone',
			'role'         => 'author',
		]
	);
}

// Extra users — they don't get curated events at the top but show up in
// historical noise so "Most active users" feels like a real multi-user site.
$extra_users = [
	[ 'login' => 'mike', 'first' => 'Mike', 'last' => 'Patel', 'role' => 'editor' ],
	[ 'login' => 'jess', 'first' => 'Jess', 'last' => 'Park', 'role' => 'author' ],
	[ 'login' => 'sam', 'first' => 'Sam', 'last' => 'Wong', 'role' => 'contributor' ],
];

$extra_user_ids = [];
foreach ( $extra_users as $eu ) {
	$existing = username_exists( $eu['login'] );
	if ( $existing ) {
		$extra_user_ids[] = $existing;
		continue;
	}
	$extra_user_ids[] = wp_insert_user(
		[
			'user_login'   => $eu['login'],
			'user_email'   => $eu['login'] . '@example.com',
			'user_pass'    => wp_generate_password( 20, false ),
			'first_name'   => $eu['first'],
			'last_name'    => $eu['last'],
			'display_name' => $eu['first'] . ' ' . $eu['last'],
			'role'         => $eu['role'],
		]
	);
}

// ---------------------------------------------------------------------------
// Event 5 (oldest): User created via WP-CLI — "wp user create deploy-bot
// deploy@example.com --role=administrator". Distinctly CLI work (you wouldn't
// hand-create a service account in admin), and the admin role plays into the
// security audience ("an admin user, from a script — I'd want to see that").
// Fired first so it lands at the bottom of the visible log.
// ---------------------------------------------------------------------------
$user_logger = $simple_history->get_instantiated_logger_by_slug( 'SimpleUserLogger' );
if ( $user_logger ) {
	// Use a fake id high enough that it doesn't collide with the real users
	// we created earlier. Simple History renders user_created events from
	// context fields, not a live user lookup, so the id doesn't need to exist.
	$user_logger->info_message(
		'user_created',
		[
			'created_user_id'         => 999,
			'created_user_email'      => 'deploy@example.com',
			'created_user_login'      => 'deploy-bot',
			'created_user_first_name' => '',
			'created_user_last_name'  => '',
			'created_user_url'        => '',
			'created_user_role'       => 'administrator',
			'send_user_notification'  => 0,
			'_initiator'              => \Simple_History\Log_Initiators::WP_CLI,
			'_curated_event'          => '1',
		]
	);
}

// ---------------------------------------------------------------------------
// Event 4b: Alex created `deedee` as a contributor under her birth name
// Douglas Colvin, with the welcome email enabled. Paired with the
// user_updated_profile event below — together they tell the "Simple History
// captures the full lifecycle of user accounts" story for screenshot-3
// (Ramones names because they're memorable).
// ---------------------------------------------------------------------------
if ( $user_logger ) {
	$user_logger->info_message(
		'user_created',
		[
			'created_user_id'         => $deedee_id,
			'created_user_email'      => 'deedee@ramones.net',
			'created_user_login'      => 'deedee',
			'created_user_first_name' => 'Douglas',
			'created_user_last_name'  => 'Colvin',
			'created_user_url'        => '',
			'created_user_role'       => 'contributor',
			'send_user_notification'  => 1,
			'_user_id'                => $alex_id,
			'_user_login'             => 'alex',
			'_user_email'             => 'alex@example.com',
			'_initiator'              => \Simple_History\Log_Initiators::WP_USER,
			'_curated_event'          => '1',
		]
	);
}

// ---------------------------------------------------------------------------
// Event 4c: Alex edited deedee's profile — upgraded role + applied her stage
// name + set her band URL. Fires right after the create above so the two
// rows appear stacked for screenshot-3.
// ---------------------------------------------------------------------------
if ( $user_logger ) {
	$user_logger->info_message(
		'user_updated_profile',
		[
			'edited_user_id'         => $deedee_id,
			'edited_user_login'      => 'deedee',
			'edited_user_email'      => 'deedee@ramones.net',
			'user_prev_first_name'   => 'Douglas',
			'user_new_first_name'    => 'Dee Dee',
			'user_prev_last_name'    => 'Colvin',
			'user_new_last_name'     => 'Ramone',
			'user_prev_user_url'     => '',
			'user_new_user_url'      => 'http://www.deedeeramone.com/',
			'user_prev_display_name' => 'Douglas Colvin',
			'user_new_display_name'  => 'Dee Dee Ramone',
			'_user_id'               => $alex_id,
			'_user_login'            => 'alex',
			'_user_email'            => 'alex@example.com',
			'_initiator'             => \Simple_History\Log_Initiators::WP_USER,
			'_curated_event'         => '1',
		]
	);
}

// ---------------------------------------------------------------------------
// Event 4: Media upload "team-photo.jpg" with thumbnail.
// Copies the fixture into uploads and registers an attachment — Media_Logger
// fires its own log entry via the add_attachment action.
// ---------------------------------------------------------------------------
$fixture       = __DIR__ . '/team-photo.jpg';
$upload_dir    = wp_upload_dir();
$dest_filename = wp_unique_filename( $upload_dir['path'], 'team-photo.jpg' );
$dest_path     = $upload_dir['path'] . '/' . $dest_filename;

if ( file_exists( $fixture ) ) {
	copy( $fixture, $dest_path );

	// Set current user so Media_Logger attributes the upload to Sally rather
	// than the empty CLI-context default ("Other").
	wp_set_current_user( $sally_id );

	$filetype      = wp_check_filetype( $dest_filename );
	$attachment_id = wp_insert_attachment(
		[
			'post_mime_type' => $filetype['type'],
			'post_title'     => 'Team photo',
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_author'    => $sally_id,
		],
		$dest_path
	);

	$metadata = wp_generate_attachment_metadata( $attachment_id, $dest_path );
	wp_update_attachment_metadata( $attachment_id, $metadata );

	wp_set_current_user( 0 );
}

// ---------------------------------------------------------------------------
// Event 3: Plugin update — "WooCommerce" 9.5.2 -> 9.5.3. Faked so we don't
// actually need WooCommerce installed.
// ---------------------------------------------------------------------------
$plugin_logger = $simple_history->get_instantiated_logger_by_slug( 'SimplePluginLogger' );
if ( $plugin_logger ) {
	$plugin_logger->info_message(
		'plugin_updated',
		[
			'plugin_name'         => 'WooCommerce',
			'plugin_version'      => '9.5.3',
			'plugin_prev_version' => '9.5.2',
			'plugin_slug'         => 'woocommerce',
			'plugin_title'        => 'WooCommerce',
			'plugin_description'  => 'Sell products online.',
			'plugin_author'       => 'Automattic',
			'plugin_url'          => 'https://woocommerce.com/',
			'_user_id'            => $alex_id,
			'_user_login'         => 'alex',
			'_user_email'         => 'alex@example.com',
			'_initiator'          => \Simple_History\Log_Initiators::WP_USER,
		]
	);
}

// ---------------------------------------------------------------------------
// Event 3b: Plugin install — "Yoast SEO" with rich context (description,
// install source, version, author, URL). Stacked with the activate +
// deactivate events below to show the full plugin lifecycle.
// ---------------------------------------------------------------------------
if ( $plugin_logger ) {
	$plugin_logger->info_message(
		'plugin_installed',
		[
			'plugin_name'           => 'Yoast SEO',
			'plugin_slug'           => 'wordpress-seo',
			'plugin_title'          => 'Yoast SEO',
			'plugin_description'    => 'The first true all-in-one SEO solution for WordPress, including on-page content analysis, XML sitemaps and much more.',
			'plugin_author'         => 'Team Yoast',
			'plugin_url'            => 'https://yoast.com/wordpress/plugins/seo/',
			'plugin_version'        => '23.7',
			'plugin_install_source' => 'web',
			'_user_id'              => $alex_id,
			'_user_login'           => 'alex',
			'_user_email'           => 'alex@example.com',
			'_initiator'            => \Simple_History\Log_Initiators::WP_USER,
		]
	);
}

// ---------------------------------------------------------------------------
// Event 3c: Plugin activate — Yoast SEO turned on right after install.
// ---------------------------------------------------------------------------
if ( $plugin_logger ) {
	$plugin_logger->info_message(
		'plugin_activated',
		[
			'plugin_name'        => 'Yoast SEO',
			'plugin_slug'        => 'wordpress-seo',
			'plugin_title'       => 'Yoast SEO',
			'plugin_description' => 'The first true all-in-one SEO solution for WordPress, including on-page content analysis, XML sitemaps and much more.',
			'plugin_author'      => 'Team Yoast',
			'plugin_url'         => 'https://yoast.com/wordpress/plugins/seo/',
			'plugin_version'     => '23.7',
			'_user_id'           => $alex_id,
			'_user_login'        => 'alex',
			'_user_email'        => 'alex@example.com',
			'_initiator'         => \Simple_History\Log_Initiators::WP_USER,
			'_curated_event'     => '1',
		]
	);
}

// ---------------------------------------------------------------------------
// Event 3d: Plugin deactivate — the default "Hello Dolly" plugin every WP
// install ships with, turned off as part of housekeeping. Rounds out the
// install/activate/deactivate trio for screenshot-4.
// ---------------------------------------------------------------------------
if ( $plugin_logger ) {
	$plugin_logger->info_message(
		'plugin_deactivated',
		[
			'plugin_name'        => 'Hello Dolly',
			'plugin_slug'        => 'hello-dolly',
			'plugin_title'       => 'Hello Dolly',
			'plugin_description' => 'This is not just a plugin, it symbolizes the hope and enthusiasm of an entire generation summed up in two words sung most famously by Louis Armstrong: Hello, Dolly.',
			'plugin_author'      => 'Matt Mullenweg',
			'plugin_url'         => 'http://wordpress.org/plugins/hello-dolly/',
			'plugin_version'     => '1.7.3',
			'_user_id'           => $alex_id,
			'_user_login'        => 'alex',
			'_user_email'        => 'alex@example.com',
			'_initiator'         => \Simple_History\Log_Initiators::WP_USER,
			'_curated_event'     => '1',
		]
	);
}

// ---------------------------------------------------------------------------
// Event 2: Post update with inline title + content diff. The diff is rendered
// from post_prev_* / post_new_* context-key pairs by Post_Logger.
// ---------------------------------------------------------------------------
$about_page = get_page_by_path( 'about-us', OBJECT, 'page' );
if ( ! $about_page ) {
	$about_id = wp_insert_post(
		[
			'post_type'    => 'page',
			'post_title'   => 'About us',
			'post_name'    => 'about-us',
			'post_content' => 'We design and ship premium organic cotton t-shirts, hoodies, and limited-edition tour merch worldwide. Free shipping on orders over $50.',
			'post_status'  => 'publish',
			'post_author'  => $sally_id,
		]
	);
} else {
	$about_id = $about_page->ID;
}

$post_logger = $simple_history->get_instantiated_logger_by_slug( 'SimplePostLogger' );
if ( $post_logger ) {
	$post_logger->info_message(
		'post_updated',
		[
			'post_id'                => $about_id,
			'post_type'              => 'page',
			'post_title'             => 'About us',
			'post_prev_post_title'   => 'About',
			'post_new_post_title'    => 'About us',
			'post_prev_post_content' => 'Discount t-shirts and hoodies for our fans.',
			'post_new_post_content'  => 'Premium organic cotton t-shirts and hoodies for our fans worldwide.',
			'_user_id'               => $sally_id,
			'_user_login'            => 'sally',
			'_user_email'            => 'sally@example.com',
			'_initiator'             => \Simple_History\Log_Initiators::WP_USER,
		]
	);
}

// ---------------------------------------------------------------------------
// Event 1b: Robin published a post — adds a third real user to the mix and
// rounds out the content workflow (Sally drafts/edits, Robin publishes).
// ---------------------------------------------------------------------------
if ( $post_logger ) {
	$post_logger->info_message(
		'post_updated',
		[
			'post_id'                => 0,
			'post_type'              => 'post',
			'post_title'             => 'Summer collection now available',
			'post_prev_post_status'  => 'draft',
			'post_new_post_status'   => 'publish',
			'_user_id'               => $robin_id,
			'_user_login'            => 'robin',
			'_user_email'            => 'robin@example.com',
			'_initiator'             => \Simple_History\Log_Initiators::WP_USER,
		]
	);
}

// ---------------------------------------------------------------------------
// Event 1 (most recent): Failed login from a "real-looking" attacker IP.
// 203.0.113.x is RFC 5737 reserved-for-docs but reads as plausible.
// ---------------------------------------------------------------------------
$user_logger = $simple_history->get_instantiated_logger_by_slug( 'SimpleUserLogger' );
if ( $user_logger ) {
	$user_logger->warning_message(
		'user_login_failed',
		[
			'login'                  => 'admin',
			'_server_remote_addr'    => '203.0.113.42',
			'server_http_user_agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
			'_user_id'               => 0,
			'_initiator'             => \Simple_History\Log_Initiators::WEB_USER,
		]
	);
}

// ---------------------------------------------------------------------------
// Historical noise — generic events spread across the last 28 days so
// "Daily activity over last 30 days" in History Insights looks populated
// (varied bars, not a single spike today) and "Most active users" reads
// like a real multi-user site.
//
// Direct DB inserts so we can control the date — Simple History loggers
// always stamp NOW(). All look-the-part but are intentionally mundane so
// they don't compete with the four curated events at the top.
// ---------------------------------------------------------------------------
// Weight the pool so the users with avatar fixtures (Sally / Alex / Robin)
// show up first in "Most active users" — each gets four slots vs one for
// the avatar-less extras. Net result: top 3 are guaranteed to have photos.
$user_ids = array_merge(
	array_fill( 0, 4, $sally_id ),
	array_fill( 0, 4, $alex_id ),
	array_fill( 0, 4, $robin_id ),
	$extra_user_ids
);
$templates = [
	[
		'logger'  => 'SimpleUserLogger',
		'level'   => 'info',
		'message' => 'Logged in',
		'key'     => 'user_logged_in',
	],
	[
		'logger'  => 'SimplePostLogger',
		'level'   => 'info',
		'message' => 'Updated {post_type} "{post_title}"',
		'key'     => 'post_updated',
		'extra'   => [ 'post_title' => 'Homepage', 'post_type' => 'page' ],
	],
	[
		'logger'  => 'SimplePostLogger',
		'level'   => 'info',
		'message' => 'Updated {post_type} "{post_title}"',
		'key'     => 'post_updated',
		'extra'   => [ 'post_title' => 'Contact', 'post_type' => 'page' ],
	],
	[
		'logger'  => 'SimplePostLogger',
		'level'   => 'info',
		'message' => 'Created {post_type} "{post_title}"',
		'key'     => 'post_created',
		'extra'   => [ 'post_title' => 'Winter sale 2026', 'post_type' => 'post' ],
	],
	[
		'logger'  => 'SimpleCommentsLogger',
		'level'   => 'info',
		'message' => 'Approved a comment',
		'key'     => 'comment_status_approve',
	],
];

// Walk back day-by-day; some days are quiet, some busy, a couple are empty.
// Total lands around 50 events across 28 days.
$event_counter = 0;
for ( $days_ago = 1; $days_ago <= 28; $days_ago++ ) {
	$roll = wp_rand( 1, 10 );
	if ( $roll <= 3 ) {
		$events_today = 0;
	} elseif ( $roll <= 7 ) {
		$events_today = wp_rand( 1, 3 );
	} else {
		$events_today = wp_rand( 4, 7 );
	}

	for ( $e = 0; $e < $events_today; $e++ ) {
		$hours_ago = wp_rand( 0, 23 );
		$mins_ago  = wp_rand( 0, 59 );
		$date      = gmdate(
			'Y-m-d H:i:s',
			time() - ( $days_ago * DAY_IN_SECONDS ) - ( $hours_ago * HOUR_IN_SECONDS ) - ( $mins_ago * MINUTE_IN_SECONDS )
		);

		$user_id  = $user_ids[ array_rand( $user_ids ) ];
		$template = $templates[ array_rand( $templates ) ];

		$wpdb->insert(
			$events_table,
			[
				'date'        => $date,
				'logger'      => $template['logger'],
				'level'       => $template['level'],
				'message'     => $template['message'],
				'initiator'   => 'wp_user',
				'occasionsID' => md5( $template['logger'] . $template['key'] . $event_counter ),
			]
		);

		$event_id = (int) $wpdb->insert_id;

		$user        = get_userdata( $user_id );
		$user_login  = $user ? $user->user_login : '';
		$user_email  = $user ? $user->user_email : '';

		$context = array_merge(
			[
				'_user_id'            => (string) $user_id,
				'_user_login'         => $user_login,
				'_user_email'         => $user_email,
				'_message_key'        => $template['key'],
				'_loggerSlug'         => $template['logger'],
				'_initiator'          => 'wp_user',
				'_server_remote_addr' => '127.0.0.1',
			],
			$template['extra'] ?? []
		);

		foreach ( $context as $key => $value ) {
			$wpdb->insert(
				$context_table,
				[
					'history_id' => $event_id,
					'key'        => $key,
					'value'      => (string) $value,
				]
			);
		}

		$event_counter++;
	}
}
