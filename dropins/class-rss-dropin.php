<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;
use Simple_History\Simple_History;
use Simple_History\Log_Query;
use Simple_History\Log_Levels;

/**
 * Dropin Name: Global RSS Feed
 * Dropin URI: http://simple-history.com/
 * Author: Pär Thernström
 */
class RSS_Dropin extends Dropin {
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// TODO: Investigate if this include is actually needed.
		// get_editable_roles() is checked but never called in this file.
		// This might be leftover code copied from class-privacy-logger.php.
		// If not needed, this include should be removed.
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . '/wp-admin/includes/user.php';
		}

		// Check the status of the RSS feed.
		$this->is_rss_enabled();

		// Generate a rss secret, if it does not exist.
		if ( ! $this->get_rss_secret() ) {
			$this->update_rss_secret();
		}

		add_action( 'init', array( $this, 'check_for_rss_feed_request' ) );

		// Add settings with priority 15 so it' added after the main Simple History settings.
		add_action( 'admin_menu', array( $this, 'add_settings' ), 15 );
	}

	/**
	 * Add settings for the RSS feed.
	 *
	 * Also regenerates the secret if requested.
	 */
	public function add_settings() {
		// Register a setting to keep track of the RSS feed status (enabled/disabled).
		register_setting(
			Simple_History::SETTINGS_GENERAL_OPTION_GROUP,
			'simple_history_enable_rss_feed',
			array(
				'sanitize_callback' => array(
					Helpers::class,
					'sanitize_checkbox_input',
				),
			)
		);

		/**
		 * Start new section for RSS feed.
		 *
		 * @var string $settings_section_rss_id ID of the section
		 */
		$settings_section_rss_id = 'simple_history_settings_section_rss';

		/**
		 * Filters the title for the feeds section headline.
		 *
		 * @var string $rss_section_title
		 */
		$rss_section_title = apply_filters(
			'simple_history/feeds/settings_section_title',
			_x( 'RSS and JSON feeds', 'feeds settings headline', 'simple-history' )
		);

		Helpers::add_settings_section(
			$settings_section_rss_id,
			[ $rss_section_title, 'rss_feed' ],
			array( $this, 'settings_section_output' ),
			Simple_History::SETTINGS_MENU_SLUG // same slug as for options menu page.
		);

		// Enable/Disable RSS feed.
		add_settings_field(
			'simple_history_enable_rss_feed',
			Helpers::get_settings_field_title_output( __( 'Enable', 'simple-history' ), 'toggle-on' ),
			array( $this, 'settings_field_rss_enable' ),
			Simple_History::SETTINGS_MENU_SLUG,
			$settings_section_rss_id
		);

		// If RSS is activated we display other fields.
		if ( $this->is_rss_enabled() ) {
			// RSS address.
			add_settings_field(
				'simple_history_rss_feed',
				Helpers::get_settings_field_title_output( __( 'Address', 'simple-history' ), 'link' ),
				array( $this, 'settings_field_rss' ),
				Simple_History::SETTINGS_MENU_SLUG,
				$settings_section_rss_id
			);

			// Link button to regenerate RSS secret.
			add_settings_field(
				'simple_history_rss_feed_regenerate_secret',
				Helpers::get_settings_field_title_output( __( 'Regenerate', 'simple-history' ), 'autorenew' ),
				array( $this, 'settings_field_rss_regenerate' ),
				Simple_History::SETTINGS_MENU_SLUG,
				$settings_section_rss_id
			);
		}

		// Create new RSS secret.
		$create_secret_nonce_name = 'simple_history_rss_secret_regenerate_nonce';
		$create_nonce_ok          = isset( $_GET[ $create_secret_nonce_name ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ $create_secret_nonce_name ] ) ), 'simple_history_rss_update_secret' );

		if ( $create_nonce_ok ) {
			$this->update_rss_secret();

			// Add updated-message and store in transient and then redirect
			// This is the way options.php does it.
			$msg = __( 'Created new secret RSS address', 'simple-history' );
			add_settings_error( 'simple_history_rss_feed_regenerate_secret', 'simple_history_rss_feed_regenerate_secret', $msg, 'updated' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );

			/**
			 * Fires after RSS secret has been updated.
			 */
			do_action( 'simple_history/rss_feed/secret_updated' );

			$goback = esc_url_raw( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			wp_redirect( $goback );
			exit;
		}
	}

	/**
	 * Check if RSS feed is enabled or disabled.
	 *
	 * @return bool true if enabled, false if disabled
	 */
	public function is_rss_enabled() {
		$is_enabled = false;

		// User has never used the plugin we disable RSS feed.
		if ( $this->get_rss_secret() === false && get_option( 'simple_history_enable_rss_feed' ) === false ) {
			// We disable RSS by default, we use 0/1 to prevent fake disabled with bools from functions returning false for unset.
			update_option( 'simple_history_enable_rss_feed', '0' );
		} elseif ( get_option( 'simple_history_enable_rss_feed' ) === false ) {
			// User was using the plugin before RSS feed became disabled by default.
			// We activate RSS to prevent a "breaking change".
			update_option( 'simple_history_enable_rss_feed', '1' );
			$is_enabled = true;
		} elseif ( get_option( 'simple_history_enable_rss_feed' ) === '1' ) {
			$is_enabled = true;
		}

		return $is_enabled;
	}

	/**
	 * Output for settings field that show current RSS address.
	 */
	public function settings_field_rss_enable() {
		/**
		 * Filters the text for the RSS enable checkbox.
		 *
		 * @var string $enable_rss_text
		 */
		$enable_rss_text = apply_filters(
			'simple_history/feeds/enable_feeds_checkbox_text',
			__( 'Enable feed', 'simple-history' )
		);

		?>
		<input value="1" type="checkbox" id="simple_history_enable_rss_feed" name="simple_history_enable_rss_feed" <?php checked( $this->is_rss_enabled(), 1 ); ?> />
		<label for="simple_history_enable_rss_feed"><?php echo esc_html( $enable_rss_text ); ?></label>

		<?php
		// Show premium teaser for JSON feed below the enable checkbox.
		echo wp_kses_post(
			Helpers::get_premium_feature_teaser(
				__( 'JSON Feed for Automation', 'simple-history' ),
				[
					__( 'Structured data format for easy parsing', 'simple-history' ),
					__( 'Connect to Zapier, Make, n8n, or custom scripts', 'simple-history' ),
					__( 'Real-time monitoring and alerting', 'simple-history' ),
				],
				'premium_feeds_settings',
				__( 'Enable JSON Feed', 'simple-history' )
			)
		);
	}

	/**
	 * Check if current request is a request for the RSS feed.
	 */
	public function check_for_rss_feed_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['simple_history_get_rss'] ) ) {
			$this->output_rss();
			exit;
		}
	}

	/**
	 * Get the RSS secret.
	 *
	 * @return bool|string RSS secret or false if not set.
	 */
	public function get_rss_secret() {
		return get_option( 'simple_history_rss_secret' );
	}

	/**
	 * Output RSS.
	 */
	public function output_rss() {
		$rss_secret_option = get_option( 'simple_history_rss_secret' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rss_secret_get = sanitize_text_field( wp_unslash( $_GET['rss_secret'] ?? '' ) );

		if ( empty( $rss_secret_option ) || empty( $rss_secret_get ) ) {
			die();
		}

		/** @var bool $rss_show */
		$rss_show = true;

		/**
		 * Filter if RSS feed should be shown or not.
		 * Default is true.
		 * @since 1.3.8
		 * @param bool $rss_show
		 */
		$rss_show = apply_filters( 'simple_history/rss_feed_show', $rss_show );

		if ( ! $rss_show || ! $this->is_rss_enabled() ) {
			wp_die( 'Nothing here.' );
		}

		header( 'Content-Type: text/xml; charset=utf-8' );

		echo '<?xml version="1.0" encoding="UTF-8"?>';

		$self_link = $this->get_rss_address();

		$title = sprintf(
			/* translators: %s blog name */
			__( 'History for %s', 'simple-history' ),
			get_bloginfo( 'name' ),
		);

		$description = sprintf(
			/* translators: %s blog name */
			esc_html__( 'WordPress History for %s', 'simple-history' ),
			get_bloginfo( 'name' )
		);

		if ( $rss_secret_option === $rss_secret_get ) {
			echo PHP_EOL;

			?>
			<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
				<channel>
					<title><?php echo esc_xml( $title ); ?></title>
					<description><?php echo esc_xml( $description ); ?></description>
					<link><?php echo esc_url( get_bloginfo( 'url' ) ); ?></link>
					<atom:link href="<?php echo esc_url( $self_link ); ?>" rel="self" type="application/atom+xml" />
					<?php

					// Override capability check: if you have a valid rss_secret_key you can read it all.
					$action_tag = 'simple_history/loggers_user_can_read/can_read_single_logger';
					add_filter( $action_tag, '__return_true', 10, 0 );

					// Modify header time output so it does not show relative date or time ago-format
					// Because we don't know when a user reads the RSS feed, time ago format may be very inaccurate.
					add_filter( 'simple_history/header_just_now_max_time', '__return_zero' );
					add_filter( 'simple_history/header_time_ago_max_time', '__return_zero' );

					// Set args from query string.
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$args = $this->set_log_query_args_from_query_string( $_GET );

					/**
					 * Filters the arguments passed to `SimpleHistoryLogQuery()` when fetching the RSS feed
					 *
					 * @example Change number of posts to retrieve in RSS feed.
					 *
					 * // This example changes the number of posts in the RSS feed to 50 from the default 10.
					 *
					 * ```php
					 *  add_filter(
					 *    'simple_history/rss_feed_args',
					 *      function( $args ) {
					 *        $args['posts_per_page'] = 50;
					 *        return $args;
					 *     }
					 * );
					 *
					 * @example Change number of posts to retrieve in RSS feed.
					 *
					 * // This example changes the number of posts in the RSS feed to 20 from the default 10.
					 *
					 * ```php
					 *  add_filter(
					 *    'simple_history/rss_feed_args',
					 *      function( $args ) {
					 *        $args['posts_per_page'] = 20;
					 *        return $args;
					 *     }
					 * );
					 *
					 * @param array $args SimpleHistoryLogQuery arguments.
					 * @return array
					 */
					$args = apply_filters( 'simple_history/rss_feed_args', $args );

					$logQuery     = new Log_Query();
					$queryResults = $logQuery->query( $args );

					// Remove capability override after query is done
					// remove_action( $action_tag, '__return_true', 10 );.
					if ( is_wp_error( $queryResults ) ) {
						$queryResults = array( 'log_rows' => array() );
					}

					foreach ( $queryResults['log_rows'] as $row ) {
						$header_output  = $this->simple_history->get_log_row_header_output( $row );
						$text_output    = $this->simple_history->get_log_row_plain_text_output( $row );
						$details_output = $this->simple_history->get_log_row_details_output( $row );

						// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- URL reference.
						// See http://cyber.law.harvard.edu/rss/rss.html#ltguidgtSubelementOfLtitemgt.
						$item_guid = esc_url( add_query_arg( 'SimpleHistoryGuid', $row->id, home_url() ) );
						$item_link = esc_url( add_query_arg( 'SimpleHistoryGuid', $row->id, home_url() ) );

						/**
						 * Filter the guid/link URL used in RSS feed.
						 * Link will be esc_url'ed by simple history, so no need to do that in your filter
						 *
						 * @since 2.0.23
						 *
						 * @param string $item_guid link.
						 * @param object $row
						 */
						$item_link = apply_filters( 'simple_history/rss_item_link', $item_link, $row );
						$item_link = esc_url( $item_link );

						$item_title = sprintf(
							'%2$s',
							Log_Levels::get_log_level_translated( $row->level ),
							wp_kses( $text_output, array() )
						);

						$level_output = sprintf(
							// translators: %s is the severity level of the log.
							esc_html__( 'Severity level: %1$s', 'simple-history' ),
							Log_Levels::get_log_level_translated( $row->level )
						);

						$wp_kses_attrs = array(
							'a'      => array(
								'href'            => array(),
								'class'           => array(),
								'data-ip-address' => array(),
								'target'          => array(),
								'title'           => array(),
							),
							'em'     => array(),
							'span'   => array(
								'class'       => array(),
								'title'       => array(),
								'aria-hidden' => array(),
							),
							'time'   => array(
								'datetime' => array(),
								'class'    => array(),
							),
							'strong' => array(
								'class' => array(),
							),
							'div'    => array(
								'class'    => array(),
								'tabindex' => array(),
							),
							'p'      => array(),
							'del'    => array(),
							'ins'    => array(),
							'table'  => array(
								'class' => array(),
							),
							'tbody'  => array(),
							'tr'     => array(),
							'td'     => array(
								'class' => array(),
							),
							'col'    => array(
								'class' => array(),
							),
						);
						?>
						<item>
						<title><?php echo esc_xml( $item_title ); ?></title>
							<description><![CDATA[
								<p><?php echo wp_kses( $header_output, $wp_kses_attrs ); ?></p>
								<p><?php echo wp_kses( $text_output, $wp_kses_attrs ); ?></p>
								<div><?php echo wp_kses( $details_output, $wp_kses_attrs ); ?></div>
								<p><?php echo wp_kses( $level_output, $wp_kses_attrs ); ?></p>
								<?php
								// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
								$occasions = $row->subsequentOccasions - 1;

								if ( $occasions ) {
									echo '<p>';
									esc_html(
										sprintf(
											// translators: %1$s is the number of times this log has been repeated.
											_n( '+%1$s occasion', '+%1$s occasions', $occasions, 'simple-history' ),
											(int) $occasions
										)
									);
									echo '</p>';
								}
								?>
							]]></description>
							<?php
							// author must be email to validate, but the field is optional, so we skip it.
							/* <author><?php echo $row->initiator ?></author> */
							?>
							<pubDate><?php echo esc_xml( gmdate( 'D, d M Y H:i:s', strtotime( $row->date ) ) ); ?> GMT</pubDate>
							<guid isPermaLink="false"><![CDATA[<?php echo esc_xml( $item_guid ); ?>]]></guid>
							<link><![CDATA[<?php echo esc_url( $item_link ); ?>]]></link>
						</item>
						<?php
					}

					?>
				</channel>
			</rss>
			<?php
		} else {
			// RSS secret was not ok.
			echo PHP_EOL;
			?>
			<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
				<channel>
					<title><?php echo esc_xml( $title ); ?></title>
					<description><?php echo esc_xml( $description ); ?></description>
					<link><?php echo esc_url( home_url() ); ?></link>
					<atom:link href="<?php echo esc_url( $self_link ); ?>" rel="self" type="application/atom+xml" />
					<item>
						<title><?php echo esc_xml( __( 'Wrong RSS secret', 'simple-history' ) ); ?></title>
						<description><?php echo esc_xml( __( 'Your RSS secret for Simple History RSS feed is wrong. Please see WordPress settings for current link to the RSS feed.', 'simple-history' ) ); ?></description>
						<pubDate><?php echo esc_xml( gmdate( 'D, d M Y H:i:s', time() ) ); ?> GMT</pubDate>
						<guid><?php echo esc_url( add_query_arg( 'SimpleHistoryGuid', 'wrong-secret', home_url() ) ); ?></guid>
					</item>
				</channel>
			</rss>
			<?php
		}
	}

	/**
	 * Create a new RSS secret.
	 *
	 * @return string new secret
	 */
	public function update_rss_secret() {
		$rss_secret = '';

		for ( $i = 0; $i < 20; $i++ ) {
			$rss_secret .= chr( random_int( 97, 122 ) );
		}

		update_option( 'simple_history_rss_secret', $rss_secret );

		return $rss_secret;
	}

	/**
	 * Output for settings field that show current RSS address.
	 */
	public function settings_field_rss() {
		echo '<p class="simple_history_rss_feed_query_parameters">';
		echo wp_kses(
			sprintf(
				/* translators: %s is a link to the documentation */
				__( 'Query parameters can be used to control what to include in the feed. <a href="%1$s" class="sh-ExternalLink" target="_blank">View documentation</a>.', 'simple-history' ),
				esc_url( Helpers::get_tracking_url( 'https://simple-history.com/docs/feeds/', 'docs_rss_help' ) )
			),
			[
				'a' => [
					'href'   => [],
					'target' => [],
					'class'  => [],
				],
			]
		);
		echo '</p>';
		echo '<br />';

		printf(
			'
			<p>
				<strong>
					%1$s
				</strong>
			</p>
			',
			esc_html__( 'RSS feed', 'simple-history' ) // 1
		);
		
		printf(
			'<p>
				<code>
					<a id="simple_history_rss_feed_address" href="%1$s">%1$s</a>
				</code>
			</p>',
			esc_url( $this->get_rss_address() )
		);

		/**
		 * Fires after the RSS address has been output.
		 *
		 * @param RSS_Dropin $instance
		 */
		do_action( 'simple_history/feeds/after_address', $this );
	}

	/**
	 * Output for settings field that regenerates the RSS address/secret
	 */
	public function settings_field_rss_regenerate() {
		$update_link = esc_url( add_query_arg( '', '' ) );
		$update_link = wp_nonce_url( $update_link, 'simple_history_rss_update_secret', 'simple_history_rss_secret_regenerate_nonce' );

		echo '<p>';
		esc_html_e( 'You can generate a new secret for the feeds. This is useful if you think that the address has fallen into the wrong hands.', 'simple-history' );
		echo '</p>';

		echo '<p>';
		printf(
			'<a class="button" href="%1$s">%2$s</a>',
			esc_url( $update_link ), // 1
			esc_html__( 'Generate new address', 'simple-history' ) // 2
		);

		echo '</p>';
	}

	/**
	 * Get the URL to the RSS feed.
	 *
	 * @return string URL
	 */
	public function get_rss_address() {
		$rss_secret = get_option( 'simple_history_rss_secret' );

		$rss_address = add_query_arg(
			array(
				'simple_history_get_rss' => '1',
				'rss_secret'             => $rss_secret,
			),
			get_bloginfo( 'url' ) . '/'
		);

		return $rss_address;
	}

	/**
	 * Content for section intro. Leave it be, even if empty.
	 * Called from add_sections_setting.
	 */
	public function settings_section_output() {
		?>
		<p>
			<strong><?php esc_html_e( 'Monitor your site activity in real-time with feeds.', 'simple-history' ); ?></strong>
		</p>
		<p>
			<?php esc_html_e( 'Get updates on logins, content changes, plugin activity and more—delivered to your feed reader or monitoring tools. Perfect for staying informed without constantly checking your dashboard.', 'simple-history' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'Make sure you only share the feeds with people you trust, since they can contain sensitive or confidential information.', 'simple-history' ); ?>
		</p>
		<?php

		/**
		 * Allow premium to add additional feed information.
		 *
		 * @since 4.0
		 */
		do_action( 'simple_history/feeds/settings_section_description' );
	}

	/**
	 * Update log query args from query string.
	 *
	 * @param array $args Query string from $_GET.
	 * @return array Updated log query args.
	 */
	public function set_log_query_args_from_query_string( $args ) {
		$posts_per_page = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 10;
		$paged          = isset( $args['paged'] ) ? (int) $args['paged'] : 1;
		$date_from      = isset( $args['date_from'] ) ? sanitize_text_field( $args['date_from'] ) : null;
		$date_to        = isset( $args['date_to'] ) ? sanitize_text_field( $args['date_to'] ) : null;
		$loggers        = isset( $args['loggers'] ) ? sanitize_text_field( $args['loggers'] ) : null;
		$messages       = isset( $args['messages'] ) ? sanitize_text_field( $args['messages'] ) : null;
		$loglevels      = isset( $args['loglevels'] ) ? sanitize_text_field( $args['loglevels'] ) : null;

		// Exclusion filters - useful for subscribing to events excluding your own actions.
		$exclude_loggers   = isset( $args['exclude_loggers'] ) ? sanitize_text_field( $args['exclude_loggers'] ) : null;
		$exclude_messages  = isset( $args['exclude_messages'] ) ? sanitize_text_field( $args['exclude_messages'] ) : null;
		$exclude_loglevels = isset( $args['exclude_loglevels'] ) ? sanitize_text_field( $args['exclude_loglevels'] ) : null;
		$exclude_user      = isset( $args['exclude_user'] ) ? (int) $args['exclude_user'] : null;
		$exclude_users     = isset( $args['exclude_users'] ) ? sanitize_text_field( $args['exclude_users'] ) : null;

		return [
			'posts_per_page'    => $posts_per_page,
			'paged'             => $paged,
			'date_from'         => $date_from,
			'date_to'           => $date_to,
			'loggers'           => $loggers,
			'messages'          => $messages,
			'loglevels'         => $loglevels,
			'exclude_loggers'   => $exclude_loggers,
			'exclude_messages'  => $exclude_messages,
			'exclude_loglevels' => $exclude_loglevels,
			'exclude_user'      => $exclude_user,
			'exclude_users'     => $exclude_users,
		];
	}
}
