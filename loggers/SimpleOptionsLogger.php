<?php

defined( 'ABSPATH' ) or die();

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

	function on_updated_option( $option, $old_value, $new_value ) {

		if ( empty( $_SERVER["REQUEST_URI"] ) ) {
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

		$is_valid_options_page = $option_page && in_array( $option_page, $arr_valid_option_pages );

		// Permalink settings page does not post any "option_page", so use http referer instead
		if ( strpos( $_SERVER["REQUEST_URI"], "options-permalink.php" ) !== false ) {
			$is_valid_options_page = true;
			$options_page = "permalink";
		}

		if ( ! $is_valid_options_page ) {
			return;
		}

		// Check if option name is ok
		// For example if you change front page displays setting the "rewrite_rules" options gets updated too
		$arr_invalid_option_names = array(
			"rewrite_rules"
		);

		if ( in_array( $option, $arr_invalid_option_names ) ) {
			return;
		}

		$context = array(
			'option' => $option,
			'old_value' => $old_value,
			'new_value' => $new_value,
			'option_page' => $option_page,
			#'referer' => wp_get_referer(),
			#'REQUEST_URI' => $_SERVER['REQUEST_URI'],
			#'$_REQUEST' => print_r($_REQUEST, true),
		);

		// Store a bit more about some options
		// Like "page_on_front" we also store post title
		// Check for a method for current option in this class and calls it automagically
		$methodname = "add_context_for_option_{$option}";
		if ( method_exists( $this, $methodname ) ) {
			$context = $this->$methodname( $context, $old_value, $new_value, $option, $option_page );
		}

		$this->infoMessage( 'option_updated', $context );

	}

	/**
	 * Give some options better plain text output
	 *
	 * Not doing anything at the moment, because it was really difficaly to give them meaningful text values
	 */
	public function getLogRowPlainTextOutput( $row ) {

		$message = $row->message;
		$context = $row->context;
		$message_key = $context["_message_key"];

		$return_message = "";

		// Only link to attachment if it is still available
		if ( "option_updated" == $message_key ) {

			/*
			$option = isset( $context["option"] ) ? $context["option"] : null;
			$option_page = isset( $context["option_page"] ) ? $context["option_page"] : null;
			$new_value = isset( $context["new_value"] ) ? $context["new_value"] : null;
			$old_value = isset( $context["old_value"] ) ? $context["old_value"] : null;

			# $return_message = "";
			$arr_options_to_translate = array(
				"$option_page/blog_public" => array(
					"text" => "Updated setting Search Engine Visibility"
				),
				"$option_page/rss_use_excerpt" => array(
					"text" => "Updated setting For each article in a feed, show"
				),
				"$option_page/posts_per_rss" => array(
					"text" => "Updated setting for Syndication feeds show the most recent"
				),
				"$option_page/posts_per_page" => array(
					"text" => "Updated setting for Blog pages show at most"
				)
			);

			if ( isset( $arr_options_to_translate[ "{$option_page}/{$option}" ] ) ) {
				$return_message = $arr_options_to_translate[ "{$option_page}/{$option}" ]["text"];
			}
			*/

		}

		if ( empty( $return_message ) ) {

			// No specific text to output, fallback to default
			$return_message = parent::getLogRowPlainTextOutput( $row );

		}

		return $return_message;

	}

	/**
	 * Get detailed output
	 */
	function getLogRowDetailsOutput($row) {

		$context = $row->context;
		$message_key = $context["_message_key"];
		$output = "";

		$option = isset( $context["option"] ) ? $context["option"] : null;
		$option_page = isset( $context["option_page"] ) ? $context["option_page"] : null;
		$new_value = isset( $context["new_value"] ) ? $context["new_value"] : null;
		$old_value = isset( $context["old_value"] ) ? $context["old_value"] : null;

		$tmpl_row = '
			<tr>
				<td>%1$s</td>
				<td>%2$s</td>
			</tr>
		';

		if ( "option_updated" == $message_key ) {

			//$message = 'Old value was {old_value} and new value is {new_value}';
			$output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

			// Output old and new values
			if ( $context["new_value"] || $context["old_value"] ) {

				$option_custom_output = "";
				$methodname = "get_details_output_for_option_{$option}";

				if ( method_exists( $this, $methodname ) ) {
					$option_custom_output = $this->$methodname( $context, $old_value, $new_value, $option, $option_page, $tmpl_row );
				}

				if ( empty( $option_custom_output ) ) {

					// all other options or fallback if custom output did not find all it's stuff
					$more = __( '&hellip;' );
					$trim_length = 250;

					$trimmed_new_value = substr( $new_value, 0, $trim_length );
					$trimmed_old_value = substr( $old_value, 0, $trim_length );

					if ( strlen( $new_value ) > $trim_length ) {
						$trimmed_new_value .= $more;
					}

					if ( strlen( $old_value ) > $trim_length ) {
						$trimmed_old_value .= $more;
					}

					$output .= sprintf(
						$tmpl_row,
						__("New value", "simple-history"),
						esc_html( $trimmed_new_value )
					);

					$output .= sprintf(
						$tmpl_row,
						__("Old value", "simple-history"),
						esc_html( $trimmed_old_value )
					);


				} else {

					$output .= $option_custom_output;

				} // if option output


			} // if new or old val


			// If key option_page this was saved from regular settings pages
			if ( ! empty( $option_page ) ) {

				$output .= sprintf(
					'
					<tr>
						<td>%1$s</td>
						<td><a href="%3$s">%2$s</a></td>
					</tr>
					',
					__("Settings page", "simple-history"),
					esc_html( $context["option_page"] ),
					admin_url("options-{$option_page}.php")
				);

			}

			// If option = permalink_structure then we did it from permalink page
			if ( ! empty( $option ) && ( "permalink_structure" == $option || "tag_base" == $option || "category_base" == $option ) ) {

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


	/**
	 * Page on front = "Front page displays" -> Your latest posts / A static page
	 * value 0 = Your latest post
	 * value int n = A static page
	 */
	function add_context_for_option_page_on_front( $context, $old_value, $new_value, $option, $option_page ) {

		if ( ! empty( $old_value ) && is_numeric( $old_value ) ) {

			$old_post = get_post( $old_value );

			if ( $old_post ) {
				$context["old_post_title"] = $old_post->post_title;
			}

		}

		if ( ! empty( $new_value ) && is_numeric( $new_value ) ) {

			$new_post = get_post( $new_value );

			if ( $new_post ) {
				$context["new_post_title"] = $new_post->post_title;
			}

		}

		return $context;

	}

	function add_context_for_option_page_for_posts( $context, $old_value, $new_value, $option, $option_page ) {

		// Get same info as for page_on_front
		$context = call_user_func_array( array( $this, "add_context_for_option_page_on_front"), func_get_args() );

		return $context;

	}

	function get_details_output_for_option_page_for_posts( $context, $old_value, $new_value, $option, $option_page ) {

		$output = call_user_func_array( array( $this, "get_details_output_for_option_page_on_front"), func_get_args() );

		return $output;

	}

	/**
	 * Add detailed putput for page_on_front
	 *
	 * @return string output
	 */
	function get_details_output_for_option_page_on_front( $context, $old_value, $new_value, $option, $option_page, $tmpl_row ) {

		$output = "";

		if ( $new_value && ! empty( $context["new_post_title"] ) ) {

			$post_title_with_link = "";

			if ( get_post_status( $new_value ) ) {
				$post_title_with_link = sprintf('<a href="%1$s">%2$s</a>', get_edit_post_link( $new_value ), esc_html( $context["new_post_title"] ) );
			} else {
				$post_title_with_link = esc_html( $context["new_post_title"] );
			}

			$output .= sprintf(
				$tmpl_row,
				__("New value", "simple-history"),
				sprintf( __('Page %1$s', "simple-history" ), $post_title_with_link)
			);

		}
		if ( intval( $new_value ) == 0  ) {

			$output .= sprintf(
				$tmpl_row,
				__("New value", "simple-history"),
				__("Your latests posts", "simple-history")
			);

		}

		if ( $old_value && ! empty( $context["old_post_title"] ) ) {
			$post_title_with_link = "";

			if ( get_post_status( $old_value ) ) {
				$post_title_with_link = sprintf('<a href="%1$s">%2$s</a>', get_edit_post_link( $old_value ), esc_html( $context["old_post_title"] ) );
			} else {
				$post_title_with_link = esc_html( $context["old_post_title"] );
			}

			$output .= sprintf(
				$tmpl_row,
				__("Old value", "simple-history"),
				sprintf( __('Page %1$s', "simple-history" ), $post_title_with_link)
			);

		}

		if ( intval( $old_value ) == 0  ) {

			$output .= sprintf(
				$tmpl_row,
				__("Old value", "simple-history"),
				__("Your latests posts", "simple-history")
			);

		}

		return $output;

	} // custom output page_on_front


	/**
	 * "default_category" = Writing Settings » Default Post Category
	 */
	function add_context_for_option_default_category( $context, $old_value, $new_value, $option, $option_page ) {

		if ( ! empty( $old_value ) && is_numeric( $old_value ) ) {

			$old_category_name = get_the_category_by_ID( $old_value );

			if ( ! is_wp_error( $old_category_name) ) {

				$context["old_category_name"] = $old_category_name;

			}

		}

		if ( ! empty( $new_value ) && is_numeric( $new_value ) ) {

			$new_category_name = get_the_category_by_ID( $new_value );

			if ( ! is_wp_error( $new_category_name) ) {

				$context["new_category_name"] = $new_category_name;

			}

		}

		return $context;

	}

	function add_context_for_option_default_email_category( $context, $old_value, $new_value, $option, $option_page ) {

		$context = call_user_func_array( array( $this, "add_context_for_option_default_category"), func_get_args() );

		return $context;

	}


	/**
	 * Add detailed putput for default_category
	 *
	 * @return string output
	 */
	function get_details_output_for_option_default_category( $context, $old_value, $new_value, $option, $option_page, $tmpl_row ) {

		$old_category_name = isset( $context["old_category_name"] ) ? $context["old_category_name"] : null;
		$new_category_name = isset( $context["new_category_name"] ) ? $context["new_category_name"] : null;

		if ( $old_category_name ) {

			$output .= sprintf(
				$tmpl_row,
				__("Old value", "simple-history"),
				esc_html( $old_category_name )
			);

		}

		if ( $new_category_name ) {

			$output .= sprintf(
				$tmpl_row,
				__("New value", "simple-history"),
				esc_html( $new_category_name )
			);

		}

		return $output;

	}

	function get_details_output_for_option_default_email_category( $context, $old_value, $new_value, $option, $option_page, $tmpl_row ) {

		$output = call_user_func_array( array( $this, "get_details_output_for_option_default_category"), func_get_args() );

		return $output;

	}

}
