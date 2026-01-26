<?php

namespace Simple_History;

/**
 * An example of a general-purpose implementation that includes the optional
 * functionality of allowing multiple base directories for a single namespace
 * prefix.
 *
 * Given a foo-bar package of classes in the file system at the following
 * paths ...
 *
 *     /path/to/packages/foo-bar/
 *         src/
 *             Baz.php             # Foo\Bar\Baz
 *             Qux/
 *                 Quux.php        # Foo\Bar\Qux\Quux
 *         tests/
 *             BazTest.php         # Foo\Bar\BazTest
 *             Qux/
 *                 QuuxTest.php    # Foo\Bar\Qux\QuuxTest
 *
 * ... add the path to the class files for the \Foo\Bar\ namespace prefix
 * as follows:
 *
 *      <?php
 *      // instantiate the loader
 *      $loader = new \Example\Psr4AutoloaderClass;
 *
 *      // register the autoloader
 *      $loader->register();
 *
 *      // register the base directories for the namespace prefix
 *      $loader->addNamespace('Foo\Bar', '/path/to/packages/foo-bar/src');
 *      $loader->addNamespace('Foo\Bar', '/path/to/packages/foo-bar/tests');
 *
 * The following line would cause the autoloader to attempt to load the
 * \Foo\Bar\Qux\Quux class from /path/to/packages/foo-bar/src/Qux/Quux.php:
 *
 *      <?php
 *      new \Foo\Bar\Qux\Quux;
 *
 * The following line would cause the autoloader to attempt to load the
 * \Foo\Bar\Qux\QuuxTest class from /path/to/packages/foo-bar/tests/Qux/QuuxTest.php:
 *
 *      <?php
 *      new \Foo\Bar\Qux\QuuxTest;
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
	 * Static classmap for optimized loading.
	 * Maps fully qualified class names to relative file paths.
	 *
	 * @since 5.23.0
	 * @var array<string, string>|null
	 */
	private ?array $classmap = null;

	/**
	 * Lowercase class name lookup map for case-insensitive matching.
	 * Maps lowercase class names to actual class names in $classmap.
	 *
	 * @since 5.23.0
	 * @var array<string, string>|null
	 */
	private ?array $classmap_lowercase = null;

	/**
	 * Whether to use classmap-based loading.
	 *
	 * @since 5.23.0
	 * @var bool
	 */
	private bool $use_classmap = false;

	/**
	 * Plugin base path for classmap file resolution.
	 *
	 * @since 5.23.0
	 * @var string
	 */
	private string $base_path = '';

	/**
	 * Register loader with SPL autoloader stack.
	 *
	 * @return void
	 */
	public function register() {
		spl_autoload_register( array( $this, 'load_class' ) );
	}

	/**
	 * Enable classmap-based loading for improved performance.
	 *
	 * When enabled, the autoloader checks a static classmap before
	 * falling back to filesystem-based resolution. This eliminates
	 * multiple file_exists() calls per class lookup.
	 *
	 * @since 5.23.0
	 * @param string $base_path Plugin base path for resolving relative paths in classmap.
	 * @return bool True if classmap was loaded successfully, false otherwise.
	 */
	public function enable_classmap( string $base_path ): bool {
		$this->base_path = rtrim( $base_path, '/' ) . '/';
		$classmap_file   = $this->base_path . 'inc/classmap-generated.php';

		if ( ! file_exists( $classmap_file ) ) {
			return false;
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Safe: path is constructed from plugin constant.
		$classmap = require $classmap_file;

		if ( ! is_array( $classmap ) ) {
			return false;
		}

		$this->classmap     = $classmap;
		$this->use_classmap = true;

		// Build lowercase lookup map for case-insensitive matching.
		// PHP class names are case-insensitive, but array keys are not.
		// This handles cases where class names are dynamically generated
		// with different casing (e.g., ucwords converts 'REST_API' to 'Rest_Api').
		$this->classmap_lowercase = array_combine(
			array_map( 'strtolower', array_keys( $classmap ) ),
			array_keys( $classmap )
		);

		return true;
	}

	/**
	 * Check if classmap-based loading is enabled and active.
	 *
	 * @since 5.23.0
	 * @return bool True if classmap is enabled and loaded.
	 */
	public function is_classmap_enabled(): bool {
		return $this->use_classmap && $this->classmap !== null;
	}

	/**
	 * Get the number of classes in the loaded classmap.
	 *
	 * @since 5.23.0
	 * @return int Number of classes, or 0 if classmap not loaded.
	 */
	public function get_classmap_count(): int {
		return $this->classmap !== null ? count( $this->classmap ) : 0;
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

		// Fast path: check classmap first (no file_exists calls needed).
		if ( $this->use_classmap && $this->classmap !== null ) {
			// Direct lookup (exact case match).
			if ( isset( $this->classmap[ $class_name ] ) ) {
				$file = $this->base_path . $this->classmap[ $class_name ];
				// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Safe: path from generated classmap.
				require $file;
				return $file;
			}

			// Case-insensitive lookup for dynamically generated class names.
			// PHP class names are case-insensitive, so 'REST_API' and 'Rest_Api' are the same class.
			$class_name_lower = strtolower( $class_name );
			if ( isset( $this->classmap_lowercase[ $class_name_lower ] ) ) {
				$actual_class_name = $this->classmap_lowercase[ $class_name_lower ];
				$file              = $this->base_path . $this->classmap[ $actual_class_name ];
				// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Safe: path from generated classmap.
				require $file;
				return $file;
			}
		}

		// Standard path: filesystem-based resolution.
		// The current namespace prefix.
		$prefix = $class_name;

		// Work backwards through the namespace names of the fully-qualified
		// class name to find a mapped file name.
		$pos = strrpos( $prefix, '\\' );

		while ( false !== $pos ) {
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
		// are there any base directories for this namespace prefix?
		if ( ! isset( $this->prefixes[ $prefix ] ) ) {
			return false;
		}

		// look through base directories for this namespace prefix.
		foreach ( $this->prefixes[ $prefix ] as $base_dir ) {
			// replace the namespace prefix with the base directory,
			// replace namespace separators with directory separators
			// in the relative class name, append with .php.

			// "Dropins/Debug_Dropin"
			$path_and_file = str_replace( '\\', '/', $relative_class );

			// Check for file with prefixed 'class-' and lowercase filename.
			$path_and_file_lowercased_and_prefixed = strtolower( $path_and_file );
			$path_and_file_lowercased_and_prefixed = str_replace( '_', '-', $path_and_file_lowercased_and_prefixed );
			$path_and_file_lowercased_and_prefixed = str_replace( '/', '/class-', $path_and_file_lowercased_and_prefixed );

			// Prepend "class" if it was not added above, because file was not in a subfolder.
			if ( ! str_contains( $path_and_file_lowercased_and_prefixed, 'class-' ) ) {
				$path_and_file_lowercased_and_prefixed = "class-{$path_and_file_lowercased_and_prefixed}";
			}

			$file_with_class_prefix = $base_dir . $path_and_file_lowercased_and_prefixed . '.php';

			// if the mapped file with "class-" prefix exists, require it.
			// <path>/WordPress-Simple-History/inc/services/class-admin-pages.php.
			if ( $this->require_file( $file_with_class_prefix ) ) {
				// yes, we're done.
				return $file_with_class_prefix;
			}

			// Check for file with prefixed 'interface-' and lowercase filename.
			$path_and_file_lowercased_and_prefixed_with_interface = strtolower( $path_and_file );
			$path_and_file_lowercased_and_prefixed_with_interface = str_replace( '_', '-', $path_and_file_lowercased_and_prefixed_with_interface );
			$path_and_file_lowercased_and_prefixed_with_interface = str_replace( '/', '/interface-', $path_and_file_lowercased_and_prefixed_with_interface );
			if ( ! str_contains( $path_and_file_lowercased_and_prefixed_with_interface, 'interface-' ) ) {
				$path_and_file_lowercased_and_prefixed_with_interface = "interface-{$path_and_file_lowercased_and_prefixed_with_interface}";
			}

			$file_with_interface_prefix = $base_dir . $path_and_file_lowercased_and_prefixed_with_interface . '.php';

			// if the mapped file with "interface-" prefix exists, require it.
			// <path>/WordPress-Simple-History/inc/event-details/interface-event-details-container-interface.php.
			if ( $this->require_file( $file_with_interface_prefix ) ) {
				// yes, we're done.
				return $file_with_interface_prefix;
			}

			// Check for file with prefixed 'trait-' and lowercase filename.
			$path_and_file_lowercased_and_prefixed_with_trait = strtolower( $path_and_file );
			$path_and_file_lowercased_and_prefixed_with_trait = str_replace( '_', '-', $path_and_file_lowercased_and_prefixed_with_trait );
			$path_and_file_lowercased_and_prefixed_with_trait = str_replace( '/', '/trait-', $path_and_file_lowercased_and_prefixed_with_trait );
			if ( ! str_contains( $path_and_file_lowercased_and_prefixed_with_trait, 'trait-' ) ) {
				$path_and_file_lowercased_and_prefixed_with_trait = "trait-{$path_and_file_lowercased_and_prefixed_with_trait}";
			}

			$file_with_trait_prefix = $base_dir . $path_and_file_lowercased_and_prefixed_with_trait . '.php';

			// if the mapped file with "trait-" prefix exists, require it.
			// <path>/WordPress-Simple-History/inc/channels/trait-channel-error-tracking.php.
			if ( $this->require_file( $file_with_trait_prefix ) ) {
				// yes, we're done.
				return $file_with_trait_prefix;
			}

			// <path>/WordPress-Simple-History/Dropins/Debug_Dropin.php.
			$file = $base_dir . $path_and_file . '.php';

			// if the mapped file exists, require it.
			if ( $this->require_file( $file ) ) {
				// yes, we're done.
				return $file;
			}
		}

		// never found it.
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
