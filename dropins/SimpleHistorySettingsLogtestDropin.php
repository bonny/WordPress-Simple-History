<?php

defined( 'ABSPATH' ) || die();

class SimpleHistorySettingsLogtestDropin {

	// Simple History instance
	private $sh;

	public function __construct( $sh ) {

		// Since it's not quite done yet, it's for da devs only for now
		if ( ! defined( 'SIMPLE_HISTORY_DEV' ) || ! SIMPLE_HISTORY_DEV ) {
			return;
		}

		$this->sh = $sh;

		// How do we register this to the settings array?
		$sh->registerSettingsTab(
			array(
				'slug' => 'testLog',
				'name' => __( 'Test data (debug)', 'simple-history' ),
				'function' => array( $this, 'output' ),
			)
		);

		// add_action( 'admin_enqueue_scripts', array( $this, 'on_admin_enqueue_scripts') );
		add_action( 'admin_head', array( $this, 'on_admin_head' ) );
		add_action( 'wp_ajax_SimpleHistoryAddLogTest', array( $this, 'on_ajax_add_logtests' ) );
	}

	public function on_ajax_add_logtests() {

		$this->doLogTestThings();

		$arr = array(
			'message' => 'did it!',
		);

		wp_send_json_success( $arr );
	}

	public function on_admin_head() {

		?>
		<script>

			jQuery(function($) {

				var button = $(".js-SimpleHistorySettingsLogtestDropin-addStuff");
				var messageDone = $(".js-SimpleHistorySettingsLogtestDropin-addStuffDone");
				var messageWorking = $(".js-SimpleHistorySettingsLogtestDropin-addStuffWorking");

				button.on("click", function(e) {

					messageWorking.show();
					messageDone.hide();

					$.post(ajaxurl, {
						action: "SimpleHistoryAddLogTest"
					}).done(function(r) {

						messageWorking.hide();
						messageDone.show();

					});

				});

			});


		</script>
		<?php
	}

	public function output() {

		?>
		<h1>Test data</h1>

		<p>Add lots of test data to the log database.</p>

		<p>
			<button class="button js-SimpleHistorySettingsLogtestDropin-addStuff">Ok, add lots of stuff to the log!</button>
		</p>

		<div class="updated hidden js-SimpleHistorySettingsLogtestDropin-addStuffDone">
			<p>Done! Added lots of test rows</p>
		</div>

		<div class="updated hidden js-SimpleHistorySettingsLogtestDropin-addStuffWorking">
			<p>Adding...</p>
		</div>

		<?php
	}

