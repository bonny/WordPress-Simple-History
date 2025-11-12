<?php

namespace Simple_History\Dropins;

/**
 * Dropin Name: Sidebar with eventstats.
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 *
 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service instead.
 */
class Sidebar_Stats_Dropin extends Dropin {
	/**
	 * Called when dropin is loaded.
	 *
	 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service instead.
	 */
	public function loaded() {
	}

	/**
	 * Enqueue scripts.
	 *
	 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service::enqueue_scripts_and_styles() instead.
	 */
	public function on_admin_enqueue_scripts() {
		_deprecated_function( __METHOD__, '5.18.1', 'Simple_History\Services\History_Insights_Sidebar_Service::enqueue_scripts_and_styles()' );
	}

	/**
	 * Run JS in footer to generate chart.
	 *
	 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service::output_admin_footer_script() instead.
	 */
	public function on_admin_footer() {
		_deprecated_function( __METHOD__, '5.18.1', 'Simple_History\Services\History_Insights_Sidebar_Service::output_admin_footer_script()' );
	}

	/**
	 * Get data for chart.
	 *
	 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service::get_chart_data() instead.
	 * @param int   $num_days Number of days to get data for.
	 * @param array $num_events_per_day_for_period Cached chart data from get_quick_stats_data.
	 * @return string HTML.
	 */
	protected function get_chart_data( $num_days, $num_events_per_day_for_period ) {
		_deprecated_function( __METHOD__, '5.18.1', 'Simple_History\Services\History_Insights_Sidebar_Service::get_chart_data()' );
		return '';
	}

	/**
	 * Get HTML for stats and summaries link.
	 *
	 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service::get_cta_link_html() instead.
	 * @param bool $current_user_can_manage_options If current user has manage options capability.
	 * @return string HTML.
	 */
	protected function get_cta_link_html( $current_user_can_manage_options ) {
		_deprecated_function( __METHOD__, '5.18.1', 'Simple_History\Services\History_Insights_Sidebar_Service::get_cta_link_html()' );
		return '';
	}

	/**
	 * Get data for the quick stats.
	 *
	 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service::get_quick_stats_data() instead.
	 * @param int $num_days_month Number of days to consider month, to get data for.
	 * @param int $num_days_week Number of days to consider week, to get data for.
	 * @return array<string,mixed> Array with stats data.
	 */
	protected function get_quick_stats_data( $num_days_month, $num_days_week ) {
		_deprecated_function( __METHOD__, '5.18.1', 'Simple_History\Services\History_Insights_Sidebar_Service::get_quick_stats_data()' );
		return [];
	}

	/**
	 * Output HTML for sidebar.
	 *
	 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service::output_sidebar_widget() instead.
	 */
	public function on_sidebar_html() {
		_deprecated_function( __METHOD__, '5.18.1', 'Simple_History\Services\History_Insights_Sidebar_Service::output_sidebar_widget()' );
	}

	/**
	 * Get HTML for cache info.
	 *
	 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service::get_cache_info_html() instead.
	 * @param bool $current_user_can_manage_options If current user has manage options capability.
	 * @return string HTML.
	 */
	protected function get_cache_info_html( $current_user_can_manage_options ) {
		_deprecated_function( __METHOD__, '5.18.1', 'Simple_History\Services\History_Insights_Sidebar_Service::get_cache_info_html()' );
		return '';
	}

	/**
	 * Get HTML for total events logged.
	 *
	 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service::get_total_events_logged_html() instead.
	 * @param bool  $current_user_can_manage_options If current user has manage options capability.
	 * @param array $stats_data Stats data.
	 * @return string HTML.
	 */
	protected function get_total_events_logged_html( $current_user_can_manage_options, $stats_data ) {
		_deprecated_function( __METHOD__, '5.18.1', 'Simple_History\Services\History_Insights_Sidebar_Service::get_total_events_logged_html()' );
		return '';
	}

	/**
	 * Output HTML for most active users data, i.e. avatars and usernames.
	 *
	 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service::get_most_active_users_html() instead.
	 * @param bool  $current_user_can_list_users If current user has list users capability.
	 * @param array $stats_data Stats data.
	 * @return string Avatars and usernames if user can view, empty string otherwise.
	 */
	protected function get_most_active_users_html( $current_user_can_list_users, $stats_data ) {
		_deprecated_function( __METHOD__, '5.18.1', 'Simple_History\Services\History_Insights_Sidebar_Service::get_most_active_users_html()' );
		return '';
	}

	/**
	 * Get HTML for number of events per today, 7 days, 30 days.
	 *
	 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service::get_events_per_days_stats_html() instead.
	 * @param array $stats_data Array with stats data.
	 * @return string
	 */
	protected function get_events_per_days_stats_html( $stats_data ) {
		_deprecated_function( __METHOD__, '5.18.1', 'Simple_History\Services\History_Insights_Sidebar_Service::get_events_per_days_stats_html()' );
		return '';
	}

	/**
	 * Get the SQL for the loggers that the current user is allowed to read.
	 *
	 * @deprecated 5.18.1
	 * @return string
	 */
	protected function get_sql_loggers_in() {
		_deprecated_function( __METHOD__, '5.18.1' );
		return '';
	}

	/**
	 * Get the number of users that have done something today.
	 *
	 * @deprecated 5.18.1
	 * @return int
	 */
	protected function get_num_users_today() {
		_deprecated_function( __METHOD__, '5.18.1' );
		return 0;
	}

	/**
	 * Get number of other sources (not wp_user).
	 *
	 * @deprecated 5.18.1
	 * @return int Number of other sources.
	 */
	protected function get_other_sources_count() {
		_deprecated_function( __METHOD__, '5.18.1' );
		return 0;
	}

	/**
	 * Get a stat dashboard stats item.
	 *
	 * @deprecated 5.18.1 Use History_Insights_Sidebar_Service::get_stat_dashboard_item() instead.
	 * @param string $stat_label The label text for the stat.
	 * @param string $stat_value The main value text to display.
	 * @param string $stat_subvalue Optional subvalue to display below main value.
	 * @param string $stat_url Optional URL to make the stat label clickable.
	 * @return string
	 */
	protected function get_stat_dashboard_item( $stat_label, $stat_value, $stat_subvalue = '', $stat_url = '' ) {
		_deprecated_function( __METHOD__, '5.18.1', 'Simple_History\Services\History_Insights_Sidebar_Service::get_stat_dashboard_item()' );
		return '';
	}
}
