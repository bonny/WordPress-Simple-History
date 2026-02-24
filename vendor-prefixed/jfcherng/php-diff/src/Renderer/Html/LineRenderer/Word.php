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
use Simple_History\Vendor\Jfcherng\Diff\Utility\Str;
use Simple_History\Vendor\Jfcherng\Utility\MbString;

final class Word extends AbstractLineRenderer
{
    /**
     * @return static
     */
    public function render(MbString $mbOld, MbString $mbNew): LineRendererInterface
    {
        static $splitRegex = '/([' . RendererConstant::PUNCTUATIONS_RANGE . '])/uS';
        static $dummyHtmlClosure = RendererConstant::HTML_CLOSURES[0] . RendererConstant::HTML_CLOSURES[1];

        $pregFlag = \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY;
        $oldWords = $mbOld->toArraySplit($splitRegex, -1, $pregFlag);
        $newWords = $mbNew->toArraySplit($splitRegex, -1, $pregFlag);

        $hunk = $this->getChangedExtentSegments($oldWords, $newWords);

        // reversely iterate hunk
        foreach (ReverseIterator::fromArray($hunk) as [$op, $i1, $i2, $j1, $j2]) {
            if ($op & (SequenceMatcher::OP_REP | SequenceMatcher::OP_DEL)) {
                $oldWords[$i1] = RendererConstant::HTML_CLOSURES[0] . $oldWords[$i1];
                $oldWords[$i2 - 1] .= RendererConstant::HTML_CLOSURES[1];

                // insert dummy HTML closure to ensure there are always
                // the same amounts of HTML closures in $oldWords and $newWords
                // thus, this should make that "wordGlues" work correctly
                // @see https://github.com/jfcherng/php-diff/pull/25
                if ($op === SequenceMatcher::OP_DEL) {
                    array_splice($newWords, $j1, 0, [$dummyHtmlClosure]);
                }
            }

            if ($op & (SequenceMatcher::OP_REP | SequenceMatcher::OP_INS)) {
                $newWords[$j1] = RendererConstant::HTML_CLOSURES[0] . $newWords[$j1];
                $newWords[$j2 - 1] .= RendererConstant::HTML_CLOSURES[1];

                if ($op === SequenceMatcher::OP_INS) {
                    array_splice($oldWords, $i1, 0, [$dummyHtmlClosure]);
                }
            }
        }

        if (!empty($hunk) && !empty($this->rendererOptions['wordGlues'])) {
            $regexGlues = array_map(
                static fn (string $glue): string => preg_quote($glue, '/'),
                $this->rendererOptions['wordGlues'],
            );

            $gluePattern = '/^(?:' . implode('|', $regexGlues) . ')+$/uS';

            $this->glueWordsResult($oldWords, $gluePattern);
            $this->glueWordsResult($newWords, $gluePattern);
        }

        $mbOld->set(implode('', $oldWords));
        $mbNew->set(implode('', $newWords));

        return $this;
    }

    /**
     * Beautify diff result by glueing words.
     *
     * What this function does is basically making
     *     ["<diff_begin>good<diff_end>", "-", "<diff_begin>looking<diff_end>"]
     * into
     *     ["<diff_begin>good", "-", "looking<diff_end>"].
     *
     * @param array  $words       the words
     * @param string $gluePattern the regex to determine a string is purely glue or not
     */
    protected function glueWordsResult(array &$words, string $gluePattern): void
    {
        /** @var int index of the word which has the trailing closure */
        $endClosureIdx = -1;

        foreach ($words as $idx => &$word) {
            if ($word === '') {
                continue;
            }

            if ($endClosureIdx < 0) {
                if (Str::endsWith($word, RendererConstant::HTML_CLOSURES[1])) {
                    $endClosureIdx = $idx;
                }
            } elseif (Str::startsWith($word, RendererConstant::HTML_CLOSURES[0])) {
                $words[$endClosureIdx] = substr($words[$endClosureIdx], 0, -\strlen(RendererConstant::HTML_CLOSURES[1]));
                $word = substr($word, \strlen(RendererConstant::HTML_CLOSURES[0]));
                $endClosureIdx = $idx;
            } elseif (!preg_match($gluePattern, $word)) {
                $endClosureIdx = -1;
            }
        }
    }
}
