<?php

namespace Simple_History;

/**
 * Class with information and data for a plus plugin.
 */
class AddOn_Plugin {
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

	/**
	 * ID of product that this plugin is for.
	 * Will be used to check that the entered licence key is for this product.
	 * For example History PLUS has id 105282.
	 *
	 * @var int
	 */
	public $product_id;

	private const OPTION_PREFIX = 'simple_history_plusplugin_';

	/**
	 * Default values for the licence message option.
	 *
	 * @var array<string,mixed>
	 */
	private array $message_defaults = [
		'key'             => null,
		'key_activated'   => false,
		'key_instance_id' => null,
		'key_created_at'  => null,
		'key_expires_at'  => null,
		'product_id'      => null,
		'product_name'    => null,
		'customer_name'   => null,
		'customer_email'  => null,
	];

	/**
	 * @param string   $id Id of plugin, eg basenamed path + index file: "simple-history-plus-woocommerce/index.php".
	 * @param string   $slug Slug of plugin, eg "simple-history-plus-woocommerce".
	 * @param string   $version Current version of plugin, eg "1.0.0".
	 * @param string   $name Name of plugin, eg "Simple History Plus for WooCommerce".
	 * @param int|null $product_id ID of product that this plugin is for.
	 */
	public function __construct( $id, $slug, $version, $name = '', $product_id = null ) {
		$this->id         = $id;
		$this->slug       = $slug;
		$this->version    = $version;
		$this->name       = $name;
		$this->product_id = $product_id;
	}

	/**
	 * Get the licence key for this plugin.
	 *
	 * @return mixed|null Licence key, or null if no key.
	 */
	public function get_license_key() {
		$message = $this->get_license_message();
		return $message['key'] ?? null;
	}

	/**
	 * Get the licence message for this plugin.
	 *
	 * @return array<string,mixed> Licence message.
	 */
	public function get_license_message() {
		/** @var array<string,mixed> */
		return get_option( $this->get_license_message_option_name(), $this->message_defaults );
	}

	/**
	 * Set the licence message for this plugin.
	 *
	 * @param array<string,mixed> $new_licence_message Licence message.
	 * @return bool True if option was updated, false if not.
	 */
	public function set_licence_message( $new_licence_message ) {
		return update_option( $this->get_license_message_option_name(), $new_licence_message );
	}

	/**
	 * Get the option name for the licence message for this plugin.
	 *
	 * @return string Option name.
	 */
	private function get_license_message_option_name() {
		return self::OPTION_PREFIX . 'message_' . $this->slug;
	}

	/**
	 * Activate a license key.
	 * Stores API result in option.
	 *
	 * @param string $license_key License key to activate.
	 * @return array<mixed>|null Array with info about key activation, or null if invalid.
	 */
	public function activate_license( $license_key ) {
		$activation_url = add_query_arg(
			array(
				'license_key'   => $license_key,
				'instance_name' => home_url(),
			),
			SIMPLE_HISTORY_LICENCES_API_URL . '/activate'
		);

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$response = wp_remote_get(
			$activation_url,
			array(
				'sslverify' => false,
				'timeout'   => 3,
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

		if ( is_null( $remote_body_json ) || ! is_array( $remote_body_json ) ) {
			return [
				'success' => false,
				'message' => __( 'Unknown error', 'simple-history' ),
			];
		}

		// Bail when bad request.
		if ( 400 === wp_remote_retrieve_response_code( $response ) ) {
			$this->set_licence_message( $this->message_defaults );
			$message = null;

			// Get error. Can be a single field (for example when licence key is not found),
			// or array of messages (for example when no licence_key exists in query string).
			if ( isset( $remote_body_json['data']['errors'] ) && is_array( $remote_body_json['data']['errors'] ) ) {
				$message = $remote_body_json['data']['errors'][0]['detail'];
			} elseif ( isset( $remote_body_json['data']['error'] ) ) {
				$message = $remote_body_json['data']['error'];
			}

			return [
				'success' => false,
				'message' => $message,
			];
		}

		// Key was activated successfully.
		$message = [
			'key_activated'   => true,
			'key'             => $remote_body_json['data']['license_key']['key'] ?? null,
			'key_instance_id' => $remote_body_json['data']['instance']['id'] ?? null,
			'key_created_at'  => $remote_body_json['data']['instance']['created_at'] ?? null,
			'key_expires_at'  => $remote_body_json['data']['license_key']['expires_at'] ?? null,
			'product_id'      => $remote_body_json['data']['meta']['product_id'] ?? null,
			'product_name'    => $remote_body_json['data']['meta']['product_name'] ?? null,
			'customer_name'   => $remote_body_json['data']['meta']['customer_name'] ?? null,
			'customer_email'  => $remote_body_json['data']['meta']['customer_email'] ?? null,
		];

		$this->set_licence_message( $message );

		// Deactivate and bail if activation was for another product.
		if ( $this->product_id && $this->product_id !== $remote_body_json['data']['meta']['product_id'] ) {
			$this->deactivate_license();

			return [
				'success' => false,
				'message' => 'The license key is not valid for this plugin.',
			];
		}

		return [
			'success' => true,
			'message' => 'Licence key successfully activated.',
		];
	}

	/**
	 * Deactivate a license key.
	 *
	 * @return bool|null True if deactivated, null if error.
	 */
	public function deactivate_license() {
		$license_key     = $this->get_license_key();
		$licence_message = $this->get_license_message();
		$instance_id     = $licence_message['key_instance_id'];

		$activation_url = add_query_arg(
			array(
				'license_key' => $license_key,
				'instance_id' => $instance_id,
			),
			SIMPLE_HISTORY_LICENCES_API_URL . '/deactivate'
		);

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$response = wp_remote_get(
			$activation_url,
			array(
				'sslverify' => false,
				'timeout'   => 3,
			)
		);

		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$this->set_licence_message(
				[
					'key'             => null,
					'key_activated'   => false,
					'key_instance_id' => null,
					'key_created_at'  => null,
					'key_expires_at'  => null,
					'product_id'      => null,
					'product_name'    => null,
					'customer_name'   => null,
					'customer_email'  => null,
				]
			);
			return true;
		} else {
			return null;
		}
	}
}
