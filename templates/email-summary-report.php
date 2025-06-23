<?php
/**
 * Template for weekly emails.
 */

defined( 'ABSPATH' ) || exit;

$learn_how_to_unsubscribe_url = 'https://simple-history.com/support/weekly-summary-email-unsubscribe/';
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<meta name="x-apple-disable-message-reformatting">
	<title><?php echo esc_html( sprintf( __( 'Simple History: Weekly Activity Summary', 'simple-history' ) ) ); ?></title>
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
		@media only screen and (max-width: 599px) {
			.email-container {
				width: 100% !important;
				margin: auto !important;
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
				font-size: 16px !important;
				line-height: 24px !important;
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
	
	<div role="article" aria-roledescription="email" lang="<?php echo esc_attr( get_locale() ); ?>" style="text-size-adjust: 100%; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #FFF4E4;">
		
		<!-- Visually Hidden Preheader Text -->
		<div style="display: none; font-size: 1px; color: #fefefe; line-height: 1px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: number of events, 2: day of the week */
					__( '%1$d events this week • %2$s was your busiest day', 'simple-history' ),
					$args['total_events_this_week'],
					$args['most_active_day_1_name']
				)
			);
			?>
		</div>
		
		<!-- Email Container -->
		<table align="center" role="presentation" cellspacing="0" cellpadding="0" border="0" width="500" style="margin: auto;" class="email-container">
			
			<!-- Logo on Background -->
			<tr>
				<td style="padding: 40px 0 20px; text-align: left;">
					<img src="https://simple-history.com/wp/wp-content/uploads/2023/09/SH_logo_NEW-768x156.png" width="200" height="41" alt="<?php echo esc_attr( __( 'Simple History', 'simple-history' ) ); ?>" border="0" style="height: auto; font-family: sans-serif; font-size: 15px; line-height: 15px; color: #333333; display: block;">
				</td>
			</tr>
			
			<!-- White Container Starts -->
			<tr>
				<td style="background-color: #ffffff; border-radius: 8px 8px 0 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" class="email-container">
					<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
						<tr>
							<td style="padding: 30px 40px 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; text-align: center; background-color: #ffffff;" class="mobile-padding email-container" role="main">
								
					<!-- Main Headline -->
					<h1 style="margin: 0 0 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 36px; line-height: 42px; color: #000000; font-weight: 600; text-align: left; text-wrap: balance;" class="mobile-header"><?php echo esc_html( __( 'Website Weekly Activity Summary', 'simple-history' ) ); ?></h1>
					
					<!-- Date Range -->
					<p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; line-height: 18px; color: #000000; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; text-align: left;">
						<?php echo esc_html( $args['date_range'] ); ?> 
					</p>

					<!-- Site name -->
					<p style="margin: 5px 0 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; line-height: 18px; color: #000000; font-weight: 500; text-align: left;">
						<a href="<?php echo esc_url( $args['site_url'] ); ?>" style="color: #0040FF; text-decoration: none;">
							<?php echo esc_html( $args['site_name'] ); ?>
						</a>
					</p>
					
					<!-- Domain -->
					<p style="margin: 5px 0 30px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; line-height: 18px; color: #000000; font-weight: 500; text-align: left;">
						<a href="<?php echo esc_url( $args['site_url'] ); ?>" style="color: #0040FF; text-decoration: none;">
							<?php echo esc_html( $args['site_url_domain'] ); ?>
						</a>
					</p>
					
					<!-- Subtitle -->
					<p style="margin: 0 0 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 18px; line-height: 26px; color: #000000; text-align: left;" class="mobile-text"><?php echo esc_html( __( 'Here\'s a summary of what Simple History recorded happening on your WordPress site this week.', 'simple-history' ) ); ?></p>
					
					<!-- Key Metrics Section -->
					<div style="margin-bottom: 40px;">
						
						<!-- This Week's Activity -->
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;"><?php echo esc_html( __( 'Events this week', 'simple-history' ) ); ?></h2>
							<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 36px; line-height: 42px; color: #000000; font-weight: 700; text-align: left;"><?php echo esc_html( number_format_i18n( $args['total_events_this_week'] ) ); ?></div>
						</div>
						
						<!-- Most Active Days -->
						<?php
						$has_active_days = false;
						if ( ( $args['most_active_day_1_count'] ) > 0 || 
							 ( $args['most_active_day_2_count'] ) > 0 || 
							 ( $args['most_active_day_3_count'] ) > 0 ) {
							$has_active_days = true;
						}
						?>
						<?php if ( $has_active_days ) { ?>
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;"><?php echo esc_html( __( 'Most Active Days', 'simple-history' ) ); ?></h2>
							
							<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<?php if ( ( $args['most_active_day_1_count'] ) > 0 ) { ?>
									<td style="width: 33.33%; vertical-align: top; padding-right: 15px;">
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; color: #000000; text-align: left;"><?php echo esc_html( $args['most_active_day_1_name'] ); ?></div>
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 24px; line-height: 28px; color: #000000; font-weight: 700; text-align: left; margin-top: 2px;"><?php echo esc_html( number_format_i18n( $args['most_active_day_1_count'] ) ); ?></div>
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; color: #666; text-align: left;"><?php echo esc_html( __( 'events', 'simple-history' ) ); ?></div>
									</td>
									<?php } ?>
									<?php if ( ( $args['most_active_day_2_count'] ) > 0 ) { ?>
									<td style="width: 33.33%; vertical-align: top; padding-right: 15px;">
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; color: #000000; text-align: left;"><?php echo esc_html( $args['most_active_day_2_name'] ); ?></div>
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 24px; line-height: 28px; color: #000000; font-weight: 700; text-align: left; margin-top: 2px;"><?php echo esc_html( number_format_i18n( $args['most_active_day_2_count'] ) ); ?></div>
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; color: #666; text-align: left;"><?php echo esc_html( __( 'events', 'simple-history' ) ); ?></div>
									</td>
									<?php } ?>
									<?php if ( ( $args['most_active_day_3_count'] ) > 0 ) { ?>
									<td style="width: 33.33%; vertical-align: top;">
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; color: #000000; text-align: left;"><?php echo esc_html( $args['most_active_day_3_name'] ); ?></div>
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 24px; line-height: 28px; color: #000000; font-weight: 700; text-align: left; margin-top: 2px;"><?php echo esc_html( number_format_i18n( $args['most_active_day_3_count'] ) ); ?></div>
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; color: #666; text-align: left;"><?php echo esc_html( __( 'events', 'simple-history' ) ); ?></div>
									</td>
									<?php } ?>
								</tr>
							</table>
						</div>
						<?php } ?>
						
						<!-- Most Active Users -->
						<?php
						$has_active_users = false;
						if ( ( $args['most_active_user_1_count'] ) > 0 || 
							 ( $args['most_active_user_2_count'] ) > 0 || 
							 ( $args['most_active_user_3_count'] ) > 0 ) {
							$has_active_users = true;
						}
						?>
						<?php if ( $has_active_users ) { ?>
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;"><?php echo esc_html( __( 'Most Active Users', 'simple-history' ) ); ?></h2>
							
							<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<?php if ( ( $args['most_active_user_1_count'] ) > 0 ) { ?>
									<td style="width: 33.33%; vertical-align: top; padding-right: 15px;">
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; color: #000000; text-align: left;"><?php echo esc_html( $args['most_active_user_1_name'] ); ?></div>
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 24px; line-height: 28px; color: #000000; font-weight: 700; text-align: left; margin-top: 2px;"><?php echo esc_html( number_format_i18n( $args['most_active_user_1_count'] ) ); ?></div>
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; color: #666; text-align: left;"><?php echo esc_html( __( 'events', 'simple-history' ) ); ?></div>
									</td>
									<?php } ?>
									<?php if ( ( $args['most_active_user_2_count'] ) > 0 ) { ?>
									<td style="width: 33.33%; vertical-align: top; padding-right: 15px;">
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; color: #000000; text-align: left;"><?php echo esc_html( $args['most_active_user_2_name'] ); ?></div>
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 24px; line-height: 28px; color: #000000; font-weight: 700; text-align: left; margin-top: 2px;"><?php echo esc_html( number_format_i18n( $args['most_active_user_2_count'] ) ); ?></div>
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; color: #666; text-align: left;"><?php echo esc_html( __( 'events', 'simple-history' ) ); ?></div>
									</td>
									<?php } ?>
									<?php if ( ( $args['most_active_user_3_count'] ) > 0 ) { ?>
									<td style="width: 33.33%; vertical-align: top;">
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; color: #000000; text-align: left;"><?php echo esc_html( $args['most_active_user_3_name'] ); ?></div>
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 24px; line-height: 28px; color: #000000; font-weight: 700; text-align: left; margin-top: 2px;"><?php echo esc_html( number_format_i18n( $args['most_active_user_3_count'] ) ); ?></div>
										<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; color: #666; text-align: left;"><?php echo esc_html( __( 'events', 'simple-history' ) ); ?></div>
									</td>
									<?php } ?>
								</tr>
							</table>
						</div>
						<?php } ?>
						
						<!-- Total Events Since Install -->
						<div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 2px solid #000000;">
							<h2 style="margin: 0 0 10px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600; text-align: left;"><?php echo esc_html( __( 'Total Events Since Install', 'simple-history' ) ); ?></h2>
							<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 36px; line-height: 42px; color: #000000; font-weight: 700; text-align: left;"><?php echo esc_html( number_format_i18n( $args['total_events_since_install'] ) ); ?></div>
						</div>
						
					</div>
					
					<!-- View All Events Button -->
					<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto 60px;">
						<tr>
							<td style="border-radius: 6px; background: #0040FF;">
								<a href="https://simple-history.com" style="background: #0040FF; border: 16px solid #0040FF; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 16px; line-height: 20px; text-decoration: none; color: #ffffff; display: block; border-radius: 6px; font-weight: 600;">
									<?php echo esc_html( __( 'View All Events', 'simple-history' ) ); ?>
								</a>
							</td>
						</tr>
					</table>
					
					<!-- Upsell Section -->
					<div style="text-align: center; margin: 60px 0 25px; padding: 25px; background: linear-gradient(135deg, #FFE4EC 0%, #B4EDE2 100%); border-radius: 8px; border: 1px solid #B4EDE2;">
						<h2 style="margin: 0 0 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 20px; line-height: 26px; color: #000000; font-weight: 600;"><?php echo esc_html( __( 'Want More Insights?', 'simple-history' ) ); ?></h2>
						<p style="margin: 0 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 18px; line-height: 22px; color: #000000;"><?php echo esc_html( __( 'Simple History Premium includes detailed activity breakdowns, user insights, security monitoring, and weekly trends.', 'simple-history' ) ); ?></p>
						
						<p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 18px; line-height: 22px; color: #000000;">
							<a href="https://simple-history.com/add-ons/premium/" style="color: #0040FF; text-decoration: underline; font-weight: 500;"><?php echo esc_html( __( 'Learn More About Premium', 'simple-history' ) ); ?></a>
						</p>
					</div>
					
								</td>
							</tr>
						</table>
					</td>
				</tr>
			
			<!-- Footer -->
			<tr>
				<td style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; line-height: 20px; text-align: center; color: #000000; background-color: #ffffff; border-radius: 0 0 8px 8px;" class="email-container">
				</td>
			</tr>
			
		</table>
		
		<!-- Unsubscribe Text Outside White Container -->
		<table align="center" role="presentation" cellspacing="0" cellpadding="0" border="0" width="500" style="margin: 40px auto;" class="email-container">
			<tr>
				<td style="padding: 20px 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 12px; line-height: 16px; text-align: center; color: #000000;" class="mobile-padding">
					<p style="margin: 15px 0 0; font-size: 12px; color: #000000;">
						<?php echo esc_html( __( 'You\'re receiving this email because your email address was entered in the WordPress admin settings for Simple History.', 'simple-history' ) ); ?>
					</p>
					<p style="margin: 10px 0 0; font-size: 12px; color: #000000;">
						<?php echo esc_html( __( 'This email was auto-generated and sent from www.simple-history.com.', 'simple-history' ) ); ?>
						<a href="<?php echo esc_url( $learn_how_to_unsubscribe_url ); ?>" style="color: #000000; text-decoration: underline;"><?php echo esc_html( __( 'Learn how to unsubscribe/stop receiving emails', 'simple-history' ) ); ?></a>.
					</p>
				</td>
			</tr>
		</table>
		
	</div>
</body>
</html>
