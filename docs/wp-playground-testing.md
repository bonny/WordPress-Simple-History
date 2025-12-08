# WP Playground CLI for Quick Testing

For quick, ephemeral WordPress testing without Docker setup, use [WP Playground CLI](https://wordpress.github.io/wordpress-playground/). It auto-mounts the current directory as a plugin.

## Basic Usage

```bash
# Start WordPress with current directory mounted as plugin
npx @wp-playground/cli@latest server --auto-mount --login --wp=6.9 --php=8.2

# Test with different WordPress/PHP versions
npx @wp-playground/cli@latest server --auto-mount --login --wp=6.7 --php=8.3
npx @wp-playground/cli@latest server --auto-mount --login --wp=6.5 --php=7.4
```

## Options

| Flag | Description |
|------|-------------|
| `--auto-mount` | Mounts current directory as a WordPress plugin |
| `--login` | Auto-logs you into wp-admin |
| `--wp=X.X` | Specify WordPress version (e.g., 6.7, 6.9) |
| `--php=X.X` | Specify PHP version (e.g., 7.4, 8.2, 8.3) |

## When to Use

- Quick smoke testing of plugin changes
- Testing compatibility with different WP/PHP versions
- Demos and screenshots
- No persistent data needed

## Notes

- Data is ephemeral and lost when the server stops
- For persistent testing, use Docker-based installations
- See https://wordpress.github.io/wordpress-playground/developers/cli for full documentation
