<?php

defined( 'ABSPATH' ) || die();

/**
 * A PSR-3 inspired logger class
 * This class logs + formats logs for display in the Simple History GUI/Viewer
 *
 * Extend this class to make your own logger
 *
 * @link https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md PSR-3 specification
 */
class SimpleLogger {

	/**
	 * Unique slug for this logger
	 * Will be saved in DB and used to associate each log row with its logger
	 */
	public $slug = __CLASS__;

	/**
	 * Will contain the untranslated messages from getInfo()
	 *
	 * By adding your messages here they will be stored both translated and non-translated
	 * You then log something like this:
	 * <code>
	 *   $this->info( $this->messages["POST_UPDATED"] );
	 * </code>
	 * or with the shortcut
	 * <code>
	 *   $this->infoMessage("POST_UPDATED");
	 * </code>
	 * which results in the original, untranslated, string being added to the log and database
	 * the translated string are then only used when showing the log in the GUI
	 */
	public $messages;

	/**
	 * ID of last inserted row
	 *
	 * @var int $lastInsertID Database row primary key.
	 */
	public $lastInsertID;

	/**
	 * Context of last inserted row.
	 *
	 * @var int $lastInsertContext Context used for the last insert.
	 * @since 2.2x
	 */
	public $lastInsertContext;

	/**
	 * Simple History instance.
	 *
	 * @var object $simpleHistory Simple history instance.
	 */
	public $simpleHistory;

	/**
	 * Constructor. Remember to call this as parent constructor if making a childlogger
	 *
	 * @param $simpleHistory history class  objectinstance
	 */
	public function __construct( $simpleHistory = null ) {
		global $wpdb;

		$this->db_table = $wpdb->prefix . SimpleHistory::DBTABLE;
		$this->db_table_contexts =
			$wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

		$this->simpleHistory = $simpleHistory;
	}

	/**
	 * Method that is called automagically when logger is loaded by Simple History
	 * Add your init stuff here
	 */
	public function loaded() {
	}

	/**
	 * Get array with information about this logger.
	 *
	 * @return array
	 */
	public function getInfo() {
		$arr_info = array(
			// The logger slug. Defaulting to the class name is nice and logical I think.
			'slug' => __CLASS__,

			// Shown on the info-tab in settings, use these fields to tell
			// an admin what your logger is used for.
			'name'        => 'SimpleLogger',
			'description' => __( 'The built in logger for Simple History', 'simple-history' ),

			// Capability required to view log entries from this logger
			'capability' => 'edit_pages',
			'messages'   => array(
				// No pre-defined variants
				// when adding messages __() or _x() must be used
			),
		);

		return $arr_info;
	}

	/**
	 * Return single array entry from the array in getInfo()
	 * Returns the value of the key if value exists, or null
	 *
	 * @since 2.5.4
	 * @return Mixed
	 */
	public function getInfoValueByKey( $key ) {
		$arr_info = $this->getInfo();

		return isset( $arr_info[ $key ] ) ? $arr_info[ $key ] : null;
	}

	/**
	 * Returns the capability required to read log rows from this logger
	 *
	 * @return $string capability
	 */
	public function getCapability() {
		$arr_info = $this->getInfo();

		$capability = 'manage_options';

		if ( ! empty( $arr_info['capability'] ) ) {
			$capability = $arr_info['capability'];
		}

		return $capability;
	}

	/**
	 * Interpolates context values into the message placeholders.
	 *
	 * @param string $message
	 * @param array  $context
	 * @param array  $row Currently not always passed, because loggers need to be updated to support this...
	 */
	public function interpolate( $message, $context = array(), $row = null ) {
		if ( ! is_array( $context ) ) {
			return $message;
		}

		/**
		 * Filters the context used to create the message from the message template.
		 * Can be used to modify the variables sent to the message template.
		 *
		 * @example Example that modifies the parameters sent to the message template.
		 *
		 * This example will change the post type from "post" or "page" or similar to "my own page type".
		 *
		 *  ```php
		 *  add_filter(
		 *      'simple_history/logger/interpolate/context',
		 *      function ( $context, $message, $row ) {
		 *
		 *          if ( empty( $row ) ) {
		 *              return $context;
		 *          }
		 *
		 *          if ( $row->logger == 'SimplePostLogger' && $row->context_message_key == 'post_updated' ) {
		 *              $context['post_type'] = 'my own page type';
		 *          }
		 *
		 *          return $context;
		 *      },
		 *      10,
		 *      3
		 *  );
		 * ```
		 *
		 * @since 2.2.4
		 *
		 * @param array $context
		 * @param string $message
		 * @param array $row The row. Not supported by all loggers.
		 */
		$context = apply_filters(
			'simple_history/logger/interpolate/context',
			$context,
			$message,
			$row
		);

		// Build a replacement array with braces around the context keys
		$replace = array();
		foreach ( $context as $key => $val ) {
			// Both key and val must be strings or number (for vals)
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			if ( is_string( $key ) || is_numeric( $key ) ) {
				// key ok
			}

			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
			if ( is_string( $val ) || is_numeric( $val ) ) {
				// val ok
			} else {
				// not a value we can replace
				continue;
			}

			$replace[ '{' . $key . '}' ] = $val;
		}

		// Interpolate replacement values into the message and return
		return strtr( $message, $replace );
	}

