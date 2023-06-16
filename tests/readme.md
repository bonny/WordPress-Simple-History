# Tests

- Tests use [wpbrowser](https://wpbrowser.wptestkit.dev/).
- Tests are run using Docker.
- Install required composer dependencies with `$ docker-compose run --rm php-cli composer install`.
- Copy `dump.sql` to `tests/_data/dump.sql`.
  - This is the starting database fixture, containing the WordPress state that the tests start from. It's a minimal, starting environment shared by all tests. The file is not included in the repo.
- Copy `twentysixteen.2.6.zip` and `twentysixteen.2.7.zip` to `tests/_data/twentysixteen.2.6.zip` and `tests/_data/twentysixteen.2.7.zip`.
- Manually download [Jetpack](https://wordpress.org/plugins/jetpack/) and [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) and place in `tests/plugins`. The plugins are are used to test that Simple History catches changes in those plugins.
- Start containers required for testing:
  `$ docker compose up -d`.
  This will start **WordPress**, **MariaDB** and a **Headless Chrome** using Selenium.
- Run _unit_, _acceptance_, and _functional_ tests using PHP 7.4:
  - `$ docker-compose run --rm php-cli vendor/bin/codecept run wpunit`
    - Faster tests to test things that does not require so much user input.
  - `$ docker-compose run --rm php-cli vendor/bin/codecept run acceptance`
    - These are tests that are performed using a Chromium browser, like it was done with users that actually visits the WP admin in a browser and does things. These test are slower but more realistic.
    - To run a single test run for example
      `$ docker-compose run --rm php-cli vendor/bin/codecept run acceptance SimpleUserLoggerCest:logUserCreated` or to run a single suite
      `$ docker-compose run --rm php-cli vendor/bin/codecept run acceptance SimpleUserLoggerCest`
  - `❯ docker-compose run --rm php-cli vendor/bin/codecept run functional`
  - Run single test:
    - `docker-compose run --rm php-cli vendor/bin/codecept run acceptance FirstCest:visitPluginPage`

## Run a single test

To run for example `UserCest:logUserProfileUpdated`:

`$ docker-compose run --rm php-cli vendor/bin/codecept run acceptance UserCest:logUserProfileUpdated`

## Setting up the starting database fixture

The `dump.sql` file is generating something like this:

```sh
# Install WordPress
docker-compose run --rm wp-cli wp core install --version=6.1 --url=localhost:8080 --title=wp-tests --admin_user=admin --admin_email=test@example.com --admin_password=admin --skip-email

# Empty site (removes post etc.)
docker-compose run --rm wp-cli wp site empty --yes --uploads

# Activate plugin
docker-compose run --rm wp-cli plugin activate simple-history

# Export database to local file
docker-compose run --rm wp-cli wp db export - > db-export-`date +"%Y-%m-%d_%H:%M"`.sql
```

To modify installed WordPress version using a web browser, to for example update WordPress or update plugins:

```sh
# Restore DB so can browse from localhost:9191 again, perhaps to update the fixture.
# Note: to update WP you need to temporary disable mu-plugin.php. (Is this still true?)

docker-compose run --rm wp-cli db import /var/www/html/tests/\_data/dump.sql
docker compose run --rm wp-cli option set siteurl http://localhost:9191
docker compose run --rm wp-cli option set home http://localhost:9191
# ...do changes...
# then export sql file again:
docker-compose run --rm wp-cli wp db export - > db-export-`date +"%Y-%m-%d_%H:%M"`.sql
```

## Update log

Changes made to the test site and SQL-file.

- June 2023: Misc changes, updated Jetpack, added support for changed classes, added Developer Loggers, and more.
- 24 june 2022: Added Jetpack 11.0 and WP Crontrol 1.12.1.
- 18 june 2022: Updated to WordPress 6.0 and updated Akismet + languages + themes.

## Clean install of WordPress for tests

- docker compose up
- wp running on localhost:9191 but it thinks its on port 80 (beacuse thats the internal port)
- access at http://localhost:9191/wp-login.php
- ...forgot the rest.. update this next time I need to do it 🤷‍♀️.
