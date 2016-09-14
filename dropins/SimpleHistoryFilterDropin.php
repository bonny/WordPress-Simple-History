<?php

defined( 'ABSPATH' ) or die();

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

		wp_enqueue_script("simple_history_FilterDropin", $file_url . "SimpleHistoryFilterDropin.js", array("jquery"), SIMPLE_HISTORY_VERSION, true);

		wp_enqueue_style("simple_history_FilterDropin", $file_url . "SimpleHistoryFilterDropin.css", null, SIMPLE_HISTORY_VERSION);

	}


	public function gui_page_filters() {

		$loggers_user_can_read = $this->sh->getLoggersThatUserCanRead();

		/**
		 * Filter that determines if search filters should be visible directly on page load
		 *
		 * Set to true to show the filters when page is loaded
		 * If false then user must click "Show options"
		 *
		 * @since 2.1.2
		 *
		 * @param bool $show_more_filters_on_load Default false
		 */
		$show_more_filters_on_load = false;
		$show_more_filters_on_load = apply_filters("SimpleHistoryFilterDropin/show_more_filters_on_load" , $show_more_filters_on_load);

		?>
		<div class="SimpleHistory__filters <?php echo $show_more_filters_on_load ? "is-showingMoreFilters" : "" ?>">

			<form class="SimpleHistory__filters__form js-SimpleHistory__filters__form">

				<!-- <h3><?php _e("Filter history", "simple-history") ?></h3> -->

				<?php

				// Start months filter
				global $wpdb;
				$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;
				$loggers_user_can_read_sql_in = $this->sh->getLoggersThatUserCanRead(null, "sql");

				// Get unique months
				$cache_key = "sh_filter_unique_months";
				$result_months = get_transient( $cache_key );

				if ( false === $result_months ) {

					$sql_dates = sprintf('
						SELECT DISTINCT ( date_format(DATE, "%%Y-%%m") ) AS yearMonth
						FROM %s
						WHERE logger IN %s
						ORDER BY yearMonth DESC
						', $table_name, // 1
						$loggers_user_can_read_sql_in // 2
					);

					$result_months = $wpdb->get_results($sql_dates);

					set_transient( $cache_key, $result_months, HOUR_IN_SECONDS );

				}

				$arr_days_and_pages = array();

				// Default month = current month
				// Mainly for performance reasons, since often
				// it's not the users intention to view all events,
				// but just the latest
				$this_month = date("Y-m");

				// Determine if we limit the date range by default
				$daysToShow = 1;

				// Start with the latest day
				$numEvents = $this->sh->get_unique_events_for_days($daysToShow);
				$numPages = $numEvents / $this->sh->get_pager_size();

				$arr_days_and_pages[] = array(
					"daysToShow" => $daysToShow,
					"numPages" => $numPages
				);

				// Example on my server with lots of brute force attacks (causing log to not load)
				// 166434 / 15 = 11 000 pages for last 7 days
				// 1 day = 3051 / 15 = 203 pages = still much but better than 11000 pages!

				if ( $numPages < 20 ) {

					// Not that many things the last day. Let's try to expand to 7 days instead.
					$daysToShow = 7;
					$numEvents = $this->sh->get_unique_events_for_days($daysToShow);
					$numPages = $numEvents / $this->sh->get_pager_size();

					$arr_days_and_pages[] = array(
						"daysToShow" => $daysToShow,
						"numPages" => $numPages
					);

					if ( $numPages < 20 ) {

						// Not that many things the last 7 days. Let's try to expand to 14 days instead.
						$daysToShow = 14;
						$numEvents = $this->sh->get_unique_events_for_days($daysToShow);
						$numPages = $numEvents / $this->sh->get_pager_size();

						$arr_days_and_pages[] = array(
							"daysToShow" => $daysToShow,
							"numPages" => $numPages
						);

						if ( $numPages < 20 ) {

							// Not many things the last 14 days either. Let try with 30 days.
							$daysToShow = 30;
							$numEvents = $this->sh->get_unique_events_for_days($daysToShow);
							$numPages = $numEvents / $this->sh->get_pager_size();

							$arr_days_and_pages[] = array(
								"daysToShow" => $daysToShow,
								"numPages" => $numPages
							);

							// If 30 days gives a big amount of pages, go back to 14 days
							if ( $numPages > 1000 ) {
								$daysToShow = 14;
							}

							// @TODO: for sites with very low activity,
							// if they have no events for the last 30 days should we just show all?

						}

					}

				}

				?>

				<p data-debug-daysAndPages='<?php echo json_encode( $arr_days_and_pages ) ?>'>

					<label class="SimpleHistory__filters__filterLabel"><?php _ex("Dates:", "Filter label", "simple-history") ?></label>

					<select class="SimpleHistory__filters__filter SimpleHistory__filters__filter--date"
							name="dates"
							placeholder="<?php echo _e("All dates", "simple-history") ?>"
							NOTmultiple
							>
						<?php

						// custom date range
						// since 2.8.1
						printf(
							'<option value="%1$s" %3$s>%2$s</option>',
							"customRange", // 1 - value
							_x("Custom date range...", "Filter dropin: filter custom range", "simple-history"), // 2 text
							selected( $daysToShow, "customRange", 0 )
						);

						// One day+ Last week + two weeks back + 30 days back

						printf(
							'<option value="%1$s" %3$s>%2$s</option>',
							"lastdays:1", // 1 - value
							_x("Last day", "Filter dropin: filter week", "simple-history"), // 2 text
							selected( $daysToShow, 1, 0 )
						);

						printf(
							'<option value="%1$s" %3$s>%2$s</option>',
							"lastdays:7", // 1 - value
							_x("Last 7 days", "Filter dropin: filter week", "simple-history"), // 2 text
							selected( $daysToShow, 7, 0 )
						);

						printf(
							'<option value="%1$s" %3$s>%2$s</option>',
							"lastdays:14", // 1 - value
							_x("Last 14 days", "Filter dropin: filter week", "simple-history"), // 2 text
							selected( $daysToShow, 14, 0 )
						);

						printf(
							'<option value="%1$s" %3$s>%2$s</option>',
							"lastdays:30", // 1 - value
							_x("Last 30 days", "Filter dropin: filter week", "simple-history"), // 2 text
							selected( $daysToShow, 30, 0 )
						);

						printf(
							'<option value="%1$s" %3$s>%2$s</option>',
							"lastdays:60", // 1 - value
							_x("Last 60 days", "Filter dropin: filter week", "simple-history"), // 2 text
							selected( $daysToShow, 60, 0 )
						);

						// Months
						foreach ( $result_months as $row ) {

							printf(
								'<option value="%1$s" %3$s>%2$s</option>',
								"month:" . $row->yearMonth,
								date_i18n( "F Y", strtotime($row->yearMonth) ),
								"" // selected( $this_month, $row->yearMonth, false )
							);

						}

						?>
					</select>

					<!-- <p> -->
						<!-- <label class="SimpleHistory__filters__filterLabel"><?php _ex("Between dates:", "Filter label", "simple-history") ?></label> -->
						<span class="SimpleHistory__filters__filter--dayValuesWrap">
							<?php
							$this->touch_time("from");
							$this->touch_time("to");
							?>
						</span>
					<!-- </p> -->

				</p><!-- end months -->

				<?php
				/**
				 * Filter to control what the default search string is. Default to empty string.
				 *
				 * @since 2.1.2
				 *
				 * @param string Default search string
				 */
				$default_search_string = apply_filters("SimpleHistoryFilterDropin/filter_default_search_string" , "");
				?>

				<p>

					<label class="SimpleHistory__filters__filterLabel"><?php _ex("Containing words:", "Filter label", "simple-history") ?></label>

					<input
						type="search"
						class="SimpleHistoryFilterDropin-searchInput"
						placeholder="<?php /* _e("Containing words", "simple-history"); */ ?>"
						name="search"
						value="<?php echo esc_attr($default_search_string); ?>"
						>

				</p>

				<p class="SimpleHistory__filters__filterSubmitWrap">
					<button class="button SimpleHistoryFilterDropin-doFilterButton SimpleHistoryFilterDropin-doFilterButton--first js-SimpleHistoryFilterDropin-doFilter"><?php _e("Search events", "simple-history") ?></button>
					<button type="button" class="SimpleHistoryFilterDropin-showMoreFilters SimpleHistoryFilterDropin-showMoreFilters--first js-SimpleHistoryFilterDropin-showMoreFilters"><?php _ex("Show search options", "Filter dropin: button to show more search options", "simple-history") ?></button>
				</p>

				<?php
				/**
				 * Filter to control what the default loglevels are.
				 *
				 * @since 2.1.2
				 *
				 * @param array Array with loglevel sugs. Default empty = show all.
				 */
				$arr_default_loglevels = apply_filters("SimpleHistoryFilterDropin/filter_default_loglevel", array());
				?>
				<div class="SimpleHistory__filters__moreFilters js-SimpleHistory__filters__moreFilters">

					<p>

						<label class="SimpleHistory__filters__filterLabel"><?php _ex("Log levels:", "Filter label", "simple-history") ?></label>

						<select name="loglevels" class="SimpleHistory__filters__filter SimpleHistory__filters__filter--loglevel" style="width: 300px" placeholder="<?php _e("All log levels", "simple-history") ?>" multiple>
							<option <?php selected(in_array("debug", $arr_default_loglevels)) ?> value="debug" data-color="#CEF6D8"><?php echo $this->sh->getLogLevelTranslated("Debug") ?></option>
							<option <?php selected(in_array("info", $arr_default_loglevels)) ?> value="info" data-color="white"><?php echo $this->sh->getLogLevelTranslated("Info") ?></option>
							<option <?php selected(in_array("notice", $arr_default_loglevels)) ?> value="notice" data-color="rgb(219, 219, 183)"><?php echo $this->sh->getLogLevelTranslated("Notice") ?></option>
							<option <?php selected(in_array("warning", $arr_default_loglevels)) ?> value="warning" data-color="#F7D358"><?php echo $this->sh->getLogLevelTranslated("Warning") ?></option>
							<option <?php selected(in_array("error", $arr_default_loglevels)) ?> value="error" data-color="#F79F81"><?php echo $this->sh->getLogLevelTranslated("Error") ?></option>
							<option <?php selected(in_array("critical", $arr_default_loglevels)) ?> value="critical" data-color="#FA5858"><?php echo $this->sh->getLogLevelTranslated("Critical") ?></option>
							<option <?php selected(in_array("alert", $arr_default_loglevels)) ?> value="alert" data-color="rgb(199, 69, 69)"><?php echo $this->sh->getLogLevelTranslated("Alert") ?></option>
							<option <?php selected(in_array("emergency", $arr_default_loglevels)) ?> value="emergency" data-color="#DF0101"><?php echo $this->sh->getLogLevelTranslated("Emergency") ?></option>
						</select>

					</p>

					<?php

					/**
					 * Todo: Filter to control what the default messages to filter/search.
					 * Message in in format: LoggerSlug:MessageKey
					 * For example:
					 *  - SimplePluginLogger:plugin_activated
					 *  - SimpleCommentsLogger:user_comment_added
					 *
					 * @since 2.1.2
					 *
					 * @param array Array with loglevel sugs. Default empty = show all.
					 */
					// $arr_default_messages = apply_filters("SimpleHistoryFilterDropin/filter_default_messages", array());
					?>
					<p>

						<label class="SimpleHistory__filters__filterLabel"><?php _ex("Message types:", "Filter label", "simple-history") ?></label>

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
										printf(
												'<option value="%2$s">%1$s</option>',
												esc_attr( $option_key ), // 1
												esc_attr( $str_option_messages ) // 2
											);

									}

									printf('</optgroup>');

								}

							}
							?>
						</select>
					</p>

					<?php

					/**
					 * Filter what users to search for by default
					 *
					 * Example:
					 *
					 *     add_filter("SimpleHistoryFilterDropin/filter_default_user_ids", function() { return array(1,4); });
					 *
					 * @since 2.1.2
					 *
					 * @param array Array with user ids. Default is an empty array = show all users.
					 */

					/*
					add_filter("SimpleHistoryFilterDropin/filter_default_user_ids", function($arr) {
						$arr = array(
							1,
							4
						);
						return $arr;
					});
					//*/

					$default_user_ids = apply_filters("SimpleHistoryFilterDropin/filter_default_user_ids", array());
					$arr_default_user_data = array();

					foreach ($default_user_ids as $user_id) {
						$arr_default_user_data[] = $this->get_data_for_user($user_id);
					}

					if ( current_user_can("list_users") ) {
						?>
						<p>

							<label class="SimpleHistory__filters__filterLabel"><?php _ex("Users:", "Filter label", "simple-history") ?></label>

							<input type="text"
									name = "users"
									class="SimpleHistory__filters__filter SimpleHistory__filters__filter--user"
									style="width: 300px"
									placeholder="<?php _e("All users", "simple-history") ?>"
									value="<?php echo esc_attr(implode(",",$default_user_ids)) ?>"
									data-default-user-data="<?php echo esc_attr( json_encode($arr_default_user_data) ) ?>"
								/>

						</p>
						<?php
					}

					?>

					<p class="SimpleHistory__filters__filterSubmitWrap">
						<button class="button SimpleHistoryFilterDropin-doFilterButton SimpleHistoryFilterDropin-doFilterButton--second js-SimpleHistoryFilterDropin-doFilter"><?php _e("Search events", "simple-history") ?></button>
						<button type="button" class="SimpleHistoryFilterDropin-showMoreFilters SimpleHistoryFilterDropin-showMoreFilters--second js-SimpleHistoryFilterDropin-showMoreFilters"><?php _ex("Hide search options", "Filter dropin: button to hide more search options", "simple-history") ?></button>
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
	 * Return format used for select2 for a single user id
	 *
	 * @param int $userID
	 * @return array Array with each user as an object
	 */
	public function get_data_for_user($userID) {

		if ( ! $userID || ! is_numeric($userID) ) {
			return false;
		}

		$user = get_user_by( "id", $userID );

		if ( false == $user ) {
			return false;
		}

		$userdata = (object) array(
			"id" => $user->ID,
			"user_email" => $user->user_email,
			"user_login" => $user->user_login,
			"user_nicename" => $user->user_nicename
		);

		$this->add_gravatar_to_user_array($userdata);

		return $userdata;

	}

	/**
	 * Return users
	 */
	public function ajax_simple_history_filters_search_user() {

		$q = isset( $_GET["q"] ) ? $_GET["q"] : "";
		$page_limit = isset( $_GET["page_limit"] ) ? (int) $_GET["page_limit"] : "";

		// query and page limit must be set
		if ( ! $q || ! $page_limit ) {
			wp_send_json_error();
		}

		// user must have list_users capability (default super admin + administrators have this)
		if ( ! current_user_can("list_users") ) {
			wp_send_json_error();
		}

		// Search both current users and all logged rows,
		// because a user can change email
		// search in context: user_id, user_email, user_login
		// search in wp_users: login, nicename, user_email

		// search and get users. make sure to use "fields" and "number" or we can get timeout/use lots of memory if we have a large amount of users
		$results_user = get_users( array(
			"search" => "*{$q}*",
			"fields" => array("ID", "user_login", "user_nicename", "user_email", "display_name"),
			"number" => 20
		) );

		// add lower case id to user array
		array_walk( $results_user, function( $val ) {
			$val->id = $val->ID;
		} );

		// add gravatars to user array
		array_walk( $results_user, array( $this, "add_gravatar_to_user_array" ) );

		$data = array(
			"results" => array(
			),
			"more" => false,
			"context" => array(),
			"count" => sizeof( $results_user )
		);

		$data["results"] = array_merge( $data["results"], $results_user );

		wp_send_json_success( $data );

	} // function

	function add_gravatar_to_user_array(& $val, $index = null) {
		$val->text = sprintf(
			'%1$s - %2$s',
			$val->user_login,
			$val->user_email
		);

		$val->gravatar = $this->sh->get_avatar( $val->user_email, "18", "mm");

	}


	/**
	 * Print out HTML form date elements for editing post or comment publish date.
	 *
	 * Based on the wordpress function touch_time();
	 *
	 * @global WP_Locale  $wp_locale
	 *
	 * @param int|bool $edit      Accepts 1|true for editing the date, 0|false for adding the date.
	 * @param int|bool $for_post  Accepts 1|true for applying the date to a post, 0|false for a comment.
	 * @param int      $tab_index The tabindex attribute to add. Default 0.
	 * @param int|bool $multi     Optional. Whether the additional fields and buttons should be added.
	 *                            Default 0|false.
	 */
	function touch_time( $from_or_to, $edit = 1 ) {

		global $wp_locale;

		// Prefix = text before the inputs
		$prefix = "";
		$input_prefix = "";
		if ( "from" == $from_or_to ) {
			$prefix = _x("From", "Filter dropin, custom date range", "simple-history");
			$input_prefix = "from_";
		} else if ( "to" == $from_or_to ) {
			$prefix = _x("To", "Filter dropin, custom date range", "simple-history");
			$input_prefix = "to_";
		}

		// The default date to show in the inputs
		$date = date("Y-m-d");

		$jj = mysql2date( 'd', $date, false );
		$mm = mysql2date( 'm', $date, false );
		$aa = mysql2date( 'Y', $date, false );

		$month = "<select name='{$input_prefix}mm'>";

		for ( $i = 1; $i < 13; $i = $i +1 ) {
			$monthnum = zeroise($i, 2);
			$monthtext = $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) );
			$month .= "\t\t\t" . '<option value="' . $monthnum . '" data-text="' . $monthtext . '" ' . selected( $monthnum, $mm, false ) . '>';
			/* translators: 1: month number (01, 02, etc.), 2: month abbreviation */
			$month .= sprintf( __( '%1$s-%2$s' ), $monthnum, $monthtext ) . "</option>\n";
		}
		$month .= '</select>';
		$month .= '</label>';

		$day = '<label><span class="screen-reader-text">' . __( 'Day' ) . '</span><input type="text" name="'.$input_prefix.'jj" value="' . $jj . '" size="2" maxlength="2" autocomplete="off" /></label>';
		$year = '<label><span class="screen-reader-text">' . __( 'Year' ) . '</span><input type="text" name="'.$input_prefix.'aa" value="' . $aa . '" size="4" maxlength="4" autocomplete="off" /></label>';

		echo '<span class="SimpleHistory__filters__filter SimpleHistory__filters__filter--day">';

		echo $prefix . "<br>";

		/* translators: 1: month, 2: day, 3: year, 4: hour, 5: minute */
		printf( __( '%1$s %2$s, %3$s ' ), $month, $day, $year );

		echo '</span>';

		?>

	<?php
	} // func

} // end class
