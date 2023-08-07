<?php

namespace Simple_History\Dropins;

use Simple_History\Helpers;
use Simple_History\Event_Details_Container;
use Simple_History\Event_Details_Group;
use Simple_History\Event_Details_Item;
use Simple_History\Event_Details_Group_Inline_Formatter;
use Simple_History\Event_Details_Group_Table_Formatter;

include __DIR__ . '/../inc/class-event-details-container.php';
include __DIR__ . '/../inc/class-event-details-group-inline-formatter.php';

/**
 * Development Dropin
 * Used during development to test things.
 */
class Development_Dropin extends Dropin {
	public function loaded() {
		if ( false === Helpers::dev_mode_is_enabled() ) {
			return;
		}

		add_action( 'init', array( $this, 'add_settings_tab' ) );
	}

	public function add_settings_tab() {
		$this->simple_history->register_settings_tab(
			array(
				'slug' => 'dropin_development_dropin_tab',
				'name' => __( 'Development', 'simple-history' ),
				'function' => array( $this, 'tab_output' ),
			)
		);
	}

	private function get_example_context() {
		return [
			// Common keys.
			'id' => '24',
			'logger' => 'SimpleHistoryLogger',
			'level' => 'info',
			'date' => '2023-08-03 09:49:51',
			'message' => 'Modified settings',
			'initiator' => 'wp_user',
			'context_message_key' => 'modified_settings',
			'_message_key' => 'modified_settings',
			'_user_id' => '1',
			'_user_login' => 'par',
			'_user_email' => 'par.thernstrom@gmail.com',
			'_server_remote_addr' => '127.0.0.x',

			// New key format, with "_new" and "_prev" added last.
			'show_on_dashboard_prev' => '0',
			'show_on_dashboard_new' => '1',
			'show_as_page_prev' => '0',
			'show_as_page_new' => '1',
			'enable_rss_feed_prev' => '0',
			'enable_rss_feed_new' => '1',
			'pager_size_dashboard_new' => '25',
			'pager_size_dashboard_prev' => '50',
			'pager_size_new' => '25',
			'pager_size_prev' => '50',

			// Format used by plugin and theme updates
			'theme_name' => 'Neve',
			'theme_new_version' => '3.6.6',
			'theme_current_version' => '3.6.4',
			'plugin_slug' => 'wp-plugin-dependencies',
			'plugin_name' => 'Plugin Dependencies',
			'plugin_title' => '<a href="https://wordpress.org/plugins/wp-plugin-dependencies">Plugin Dependencies</a>',
			'plugin_description' => 'Parses ‘Requires Plugins’ header, add plugin install dependencies tab, and information about dependencies. <cite>By Andy Fragen, Colin Stewart, Paul Biron.</cite>',
			'plugin_author' => 'Andy Fragen, Colin Stewart, Paul Biron',
			'plugin_version' => '1.14.3', // New version. Badly named.
			'plugin_new_version' => '1.14.2',
			'plugin_prev_version' => '1.14.0',
			// If plugin update is found then "plugin_current_version" contains the currently installed version.
			'plugin_current_version' => '1.14.0',
			'plugin_url' => 'https://wordpress.org/plugins/wp-plugin-dependencies',

			// Format used by user profile logger.
			'edited_user_email' => 'par.thernstrom@gmail.com',
			'edited_user_login' => 'par',
			'user_new_user_url' => 'https://texttv.nu/',
			'user_prev_user_url' => 'http://wordpress-stable.test/wordpress',
			'user_new_nickname' => 'parrabarry',
			'user_prev_nickname' => 'par',
			'user_new_description' => 'Det e jag som e jag.',
			'user_prev_description' => '',

			// Format used by post logger.
			'post_type' => 'page',
			'post_title' => 'About Us',
			'post_prev_post_title' => 'About us',
			'post_new_post_title' => 'About Us',
			'post_prev_post_content' => "=> '<!-- wp:paragraph --><p>Hi. Yo.</p><!-- /wp:paragraph -->'",
			'post_new_post_content' => "=> '<!-- wp:paragraph --><p>Hello, hey!</p><!-- /wp:paragraph -->'",
			'post_prev_thumb_id' => '110',
			'post_prev_thumb_title' => 'product-cat-2',
			'post_new_thumb_id' => '108',
			'post_new_thumb_title' => 'product-cat-4',

			// Keys with no prev or new value, just value, the current/set value.
			'generated_user_nickname' => 'eskaloo',
			'post_thumbnail_id' => '123',
			'user_content' => 'Lorem ipsum some content that user has entered lorem ipsum dolor sit amet',
			'image_size' => '34 Kb',
			'image_format' => 'PNG',
			'image_dimensions' => '420 × 420',
		];
	}

