<?php

/*
Dropin Name: Global RSS Feed
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

/**
 * Simple History RSS Feed drop-in
 */
class SimpleHistoryRSSDropin {

	function __construct() {
		
		add_action( 'init', array($this, 'check_for_rss_feed_request') );

		// Add settings with prio 11 so it' added after the main Simple History settings
		add_action( 'admin_menu', array($this, 'add_settings'), 11 );

	}

	/**
	 * Add settings for the RSS feed 
	 * + also regenerates the secret if requested
	 */
	public function add_settings() {

		/**
		 * Start new section for RSS feed
		 */
		$settings_section_rss_id = "simple_history_settings_section_rss";

		add_settings_section(
			$settings_section_rss_id, 
			_x("RSS feed", "rss settings headline", "simple-history"), // No title __("General", "simple-history"), 
			array($this, "settings_section_output"), 
			SimpleHistory::SETTINGS_MENU_SLUG // same slug as for options menu page
		);

		// RSS address
		add_settings_field(
			"simple_history_rss_feed", 
			__("Address", "simple-history"),
			array($this, "settings_field_rss"),
			SimpleHistory::SETTINGS_MENU_SLUG,
			$settings_section_rss_id
		);

		// Regnerate address
		add_settings_field(
			"simple_history_rss_feed_regenerate_secret", 
			__("Regenerate", "simple-history"),
			array($this, "settings_field_rss_regenerate"),
			SimpleHistory::SETTINGS_MENU_SLUG,
			$settings_section_rss_id
		);

		// Create new RSS secret
		$create_new_secret = false;
		$create_secret_nonce_name = "simple_history_rss_secret_regenerate_nonce";
		
	    if ( isset( $_GET[$create_secret_nonce_name] ) && wp_verify_nonce( $_GET[$create_secret_nonce_name], 'simple_history_rss_update_secret')) {

			$create_new_secret = true;
			$this->update_rss_secret();

			// Add updated-message and store in transient and then redirect
			// This is the way options.php does it.
			$msg = __("Created new secret RSS address", 'simple-history');
			add_settings_error( "simple_history_rss_feed_regenerate_secret", "simple_history_rss_feed_regenerate_secret", $msg, "updated" );
			set_transient('settings_errors', get_settings_errors(), 30);

			$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
			wp_redirect( $goback );
			exit;

		}

	} // settings

	/**
	 * Check if current request is a request for the RSS feed
	 */
	function check_for_rss_feed_request() {
		
		// check for RSS
		// don't know if this is the right way to do this, but it seems to work!
		if ( isset($_GET["simple_history_get_rss"]) ) {

			$this->output_rss();
			exit;

		}
		
	}

