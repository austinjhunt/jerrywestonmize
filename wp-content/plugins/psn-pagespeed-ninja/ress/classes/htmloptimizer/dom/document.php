<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_HtmlOptimizer_Dom_Document extends DOMDocument
{
    /** @var string */
    protected $htmlPrefix = '';

    /** @var int */
    protected $tagsSavedContentId;

    /** @var string[] */
    protected $tagsSavedContent;

    /**
     * @param string $version
     * @param string $encoding
     */
    public function __construct($version = '1.0', $encoding = '')
    {
        parent::__construct($version, $encoding);
        $this->registerNodeClass('DOMElement', Ressio_HtmlOptimizer_Dom_Element::class);
        $this->registerNodeClass('DOMComment', Ressio_HtmlOptimizer_Dom_Comment::class);
        $this->registerNodeClass('DOMText', Ressio_HtmlOptimizer_Dom_Text::class);
        $this->registerNodeClass('DOMCdataSection', Ressio_HtmlOptimizer_Dom_CdataSection::class);
        $this->registerNodeClass('DOMAttr', Ressio_HtmlOptimizer_Dom_Attr::class);
    }

    /**
     * @param string $name
     * @param string $publicId
     * @param string $systemId
     */
    public function addDoctype($name, $publicId = '', $systemId = '')
    {
        if ($this->doctype) {
            $this->removeChild($this->doctype);
        }
        $this->htmlPrefix = '<!DOCTYPE ' . $name . ($publicId ? ' ' . $publicId : '') . ($systemId ? ' ' . $systemId : '') . ">\n";
    }

    /**
     * @param string|Ressio_HtmlOptimizer_Dom_Element $tag
     * @return Ressio_HtmlOptimizer_Dom_Element
     */
    public function addChild($tag)
    {
        $tag = is_object($tag) ? $this->importNode($tag, true) : $this->createElement($tag);
        $this->appendChild($tag);
        return $tag;
    }

    /**
     * @param string $source
     * @param int $options
     * @return bool
     */
    #[ReturnTypeWillChange]
    public function loadHTML($source, $options = 0)
    {
        // fix non-utf-8 characters
        $source = preg_replace('#(?<=[\x00-\x7F]|[\xC0-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF]{2}|[\xF0-\xF7][\x80-\xBF]{3})[\x80-\xBF]#S', "\xC0\\0", $source);

        // keep IE's <![if ...]> and <![endif]> by converting to comments
        $source = preg_replace('#<(!\[(?:if\s.*?|endif)\])>#is', '<!--!RESS\1-->', $source);

        $source = $this->saveTags($source);

        // fix html5 self-closing tags
        $source = preg_replace('#<((command|keygen|source|track|wbr)\b[^>]*)/?>(?:</\2>)?#i', '<\1></\2>', $source);

        $xml_errors = libxml_use_internal_errors(true);
        $status = parent::loadHTML('<?xml encoding="utf-8" ?\>' . $source, $options | LIBXML_HTML_NODEFDTD | LIBXML_NONET | LIBXML_COMPACT);

        libxml_use_internal_errors($xml_errors);

        // remove <?xml node
        foreach ($this->childNodes as $item) {
            if ($item->nodeType === XML_PI_NODE) {
                $this->removeChild($item);
                break;
            }
        }

        $this->restoreTagsDOM();

        return $status;
    }

    /**
     * @param DOMNode $node
     * @return string
     * @requires PHP5.3.6+ (to avoid "saveHTML() expects exactly 0 parameters" error)
     */
    #[ReturnTypeWillChange]
    public function saveHTML($node = null)
    {
        // convert <noscript>'s text content to subtree
        if ($node !== null) {
            $html = parent::saveHTML($node);
        } else {
            $noscripts = $this->getElementsByTagName('noscript');
            if ($noscripts->length) {
                $tmpDoc = new DOMDocument();
                foreach ($noscripts as $noscript) {
                    if ($noscript->childElementCount === 0 && $noscript->textContent !== '') {
                        $tmpDoc->loadHTML("<noscript>{$noscript->textContent}</noscript>");
                        $noscript->parentNode->replaceChild(
                            $this->importNode($tmpDoc->getElementsByTagName('noscript')->item(0), true),
                            $noscript
                        );
                    }
                }
            }

            $html = $this->htmlPrefix;
            if ($this->doctype) {
                $html .= '<!DOCTYPE ' . $this->doctype->name .
                    ($this->doctype->publicId ? ' ' . $this->doctype->publicId : '') .
                    ($this->doctype->systemId ? ' ' . $this->doctype->systemId : '') .
                    ">\n";
            }
            $html .= parent::saveHTML($this->documentElement);
        }

        $html = $this->saveTags($html);

        // fix self-closing tags
        $html = str_replace(array(
            '></command>',
            '></keygen>',
            '></source>',
            '></track>',
            '></wbr>',
        ), '>', $html);

        // restore conditional comments
        $html = rtrim(preg_replace('#<!--!RESS(!.*?)-->#s', '<\1>', $html));

        // fix boolean attributes (supported by libxml: checked, defer, disabled, ismap, multiple, readonly, selected)
        $html = preg_replace('#(?<=\s)(' .
            'allowfullscreen|allowpaymentrequest|async|autofocus|autoplay|controls|crossorigin|default|formnovalidate|' .
            'hidden|itemscope|loop|muted|nomodule|novalidate|open|playsinline|required|reversed|scoped|truespeed' .
            ')="(?:\1)?"(?=[\s/>])#', '\1', $html);

        $html = $this->restoreTagsHTML($html);

        return $html;
    }

    /**
     * @param string $source
     * @return string
     */
    protected function saveTags($source)
    {
        // save script and style tags content
        $this->tagsSavedContentId = 0;
        $this->tagsSavedContent = array();
        $source = preg_replace_callback('/(<(script|style)\b[^>]*+>)(.*?)<\/\2\b[^>]*+>|<!--.*?-->/is', array($this, 'saveTagContent'), $source);
        return $source;
    }

    /**
     * @param string[] $matches
     * @return string
     */
    protected function saveTagContent($matches)
    {
        if (isset($matches[3]) && $matches[3] !== '') {
            $id = (string)(++$this->tagsSavedContentId);
            $this->tagsSavedContent[$id] = $matches[3];
            return $matches[1] . $id . '</' . $matches[2] . '>';
        }
        return $matches[0];
    }

    /** @return void */
    protected function restoreTagsDOM()
    {
        // restore script and style tags content
        foreach ($this->getElementsByTagName('script') as $script) {
            $id = $script->textContent;
            if (isset($this->tagsSavedContent[$id])) {
                $script->textContent = $this->tagsSavedContent[$id];
            }
        }

        foreach ($this->getElementsByTagName('style') as $style) {
            $id = $style->textContent;
            if (isset($this->tagsSavedContent[$id])) {
                $style->textContent = $this->tagsSavedContent[$id];
            }
        }

        // free memory
        $this->tagsSavedContent = null;
    }

    /**
     * @param string $source
     * @return string
     */
    protected function restoreTagsHTML($source)
    {
        $source = preg_replace_callback('/(<(script|style)\b[^>]*+>)(.*?)<\/\2\b[^>]*+>|<!--.*?-->/is', array($this, 'getTagContent'), $source);
        // free memory
        $this->tagsSavedContent = null;
        return $source;
    }

    /**
     * @param string[] $matches
     * @return string
     */
    protected function getTagContent($matches)
    {
        if (isset($matches[3]) && $matches[3] !== '') {
            $id = (int)$matches[3];
            return $matches[1] . $this->tagsSavedContent[$id] . '</' . $matches[2] . '>';
        }
        return $matches[0];
    }
}
