<?php

namespace Simple_History;

/**
 * Generates a static classmap for optimized autoloading.
 *
 * Scans the plugin's PHP files, extracts fully qualified class names,
 * and generates a PHP file mapping class names to file paths.
 *
 * @since 5.23.0
 */
class Classmap_Generator {

	/**
	 * Directories to scan for PHP class files, relative to plugin root.
	 *
	 * @var array<string>
	 */
	private array $directories = [
		'inc',
		'inc/services',
		'inc/services/wp-cli-commands',
		'inc/event-details',
		'inc/channels',
		'inc/deprecated',
		'loggers',
		'dropins',
	];

	/**
	 * Plugin root path.
	 *
	 * @var string
	 */
	private string $plugin_path;

	/**
	 * Constructor.
	 *
	 * @param string|null $plugin_path Optional plugin root path. Defaults to SIMPLE_HISTORY_PATH.
	 */
	public function __construct( ?string $plugin_path = null ) {
		$this->plugin_path = $plugin_path ?? ( defined( 'SIMPLE_HISTORY_PATH' ) ? SIMPLE_HISTORY_PATH : dirname( __DIR__ ) . '/' );
	}

	/**
	 * Generate the classmap by scanning all directories.
	 *
	 * @return array<string, string> Classmap array with class names as keys and relative file paths as values.
	 */
	public function generate(): array {
		$classmap = [];

		foreach ( $this->directories as $dir ) {
			$full_path = $this->plugin_path . $dir;
			if ( ! is_dir( $full_path ) ) {
				continue;
			}

			$dir_classes = $this->scan_directory( $full_path, $dir );
			$classmap    = array_merge( $classmap, $dir_classes );
		}

		// Sort by class name for consistent output.
		ksort( $classmap );

		return $classmap;
	}

	/**
	 * Scan a directory for PHP class files.
	 *
	 * @param string $dir_path Absolute path to directory.
	 * @param string $relative_dir Relative path from plugin root.
	 * @return array<string, string> Array of class => relative_path mappings.
	 */
	private function scan_directory( string $dir_path, string $relative_dir ): array {
		$classmap = [];
		$files    = glob( $dir_path . '/*.php' );

		if ( $files === false ) {
			return $classmap;
		}

		foreach ( $files as $file ) {
			$class_info = $this->extract_class_from_file( $file );
			if ( $class_info !== null ) {
				$relative_path           = $relative_dir . '/' . basename( $file );
				$classmap[ $class_info ] = $relative_path;
			}
		}

		return $classmap;
	}

	/**
	 * Extract the fully qualified class, interface, or trait name from a PHP file.
	 *
	 * Uses PHP's built-in tokenizer for reliable parsing instead of regex.
	 *
	 * @param string $filepath Path to PHP file.
	 * @return string|null Fully qualified class name or null if not found.
	 */
	private function extract_class_from_file( string $filepath ): ?string {
		$content = file_get_contents( $filepath );
		if ( $content === false ) {
			return null;
		}

		$tokens    = token_get_all( $content );
		$namespace = '';
		$class     = null;

		$token_count = count( $tokens );
		for ( $i = 0; $i < $token_count; $i++ ) {
			$token = $tokens[ $i ];

			// Skip non-array tokens (simple characters like ; { } etc.).
			if ( ! is_array( $token ) ) {
				continue;
			}

			// Extract namespace.
			if ( $token[0] === T_NAMESPACE ) {
				$namespace = $this->get_namespace_from_tokens( $tokens, $i );
			}

			// Extract class, interface, or trait name.
			if ( in_array( $token[0], [ T_CLASS, T_INTERFACE, T_TRAIT ], true ) ) {
				$class = $this->get_name_from_tokens( $tokens, $i );
				break; // Only get the first class/interface/trait.
			}
		}

		if ( $class === null ) {
			return null;
		}

		// Build fully qualified class name.
		if ( $namespace !== '' ) {
			return $namespace . '\\' . $class;
		}

		return $class;
	}

