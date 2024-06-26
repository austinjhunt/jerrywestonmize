<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Plugin_FontDisplaySwap extends Ressio_Plugin
{
    /** @var bool[] */
    private $excludedFonts;

    /**
     * @param Ressio_DI $di
     * @param ?stdClass $params
     */
    public function __construct($di, $params = null)
    {
        parent::__construct($di);
        $this->loadConfig(__DIR__ . '/config.json', $params);

        $this->excludedFonts = array_fill_keys($this->params->excludedFonts, true);
    }

    /**
     * @param Ressio_Event $event
     * @param string $buffer
     * @return void
     */
    public function onCssRelocatorAfter($event, &$buffer)
    {
        if (strpos($buffer, '@font-face') === false) {
            return;
        }

        $buffer = preg_replace_callback('/(@font-face\s*\{)(.*?)(?=})/s', array($this, 'replace_callback'), $buffer);
    }

    /**
     * @param string[] $fontface_rule_matches
     * @return string
     */
    public function replace_callback($fontface_rule_matches)
    {
        $fontface_rule = $fontface_rule_matches[2];
        if (preg_match('/\bfont-family:\s*("[^"]*"|\'[^\']*\'|\w+)/', $fontface_rule, $match)) {
            $fontFamily = trim($match[1], " \t\n\r\0\x0B'\"");
            if (!isset($this->excludedFonts[$fontFamily])) {
                return $fontface_rule_matches[1] . 'font-display:' . $this->params->display . ';' . $fontface_rule;
            }
        }
        return $fontface_rule_matches[0];
    }
}