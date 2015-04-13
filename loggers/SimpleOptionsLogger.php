<?php

defined( 'ABSPATH' ) or die();

/*

<form> is posted to options.php

If $_GET['action'] == 'update' we are saving settings sent from a settings page

update_option( $option, $value );
set_transient('settings_errors', get_settings_errors(), 30);


 * Fires immediately before an option value is updated.
 * @param string $option    Name of the option to update.
 * @param mixed  $old_value The old option value.
 * @param mixed  $value     The new option value.
do_action( 'update_option', $option, $old_value, $value );


 * Fires after the value of an option has been successfully updated.
 *
 * @since 2.9.0
 *
 * @param string $option    Name of the updated option.
 * @param mixed  $old_value The old option value.
 * @param mixed  $value     The new option value.
 do_action( 'updated_option', $option, $old_value, $value );

options white list.
$whitelist_options = apply_filters( 'whitelist_options', $whitelist_options );
Array
(
	[general] => Array
		(
			[0] => blogname
			[1] => blogdescription
			[2] => gmt_offset
			[3] => date_format
			[4] => time_format
			[5] => start_of_week
			[6] => timezone_string
			[7] => siteurl
			[8] => home
			[9] => admin_email
			[10] => users_can_register
			[11] => default_role
		)

	[discussion] => Array
		(
			[0] => default_pingback_flag
			[1] => default_ping_status
			[2] => default_comment_status
			[3] => comments_notify
			[4] => moderation_notify
			[5] => comment_moderation
			[6] => require_name_email
			[7] => comment_whitelist
			[8] => comment_max_links
			[9] => moderation_keys
			[10] => blacklist_keys
			[11] => show_avatars
			[12] => avatar_rating
			[13] => avatar_default
			[14] => close_comments_for_old_posts
			[15] => close_comments_days_old
			[16] => thread_comments
			[17] => thread_comments_depth
			[18] => page_comments
			[19] => comments_per_page
			[20] => default_comments_page
			[21] => comment_order
			[22] => comment_registration
		)

	[media] => Array
		(
			[0] => thumbnail_size_w
			[1] => thumbnail_size_h
			[2] => thumbnail_crop
			[3] => medium_size_w
			[4] => medium_size_h
			[5] => large_size_w
			[6] => large_size_h
			[7] => image_default_size
			[8] => image_default_align
			[9] => image_default_link_type
			[10] => uploads_use_yearmonth_folders
		)

	[reading] => Array
		(
			[0] => posts_per_page
			[1] => posts_per_rss
			[2] => rss_use_excerpt
			[3] => show_on_front
			[4] => page_on_front
			[5] => page_for_posts
			[6] => blog_public
		)

	[writing] => Array
		(
			[0] => use_smilies
			[1] => default_category
			[2] => default_email_category
			[3] => use_balanceTags
			[4] => default_link_category
			[5] => default_post_format
			[6] => mailserver_url
			[7] => mailserver_port
			[8] => mailserver_login
			[9] => mailserver_pass
			[10] => ping_sites
		)

*/

/**
 * Logs changes to wordpress options
 */
class SimpleOptionsLogger extends SimpleLogger
{

	public $slug = __CLASS__;

	/**
	 * Get array with information about this logger
	 * 
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(			
			"name" => "Options Logger",
			"description" => "Logs updates to WordPress settings",
			"capability" => "manage_options",
			"messages" => array(
				//'option_updated' => __('Updated option "{option}" on settings page "{option_page}"', "simple-history"),
				'option_updated' => __('Updated option "{option}"', "simple-history"),
				/*

				Updated option "default_comment_status" on settings page "discussion"
				Edited option "default_comment_status" on settings page "discussion"
				Modified option "default_comment_status" on settings page "discussion"

				Edited settings page "discussion" and the "default_comment_status" options
	
				*/
			),
			"labels" => array(
				"search" => array(
					"label" => _x("Options", "Options logger: search", "simple-history"),
					"options" => array(
						_x("Changed options", "Options logger: search", "simple-history") => array(
							"option_updated"
						),						
					)
				) // end search array
			) // end labels
		);
		
		return $arr_info;

	}

	function loaded() {

		add_action( 'updated_option', array($this, "on_updated_option"), 10, 3 );
 
	}

	function on_updated_option($option, $old_value, $new_value) {

		if (empty( $_SERVER["REQUEST_URI"] )) {
			return;
		}

		$arr_option_pages = array(
			0 => "options.php",
			1 => "options-permalink.php"
		);

		// We only want to log options being added via pages in $arr_option_pages
		if ( ! in_array( basename( $_SERVER["REQUEST_URI"] ), $arr_option_pages ) || basename( dirname( $_SERVER["REQUEST_URI"] ) ) !== "wp-admin" ) {
			return;
		}

		// Also only if "option_page" is set to one of these "built in" ones
		// We don't wanna start loging things from other plugins, like EDD
		$option_page = isset( $_REQUEST["option_page"] ) ? $_REQUEST["option_page"] : ""; // general | discussion | ...
		
		$arr_valid_option_pages = array(
			'general',
			'discussion',
			'media',
			'reading',
			'writing',
		);

		if ( $option_page && ! in_array($option_page, $arr_valid_option_pages) ) {
			return;
		}

		$this->debugMessage( 'option_updated', array(
			'option' => $option,
			'old_value' => $old_value,
			'new_value' => $new_value,
			'REQUEST_URI' => $_SERVER['REQUEST_URI'],
			'referer' => wp_get_referer(),
			'option_page' => $option_page,
			'$_REQUEST' => print_r($_REQUEST, true),
		) );


	}

	/**
	 * Get detailed output
	 */
	function getLogRowDetailsOutput($row) {
	
		$context = $row->context;
		$message_key = $context["_message_key"];
		$output = "";

		if ( "option_updated" == $message_key ) {
		
			//$message = 'Old value was {old_value} and new value is {new_value}';
			$output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

			// Output old and new values
			if ( $context["new_value"] || $context["old_value"] ) {
	
				$output .= sprintf(
					'
					<tr>
						<td>%1$s</td>
						<td>%2$s</td>
					</tr>
					',
					__("New value", "simple-history"),
					esc_html( mb_strimwidth( $context["new_value"], 0, 250, "..." ) )
				);

				$output .= sprintf(
					'
					<tr>
						<td>%1$s</td>
						<td>%2$s</td>
					</tr>
					',
					__("Old value", "simple-history"),
					esc_html( mb_strimwidth( $context["old_value"], 0, 250, "..." ) )
				);
			}

			// If key option_page this was saved from regular settings pages
			if ( ! empty( $context["option_page"] ) ) {

				$output .= sprintf(
					'
					<tr>
						<td>%1$s</td>
						<td><a href="%3$s">%2$s</a></td>
					</tr>
					',
					__("Settings page", "simple-history"),
					esc_html( $context["option_page"] ),
					admin_url("options-{$context["option_page"]}.php")
				);

			}

			// If option = permalink_structure then we did it from permalink page
			if ( ! empty( $context["option"] ) && ( "permalink_structure" == $context["option"] || "tag_base" == $context["option"] || "category_base" == $context["option"] ) ) {

				$output .= sprintf(
					'
					<tr>
						<td>%1$s</td>
						<td><a href="%3$s">%2$s</a></td>
					</tr>
					',
					__("Settings page", "simple-history"),
					"permalink",
					admin_url("options-permalink.php")
				);

			}
			
			$output .= "</table>";

		}

		return $output;

	}
}
