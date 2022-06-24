# Tests

- Tests use [wpbrowser](https://wpbrowser.wptestkit.dev/).
- Tests are run using Docker.
- Install required dependencies with `$ docker-compose run --rm php-cli composer install`.
- Copy `dump.sql` to `tests/_data/dump.sql`.
  This is the starting database fixture, containing the WordPress state that the tests start from. It's a minimal, starting environment shared by all tests. The file is not included in the repo.
- Manually download [Jetpack](https://wordpress.org/plugins/jetpack/) and [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) and place in `tests/plugins`. The plugins are are used to test that Simple History catches changes in those plugins.
- Start containers required for testing:
  `$ docker compose up -d`.
  This will start WordPress, MariaDB and a Headliess Chrome using Selenium.
- Run _unit_, _acceptance_, and _functional_ tests using PHP 7.4:
  - `$ docker-compose run --rm php-cli vendor/bin/codecept run wpunit`
    - Faster tests to test things that does not require so much user input.
  - `$ docker-compose run --rm php-cli vendor/bin/codecept run acceptance`
    - These are tests that are performed using a Chromium browser, like it was done with users that actually visits the WP admin in a browser and does things. These test are slower but more realistic.
  - `❯ docker-compose run --rm php-cli vendor/bin/codecept run functional`
  - Run single test: `docker-compose run --rm php-cli vendor/bin/codecept run acceptance FirstCest:visitPluginPage`

## Run a single test

To run for example `UserCest:logUserProfileUpdated`:

`$ docker-compose run --rm php-cli vendor/bin/codecept run acceptance UserCest:logUserProfileUpdated`

## Setting up the starting database fixture

The `dump.sql` file is generating something like this:

```sh
# Install WordPress
docker-compose run --rm wp-cli wp core install --url=localhost:8080 --title=wp-tests --admin_user=admin --admin_email=test@example.com --admin_password=admin --skip-email

# Empty site (removes post etc.)
docker-compose run --rm wp-cli wp site empty --yes --uploads

# Activate plugin
docker-compose run --rm wp-cli plugin activate simple-history

# Export database to local file
docker-compose run --rm wp-cli wp db export - > db-export-`date +"%Y-%m-%d_%H:%M"`.sql

# Restore DB so can browse from localhost:9191 again, perhaps to update the fixture.
# Note: to update WP you need to temporary disable mu-plugin.php.
docker-compose run --rm wp-cli db import /var/www/html/tests/_data/dump.sql
docker compose run --rm wp-cli option set siteurl http://localhost:9191
docker compose run --rm wp-cli option set home http://localhost:9191
```

## Update log

Changes made to the test site and SQL-file.

- 24 june 2022: Added Jetpack 11.0 and WP Crontrol 1.12.1.
- 18 june 2022: Updated to WordPress 6.0 and updated Akismet + languages + themes.