	private function get_example_event_details_container() {
		// Array with details, to format in the same way.
		$event_details_container = [
			new Event_Details_Item(
				[ 'show_on_dashboard' ],
				__( 'Show on dashboard', 'simple-history' ),
				// TODO: How to convert 0 to no and 1 to yes (or on/checked etc.)
				// custom item-value-formatter?
			),
			new Event_Details_Item(
				[ 'show_as_page' ],
				__( 'Show as a page', 'simple-history' ),
			),
			new Event_Details_Item(
				[ 'pager_size' ],
				__( 'Items on page', 'simple-history' ),
			),
			new Event_Details_Item(
				[ 'pager_size_dashboard' ],
				__( 'Items on dashboard', 'simple-history' ),
			),
			new Event_Details_Item(
				[ 'enable_rss_feed' ],
				__( 'RSS feed enabled', 'simple-history' ),
			),
			new Event_Details_Item(
				[ 'user_new_nickname', 'user_prev_nickname' ],
				__( 'Nickname', 'simple-history' ),
			),
			new Event_Details_Item(
				'plugin_new_version',
				__( 'Available version', 'simple-history' ),
			),
			new Event_Details_Item(
				'plugin_current_version',
				__( 'Installed version', 'simple-history' ),
			),
		];

		// Group with details = items that will be formatted the same way.
		$event_details_group_inline = new Event_Details_Group();
		$event_details_group_inline->set_formatter( new Event_Details_Group_Inline_Formatter() );
		$event_details_group_inline->add_items( $event_details_container );

		// Another group, with same items, but different format.
		$event_details_group_table = new Event_Details_Group();
		$event_details_group_table->set_formatter( new Event_Details_Group_Table_Formatter() );
		$event_details_group_table->add_items( $event_details_container );

		// Another group. Empty second arg to each item to not show title.
		// Value of each thing must be self-explanatory.
		$event_details_group_two = new Event_Details_Group();
		$event_details_group_two->set_formatter( new Event_Details_Group_Inline_Formatter() );
		$event_details_group_two->add_items(
			[
				new Event_Details_Item( 'image_size' ),
				new Event_Details_Item( 'image_format' ),
				new Event_Details_Item( 'image_dimensions' ),
			]
		);

		// Grouop with no added formatter.
		// Uses table layout.
		$event_details_group_three = new Event_Details_Group();
		$event_details_group_three->add_items(
			[
				new Event_Details_Item( 'image_size', 'Size' ),
				new Event_Details_Item( 'image_format', 'Format' ),
				new Event_Details_Item( 'image_dimensions', 'Dimensions' ),
			]
		);

		// Items can pass values manually upon creation,
		// so values will no be fetched from context.
		$event_details_group_four = new Event_Details_Group();
		$item1 = new Event_Details_Item( 'image_size', 'Size with custom value' );
		$item1->set_new_value( '123 Kb' );
		$item2 = new Event_Details_Item( 'image_format', 'Format with custom values' );
		$item2->set_values( 'WebP', 'PNG' );
		$event_details_group_four->add_items( [ $item1, $item2 ] );

		// Create container for the group and add the groups.
		$event_details_container = new Event_Details_Container();
		$event_details_container->add_group( $event_details_group_inline );
		$event_details_container->add_group( $event_details_group_table, );
		$event_details_container->add_group( $event_details_group_two, );
		$event_details_container->add_group( $event_details_group_three, );
		$event_details_container->add_group( $event_details_group_four, );

		// No key, only message shown.
		$event_details_container->add_item(
			new Event_Details_Item(
				null,
				__( 'Hey I have no key just some text.', 'simple-history' ),
			),
		);

		// Item with custom output.
		// Output is not escaped, so user must escape accordingly.
		$html_item = new Event_Details_Item(
			null,
			__( 'And I have <em>custom <strong>HTML</strong></em>.', 'simple-history' ),
		);
		// $html_item->set-formatter('HTML_Formatter');
		// $html_item->set_value_formatter('RAW_FORMATTER');
		// Pass new + old value to formatter too, for special cases.
		$event_details_container->add_item( $html_item );

		// Set the context. Must be done last atm.
		$event_details_container->set_context( $this->get_example_context() );

		return $event_details_container;
	}

	public function tab_output() {
		?>
		<div class="wrap">
			<h1>Development</h1>
			
			<p>Context config test.</p>

			<?php

			$event_details_container = $this->get_example_event_details_container();

			echo '<hr /><p>The event details container contains ' . count( $event_details_container->groups ) . ' groups and this is the HTML output:</p>';
			echo $event_details_container->get_output( 'html' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

			// echo '<hr /><p>The event details container contains ' . count( $event_details_container->groups ) . ' groups and this is the JSON output:</p>';
			// echo $event_details_container->get_output( 'json' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
		<?php
	}
}
