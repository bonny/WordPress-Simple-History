<?php
/**
 * @license BSD-3-Clause
 *
 * Modified by Pär Thernström on 24-February-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace Simple_History\Vendor\Jfcherng\Diff\Renderer\Html\LineRenderer;

use Simple_History\Vendor\Jfcherng\Utility\MbString;

interface LineRendererInterface
{
    /**
     * Renderer the in-line changed extent.
     *
     * @param MbString $mbOld the old megabytes line
     * @param MbString $mbNew the new megabytes line
     *
     * @return static
     */
    public function render(MbString $mbOld, MbString $mbNew): self;
}
