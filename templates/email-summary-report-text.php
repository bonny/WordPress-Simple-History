<?php
/**
 * Plain text version of the weekly email.
 *
 * Sent as the text/plain part of the summary email, alongside the HTML
 * version in templates/email-summary-report.php. It is built from the same
 * $args, in the same order, rather than by stripping tags from the HTML: the
 * HTML is a 900-line table layout, and what falls out of it is not something
 * anyone would want to read.
 *
 * The numbers here do not carry the log links the HTML version puts behind
 * them. A filtered log URL carries a JSON message filter and runs to a few
 * hundred characters, and eighteen of those — inline or gathered into a list
 * at the end — read worse than no links at all. The few links that are worth
 * their length are here: the log itself, and the settings page.
 */

defined( 'ABSPATH' ) || exit;

// Ensure $args is defined and holds every key used below.
if ( ! isset( $args ) ) {
	$args = [];
}

$args = wp_parse_args(
	$args,
	[
		'email_subject'          => __( 'Website Activity Summary', 'simple-history' ),
		'total_events_this_week' => 0,
		'most_active_days'       => [],
		'date_range'             => '',
		'site_url'               => '',
		'site_name'              => '',
		'site_url_domain'        => '',
		'successful_logins'      => 0,
		'failed_logins'          => 0,
		'posts_created'          => 0,
		'posts_updated'          => 0,
		'notes_enabled'          => false,
		'notes_added'            => 0,
		'notes_resolved'         => 0,
		'users_created'          => 0,
		'users_updated'          => 0,
		'media_uploads'          => 0,
		'media_edits'            => 0,
		'comments_enabled'       => false,
		'comments_added'         => 0,
		'comments_approved'      => 0,
		'comments_spam'          => 0,
		'plugin_activations'     => 0,
		'plugin_deactivations'   => 0,
		'theme_switches'         => 0,
		'theme_updates'          => 0,
		'wordpress_updates'      => 0,
		'history_admin_url'      => '',
		'stat_urls'              => [],
		'summary_text'           => '',
		'settings_url'           => '',
	]
);

// Tell the section filters which format they are rendering into. Add-ons
// written before this existed do not look at it and return HTML, which is
// handled where the filter output is used.
$args['format'] = 'text';

$show_upsell = apply_filters( 'simple_history/email_summary_report/show_upsell', \Simple_History\Helpers::show_promo_boxes() );

$show_main_core_stats = apply_filters( 'simple_history/email_summary_report/show_main_core_stats', true );

$email_report_service = \Simple_History\Simple_History::get_instance()->get_service( \Simple_History\Services\Email_Report_Service::class );

// Width to wrap prose at. Narrow enough to stay readable in a terminal mail
// client and in the preview pane of a client that shows the text part.
$wrap_width = 72;

$lines = [];

/**
 * Turn a piece of text meant for a person into plain text.
 *
 * Site names, tips and anything an add-on contributes travel through
 * WordPress as HTML, so an ampersand arrives as &amp; and an arrow as &rarr;,
 * and both would be read as those. Non-breaking spaces become ordinary ones,
 * since nothing here is laid out tightly enough to need them.
 *
 * @param string $text Text to clean up.
 * @return string
 */
$plain = function ( $text ) {
	$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );

	return str_replace( "\xc2\xa0", ' ', $text );
};

/**
 * One statistic as a line: its label and its number.
 *
 * The HTML version links each number to the log. Here the numbers stand on
 * their own: a filtered log URL runs to a few hundred characters, and a
 * column of them reads worse than no links at all.
 *
 * @param string $label Label for the number.
 * @param int    $count Number of events.
 * @return string
 */
$stat_line = function ( $label, $count ) {
	return '  ' . $label . ': ' . number_format_i18n( $count );
};

/**
 * Section content contributed by add-ons, as text.
 *
 * An add-on that predates the text part returns HTML here. Rather than drop
 * what it has to say, block level tags become line breaks and the rest is
 * stripped, which reads as a rough list instead of as markup.
 *
 * @param string $section Section name, as used in the filter.
 * @return string[] Lines to add, empty when the add-on had nothing.
 */
