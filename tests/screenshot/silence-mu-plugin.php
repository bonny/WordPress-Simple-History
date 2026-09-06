<?php
/**
 * Plugin Name: Screenshot Silencer (MU)
 * Description: Suppresses noisy Simple History log events while capturing
 *   wordpress.org marketing screenshots so only the curated events from
 *   tests/screenshot/events.php surface, and remaps Sally/Alex/Robin avatars
 *   to local fixtures. Dropped into mu-plugins by the screenshot blueprint
 *   only — never ships with the real plugin.
 *
 * Safety: this file should only execute inside the screenshot WordPress
 * Playground sandbox. If it somehow ends up in a real install's mu-plugins
 * folder, bail out instead of silently muting that site's log. We check for
 * the screenshot fixture files (events.php, avatars) — they're shipped only
 * in the dev tree, never in the wordpress.org or composer-built release.
 */

$sh_screenshot_fixture = WP_CONTENT_DIR . '/plugins/simple-history/tests/screenshot/events.php';
if ( ! file_exists( $sh_screenshot_fixture ) ) {
	return;
}
unset( $sh_screenshot_fixture );

// Filter out Simple History's own welcome/backfill events.
add_filter( 'simple_history/log/do_log/SimpleHistoryLogger/auto_backfill_completed', '__return_false' );
add_filter( 'simple_history/log/do_log/SimpleHistoryLogger/manual_backfill_completed', '__return_false' );

// Filter out "Found an update to plugin X" notices from the available-updates logger.
add_filter( 'simple_history/log/do_log/AvailableUpdatesLogger/plugin_update_available', '__return_false' );
add_filter( 'simple_history/log/do_log/AvailableUpdatesLogger/theme_update_available', '__return_false' );
add_filter( 'simple_history/log/do_log/AvailableUpdatesLogger/core_update_available', '__return_false' );

// Filter out Playwright admin logins from the User logger.
add_filter( 'simple_history/log/do_log/SimpleUserLogger/user_logged_in', '__return_false' );
add_filter( 'simple_history/log/do_log/SimpleUserLogger/user_unknown_logged_in', '__return_false' );
add_filter( 'simple_history/log/do_log/SimpleUserLogger/user_logged_out', '__return_false' );

// Mute auto-fired user_created events from blueprint bootstrap (Sally/Alex/
// Robin/Mike/Jess/Sam/Deedee — all created in events.php). Curated events
// explicitly fired by events.php carry `_curated_event => '1'` and are let
// through.
add_filter(
	'simple_history/log/do_log',
	function ( $do_log, $level, $message, $context, $logger ) {
		if (
			$logger->get_slug() === 'SimpleUserLogger'
			&& ( $context['_message_key'] ?? '' ) === 'user_created'
			&& empty( $context['_curated_event'] )
		) {
			return false;
		}
		return $do_log;
	},
	10,
	5
);

// Suppress the SimplePluginLogger activate noise from blueprint bootstrap.
// Curated activate/deactivate events fired from events.php carry the
// `_curated_event => '1'` marker and are let through.
add_filter(
	'simple_history/log/do_log',
	function ( $do_log, $level, $message, $context, $logger ) {
		if (
			$logger->get_slug() === 'SimplePluginLogger'
			&& in_array( $context['_message_key'] ?? '', [ 'plugin_activated', 'plugin_deactivated' ], true )
			&& empty( $context['_curated_event'] )
		) {
			return false;
		}
		return $do_log;
	},
	10,
	5
);

// Hide the "1 plugin update" bubble in the admin menu — it pulls focus from the
// event log in the screenshot. Empties the plugin / theme / core update
// transients so WordPress reports nothing pending.
$sh_no_updates = function () {
	$empty = new stdClass();
	$empty->last_checked = time();
	$empty->response     = [];
	$empty->translations = [];
	$empty->no_update    = [];
	return $empty;
};
add_filter( 'site_transient_update_plugins', $sh_no_updates );
add_filter( 'site_transient_update_themes', $sh_no_updates );
add_filter(
	'site_transient_update_core',
	function () {
		$empty                = new stdClass();
		$empty->updates       = [];
		$empty->last_checked  = time();
		$empty->version_checked = get_bloginfo( 'version' );
		return $empty;
	}
);

// Give Sally and Alex visible avatars by replacing the gravatar URL with
// local fixture images. Short-circuits via pre_get_avatar_data — runs before
// WP touches the URL so the result can't be re-overridden by anything else.
add_filter(
	'pre_get_avatar_data',
	function ( $args, $id_or_email ) {
		// Check is_numeric BEFORE is_string — string "2" passes both, and the
		// numeric path needs to win so we look up the user by ID.
		if ( is_numeric( $id_or_email ) ) {
			$user  = get_user_by( 'id', (int) $id_or_email );
			$email = $user ? $user->user_email : '';
		} elseif ( is_string( $id_or_email ) ) {
			$email = $id_or_email;
		} elseif ( $id_or_email instanceof WP_User ) {
			$email = $id_or_email->user_email;
		} elseif ( is_object( $id_or_email ) && isset( $id_or_email->comment_author_email ) ) {
			$email = $id_or_email->comment_author_email;
		} else {
			$email = '';
		}

		$presets = [
			'sally@example.com'  => 'avatar-sally.webp',
			'alex@example.com'   => 'avatar-alex.webp',
			'robin@example.com'  => 'avatar-robin.png',
			'deedee@ramones.net' => 'avatar-robin.png',
		];

		if ( ! isset( $presets[ $email ] ) ) {
			return $args;
		}

		return array_merge(
			(array) $args,
			[
				'url'          => content_url(
					'plugins/simple-history/tests/screenshot/' . $presets[ $email ]
				),
				'found_avatar' => true,
			]
		);
	},
	10,
	2
);
