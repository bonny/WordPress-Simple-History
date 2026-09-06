<?php

namespace Simple_History\Dropins;

/**
 * Dropin Name: IP Info
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class IP_Info_Dropin extends Dropin {
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_filter(
			'simple_history/row_header_output/display_ip_address',
			array( $this, 'row_header_display_ip_address_filter' ),
			10,
			2
		);
	}

	/**
	 * Display IP Addresses for login related messages.
	 *
	 * @param bool   $bool_value True if IP Address should be displayed.
	 * @param object $row Log row.
	 * @return bool
	 */
	public function row_header_display_ip_address_filter( $bool_value, $row ) {
		// Bail if log row in not from our logger.
		if ( $row->logger !== 'SimpleUserLogger' ) {
			return $bool_value;
		}

		// Bail if no message key.
		if ( empty( $row->context_message_key ) ) {
			return $bool_value;
		}

		// Message keys to show IP Addresses for.
		$arr_keys_to_log = array(
			'user_logged_in',
			'user_login_failed',
			'user_unknown_login_failed',
			'user_unknown_logged_in',
			'user_application_password_login_failed',
			'user_application_password_unknown_login_failed',
		);

		// Bail if not correct message key.
		if ( ! in_array( $row->context_message_key, $arr_keys_to_log, true ) ) {
			return $bool_value;
		}

		return true;
	}
}
