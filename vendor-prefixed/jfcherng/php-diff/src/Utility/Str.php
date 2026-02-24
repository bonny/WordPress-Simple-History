<?php
/**
 * @license BSD-3-Clause
 *
 * Modified by Pär Thernström on 24-February-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace Simple_History\Vendor\Jfcherng\Diff\Utility;

final class Str
{
    /**
     * Determine if a given string starts with a given substring.
     *
     * @param string $haystack the haystack
     * @param string $needle   the needle
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return substr($haystack, 0, \strlen($needle)) === $needle;
    }

    /**
     * Determine if a given string ends with a given substring.
     *
     * @param string $haystack the haystack
     * @param string $needle   the needle
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return substr($haystack, -\strlen($needle)) === $needle;
    }
}
