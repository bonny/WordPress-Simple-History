<?php

/*
Dropin Name: Filter GUI
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistoryFilterDropin {

	// Simple History instance
	private $sh;

	function __construct($sh) {

		$this->sh = $sh;

		add_action("simple_history/enqueue_admin_scripts", array($this, "enqueue_admin_scripts"));
		add_action("simple_history/history_page/before_gui", array( $this, "gui_page_filters") );
		add_action("simple_history/dashboard/before_gui", array( $this, "gui_page_filters") );
		add_action("wp_ajax_simple_history_filters_search_user", array( $this, "ajax_simple_history_filters_search_user") );

	}

	public function enqueue_admin_scripts() {

		$file_url = plugin_dir_url(__FILE__);

		wp_enqueue_script("simple_history_FilterDropin", $file_url . "SimpleHistoryFilterDropin.js", array("jquery"), SimpleHistory::VERSION, true);

		wp_enqueue_style("simple_history_FilterDropin", $file_url . "SimpleHistoryFilterDropin.css", null, SimpleHistory::VERSION);

	}

	public function gui_page_filters() {

		$loggers_user_can_read = $this->sh->getLoggersThatUserCanRead();

		?>
		<div class="SimpleHistory__filters">

			<form class="SimpleHistory__filters__form js-SimpleHistory__filters__form">

				<!-- <h3><?php _e("Filter history", "simple-history") ?></h3> -->

				<p>
					<input type="search" class="SimpleHistoryFilterDropin-searchInput" placeholder="<?php _e("", "simple-history"); ?>" name="search">
					<button class="button SimpleHistoryFilterDropin-doFilterButton SimpleHistoryFilterDropin-doFilterButton--first js-SimpleHistoryFilterDropin-doFilter"><?php _e("Search events", "simple-history") ?></button>
					<!-- <br> -->
					<button type="button" class="SimpleHistoryFilterDropin-showMoreFilters SimpleHistoryFilterDropin-showMoreFilters--first js-SimpleHistoryFilterDropin-showMoreFilters"><?php _ex("Show options", "Filter dropin: button to show more search options", "simple-history") ?></button>
				</p>

				<div class="SimpleHistory__filters__moreFilters js-SimpleHistory__filters__moreFilters">
					
					<p>
						<select name="loglevels" class="SimpleHistory__filters__filter SimpleHistory__filters__filter--loglevel" style="width: 300px" placeholder="<?php _e("All log levels", "simple-history") ?>" multiple>
							<option value="debug" data-color="#CEF6D8"><?php echo $this->sh->getLogLevelTranslated("Debug") ?></option>
							<option value="info" data-color="white"><?php echo $this->sh->getLogLevelTranslated("Info") ?></option>
							<option value="notice" data-color="rgb(219, 219, 183)"><?php echo $this->sh->getLogLevelTranslated("Notice") ?></option>
							<option value="warning" data-color="#F7D358"><?php echo $this->sh->getLogLevelTranslated("Warning") ?></option>
							<option value="error" data-color="#F79F81"><?php echo $this->sh->getLogLevelTranslated("Error") ?></option>
							<option value="critical" data-color="#FA5858"><?php echo $this->sh->getLogLevelTranslated("Critical") ?></option>
							<option value="alert" data-color="rgb(199, 69, 69)"><?php echo $this->sh->getLogLevelTranslated("Alert") ?></option>
							<option value="emergency" data-color="#DF0101"><?php echo $this->sh->getLogLevelTranslated("Emergency") ?></option>
						</select>
					</p>

					<p>
						<select name="messages" class="SimpleHistory__filters__filter SimpleHistory__filters__filter--logger" style="width: 300px"
								placeholder="<?php _e("All messages", "simple-history") ?>" multiple>
							<?php
							foreach ($loggers_user_can_read as $logger) {

								$logger_info = $logger["instance"]->getInfo();
								$logger_slug = $logger["instance"]->slug;

								// Get labels for logger
								if ( isset( $logger_info["labels"]["search"] ) ) {

									printf('<optgroup label="%1$s">', esc_attr( $logger_info["labels"]["search"]["label"] ) );

									// If all activity
									if ( ! empty( $logger_info["labels"]["search"]["label_all"] ) ) {

										$arr_all_search_messages = array();
										foreach ( $logger_info["labels"]["search"]["options"] as $option_key => $option_messages ) {
											$arr_all_search_messages = array_merge($arr_all_search_messages, $option_messages);
										}

										foreach ($arr_all_search_messages as $key => $val) {
											$arr_all_search_messages[ $key ] = $logger_slug . ":" . $val;
										}

										printf('<option value="%2$s">%1$s</option>', esc_attr( $logger_info["labels"]["search"]["label_all"] ), esc_attr( implode(",", $arr_all_search_messages) ));

									}

									// For each specific search option
									foreach ( $logger_info["labels"]["search"]["options"] as $option_key => $option_messages ) {

										foreach ($option_messages as $key => $val) {
											$option_messages[ $key ] = $logger_slug . ":" . $val;
										}

										$str_option_messages = implode(",", $option_messages);
										printf('<option value="%2$s">%1$s</option>', esc_attr( $option_key ), esc_attr( $str_option_messages ));

									}

									printf('</optgroup>');

								}

							}
							?>
						</select>
					</p>

					<p>
						<input type="text"
								name = "user"
								class="SimpleHistory__filters__filter SimpleHistory__filters__filter--user"
								style="width: 300px"
								placeholder="<?php _e("All users", "simple-history") ?>" />
					</p>

					<?php
					global $wpdb;
					$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;
					$loggers_user_can_read_sql_in = $this->sh->getLoggersThatUserCanRead(null, "sql");
					$sql_dates = sprintf('
						SELECT DISTINCT ( date_format(DATE, "%%Y-%%m") ) AS yearMonth
						FROM %s
						WHERE logger IN %s
						ORDER BY yearMonth DESC
						', $table_name, // 1
						$loggers_user_can_read_sql_in // 2
					);

					$result_months = $wpdb->get_results($sql_dates);
					?>
					<p>
						<select class="SimpleHistory__filters__filter SimpleHistory__filters__filter--date"
								name="months"
								placeholder="<?php echo _e("All dates", "simple-history") ?>" multiple>
							<?php
							foreach ($result_months as $row) {
								printf(
									'<option value="%1$s">%2$s</option>',
									$row->yearMonth,
									date_i18n( "F Y", strtotime($row->yearMonth) )
								);
							}
							?>
						</select>
					</p>

					<p>
						<button class="button SimpleHistoryFilterDropin-doFilterButton SimpleHistoryFilterDropin-doFilterButton--second js-SimpleHistoryFilterDropin-doFilter"><?php _e("Search events", "simple-history") ?></button>
						<button type="button" class="SimpleHistoryFilterDropin-showMoreFilters SimpleHistoryFilterDropin-showMoreFilters--second js-SimpleHistoryFilterDropin-showMoreFilters"><?php _ex("Hide options", "Filter dropin: button to hide more search options", "simple-history") ?></button>
					</p>


				</div><!-- // more filters -->

				<!--
				<p>
					<button class="button js-SimpleHistoryFilterDropin-doFilter"><?php _e("Search", "simple-history") ?></button>
				</p>
				-->

			</form>

		</div>
		<?php

	} // function

	/**
	 * Return users
	 */
	public function ajax_simple_history_filters_search_user() {

		$q = isset( $_GET["q"] ) ? $_GET["q"] : "";
		$page_limit = isset( $_GET["page_limit"] ) ? (int) $_GET["page_limit"] : "";

		if ( ! $q || ! $page_limit ) {
			return;
		}

		// Search both current users and all logged rows,
		// because a user can change email
		// search in context: user_id, user_email, user_login
		// search in wp_users: login, nicename, user_email

		// Can't get this simple query to work, so using my own query instead
		/*
		$wp_users = get_users( array(
			"search" => "*{$q}*"
		));
		*/
		global $wpdb;

		if ( method_exists($wpdb, "esc_like") ) {
			$str_like = $wpdb->esc_like( $q );
		} else {
			$str_like = like_escape( esc_sql( $q ) );
		}

		$sql_users = $wpdb->prepare(
			'SELECT ID as id, user_login, user_nicename, user_email, display_name FROM %1$s
			WHERE
				user_login LIKE "%%%2$s%%"
				OR user_nicename LIKE "%%%2$s%%"
				OR user_email LIKE "%%%2$s%%"
				OR display_name LIKE "%%%2$s%%"
			',
			$wpdb->users,
			$str_like
		);

		$results_user = $wpdb->get_results( $sql_users );

		// add gravatars to user array
		array_walk( $results_user, array($this, "add_gravatar_to_user_array") );

		$data = array(
			"results" => array(
			),
			"more" => false,
			"context" => array()
		);

		$data["results"] = array_merge( $data["results"], $results_user );

		wp_send_json_success( $data );

	} // function

	function add_gravatar_to_user_array(& $val, $index) {

		$val->text = sprintf(
			'%1$s - %2$s',
			$val->user_login,
			$val->user_email
		);

		$val->gravatar = $this->sh->get_avatar( $val->user_email, "18", "mm");

	}

} // end class
