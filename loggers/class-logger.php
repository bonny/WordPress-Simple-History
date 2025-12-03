<?php

namespace Simple_History\Loggers;

use DateTime;
use DateTimeZone;
use Simple_History\Simple_History;
use Simple_History\Log_Levels;
use Simple_History\Log_Initiators;
use Simple_History\Helpers;
use Simple_History\Services;
use Simple_History\Event_Details\Event_Details_Container_Interface;
use Simple_History\Event_Details\Event_Details_Group;

/**
 * Abstract base class for loggers.
 *
 * A PSR-3 inspired logger class.
 * This class logs + formats logs for display in the Simple History GUI/Viewer.
 *
 * Extend this class to make your own logger.
 *
 * @link https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md PSR-3 specification.
 */
abstract class Logger {
	/**
	 * Unique slug for the logger.
	 *
	 * The slug will be saved in DB and used to associate each log row with its logger.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Will contain the untranslated messages from get_info(),
	 * added when the logger is loaded in the Simple_History class..
	 *
	 * Messages here will be stored both translated and non-translated
	 * You then log something like this:
	 * <code>
	 *   $this->info( $this->messages["POST_UPDATED"] );
	 * </code>
	 * or with the shortcut
	 * <code>
	 *   $this->info_message("POST_UPDATED");
	 * </code>
	 * which results in the original, untranslated, string being added to the log and database
	 * the translated string are then only used when showing the log in the GUI.
	 *
	 * Example array contents for Swedish language and core updates logger:
	 *
	 *     [messages] => Array
	 *     (
	 *         [core_updated] => Array
	 *         (
	 *             [untranslated_text] => Updated WordPress to {new_version} from {prev_version}
	 *             [translated_text] => Uppdaterade WordPress till {new_version} från {prev_version}
	 *             [domain] => simple-history
	 *             [context] => null
	 *         )
	 *         [core_auto_updated] => Array
	 *         (
	 *             [untranslated_text] => WordPress auto-updated to {new_version} from {prev_version}
	 *             [translated_text] => WordPress auto-uppdaterades till {new_version} från {prev_version}
	 *             [domain] => simple-history
	 *             [context] => null
	 *         )
	 *     )
	 *
	 * @var array $messages
	 */
	public $messages;

	/**
	 * ID of last inserted row.
	 *
	 * @var int
	 */
	public $last_insert_id;

	/**
	 * Context of last inserted row.
	 *
	 * @var array $last_insert_context
	 * @since 2.2x
	 */
	public $last_insert_context;

	/**
	 * Data of last inserted row.
	 *
	 * @var array $last_insert_data
	 */
	public $last_insert_data;

	/**
	 * Simple History instance.
	 *
	 * @var Simple_History
	 */
	public $simple_history;

	/**
	 * Full name of simple history table, i.e. wp db prefix + simple history db name.
	 *
	 * @var string
	 */
	public $db_table;

	/**
	 * Full name of simple history contexts table, i.e. wp db prefix + simple history contexts db name.
	 *
	 * @var string
	 */
	public $db_table_contexts;

	/**
	 * Flag to track if messages have been loaded for this logger.
	 *
	 * @var bool
	 */
	private bool $messages_loaded = false;

	/**
	 * Constructor. Remember to call this as parent constructor if making a child logger.
	 *
	 * @param Simple_History $simple_history Simple History instance.
	 */
	public function __construct( $simple_history = null ) {
		$this->simple_history    = $simple_history;
		$this->db_table          = $this->simple_history->get_events_table_name();
		$this->db_table_contexts = $this->simple_history->get_contexts_table_name();
	}

	/**
	 * Method that is called automagically when logger is loaded by Simple History.
	 *
	 * Add init things here.
	 *
	 * @return void
	 */
	public function loaded() {
	}

	/**
	 * Get the slug for the logger.
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Get array with information about this logger.
	 *
	 * @return array<string,mixed> Array with keys 'name', 'description', 'messages', and so on.
	 *               See existing loggers for examples.
	 */
	abstract public function get_info();

	/**
	 * Return single array entry from the array in get_info()
	 * Returns the value of the key if value exists,
	 * or null if no value exists.
	 *
	 * @since 2.5.4
	 * @param string $key Key to get value for.
	 * @return Mixed
	 */
	public function get_info_value_by_key( $key ) {
		$arr_info = $this->get_info();

		return $arr_info[ $key ] ?? null;
	}

	/**
	 * Returns the capability required to read log rows from this logger
	 *
	 * @return string
	 */
	public function get_capability() {
		return $this->get_info_value_by_key( 'capability' ) ?? 'manage_options';
	}