	/**
	 * Output RSS
	 */
	function output_rss() {

			$rss_secret_option = get_option("simple_history_rss_secret");
			$rss_secret_get = isset( $_GET["rss_secret"] ) ? $_GET["rss_secret"] : "";

			if ( empty($rss_secret_option) || empty($rss_secret_get) ) {
				die();
			}

			$rss_show = true;
			$rss_show = apply_filters("simple_history/rss_feed_show", $rss_show);
			if( ! $rss_show ) {
				wp_die( 'Nothing here.' );
			}

			header ("Content-Type:text/xml");
			echo '<?xml version="1.0" encoding="UTF-8"?>';
			$self_link = $this->get_rss_address();
	
			if ($rss_secret_option === $rss_secret_get) {
				?>
				<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
					<channel>
						<title><?php printf(__("History for %s", 'simple-history'), get_bloginfo("name")) ?></title>
						<description><?php printf(__("WordPress History for %s", 'simple-history'), get_bloginfo("name")) ?></description>
						<link><?php echo get_bloginfo("url") ?></link>
						<atom:link href="<?php echo $self_link; ?>" rel="self" type="application/atom+xml" />
						<?php

						// Add filters here
						/*
								"page"        => 0,
								"items"       => $simple_history->get_pager_size(),
								"filter_type" => "",
								"filter_user" => "",
								"search"      => "",
								"num_added"   => 0
						*/
						$args = array(
							"items" => "10"
						);

						$args = apply_filters("simple_history/rss_feed_args", $args);

						$arr_items = simple_history_get_items_array($args);

						foreach ($arr_items as $one_item) {
							$object_type = ucwords($one_item->object_type);
							$object_name = esc_html($one_item->object_name);
							$user = get_user_by("id", $one_item->user_id);
							$user_nicename = esc_html(@$user->user_nicename);
							$user_email = esc_html(@$user->user_email);
							$description = "";
							if ($user_nicename) {
								$description .= sprintf(__("By %s", 'simple-history'), $user_nicename);
								$description .= "<br />";
							}
							if ($one_item->occasions) {
								$description .= sprintf(__("%d occasions", 'simple-history'), sizeof($one_item->occasions));
								$description .= "<br />";
							}
							$description = apply_filters("simple_history_rss_item_description", $description, $one_item);
	
							$item_title = esc_html($object_type) . " \"" . esc_html($object_name) . "\" {$one_item->action}";
							$item_title = html_entity_decode($item_title, ENT_COMPAT, "UTF-8");
							$item_title = apply_filters("simple_history_rss_item_title", $item_title, $one_item);

							$item_guid = home_url() . "?SimpleHistoryGuid=" . $one_item->id;

							?>
							  <item>
								 <title><![CDATA[<?php echo $item_title; ?>]]></title>
								 <description><![CDATA[<?php echo $description ?>]]></description>
								 <author><?php echo $user_email . ' (' . $user_nicename . ')' ?></author>
								 <pubDate><?php echo date("D, d M Y H:i:s", strtotime($one_item->date)) ?> GMT</pubDate>
								 <guid isPermaLink="false"><?php echo $item_guid ?></guid>
								 <link><?php echo $item_guid ?></link>
							  </item>
							<?php
						}
						?>
					</channel>
				</rss>
				<?php
			} else {
				// not ok rss secret
				?>
				<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
					<channel>
						<title><?php printf(__("History for %s", 'simple-history'), get_bloginfo("name")) ?></title>
						<description><?php printf(__("WordPress History for %s", 'simple-history'), get_bloginfo("name")) ?></description>
						<link><?php echo home_url() ?></link>
						<item>
							<title><?php _e("Wrong RSS secret", 'simple-history')?></title>
							<description><?php _e("Your RSS secret for Simple History RSS feed is wrong. Please see WordPress settings for current link to the RSS feed.", 'simple-history')?></description>
							<pubDate><?php echo date("D, d M Y H:i:s", time()) ?> GMT</pubDate>
							<guid><?php echo home_url() . "?SimpleHistoryGuid=wrong-secret" ?></guid>
						</item>
					</channel>
				</rss>
				<?php
	
			}

	} // rss


	/**
	 * Create a new RSS secret
	 *
	 * @return string new secret
	 */
	function update_rss_secret() {
		
		$rss_secret = "";
		
		for ($i=0; $i<20; $i++) {
			$rss_secret .= chr(rand(97,122));
		}

		update_option("simple_history_rss_secret", $rss_secret);

		return $rss_secret;
	}

	/**
	 * Output for settings field that show current RSS address
	 */
	function settings_field_rss() {

		$rss_address = $this->get_rss_address();

		echo "<p><code><a href='$rss_address'>$rss_address</a></code></p>";

	}

	/**
	 * Output for settings field that regenerates the RSS adress/secret
	 */
	function settings_field_rss_regenerate() {
			
		$update_link = add_query_arg("", "");
		$update_link = wp_nonce_url( $update_link, "simple_history_rss_update_secret", "simple_history_rss_secret_regenerate_nonce" );

		echo "<p>";
		_e("You can generate a new address for the RSS feed. This is useful if you think that the address has fallen into the wrong hands.", 'simple-history');
		echo "</p>";
		echo "<p>";
		printf( __('<a class="button" href="%s">Generate new address</a>'), $update_link );
		echo "</p>";

	}


	/**
	 * Get the URL to the RSS feed
	 * @return string URL
	 */
	function get_rss_address() {
		
		$rss_secret = get_option("simple_history_rss_secret");
		$rss_address = add_query_arg(array("simple_history_get_rss" => "1", "rss_secret" => $rss_secret), get_bloginfo("url") . "/");
		$rss_address = htmlspecialchars($rss_address, ENT_COMPAT, "UTF-8");

		return $rss_address;

	}

	/**
	 * Content for section intro. Leave it be, even if empty.
	 * Called from add_sections_setting.
	 */
	function settings_section_output() {

		echo "<p>";
		_e("Simple History has a RSS feed which you can subscribe to and receive log updates. Make sure you only share the feed with people you trust, since it can contain sensitive or confidential information.", 'simple-history');
		echo "</p>";

	}

} // end rss class
