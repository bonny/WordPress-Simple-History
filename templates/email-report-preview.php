<?php
/**
 * Template for weekly emails.
 */

defined( 'ABSPATH' ) || exit;

$site_name = get_bloginfo( 'name' );
$site_url = get_bloginfo( 'url' );
$date_range = sprintf(
	/* translators: 1: start date, 2: end date */
	__( '%1$s to %2$s', 'simple-history' ),
	date_i18n( get_option( 'date_format' ), strtotime( '-7 days' ) ),
	date_i18n( get_option( 'date_format' ), time() )
);

?>
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="x-apple-disable-message-reformatting">
	<title>Simple History: Weekly Activity Summary</title>
	
	<!--[if mso]>
	<noscript>
		<xml>
			<o:OfficeDocumentSettings>
				<o:AllowPNG/>
				<o:PixelsPerInch>96</o:PixelsPerInch>
			</o:OfficeDocumentSettings>
		</xml>
	</noscript>
	<![endif]-->
	
	<style>
		/* Reset styles */
		html, body {
			margin: 0 !important;
			padding: 0 !important;
			height: 100% !important;
			width: 100% !important;
			background: #FFF4E4;
		}
		
		* {
			-ms-text-size-adjust: 100%;
			-webkit-text-size-adjust: 100%;
		}
		
		div[style*="margin: 16px 0"] {
			margin: 0 !important;
		}
		
		#MessageViewBody, #MessageWebViewDiv {
			width: 100% !important;
		}
		
		table {
			border-collapse: collapse !important;
			border-spacing: 0 !important;
			table-layout: fixed !important;
			margin: 0 auto !important;
		}
		
		table table table {
			table-layout: auto;
		}
		
		th {
			font-weight: normal;
		}
		
		img {
			-ms-interpolation-mode: bicubic;
			max-width: 100%;
			border: 0;
			height: auto;
			line-height: 100%;
			outline: none;
			text-decoration: none;
		}
		
		a {
			text-decoration: none;
		}
		
		/* Dark mode styles */
		@media (prefers-color-scheme: dark) {
			.email-bg { background-color: #1a1a1a !important; }
			.email-container { background-color: #2d2d30 !important; }
		}
		
		[data-ogsc] .email-bg { background-color: #1a1a1a !important; }
		[data-ogsc] .email-container { background-color: #2d2d30 !important; }
		
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

	<body width="100%" style="margin: 0; padding: 0 !important; mso-line-height-rule: exactly; background-color: #FFF4E4;" class="email-bg">
	<center style="width: 100%; background-color: #FFF4E4;" class="email-bg">
		
		<!-- Visually Hidden Preheader Text -->
		<div style="display: none; font-size: 1px; color: #fefefe; line-height: 1px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
			Your weekly WordPress activity summary - see what happened on your site this week
		</div>
		
		<!-- Email Container -->
		<table align="center" role="presentation" cellspacing="0" cellpadding="0" border="0" width="500" style="margin: auto;" class="email-container">
			
			<!-- Logo on Background -->
			<tr>
				<td style="padding: 40px 0 20px; text-align: center;">
					<img src="https://simple-history.com/wp/wp-content/uploads/2023/09/SH_logo_NEW-768x156.png" width="200" height="41" alt="Simple History - WordPress Activity Log Plugin" border="0" style="height: auto; font-family: sans-serif; font-size: 15px; line-height: 15px; color: #333333; display: block; margin: 0 auto;">
				</td>
			</tr>
			
			<!-- White Container Starts -->
			<tr>
				<td style="background-color: #ffffff; border-radius: 8px 8px 0 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" class="email-container">
					<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
						<tr>
							<td style="padding: 30px 40px 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; text-align: center; background-color: #ffffff;" class="mobile-padding email-container">
					
					<!-- Main Headline -->
					<h1 style="margin: 0 0 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 30px; line-height: 38px; color: #000000; font-weight: 600;" class="mobile-header">Weekly Activity Summary for <a href="https://www.simple-history.com" style="color: #0040FF; text-decoration: none;">www.simple-history.com</a></h1>
					
					<!-- Subtitle -->
					<p style="margin: 0 0 40px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 18px; line-height: 26px; color: #000000;" class="mobile-text">Here's what Simple History recorded happening on your WordPress site this week.</p>
					
					<!-- Key Metrics Section -->
					<div style="text-align: center; margin-bottom: 40px;">
						
						<!-- Top Stats Row -->
						<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 30px;">
							<tr>
								<!-- Total Events -->
								<td style="width: 33.33%; padding: 20px 10px; background-color: #B4EDE2; border-radius: 8px; text-align: center;" class="mobile-stat">
									<h3 style="margin: 0 0 5px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; line-height: 18px; color: #000000; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Total Events</h3>
									<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 36px; line-height: 40px; color: #000000; font-weight: 700;">293</div>
								</td>
								<td style="width: 5px;"></td>
								<!-- Total Users -->
								<td style="width: 33.33%; padding: 20px 10px; background-color: #FFE4EC; border-radius: 8px; text-align: center;" class="mobile-stat">
									<h3 style="margin: 0 0 5px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; line-height: 18px; color: #000000; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Total Users</h3>
									<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 36px; line-height: 40px; color: #000000; font-weight: 700;">6</div>
								</td>
								<td style="width: 5px;"></td>
								<!-- Active Users -->
								<td style="width: 33.33%; padding: 20px 10px; background-color: #FFF4E4; border-radius: 8px; text-align: center;" class="mobile-stat">
									<h3 style="margin: 0 0 5px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 14px; line-height: 18px; color: #000000; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Most Active</h3>
									<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 16px; line-height: 20px; color: #000000; font-weight: 600;">Multiple Users</div>
								</td>
							</tr>
						</table>
						
						<!-- Activity Breakdown -->
						<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
							<tr>
								<!-- User Profile Actions -->
								<td style="width: 23%; padding: 15px 8px; text-align: center;" class="mobile-breakdown">
									<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 28px; line-height: 32px; color: #0040FF; font-weight: 700; margin-bottom: 5px;">42</div>
									<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 12px; line-height: 16px; color: #000000; font-weight: 500;">User Profile</div>
								</td>
								<td style="width: 2%;"></td>
								<!-- Content Actions -->
								<td style="width: 23%; padding: 15px 8px; text-align: center;" class="mobile-breakdown">
									<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 28px; line-height: 32px; color: #0040FF; font-weight: 700; margin-bottom: 5px;">31</div>
									<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 12px; line-height: 16px; color: #000000; font-weight: 500;">Content</div>
								</td>
								<td style="width: 2%;"></td>
								<!-- Plugin Actions -->
								<td style="width: 23%; padding: 15px 8px; text-align: center;" class="mobile-breakdown">
									<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 28px; line-height: 32px; color: #0040FF; font-weight: 700; margin-bottom: 5px;">75</div>
									<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 12px; line-height: 16px; color: #000000; font-weight: 500;">Plugins</div>
								</td>
								<td style="width: 2%;"></td>
								<!-- Media Actions -->
								<td style="width: 23%; padding: 15px 8px; text-align: center;" class="mobile-breakdown">
									<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 28px; line-height: 32px; color: #0040FF; font-weight: 700; margin-bottom: 5px;">0</div>
									<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 12px; line-height: 16px; color: #000000; font-weight: 500;">Media</div>
								</td>
							</tr>
						</table>
					</div>
					
					
					<!-- Total Events Since Install -->
					<div style="text-align: center; margin-bottom: 30px; padding: 20px; background-color: #FFF4E4; border-radius: 8px; border: 1px solid #B4EDE2;">
						<p style="margin: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 16px; line-height: 22px; color: #000000;">
							A total of <strong style="color: #0040FF;">22,327 events</strong> have been logged since Simple History was installed.
						</p>
					</div>
					
					<!-- Secondary Button -->
					<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto 40px;">
						<tr>
							<td style="border-radius: 6px; background: #0040FF;">
								<a href="https://simple-history.com" style="background: #0040FF; border: 16px solid #0040FF; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size: 16px; line-height: 20px; text-decoration: none; color: #ffffff; display: block; border-radius: 6px; font-weight: 600;">
									View All Events
								</a>
							</td>
						</tr>
					</table>
					
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
						You're receiving this email because your email address was entered in the WordPress admin settings for Simple History.
					</p>
					<p style="margin: 10px 0 0; font-size: 12px; color: #000000;">
						This email was auto-generated and sent from www.simple-history.com. <a href="https://simple-history.com" style="color: #000000; text-decoration: underline;">Learn how to unsubscribe/stop receiving emails</a>.
					</p>
				</td>
			</tr>
			
		</table>
		
	</center>
</body>
</html>
