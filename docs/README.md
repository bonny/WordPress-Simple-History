# Simple History Documentation

Simple History is a WordPress plugin that logs various things that occur in WordPress and presents those events in a beautiful and user-friendly interface. This documentation will help you understand how the plugin works, how to use it, and how to extend it for your needs.

## Quick Start

Simple History automatically logs various actions in WordPress and displays them in:
- WordPress admin area (as a page under the "Tools" menu)
- Dashboard widget
- Through the REST API
- Via WP-CLI commands

## Documentation Sections

### Architecture
- [Overview](architecture/overview.md) - High-level architecture of the plugin
- [Core Components](architecture/core-components.md) - Details about the main plugin components
- [Event System](architecture/event-system.md) - How events are logged and processed
- [Event Details](architecture/event-details.md) - Event details formatting and display system
- [Services](architecture/services.md) - Service-based architecture details

### Folder Structure
- [Overview](folder-structure/overview.md) - General folder organization
- [Key Directories](folder-structure/key-directories.md) - Important directories and their purposes

### Database
- [Schema](database/schema.md) - Database tables and fields
- [Versioning](database/versioning.md) - Database version management
- [Relationships](database/relationships.md) - How data is related

### Development Guide
- [Extending](development/extending.md) - How to extend the plugin
- [Custom Loggers](development/custom-loggers.md) - Creating your own loggers
- [Hooks Reference](development/hooks-reference.md) - Available filters and actions
- [Best Practices](development/best-practices.md) - Development guidelines

## Requirements

- WordPress 6.6 or newer
- PHP 7.4 or newer

## Support

For support:
- [GitHub Issues](https://github.com/bonny/WordPress-Simple-History/issues)
- [WordPress.org Support Forum](https://wordpress.org/support/plugin/simple-history/)

## Contributing

Contributions are welcome! Please read our [Contributing Guidelines](../CONTRIBUTING.md) before submitting pull requests.

## License

Simple History is licensed under the GPL v2 or later. 