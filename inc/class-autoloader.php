<?php

namespace Simple_History;

/**
 * PSR-4 autoloader for Simple History classes.
 *
 * Supports multiple base directories for a single namespace prefix.
 */
class Autoloader {

	/**
	 * An associative array where the key is a namespace prefix and the value
	 * is an array of base directories for classes in that namespace.
	 *
	 * @var array
	 */
	protected $prefixes = array();

	/**
	 * Register loader with SPL autoloader stack.
	 *
	 * @return void
	 */
	public function register() {
		spl_autoload_register( array( $this, 'load_class' ) );
	}

	/**
	 * Adds a base directory for a namespace prefix.
	 *
	 * @param string $prefix The namespace prefix.
	 * @param string $base_dir A base directory for class files in the
	 * namespace.
	 * @param bool   $prepend If true, prepend the base directory to the stack
	 *   instead of appending it; this causes it to be searched first rather
	 *   than last.
	 * @return void
	 */
	public function add_namespace( $prefix, $base_dir, $prepend = false ) {
		// normalize namespace prefix.
		$prefix = trim( $prefix, '\\' ) . '\\';

		// normalize the base directory with a trailing separator.
		$base_dir = rtrim( $base_dir, DIRECTORY_SEPARATOR ) . '/';

		// initialize the namespace prefix array.
		if ( ! isset( $this->prefixes[ $prefix ] ) ) {
			$this->prefixes[ $prefix ] = array();
		}

		// retain the base directory for the namespace prefix.
		if ( $prepend ) {
			array_unshift( $this->prefixes[ $prefix ], $base_dir );
		} else {
			$this->prefixes[ $prefix ][] = $base_dir;
		}
	}

	/**
	 * Loads the class file for a given class name.
	 *
	 * @param string $class_name The fully-qualified class name.
	 * @return mixed The mapped file name on success, or boolean false on
	 * failure.
	 */
	public function load_class( $class_name ) {
		// Early return for classes not in the Simple_History namespace.
		// This autoloader only handles Simple_History classes - let other autoloaders handle the rest.
		if ( ! str_starts_with( $class_name, 'Simple_History\\' ) && ! str_starts_with( $class_name, 'SimpleHistory' ) && ! str_starts_with( $class_name, 'SimpleLogger' ) ) {
			return false;
		}

		// The current namespace prefix.
		$prefix = $class_name;

		// Work backwards through the namespace names of the fully-qualified
		// class name to find a mapped file name.
		$pos = strrpos( $prefix, '\\' );

		while ( $pos !== false ) {
			// Retain the trailing namespace separator in the prefix.
			$prefix = substr( $class_name, 0, $pos + 1 );

			// The rest is the relative class name.
			$relative_class = substr( $class_name, $pos + 1 );

			// Try to load a mapped file for the prefix and relative class.
			$mapped_file = $this->load_mapped_file( $prefix, $relative_class );
			if ( $mapped_file ) {
				return $mapped_file;
			}

			// Remove the trailing namespace separator for the next iteration.
			$prefix = rtrim( $prefix, '\\' );
			$pos    = strrpos( $prefix, '\\' );
		}

		// Never found a mapped file.
		return false;
	}

	/**
	 * Load the mapped file for a namespace prefix and relative class.
	 *
	 * @param string $prefix The namespace prefix.
	 * @param string $relative_class The relative class name.
	 * @return mixed Boolean false if no mapped file can be loaded, or the
	 * name of the mapped file that was loaded.
	 */
	protected function load_mapped_file( $prefix, $relative_class ) {
		if ( ! isset( $this->prefixes[ $prefix ] ) ) {
			return false;
		}

		foreach ( $this->prefixes[ $prefix ] as $base_dir ) {
			// "Dropins/Debug_Dropin" -> "dropins/debug-dropin"
			// Single strtr() call replaces both \ and _ in one pass.
			$path_and_file   = str_replace( '\\', '/', $relative_class );
			$path_lowercased = strtolower( strtr( $path_and_file, '_', '-' ) );

			// Check class-, interface-, trait- prefixed files.
			foreach ( [ 'class', 'interface', 'trait' ] as $type_prefix ) {
				// Add prefix after directory separator, or at start if no separator.
				if ( str_contains( $path_lowercased, '/' ) ) {
					$prefixed_path = str_replace( '/', "/{$type_prefix}-", $path_lowercased );
				} else {
					$prefixed_path = "{$type_prefix}-{$path_lowercased}";
				}

				$file = $base_dir . $prefixed_path . '.php';

				if ( $this->require_file( $file ) ) {
					return $file;
				}
			}

			// Fallback: direct path without prefix (e.g., Dropins/Debug_Dropin.php).
			$file = $base_dir . $path_and_file . '.php';

			if ( $this->require_file( $file ) ) {
				return $file;
			}
		}

		return false;
	}

	/**
	 * If a file exists, require it from the file system.
	 *
	 * @param string $file The file to require.
	 * @return bool True if the file exists, false if not.
	 */
	protected function require_file( $file ) {
		if ( file_exists( $file ) ) {
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Safe: path built from registered namespaces, not user input.
			require $file;
			return true;
		}

		return false;
	}
}
