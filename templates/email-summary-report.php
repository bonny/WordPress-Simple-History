<?php
/**
 * Template for weekly emails.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filter to show the upsell.
 *
 * @param bool $show_upsell Whether to show the upsell.
 * @return bool Whether to show the upsell.
 */
$show_upsell = apply_filters( 'simple_history/email_summary_report/show_upsell', \Simple_History\Helpers::show_promo_boxes() );

/**
 * Filter to show the main core stats.
 *
 * @param bool $show_main_core_stats Whether to show the main core stats.
 * @return bool Whether to show the main core stats.
 */
$show_main_core_stats = apply_filters( 'simple_history/email_summary_report/show_main_core_stats', true );

/**
 * Filter to add content after the core stats.
 * Can be used by add-ons to add content after the core stats.
 *
 * @param string $content_after_core_stats The content to add after the core stats.
 * @return string The content to add after the core stats.
 */
$content_after_core_stats = apply_filters( 'simple_history/email_summary_report/content_after_core_stats', '' );

// Ensure $args is defined and is an array with fallback values for all used data.
if ( ! isset( $args ) ) {
	$args = [];
}
$args = wp_parse_args(
	$args,
	[
		'email_subject'          => __( 'Website Activity Summary', 'simple-history' ),
		'total_events_this_week' => 0,
		'most_active_days'       => [],
		'busiest_day_name'       => __( 'No activity', 'simple-history' ),
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

$stat_font             = '-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif';
$stat_label_style      = "font-family: {$stat_font}; font-size: 13px; line-height: 18px; color: #666666; text-align: left; font-weight: 400; margin-bottom: 2px;";
$stat_number_style     = "font-family: {$stat_font}; font-size: 24px; line-height: 28px; font-weight: 700; text-align: left;";
$stat_big_number_style = "font-family: {$stat_font}; font-size: 36px; line-height: 42px; font-weight: 700; text-align: left;";

/**
 * Render one statistic: its label and its number.
 *
 * Every number answers "how many", and the reader's next question is "which
 * ones", so a number with events behind it links to the log filtered to those
 * events over the same days. A zero stays plain text — a link to an empty log
 * is a worse answer than no link. Linked numbers take the accent colour, the
 * same signal the clickable day counts already use.
 *
 * @param string $label        Label shown above the number.
 * @param int    $count        Number of events.
 * @param string $stat_key     Key into $args['stat_urls'].
 * @param string $number_style Inline style for the number, without its colour.
 */
$render_stat = function ( $label, $count, $stat_key, $number_style = null ) use ( $args, $stat_label_style, $stat_number_style ) {
	if ( $number_style === null ) {
		$number_style = $stat_number_style;
	}

	$url = $count > 0 && ! empty( $args['stat_urls'][ $stat_key ] ) ? $args['stat_urls'][ $stat_key ] : '';

	$number_style .= $url === '' ? ' color: #000000;' : ' color: #0040FF;';

	$html = '<div style="' . esc_attr( $stat_label_style ) . '">' . esc_html( $label ) . '</div>';

	$html .= '<div style="' . esc_attr( $number_style ) . '">' . esc_html( number_format_i18n( $count ) ) . '</div>';

	if ( $url !== '' ) {
		$html = '<a href="' . esc_url( $url ) . '" style="text-decoration: none; display: block;">' . $html . '</a>';
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped as it is built above.
	echo $html;
};

$tips_service = \Simple_History\Simple_History::get_instance()->get_service( \Simple_History\Services\Tips_Service::class );
$week_index   = $tips_service instanceof \Simple_History\Services\Tips_Service ? $tips_service->get_week_index( $args ) : (int) gmdate( 'W' );

// Pick the top teaser text (free users only). Every teaser that matches this
// week's activity goes in a pool together with the generic ones, and the pick
// rotates by week number so the same activity does not repeat the same line.
$top_teaser_text = '';
$premium_url     = 'https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_medium=email&utm_campaign=weekly-report&utm_content=top-teaser';

if ( $show_upsell ) {
	$teaser_pool = [];

	if ( $args['failed_logins'] > 0 ) {
		$teaser_pool[] = __( 'With Premium, this email shows the IP addresses and usernames behind every failed login attempt.', 'simple-history' );
	}

	if ( $args['plugin_activations'] + $args['plugin_deactivations'] > 0 ) {
		$teaser_pool[] = __( 'With Premium, this email names each plugin and the person who changed it.', 'simple-history' );
	}

	// Only tease the posts list when activity is notable — low counts don't create curiosity.
	if ( $args['posts_created'] + $args['posts_updated'] > 3 ) {
		$teaser_pool[] = __( 'With Premium, this email shows who edited which posts and when.', 'simple-history' );
	}

	if ( $args['users_created'] > 0 ) {
		$teaser_pool[] = __( 'With Premium, this email includes the username and role of every new account.', 'simple-history' );
	}

	// Generic teasers, always in the pool.
	$teaser_pool[] = __( 'Premium fills this email in with the details — which post, which plugin, who logged in — and sends real-time alerts for critical events, so you don\'t have to wait for Monday to hear about them.', 'simple-history' );
	$teaser_pool[] = __( 'Free logs expire after 60 days. Premium keeps up to a full year, so you can still see what changed months later.', 'simple-history' );
	$teaser_pool[] = __( 'With Premium, this email lists who did what — the names behind the numbers below.', 'simple-history' );

	$top_teaser_text = $teaser_pool[ $week_index % count( $teaser_pool ) ];

	/**
	 * Filter the teaser text shown under the intro.
	 * Return an empty string to hide the teaser.
	 *
	 * @param string $top_teaser_text The teaser text.
	 * @param array  $args The email template args.
	 */
	$top_teaser_text = apply_filters( 'simple_history/email_summary_report/top_teaser_text', $top_teaser_text, $args );
}

/**
 * Filter whether to show the weekly tip.
 *
 * @param bool $show_tip Whether to show the tip. Default true.
 */
$show_tip = apply_filters( 'simple_history/email_summary_report/show_tip', true );
$tip_text = '';

if ( $show_tip && $tips_service instanceof \Simple_History\Services\Tips_Service ) {
	$tip_text = $tips_service->get_tip_for_email( $args );
}

?>

<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<meta name="x-apple-disable-message-reformatting">
	<title><?php echo esc_html( $args['email_subject'] ); ?></title>
	<!--[if mso]>
	<noscript>
		<xml>
			<o:OfficeDocumentSettings>
				<o:PixelsPerInch>96</o:PixelsPerInch>
			</o:OfficeDocumentSettings>
		</xml>
	</noscript>
	<![endif]-->
	<style>
		table, td, div, h1, p {font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;}

		/* Mobile styles */
		@media only screen and (max-width: 639px) {
			.email-container {
				width: calc(100% - 40px) !important;
			}

			.fluid {
				max-width: 100% !important;
				width: 100% !important;
				height: auto !important;
			}

			.mobile-padding {
				padding: 20px !important;
			}

			.mobile-text {
				font-size: 16px !important; line-height: 24px !important;
			}

			.mobile-header {
				font-size: 26px !important;
				line-height: 32px !important;
			}

			.mobile-stat {
				display: block !important;
				width: 100% !important;
				margin-bottom: 10px !important;
			}

			.mobile-breakdown {
				display: block !important;
				width: 48% !important;
				margin-bottom: 15px !important;
			}
		}
	</style>
</head>
<body style="margin: 0; padding: 0; width: 100%; word-break: break-word; -webkit-font-smoothing: antialiased; background-color: #FFF4E4;">

	<div role="article" aria-roledescription="email" lang="<?php echo esc_attr( get_locale() ); ?>" 
		style="text-size-adjust: 100%; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #FFF4E4;">

		<!-- Visually Hidden Preheader Text -->
		<div style="display: none; font-size: 1px; color: #fefefe; line-height: 1px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
			<?php
			$busiest_day = ! empty( $args['busiest_day_name'] ) ? $args['busiest_day_name'] : __( 'No activity', 'simple-history' );

			// Show different message if there was no activity.
			if ( $args['total_events_this_week'] === 0 ) {
				echo esc_html( __( 'No activity last week', 'simple-history' ) );
			} else {
				echo esc_html(
					sprintf(
						/* translators: 1: number of events, 2: day of the week */
						__( '%1$d events last week • %2$s was the busiest day', 'simple-history' ),
						$args['total_events_this_week'],
						$busiest_day
					)
				);
			}
			?>
		</div>

		<!-- Email Container -->
		<table align="center" role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="margin: auto;" class="email-container">

			<!-- Logo -->
			<tr>
				<td style="padding: 40px 0 20px; text-align: left;">
					<table role="presentation" cellspacing="0" cellpadding="0" border="0">
						<tr>
							<td style="vertical-align: middle;">
								<a href="https://simple-history.com/?utm_source=wpadmin&amp;utm_medium=email&amp;utm_campaign=weekly-report" style="text-decoration: none;">
									<img src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL . 'css/simple-history-logo.png' ); ?>"
										width="200" height="41" alt="<?php echo esc_attr( __( 'Simple History', 'simple-history' ) ); ?>"
										border="0" style="height: auto; font-family: sans-serif; font-size: 15px; line-height: 15px; color: #333333; display: block;">
								</a>
							</td>
							<?php
							/**
							 * Filter to add content after the logo, e.g. a "Premium" badge.
							 *
							 * @param string $content HTML content to display after the logo.
							 */
							$logo_after_content = apply_filters( 'simple_history/email_summary_report/logo_after', '' );
							if ( ! empty( $logo_after_content ) ) {
								?>
								<td style="vertical-align: middle; padding-left: 10px;">
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from filter, premium is responsible for escaping.
									echo $logo_after_content;
									?>
								</td>
								<?php
							}
							?>
						</tr>
					</table>
				</td>
			</tr>

			<!-- White Container Starts -->
			<tr>
				<td style="background-color: #ffffff; border-radius: 8px 8px 0 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" class="email-container">
					<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
						<tr>
							<td style="padding: 30px 40px 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; text-align: center; background-color: #ffffff;" 
								class="mobile-padding email-container" role="main">

					<!-- Main Headline -->
					<h1 style="margin: 0 0 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 36px; line-height: 42px; color: #000000; font-weight: 600; text-align: left; text-wrap: balance;"
						class="mobile-header">
						<?php echo esc_html( __( 'Website activity summary', 'simple-history' ) ); ?>
					</h1>

					<!-- Date Range -->
					<p style="margin: 0 0 30px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; line-height: 18px; color: #000000; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; text-align: left;">
						<?php echo esc_html( $args['date_range'] ); ?>
					</p>

					<!-- Summary -->
					<?php
					$summary_text = $args['summary_text'];

					if ( $summary_text === '' ) {
						$summary_text = __( "Here's a summary of activity on your website.", 'simple-history' );
					}
					?>
					<p style="margin: 0 0 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 16px; line-height: 24px; color: #000000; text-align: left;"
						class="mobile-text">
						<?php echo esc_html( $summary_text ); ?>
					</p>

					<p style="margin: 0 0 <?php echo $top_teaser_text ? '10px' : '40px'; ?>; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; line-height: 22px; color: #666666; text-align: left;">
						<?php
						$allowed_html = array(
							'a' => array(
								'href'  => array(),
								'style' => array(),
							),
						);
						$link_style   = 'style="color: #0040FF; text-decoration: underline;"';
						echo wp_kses(
							sprintf(
								/* translators: 1: URL to history page, 2: link style attribute including style="" */
								__( '<a href="%1$s" %2$s>View the Simple History event log</a> on your website for a detailed history of changes and activities.', 'simple-history' ),
								esc_url( $args['history_admin_url'] ),
								$link_style
							),
							$allowed_html
						);
						?>
					</p>

					<?php
					// Premium teaser, shown once under the intro for free users.
					if ( $top_teaser_text ) {
						?>
						<p style="margin: 0 0 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 13px; line-height: 20px; color: #666666; text-align: left;">
							<?php echo esc_html( $top_teaser_text ); ?>
							<a href="<?php echo esc_url( $premium_url ); ?>" style="color: #0040FF; text-decoration: underline;"><?php echo esc_html( __( 'See what\'s included', 'simple-history' ) ); ?></a>
						</p>
						<?php
					}

					if ( $show_main_core_stats ) {
						?>
					<!-- Key Metrics Section -->
					<div style="margin-bottom: 40px;">

						<!-- Website Information -->
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
								<?php echo esc_html( __( 'Website', 'simple-history' ) ); ?>
							</h2>
							<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 24px; line-height: 28px; color: #000000; font-weight: 700; text-align: left; margin-bottom: 5px;">
								<a href="<?php echo esc_url( $args['site_url'] ); ?>" style="color: #0040FF; text-decoration: none;">
									<?php echo esc_html( $args['site_name'] ); ?>
								</a>
							</div>
							<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 16px; line-height: 20px; color: #666; text-align: left;">
								<a href="<?php echo esc_url( $args['site_url'] ); ?>" style="color: #0040FF; text-decoration: none;">
									<?php echo esc_html( $args['site_url_domain'] ); ?>
								</a>
							</div>
						</div>

						<!-- Period Information -->
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
								<?php echo esc_html( __( 'Period', 'simple-history' ) ); ?>
							</h2>
							<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 18px; line-height: 22px; color: #000000; font-weight: 600; text-align: left;">
								<?php echo esc_html( $args['date_range'] ); ?>
							</div>
						</div>

						<!-- This Week's Activity -->
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
								<?php echo esc_html( __( 'Total events', 'simple-history' ) ); ?>
							</h2>
							<?php
							$total_events_url = $args['total_events_this_week'] > 0 && ! empty( $args['stat_urls']['total_events_this_week'] ) ? $args['stat_urls']['total_events_this_week'] : '';

							if ( $total_events_url !== '' ) {
								echo '<a href="' . esc_url( $total_events_url ) . '" style="text-decoration: none; display: block;">';
							}
							?>
							<div style="<?php echo esc_attr( $stat_big_number_style . ( $total_events_url === '' ? ' color: #000000;' : ' color: #0040FF;' ) ); ?>">
								<?php echo esc_html( number_format_i18n( $args['total_events_this_week'] ) ); ?>
							</div>
							<?php
							if ( $total_events_url !== '' ) {
								echo '</a>';
							}
							?>
						</div>

						<!-- Weekly Activity Breakdown -->
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
								<?php echo esc_html( __( 'Event count by day', 'simple-history' ) ); ?>
							</h2>

							<?php
							// Create a lookup array from most_active_days for easy access.
							// Data is already keyed by day number (0-6) to avoid language issues.
							$day_counts = [];
							foreach ( $args['most_active_days'] as $day ) {
								if ( ! isset( $day['day_number'] ) || ! isset( $day['count'] ) ) {
									continue;
								}

								$day_counts[ $day['day_number'] ] = $day['count'];
							}

							// Build days array in chronological order based on actual date range.
							$ordered_days    = [];
							$start_timestamp = $args['date_from_timestamp'] ?? strtotime( '-6 days' );
							$end_timestamp   = $args['date_to_timestamp'] ?? time();

							// Create DateTimeImmutable objects for iteration.
							$current_date = ( new DateTimeImmutable( '@' . $start_timestamp ) )->setTimezone( wp_timezone() );
							$end_date     = ( new DateTimeImmutable( '@' . $end_timestamp ) )->setTimezone( wp_timezone() );

							// Iterate through each day in the range.
							while ( $current_date <= $end_date ) {
								$day_name   = wp_date( 'l', $current_date->getTimestamp() ); // Full day name (e.g., "Monday").
								$day_number = (int) $current_date->format( 'w' ); // Day of week (0=Sunday, 6=Saturday).

								// Get full formatted date for tooltip.
								$full_date = wp_date(
									sprintf(
										/* translators: Full date format for tooltip: "Thursday 2 October 2025" */
										__( 'l j F Y', 'simple-history' )
									),
									$current_date->getTimestamp()
								);

								// Get date in Y-m-d format for URL parameters.
								$date_ymd = $current_date->format( 'Y-m-d' );

								$ordered_days[] = [
									'name'      => $day_name,
									'key'       => $day_number,
									'count'     => $day_counts[ $day_number ] ?? 0,
									'full_date' => $full_date,
									'date_ymd'  => $date_ymd,
								];

								// Move to next day.
								$current_date = $current_date->modify( '+1 day' );
							}
							?>

							<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<?php
									$day_index  = 0;
									$total_days = count( $ordered_days );
									foreach ( $ordered_days as $day_data ) {
										// Build URL with date filter for this specific day.
										$day_url = add_query_arg(
											[
												'date' => 'customRange',
												'from' => $day_data['date_ymd'],
												'to'   => $day_data['date_ymd'],
											],
											$args['history_admin_url']
										);
										?>
										<td style="<?php echo esc_attr( 'width: 14.28%; vertical-align: top; text-align: center;' . ( $day_index < $total_days - 1 ? ' padding-right: 8px;' : '' ) ); ?>" title="<?php echo esc_attr( $day_data['full_date'] ); ?>">
											<a href="<?php echo esc_url( $day_url ); ?>" style="color: #0040FF; text-decoration: none; display: block;">
												<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 12px; color: #000000; text-align: center; font-weight: 500;">
													<?php echo esc_html( substr( $day_data['name'], 0, 3 ) ); ?>
												</div>
												<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 18px; line-height: 22px; color: #0040FF; font-weight: 700; text-align: center; margin-top: 2px;">
													<?php echo esc_html( number_format_i18n( $day_data['count'] ) ); ?>
												</div>
											</a>
										</td>
										<?php
										++$day_index;
									}
									?>
								</tr>
							</table>
						</div>

						<?php
						$empty_sections = [];

						if ( $args['posts_created'] + $args['posts_updated'] > 0 ) {
							?>
						<!-- Posts Section -->
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
								<img src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL . 'css/icons/email/posts.png' ); ?>" width="20" height="20" alt="✎" style="display: inline-block; width: 20px; height: 20px; vertical-align: -3px; margin-right: 6px; border: 0; font-size: 18px; line-height: 20px; color: #000000;"><?php echo esc_html( __( 'Posts and Pages', 'simple-history' ) ); ?>
							</h2>

							<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<td style="width: 50%; vertical-align: top; padding-right: 15px;">
										<?php $render_stat( __( 'Created', 'simple-history' ), $args['posts_created'], 'posts_created' ); ?>
									</td>
									<td style="width: 50%; vertical-align: top; padding-left: 15px;">
										<?php $render_stat( __( 'Edits', 'simple-history' ), $args['posts_updated'], 'posts_updated' ); ?>
									</td>
								</tr>
							</table>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from filter, premium is responsible for escaping.
							echo apply_filters( 'simple_history/email_summary_report/section_content/posts', '', $args );
							?>
						</div>
							<?php
						} else {
							$empty_sections[] = __( 'Posts and Pages', 'simple-history' );
						}
						?>
						<?php
						if ( $args['media_uploads'] + $args['media_edits'] > 0 ) {
							?>
						<!-- Media Section -->
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
								<img src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL . 'css/icons/email/media.png' ); ?>" width="20" height="20" alt="▣" style="display: inline-block; width: 20px; height: 20px; vertical-align: -3px; margin-right: 6px; border: 0; font-size: 18px; line-height: 20px; color: #000000;"><?php echo esc_html( __( 'Media', 'simple-history' ) ); ?>
							</h2>

							<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<td style="width: 50%; vertical-align: top; padding-right: 15px;">
										<?php $render_stat( __( 'Uploads', 'simple-history' ), $args['media_uploads'], 'media_uploads' ); ?>
									</td>
									<td style="width: 50%; vertical-align: top; padding-left: 15px;">
										<?php $render_stat( __( 'Edits', 'simple-history' ), $args['media_edits'], 'media_edits' ); ?>
									</td>
								</tr>
							</table>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from filter, premium is responsible for escaping.
							echo apply_filters( 'simple_history/email_summary_report/section_content/media', '', $args );
							?>
						</div>
							<?php
						} else {
							$empty_sections[] = __( 'Media', 'simple-history' );
						}
						?>
						<?php
						// Comments Section - only show when comments are enabled on the site.
						if ( $args['comments_enabled'] && $args['comments_added'] + $args['comments_approved'] + $args['comments_spam'] > 0 ) {
							?>
							<!-- Comments Section -->
							<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
								<h2 style="margin: 0 0 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
									<img src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL . 'css/icons/email/comments.png' ); ?>" width="20" height="20" alt="❝" style="display: inline-block; width: 20px; height: 20px; vertical-align: -3px; margin-right: 6px; border: 0; font-size: 18px; line-height: 20px; color: #000000;"><?php echo esc_html( __( 'Comments', 'simple-history' ) ); ?>
								</h2>

								<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
									<tr>
										<td style="width: 50%; vertical-align: top; padding-right: 15px;">
											<?php $render_stat( __( 'New', 'simple-history' ), $args['comments_added'], 'comments_added' ); ?>
										</td>
										<td style="width: 50%; vertical-align: top; padding-left: 15px;">
											<?php $render_stat( __( 'Approved', 'simple-history' ), $args['comments_approved'], 'comments_approved' ); ?>
										</td>
									</tr>
									<?php if ( $args['comments_spam'] > 0 ) { ?>
									<tr>
										<td style="width: 50%; vertical-align: top; padding-right: 15px; padding-top: 15px;">
											<?php $render_stat( __( 'Spam', 'simple-history' ), $args['comments_spam'], 'comments_spam' ); ?>
										</td>
										<td style="width: 50%; vertical-align: top; padding-left: 15px; padding-top: 15px;"></td>
									</tr>
									<?php } ?>
								</table>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from filter, premium is responsible for escaping.
								echo apply_filters( 'simple_history/email_summary_report/section_content/comments', '', $args );
								?>
							</div>
							<?php
						} elseif ( $args['comments_enabled'] ) {
							$empty_sections[] = __( 'Comments', 'simple-history' );
						}

						// Notes Section - only show on WordPress 6.9+ where Notes feature exists.
						if ( $args['notes_enabled'] && $args['notes_added'] + $args['notes_resolved'] > 0 ) {
							?>
							<!-- Notes Section (WordPress 6.9+) -->
							<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
								<h2 style="margin: 0 0 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
									<img src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL . 'css/icons/email/notes.png' ); ?>" width="20" height="20" alt="❏" style="display: inline-block; width: 20px; height: 20px; vertical-align: -3px; margin-right: 6px; border: 0; font-size: 18px; line-height: 20px; color: #000000;"><?php echo esc_html( __( 'Notes', 'simple-history' ) ); ?>
								</h2>

								<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
									<tr>
										<td style="width: 50%; vertical-align: top; padding-right: 15px;">
											<?php $render_stat( __( 'Added', 'simple-history' ), $args['notes_added'], 'notes_added' ); ?>
										</td>
										<td style="width: 50%; vertical-align: top; padding-left: 15px;">
											<?php $render_stat( __( 'Resolved', 'simple-history' ), $args['notes_resolved'], 'notes_resolved' ); ?>
										</td>
									</tr>
								</table>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from filter, premium is responsible for escaping.
							echo apply_filters( 'simple_history/email_summary_report/section_content/notes', '', $args );
							?>
							</div>
							<?php
						} elseif ( $args['notes_enabled'] ) {
							$empty_sections[] = __( 'Notes', 'simple-history' );
						}
						?>
						<?php
						if ( $args['successful_logins'] + $args['failed_logins'] + $args['users_created'] + $args['users_updated'] > 0 ) {
							?>
						<!-- Users Section -->
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
								<img src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL . 'css/icons/email/users.png' ); ?>" width="20" height="20" alt="◉" style="display: inline-block; width: 20px; height: 20px; vertical-align: -3px; margin-right: 6px; border: 0; font-size: 18px; line-height: 20px; color: #000000;"><?php echo esc_html( __( 'Users', 'simple-history' ) ); ?>
							</h2>

							<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<td style="width: 50%; vertical-align: top; padding-right: 15px;">
										<?php $render_stat( __( 'Successful logins', 'simple-history' ), $args['successful_logins'], 'successful_logins' ); ?>
									</td>
									<td style="width: 50%; vertical-align: top; padding-left: 15px;">
										<?php $render_stat( __( 'Failed logins', 'simple-history' ), $args['failed_logins'], 'failed_logins' ); ?>
									</td>
								</tr>
								<tr>
									<td style="width: 50%; vertical-align: top; padding-right: 15px; padding-top: 15px;">
										<?php $render_stat( __( 'Created', 'simple-history' ), $args['users_created'], 'users_created' ); ?>
									</td>
									<td style="width: 50%; vertical-align: top; padding-left: 15px; padding-top: 15px;">
										<?php $render_stat( __( 'Profile updates', 'simple-history' ), $args['users_updated'], 'users_updated' ); ?>
									</td>
								</tr>
							</table>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from filter, premium is responsible for escaping.
							echo apply_filters( 'simple_history/email_summary_report/section_content/users', '', $args );
							?>
						</div>
							<?php
						} else {
							$empty_sections[] = __( 'Users', 'simple-history' );
						}
						?>

						<?php
						if ( $args['plugin_activations'] + $args['plugin_deactivations'] > 0 ) {
							?>
						<!-- Plugins Section -->
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
								<img src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL . 'css/icons/email/plugins.png' ); ?>" width="20" height="20" alt="✚" style="display: inline-block; width: 20px; height: 20px; vertical-align: -3px; margin-right: 6px; border: 0; font-size: 18px; line-height: 20px; color: #000000;"><?php echo esc_html( __( 'Plugins', 'simple-history' ) ); ?>
							</h2>

							<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<td style="width: 50%; vertical-align: top; padding-right: 15px;">
										<?php $render_stat( __( 'Activations', 'simple-history' ), $args['plugin_activations'], 'plugin_activations' ); ?>
									</td>
									<td style="width: 50%; vertical-align: top; padding-left: 15px;">
										<?php $render_stat( __( 'Deactivations', 'simple-history' ), $args['plugin_deactivations'], 'plugin_deactivations' ); ?>
									</td>
								</tr>
							</table>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from filter, premium is responsible for escaping.
							echo apply_filters( 'simple_history/email_summary_report/section_content/plugins', '', $args );
							?>
						</div>
							<?php
						} else {
							$empty_sections[] = __( 'Plugins', 'simple-history' );
						}
						?>

						<?php
						if ( $args['theme_switches'] + $args['theme_updates'] > 0 ) {
							?>
						<!-- Themes Section -->
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
								<img src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL . 'css/icons/email/themes.png' ); ?>" width="20" height="20" alt="◐" style="display: inline-block; width: 20px; height: 20px; vertical-align: -3px; margin-right: 6px; border: 0; font-size: 18px; line-height: 20px; color: #000000;"><?php echo esc_html( __( 'Themes', 'simple-history' ) ); ?>
							</h2>

							<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<td style="width: 50%; vertical-align: top; padding-right: 15px;">
										<?php $render_stat( __( 'Switches', 'simple-history' ), $args['theme_switches'], 'theme_switches' ); ?>
									</td>
									<td style="width: 50%; vertical-align: top; padding-left: 15px;">
										<?php $render_stat( __( 'Updates', 'simple-history' ), $args['theme_updates'], 'theme_updates' ); ?>
									</td>
								</tr>
							</table>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from filter, premium is responsible for escaping.
							echo apply_filters( 'simple_history/email_summary_report/section_content/themes', '', $args );
							?>
						</div>
							<?php
						} else {
							$empty_sections[] = __( 'Themes', 'simple-history' );
						}
						?>

						<?php
						if ( $args['wordpress_updates'] > 0 ) {
							?>
						<!-- WordPress Section -->
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
								<img src="<?php echo esc_url( SIMPLE_HISTORY_DIR_URL . 'css/icons/email/wordpress.png' ); ?>" width="20" height="20" alt="Ⓦ" style="display: inline-block; width: 20px; height: 20px; vertical-align: -3px; margin-right: 6px; border: 0; font-size: 18px; line-height: 20px; color: #000000;"><?php echo esc_html( __( 'WordPress core', 'simple-history' ) ); ?>
							</h2>
							<?php $render_stat( __( 'Core updates', 'simple-history' ), $args['wordpress_updates'], 'wordpress_updates', $stat_big_number_style ); ?>
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from filter, premium is responsible for escaping.
							echo apply_filters( 'simple_history/email_summary_report/section_content/wordpress', '', $args );
							?>
						</div>
							<?php
						} else {
							$empty_sections[] = __( 'WordPress core', 'simple-history' );
						}
						?>
						<?php
						// Sections without activity collapse into one line so the email ends sooner.
						if ( ! empty( $empty_sections ) ) {
							?>
							<div style="margin-bottom: 30px;">
								<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 11px; line-height: 16px; color: #6B6B6B; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; text-align: left; margin-bottom: 6px;">
									<?php echo esc_html( __( 'Nothing to report', 'simple-history' ) ); ?>
								</div>
								<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; line-height: 22px; color: #666666; text-align: left;">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: list of section names, e.g. "Media, Themes, and WordPress core" */
											__( 'No activity was logged this week in %s.', 'simple-history' ),
											wp_sprintf( '%l', $empty_sections )
										)
									);
									?>
								</div>
							</div>
							<?php
						}
						?>
						<?php
					}
					?>
					</div>

					<?php echo wp_kses_post( $content_after_core_stats ); ?>

					<!-- View All Events Button -->
					<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
						<tr>
							<td>
								<a href="<?php echo esc_url( $args['history_admin_url'] ); ?>" 
									style="background: #0040FF; border: 16px solid #0040FF; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 16px; line-height: 20px; text-decoration: none; color: #ffffff; display: block; border-radius: 6px; font-weight: 600;">
									<?php echo esc_html( __( 'View Activity Log', 'simple-history' ) ); ?>
								</a>
							</td>
						</tr>
					</table>

					<!-- Weekly Tip -->
					<?php
					if ( $tip_text ) {
						?>
						<div style="margin: 30px 0 0; padding: 15px 20px; background-color: #f6f6f6; border-radius: 8px;">
							<p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; line-height: 22px; color: #000000; text-align: left;">
								<span aria-hidden="true">💡</span>
								<strong><?php echo esc_html( __( 'Tip:', 'simple-history' ) ); ?></strong>
								<?php echo esc_html( $tip_text ); ?>
							</p>
						</div>
						<?php
					}
					?>

					<!-- Upsell Section -->
					<?php
					if ( $show_upsell ) {
						?>
						<div style="margin: 40px 0 25px; padding: 25px; background: linear-gradient(135deg, #FFE4EC 0%, #B4EDE2 100%); border-radius: 8px; border: 1px solid #B4EDE2;">
							<h2 style="margin: 0 0 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;">
								<?php echo esc_html( __( 'Get more from your activity log', 'simple-history' ) ); ?>
							</h2>
							<p style="margin: 0 0 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 16px; line-height: 24px; color: #000000; text-align: left;">
								<?php echo esc_html( __( 'Free logs expire after 60 days. Premium lets you keep them longer — and adds real-time alerts, Slack notifications, CSV export, and log forwarding to syslog or external databases.', 'simple-history' ) ); ?>
							</p>

							<p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 16px; line-height: 24px; text-align: left;">
								<a href="https://simple-history.com/add-ons/premium/?utm_source=wpadmin&amp;utm_medium=email&amp;utm_campaign=weekly-report&amp;utm_content=upsell-block" style="color: #0040FF; text-decoration: underline; font-weight: 600;">
									<?php echo esc_html( __( 'See what Premium includes', 'simple-history' ) ); ?>
								</a>
							</p>
						</div>
						<?php
					}
					?>
								</td>
							</tr>
						</table>
					</td>
				</tr>

		</table>

		<!-- Unsubscribe Text Outside White Container -->
		<table align="center" role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="padding: 40px 0;" class="email-container">
			<tr>
				<td style="padding: 0 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 12px; line-height: 16px; text-align: center; color: #000000;"
					class="mobile-padding">
					<p style="margin: 15px 0 0; font-size: 12px; color: #000000;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: Site name */
								__( 'You\'re receiving this email because you\'re listed as a recipient in the Simple History email report settings for %s.', 'simple-history' ),
								$args['site_name']
							)
						);
						?>
					</p>
					<p style="margin: 10px 0 0; font-size: 12px; color: #000000;">
						<?php
						$allowed_html = array(
							'a' => array(
								'href'  => array(),
								'style' => array(),
							),
						);
						$link_style   = 'style="color: #000000; text-decoration: underline;"';
						echo wp_kses(
							sprintf(
								/* translators: 1: URL to settings page, 2: link style attribute including style="" */
								__( 'To stop receiving these emails, go to <a href="%1$s" %2$s>Settings → Simple History → Email Reports</a> in your WordPress admin and remove your email address.', 'simple-history' ),
								esc_url( $args['settings_url'] ),
								$link_style
							),
							$allowed_html
						);
						?>
					</p>
				</td>
			</tr>
		</table>

	</div>
</body>
</html>
