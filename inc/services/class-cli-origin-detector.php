<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use WP_CLI;

/**
 * Records where a WP-CLI command came from: the command, the server user
 * that ran it and, for commands run over SSH, the client IP address.
 *
 * Attached as context to every event logged during a WP-CLI run. Only
 * stored for now, nothing in the log UI shows it: it is being evaluated on
 * real servers first. Users without manage_options can't read, search or
 * filter on the server users and SSH IP.
 *
 * Only the process user comes from the kernel. SSH_CONNECTION and SUDO_USER
 * are environment variables that anyone with a shell can set or unset, so
 * this is an attribution hint, not proof. Absent values prove nothing
 * either: cron, docker exec and hosting panel terminals have no SSH
 * variables, and a tmux or screen session keeps the SSH_CONNECTION of the
 * login that started it.
 *
 * Arguments are never stored, positional ones included: `wp eval`,
 * `wp option update` and `wp db query` carry code, secrets and SQL in them.
 *
 * Experimental.
 */
class CLI_Origin_Detector extends Service {
	/** Command path, for example "plugin deactivate". */
	const CONTEXT_KEY_COMMAND = '_cli_command';

	/** Name of the server user the PHP process runs as. */
	const CONTEXT_KEY_PROCESS_USER = '_cli_process_user';

	/** The user behind sudo, from SUDO_USER. Self-reported. */
	const CONTEXT_KEY_SUDO_USER = '_cli_sudo_user';

	/** SSH client IP from SSH_CONNECTION, masked like other IP addresses. Self-reported. */
	const CONTEXT_KEY_SSH_CLIENT_IP = '_cli_ssh_client_ip';

	/**
	 * Context keys that identify server users and their machines.
	 *
	 * Valid SSH login names and the IP addresses admins connect from are
	 * reconnaissance data, so they are only shown to users who can see IP
	 * addresses.
	 *
	 * @var string[]
	 */
	const SENSITIVE_CONTEXT_KEYS = [
		self::CONTEXT_KEY_PROCESS_USER,
		self::CONTEXT_KEY_SUDO_USER,
		self::CONTEXT_KEY_SSH_CLIENT_IP,
	];

	/**
	 * Cached context for this process, null until detected.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $cli_context = null;

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		add_filter( 'simple_history/log_insert_context', [ $this, 'attach_context' ], 10, 1 );
	}

	/**
	 * Add the WP-CLI origin keys to an event's context.
	 *
	 * @param array<string, mixed> $context Context array as built by the logger.
	 * @return array<string, mixed>
	 */
	public function attach_context( $context ) {
		if ( $this->cli_context === null ) {
			$this->cli_context = self::build_context(
				[
					'SSH_CONNECTION' => getenv( 'SSH_CONNECTION' ),
					'SUDO_USER'      => getenv( 'SUDO_USER' ),
				],
				self::get_process_user(),
				self::get_command_path()
			);
		}

		return array_merge( $context, $this->cli_context );
	}

	/**
	 * Build the context keys from raw values.
	 *
	 * Every value is treated as untrusted: anything that doesn't parse
	 * cleanly is dropped rather than cleaned up.
	 *
	 * @param array<string, string|false> $env          SSH_CONNECTION and SUDO_USER, false when unset.
	 * @param string|null                 $process_user Name of the user the process runs as.
	 * @param string[]                    $command_path Command names, for example [ 'plugin', 'deactivate' ].
	 * @return array<string, string>
	 */
	public static function build_context( $env, $process_user, $command_path ) {
		$context = [];

		$command = self::sanitize_command_path( $command_path );

		if ( $command !== null ) {
			$context[ self::CONTEXT_KEY_COMMAND ] = $command;
		}

		$process_user = self::sanitize_username( $process_user );

		if ( $process_user !== null ) {
			$context[ self::CONTEXT_KEY_PROCESS_USER ] = $process_user;
		}

		$sudo_user = self::sanitize_username( $env['SUDO_USER'] ?? null );

		if ( $sudo_user !== null ) {
			$context[ self::CONTEXT_KEY_SUDO_USER ] = $sudo_user;
		}

		$ssh_client_ip = self::parse_ssh_client_ip( $env['SSH_CONNECTION'] ?? null );

		if ( $ssh_client_ip !== null ) {
			$context[ self::CONTEXT_KEY_SSH_CLIENT_IP ] = Helpers::privacy_anonymize_ip( $ssh_client_ip );
		}

		return $context;
	}

	/**
	 * Get the client IP from SSH_CONNECTION.
	 *
	 * The format is "client_ip client_port server_ip server_port". Only the
	 * client IP is kept: the server IP shows the internal network layout and
	 * the ports tell nobody anything.
	 *
	 * @param mixed $ssh_connection Value of SSH_CONNECTION.
	 * @return string|null
	 */
	public static function parse_ssh_client_ip( $ssh_connection ) {
		if ( ! is_string( $ssh_connection ) || $ssh_connection === '' ) {
			return null;
		}

		$fields = explode( ' ', trim( $ssh_connection ) );

		if ( count( $fields ) !== 4 ) {
			return null;
		}

		$client_ip = filter_var( $fields[0], FILTER_VALIDATE_IP );

		return is_string( $client_ip ) ? $client_ip : null;
	}

	/**
	 * Accept a server username only if it looks like one.
	 *
	 * @param mixed $username Username.
	 * @return string|null
	 */
	public static function sanitize_username( $username ) {
		if ( ! is_string( $username ) || ! preg_match( '/^[A-Za-z0-9._-]{1,32}$/', $username ) ) {
			return null;
		}

		return $username;
	}

	/**
	 * Join command names into a command path, if every name looks like one.
	 *
	 * @param mixed $command_path Command names.
	 * @return string|null
	 */
	public static function sanitize_command_path( $command_path ) {
		if ( ! is_array( $command_path ) || empty( $command_path ) || count( $command_path ) > 5 ) {
			return null;
		}

		foreach ( $command_path as $name ) {
			if ( ! is_string( $name ) || ! preg_match( '/^[a-z0-9][a-z0-9_:-]{0,49}$/i', $name ) ) {
				return null;
			}
		}

		return implode( ' ', $command_path );
	}

	/**
	 * Get the name of the user the PHP process runs as.
	 *
	 * From the effective uid, which can't be faked without privileges. Not
	 * get_current_user(), which returns the owner of the PHP file.
	 *
	 * @return string|null Null when the posix extension is missing.
	 */
	private static function get_process_user() {
		if ( ! function_exists( 'posix_geteuid' ) || ! function_exists( 'posix_getpwuid' ) ) {
			return null;
		}

		$user_info = posix_getpwuid( posix_geteuid() );

		return is_array( $user_info ) ? $user_info['name'] : null;
	}

	/**
	 * Get the names of the command being run, without its arguments.
	 *
	 * Walks the same command tree WP-CLI uses, so "wp plugin deactivate
	 * akismet" gives [ 'plugin', 'deactivate' ].
	 *
	 * @return string[]
	 */
	private static function get_command_path() {
		if ( ! class_exists( WP_CLI::class ) ) {
			return [];
		}

		try {
			$args    = WP_CLI::get_runner()->arguments;
			$command = WP_CLI::get_root_command();
			$path    = [];

			while ( is_array( $args ) && ! empty( $args ) && $command->can_have_subcommands() ) {
				// Removes the command name from $args.
				$subcommand = $command->find_subcommand( $args );

				if ( ! $subcommand ) {
					break;
				}

				$path[]  = $subcommand->get_name();
				$command = $subcommand;
			}

			return $path;
		} catch ( \Throwable $e ) {
			return [];
		}
	}
}