	/**
	 * @param object $row
	 * @return string HTML
	 */
	public function getLogRowHeaderInitiatorOutput( $row ) {
		$initiator_html = '';
		$initiator = $row->initiator;
		$context = $row->context;

		switch ( $initiator ) {
			case 'wp':
				$initiator_html .=
					'<strong class="SimpleHistoryLogitem__inlineDivided">WordPress</strong> ';
				break;

			case 'wp_cli':
				$initiator_html .=
					'<strong class="SimpleHistoryLogitem__inlineDivided">WP-CLI</strong> ';
				break;

			// wp_user = wordpress uses, but user may have been deleted since log entry was added
			case 'wp_user':
				$user_id = isset( $row->context['_user_id'] )
					? $row->context['_user_id']
					: null;

				$user = get_user_by( 'id', $user_id );
				if ( $user_id > 0 && ( $user ) ) {
					// Sender is user and user still exists.
					$is_current_user =
						get_current_user_id() == $user_id ? true : false;

					// get user role, as done in user-edit.php
					$wp_roles = $GLOBALS['wp_roles'];
					$user_roles = array_intersect(
						array_values( (array) $user->roles ),
						array_keys( (array) $wp_roles->roles )
					);
					$user_role = array_shift( $user_roles );

					$user_display_name = $user->display_name;

					/*
					* If user who logged this is the currently logged in user
					* skip name and email and use just "You"
					*
					* @param bool If you should be used
					* @since 2.1
					*/
					$use_you = apply_filters(
						'simple_history/header_initiator_use_you',
						true
					);

					if ( $use_you && $is_current_user ) {
						$tmpl_initiator_html = '
                            <a href="%6$s" class="SimpleHistoryLogitem__headerUserProfileLink">
                                <strong class="SimpleHistoryLogitem__inlineDivided">%5$s</strong>
                            </a>
                        ';
					} else {
						$tmpl_initiator_html = '
                            <a href="%6$s" class="SimpleHistoryLogitem__headerUserProfileLink">
                                <strong class="SimpleHistoryLogitem__inlineDivided">%3$s</strong>
                                <span class="SimpleHistoryLogitem__inlineDivided SimpleHistoryLogitem__headerEmail">%2$s</span>
                            </a>
                        ';
					}

					/**
					 * Filter the format for the user output
					 *
					 * @since 2.0
					 *
					 * @param string $format.
					 */
					$tmpl_initiator_html = apply_filters(
						'simple_history/header_initiator_html_existing_user',
						$tmpl_initiator_html
					);

					$initiator_html .= sprintf(
						$tmpl_initiator_html,
						esc_html( $user->user_login ), // 1
						esc_html( $user->user_email ), // 2
						esc_html( $user_display_name ), // 3
						$user_role, // 4
						_x(
							'You',
							'header output when initiator is the currently logged in user',
							'simple-history'
						), // 5
						get_edit_user_link( $user_id ) // 6
					);
				} elseif ( $user_id > 0 ) {
					// Sender was a user, but user is deleted now
					// output all info we have
					// _user_id
					// _username
					// _user_login
					// _user_email
					$initiator_html .= sprintf(
						'<strong class="SimpleHistoryLogitem__inlineDivided">' .
							__(
								'Deleted user (had id %1$s, email %2$s, login %3$s)',
								'simple-history'
							) .
							'</strong>',
						esc_html( $context['_user_id'] ), // 1
						esc_html( $context['_user_email'] ), // 2
						esc_html( $context['_user_login'] ) // 3
					);
				} // End if().

				break;

			case 'web_user':
				/*
				Note: server_remote_addr may not show visiting/attacking ip, if server is behind...stuff..
				Can be behind varnish cache, or browser can for example use compression in chrome mobile
				then the real ip is behind _server_http_x_forwarded_for_0 or similar
				_server_remote_addr 66.249.81.222
				_server_http_x_forwarded_for_0  5.35.187.212
				*/

				// Check if additional IP addresses are stored, from http_x_forwarded_for and so on.
				$arr_found_additional_ip_headers = $this->get_event_ip_number_headers(
					$row
				);

				$initiator_html .=
					"<strong class='SimpleHistoryLogitem__inlineDivided'>" .
					__( 'Anonymous web user', 'simple-history' ) .
					'</strong> ';

				break;

			case 'other':
				$initiator_html .=
					"<strong class='SimpleHistoryLogitem__inlineDivided'>" .
					_x(
						'Other',
						'Event header output, when initiator is unknown',
						'simple-history'
					) .
					'</strong>';
				break;

			// no initiator
			case null:
				// $initiator_html .= "<strong class='SimpleHistoryLogitem__inlineDivided'>Null</strong>";
				break;

			default:
				$initiator_html .=
					"<strong class='SimpleHistoryLogitem__inlineDivided'>" .
					esc_html( $initiator ) .
					'</strong>';
		} // End switch().

		/**
		 * Filter generated html for the initiator row header html
		 *
		 * @since 2.0
		 *
		 * @param string $initiator_html
		 * @param object $row Log row
		 */
		$initiator_html = apply_filters(
			'simple_history/row_header_initiator_output',
			$initiator_html,
			$row
		);

		return $initiator_html;
	}