	/**
	 * Returns the header initiator output for a row.
	 * Example return value is similar to:
	 * "You" or "par • par.thernstrom@gmail.com"
	 *
	 * @param object $row Log row.
	 * @return string HTML
	 */
	public function get_log_row_header_initiator_output( $row ) {
		$initiator_html = '';
		$initiator      = $row->initiator;
		$context        = $row->context;

		switch ( $initiator ) {
			case 'wp':
				$initiator_html .=
					'<strong class="SimpleHistoryLogitem__inlineDivided">WordPress</strong> ';
				break;

			case 'wp_cli':
				$initiator_html .=
					'<strong class="SimpleHistoryLogitem__inlineDivided">WP-CLI</strong> ';
				break;

			// wp_user = WordPress uses, but user may have been deleted since log entry was added.
			case 'wp_user':
				$user_id = $row->context['_user_id'] ?? null;

				$user = get_user_by( 'id', $user_id );
				if ( $user_id > 0 && ( $user ) ) {
					// Sender is user and user still exists.
					$is_current_user = get_current_user_id() === (int) $user_id;

					// get user role, as done in user-edit.php.
					$wp_roles   = $GLOBALS['wp_roles'];
					$user_roles = array_intersect(
						array_values( (array) $user->roles ),
						array_keys( (array) $wp_roles->roles )
					);
					$user_role  = array_shift( $user_roles );

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
					// output all info we have.
					$initiator_html .= sprintf(
						'<strong class="SimpleHistoryLogitem__inlineDivided">' .
							/* translators: 1: user id, 2: user email address, 3: user account name. */
							__(
								'Deleted user (had id %1$s, email %2$s, login %3$s)',
								'simple-history'
							) .
						'</strong>',
						esc_html( $context['_user_id'] ?? '' ), // 1
						esc_html( $context['_user_email'] ?? '' ), // 2
						esc_html( $context['_user_login'] ?? '' ) // 3
					);
				}

				break;

			case 'web_user':
				/*
				Note: server_remote_addr may not show visiting/attacking ip, if server is behind...stuff..
				Can be behind varnish cache, or browser can for example use compression in chrome mobile
				then the real ip is behind _server_http_x_forwarded_for_0 or similar
				_server_remote_addr 66.249.81.222
				_server_http_x_forwarded_for_0  5.35.187.212
				*/

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

			// no initiator.
			case null:
				break;

			default:
				$initiator_html .=
					"<strong class='SimpleHistoryLogitem__inlineDivided'>" .
					esc_html( $initiator ) .
					'</strong>';
		}

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

	/**
	 * Generate date output for a row.
	 *
	 * @param object $row Log row.
	 * @return string HTML
	 */
	public function get_log_row_header_date_output( $row ) {
		// HTML for date
		// Date (should...) always exist
		// http://developers.whatwg.org/text-level-semantics.html#the-time-element.
		$date_html = '';
		$str_when  = '';

		// $row->date is in GMT
		$date_datetime = new DateTime( $row->date, new DateTimeZone( 'GMT' ) );

		// Current datetime in GMT.
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

		/** @var int $time_ago_just_now_max_time Seconds */
		$time_ago_just_now_max_time = 30;

		/**
		 * Filter how many seconds as most that can pass since an
		 * event occurred to show "just now" instead of exact date
		 *
		 * @since 2.0
		 *
		 * @param int $time_ago_max_time Seconds
		 */
		$time_ago_just_now_max_time = apply_filters(
			'simple_history/header_just_now_max_time',
			$time_ago_just_now_max_time
		);

		$date_format          = get_option( 'date_format' );
		$time_format          = get_option( 'time_format' );
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
			$time_current - $date_datetime->getTimestamp() <= $time_ago_just_now_max_time
		) {
			// Show "just now" if event is very recent.
			$str_when = __( 'Just now', 'simple-history' );
		} elseif (
			$time_current - $date_datetime->getTimestamp() > $time_ago_max_time
		) {
			/* translators: Date format for log row header, see http://php.net/date */
			$datef    = __( 'M j, Y \a\t G:i', 'simple-history' );
			$str_when = date_i18n(
				$datef,
				strtotime( get_date_from_gmt( $row->date ) )
			);
		} else {
			// Show "nn minutes ago" when event is xx seconds ago or earlier.
			$date_human_time_diff = human_time_diff(
				$date_datetime->getTimestamp(),
				$time_current
			);

			$str_when = sprintf(
				/* translators: %s last modified date and time in human time diff-format */
				__( '%1$s ago', 'simple-history' ),
				$date_human_time_diff
			);
		}

		$item_permalink = Helpers::get_history_admin_url();

		if ( ! empty( $row->id ) ) {
			$item_permalink .= "#simple-history/event/{$row->id}";
		}

		// Datetime attribute on <time> element.
		$str_datetime_title = sprintf(
			/* translators: 1: local time string, 2: GMT time string. */
			__( '%1$s local time %3$s (%2$s GMT time)', 'simple-history' ),
			get_date_from_gmt(
				$date_datetime->format( 'Y-m-d H:i:s' ),
				$date_and_time_format
			), // 1 local time
			$date_datetime->format( $date_and_time_format ), // GMT time.
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- This explains sprintf placeholder.
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
		$date_html  =
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

	/**
	 * Get the log row header output for the row when "name_via" is set.
	 *
	 * @param object $row Log row.
	 * @return string HTML
	 */
	public function get_log_row_header_using_plugin_output( $row ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Logger "via" info in header, i.e. output some extra
		// info next to the time to make it more clear what plugin etc.
		// that "caused" this event.
		$logger_name_via = $this->get_info_value_by_key( 'name_via' );

		if ( ! $logger_name_via ) {
			return;
		}

		$via_html  = "<span class='SimpleHistoryLogitem__inlineDivided SimpleHistoryLogitem__via'>";
		$via_html .= $logger_name_via;
		$via_html .= '</span>';

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
	 * @param mixed $row Log row.
	 * @return string
	 */
	public function get_log_row_header_ip_address_output( $row ) {
		/**
		 * Filter if IP Address should be added to header row.
		 *
		 * @since 2.x
		 *
		 * @param bool $show_ip_address True to show IP address, false to hide it. Defaults to false.
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
		$html    = "<span class='SimpleHistoryLogitem__inlineDivided SimpleHistoryLogitem__anonUserWithIp'>";

		// Look for additional ip addresses.
		$arr_found_additional_ip_headers = Helpers::get_event_ip_number_headers( $row );

		$arr_ip_addresses = array_merge(
			// Remote addr always exists.
			array( '_server_remote_addr' => $context['_server_remote_addr'] ),
			$arr_found_additional_ip_headers
		);

		$first_ip_address = reset( $arr_ip_addresses );

		// Output single or plural text.
		if ( count( $arr_ip_addresses ) === 1 ) {
			// Single ip address.
			$iplookup_link = sprintf(
				'https://ipinfo.io/%1$s',
				esc_attr( Helpers::get_valid_ip_address_from_anonymized( $first_ip_address ) )
			);

			$html .= sprintf(
				/* translators: %s link to ipinfo.io with first IP as link label. */
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
					esc_attr( Helpers::get_valid_ip_address_from_anonymized( $ip_address ) )
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
				/* translators: %s list of links to ipinfo.io with IP:s as link labels. */
				__( 'IP Addresses %1$s', 'simple-history' ),
				$ip_addresses_html
			);
		}

		$html .= '</span> ';

		return $html;
	}

	/**
	 * Returns header output for a log row,
	 * by concatenating the output from the other header methods.
	 *
	 * Format should be common for all log rows and should be like:
	 * Username (user role) • Date • IP Address • Via plugin abc
	 * I.e.:
	 * Initiator • Date/time • IP Address • Via logger
	 *
	 * @param object $row Row data.
	 * @return string HTML
	 */
	public function get_log_row_header_output( $row ) {
		$initiator_html  = $this->get_log_row_header_initiator_output( $row );
		$date_html       = $this->get_log_row_header_date_output( $row );
		$via_html        = $this->get_log_row_header_using_plugin_output( $row );
		$ip_address_html = $this->get_log_row_header_ip_address_output( $row );

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
	 *
	 * @param object $row Log row.
	 * @return string Plain text
	 */
	public function get_log_row_plain_text_output( $row ) {
		$message     = $row->message;
		$message_key = $row->context['_message_key'] ?? null;

		// Message is translated here, but translation must be added in
		// plain text before.
		if ( empty( $message_key ) ) {
			// Message key did not exist, so check if we should translate using textdomain.
			if ( ! empty( $row->context['_gettext_domain'] ) ) {
   				// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain, WordPress.WP.I18n.NonSingularStringLiteralText
				$message = __( $message, $row->context['_gettext_domain'] );
			}
		} else {
			$translated_message = $this->get_translated_message( $message_key );

			if ( $translated_message !== null ) {
				// Check that messages does exist
				// If we for example disable a Logger we may have references
				// to message keys that are unavailable. If so then fallback to message.
				$message = $translated_message;
			} else { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedElse
					// Not message exists for message key. Just keep using message.
			}
		}

		$html = helpers::interpolate( $message, $row->context, $row );

		// All messages are escaped by default.
		// If you need unescaped output override this method
		// in your own logger.
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
	 * or other images if sender i system, WordPress, and so on
	 *
	 * @param object $row Log row.
	 */
	public function get_log_row_sender_image_output( $row ) {
		$sender_image_html = '';
		$sender_image_size = 32;

		$initiator = $row->initiator;

		if ( $initiator === 'wp_user' ) {
			$user_id = $row->context['_user_id'] ?? null;
			$user    = get_user_by( 'id', $user_id );
			if ( $user_id > 0 && ( $user ) ) {
					// Sender was user.
					$sender_image_html = Helpers::get_avatar(
						$user->user_email,
						$sender_image_size
					);
			} elseif ( $user_id > 0 ) {
				// Sender was a user, but user is deleted now.
				$sender_image_html = Helpers::get_avatar(
					'',
					$sender_image_size
				);
			} else {
				$sender_image_html = Helpers::get_avatar(
					'',
					$sender_image_size
				);
			}
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
	 * @param object $row Log row.
	 * @return string|Event_Details_Container_Interface|Event_Details_Group HTML-formatted output or Event_Details_Container (stringable object).
	 */
	public function get_log_row_details_output( $row ) {
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
	 * @param string $message Message to log.
	 * @param array  $context Context to log.
	 * @return Logger
	 */
	public function emergency( $message, array $context = array() ) {
		return $this->log( Log_Levels::EMERGENCY, $message, $context );
	}

	/**
	 * System is unusable.
	 *
	 * @param string $message key from get_info messages array.
	 * @param array  $context Context to log.
	 */
	public function emergency_message( $message, array $context = array() ) {
		return $this->log_by_message_key(
			Log_Levels::EMERGENCY,
			$message,
			$context
		);
	}

	/**
	 * Log with message
	 * Called from info_message(), error_message(), and so on
	 *
	 * @param string $SimpleLoggerLogLevelsLevel Log level.
	 * @param string $messageKey Message key.
	 * @param array  $context Context to log.
	 */
	private function log_by_message_key(
		$SimpleLoggerLogLevelsLevel,
		$messageKey,
		$context
	) {
		// Ensure messages are loaded before checking if key exists.
		$this->ensure_messages_loaded();

		// When logging by message then the key must exist.
		if ( ! isset( $this->messages[ $messageKey ]['untranslated_text'] ) ) {
			return;
		}

		/**
		 * Filter so plugins etc. can shortcut logging
		 *
		 * @since 2.0.20
		 *
		 * @param bool $do_log true yes, we default to do the logging
		 * @param string $slug logger slug
		 * @param string $messageKey
		 * @param string $log_level
		 * @param array $context
		 * @return bool false to abort logging
		 */
		$doLog = apply_filters(
			'simple_history/simple_logger/log_message_key',
			true,
			$this->get_slug(),
			$messageKey,
			$SimpleLoggerLogLevelsLevel,
			$context
		);

		if ( ! $doLog ) {
			return;
		}

		$context['_message_key'] = $messageKey;

		$message = $this->get_untranslated_message( $messageKey );
		if ( $message !== null ) {
			$this->log( $SimpleLoggerLogLevelsLevel, $message, $context );
		}
	}

	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context to log.
	 * @return null
	 */
	public function alert( $message, array $context = array() ) {
		return $this->log( Log_Levels::ALERT, $message, $context );
	}

	/**
	 * Action must be taken immediately.
	 *
	 * @param string $message key from get_info messages array.
	 * @param array  $context  Context to log.
	 * @return null
	 */
	public function alert_message( $message, array $context = array() ) {
		return $this->log_by_message_key(
			Log_Levels::ALERT,
			$message,
			$context
		);
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context to log.
	 * @return null
	 */
	public function critical( $message, array $context = array() ) {
		return $this->log( Log_Levels::CRITICAL, $message, $context );
	}

	/**
	 * Critical conditions.
	 *
	 * @param string $message key from get_info messages array.
	 * @param array  $context Context to log.
	 */
	public function critical_message( $message, array $context = array() ) {
		$untranslated_message = $this->get_untranslated_message( $message );

		if ( $untranslated_message === null ) {
			return;
		}

		$context['_message_key'] = $message;

		$this->log( Log_Levels::CRITICAL, $untranslated_message, $context );
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context to log.
	 */
	public function error( $message, array $context = array() ) {
		return $this->log( Log_Levels::ERROR, $message, $context );
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message key from get_info messages array.
	 * @param array  $context Context to log.
	 * @return null
	 */
	public function error_message( $message, array $context = array() ) {
		return $this->log_by_message_key(
			Log_Levels::ERROR,
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
	 * @param string $message Message to log.
	 * @param array  $context Context to log.
	 * @return null
	 */
	public function warning( $message, array $context = array() ) {
		return $this->log( Log_Levels::WARNING, $message, $context );
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string $message key from get_info messages array.
	 * @param array  $context Context to log.
	 * @return null
	 */
	public function warning_message( $message, array $context = array() ) {
		return $this->log_by_message_key(
			Log_Levels::WARNING,
			$message,
			$context
		);
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context to log.
	 * @return null
	 */
	public function notice( $message, array $context = array() ) {
		return $this->log( Log_Levels::NOTICE, $message, $context );
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message key from get_info messages array.
	 * @param array  $context Context to log.
	 * @return null
	 */
	public function notice_message( $message, array $context = array() ) {
		return $this->log_by_message_key(
			Log_Levels::NOTICE,
			$message,
			$context
		);
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context to log.
	 * @return Logger SimpleLogger instance
	 */
	public function info( $message, array $context = array() ) {
		return $this->log( Log_Levels::INFO, $message, $context );
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message key from get_info messages array.
	 * @param array  $context Context to log.
	 * @return null
	 */
	public function info_message( $message, array $context = array() ) {
		return $this->log_by_message_key(
			Log_Levels::INFO,
			$message,
			$context
		);
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context to log.
	 * @return null
	 */
	public function debug( $message, array $context = array() ) {
		return $this->log( Log_Levels::DEBUG, $message, $context );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message key from get_info messages array.
	 * @param array  $context Context to log.
	 * @return null
	 */
	public function debug_message( $message, array $context = array() ) {
		return $this->log_by_message_key(
			Log_Levels::DEBUG,
			$message,
			$context
		);
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * This is the function that all other log functions call in the end.
	 *
	 * @param mixed  $level The log level. Default "info".
	 * @param string $message The log message. Default "".
	 * @param array  $context The log context. Default empty array.
	 * @return Logger SimpleLogger instance
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

		$message = trim( $message );

		// Don't go on if message is empty.
		if ( empty( $message ) ) {
			return $this;
		}

		/**
		 * Filter that makes it possible to shortcut the logging of a message.
		 * Return bool false to cancel logging.
		 *
		 * @since 2.3.1
		 *
		 * @param bool $doLog Whether to log or not.
		 * @param string $level The loglevel.
		 * @param string $message The log message.
		 * @param array $context The message context.
		 * @param Logger $instance Logger instance.
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
			"simple_history/log/do_log/{$this->get_slug()}",
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
		$message_key = $context['_message_key'] ?? null;
		$do_log      = apply_filters(
			"simple_history/log/do_log/{$this->get_slug()}/{$message_key}",
			true
		);

		if ( false === $do_log ) {
			return $this;
		}

		/**
		 * Filter arguments passed to log function
		 *
		 * @since 2.0
		 *
		 * @param string $level
		 * @param string $message
		 * @param array $context
		 * @param object $instance SimpleLogger object
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

		/**
		 * Store date as GMT/UTC date, i.e. not local date/time.
		 *
		 * @see http://www.skyverge.com/blog/down-the-rabbit-hole-wordpress-and-timezones/
		 * @var string $date_gmt Date in GMT/UTC format.
		 */
		$date_gmt = current_time( 'mysql', 1 );

		/**
		 * Main table data row array.
		 *
		 * @array $data Data to be inserted into database.
		 */
		$data = array(
			'logger'  => $this->get_slug(),
			'level'   => $level,
			'date'    => $date_gmt,
			'message' => $message,
		);

		[$data, $context] = $this->append_date_to_context( $data, $context );
		[$data, $context] = $this->append_occasions_id_to_context( $data, $context );
		[$data, $context] = $this->append_initiator_to_context( $data, $context );
		$context          = $this->append_xmlrpc_request_to_context( $context );
		$context          = $this->append_rest_api_request_to_context( $context );

		/**
		 * Filter data to be saved to db.
		 *
		 * @since 2.0
		 *
		 * @param array $data
		 */
		$data = apply_filters( 'simple_history/log_insert_data', $data );

		if ( ! is_array( $context ) ) {
			$context = array();
		}

		$context = $this->append_user_context( $context );
		$context = $this->append_remote_addr_to_context( $context );

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
		 * @param Logger $instance Reference to this logger instance.
		 */
		$context = apply_filters(
			'simple_history/log_insert_context',
			$context,
			$data,
			$this
		);

		/**
		 * Filter that lets user modify both data and context before logging.
		 *
		 * @param array $arr_data_and_context Array with numerical keys [0] = data and [1] = context.
		 * @param Logger $instance Reference to this logger instance.
		 */
		[$data, $context] = apply_filters(
			'simple_history/log_insert_data_and_context',
			array( $data, $context ),
			$this
		);

		// Insert data into db.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert( $this->db_table, $data );

		// Auto-recover from missing tables.
		if ( false === $result && ! empty( $wpdb->last_error ) ) {
			if ( Services\Setup_Database::is_table_missing_error( $wpdb->last_error ) ) {
				// Try to recreate tables.
				$recreated = Services\Setup_Database::recreate_tables_if_missing();

				if ( $recreated ) {
					// Retry the insert after recreating tables.
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$result = $wpdb->insert( $this->db_table, $data );
				}
			}
		}

		// Save context if able to store row.
		if ( false === $result ) {
			$history_inserted_id = null;
		} else {
			$history_inserted_id = $wpdb->insert_id;

			// Insert all context values into db.
			$this->append_context( $history_inserted_id, $context );
		}

		$this->last_insert_id      = $history_inserted_id;
		$this->last_insert_context = $context;
		$this->last_insert_data    = $data;

		Helpers::clear_cache();

		/**
		 * Fired after an event has been logged.
		 *
		 * @since 2.5.1
		 *
		 * @param array $context Array with all context data that was used to log event.
		 * @param array $data_parent_row Array with data used for parent/main row.
		 * @param Logger $instance Reference to this logger instance.
		 */
		do_action(
			'simple_history/log/inserted',
			$context,
			$data,
			$this
		);

		Helpers::increase_total_logged_events_count();

		return $this;
	}

	/**
	 * Append new info to the context of history item with id $post_logger->last_insert_id.
	 *
	 * @param int   $history_id The id of the history row to add context to.
	 * @param array $context Context to append to existing context for the row.
	 * @return bool True if context was added, false if not (because row_id or context is empty).
	 */
	public function append_context( $history_id, $context ) {
		// Use new batched method.
		return $this->append_context_batched( $history_id, $context );
	}

	/**
	 * Append new info to the context of history item using batch inserts for better performance.
	 * This method uses size-based batching to ensure queries stay within database limits.
	 *
	 * @param int   $history_id The id of the history row to add context to.
	 * @param array $context Context to append to existing context for the row.
	 * @return bool True if context was added, false if not (because row_id or context is empty).
	 */
	public function append_context_batched( $history_id, $context ) {
		if ( empty( $history_id ) || empty( $context ) ) {
			return false;
		}

		global $wpdb;

		// Debug tracking variables.
		$debug_total_size  = 0;
		$debug_total_items = count( $context );
		$debug_start_time  = microtime( true );

		// Conservative batch size: 500KB to ensure compatibility with 4MB max_allowed_packet.
		$batch_max_size_bytes = 500000;
		$current_batch_size   = 0;
		$current_batch        = array();

		/*
		 * Array of batches, where each batch is an associative array of key => value pairs.
		 * Each batch will be inserted with a single query.
		 * Example structure:
		 * [
		 *     0 => ['post_title' => 'Hello', 'post_status' => 'publish', 'post_author' => '1'],  // Batch 1: 3 items, 1 query.
		 *     1 => ['post_content' => 'Large content...', 'post_excerpt' => 'Summary...'],        // Batch 2: 2 items, 1 query.
		 * ]
		 */
		$batches = array();

		foreach ( $context as $context_key => $context_value ) {
			// Everything except strings should be json_encoded.
			if ( ! is_string( $context_value ) ) {
				$context_value = Helpers::json_encode( $context_value );
			}

			// Strip 4-byte UTF-8 chars (emojis) that fail with utf8 charset tables.
			$context_value = Helpers::strip_4_byte_chars( $context_value );

			// Calculate size of this item: key + value + SQL overhead.
			// Add 100 bytes for SQL syntax, quotes, escaping overhead.
			$item_size         = strlen( $context_key ) + strlen( $context_value ) + 100;
			$debug_total_size += $item_size;

			// If single item is larger than the batch max size, handle it separately.
			if ( $item_size > $batch_max_size_bytes ) {
				// Flush current batch first.
				if ( ! empty( $current_batch ) ) {
					$batches[]          = $current_batch;
					$current_batch      = array();
					$current_batch_size = 0;
				}

				// Add oversized item as single-item batch.
				$batches[] = array( $context_key => $context_value );
				continue;
			}

			// If adding this item would exceed batch size, start new batch.
			if ( $current_batch_size + $item_size > $batch_max_size_bytes && ! empty( $current_batch ) ) {
				$batches[]          = $current_batch;
				$current_batch      = array();
				$current_batch_size = 0;
			}

			$current_batch[ $context_key ] = $context_value;
			$current_batch_size           += $item_size;
		}

		// Add final batch if not empty.
		if ( ! empty( $current_batch ) ) {
			$batches[] = $current_batch;
		}

		// Execute batches.
		foreach ( $batches as $batch ) {
			// Build batch insert query.
			$values       = array();
			$placeholders = array();

			foreach ( $batch as $context_key => $context_value ) {
				$values[]       = $history_id;
				$values[]       = $context_key;
				$values[]       = $context_value;
				$placeholders[] = '(%d, %s, %s)';
			}

			// Execute batch insert.
			$sql = "INSERT INTO {$this->db_table_contexts} (history_id, `key`, value) VALUES "
				. implode( ', ', $placeholders );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $wpdb->prepare( $sql, $values ) );
		}

		// Log debug summary if debug logging is enabled.
		$enable_debug = false;
		if ( $enable_debug ) {
			$debug_elapsed_time   = microtime( true ) - $debug_start_time;
			$debug_num_batches    = count( $batches );
			$debug_avg_batch_size = $debug_num_batches > 0 ? round( $debug_total_size / $debug_num_batches ) : 0;

			sh_error_log(
				'[append_context_batched]',
				'history_id=' . $history_id,
				'items=' . $debug_total_items,
				'total_size=' . size_format( $debug_total_size ),
				'batches=' . $debug_num_batches,
				'queries=' . $debug_num_batches,
				'avg_batch=' . size_format( $debug_avg_batch_size ),
				'time=' . $debug_elapsed_time . 's'
			);
		}

		return true;
	}

	/**
	 * Returns additional headers with ip numbers from context.
	 *
	 * @since 2.0.29
	 * @deprecated 4.3.1 Use Helpers::get_event_ip_number_headers() instead.
	 * @param object $row Row with info.
	 * @return array Headers
	 */
	public function get_event_ip_number_headers( $row ) {
		_deprecated_function( __METHOD__, '4.3.1', 'Helpers::get_event_ip_number_headers()' );
		return Helpers::get_event_ip_number_headers( $row );
	}

	/**
	 * Override this to add CSS in <head> for your logger.
	 * The CSS that you output will only be outputted
	 * on pages where Simple History is used.
	 */
	public function admin_css() {
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
	public function admin_js() {
		/*
		?>
		<script>
		console.log("This is outputted in the footer");
		</script>
		<?php
		*/
	}

	/**
	 * Ensure messages are loaded for this logger instance.
	 * This method will load messages on-demand using gettext filters,
	 * similar to the previous global approach but per-logger.
	 *
	 * @return void
	 */
	private function ensure_messages_loaded(): void {
		if ( $this->messages_loaded ) {
			return;
		}

		$this->load_messages();
		$this->messages_loaded = true;
	}

	/**
	 * Get a single message by message key.
	 *
	 * @param string $message_key The message key.
	 * @return array|null Array with 'translated_text' and 'untranslated_text' keys, or null if not found.
	 */
	public function get_message_by_key( string $message_key ): ?array {
		$this->ensure_messages_loaded();

		if ( ! isset( $this->messages[ $message_key ] ) ) {
			return null;
		}

		return $this->messages[ $message_key ];
	}

	/**
	 * Get all messages for this logger.
	 *
	 * @return array Array of messages with message keys as keys and message data as values.
	 */
	public function get_messages(): array {
		$this->ensure_messages_loaded();

		return $this->messages;
	}

	/**
	 * Get translated text for a message key.
	 *
	 * @param string $message_key The message key.
	 * @return string|null Translated text or null if not found.
	 */
	public function get_translated_message( string $message_key ): ?string {
		$message_data = $this->get_message_by_key( $message_key );
		return $message_data['translated_text'] ?? null;
	}

	/**
	 * Get untranslated text for a message key.
	 *
	 * @param string $message_key The message key.
	 * @return string|null Untranslated text or null if not found.
	 */
	public function get_untranslated_message( string $message_key ): ?string {
		$message_data = $this->get_message_by_key( $message_key );
		return $message_data['untranslated_text'] ?? null;
	}

	/**
	 * Load messages for this logger using gettext filters.
	 * This is the same approach as the global loader but applied per-logger.
	 *
	 * @return void
	 */
	private function load_messages(): void {
		// Temporarily add gettext filters for this logger only.
		add_filter( 'gettext', array( $this, 'filter_gettext' ), 20, 3 );
		add_filter( 'gettext_with_context', array( $this, 'filter_gettext_with_context' ), 20, 4 );

		// Get logger info to trigger translations.
		$logger_info = $this->get_info();

		// Remove gettext filters immediately.
		remove_filter( 'gettext', array( $this, 'filter_gettext' ), 20 );
		remove_filter( 'gettext_with_context', array( $this, 'filter_gettext_with_context' ), 20 );

		// Process messages (same logic as original Loggers_Loader).
		$arr_messages_by_message_key = array();

		if ( isset( $logger_info['messages'] ) && is_array( $logger_info['messages'] ) ) {
			foreach ( $logger_info['messages'] as $message_key => $message_translated ) {
				// Find message in array with both translated and non translated strings.
				foreach ( $this->messages as $one_message_with_translation_info ) {
					if ( $message_translated === $one_message_with_translation_info['translated_text'] ) {
						$arr_messages_by_message_key[ $message_key ] = $one_message_with_translation_info;
						continue;
					}
				}
			}
		}

		$this->messages = $arr_messages_by_message_key;
	}

	/**
	 * Store both translated and untranslated versions of a text.
	 * Moved from Loggers_Loader to work per-logger.
	 *
	 * @param string $translated_text Translated text.
	 * @param string $untranslated_text Untranslated text.
	 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
	 * @return string Translated text.
	 */
	public function filter_gettext( $translated_text, $untranslated_text, $domain ) {
		$this->messages[] = array(
			'untranslated_text' => $untranslated_text,
			'translated_text'   => $translated_text,
			'domain'            => $domain,
			'context'           => null,
		);

		return $translated_text;
	}

	/**
	 * Store both translated and untranslated versions of a text with context.
	 * Moved from Loggers_Loader to work per-logger.
	 *
	 * @param string $translated_text Translated text.
	 * @param string $untranslated_text Untranslated text.
	 * @param string $context Context information for the translators.
	 * @param string $domain Text domain. Unique identifier for retrieving translated strings.
	 * @return string Translated text.
	 */
	public function filter_gettext_with_context( $translated_text, $untranslated_text, $context, $domain ) {
		$this->messages[] = array(
			'untranslated_text' => $untranslated_text,
			'translated_text'   => $translated_text,
			'domain'            => $domain,
			'context'           => $context,
		);

		return $translated_text;
	}

	/**
	 * Append user data to context.
	 *
	 * @param array $context Context.
	 * @return array $context Context with user data appended.
	 */
	private function append_user_context( $context ) {
		if ( isset( $context['_user_id'] ) ) {
			return $context;
		}

		// Bail if `wp_get_current_user` is not loaded,
		// because is not available early. (?)
		// https://developer.wordpress.org/reference/functions/wp_get_current_user/
		// https://core.trac.wordpress.org/ticket/14024.
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			return $context;
		}

		$current_user = wp_get_current_user();

		// Bail if no user is set.
		if ( $current_user->ID === 0 ) {
			return $context;
		}

		$context['_user_id']    = $current_user->ID;
		$context['_user_login'] = $current_user->user_login;
		$context['_user_email'] = $current_user->user_email;

		return $context;
	}

	/**
	 * Append initiator to context
	 * If no initiator is set then try to determine it
	 *
	 * @param array $data Data.
	 * @param array $context Context.
	 * @return array $data as first key, $context as second key.
	 */
	private function append_initiator_to_context( $data, $context ) {
		if ( isset( $context['_initiator'] ) ) {
			// Manually set in context.
			$data['initiator'] = $context['_initiator'];
			unset( $context['_initiator'] );
		} else {
			// No initiator set, try to determine
			// Default to other.
			$data['initiator'] = Log_Initiators::OTHER;

			// Check if user is responsible.
			if ( function_exists( 'wp_get_current_user' ) ) {
				$current_user = wp_get_current_user();

				if ( isset( $current_user->ID ) && $current_user->ID ) {
					$data['initiator']      = Log_Initiators::WP_USER;
					$context['_user_id']    = $current_user->ID;
					$context['_user_login'] = $current_user->user_login;
					$context['_user_email'] = $current_user->user_email;
				}
			}

			// If cron then set WordPress as responsible.
			if ( wp_doing_cron() ) {
				$data['initiator']           = Log_Initiators::WORDPRESS;
				$context['_wp_cron_running'] = true;

				// To aid debugging we log the current filter and a list of all filters.
				if ( Helpers::log_debug_is_enabled() ) {
					$context['_wp_cron_current_filter'] = current_filter();
				}
			}

			// If running as CLI and WP_CLI_PHP_USED is set then it is WP CLI that is doing it.
			if ( defined( \WP_CLI::class ) && WP_CLI ) {
				$data['initiator'] = Log_Initiators::WP_CLI;
			}
		}

		return array( $data, $context );
	}

	/**
	 * Append remote addr and other related headers to to context.
	 *
	 * @param array $context Context.
	 * @return array $context
	 */
	private function append_remote_addr_to_context( $context ) {
		if ( ! isset( $context['_server_remote_addr'] ) ) {
			// Validate and sanitize REMOTE_ADDR.
			$remote_addr = '';
			// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders -- REMOTE_ADDR is validated with filter_var() below
			if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
				$remote_addr = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
				// Validate that it's a proper IP address.
				$validated_ip = filter_var( $remote_addr, FILTER_VALIDATE_IP );
				$remote_addr  = $validated_ip !== false ? $validated_ip : '';
			}
			// phpcs:enable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders

			$context['_server_remote_addr'] = Helpers::privacy_anonymize_ip( $remote_addr );

			// phpcs:disable Squiz.PHP.CommentedOutCode.Found
			// Fake some headers to test.
			// phpcs:disable Squiz.Commenting.InlineComment.InvalidEndChar
			// $_SERVER['HTTP_CLIENT_IP'] = '216.58.209.99';
			// $_SERVER['HTTP_X_FORWARDED_FOR'] = '5.35.187.2';
			// $_SERVER['HTTP_X_FORWARDED'] = '144.63.252.10';
			// $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'] = '5.35.187.4';
			// phpcs:enable Squiz.Commenting.InlineComment.InvalidEndChar

			// If web server is behind a load balancer then the ip address will always be the same
			// See bug report: https://wordpress.org/support/topic/use-x-forwarded-for-http-header-when-logging-remote_addr?replies=1#post-6422981
			// Note that the x-forwarded-for header can contain multiple ips, comma separated
			// Also note that the header can be faked
			// Ref: http://stackoverflow.com/questions/753645/how-do-i-get-the-correct-ip-from-http-x-forwarded-for-if-it-contains-multiple-ip
			// Ref: http://blackbe.lt/advanced-method-to-obtain-the-client-ip-in-php/
			// Check for IP in lots of headers
			// Based on code:
			// http://blackbe.lt/advanced-method-to-obtain-the-client-ip-in-php/.
			$ip_keys = Helpers::get_ip_number_header_names();

			foreach ( $ip_keys as $key ) {
				if ( array_key_exists( $key, $_SERVER ) ) {
					// Loop through all IPs.
					$ip_loop_num = 0;
					foreach ( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ) as $ip ) {
						// trim for safety measures.
						$ip = trim( $ip );

						// attempt to validate IP.
						if ( Helpers::is_valid_public_ip( $ip ) ) {
							// valid, add to context, with loop index appended so we can store many IPs.
							$key_lower = strtolower( $key );
							$ip        = Helpers::privacy_anonymize_ip( $ip );

							$context[ "_server_{$key_lower}_{$ip_loop_num}" ] = $ip;
						}

						++$ip_loop_num;
					}
				}
			}
		} // End if().

		// Append http referer.
		if (
			! isset( $context['_server_http_referer'] ) &&
			isset( $_SERVER['HTTP_REFERER'] )
		) {
			$context['_server_http_referer'] = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		return $context;
	}

	/**
	 * Append occasionsID to context.
	 *
	 * @param array $data Data.
	 * @param array $context Context.
	 * @return array $data as first key, $context as second key.
	 */
	private function append_occasions_id_to_context( $data, $context ) {
		if ( isset( $context['_occasionsID'] ) ) {
			// Minimize risk of similar loggers logging same messages and such and resulting in same occasions id
			// by generating a new occasionsID with logger slug appended.
			$occasions_data = array(
				'_occasionsID' => $context['_occasionsID'],
				'_loggerSlug'  => $this->get_slug(),
			);
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			$occasions_id = md5( json_encode( $occasions_data ) );
			unset( $context['_occasionsID'] );
		} else {
			// No occasions id specified, create one based on the data array.
			$occasions_data = $data + $context;

			// Don't include date in context data.
			unset( $occasions_data['date'] );

			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			$occasions_id = md5( json_encode( $occasions_data ) );
		}

		$data['occasionsID'] = $occasions_id;

		return array( $data, $context );
	}

	/**
	 * Append _xmlrpc_request to context if this is a XMLRPC request.
	 *
	 * @param array $context Context.
	 * @return array $context Context with _xmlrpc_request appended.
	 */
	private function append_xmlrpc_request_to_context( $context ) {
		if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
			return $context;
		}

		$context['_xmlrpc_request'] = true;

		return $context;
	}

	/**
	 * Append _rest_api_request to context if this is a REST API request.
	 *
	 * @param array $context Context.
	 * @return array $context Context with _rest_api_request appended.
	 */
	private function append_rest_api_request_to_context( $context ) {
		// Detect REST calls and append to context, if not already there.
		$is_rest_api_request = defined( 'REST_API_REQUEST' ) && constant( 'REST_API_REQUEST' );
		$is_rest_request     = defined( 'REST_REQUEST' ) && constant( 'REST_REQUEST' );

		if ( $is_rest_api_request || $is_rest_request ) {
			$context['_rest_api_request'] = true;
		}

		return $context;
	}

	/**
	 * Set date on data array if date is set in context.
	 * This is used to override the date that is set by default.
	 * The date must be in format 'Y-m-d H:i:s'.
	 *
	 * @param array $data Data.
	 * @param array $context Context.
	 * @return array $data as first key, $context as second key.
	 */
	private function append_date_to_context( $data, $context ) {
		// Allow date to be overridden from context.
		// Date must be in format 'Y-m-d H:i:s'.
		if ( isset( $context['_date'] ) ) {
			$data['date'] = $context['_date'];
			unset( $context['_date'] );
		}

		return array( $data, $context );
	}

	/**
	 *  Magic getter for "_slug".
	 *  Used for backwards compatibility.
	 *
	 * @param string $name Name of property to get.
	 */
	public function __get( $name ) {
		if ( 'slug' === $name ) {
			_deprecated_function( __METHOD__, '4.5.1', 'get_slug()' );
			return $this->get_slug();
		}
	}

	/**
	 * Check if logger is enabled or disabled.
	 * If a logger is missing the "enabled_by_default" they are considered enabled by default.
	 *
	 * @return bool True if enabled, false if disabled.
	 */
	public function is_enabled() {
		/** @var bool $is_enabled_by_default */
		$is_enabled_by_default = $this->get_info_value_by_key( 'enabled_by_default' ) ?? true;

		/**
		 * Filter the default enabled state of a logger.
		 *
		 * @param bool $is_enabled_by_default
		 * @param string $slug
		 * @return bool
		 */
		$is_enabled = apply_filters(
			'simple_history/logger/enabled',
			$is_enabled_by_default,
			$this->get_slug()
		);

		return $is_enabled;
	}
}