	/**
	 * Extract namespace from tokens starting at the namespace keyword position.
	 *
	 * @param array $tokens All tokens from the file.
	 * @param int   $start_index Index of T_NAMESPACE token.
	 * @return string The namespace or empty string if not found.
	 */
	private function get_namespace_from_tokens( array $tokens, int $start_index ): string {
		$namespace   = '';
		$token_count = count( $tokens );

		// Token types to capture for namespace.
		// T_STRING for individual namespace parts.
		// T_NS_SEPARATOR for backslashes.
		// T_NAME_QUALIFIED for namespaces like "Simple_History\Services" (PHP 8.0+).
		$namespace_tokens = [ T_STRING, T_NS_SEPARATOR ];
		if ( defined( 'T_NAME_QUALIFIED' ) ) {
			$namespace_tokens[] = T_NAME_QUALIFIED;
		}

		for ( $i = $start_index + 1; $i < $token_count; $i++ ) {
			$token = $tokens[ $i ];

			// End of namespace declaration.
			if ( $token === ';' || $token === '{' ) {
				break;
			}

			if ( is_array( $token ) && in_array( $token[0], $namespace_tokens, true ) ) {
				$namespace .= $token[1];
			}
		}

		return trim( $namespace );
	}

	/**
	 * Extract class/interface/trait name from tokens after the keyword.
	 *
	 * @param array $tokens All tokens from the file.
	 * @param int   $start_index Index of T_CLASS/T_INTERFACE/T_TRAIT token.
	 * @return string|null The name or null if not found.
	 */
	private function get_name_from_tokens( array $tokens, int $start_index ): ?string {
		$token_count = count( $tokens );

		for ( $i = $start_index + 1; $i < $token_count; $i++ ) {
			$token = $tokens[ $i ];

			if ( is_array( $token ) ) {
				// Skip whitespace.
				if ( $token[0] === T_WHITESPACE ) {
					continue;
				}

				// Found the name.
				if ( $token[0] === T_STRING ) {
					return $token[1];
				}
			}

			// Hit something unexpected, stop looking.
			break;
		}

		return null;
	}

	/**
	 * Write the classmap to a PHP file.
	 *
	 * @param string                     $output_path Path to output file.
	 * @param array<string, string>|null $classmap Optional classmap. Will generate if not provided.
	 * @return bool True on success, false on failure.
	 */
	public function write_classmap_file( string $output_path, ?array $classmap = null ): bool {
		if ( $classmap === null ) {
			$classmap = $this->generate();
		}

		$content = $this->generate_file_content( $classmap );

		$result = file_put_contents( $output_path, $content );
		return $result !== false;
	}

	/**
	 * Generate the PHP file content for the classmap.
	 *
	 * Uses var_export() for reliable PHP escaping of class names.
	 *
	 * @param array<string, string> $classmap The classmap array.
	 * @return string PHP file content.
	 */
	private function generate_file_content( array $classmap ): string {
		$header = <<<'PHP'
<?php
/**
 * Auto-generated classmap for optimized autoloading.
 * Do not edit manually - regenerate with: npm run classmap:generate
 *
 * Generated: %s
 * Class count: %d
 *
 * @package Simple_History
 */

return %s;

PHP;

		// var_export generates valid PHP array syntax with proper escaping.
		$array_export = var_export( $classmap, true );

		// Convert array() syntax to [] for modern PHP style.
		$array_export = preg_replace( '/^array \(/', '[', $array_export );
		$array_export = preg_replace( '/\)$/', ']', $array_export );

		return sprintf(
			$header,
			gmdate( 'Y-m-d\TH:i:s\Z' ),
			count( $classmap ),
			$array_export
		);
	}

	/**
	 * Get the list of directories being scanned.
	 *
	 * @return array<string> List of relative directory paths.
	 */
	public function get_directories(): array {
		return $this->directories;
	}

	/**
	 * Add a directory to scan.
	 *
	 * @param string $dir Relative path from plugin root.
	 * @return self
	 */
	public function add_directory( string $dir ): self {
		if ( ! in_array( $dir, $this->directories, true ) ) {
			$this->directories[] = $dir;
		}
		return $this;
	}
}
