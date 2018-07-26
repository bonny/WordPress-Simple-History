<?php
/**
 * Logger for translation related things, like translation updated.
 *
 * @package SimpleHistory
 */

defined( 'ABSPATH' ) || die();

/**
 * Logger for translation related things.
 */
class SH_Translations_Logger extends SimpleLogger {

	/**
	 * Logger slug.
	 *
	 * @var string
	 */
	public $slug = __CLASS__;

	/**
	 * Return info about logger.
	 *
	 * @return array Array with plugin info.
	 */
	public function getInfo() {
		$arr_info = array(
			'name' => 'Translation',
			'description' => _x( 'Log WordPress translation related things', 'Logger: Translations', 'simple-history' ),
			'capability' => 'manage_options',
			'messages' => array(
				'translations_updated' => _x( 'Updated translations"', 'Logger: Translations', 'simple-history' ),
			),
		);

		return $arr_info;
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_process_complete' ), 10, 2);
	}

	public function on_upgrader_process_complete($upgrader, $options) {
		if ('translation' !== $options['type']) {
			return;
		}

		if ('update' !== $options['action']) {
			return;
		}

		/*
		Array
		(
		    [action] => update
		    [type] => translation
		    [bulk] => 1
		    [translations] => Array
		        (
		            [0] => Array
		                (
		                    [language] => de_DE
		                    [type] => plugin
		                    [slug] => akismet
		                    [version] => 4.0.8
		                )

		            [1] => Array
		                (
		                    [language] => sv_SE
		                    [type] => plugin
		                    [slug] => akismet
		                    [version] => 4.0.8
		                )

		            [2] => Array
		                (
		                    [language] => de_DE
		                    [type] => plugin
		                    [slug] => bbpress
		                    [version] => 2.5.14
		                )

		            [3] => Array
		                (
		                    [language] => sv_SE
		                    [type] => plugin
		                    [slug] => bbpress
		                    [version] => 2.5.14
		                )
		            [10] => Array
		                (
		                    [language] => de_DE
		                    [type] => theme
		                    [slug] => twentysixteen
		                    [version] => 1.5
		                )

		            [11] => Array
		                (
		                    [language] => sv_SE
		                    [type] => theme
		                    [slug] => twentysixteen
		                    [version] => 1.5
		                )
		        )

		)
		*/

		sh_error_log('on_upgrader_process_complete yes', $options);
	}

} // class


