-- Runs once, when the database container starts on an empty data directory
-- (a fresh clone, CI, or after deleting data/mysql).
--
-- The image only creates `wp_test_site` from MYSQL_DATABASE. The wpunit suite
-- installs its own WordPress into `tests_db` (TEST_DB_NAME in .env.testing),
-- and WPLoader tries `CREATE DATABASE IF NOT EXISTS` with the dbuser account,
-- which has no global CREATE privilege. So on a machine where nobody has made
-- this database by hand, the suite dies at bootstrap with "Access denied".
--
-- The grant mirrors what MYSQL_USER gets on MYSQL_DATABASE.
CREATE DATABASE IF NOT EXISTS `tests_db`;
GRANT ALL PRIVILEGES ON `tests_db`.* TO 'dbuser'@'%';
FLUSH PRIVILEGES;
