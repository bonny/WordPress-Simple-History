<?php

namespace Simple_History;

/**
 * Class with information and data for a plus plugin.
 */
class PLus_Plugin {
	/**
	 * Id of plugin, eg basenamed path + index file: "simple-history-plus-woocommerce/index.php".
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Slug of plugin, eg "simple-history-plus-woocommerce".
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Current version of plugin, eg "1.0.0".
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Name of plugin, eg "Simple History Plus for WooCommerce".
	 *
	 * @var string
	 */
	public $name;

	private const OPTION_PREFIX = 'simple_history_plusplugin_';

	private array $message_defaults = [
		'key' => null,
		'key_activated' => false,
		'key_instance_id' => null,
		'key_created_at' => null,
		'key_expires_at' => null,
		'product_id' => null,
		'product_name' => null,
		'customer_name' => null,
		'customer_email' => null,
	];

	public function __construct( $id, $slug, $version, $name ) {
		$this->id = $id;
		$this->slug = $slug;
		$this->version = $version;
		$this->name = $name;
	}

	public function get_license_key() {
		$message = $this->get_license_message();
		return $message['key'] ?? null;
	}

	public function get_license_message() {
		return get_option( $this->get_license_message_option_name(), $this->message_defaults );
	}

	public function set_licence_message( $new_licence_message ) {
		return update_option( $this->get_license_message_option_name(), $new_licence_message );
	}

	private function get_license_message_option_name() {
		return self::OPTION_PREFIX . 'message_' . $this->slug;
	}

	/**
	 * Activate a license key.
	 * Stores API result in option.
	 *
	 * @param string $license_key License key to activate.
	 * @return array|null Array with info about key activation, or null if invalid.
	 */
	public function activate_license( $license_key ) {
		$activation_url = add_query_arg(
			array(
				'license_key' => $license_key,
				'instance_name' => home_url(),
			),
			SIMPLE_HISTORY_LICENCES_API_URL . '/activate'
		);

		$response = wp_remote_get(
			$activation_url,
			array(
				'sslverify' => false,
				'timeout' => 10,
			)
		);

		if (
			is_wp_error( $response )
			|| ( 200 !== wp_remote_retrieve_response_code( $response ) && 400 !== wp_remote_retrieve_response_code( $response ) )
			|| empty( wp_remote_retrieve_body( $response ) )
		) {
			return [
				'success' => false,
				'message' => __( 'Unknown error', 'simple-history' ),
			];
		}

		$remote_body_json = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $remote_body_json['data']['activated'] === true ) {
			$message = [
				'key_activated' => true,
				'key' => $remote_body_json['data']['license_key']['key'],
				'key_instance_id' => $remote_body_json['data']['instance']['id'],
				'key_created_at' => $remote_body_json['data']['instance']['created_at'],
				'key_expires_at' => $remote_body_json['data']['license_key']['expires_at'],
				'product_id' => $remote_body_json['data']['meta']['product_id'],
				'product_name' => $remote_body_json['data']['meta']['product_name'],
				'customer_name' => $remote_body_json['data']['meta']['customer_name'],
				'customer_email' => $remote_body_json['data']['meta']['customer_email'],
			];

			$this->set_licence_message( $message );

			return [
				'success' => true,
				'message' => 'Licence key successfully activated.',
			];
		} else {
			$message = [
				'key' => null,
				'key_activated' => false,
				'key_instance_id' => null,
				'key_created_at' => null,
				'key_expires_at' => null,
				'product_id' => null,
				'product_name' => null,
				'customer_name' => null,
				'customer_email' => null,
			];

			$this->set_licence_message( $message );

			return [
				'success' => false,
				'message' => $remote_body_json['data']['error'],
			];
		}
	}

	/**
	 * Deactivate a license key.
	 *
	 * @return bool|null True if deactivated, null if error.
	 */
	public function deactivate_license() {
		$license_key = $this->get_license_key();
		$licence_message = $this->get_license_message();
		$instance_id = $licence_message['key_instance_id'];

		$activation_url = add_query_arg(
			array(
				'license_key' => $license_key,
				'instance_id' => $instance_id,
			),
			SIMPLE_HISTORY_LICENCES_API_URL . '/deactivate'
		);

		$response = wp_remote_get(
			$activation_url,
			array(
				'sslverify' => false,
				'timeout' => 10,
			)
		);

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$this->set_licence_message(
				[
					'key' => null,
					'key_activated' => false,
					'key_instance_id' => null,
					'key_created_at' => null,
					'key_expires_at' => null,
					'product_id' => null,
					'product_name' => null,
					'customer_name' => null,
					'customer_email' => null,
				]
			);
			return true;
		} else {
			return null;
		}
	}
}