	public function doLogTestThings() {

		// Add some data random back in time, to fill up the log to test much data
		for ( $j = 0; $j < 50; $j++ ) {
			// between yesterday and a month back in time
			for ( $i = 0; $i < rand( 1, 30 ); $i++ ) {
				$str_date = gmdate( 'Y-m-d H:i:s', strtotime( "now -{$i}days" ) );
				SimpleLogger()->info(
					'Entry with date in the past',
					array(
						'_date' => $str_date,
						'_occasionsID' => "past_date:{$str_date}",
					)
				);
			}
		}

		SimpleLogger()->info( 'This is a message sent to the log' );

		// Second log entry with same info will make these two become an occasionGroup,
		// collapsing their entries into one expandable log item
		SimpleLogger()->info( 'This is a message sent to the log' );

		// Log entries can be of different severity
		SimpleLogger()->info( "User admin edited page 'About our company'" );
		SimpleLogger()->warning( "User 'Jessie' deleted user 'Kim'" );
		SimpleLogger()->debug( 'Ok, cron job is running!' );

		// Log entries can have placeholders and context
		// This makes log entries translatable and filterable
		for ( $i = 0; $i < rand( 1, 50 ); $i++ ) {
			SimpleLogger()->notice(
				'User {username} edited page {pagename}',
				array(
					'username' => 'bonnyerden',
					'pagename' => 'My test page',
					'_initiator' => SimpleLoggerLogInitiators::WP_USER,
					'_user_id' => rand( 1, 20 ),
					'_user_login' => 'loginname' . rand( 1, 20 ),
					'_user_email' => 'user' . rand( 1, 20 ) . '@example.com',
				)
			);
		}
		// return;
		// Log entries can have custom occasionsID
		// This will group items together and a log entry will only be shown once
		// in the log overview
		for ( $i = 0; $i < rand( 1, 50 ); $i++ ) {
			SimpleLogger()->notice(
				'User {username} edited page {pagename}',
				array(
					'username' => 'admin',
					'pagename' => 'My test page',
					'_occasionsID' => 'username:1,postID:24884,action:edited',
				)
			);
		}

		SimpleLogger()->info(
			'WordPress updated itself from version {from_version} to {to_version}',
			array(
				'from_version' => '3.8',
				'to_version' => '3.8.1',
				'_initiator' => SimpleLoggerLogInitiators::WORDPRESS,
			)
		);

		SimpleLogger()->info(
			'Plugin {plugin_name} was updated from version {plugin_from_version} to version {plugin_to_version}',
			array(
				'plugin_name' => 'CMS Tree Page View',
				'plugin_from_version' => '4.0',
				'plugin_to_version' => '4.2',
				'_initiator' => SimpleLoggerLogInitiators::WORDPRESS,
			)
		);

		SimpleLogger()->info(
			'Updated plugin {plugin_name} from version {plugin_from_version} to version {plugin_to_version}',
			array(
				'plugin_name' => 'Ninja Forms',
				'plugin_from_version' => '1.1',
				'plugin_to_version' => '1.1.2',
				'_initiator' => SimpleLoggerLogInitiators::WP_USER,
			)
		);

		SimpleLogger()->warning(
			"An attempt to login as user 'administrator' failed to login because the wrong password was entered",
			array(
				'_initiator' => SimpleLoggerLogInitiators::WEB_USER,
			)
		);

		SimpleLogger()->info(
			'Updated plugin {plugin_name} from version {plugin_from_version} to version {plugin_to_version}',
			array(
				'plugin_name' => 'Simple Fields',
				'plugin_from_version' => '1.3.7',
				'plugin_to_version' => '1.3.8',
				'_initiator' => SimpleLoggerLogInitiators::WP_USER,
			)
		);

		SimpleLogger()->error(
			"A JavaScript error was detected on page 'About us'",
			array(
				'_initiator' => SimpleLoggerLogInitiators::WEB_USER,
			)
		);

		SimpleLogger()->debug(
			"WP Cron 'my_test_cron_job' finished in 0.012 seconds",
			array(
				'_initiator' => SimpleLoggerLogInitiators::WORDPRESS,
			)
		);

		for ( $i = 0; $i < rand( 50, 1000 ); $i++ ) {
			SimpleLogger()->warning(
				'An attempt to login as user "{user_login}" failed to login because the wrong password was entered',
				array(
					'user_login' => 'admin',
					'_userID' => null,
					'_initiator' => SimpleLoggerLogInitiators::WEB_USER,
				)
			);
		}

		// Add more data to context array. Data can be used later on to show detailed info about a log entry.
		SimpleLogger()->info(
			"Edited product '{pagename}'",
			array(
				'pagename' => 'We are hiring!',
				'_postType' => 'product',
				'_userID' => 1,
				'_userLogin' => 'jessie',
				'_userEmail' => 'jessie@example.com',
				'_occasionsID' => 'username:1,postID:24885,action:edited',
			)
		);

		SimpleLogger()->debug( 'This is a message with no translation' );
		SimpleLogger()->debug(
			'Plugin',
			array(
				'comment' => "This message is 'Plugin' and should contain text domain 'default' since it's a translation that comes with WordPress",
			)
		);
		SimpleLogger()->debug(
			'Enter title of new page',
			array(
				'comment' => 'A translation used in CMS Tree Page View',
			)
		);
	}
}