$section_content = function ( $section ) use ( $args, $plain, $wrap_width ) {
	$content = apply_filters( 'simple_history/email_summary_report/section_content/' . $section, '', $args );

	if ( ! is_string( $content ) || trim( $content ) === '' ) {
		return [];
	}

	// Give the block level tags of an HTML-returning add-on somewhere to break.
	$content = preg_replace( '#<(?:/(?:tr|div|p|li|h[1-6])|br\s*/?)>#i', "\n", $content );

	$content = $plain( $content );

	$lines = [];

	foreach ( preg_split( '/\R/', $content ) as $line ) {
		$line = trim( preg_replace( '/[ \t]+/', ' ', $line ) );

		if ( $line === '' ) {
			continue;
		}

		$lines[] = wordwrap( '  ' . $line, $wrap_width, "\n    ", false );
	}

	return $lines;
};

// Header: what this is, which week, and which site.
$lines[] = $plain( __( 'Website activity summary', 'simple-history' ) );

if ( $args['date_range'] !== '' ) {
	$lines[] = $plain( $args['date_range'] );
}

$lines[] = '';
$lines[] = $plain( $args['site_name'] );
$lines[] = $plain( $args['site_url'] );
$lines[] = '';
$lines[] = wordwrap( $plain( __( "Here's a summary of activity on your website.", 'simple-history' ) ), $wrap_width, "\n", false );

if ( $args['summary_text'] !== '' ) {
	$lines[] = '';
	$lines[] = wordwrap( $plain( $args['summary_text'] ), $wrap_width, "\n", false );
}

// The same sentence the HTML version uses, with its link markup removed, so
// the two parts do not need two translations of one line.
$lines[] = '';
$lines[] = wordwrap(
	$plain(
		sprintf(
			/* translators: 1: URL to history page, 2: link style attribute including style="" */
			__( '<a href="%1$s" %2$s>View the Simple History event log</a> on your website for a detailed history of changes and activities.', 'simple-history' ),
			'',
			''
		)
	),
	$wrap_width,
	"\n",
	false
);
$lines[] = $args['history_admin_url'];

// The Premium teaser the HTML version shows under the intro, for free users.
$top_teaser_text = $email_report_service instanceof \Simple_History\Services\Email_Report_Service
	? $email_report_service->get_top_teaser_text( $args )
	: '';

if ( $top_teaser_text !== '' ) {
	$lines[] = '';
	$lines[] = wordwrap( $plain( $top_teaser_text ), $wrap_width, "\n", false );
	$lines[] = 'https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_medium=email&utm_campaign=weekly-report&utm_content=top-teaser';
}

