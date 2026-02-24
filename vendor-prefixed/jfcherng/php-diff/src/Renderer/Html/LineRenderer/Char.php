<?php
/**
 * @license BSD-3-Clause
 *
 * Modified by Pär Thernström on 24-February-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace Simple_History\Vendor\Jfcherng\Diff\Renderer\Html\LineRenderer;

use Simple_History\Vendor\Jfcherng\Diff\Renderer\RendererConstant;
use Simple_History\Vendor\Jfcherng\Diff\SequenceMatcher;
use Simple_History\Vendor\Jfcherng\Diff\Utility\ReverseIterator;
use Simple_History\Vendor\Jfcherng\Utility\MbString;

final class Char extends AbstractLineRenderer
{
    /**
     * @return static
     */
    public function render(MbString $mbOld, MbString $mbNew): LineRendererInterface
    {
        $hunk = $this->getChangedExtentSegments($mbOld->toArray(), $mbNew->toArray());

        // reversely iterate hunk
        foreach (ReverseIterator::fromArray($hunk) as [$op, $i1, $i2, $j1, $j2]) {
            if ($op & (SequenceMatcher::OP_REP | SequenceMatcher::OP_DEL)) {
                $mbOld->str_enclose_i(RendererConstant::HTML_CLOSURES, $i1, $i2 - $i1);
            }

            if ($op & (SequenceMatcher::OP_REP | SequenceMatcher::OP_INS)) {
                $mbNew->str_enclose_i(RendererConstant::HTML_CLOSURES, $j1, $j2 - $j1);
            }
        }

        return $this;
    }
}
