module.exports = function (grunt) {

  require('time-grunt')(grunt);

  // Require all grunt-tasks instead of manually initialize them.
  require('load-grunt-tasks')(grunt);

  grunt.initConfig({

    makepot: {
        target: {
            options: {
                cwd: '',                          // Directory of files to internationalize.
                domainPath: 'languages/',                   // Where to save the POT file.
                exclude: [],                      // List of files or directories to ignore.
                include: [],                      // List of files or directories to include.
                i18nToolsPath: 'node_modules/grunt-wp-i18n/vendor/wp-i18n-tools',                // Path to the i18n tools directory.
                mainFile: '',                     // Main project file.
                potComments: '',                  // The copyright at the beginning of the POT file.
                potFilename: '',                  // Name of the POT file.
                potHeaders: {
                    poedit: true,                 // Includes common Poedit headers.
                    'x-poedit-keywordslist': true // Include a list of all possible gettext functions.
                },                                // Headers to add to the generated POT file.
                processPot: null,                 // A callback function for manipulating the POT file.
                type: 'wp-plugin',                // Type of project (wp-plugin or wp-theme).
                updateTimestamp: true             // Whether the POT-Creation-Date should be updated without other changes.
            }
        }
    }
  });

  // Task(s) to run. Default is default.
  grunt.registerTask('default', ["makepot"]);

};