if ( $show_main_core_stats ) {
	$empty_sections = [];

	$lines[] = '';
	$lines[] = str_repeat( '-', $wrap_width );
	$lines[] = '';
	$lines[] = $plain( __( 'Total events', 'simple-history' ) );

	$lines[] = '  ' . number_format_i18n( $args['total_events_this_week'] );

	// Event count by day, in the order the days happened.
	$day_counts = [];

	foreach ( $args['most_active_days'] as $day ) {
		if ( ! isset( $day['day_number'], $day['count'] ) ) {
			continue;
		}

		$day_counts[ $day['day_number'] ] = $day['count'];
	}

	$start_timestamp = $args['date_from_timestamp'] ?? strtotime( '-6 days' );
	$end_timestamp   = $args['date_to_timestamp'] ?? time();

	$current_date = ( new DateTimeImmutable( '@' . $start_timestamp ) )->setTimezone( wp_timezone() );
	$end_date     = ( new DateTimeImmutable( '@' . $end_timestamp ) )->setTimezone( wp_timezone() );

	$day_lines = [];

	while ( $current_date <= $end_date ) {
		$day_number = (int) $current_date->format( 'w' );
		$day_name   = wp_date( 'D', $current_date->getTimestamp() );

		$day_lines[] = '  ' . $day_name . ': ' . number_format_i18n( $day_counts[ $day_number ] ?? 0 );

		$current_date = $current_date->modify( '+1 day' );
	}

	if ( $day_lines !== [] ) {
		$lines[] = '';
		$lines[] = $plain( __( 'Event count by day', 'simple-history' ) );
		$lines   = array_merge( $lines, $day_lines );
	}

	// Posts and pages.
	if ( $args['posts_created'] + $args['posts_updated'] > 0 ) {
		$lines[] = '';
		$lines[] = $plain( __( 'Posts and Pages', 'simple-history' ) );
		$lines[] = $stat_line( __( 'Created', 'simple-history' ), $args['posts_created'] );
		$lines[] = $stat_line( __( 'Edits', 'simple-history' ), $args['posts_updated'] );
		$lines   = array_merge( $lines, $section_content( 'posts' ) );
	} else {
		$empty_sections[] = __( 'Posts and Pages', 'simple-history' );
	}

	// Media.
	if ( $args['media_uploads'] + $args['media_edits'] > 0 ) {
		$lines[] = '';
		$lines[] = $plain( __( 'Media', 'simple-history' ) );
		$lines[] = $stat_line( __( 'Uploads', 'simple-history' ), $args['media_uploads'] );
		$lines[] = $stat_line( __( 'Edits', 'simple-history' ), $args['media_edits'] );
		$lines   = array_merge( $lines, $section_content( 'media' ) );
	} else {
		$empty_sections[] = __( 'Media', 'simple-history' );
	}

	// Comments, only when the site has them switched on.
	if ( $args['comments_enabled'] && $args['comments_added'] + $args['comments_approved'] + $args['comments_spam'] > 0 ) {
		$lines[] = '';
		$lines[] = $plain( __( 'Comments', 'simple-history' ) );
		$lines[] = $stat_line( __( 'New', 'simple-history' ), $args['comments_added'] );
		$lines[] = $stat_line( __( 'Approved', 'simple-history' ), $args['comments_approved'] );
		$lines[] = $stat_line( __( 'Spam', 'simple-history' ), $args['comments_spam'] );
		$lines   = array_merge( $lines, $section_content( 'comments' ) );
	} elseif ( $args['comments_enabled'] ) {
		$empty_sections[] = __( 'Comments', 'simple-history' );
	}

	// Notes, only on WordPress 6.9 and later where the feature exists.
	if ( $args['notes_enabled'] && $args['notes_added'] + $args['notes_resolved'] > 0 ) {
		$lines[] = '';
		$lines[] = $plain( __( 'Notes', 'simple-history' ) );
		$lines[] = $stat_line( __( 'Added', 'simple-history' ), $args['notes_added'] );
		$lines[] = $stat_line( __( 'Resolved', 'simple-history' ), $args['notes_resolved'] );
		$lines   = array_merge( $lines, $section_content( 'notes' ) );
	} elseif ( $args['notes_enabled'] ) {
		$empty_sections[] = __( 'Notes', 'simple-history' );
	}

	// Users.
	if ( $args['successful_logins'] + $args['failed_logins'] + $args['users_created'] + $args['users_updated'] > 0 ) {
		$lines[] = '';
		$lines[] = $plain( __( 'Users', 'simple-history' ) );
		$lines[] = $stat_line( __( 'Successful logins', 'simple-history' ), $args['successful_logins'] );
		$lines[] = $stat_line( __( 'Failed logins', 'simple-history' ), $args['failed_logins'] );
		$lines[] = $stat_line( __( 'Created', 'simple-history' ), $args['users_created'] );
		$lines[] = $stat_line( __( 'Profile updates', 'simple-history' ), $args['users_updated'] );
		$lines   = array_merge( $lines, $section_content( 'users' ) );
	} else {
		$empty_sections[] = __( 'Users', 'simple-history' );
	}

	// Plugins.
	if ( $args['plugin_activations'] + $args['plugin_deactivations'] > 0 ) {
		$lines[] = '';
		$lines[] = $plain( __( 'Plugins', 'simple-history' ) );
		$lines[] = $stat_line( __( 'Activations', 'simple-history' ), $args['plugin_activations'] );
		$lines[] = $stat_line( __( 'Deactivations', 'simple-history' ), $args['plugin_deactivations'] );
		$lines   = array_merge( $lines, $section_content( 'plugins' ) );
	} else {
		$empty_sections[] = __( 'Plugins', 'simple-history' );
	}

	// Themes.
	if ( $args['theme_switches'] + $args['theme_updates'] > 0 ) {
		$lines[] = '';
		$lines[] = $plain( __( 'Themes', 'simple-history' ) );
		$lines[] = $stat_line( __( 'Switches', 'simple-history' ), $args['theme_switches'] );
		$lines[] = $stat_line( __( 'Updates', 'simple-history' ), $args['theme_updates'] );
		$lines   = array_merge( $lines, $section_content( 'themes' ) );
	} else {
		$empty_sections[] = __( 'Themes', 'simple-history' );
	}

	// WordPress core.
	if ( $args['wordpress_updates'] > 0 ) {
		$lines[] = '';
		$lines[] = $plain( __( 'WordPress core', 'simple-history' ) );
		$lines[] = $stat_line( __( 'Core updates', 'simple-history' ), $args['wordpress_updates'] );
		// phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- filter slug, not prose.
		$lines = array_merge( $lines, $section_content( 'wordpress' ) );
	} else {
		$empty_sections[] = __( 'WordPress core', 'simple-history' );
	}

	// Sections without activity collapse into one line, as in the HTML version.
	if ( $empty_sections !== [] ) {
		$lines[] = '';
		$lines[] = $plain( __( 'Nothing to report', 'simple-history' ) );
		$lines[] = wordwrap(
			'  ' . $plain(
				sprintf(
					/* translators: %s: list of section names, e.g. "Media, Themes, and WordPress core" */
					__( 'No activity was logged this week in %s.', 'simple-history' ),
					wp_sprintf( '%l', $empty_sections )
				)
			),
			$wrap_width,
			"\n  ",
			false
		);
	}
}

