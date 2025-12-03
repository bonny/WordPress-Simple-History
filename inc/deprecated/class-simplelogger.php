<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
use Simple_History\Loggers\Logger;

/**
 * Un-namespaced class for old loggers that extend \SimpleLogger.
 *
 * New loggers must extend Simple_History\Loggers\Logger.
 *
 * @method null warningMessage(string $message, array $context)
 */
class SimpleLogger extends Logger {
	/**
	 * Methods that used to exist and needs to be remapped.
	 *
	 * @var string[] Array of key/value pairs where keys represent old method name and value is name of new method
	 */
	private array $methods_mapping = array(
		'getInfoValueByKey'                => 'get_info_value_by_key',
		'getCapability'                    => 'get_capability',
		'interpolate'                      => null, // moved to helper.
		'getLogRowHeaderInitiatorOutput'   => 'get_log_row_header_initiator_output',
		'getLogRowHeaderDateOutput'        => 'get_log_row_header_date_output',
		'getLogRowHeaderUsingPluginOutput' => 'get_log_row_header_using_plugin_output',
		'getLogRowHeaderIPAddressOutput'   => 'get_log_row_header_ip_address_output',
		'getLogRowHeaderOutput'            => 'get_log_row_header_output',
		'getLogRowPlainTextOutput'         => 'get_log_row_plain_text_output',
		'getLogRowSenderImageOutput'       => 'get_log_row_sender_image_output',
		'getLogRowDetailsOutput'           => 'get_log_row_details_output',
		'emergencyMessage'                 => 'emergency_message',
		'logByMessageKey'                  => 'log_by_message_key',
		'alertMessage'                     => 'alert_message',
		'criticalMessage'                  => 'critical_message',
		'errorMessage'                     => 'error_message',
		'warningMessage'                   => 'warning_message',
		'noticeMessage'                    => 'notice_message',
		'infoMessage'                      => 'info_message',
		'debugMessage'                     => 'debug_message',
		'adminCSS'                         => 'admin_css',
		'adminJS'                          => 'admin_js',
	);

	/**
	 * Calls `getInfo()` on child class, if method exists.
	 *
	 * Since `get_info()` is an abstract method on Logger class,
	 * it can't be detected in `_call` method and therefore
	 * it must be added.
	 *
	 * @return array
	 */
	public function get_info() {
		if ( ! method_exists( $this, 'getInfo' ) ) {
			return array();
		}

		return $this->getInfo();
	}

	/**
	 * Call new method when calling old/deprecated method names.
	 *
	 * @since 4.0
	 * @param string $name Method name.
	 * @param array  $arguments Arguments.
	 * @return mixed
	 */
	public function __call( $name, $arguments ) {
		// Bail if method name is nothing to act on.
		if ( ! isset( $this->methods_mapping[ $name ] ) ) {
			return;
		}

		$method_name_to_call = $this->methods_mapping[ $name ];

		return call_user_func_array( array( $this, $method_name_to_call ), $arguments );
	}
}
