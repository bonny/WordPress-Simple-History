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
		add_action("simple_history/history_page/after_gui", array( $this, "gui_page_filters") );	
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
		
				<h3>Filter history</h3>

				<p>
					<input type="search" placeholder="Search" name="search">
				</p>

				<p>
					<select name="loglevels" class="SimpleHistory__filters__filter SimpleHistory__filters__filter--loglevel" style="width: 300px" placeholder="All log levels" multiple>
						<option value="debug" data-color="#CEF6D8">debug</option>
						<option value="info" data-color="white">info</option>
						<option value="notice" data-color="rgb(219, 219, 183)">notice</option>
						<option value="warning" data-color="#F7D358">warning</option>
						<option value="error" data-color="#F79F81">error</option>
						<option value="critical" data-color="#FA5858">critical</option>
						<option value="alert" data-color="rgb(199, 69, 69)">alert</option>
						<option value="emergency" data-color="#DF0101">emergency</option>
					</select>						
				</p>
			
				<p>
					<select name="loggers" class="SimpleHistory__filters__filter SimpleHistory__filters__filter--logger" style="width: 300px" 
							placeholder="All messages" multiple>
						<?php
						foreach ($loggers_user_can_read as $logger) {
							$logger_info = $logger["instance"]->getInfo();
							printf(
								'<option value="%2$s">%3$s</option>',
								$logger["name"], // 1
								$logger["instance"]->slug, // 2
								$logger_info["search_label"]
							);
						}
						?>
					</select>						
				</p>

				<p>
					<input type="text"
							name = "user"
							class="SimpleHistory__filters__filter SimpleHistory__filters__filter--user" 
							style="width: 300px" 
							placeholder="All users" />
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
							placeholder="All dates" multiple>
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
					<button class="button js-SimpleHistoryFilterDropin-doFilter">Filter</button>
				</p>
			
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
		$sql_users = $wpdb->prepare(
			'SELECT ID as id, user_login, user_nicename, user_email, display_name FROM %1$s
			WHERE 
				user_login LIKE "%%%2$s%%"
				OR user_nicename LIKE "%%%2$s%%"
				OR user_email LIKE "%%%2$s%%"
				OR display_name LIKE "%%%2$s%%"
			',
			$wpdb->users,
			$wpdb->esc_like( $q )
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

