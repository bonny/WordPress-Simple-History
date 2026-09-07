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
		'key'                      => null,
		'key_activated'            => false,
		'key_instance_id'          => null,
		'key_created_at'           => null,
		'key_expires_at'           => null,
		'product_id'               => null,
		'product_name'             => null,
		'customer_name'            => null,
		'customer_email'           => null,
		// Refreshed by every update check, see update_license_status_from_response().
		// Absent from options written by older versions, so read them with ??.
		'license_status'           => null,
		'license_valid'            => null,
		'license_error'            => '',
		'license_activation_limit' => null,
		'license_activation_usage' => null,
		'license_checked_at'       => null,
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
	 * Merge the license object from an update-check response into the stored license message.
	 *
	 * The activation response is stored once and never refreshed by Lemon Squeezy,
	 * so a renewed subscription looks expired locally. The update endpoint on
	 * simple-history.com now returns the key's current status and expiry on every
	 * check; this stores it next to the activation data.
	 *
	 * Ignores anything that is not a well-formed license object, and never
	 * creates a license for a site that has none: a missing or null object
	 * means "could not check", and the previous state must survive.
	 *
	 * @param mixed $license Decoded `license` array from the update response.
	 * @return bool True when the option was written.
	 */
	public function update_license_status_from_response( $license ) {
		if ( ! is_array( $license ) ) {
			return false;
		}

		// checked_at is the marker that a real check happened; without it the
		// object is not from our endpoint.
		if ( ! isset( $license['checked_at'] ) || ! is_string( $license['checked_at'] ) ) {
			return false;
		}

		$message = $this->get_license_message();

		if ( empty( $message['key'] ) || empty( $message['key_activated'] ) ) {
			return false;
		}

		$status = $license['status'] ?? null;

		if ( $status !== null && ! is_string( $status ) ) {
			return false;
		}

		$expires_at = $license['expires_at'] ?? null;

		if ( $expires_at !== null && ! is_string( $expires_at ) ) {
			$expires_at = null;
		}

		$license_error = isset( $license['error'] ) && is_string( $license['error'] ) ? $license['error'] : '';

		// Cap server-supplied strings before storing: they end up in an
		// autoloaded option and this is the only validation they get.
		$message['key_expires_at']           = $this->cap_string_length( $expires_at );
		$message['license_status']           = $this->cap_string_length( $status );
		$message['license_valid']            = ! empty( $license['valid'] );
		$message['license_error']            = $this->cap_string_length( $license_error );
		$message['license_activation_limit'] = isset( $license['activation_limit'] ) && is_int( $license['activation_limit'] ) ? $license['activation_limit'] : null;
		$message['license_activation_usage'] = isset( $license['activation_usage'] ) && is_int( $license['activation_usage'] ) ? $license['activation_usage'] : null;
		$message['license_checked_at']       = $this->cap_string_length( $license['checked_at'] );

		$this->set_licence_message( $message );

		return true;
	}

	/**
	 * Derive one license state from the stored license message.
	 *
	 * Prefers the status from the last update check. Falls back to the
	 * activation-time expiry date for sites where no check has run since
	 * this was added, which is the same guess older versions made.
	 *
	 * @return array{
	 *   state: string,
	 *   source: string,
	 *   expires_at: ?string,
	 *   expires_timestamp: ?int,
	 *   is_lifetime: bool,
	 *   checked_at: ?string,
	 *   error: string,
	 *   activation_limit: ?int,
	 *   activation_usage: ?int
	 * } state is one of none, active, expired, disabled, invalid. source is one of none, activation, update_check.
	 */
	public function get_license_state() {
		$message = $this->get_license_message();

		$state = [
			'state'             => 'none',
			'source'            => 'none',
			'expires_at'        => null,
			'expires_timestamp' => null,
			'is_lifetime'       => false,
			'checked_at'        => null,
			'error'             => '',
			'activation_limit'  => null,
			'activation_usage'  => null,
		];

		if ( empty( $message['key'] ) || empty( $message['key_activated'] ) ) {
			return $state;
		}

		$expires_at        = isset( $message['key_expires_at'] ) && is_string( $message['key_expires_at'] ) ? $message['key_expires_at'] : null;
		$expires_timestamp = $expires_at !== null ? strtotime( $expires_at ) : false;

		if ( $expires_timestamp === false ) {
			$expires_timestamp = null;
			$expires_at        = null;
		}

		$state['expires_at']        = $expires_at;
		$state['expires_timestamp'] = $expires_timestamp;
		$state['error']             = isset( $message['license_error'] ) && is_string( $message['license_error'] ) ? $message['license_error'] : '';
		$state['activation_limit']  = isset( $message['license_activation_limit'] ) && is_int( $message['license_activation_limit'] ) ? $message['license_activation_limit'] : null;
		$state['activation_usage']  = isset( $message['license_activation_usage'] ) && is_int( $message['license_activation_usage'] ) ? $message['license_activation_usage'] : null;

		$checked_at = isset( $message['license_checked_at'] ) && is_string( $message['license_checked_at'] ) ? $message['license_checked_at'] : null;

		if ( $checked_at === null ) {
			// Never checked: the activation-time expiry is all there is.
			$state['source'] = 'activation';
			$state['state']  = $expires_timestamp !== null && $expires_timestamp < time() ? 'expired' : 'active';
		} else {
			$state['source']     = 'update_check';
			$state['checked_at'] = $checked_at;

			$status = isset( $message['license_status'] ) && is_string( $message['license_status'] ) ? $message['license_status'] : null;

			if ( $status === 'active' || $status === 'inactive' ) {
				$state['state'] = 'active';
			} elseif ( $status === 'expired' ) {
				$state['state'] = 'expired';
			} elseif ( $status === 'disabled' ) {
				$state['state'] = 'disabled';
			} else {
				$state['state'] = 'invalid';
			}
		}

		$state['is_lifetime'] = $state['state'] === 'active' && $expires_at === null;

		return $state;
	}

	/**
	 * One-sentence, plain-text description of the license state for the Licenses tab.
	 *
	 * An activation-source `expired` state is a guess made from the stale
	 * activation-time expiry, not a fact from the server, so it makes no
	 * claim about expiry: it returns an empty string rather than telling a
	 * renewed customer their license expired.
	 *
	 * @param array<string,mixed>|null $license_state Pass an already-computed state to avoid recomputing it. Defaults to computing it.
	 * @return string Empty when there is no license, or when the only evidence of expiry is a stale activation-time guess.
	 */
	public function get_license_state_description( ?array $license_state = null ) {
		$state = $license_state ?? $this->get_license_state();

		if ( $state['state'] === 'expired' && $state['source'] === 'activation' ) {
			return '';
		}

		$date = $state['expires_timestamp'] !== null ? wp_date( get_option( 'date_format' ), $state['expires_timestamp'] ) : '';

		$usage = '';

		if ( $state['activation_limit'] !== null && $state['activation_usage'] !== null ) {
			$usage = sprintf(
				/* translators: 1: number of sites the key is activated on, 2: number of sites the key allows. */
				__( 'Activated on %1$d of %2$d sites.', 'simple-history' ),
				$state['activation_usage'],
				$state['activation_limit']
			);
		}

		switch ( $state['state'] ) {
			case 'active':
				if ( $state['is_lifetime'] ) {
					$text = __( 'Lifetime license.', 'simple-history' );
				} else {
					/* translators: %s: date */
					$text = sprintf( __( 'Valid until %s.', 'simple-history' ), $date );
				}
				break;

			case 'expired':
				/* translators: %s: date */
				$text = $date !== '' ? sprintf( __( 'Expired on %s.', 'simple-history' ), $date ) : __( 'License has expired.', 'simple-history' );
				break;

			case 'disabled':
				$text = __( 'License has been disabled.', 'simple-history' );
				break;

			case 'invalid':
				$text = __( 'License key is no longer valid.', 'simple-history' );
				break;

			default:
				return '';
		}

		return trim( $text . ' ' . $usage );
	}

	/**
	 * Cap a server-supplied value's length before it is stored.
	 *
	 * @param mixed $value Value to cap.
	 * @return mixed The capped string, or the value unchanged when it is not a string.
	 */
	private function cap_string_length( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		return substr( $value, 0, 255 );
	}

	/**
	 * Forget the cached update-check response for this add-on.
	 *
	 * Mirrors Plugin_Updater::$cache_key. Called on (de)activation so the next
	 * check asks the server instead of replaying a stale answer.
	 */
	private function purge_updater_cache() {
		delete_transient( 'simple_history_updater_cache_' . str_replace( '-', '_', $this->slug ) );
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
			|| ( wp_remote_retrieve_response_code( $response ) !== 200 && wp_remote_retrieve_response_code( $response ) !== 400 )
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
		if ( wp_remote_retrieve_response_code( $response ) === 400 ) {
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
		$this->purge_updater_cache();

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

		if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
			$this->set_licence_message(
				[
					'key'                      => null,
					'key_activated'            => false,
					'key_instance_id'          => null,
					'key_created_at'           => null,
					'key_expires_at'           => null,
					'product_id'               => null,
					'product_name'             => null,
					'customer_name'            => null,
					'customer_email'           => null,
					'license_status'           => null,
					'license_valid'            => null,
					'license_error'            => '',
					'license_activation_limit' => null,
					'license_activation_usage' => null,
					'license_checked_at'       => null,
				]
			);
			$this->purge_updater_cache();

			return true;
		}

		return null;
	}
}
