<?php
/**
 * Config class,
 * from https://github.com/szepeviktor/starter-plugin/blob/master/src/Config.php
 */

// phpcs:disable

namespace Simple_History;

/**
 * Immutable configuration.
 */
final class Config
{
    /** @var array<string, mixed>|null */
    private static $container;

    /**
     * @param array<string, mixed> $container
     * @return void
     */
    public static function init(array $container)
    {
        if (isset(self::$container)) {
            return;
        }

        self::$container = $container;
    }

    /**
     * @return mixed
     */
    public static function get(string $name)
    {
        if (! isset(self::$container) || ! array_key_exists($name, self::$container)) {
            return null;
        }

        return self::$container[$name];
    }

    /**
     * @return array<string, mixed>
     */
    public static function all() {
        return self::$container;
    }
}