	public function getLogRowHeaderDateOutput( $row ) {
		// HTML for date
		// Date (should...) always exist
		// http://developers.whatwg.org/text-level-semantics.html#the-time-element
		$date_html = '';
		$str_when = '';

		// $row->date is in GMT
		$date_datetime = new DateTime( $row->date, new DateTimeZone( 'GMT' ) );

		// Current datetime in GMT
		$time_current = strtotime( current_time( 'mysql', 1 ) );

		/**
		 * Filter how many seconds as most that can pass since an
		 * event occurred to show "nn minutes ago" (human diff time-format) instead of exact date
		 *
		 * @since 2.0
		 *
		 * @param int $time_ago_max_time Seconds
		 */
		$time_ago_max_time = DAY_IN_SECONDS * 2;
		$time_ago_max_time = apply_filters(
			'simple_history/header_time_ago_max_time',
			$time_ago_max_time
		);

		/**
		 * Filter how many seconds as most that can pass since an
		 * event occurred to show "just now" instead of exact date
		 *
		 * @since 2.0
		 *
		 * @param int $time_ago_max_time Seconds
		 */
		$time_ago_just_now_max_time = 30;
		$time_ago_just_now_max_time = apply_filters(
			'simple_history/header_just_now_max_time',
			$time_ago_just_now_max_time
		);

		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		$date_and_time_format = $date_format . ' - ' . $time_format;

		// Show local time as hours an minutes when event is recent.
		$local_date_format = $time_format;

		// Show local time as date and hours when event is a bit older.
		if (
			$time_current - HOUR_IN_SECONDS * 6 >
			$date_datetime->getTimestamp()
		) {
			$local_date_format = $date_and_time_format;
		}

		if (
			$time_current - $date_datetime->getTimestamp() <=
			$time_ago_just_now_max_time
		) {
			// Show "just now" if event is very recent.
			$str_when = __( 'Just now', 'simple-history' );
		} elseif (
			$time_current - $date_datetime->getTimestamp() >
			$time_ago_max_time
		) {
			/* Translators: Date format for log row header, see http://php.net/date */
			$datef = __( 'M j, Y \a\t G:i', 'simple-history' );
			$str_when = date_i18n(
				$datef,
				strtotime( get_date_from_gmt( $row->date ) )
			);
		} else {
			// Show "nn minutes ago" when event is xx seconds ago or earlier
			$date_human_time_diff = human_time_diff(
				$date_datetime->getTimestamp(),
				$time_current
			);
			/* Translators: 1: last modified date and time in human time diff-format */
			$str_when = sprintf(
				__( '%1$s ago', 'simple-history' ),
				$date_human_time_diff
			);
		}

		$item_permalink = admin_url( apply_filters( 'simple_history/admin_location', 'index' ) . '.php?page=simple_history_page' );
		if ( ! empty( $row->id ) ) {
			$item_permalink .= "#item/{$row->id}";
		}

		// Datetime attribute on <time> element.
		$str_datetime_title = sprintf(
			__( '%1$s local time %3$s (%2$s GMT time)', 'simple-history' ),
			get_date_from_gmt(
				$date_datetime->format( 'Y-m-d H:i:s' ),
				$date_and_time_format
			), // 1 local time
			$date_datetime->format( $date_and_time_format ), // GMT time
			PHP_EOL // 3, new line
		);

		// Time and date before live updated relative date.
		$str_datetime_local = sprintf(
			'%1$s',
			get_date_from_gmt(
				$date_datetime->format( 'Y-m-d H:i:s' ),
				$local_date_format
			) // 1 local time
		);

		// HTML for whole span with date info.
		$date_html =
			"<span class='SimpleHistoryLogitem__permalink SimpleHistoryLogitem__when SimpleHistoryLogitem__inlineDivided'>";
		$date_html .= "<a class='' href='{$item_permalink}'>";
		$date_html .= sprintf(
			'<span title="%1$s">%4$s (<time datetime="%3$s" class="SimpleHistoryLogitem__when__liveRelative">%2$s</time>)</span>',
			esc_attr( $str_datetime_title ), // 1 datetime attribute
			esc_html( $str_when ), // 2 date text, visible in log, but overridden by JS relative date script.
			$date_datetime->format( DateTime::RFC3339 ), // 3
			esc_html( $str_datetime_local ) // 4
		);
		$date_html .= '</a>';
		$date_html .= '</span>';

		/**
		 * Filter the output of the date section of the header.
		 *
		 * @since 2.5.1
		 *
		 * @param string $date_html
		 * @param object $row
		 */
		$date_html = apply_filters(
			'simple_history/row_header_date_output',
			$date_html,
			$row
		);

		return $date_html;
	}

	public function getLogRowHeaderUsingPluginOutput( $row ) {
		// Logger "via" info in header, i.e. output some extra
		// info next to the time to make it more clear what plugin etc.
		// that "caused" this event
		$via_html = '';
		$logger_name_via = $this->getInfoValueByKey( 'name_via' );

		if ( $logger_name_via ) {
			$via_html =
				"<span class='SimpleHistoryLogitem__inlineDivided SimpleHistoryLogitem__via'>";
			$via_html .= $logger_name_via;
			$via_html .= '</span>';
		}

		return $via_html;
	}

	/**
	 * Context for IP Addresses can contain multiple entries.
	 *
	 * - "_server_remote_addr" with value for example "172.17.0.0" is the main entry.
	 *   It usually contains the IP address of the visitor.
	 *
	 * - Then zero or one or multiple entries can exist if web server is for example behind proxy.
	 *   Entries that can exist are the one with keys is get_ip_number_header_keys(),
	 *   Also each key can exist multiple times.
	 *   Final key name will be like "_server_http_x_forwarded_for_0", "_server_http_x_forwarded_for_1" and so on.
	 *
	 * @param mixed $row
	 * @return string
	 */
	public function getLogRowHeaderIPAddressOutput( $row ) {

		/**
		 * Filter if IP Address should be added to header row.
		 *
		 * @since 2.x
		 *
		 * @param bool True to show IP address, false to hide it. Defaults to false.
		 * @param object $row Row data
		 */
		$show_ip_address = apply_filters(
			'simple_history/row_header_output/display_ip_address',
			false,
			$row
		);

		if ( ! $show_ip_address ) {
			return '';
		}

		$context = $row->context;
		$html = "<span class='SimpleHistoryLogitem__inlineDivided SimpleHistoryLogitem__anonUserWithIp'>";

		$arr_ip_addresses = array();

		// Look for additional ip addresses.
		$arr_found_additional_ip_headers = $this->get_event_ip_number_headers( $row );

		$arr_ip_addresses = array_merge(
			// Remote addr always exists.
			array( '_server_remote_addr' => $context['_server_remote_addr'] ),
			$arr_found_additional_ip_headers
		);

		// if ( count( $arr_found_additional_ip_headers ) ) {
		// $iplookup_link = sprintf('https://ipinfo.io/%1$s', esc_attr($context["_server_remote_addr"]));
		// $ip_numbers_joined = wp_sprintf_l('%l', array("_server_remote_addr" => $context["_server_remote_addr"]) + $arr_found_additional_ip_headers);
		/*
			$html .= sprintf(
				__('Anonymous user with multiple IP addresses detected: %1$s', "simple-history"),
				"<a target='_blank' href={$iplookup_link} class='SimpleHistoryLogitem__anonUserWithIp__theIp'>" . esc_html( $ip_numbers_joined ) . "</a>"
			);*/

		/*
			print_r($arr_found_additional_ip_headers);
			Array
			(
				[_server_http_x_forwarded_for_0] => 5.35.187.212
				[_server_http_x_forwarded_for_1] => 83.251.97.21
			)
			*/

		// } else {

		$first_ip_address = reset( $arr_ip_addresses );

		// Output single or plural text.
		if ( count( $arr_ip_addresses ) === 1 ) {
			// Single ip address
			$iplookup_link = sprintf(
				'https://ipinfo.io/%1$s',
				esc_attr( $first_ip_address )
			);

			$html .= sprintf(
				__( 'IP Address %1$s', 'simple-history' ),
				"<a target='_blank' href='{$iplookup_link}' class='SimpleHistoryLogitem__anonUserWithIp__theIp' data-ip-address='" . esc_attr( $first_ip_address ) . "'>" .
				esc_html( $first_ip_address ) .
				'</a>'
			);
		} elseif ( count( $arr_ip_addresses ) > 1 ) {
			$ip_addresses_html = '';

			foreach ( $arr_ip_addresses as $ip_address_header => $ip_address ) {
				$iplookup_link = sprintf(
					'https://ipinfo.io/%1$s',
					esc_attr( $ip_address )
				);

				$ip_addresses_html .= sprintf(
					'<a target="_blank" href="%3$s" class="SimpleHistoryLogitem__anonUserWithIp__theIp" data-ip-address="%4$s">%1$s</a>, ',
					esc_html( $ip_address ), // 1
					esc_html( $ip_address_header ), // 2
					$iplookup_link, // 3
					esc_attr( $ip_address ) // 4
				);
			}

			// Remove trailing comma.
			$ip_addresses_html = rtrim( $ip_addresses_html, ', ' );

			$html .= sprintf(
				__( 'IP Addresses %1$s', 'simple-history' ),
				$ip_addresses_html
			);
		}

		// } // multiple ip
		$html .= '</span> ';

		// $initiator_html .= "<strong>" . __("<br><br>Unknown user from {$context["_server_remote_addr"]}") . "</strong>";
		// $initiator_html .= "<strong>" . __("<br><br>{$context["_server_remote_addr"]}") . "</strong>";
		// $initiator_html .= "<strong>" . __("<br><br>User from IP {$context["_server_remote_addr"]}") . "</strong>";
		// $initiator_html .= "<strong>" . __("<br><br>Non-logged in user from IP  {$context["_server_remote_addr"]}") . "</strong>";
		// } // End if().
		return $html;
	}

