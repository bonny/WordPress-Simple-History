<?php
/**
 * @license BSD-3-Clause
 *
 * Modified by Pär Thernström on 24-February-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace Simple_History\Vendor\Jfcherng\Diff\Exception;

final class FileNotFoundException extends \Exception
{
    public function __construct(string $filepath = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("File not found: {$filepath}", $code, $previous);
    }
}
