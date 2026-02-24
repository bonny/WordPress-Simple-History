<?php
/**
 * @license BSD-3-Clause
 *
 * Modified by Pär Thernström on 24-February-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace Simple_History\Vendor\Jfcherng\Diff\Renderer\Html;

use Simple_History\Vendor\Jfcherng\Diff\SequenceMatcher;

/**
 * HTML Json diff generator.
 */
class JsonHtml extends AbstractHtml
{
    /**
     * {@inheritdoc}
     */
    public const INFO = [
        'desc' => 'HTML Json',
        'type' => 'Html',
    ];

    /**
     * {@inheritdoc}
     */
    public const IS_TEXT_RENDERER = true;

    public function getResultForIdenticalsDefault(): string
    {
        return '[]';
    }

    protected function redererChanges(array $changes): string
    {
        if ($this->options['outputTagAsString']) {
            $this->convertTagToString($changes);
        }

        return json_encode($changes, $this->options['jsonEncodeFlags']);
    }

    /**
     * Convert tags of changes to their string form for better readability.
     *
     * @param array[][] $changes the changes
     */
    protected function convertTagToString(array &$changes): void
    {
        foreach ($changes as &$hunks) {
            foreach ($hunks as &$block) {
                $block['tag'] = SequenceMatcher::opIntToStr($block['tag']);
            }
        }
    }

    protected function formatStringFromLines(string $string): string
    {
        return $this->htmlSafe($string);
    }
}
