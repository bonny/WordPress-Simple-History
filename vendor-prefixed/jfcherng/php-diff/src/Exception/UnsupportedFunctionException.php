<?php
/**
 * @license BSD-3-Clause
 *
 * Modified by Pär Thernström on 24-February-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace Simple_History\Vendor\Jfcherng\Diff\Exception;

final class UnsupportedFunctionException extends \Exception
{
    public function __construct(string $funcName = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Unsupported function: {$funcName}", $code, $previous);
    }
}
