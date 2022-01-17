# Tests

Tests use [wpbrowser](https://wpbrowser.wptestkit.dev/).

- Install required dependencies with `$ docker-compose run --rm php-cli composer install`.
- Copy `dump.sql` to `tests/_data/dump.sql`.
  This is the starting database fixture, containing the WordPress state that the tests start from. It's a minimal, starting environment shared by all tests. The file is not included in the repo.
- Start containers required for testing:
  `$ docker compose up -d`.
  This will start WordPress, MariaDB and a Headliess Chrome using Selenium.
- Run tests:
  - On _PHP 7.4_:
    - `$ docker-compose run --rm php-cli vendor/bin/codecept run wpunit`
    - `$ docker-compose run --rm php-cli vendor/bin/codecept run acceptance`
  - On _PHP 5.6_:
    - `$ PHP_CLI_VERSION=56 docker-compose run --rm php-cli vendor/bin/codecept run wpunit`
    - For acceptance tests wordpress-container must be restarted:
      - `$ WORDPRESS_VERSION=5 PHP_VERSION=5.6 docker-compose up`
      - `$ docker-compose run --rm php-cli vendor/bin/codecept run acceptance`
