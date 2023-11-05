<?php

namespace Simple_History\Services;

/**
 * Setup pause and resume actions.
 */
class Setup_Pause_Resume_Actions extends Service {
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		$this->add_pause_and_resume_actions();
	}

	/**
	 * Actions to disable and enable logging.
	 * Useful for example when importing many things using PHP because then
	 * the log can be overwhelmed with data.
	 *
	 * @since 4.0.2
	 */
	protected function add_pause_and_resume_actions() {
		add_action(
			'simple_history/pause',
			function () {
				add_filter( 'simple_history/log/do_log', '__return_false' );
			}
		);

		add_action(
			'simple_history/resume',
			function () {
				remove_filter( 'simple_history/log/do_log', '__return_false' );
			}
		);
	}
}
