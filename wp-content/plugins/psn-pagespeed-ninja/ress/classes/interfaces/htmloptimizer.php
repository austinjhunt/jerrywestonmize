<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_HtmlOptimizer
{
    const DOCTYPE_HTML4 = 1;
    const DOCTYPE_HTML5 = 2;
    const DOCTYPE_XHTML = 3;

    /**
     * @param string $buffer
     * @return string
     */
    public function run($buffer);

    /**
     * @pure
     * @return string
     */
    public function getQueryDelim();

    /**
     * @param string $url
     * @param array|null $attribs
     * @return void
     */
    public function appendScript($url, $attribs = null);

    /**
     * @param string $content
     * @param array|null $attribs
     * @param object|null $before
     * @return void
     */
    public function appendScriptDeclaration($content, $attribs = null, $before = null);

    /**
     * @param string $url
     * @param array|null $attribs
     * @return void
     */
    public function appendStylesheet($url, $attribs = null);

    /**
     * @param string $content
     * @param array|null $attribs
     * @return void
     */
    public function appendStyleDeclaration($content, $attribs = null);

    /**
     * @param IRessio_HtmlNode $node
     * @return string
     */
    public function nodeToString($node);

    /**
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function nodeDetach($node);

    /**
     * @param IRessio_HtmlNode $node
     * @return bool
     */
    public function nodeIsDetached($node);

    /**
     * @param IRessio_HtmlNode $node
     * @return bool
     */
    public function nodeIsElement($node);

    /**
     * @param IRessio_HtmlNode $node
     * @param string $text
     * @return void
     */
    public function nodeSetInnerText($node, $text);

    /**
     * @param IRessio_HtmlNode $node
     * @return string
     */
    public function nodeGetInnerText($node);

    /**
     * @param IRessio_HtmlNode $node
     * @param string $tag
     * @param array $attribs
     * @return void
     */
    public function nodeWrap($node, $tag, $attribs = null);

    /**
     * @param IRessio_HtmlNode $node
     * @param string $tag
     * @param array $attribs
     * @param string $content
     * @return void
     */
    public function nodeInsertBefore($node, $tag, $attribs = null, $content = null);

    /**
     * @param IRessio_HtmlNode $node
     * @param string $tag
     * @param array $attribs
     * @param string $content
     * @return void
     */
    public function nodeInsertAfter($node, $tag, $attribs = null, $content = null);

    /**
     * @param array (string $tag, array $attribs, string $content) ...$nodedata
     * @return bool return false if no <head> found
     */
    public function prependHead($nodedata);

    /**
     * @return bool
     */
    public function isNoscriptState();

    /**
     * @return bool
     */
    public function isPictureState();

    /**
     * @param string $type
     * @return bool
     */
    public function isJavaScriptMime($type);

    /**
     * @param string $srcset
     * @return string[]
     */
    public function parseSrcset($srcset);

    /**
     * @param IRessio_HtmlNode $node
     * @param Ressio_ConfigExcludeRules $rule
     * @return bool
     */
    public function matchExcludeRule($node, $rule);
}
