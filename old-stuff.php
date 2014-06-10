<?php



/*
old actions and filters, to move into own loggers
*/
function old_logger_inits() {

		/** called on init: */

		// user profile page modifications
		add_action("delete_user", "simple_history_delete_user");
		add_action("user_register", "simple_history_user_register");
		add_action("profile_update", "simple_history_profile_update");
	
		// options
		#add_action("updated_option", "simple_history_updated_option", 10, 3);
		#add_action("updated_option", "simple_history_updated_option2", 10, 2);
		#add_action("updated_option", "simple_history_updated_option3", 10, 1);
		#add_action("update_option", "simple_history_update_option", 10, 3);
	
		// plugin
		add_action("activated_plugin", "simple_history_activated_plugin");
		add_action("deactivated_plugin", "simple_history_deactivated_plugin");



		/** called on admin_init */
										 
		
		// comments
		add_action("edit_comment", "simple_history_edit_comment");
		add_action("delete_comment", "simple_history_delete_comment");
		add_action("wp_set_comment_status", "simple_history_set_comment_status", 10, 2);

		// settings (all built in except permalinks)
		$arr_option_pages = array("general", "writing", "reading", "discussion", "media", "privacy");
		foreach ($arr_option_pages as $one_option_page_name) {
			$new_func = create_function('$capability', '
					return simple_history_add_update_option_page($capability, "'.$one_option_page_name.'");
				');
			add_filter("option_page_capability_{$one_option_page_name}", $new_func);
		}

		// settings page for permalinks
		add_action('check_admin_referer', "simple_history_add_update_option_page_permalinks", 10, 2);

		// core update = wordpress updates
		add_action( '_core_updated_successfully', array($this, "action_core_updated") );




}










/**
 * Old loggers/hooks are here
 * All things here are to be moved into own SimpleLogger classes
 */

function simple_history_edit_comment($comment_id) {
	
	$comment_data = get_commentdata($comment_id, 0, true);
	$comment_post_ID = $comment_data["comment_post_ID"];
	$post = get_post($comment_post_ID);
	$post_title = get_the_title($comment_post_ID);
	$excerpt = get_comment_excerpt($comment_id);
	$author = get_comment_author($comment_id);

	$str = sprintf( "$excerpt [" . __('From %1$s on %2$s') . "]", $author, $post_title );
	$str = urlencode($str);

	simple_history_add("action=edited&object_type=comment&object_name=$str&object_id=$comment_id");

}

function simple_history_delete_comment($comment_id) {
	
	$comment_data = get_commentdata($comment_id, 0, true);
	$comment_post_ID = $comment_data["comment_post_ID"];
	$post = get_post($comment_post_ID);
	$post_title = get_the_title($comment_post_ID);
	$excerpt = get_comment_excerpt($comment_id);
	$author = get_comment_author($comment_id);

	$str = sprintf( "$excerpt [" . __('From %1$s on %2$s') . "]", $author, $post_title );
	$str = urlencode($str);

	simple_history_add("action=deleted&object_type=comment&object_name=$str&object_id=$comment_id");

}

function simple_history_set_comment_status($comment_id, $new_status) {
	#echo "<br>new status: $new_status<br>"; // 0
	// $new_status hold (unapproved), approve, spam, trash
	$comment_data = get_commentdata($comment_id, 0, true);
	$comment_post_ID = $comment_data["comment_post_ID"];
	$post = get_post($comment_post_ID);
	$post_title = get_the_title($comment_post_ID);
	$excerpt = get_comment_excerpt($comment_id);
	$author = get_comment_author($comment_id);

	$action = "";
	if ("approve" == $new_status) {
		$action = 'approved';
	} elseif ("hold" == $new_status) {
		$action = 'unapproved';
	} elseif ("spam" == $new_status) {
		$action = 'marked as spam';
	} elseif ("trash" == $new_status) {
		$action = 'trashed';
	} elseif ("0" == $new_status) {
		$action = 'untrashed';
	}

	$action = urlencode($action);

	$str = sprintf( "$excerpt [" . __('From %1$s on %2$s') . "]", $author, $post_title );
	$str = urlencode($str);

	simple_history_add("action=$action&object_type=comment&object_name=$str&object_id=$comment_id");

}




// user is updated
function simple_history_profile_update($user_id) {
	$user = get_user_by("id", $user_id);
	$user_nicename = urlencode($user->user_nicename);
	simple_history_add("action=updated&object_type=user&object_id=$user_id&object_name=$user_nicename");
}

// user is created
function simple_history_user_register($user_id) {
	$user = get_user_by("id", $user_id);
	$user_nicename = urlencode($user->user_nicename);
	simple_history_add("action=created&object_type=user&object_id=$user_id&object_name=$user_nicename");
}

// user is deleted
function simple_history_delete_user($user_id) {
	$user = get_user_by("id", $user_id);
	$user_nicename = urlencode($user->user_nicename);
	simple_history_add("action=deleted&object_type=user&object_id=$user_id&object_name=$user_nicename");
}



// called when saving an options page
function simple_history_add_update_option_page($capability = NULL, $option_page = NULL) {

	$arr_options_names = array(
		"general" 		=> __("General Settings"),
		"writing"		=> __("Writing Settings"),
		"reading"		=> __("Reading Settings"),
		"discussion"	=> __("Discussion Settings"),
		"media"			=> __("Media Settings"),
		"privacy"		=> __("Privacy Settings")
	);
	
	$option_page_name = "";
	if (isset($arr_options_names[$option_page])) {
		$option_page_name = $arr_options_names[$option_page];
		simple_history_add("action=modified&object_type=settings page&object_id=$option_page&object_name=$option_page_name");
	}

	return $capability;
}

// called when updating permalinks
function simple_history_add_update_option_page_permalinks($action, $result) {
	
	if ("update-permalink" == $action) {
		$option_page_name = __("Permalink Settings");
		$option_page = "permalink";
		simple_history_add("action=modified&object_type=settings page&object_id=$option_page&object_name=$option_page_name");
	}

}


function simple_history_update_option($option, $oldval, $newval) {

	if ($option == "active_plugins") {
	
		$debug = "\n";
		$debug .= "\nsimple_history_update_option()";
		$debug .= "\noption: $option";
		$debug .= "\noldval: " . print_r($oldval, true);
		$debug .= "\nnewval: " . print_r($newval, true);
	
		//  Returns an array containing all the entries from array1 that are not present in any of the other arrays. 
		// alltså:
		//	om newval är array1 och innehåller en rad så är den tillagd
		// 	om oldval är array1 och innhåller en rad så är den bortagen
		$diff_added = array_diff((array) $newval, (array) $oldval);
		$diff_removed = array_diff((array) $oldval, (array) $newval);
		$debug .= "\ndiff_added: " . print_r($diff_added, true);
		$debug .= "\ndiff_removed: " . print_r($diff_removed, true);
	}
}


/**
 * Plugin is activated
 * plugin_name is like admin-menu-tree-page-view/index.php
 */
function simple_history_activated_plugin($plugin_name) {

	// Fetch info about the plugin
	$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name );
	
	if ( is_array( $plugin_data ) && ! empty( $plugin_data["Name"] ) ) {
		$plugin_name = urlencode( $plugin_data["Name"] );
	} else {
		$plugin_name = urlencode($plugin_name);
	}

	simple_history_add("action=activated&object_type=plugin&object_name=$plugin_name");
}

/**
 * Plugin is deactivated
 * plugin_name is like admin-menu-tree-page-view/index.php
 */
function simple_history_deactivated_plugin($plugin_name) {

	// Fetch info about the plugin
	$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_name );
	
	if ( is_array( $plugin_data ) && ! empty( $plugin_data["Name"] ) ) {
		$plugin_name = urlencode( $plugin_data["Name"] );
	} else {
		$plugin_name = urlencode($plugin_name);
	}
	
	simple_history_add("action=deactivated&object_type=plugin&object_name=$plugin_name");

}

// WordPress Core updated
function action_core_updated($wp_version) {
	simple_history_add("action=updated&object_type=wordpress_core&object_id=wordpress_core&object_name=".sprintf(__('WordPress %1$s', 'simple-history'), $wp_version));
}

