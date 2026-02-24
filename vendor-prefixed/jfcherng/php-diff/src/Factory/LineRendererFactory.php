<?php
/**
 * @license BSD-3-Clause
 *
 * Modified by Pär Thernström on 24-February-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace Simple_History\Vendor\Jfcherng\Diff\Factory;

use Simple_History\Vendor\Jfcherng\Diff\Renderer\Html\LineRenderer\AbstractLineRenderer;
use Simple_History\Vendor\Jfcherng\Diff\Renderer\RendererConstant;

final class LineRendererFactory
{
    /**
     * Instances of line renderers.
     *
     * @var AbstractLineRenderer[]
     */
    private static array $singletons = [];

    /**
     * The constructor.
     */
    private function __construct()
    {
    }

    /**
     * Get the singleton of a line renderer.
     *
     * @param string $type        the type
     * @param mixed  ...$ctorArgs the constructor arguments
     */
    public static function getInstance(string $type, ...$ctorArgs): AbstractLineRenderer
    {
        return self::$singletons[$type] ??= self::make($type, ...$ctorArgs);
    }

    /**
     * Make a new instance of a line renderer.
     *
     * @param string $type        the type
     * @param mixed  ...$ctorArgs the constructor arguments
     *
     * @throws \InvalidArgumentException
     */
    public static function make(string $type, ...$ctorArgs): AbstractLineRenderer
    {
        $className = RendererConstant::RENDERER_NAMESPACE . '\\Html\\LineRenderer\\' . ucfirst($type);

        if (!class_exists($className)) {
            throw new \InvalidArgumentException("LineRenderer not found: {$type}");
        }

        return new $className(...$ctorArgs);
    }
}