/** This filter is documented in templates/email-summary-report.php */
$content_after_core_stats = apply_filters( 'simple_history/email_summary_report/content_after_core_stats', '' );

if ( is_string( $content_after_core_stats ) && trim( $content_after_core_stats ) !== '' ) {
	$lines[] = '';
	$lines[] = wordwrap( $plain( $content_after_core_stats ), $wrap_width, "\n", false );
}

$tips_service = \Simple_History\Simple_History::get_instance()->get_service( \Simple_History\Services\Tips_Service::class );
$show_tip     = apply_filters( 'simple_history/email_summary_report/show_tip', true );

if ( $show_tip && $tips_service instanceof \Simple_History\Services\Tips_Service ) {
	$tip_text = $tips_service->get_tip_for_email( $args );

	if ( $tip_text ) {
		$lines[] = '';
		$lines[] = str_repeat( '-', $wrap_width );
		$lines[] = '';
		$lines[] = wordwrap(
			$plain( __( 'Tip:', 'simple-history' ) ) . ' ' . $plain( $tip_text ),
			$wrap_width,
			"\n",
			false
		);
	}
}

if ( $show_upsell ) {
	$lines[] = '';
	$lines[] = str_repeat( '-', $wrap_width );
	$lines[] = '';
	$lines[] = $plain( __( 'Get more from your activity log', 'simple-history' ) );
	$lines[] = '';
	$lines[] = wordwrap(
		$plain( __( 'Free logs expire after 60 days. Premium lets you keep them longer — and adds real-time alerts, Slack notifications, CSV export, and log forwarding to syslog or external databases.', 'simple-history' ) ),
		$wrap_width,
		"\n",
		false
	);
	$lines[] = '';
	$lines[] = 'https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_medium=email&utm_campaign=weekly-report&utm_content=upsell-block';
}

// Why this email arrived, and how to stop it.
$lines[] = '';
$lines[] = str_repeat( '-', $wrap_width );
$lines[] = '';
$lines[] = wordwrap(
	$plain(
		sprintf(
			/* translators: %s: Site name */
			__( 'You\'re receiving this email because you\'re listed as a recipient in the Simple History email report settings for %s.', 'simple-history' ),
			$args['site_name']
		)
	),
	$wrap_width,
	"\n",
	false
);
$lines[] = '';
$lines[] = wordwrap(
	$plain(
		sprintf(
			/* translators: 1: URL to settings page, 2: link style attribute including style="" */
			__( 'To stop receiving these emails, go to <a href="%1$s" %2$s>Settings → Simple History → Email Reports</a> in your WordPress admin and remove your email address.', 'simple-history' ),
			'',
			''
		)
	),
	$wrap_width,
	"\n",
	false
);
$lines[] = $args['settings_url'];

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text part of an email, escaping it would show the escapes to the reader.
echo implode( "\n", $lines ) . "\n";