	/**
	 * Returns header output for a log row.
	 *
	 * Format should be common for all log rows and should be like:
	 * Username (user role) · Date · IP Address · Via plugin abc
	 * I.e.:
	 * Initiator * Date/time * IP Address * Via logger
	 *
	 * @param object $row Row data
	 * @return string HTML
	 */
	public function getLogRowHeaderOutput( $row ) {
		$initiator_html = $this->getLogRowHeaderInitiatorOutput( $row );
		$date_html = $this->getLogRowHeaderDateOutput( $row );
		$via_html = $this->getLogRowHeaderUsingPluginOutput( $row );
		$ip_address_html = $this->getLogRowHeaderIPAddressOutput( $row );

		// Template to combine header parts.
		$template = '
			%1$s
			%2$s
			%3$s
			%4$s
		';

		/**
		 * Filter template used to glue together markup the log row header.
		 *
		 * @since 2.0
		 *
		 * @param string $template
		 * @param object $row Log row
		 */
		$template = apply_filters(
			'simple_history/row_header_output/template',
			$template,
			$row
		);

		// Glue together final result.
		$html = sprintf(
			$template,
			$initiator_html, // 1
			$date_html, // 2
			$via_html, // 3
			$ip_address_html // 4
		);

		/**
		 * Filter generated html for the log row header.
		 *
		 * @since 2.0
		 *
		 * @param string $html
		 * @param object $row Log row
		 */
		$html = apply_filters( 'simple_history/row_header_output', $html, $row );

		return $html;
	}

	/**
	 * Returns the plain text version of this entry
	 * Used in for example CSV-exports.
	 * Defaults to log message with context interpolated.
	 * Keep format as plain and simple as possible.
	 * Links are ok, for example to link to users or posts.
	 * Tags will be stripped when text is used for CSV-exports and so on.
	 * Keep it on a single line. No <p> or <br> and so on.
	 *
	 * Example output:
	 * Edited post "About the company"
	 *
	 * Message should sound like it's coming from the user.
	 * Image that the name of the user is added in front of the text:
	 * Jessie James: Edited post "About the company"
	 */
	public function getLogRowPlainTextOutput( $row ) {
		$message = $row->message;
		$message_key = isset( $row->context['_message_key'] )
		? $row->context['_message_key']
		: null;

		// Message is translated here, but translation must be added in
		// plain text before
		if ( empty( $message_key ) ) {
			// Message key did not exist, so check if we should translate using textdomain
			if ( ! empty( $row->context['_gettext_domain'] ) ) {
				// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.NonSingularStringLiteralText
				$message = __( $message, $row->context['_gettext_domain'] );
			}
		} else {
			// Check that messages does exist
			// If we for example disable a Logger we may have references
			// to message keys that are unavailable. If so then fallback to message.
			if ( isset( $this->messages[ $message_key ]['translated_text'] ) ) {
				$message = $this->messages[ $message_key ]['translated_text'];
			} else { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElse
				// Not message exists for message key. Just keep using message.
			}
		}

		$html = $this->interpolate( $message, $row->context, $row );

		// All messages are escaped by default.
		// If you need unescaped output override this method
		// in your own logger
		$html = esc_html( $html );

		/**
		 * Filter generated output for plain text output
		 *
		 * @since 2.0
		 *
		 * @param string $html
		 * @param object $row Log row
		 */
		$html = apply_filters(
			'simple_history/row_plain_text_output',
			$html,
			$row
		);

		return $html;
	}

