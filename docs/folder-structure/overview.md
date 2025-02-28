# Folder Structure Overview

Simple History follows a well-organized folder structure that separates different aspects of the plugin. Here's a detailed overview of the main directories and their purposes:

## Root Directory

```
WordPress-Simple-History/
├── inc/                  # Core plugin classes and functionality
├── loggers/             # Logger implementations
├── templates/           # Template files for views
├── dropins/             # Plugin extensions/drop-ins
├── js/                  # JavaScript files
├── css/                 # Stylesheet files
├── assets/             # Images and other static assets
├── tests/              # Test files
├── build/              # Build artifacts
├── src/                # Source files for build
├── docs/               # Documentation
└── vendor/             # Composer dependencies
```

## Key Directories in Detail

### `/inc` Directory
Core plugin functionality:
```
inc/
├── class-autoloader.php           # PSR-4 autoloader
├── global-helpers.php            # Global helper functions
├── services/                     # Service classes
│   ├── class-setup-database.php
│   ├── class-dropin-handler.php
│   └── ...
├── event-details/               # Event detail classes
└── deprecated/                  # Deprecated code
```

### `/loggers` Directory
Logger implementations for different types of events:
```
loggers/
├── class-plugin-logger.php      # Plugin events
├── class-user-logger.php        # User events
├── class-post-logger.php        # Post/page events
└── ...
```

### `/templates` Directory
Template files for the plugin's UI:
```
templates/
├── settings-page.php           # Settings page template
├── log-view.php               # Log viewer template
└── dashboard-widget.php       # Dashboard widget template
```

### `/dropins` Directory
Optional plugin extensions:
```
dropins/
├── class-example-dropin.php    # Example drop-in
└── ...
```

### `/js` and `/css` Directories
Frontend assets:
```
js/
├── simple-history.js          # Main JavaScript file
└── admin.js                  # Admin interface scripts

css/
├── styles.css               # Main stylesheet
└── admin.css               # Admin interface styles
```

### `/tests` Directory
Test files:
```
tests/
├── phpunit/               # PHPUnit tests
├── cypress/              # End-to-end tests
└── wordpress-tests-lib/  # WordPress test library
```

## Important Files in Root

- `index.php` - Main plugin file
- `uninstall.php` - Cleanup on plugin uninstall
- `composer.json` - Composer dependencies
- `package.json` - npm dependencies
- `README.md` - Plugin readme
- `CHANGELOG.md` - Version history

## Build System

The plugin uses a build system for processing assets:

- `/src` - Source files
- `/build` - Compiled/processed files
- `gruntfile.js` - Grunt build configuration

## Development Files

Files used during development:
```
├── .github/              # GitHub workflows and templates
├── .vscode/             # VS Code settings
├── .editorconfig        # Editor configuration
├── .eslintrc           # ESLint configuration
└── phpcs.xml.dist      # PHP CodeSniffer configuration
```

For more detailed information about specific directories and their contents, see [Key Directories](key-directories.md). 