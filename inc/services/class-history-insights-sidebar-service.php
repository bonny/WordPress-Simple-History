<?php

namespace Simple_History\Services;

use DateTimeImmutable;
use DateInterval;
use DatePeriod;
use Simple_History\Helpers;
use Simple_History\Menu_Manager;
use Simple_History\Events_Stats;
use Simple_History\Stats_View;
use Simple_History\Date_Helper;
use Simple_History\Simple_History;
use Simple_History\Services\Service;

/**
 * Service class that handles the History Insights sidebar widget.
 *
 * This service manages the sidebar widget that displays quick statistics,
 * including today's events, weekly/monthly stats, a chart, and top users.
 * It's independent of the Stats_Service to prevent conflicts with premium add-on.
 */
class History_Insights_Sidebar_Service extends Service {
	/**
	 * Cache duration in minutes for sidebar stats.
	 *
	 * @var int
	 */
	const CACHE_DURATION_MINUTES = 5;

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		// Priority 5 to show after sale promo (priority 4) but before other boxes.
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'output_sidebar_widget' ], 5 );
		add_action( 'simple_history/enqueue_admin_scripts', [ $this, 'enqueue_scripts_and_styles' ] );
	}

	/**
	 * Enqueue scripts and styles for the sidebar widget.
	 */
	public function enqueue_scripts_and_styles() {
		// Enqueue Chart.js library.
		wp_enqueue_script(
			'simple_history_chart.js',
			SIMPLE_HISTORY_DIR_URL . 'js/chart.4.4.8.min.js',
			[ 'jquery' ],
			'4.4.8',
			true
		);

		// Enqueue sidebar chart initialization script.
		wp_enqueue_script(
			'simple-history-insights-sidebar',
			SIMPLE_HISTORY_DIR_URL . 'js/simple-history-insights-sidebar.js',
			[ 'jquery', 'simple_history_chart.js' ],
			SIMPLE_HISTORY_VERSION,
			true
		);

		// Enqueue sidebar-specific styles.
		wp_enqueue_style(
			'simple-history-insights-sidebar',
			SIMPLE_HISTORY_DIR_URL . 'css/simple-history-insights-sidebar.css',
			[],
			SIMPLE_HISTORY_VERSION
		);
	}

	/**
	 * Output HTML for sidebar widget.
	 */
	public function output_sidebar_widget() {
		$num_days_month = Date_Helper::DAYS_PER_MONTH;
		$num_days_week  = Date_Helper::DAYS_PER_WEEK;

		$stats_data = $this->get_quick_stats_data( $num_days_month, $num_days_week );

		$current_user_can_list_users     = current_user_can( 'list_users' );
		$current_user_can_manage_options = current_user_can( 'manage_options' );

		?>
		<div class="postbox sh-PremiumFeaturesPostbox sh-SidebarStats">
			<div class="inside">

				<h3 class="sh-PremiumFeaturesPostbox-title">
					<?php esc_html_e( 'History Insights', 'simple-history' ); ?>
				</h3>

				<?php
				/**
				 * Fires inside the stats sidebar box, after the headline but before any content.
				 */
				do_action( 'simple_history/dropin/stats/today' );

				/**
				 * Fires inside the stats sidebar box, after the headline but before any content.
				 */
				do_action( 'simple_history/dropin/stats/before_content' );

				// Today, 7 days, 30 days.
				echo wp_kses_post( $this->get_events_per_days_stats_html( $stats_data ) );

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->get_chart_data( $num_days_month, $stats_data['chart_data_month'] );

				// Most active users in last 30 days. Only visible to admins.
				echo wp_kses_post( $this->get_most_active_users_html( $current_user_can_list_users, $stats_data ) );

				// Show total events logged. Only visible to admins.
				echo wp_kses_post( $this->get_total_events_logged_html( $current_user_can_manage_options, $stats_data ) );

				echo wp_kses_post( $this->get_cache_info_html( $current_user_can_manage_options ) );

				// Show insights page CTA. Only visible to admins.
				echo wp_kses_post( $this->get_cta_link_html( $current_user_can_manage_options ) );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get data for the quick stats.
	 * Data is cached in transient for CACHE_DURATION_MINUTES minutes.
	 *
	 * @param int $num_days_month Number of days to consider month, to get data for.
	 * @param int $num_days_week Number of days to consider week, to get data for.
	 * @return array<string,mixed> Array with stats data.
	 */
	protected function get_quick_stats_data( $num_days_month, $num_days_week ) {
		$simple_history                  = Simple_History::get_instance();
		$loggers_slugs                   = $simple_history->get_loggers_that_user_can_read( null, 'slugs' );
		$current_user_can_manage_options = current_user_can( 'manage_options' );
		$current_user_can_list_users     = current_user_can( 'list_users' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$args_serialized          = serialize( [ $num_days_month, $num_days_week, $loggers_slugs, $current_user_can_manage_options, $current_user_can_list_users ] );
		$cache_key                = 'sh_quick_stats_data_' . md5( $args_serialized );
		$cache_expiration_seconds = self::CACHE_DURATION_MINUTES * MINUTE_IN_SECONDS;

		$results = get_transient( $cache_key );

		// Uncomment below to test without cache = always run the queries = always fresh data.
		// Claude: please keep this, I need it to always get fresh data when testing/developing.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// $results = false;

		if ( false !== $results ) {
			return $results;
		}

		$results = [
			'num_events_today' => Helpers::get_num_events_today(),
			'num_events_week'  => Helpers::get_num_events_last_n_days( $num_days_week ),
			'num_events_month' => Helpers::get_num_events_last_n_days( $num_days_month ),
			'chart_data_month' => Helpers::get_num_events_per_day_last_n_days( $num_days_month ),
		];

		// Only fetch total_events and current_events_count for admins.
		if ( $current_user_can_manage_options ) {
			$results['total_events']         = Helpers::get_total_logged_events_count();
			$results['current_events_count'] = Helpers::get_current_database_events_count();
		}

		// Only fetch top_users for users who can list users.
		if ( $current_user_can_list_users ) {
			$month_date_from      = DateTimeImmutable::createFromFormat( 'U', Date_Helper::get_last_n_days_start_timestamp( $num_days_month ) );
			$month_date_to        = DateTimeImmutable::createFromFormat( 'U', Date_Helper::get_current_timestamp() );
			$events_stats         = new Events_Stats();
			$results['top_users'] = $events_stats->get_top_users( $month_date_from->getTimestamp(), $month_date_to->getTimestamp(), 5 );
		}

		set_transient( $cache_key, $results, $cache_expiration_seconds );

		return $results;
	}

	/**
	 * Get data for chart.
	 *
	 * @param int   $num_days Number of days to get data for.
	 * @param array $num_events_per_day_for_period Cached chart data from get_quick_stats_data.
	 * @return string HTML.
	 */
	protected function get_chart_data( $num_days, $num_events_per_day_for_period ) {
		ob_start();

		// Period = all dates, so empty ones don't get lost.
		// For "last N days" including today, we go back N-1 days.
		// E.g., "last 30 days" on Oct 7 = Sep 8 to Oct 7 (30 days).
		// Create DateTimeImmutable directly in WordPress timezone to avoid timezone conversion issues.
		$days_ago          = $num_days - 1;
		$period_start_date = new DateTimeImmutable( "-{$days_ago} days", wp_timezone() );
		$period_start_date = new DateTimeImmutable( $period_start_date->format( 'Y-m-d' ) . ' 00:00:00', wp_timezone() );
		$today             = new DateTimeImmutable( 'today', wp_timezone() );
		$interval          = DateInterval::createFromDateString( '1 day' );

		// DatePeriod excludes end date by default.
		// To include today, we need to set end to tomorrow 00:00:00.
		$tomorrow = $today->add( date_interval_create_from_date_string( '1 days' ) );
		$period   = new DatePeriod( $period_start_date, $interval, $tomorrow );

		?>

		<div class="sh-StatsDashboard-stat sh-StatsDashboard-stat--small">
			<div class="sh-StatsDashboard-statLabel">
				<?php
				printf(
					// translators: 1 is number of days.
					esc_html__( 'Daily activity over last %d days', 'simple-history' ),
					esc_html( Date_Helper::DAYS_PER_MONTH )
				);
				?>
			</div>

			<div class="sh-StatsDashboard-statValue">
				<!-- wrapper div so sidebar does not "jump" when loading. so annoying. -->
				<div style="position: relative; height: 0; overflow: hidden; padding-bottom: 40%;">
					<canvas style="position: absolute; left: 0; right: 0;" class="SimpleHistory_SidebarChart_ChartCanvas" width="100" height="40"></canvas>
				</div>
			</div>

			<div class="sh-StatsDashboard-statSubValue">
				<p class="sh-flex sh-justify-between sh-m-0"
					style="margin-top: calc(-1 * var(--sh-spacing-small));"
				>
					<span>
						<?php
						// From date, localized.
						// Example: "September 4, 2025".
						echo esc_html(
							wp_date(
								'M j',
								$period_start_date->getTimestamp()
							)
						);
						?>
					</span>
					<span>
						<?php
						// To date, localized.
						// Example: "October 4, 2025".
						echo esc_html(
							wp_date(
								'M j',
								$today->getTimestamp()
							)
						);
						?>
					</span>
				</p>

			</div>
		</div>
		<?php
		$arr_labels             = array();
		$arr_labels_to_datetime = array();
		$arr_dataset_data       = array();

		foreach ( $period as $dt ) {
			$datef        = _x( 'M j', 'stats: date in rows per day chart', 'simple-history' );
			$str_date     = wp_date( $datef, $dt->getTimestamp() );
			$str_date_ymd = $dt->format( 'Y-m-d' );

			// Get data for this day, if exist
			// Day in object is in format '2014-09-07'.
			$yearDate = $dt->format( 'Y-m-d' );
			$day_data = wp_filter_object_list(
				$num_events_per_day_for_period,
				array(
					'yearDate' => $yearDate,
				)
			);

			$arr_labels[] = $str_date;

			$arr_labels_to_datetime[] = array(
				'label' => $str_date,
				'date'  => $str_date_ymd,
			);

			if ( $day_data ) {
				$day_data           = reset( $day_data );
				$arr_dataset_data[] = $day_data->count;
			} else {
				$arr_dataset_data[] = 0;
			}
		}

		?>
		<input
			type="hidden"
			class="SimpleHistory_SidebarChart_ChartLabels"
			value="<?php echo esc_attr( wp_json_encode( $arr_labels ) ); ?>"
			/>

		<input
			type="hidden"
			class="SimpleHistory_SidebarChart_ChartLabelsToDates"
			value="<?php echo esc_attr( wp_json_encode( $arr_labels_to_datetime ) ); ?>"
			/>

		<input
			type="hidden"
			class="SimpleHistory_SidebarChart_ChartDatasetData"
			value="<?php echo esc_attr( wp_json_encode( $arr_dataset_data ) ); ?>"
			/>

		<?php

		return ob_get_clean();
	}

	/**
	 * Get HTML for number of events per today, 7 days, 30 days.
	 *
	 * @param array $stats_data Array with stats data.
	 * @return string
	 */
	protected function get_events_per_days_stats_html( $stats_data ) {
		// Generate URLs for filtering events by time period.
		$url_today = Helpers::get_filtered_events_url( [ 'date' => 'lastdays:1' ] );
		$url_week  = Helpers::get_filtered_events_url( [ 'date' => 'lastdays:7' ] );
		$url_month = Helpers::get_filtered_events_url( [ 'date' => 'lastdays:30' ] );

		ob_start();

		?>
		<div class="sh-SidebarStats-eventsPerDays">
			<?php
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->get_stat_dashboard_item(
				__( 'Today', 'simple-history' ),
				number_format_i18n( $stats_data['num_events_today'] ),
				_n( 'event', 'events', $stats_data['num_events_today'], 'simple-history' ),
				$url_today
			);

			echo $this->get_stat_dashboard_item(
				// translators: %d is the number of days.
				sprintf( __( '%d days', 'simple-history' ), Date_Helper::DAYS_PER_WEEK ),
				number_format_i18n( $stats_data['num_events_week'] ),
				_n( 'event', 'events', $stats_data['num_events_week'], 'simple-history' ),
				$url_week
			);

			echo $this->get_stat_dashboard_item(
				// translators: %d is the number of days.
				sprintf( __( '%d days', 'simple-history' ), Date_Helper::DAYS_PER_MONTH ),
				number_format_i18n( $stats_data['num_events_month'] ),
				_n( 'event', 'events', $stats_data['num_events_month'], 'simple-history' ),
				$url_month
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get a stat dashboard stats item.
	 *
	 * @param string $stat_label The label text for the stat.
	 * @param string $stat_value The main value text to display.
	 * @param string $stat_subvalue Optional subvalue to display below main value.
	 * @param string $stat_url Optional URL to make the stat label clickable.
	 */
	protected function get_stat_dashboard_item( $stat_label, $stat_value, $stat_subvalue = '', $stat_url = '' ) {
		ob_start();

		$tag   = empty( $stat_url ) ? 'div' : 'a';
		$attrs = empty( $stat_url ) ? '' : sprintf( ' href="%s"', esc_url( $stat_url ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tag is hardcoded 'a' or 'div', $attrs is escaped.
		printf( '<%s class="sh-StatsDashboard-stat sh-StatsDashboard-stat--small"%s>', $tag, $attrs );
		?>
			<span class="sh-StatsDashboard-statLabel"><?php echo esc_html( $stat_label ); ?></span>
			<span class="sh-StatsDashboard-statValue"><?php echo esc_html( $stat_value ); ?></span>
			<?php
			if ( ! empty( $stat_subvalue ) ) {
				?>
				<span class="sh-StatsDashboard-statSubValue">
					<?php echo wp_kses_post( $stat_subvalue ); ?>
				</span>
				<?php
			}
			?>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tag is hardcoded 'a' or 'div'.
		printf( '</%s>', $tag );

		return ob_get_clean();
	}

	/**
	 * Output HTML for most active users data, i.e. avatars and usernames.
	 *
	 * @param bool  $current_user_can_list_users If current user has list users capability.
	 * @param array $stats_data Stats data.
	 * @return string Avatars and usernames if user can view, empty string otherwise.
	 */
	protected function get_most_active_users_html( $current_user_can_list_users, $stats_data ) {
		if ( ! $current_user_can_list_users ) {
			return '';
		}

		if ( ! isset( $stats_data['top_users'] ) ) {
			return '';
		}

		ob_start();

		?>
		<div class="sh-StatsDashboard-stat sh-StatsDashboard-stat--small sh-my-medium">
			<span class="sh-StatsDashboard-statLabel">
				<?php
				printf(
					// translators: 1 is number of days.
					esc_html__( 'Most active users in last %d days', 'simple-history' ),
					esc_html( Date_Helper::DAYS_PER_MONTH )
				);
				?>
				<?php echo wp_kses_post( Helpers::get_tooltip_html( __( 'Only administrators can see user names and avatars.', 'simple-history' ) ) ); ?>
			</span>
			<span class="sh-StatsDashboard-statSubValue">
				<?php Stats_View::output_top_users_avatar_list( $stats_data['top_users'] ); ?>
			</span>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get HTML for total events logged.
	 *
	 * @param bool  $current_user_can_manage_options If current user has manage options capability.
	 * @param array $stats_data Stats data.
	 * @return string HTML.
	 */
	protected function get_total_events_logged_html( $current_user_can_manage_options, $stats_data ) {
		if ( ! $current_user_can_manage_options ) {
			return '';
		}

		if ( ! isset( $stats_data['total_events'] ) || ! isset( $stats_data['current_events_count'] ) ) {
			return '';
		}

		// Get retention period and make it a link.
		$retention_days = Helpers::get_clear_history_interval();
		$retention_text = $retention_days > 0
			? sprintf(
				// translators: %d is the number of days.
				_n( '%d day', '%d days', $retention_days, 'simple-history' ),
				$retention_days
			)
			: __( 'forever', 'simple-history' );

		// Make retention period a link to settings with anchor to retention section.
		$settings_url          = Helpers::get_settings_page_url() . '#simple_history_clear_log_info';
		$retention_text_linked = sprintf(
			'<a href="%s" class="sh-whitespace-nowrap"><b>%s</b></a>',
			esc_url( $settings_url ),
			$retention_text
		);

		// Build main message.
		$msg_text = sprintf(
			// translators: 1 is current events in database, 2 is total events logged, 3 is retention period as a link (e.g., "60 days").
			__( '<b>%1$s events</b> in database (%2$s logged in total). Events auto-removed after %3$s.', 'simple-history' ),
			number_format_i18n( $stats_data['current_events_count'] ),
			number_format_i18n( $stats_data['total_events'] ),
			$retention_text_linked
		);

		// Append tooltip.
		$msg_text .= Helpers::get_tooltip_html( __( 'Database count shows browsable events. Total shows all events ever logged, including those auto-removed. Only administrators can see these event counts.', 'simple-history' ) );

		// Return concatenated result.
		return wp_kses_post( "<p class='sh-my-medium'>" . $msg_text . '</p>' );
	}

	/**
	 * Get HTML for cache info.
	 *
	 * @param bool $current_user_can_manage_options If current user has manage options capability.
	 * @return string HTML.
	 */
	protected function get_cache_info_html( $current_user_can_manage_options ) {
		ob_start();

		?>
		<p class="sh-my-medium">
			<?php
			if ( $current_user_can_manage_options ) {
				printf(
					/* translators: %d is the number of minutes between cache refreshes */
					esc_html__( 'Insights are calculated from all events. Updates every %d minutes.', 'simple-history' ),
					absint( self::CACHE_DURATION_MINUTES )
				);
			} else {
				printf(
					/* translators: %d is the number of minutes between cache refreshes */
					esc_html__( 'Insights are based on events you can view. Updates every %d minutes.', 'simple-history' ),
					absint( self::CACHE_DURATION_MINUTES )
				);
			}
			?>
		</p>

		<?php
		return ob_get_clean();
	}

	/**
	 * Get HTML for stats and summaries link.
	 *
	 * @param bool $current_user_can_manage_options If current user has manage options capability.
	 * @return string HTML.
	 */
	protected function get_cta_link_html( $current_user_can_manage_options ) {
		if ( ! $current_user_can_manage_options ) {
			return '';
		}

		$stats_page_url = Menu_Manager::get_admin_url_by_slug( 'simple_history_stats_page' );

		// Bail if no stats page url (user has no access to stats page).
		if ( empty( $stats_page_url ) ) {
			return '';
		}

		ob_start();
		?>
		<p>
			<a class="sh-PremiumFeaturesPostbox-button" href="<?php echo esc_url( $stats_page_url ); ?>">
				<?php esc_html_e( 'See all History Insights', 'simple-history' ); ?>
			</a>
		</p>
		<?php
		return ob_get_clean();
	}
}