	/**
	 * Get output for image
	 * Image can be for example gravatar if sender is user,
	 * or other images if sender i system, wordpress, and so on
	 */
	public function getLogRowSenderImageOutput( $row ) {
		$sender_image_html = '';
		$sender_image_size = 32;

		$initiator = $row->initiator;

		switch ( $initiator ) {
			// wp_user = wordpress uses, but user may have been deleted since log entry was added
			case 'wp_user':
				$user_id = isset( $row->context['_user_id'] )
				? $row->context['_user_id']
				: null;

				$user = get_user_by( 'id', $user_id );
				if ( $user_id > 0 && ( $user ) ) {
					// Sender was user
					$sender_image_html = $this->simpleHistory->get_avatar(
						$user->user_email,
						$sender_image_size
					);
				} elseif ( $user_id > 0 ) {
					// Sender was a user, but user is deleted now
					$sender_image_html = $this->simpleHistory->get_avatar(
						'',
						$sender_image_size
					);
				} else {
					$sender_image_html = $this->simpleHistory->get_avatar(
						'',
						$sender_image_size
					);
				}

				break;
		}

		/**
		 * Filter generated output for row image (sender image)
		 *
		 * @since 2.0
		 *
		 * @param string $sender_image_html
		 * @param object $row Log row
		 */
		$sender_image_html = apply_filters(
			'simple_history/row_sender_image_output',
			$sender_image_html,
			$row
		);

		return $sender_image_html;
	}

	/**
	 * Use this method to output detailed output for a log row
	 * Example usage is if a user has uploaded an image then a
	 * thumbnail of that image can bo outputted here
	 *
	 * @param object $row
	 * @return string HTML-formatted output
	 */
	public function getLogRowDetailsOutput( $row ) {
		$html = '';

		/**
		 * Filter generated output for details
		 *
		 * @since 2.0
		 *
		 * @param string $html
		 * @param object $row Log row
		 */
		$html = apply_filters( 'simple_history/row_details_output', $html, $row );

		return $html;
	}

	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param array  $context
	 * @return null
	 */
	public function emergency( $message, array $context = array() ) {
		return $this->log( SimpleLoggerLogLevels::EMERGENCY, $message, $context );
	}

	/**
	 * System is unusable.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array  $context
	 * @return null
	 */
	public function emergencyMessage( $message, array $context = array() ) {
		return $this->logByMessageKey(
			SimpleLoggerLogLevels::EMERGENCY,
			$message,
			$context
		);
	}

