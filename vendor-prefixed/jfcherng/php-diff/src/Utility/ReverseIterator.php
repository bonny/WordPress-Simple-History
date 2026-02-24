<?php
/**
 * @license BSD-3-Clause
 *
 * Modified by Pär Thernström on 24-February-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace Simple_History\Vendor\Jfcherng\Diff\Utility;

final class ReverseIterator
{
    public const ITERATOR_GET_VALUE = 0;
    public const ITERATOR_GET_KEY = 1 << 0;
    public const ITERATOR_GET_BOTH = 1 << 1;

    /**
     * The constructor.
     */
    private function __construct()
    {
    }

    /**
     * Iterate the array reversely.
     *
     * @param array $array the array
     * @param int   $flags the flags
     */
    public static function fromArray(array $array, int $flags = self::ITERATOR_GET_VALUE): \Generator
    {
        // iterate [key => value] pair
        if ($flags & self::ITERATOR_GET_BOTH) {
            for (end($array); ($key = key($array)) !== null; prev($array)) {
                yield $key => current($array);
            }

            return;
        }

        // iterate only key
        if ($flags & self::ITERATOR_GET_KEY) {
            for (end($array); ($key = key($array)) !== null; prev($array)) {
                yield $key;
            }

            return;
        }

        // iterate only value
        for (end($array); key($array) !== null; prev($array)) {
            yield current($array);
        }
    }
}
