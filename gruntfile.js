module.exports = function ( grunt ) {
	// Require all grunt-tasks instead of manually initialize them.
	require( 'load-grunt-tasks' )( grunt );

	const pkg = grunt.file.readJSON( 'package.json' );

	const config = {};

	config.pkg = pkg;

	config.version = {
		main: {
			options: {
				prefix: 'Version:[\\s]+',
			},
			src: [ 'index.php' ],
		},
		main2: {
			options: {
				prefix: "'SIMPLE_HISTORY_VERSION', '",
			},
			src: [ 'index.php' ],
		},
		readme: {
			options: {
				prefix: 'Stable tag:[\\s]+',
			},
			src: [ 'readme.txt' ],
		},
		pkg: {
			src: [ 'package.json' ],
		},
	};

	grunt.initConfig( config );

	grunt.registerTask(
		'bump',
		'Bump version in major, minor, patch or custom steps.',
		function ( version ) {
			if ( ! version ) {
				grunt.fail.fatal(
					'No version specified. Usage: bump:major, bump:minor, bump:patch, bump:x.y.z'
				);
			}

			grunt.task.run( [ 'version::' + version ] );
		}
	);
};