	/**
	 * Log with message
	 * Called from infoMessage(), errorMessage(), and so on
	 *
	 * Call like this:
	 *
	 *   return $this->logByMessageKey(SimpleLoggerLogLevels::EMERGENCY, $message, $context);
	 */
	private function logByMessageKey(
		$SimpleLoggerLogLevelsLevel,
		$messageKey,
		$context
	) {
		// When logging by message then the key must exist
		if ( ! isset( $this->messages[ $messageKey ]['untranslated_text'] ) ) {
			return;
		}

		/**
		 * Filter so plugins etc. can shortcut logging
		 *
		 * @since 2.0.20
		 *
		 * @param true yes, we default to do the logging
		 * @param string logger slug
		 * @param string messageKey
		 * @param string log level
		 * @param array context
		 * @return bool false to abort logging
		 */
		$doLog = apply_filters(
			'simple_history/simple_logger/log_message_key',
			true,
			$this->slug,
			$messageKey,
			$SimpleLoggerLogLevelsLevel,
			$context
		);

		if ( ! $doLog ) {
			return;
		}

		$context['_message_key'] = $messageKey;
		$message = $this->messages[ $messageKey ]['untranslated_text'];

		$this->log( $SimpleLoggerLogLevelsLevel, $message, $context );
	}

	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message
	 * @param array  $context
	 * @return null
	 */
	public function alert( $message, array $context = array() ) {
		return $this->log( SimpleLoggerLogLevels::ALERT, $message, $context );
	}

	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array  $context
	 * @return null
	 */
	public function alertMessage( $message, array $context = array() ) {
		return $this->logByMessageKey(
			SimpleLoggerLogLevels::ALERT,
			$message,
			$context
		);
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message
	 * @param array  $context
	 * @return null
	 */
	public function critical( $message, array $context = array() ) {
		return $this->log( SimpleLoggerLogLevels::CRITICAL, $message, $context );
	}

	/**
	 * Critical conditions.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array  $context
	 * @return null
	 */
	public function criticalMessage( $message, array $context = array() ) {
		if ( ! isset( $this->messages[ $message ]['untranslated_text'] ) ) {
			return;
		}

		$context['_message_key'] = $message;
		$message = $this->messages[ $message ]['untranslated_text'];

		$this->log( SimpleLoggerLogLevels::CRITICAL, $message, $context );
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param array  $context
	 * @return null
	 */
	public function error( $message, array $context = array() ) {
		return $this->log( SimpleLoggerLogLevels::ERROR, $message, $context );
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array  $context
	 * @return null
	 */
	public function errorMessage( $message, array $context = array() ) {
		return $this->logByMessageKey(
			SimpleLoggerLogLevels::ERROR,
			$message,
			$context
		);
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string $message
	 * @param array  $context
	 * @return null
	 */
	public function warning( $message, array $context = array() ) {
		return $this->log( SimpleLoggerLogLevels::WARNING, $message, $context );
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array  $context
	 * @return null
	 */
	public function warningMessage( $message, array $context = array() ) {
		return $this->logByMessageKey(
			SimpleLoggerLogLevels::WARNING,
			$message,
			$context
		);
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param array  $context
	 * @return null
	 */
	public function notice( $message, array $context = array() ) {
		return $this->log( SimpleLoggerLogLevels::NOTICE, $message, $context );
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array  $context
	 * @return null
	 */
	public function noticeMessage( $message, array $context = array() ) {
		return $this->logByMessageKey(
			SimpleLoggerLogLevels::NOTICE,
			$message,
			$context
		);
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message
	 * @param array  $context
	 * @return null
	 */
	public function info( $message, array $context = array() ) {
		return $this->log( SimpleLoggerLogLevels::INFO, $message, $context );
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array  $context
	 * @return null
	 */
	public function infoMessage( $message, array $context = array() ) {
		return $this->logByMessageKey(
			SimpleLoggerLogLevels::INFO,
			$message,
			$context
		);
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param array  $context
	 * @return null
	 */
	public function debug( $message, array $context = array() ) {
		return $this->log( SimpleLoggerLogLevels::DEBUG, $message, $context );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message key from getInfo messages array
	 * @param array  $context
	 * @return null
	 */
	public function debugMessage( $message, array $context = array() ) {
		return $this->logByMessageKey(
			SimpleLoggerLogLevels::DEBUG,
			$message,
			$context
		);
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed  $level The log level. Default "info".
	 * @param string $message The log message. Default "".
	 * @param array  $context The log context. Default empty array.
	 * @return class SimpleLogger instance
	 */
	public function log( $level = 'info', $message = '', $context = array() ) {
		global $wpdb;

		// Check that passed args are of correct types.
		if ( ! is_string( $level ) || ! is_string( $message ) ) {
			return $this;
		}

		// Context must be array, but can be passed as null and so on.
		if ( ! is_array( $context ) ) {
			$context = array();
		}

		// Don't go on if message is empty.
		if ( empty( $message ) ) {
			return $this;
		}

		/**
		 * Filter that makes it possible to shortcut the logging of a message.
		 * Return bool false to cancel logging .
		 *
		 * @example Do not log some post types, for example pages and attachments in this case
		 *
		 * ```php
		 *  add_filter(
		 *      'simple_history/log/do_log',
		 *      function ( $do_log = null, $level = null, $message = null, $context = null, $logger = null ) {
		 *
		 *          $post_types_to_not_log = array(
		 *              'page',
		 *              'attachment',
		 *          );
		 *
		 *          if ( ( isset( $logger->slug ) && ( $logger->slug === 'SimplePostLogger' || $logger->slug === 'SimpleMediaLogger' ) ) && ( isset( $context['post_type'] ) && in_array( $context['post_type'], $post_types_to_not_log ) ) ) {
		 *              $do_log = false;
		 *          }
		 *
		 *          return $do_log;
		 *      },
		 *      10,
		 *      5
		 *  );
		 * ```
		 *
		 * @example Disable all logging
		 *
		 * ```php
		 *  // Disable all logging
		 *  add_filter( 'simple_history/log/do_log', '__return_false' );
		 * ```
		 *
		 * @since 2.3.1
		 *
		 * @param bool $doLog Whether to log or not.
		 * @param string $level The loglevel.
		 * @param string $message The log message.
		 * @param array $context The message context.
		 * @param SimpleLogger $this Logger instance.
		 */
		$do_log = apply_filters(
			'simple_history/log/do_log',
			true,
			$level,
			$message,
			$context,
			$this
		);

		if ( false === $do_log ) {
			return $this;
		}

		/**
		 * Easy shortcut method to disable logging of messages from
		 * a specific logger.
		 *
		 * Example filter name:
		 * simple_history/log/do_log/SimpleUserLogger
		 * simple_history/log/do_log/SimplePostLogger
		 *
		 * Example to disable logging of any user login/logout/failed login activity:
		 * add_filter('simple_history/log/do_log/SimpleUserLogger', '__return_false')
		 *
		 * @since 2.nn
		 */
		$do_log = apply_filters(
			"simple_history/log/do_log/{$this->slug}",
			true
		);
		if ( false === $do_log ) {
			return $this;
		}

		/**
		 * Easy shortcut method to disable logging of messages from
		 * a specific logger and message.
		 *
		 * Example filter name:
		 * simple_history/log/do_log/SimpleUserLogger/user_logged_in
		 * simple_history/log/do_log/SimplePostLogger/post_updated
		 *
		 * @since 2.nn
		 */
		$message_key = isset( $context['_message_key'] )
		? $context['_message_key']
		: null;
		$do_log = apply_filters(
			"simple_history/log/do_log/{$this->slug}/{$message_key}",
			true
		);
		if ( false === $do_log ) {
			return $this;
		}

		// Check if $message is a translated message, and if so then fetch original
		$sh_latest_translations =
		$this->simpleHistory->gettextLatestTranslations;

		if ( ! empty( $sh_latest_translations ) ) {
			if ( isset( $sh_latest_translations[ $message ] ) ) {
				// Translation of this phrase was found, so use original phrase instead of translated one
				// Store textdomain since it's required to translate
				$context['_gettext_domain'] =
				$sh_latest_translations[ $message ]['domain'];

				// These are good to keep when debugging
				// $context["_gettext_org_message"] = $sh_latest_translations[$message]["text"];
				// $context["_gettext_translated_message"] = $sh_latest_translations[$message]["translation"];
				$message = $sh_latest_translations[ $message ]['text'];
			}
		}

		/**
		 * Filter arguments passed to log function
		 *
		 * @since 2.0
		 *
		 * @param string $level
		 * @param string $message
		 * @param array $context
		 * @param object SimpleLogger object
		 */
		apply_filters(
			'simple_history/log_arguments',
			$level,
			$message,
			$context,
			$this
		);
		$context = apply_filters(
			'simple_history/log_argument/context',
			$context,
			$level,
			$message,
			$this
		);
		$level = apply_filters(
			'simple_history/log_argument/level',
			$level,
			$context,
			$message,
			$this
		);
		$message = apply_filters(
			'simple_history/log_argument/message',
			$message,
			$level,
			$context,
			$this
		);

		/*
		 Store date as GMT date, i.e. not local date/time
		 * Some info here:
		 * http://www.skyverge.com/blog/down-the-rabbit-hole-wordpress-and-timezones/
		 */
		$localtime = current_time( 'mysql', 1 );

		$db_table = $wpdb->prefix . SimpleHistory::DBTABLE;

		/**
		 * Filter db table used for simple history events
		 *
		 * @since 2.0
		 *
		 * @param string $db_table
		 */
		$db_table = apply_filters( 'simple_history/db_table', $db_table );

		$data = array(
			'logger' => $this->slug,
			'level' => $level,
			'date' => $localtime,
			'message' => $message,
		);

		// Allow date to be overridden.
		// Date must be in format 'Y-m-d H:i:s'.
		if ( isset( $context['_date'] ) ) {
			$data['date'] = $context['_date'];
			unset( $context['_date'] );
		}

		// Add occasions id.
		$occasions_id = null;
		if ( isset( $context['_occasionsID'] ) ) {
			// Minimize risk of similar loggers logging same messages and such and resulting in same occasions id
			// by always adding logger slug.
			$occasions_data = array(
				'_occasionsID' => $context['_occasionsID'],
				'_loggerSlug' => $this->slug,
			);
			$occasions_id = md5( json_encode( $occasions_data ) );
			unset( $context['_occasionsID'] );
		} else {
			// No occasions id specified, create one bases on the data array.
			$occasions_data = $data + $context;

			// Don't include date in context data.
			unset( $occasions_data['date'] );

			$occasions_id = md5( json_encode( $occasions_data ) );
		}

		$data['occasionsID'] = $occasions_id;

		// Log initiator, defaults to current user if exists, or other if not user exist
		if ( isset( $context['_initiator'] ) ) {
			// Manually set in context
			$data['initiator'] = $context['_initiator'];
			unset( $context['_initiator'] );
		} else {
			// No initiator set, try to determine
			// Default to other
			$data['initiator'] = SimpleLoggerLogInitiators::OTHER;

			// Check if user is responsible.
			if ( function_exists( 'wp_get_current_user' ) ) {
				$current_user = wp_get_current_user();

				if ( isset( $current_user->ID ) && $current_user->ID ) {
					$data['initiator'] = SimpleLoggerLogInitiators::WP_USER;
					$context['_user_id'] = $current_user->ID;
					$context['_user_login'] = $current_user->user_login;
					$context['_user_email'] = $current_user->user_email;
				}
			}

			// If cron then set WordPress as responsible
			if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
				// Seems to be wp cron running and doing this.
				$data['initiator'] = SimpleLoggerLogInitiators::WORDPRESS;
				$context['_wp_cron_running'] = true;

				// To aid debugging we log the current filter and a list of all filters.
				global $wp_current_filter;
				$context['_wp_cron_current_filter'] = current_filter();
			}

			// If running as CLI and WP_CLI_PHP_USED is set then it is WP CLI that is doing it
			// How to log this? Is this a user, is it WordPress, or what?
			// I'm thinking:
			// - it is a user that is manually doing this, on purpose, with intent, so not auto wordpress
			// - it is a specific user, but we don't know who
			// - sounds like a special case, set initiator to wp_cli
			// Can be used by plugins/themes to check if WP-CLI is running or not
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				$data['initiator'] = SimpleLoggerLogInitiators::WP_CLI;
			}
		} // End if().

		// Detect XML-RPC calls and append to context, if not already there.
		if (
			defined( 'XMLRPC_REQUEST' ) &&
			XMLRPC_REQUEST &&
			! isset( $context['_xmlrpc_request'] )
		) {
			$context['_xmlrpc_request'] = true;
		}

		// Detect REST calls and append to context, if not already there.
		$isRestApiRequest =
		( defined( 'REST_API_REQUEST' ) && constant( 'REST_API_REQUEST' ) ) ||
		( defined( 'REST_REQUEST' ) && constant( 'REST_REQUEST' ) );
		if ( $isRestApiRequest ) {
			$context['_rest_api_request'] = true;
		}

		// Trim message.
		$data['message'] = trim( $data['message'] );

		/**
		 * Filter data to be saved to db.
		 *
		 * @since 2.0
		 *
		 * @param array $data
		 */
		$data = apply_filters( 'simple_history/log_insert_data', $data );

		// Insert data into db.
		$result = $wpdb->insert( $db_table, $data );

		$data_parent_row = null;

		// Only save context if able to store row.
		if ( false === $result ) {
			$history_inserted_id = null;
		} else {
			$history_inserted_id = $wpdb->insert_id;

			$db_table_contexts =
			$wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

			/**
			 * Filter table name for contexts.
			 *
			 * @since 2.0
			 *
			 * @param string $db_table_contexts
			 */
			$db_table_contexts = apply_filters(
				'simple_history/logger_db_table_contexts',
				$db_table_contexts
			);

			if ( ! is_array( $context ) ) {
				$context = array();
			}

			// Append user id to context, if not already added.
			if ( ! isset( $context['_user_id'] ) ) {
				// wp_get_current_user is not available early.
				// http://codex.wordpress.org/Function_Reference/wp_get_current_user
				// https://core.trac.wordpress.org/ticket/14024
				if ( function_exists( 'wp_get_current_user' ) ) {
					$current_user = wp_get_current_user();

					if ( isset( $current_user->ID ) && $current_user->ID ) {
						$context['_user_id'] = $current_user->ID;
						$context['_user_login'] = $current_user->user_login;
						$context['_user_email'] = $current_user->user_email;
					}
				}
			}

			// Add remote addr to context.
			if ( ! isset( $context['_server_remote_addr'] ) ) {
				$remote_addr = empty( $_SERVER['REMOTE_ADDR'] )
				? ''
				: wp_unslash( $_SERVER['REMOTE_ADDR'] );

				/**
				 * Filter to control if ip addresses should be anonymized or not.
				 * Defaults to true, meaning that any IP address is anonymized.
				 *
				 * @example Disable IP anonymization.
				 *
				 * ```php
				 * add_filter( 'simple_history/privacy/anonymize_ip_address', '__return_false' );
				 * ```
				 *
				 * @since 2.22
				 *
				 * @param bool true to anonymize ip address, false to keep original ip address.
				 */
				$anonymize_ip_address = apply_filters(
					'simple_history/privacy/anonymize_ip_address',
					true
				);

				if (
					$anonymize_ip_address &&
					function_exists( 'wp_privacy_anonymize_ip' )
				) {
					$remote_addr = wp_privacy_anonymize_ip( $remote_addr );
				}

				$context['_server_remote_addr'] = $remote_addr;

				// If web server is behind a load balancer then the ip address will always be the same
				// See bug report: https://wordpress.org/support/topic/use-x-forwarded-for-http-header-when-logging-remote_addr?replies=1#post-6422981
				// Note that the x-forwarded-for header can contain multiple ips, comma separated
				// Also note that the header can be faked
				// Ref: http://stackoverflow.com/questions/753645/how-do-i-get-the-correct-ip-from-http-x-forwarded-for-if-it-contains-multiple-ip
				// Ref: http://blackbe.lt/advanced-method-to-obtain-the-client-ip-in-php/
				// Check for IP in lots of headers
				// Based on code found here:
				// http://blackbe.lt/advanced-method-to-obtain-the-client-ip-in-php/
				$ip_keys = $this->get_ip_number_header_keys();

				foreach ( $ip_keys as $key ) {
					if ( array_key_exists( $key, $_SERVER ) === true ) {
						// Loop through all IPs.
						$ip_loop_num = 0;
						foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
							// trim for safety measures.
							$ip = trim( $ip );

							// attempt to validate IP.
							if ( $this->validate_ip( $ip ) ) {
								// valid, add to context, with loop index appended so we can store many IPs.
								$key_lower = strtolower( $key );

								if (
									$anonymize_ip_address &&
									function_exists( 'wp_privacy_anonymize_ip' )
								) {
									$ip = wp_privacy_anonymize_ip( $ip );
								}

								$context[ "_server_{$key_lower}_{$ip_loop_num}" ] = $ip;
							}

							$ip_loop_num++;
						}
					}
				}
			} // End if().

			// Append http referer.
			if (
				! isset( $context['_server_http_referer'] ) &&
				isset( $_SERVER['HTTP_REFERER'] )
			) {
				$context['_server_http_referer'] = $_SERVER['HTTP_REFERER'];
			}

			/**
			 * Filters the context to store for this event/row
			 *
			 * @example Skip adding things to the context table during logging.
			 * Useful if you don't want to add cool and possible super useful info to your logged events.
			 * Also nice to have if you want to make sure your database does not grow.
			 *
			 * ```php
			 *  add_filter(
			 *      'simple_history/log_insert_context',
			 *      function ( $context, $data ) {
			 *          unset( $context['_user_id'] );
			 *          unset( $context['_user_login'] );
			 *          unset( $context['_user_email'] );
			 *          unset( $context['server_http_user_agent'] );
			 *
			 *          return $context;
			 *      },
			 *      10,
			 *      2
			 *  );
			 * ```
			 *
			 * @since 2.0.29
			 *
			 * @param array $context Array with all context data to store. Modify and return this.
			 * @param array $data Array with data used for parent row.
			 * @param array $this Reference to this logger instance.
			 */
			$context = apply_filters(
				'simple_history/log_insert_context',
				$context,
				$data,
				$this
			);
			$data_parent_row = $data;

			// Insert all context values into db.
			$this->append_context( $history_inserted_id, $context );
		} // End if().

		$this->lastInsertID = $history_inserted_id;
		$this->lastInsertContext = $context;

		$this->simpleHistory->get_cache_incrementor( true );

		/**
		 * Fired after an event has been logged.
		 *
		 * @since 2.5.1
		 *
		 * @param array $context Array with all context data that was used to log event.
		 * @param array $data_parent_row Array with data used for parent row.
		 * @param SimpleLogger $this Reference to this logger instance.
		 */
		do_action(
			'simple_history/log/inserted',
			$context,
			$data_parent_row,
			$this
		);

		// Return $this so we can chain methods.
		return $this;
	}

	/**
	 * Append new info to the context of history item with id $post_logger->lastInsertID.
	 *
	 * @param int   $history_id The id of the history row to add context to.
	 * @param array $context Context to append to existing context for the row.
	 * @return bool True if context was added, false if not (because row_id or context is empty).
	 */
	public function append_context( $history_id, $context ) {
		if ( empty( $history_id ) || empty( $context ) ) {
			return false;
		}

		global $wpdb;

		$db_table_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

		foreach ( $context as $key => $value ) {
			// Everything except strings should be json_encoded, ie. arrays and objects.
			if ( ! is_string( $value ) ) {
				$value = simpleHistory::json_encode( $value );
			}

			$data = array(
				'history_id' => $history_id,
				'key' => $key,
				'value' => $value,
			);

			$wpdb->insert( $db_table_contexts, $data );
		}

		return true;
	}

	/**
	 * Returns array with headers that may contain user IP
	 *
	 * @since 2.0.29
	 */
	public function get_ip_number_header_keys() {
		$arr = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
		);

		return $arr;
	}

	/**
	 * Returns additional headers with ip number from context
	 *
	 * @since 2.0.29
	 * @param object $row Row with info.
	 * @return array Headers
	 */
	public function get_event_ip_number_headers( $row ) {
		$ip_keys = $this->get_ip_number_header_keys();
		$arr_found_additional_ip_headers = array();
		$context = $row->context;

		foreach ( $ip_keys as $one_ip_header_key ) {
			$one_ip_header_key_lower = strtolower( $one_ip_header_key );

			foreach ( $context as $context_key => $context_val ) {
				// $key_check_for = "_server_" . strtolower($one_ip_header_key) . "_0";
				$match = preg_match(
					"/^_server_{$one_ip_header_key_lower}_[\d+]/",
					$context_key,
					$matches
				);
				if ( $match ) {
					$arr_found_additional_ip_headers[ $context_key ] = $context_val;
				}
			}
		} // End foreach().

		return $arr_found_additional_ip_headers;
	}

	/**
	 * Ensures an ip address is both a valid IP and does not fall within
	 * a private network range.
	 *
	 * @param string $ip IP number.
	 * @return bool
	 */
	public function validate_ip( $ip ) {
		if (
			filter_var(
				$ip,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_IPV4 |
				FILTER_FLAG_NO_PRIV_RANGE |
				FILTER_FLAG_NO_RES_RANGE
			) === false
		) {
			return false;
		}

		return true;
	}

	/**
	 * Override this to add CSS in <head> for your logger.
	 * The CSS that you output will only be outputted
	 * on pages where Simple History is used.
	 */
	public function adminCSS() {
		/*
		?>
		<style>
		body {
		border: 2px solid red;
		}
		</style>
		<?php
		*/
	}

	/**
	 * Override this to add JavaScript in the footer for your logger.
	 * The JS that you output will only be outputted
	 * on pages where Simple History is used.
	 */
	public function adminJS() {
		/*
		?>
		<script>
		console.log("This is outputted in the footer");
		</script>
		<?php
		*/
	}
}
