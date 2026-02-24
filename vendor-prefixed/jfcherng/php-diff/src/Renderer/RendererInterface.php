<?php
/**
 * @license BSD-3-Clause
 *
 * Modified by Pär Thernström on 24-February-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace Simple_History\Vendor\Jfcherng\Diff\Renderer;

use Simple_History\Vendor\Jfcherng\Diff\Differ;
use Simple_History\Vendor\Jfcherng\Diff\Exception\UnsupportedFunctionException;

/**
 * Renderer Interface.
 */
interface RendererInterface
{
    /**
     * Get the renderer result when the old and the new are the same.
     */
    public function getResultForIdenticals(): string;

    /**
     * Render the differ and return the result.
     *
     * @param Differ $differ the Differ object to be rendered
     */
    public function render(Differ $differ): string;

    /**
     * Render the differ array and return the result.
     *
     * @param array[][] $differArray the Differ array to be rendered
     *
     * @throws UnsupportedFunctionException if the renderer does not support this method
     */
    public function renderArray(array $differArray): string;
}
