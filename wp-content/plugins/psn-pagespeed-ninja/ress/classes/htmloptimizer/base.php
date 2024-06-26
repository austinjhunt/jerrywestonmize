<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

abstract class Ressio_HtmlOptimizer_Base implements IRessio_HtmlOptimizer, IRessio_DIAware
{
    /** @var Ressio_DI */
    protected $di;
    /** @var Ressio_Config */
    protected $config;
    /** @var IRessio_Dispatcher */
    protected $dispatcher;
    /** @var Ressio_UrlRewriter $urlRewriter */
    protected $urlRewriter;

    /** @var int[] */
    public $tags_selfclose = array(
        'area' => 0, 'base' => 0, 'basefont' => 0, 'br' => 0, 'col' => 0,
        'command' => 0, 'embed' => 0, 'frame' => 0, 'hr' => 0, 'img' => 0,
        'input' => 0, 'ins' => 0, 'keygen' => 0, 'link' => 0, 'meta' => 0,
        'param' => 0, 'source' => 0, 'track' => 0, 'wbr' => 0
    );
    /** @var int[] */
    public $tags_nospaces = array(
        'html' => 0, 'head' => 0, 'body' => 0,
        'audio' => 0, 'canvas' => 0, 'embed' => 0, 'iframe' => 0, 'map' => 0,
        'object' => 0, 'ol' => 0, 'table' => 0, 'tbody' => 0, 'tfoot' => 0,
        'thead' => 0, 'tr' => 0, 'ul' => 0, 'video' => 0
    );
    /** @var int[] */
    public $tags_preservespaces = array(
        'code' => 0, 'pre' => 0, 'textarea' => 0
    );
    /** @var int[] */
    public $jsEvents = array(
        'onabort' => 0, 'onafterprint' => 0, 'onauxclick' => 0,
        'onbeforeinput' => 0, 'onbeforematch' => 0, 'onbeforeprint' => 0, 'onbeforeunload' => 0, 'onblur' => 0,
        'oncancel' => 0, 'oncanplay' => 0, 'oncanplaythrough' => 0, 'onchange' => 0, 'onclick' => 0, 'onclose' => 0,
        'oncontextlost' => 0, 'oncontextmenu' => 0, 'oncontextrestored' => 0, 'oncopy' => 0, 'oncuechange' => 0,
        'oncut' => 0,
        'ondblclick' => 0, 'ondrag' => 0, 'ondragend' => 0, 'ondragenter' => 0, 'ondragleave' => 0, 'ondragover' => 0,
        'ondragstart' => 0, 'ondrop' => 0, 'ondurationchange' => 0,
        'onemptied' => 0, 'onended' => 0, 'onerror' => 0,
        'onfocus' => 0, 'onformdata' => 0,
        'onhashchange' => 0,
        'oninput' => 0, 'oninvalid' => 0,
        'onkeydown' => 0, 'onkeypress' => 0, 'onkeyup' => 0,
        'onlanguagechange' => 0, 'onload' => 0, 'onloadeddata' => 0, 'onloadedmetadata' => 0, 'onloadstart' => 0,
        'onmessage' => 0, 'onmessageerror' => 0, 'onmousedown' => 0, 'onmouseenter' => 0, 'onmouseleave' => 0,
        'onmousemove' => 0, 'onmouseout' => 0, 'onmouseover' => 0, 'onmouseup' => 0, 'onmousewheel' => 0,
        'onoffline' => 0, 'ononline' => 0,
        'onpagehide' => 0, 'onpageshow' => 0, 'onpaste' => 0, 'onpause' => 0, 'onplay' => 0, 'onplaying' => 0,
        'onpopstate' => 0, 'onprogress' => 0,
        'onratechange' => 0, 'onreadystatechange' => 0, 'onrejectionhandled' => 0, 'onreset' => 0, 'onresize' => 0,
        'onscroll' => 0, 'onscrollend' => 0, 'onsecuritypolicyviolation' => 0, 'onseeked' => 0, 'onseeking' => 0,
        'onselect' => 0, 'onshow' => 0, 'onslotchange' => 0, 'onstalled' => 0, 'onstorage' => 0, 'onsubmit' => 0,
        'onsuspend' => 0,
        'ontimeupdate' => 0, 'ontoggle' => 0,
        'onunhandledrejection' => 0, 'onunload' => 0,
        'onvisibilitychange' => 0, 'onvolumechange' => 0,
        'onwaiting' => 0, 'onwebkitanimationend' => 0, 'onwebkitanimationiteration' => 0, 'onwebkitanimationstart' => 0,
        'onwebkittransitionend' => 0, 'onwheel' => 0
    );
    /** @var string[][] */
    public $uriAttrs = array(
        'a' => array('href'),
        'area' => array('href'),
        'audio' => array('src'),
        'embed' => array('src'),
        'form' => array('action'),
        'frame' => array('src'),
        'html' => array('manifest'),
        'iframe' => array('src'),
        'img' => array('src'),
        'input' => array('formaction', 'src'),
        'link' => array('href'),
        'object' => array('data'),
        'portal' => array('src'),
        'script' => array('src'),
        'source' => array('src'),
        'track' => array('src'),
        'video' => array('poster', 'src')
    );
    // Note: update it carefully
    /** @var int[][] */
    public $attrFirst = array(
        'a' => array('href' => 0),
        'div' => array('class' => 0, 'id' => 1),
        'iframe' => array('src' => 0),
        'img' => array('src' => 0),
        'input' => array('type' => 0, 'name' => 1),
        'label' => array('for' => 0),
        'link' => array('rel' => 0, 'type' => 1, 'href' => 2),
        'option' => array('value' => 0),
        'param' => array('type' => 0, 'name' => 1),
        'script' => array('type' => 0),
        'select' => array('name' => 0),
        'span' => array('class' => 0, 'id' => 1),
        'style' => array('type' => 0),
        'textarea' => array('cols' => 0, 'rows' => 1, 'name' => 2)
    );
    /** @var string[][] */
    public $defaultAttrsHtml4 = array(
        'area' => array(
            'shape' => 'rect'
        ),
        'button' => array(
            'type' => 'submit'
        ),
        'form' => array(
            'enctype' => 'application/x-www-form-urlencoded',
            'method' => 'get'
        ),
        'input' => array(
            'type' => 'text'
        )
    );
    /** @var string[][] */
    public $defaultAttrsHtml5 = array(
        'area' => array(
            'shape' => 'rect'
        ),
        'button' => array(
            'type' => 'submit'
        ),
        'command' => array(
            'type' => 'command'
        ),
        'form' => array(
            'autocomplete' => 'on',
            'enctype' => 'application/x-www-form-urlencoded',
            'method' => 'get'
        ),
        'input' => array(
            'type' => 'text'
        ),
        'marquee' => array(
            'behavior' => 'scroll',
            'direction' => 'left'
        ),
        'ol' => array(
            'type' => 'decimal'
        ),
        'script' => array(
            'type' => 'text/javascript'
        ),
        'style' => array(
            'type' => 'text/css'
        ),
        'td' => array(
            'colspan' => '1',
            'rowspan' => '1'
        ),
        'textarea' => array(
            'wrap' => 'soft'
        ),
        'th' => array(
            'colspan' => '1',
            'rowspan' => '1'
        ),
        'track' => array(
            'kind' => 'subtitles'
        )
    );
    /** @var string[] */
    public $htmlTags = array(
        '!doctype',
        'a', 'abbr', 'acronym', 'address', 'applet', 'area', 'article', 'aside', 'audio',
        'b', 'base', 'basefont', 'bdi', 'bdo', 'big', 'blockquote', 'body', 'br', 'button',
        'canvas', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'command',
        'data', 'datalist', 'dd', 'del', 'details', 'dfn', 'dialog', 'dir', 'div', 'dl', 'dt',
        'em', 'embed',
        'fieldset', 'figcaption', 'figure', 'font', 'footer', 'form', 'frame', 'frameset',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hgroup', 'hr', 'html',
        'i', 'iframe', 'img', 'input', 'ins',
        'kbd', 'keygen',
        'label', 'legend', 'li', 'link',
        'main', 'map', 'mark', 'math', 'menu', 'menuitem', 'meta', 'meter',
        'nav', 'nobr', 'noframes', 'noscript',
        'object', 'ol', 'optgroup', 'option', 'output',
        'p', 'param', 'picture', 'portal', 'pre', 'progress',
        'q',
        'rb', 'rp', 'rt', 'rtc', 'ruby',
        's', 'samp', 'script', 'search', 'section', 'select', 'slot', 'small', 'source', 'span', 'strike', 'strong', 'style', 'sub', 'summary', 'sup', 'svg',
        'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th', 'thead', 'time', 'title', 'tr', 'track', 'tt',
        'u', 'ul',
        'var', 'video',
        'wbr'
    );
    /** @var int[] */
    public $jsMime = array(
        'application/ecmascript' => 0,
        'application/javascript' => 0,
        'application/x-ecmascript' => 0,
        'application/x-javascript' => 0,
        'text/ecmascript' => 0,
        'text/javascript' => 0,
        'text/javascript1.0' => 0,
        'text/javascript1.1' => 0,
        'text/javascript1.2' => 0,
        'text/javascript1.3' => 0,
        'text/javascript1.4' => 0,
        'text/javascript1.5' => 0,
        'text/jscript' => 0,
        'text/livescript' => 0,
        'text/x-ecmascript' => 0,
        'text/x-javascript' => 0
    );

    const JS_MODE_MOVABLE = 1;
    const JS_MODE_ASYNC = 2;
    const JS_MODE_DEFER = 3;
    const JS_MODE_ALL = -1;

    /** @var int */
    public $doctype = self::DOCTYPE_HTML5;

    /** @var ?IRessio_HtmlNode */
    protected $lastJsNode;
    /** @var ?IRessio_HtmlNode */
    protected $lastDeferJsNode;
    /** @var ?IRessio_HtmlNode */
    protected $lastAsyncJsNode;
    /** @var bool */
    protected $breakJsNextNode;

    /** @var ?IRessio_HtmlNode */
    protected $lastCssNode;
    /** @var ?IRessio_HtmlNode */
    protected $lastAsyncCssNode;

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->di = $di;
        $this->config = $di->config;
        $this->dispatcher = $di->dispatcher;
        $this->urlRewriter = $di->urlRewriter;
    }

    /** @var array<string,int> */
    protected $cmpAttrFirst;

    /**
     * Comparison method to sort attributes for better gzip compression
     * @param string $attr1
     * @param string $attr2
     * @return int
     */
    protected function attrFirstCmp($attr1, $attr2)
    {
        $value1 = isset($this->cmpAttrFirst[$attr1]) ? $this->cmpAttrFirst[$attr1] : 1000;
        $value2 = isset($this->cmpAttrFirst[$attr2]) ? $this->cmpAttrFirst[$attr2] : 1000;
        return $value1 - $value2;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function isJavaScriptMime($type)
    {
        return isset($this->jsMime[$type]);
    }

    /**
     * Minify CSS
     * @param string $str
     * @param ?string $srcBase
     * @return string
     */
    protected function cssMinifyInline($str, $srcBase = null)
    {
        try {
            return $this->di->cssMinify->minifyInline($str, $srcBase);
        } catch (ERessio_InvalidCss $e) {
            $this->di->logger->warning('Catched error in ' . __METHOD__ . ': ' . $e->getMessage());
            return $str;
        }
    }

    /**
     * Minify JS
     * @param string $str
     * @return string
     */
    protected function jsMinifyInline($str)
    {
        try {
            return $this->di->jsMinify->minifyInline($str);
        } catch (ERessio_InvalidJs $e) {
            $this->di->logger->warning('Catched error in ' . __METHOD__ . ': ' . $e->getMessage());
            return $str;
        }
    }

    /**
     * Parse srcset attribute
     * @param string $srcset
     * @return string[]
     */
    public function parseSrcset($srcset)
    {
        $prev = strspn($srcset, "\n\r\t\f ");
        $result = array();
        while (preg_match('/\s/', $srcset, $matches, PREG_OFFSET_CAPTURE, $prev)) {
            $pos = $matches[0][1];
            $comma = strpos($srcset, ',', max(0, $pos - 1));
            if ($comma === false) {
                break;
            }
            $result[] = rtrim(substr($srcset, $prev, $comma - $prev));
            $prev = $comma + 1;
            $prev += strspn($srcset, "\n\r\t\f ", $prev);
        }
        $result[] = rtrim(substr($srcset, $prev));
        return $result;
    }

    /**
     * @param string $src
     * @return string
     */
    protected function imgSrcOptimize($src)
    {
        if ($src !== '' && strncmp($src, 'data:', 5) !== 0) {
            $src_file = $this->urlRewriter->urlToFilepath($src);
            if ($src_file !== null) {
                if ($this->config->var->imagenextgenformat && in_array(pathinfo($src_file, PATHINFO_EXTENSION), array('gif', 'jpg', 'png'))) {
                    $src_file_webp = $this->di->imgOptimizer->convert($src_file, $this->config->var->imagenextgenformat);
                    if ($src_file_webp !== $src_file && $src_file_webp !== false) {
                        $url = $this->urlRewriter->filepathToUrl($src_file_webp);
                        if ($url !== null) {
                            return $url;
                        }
                    }
                } else {
                    $src_file = $this->di->imgOptimizer->optimize($src_file);
                    if ($src_file !== false) {
                        $url = $this->urlRewriter->filepathToUrl($src_file);
                        if ($url !== null) {
                            return $url;
                        }
                    }
                }
            }
        }
        return $src;
    }

    /**
     * @param string $srcset
     * @return string
     */
    protected function imgSrcsetOptimize($srcset)
    {
        $srclist = $this->parseSrcset($srcset);
        foreach ($srclist as &$srcitem) {
            $split = preg_split('/\s+/', trim($srcitem), 2);
            $src = $split[0];
            if (strncmp($src, 'data:', 5) !== 0) {
                $params = isset($split[1]) ? $split[1] : null;
                if ($this->config->img->minify) {
                    $src_file = $this->urlRewriter->urlToFilepath($src);
                    if ($src_file !== null) {
                        if ($this->config->var->imagenextgenformat) {
                            $src_file_webp = $this->di->imgOptimizer->convert($src_file, $this->config->var->imagenextgenformat);
                            if ($src_file_webp !== $src_file && $src_file_webp !== false) {
                                $url = $this->urlRewriter->filepathToUrl($src_file_webp);
                                if ($url !== null) {
                                    $src = $url;
                                }
                            }
                        } else {
                            $src_file = $this->di->imgOptimizer->optimize($src_file);
                            if ($src_file !== false) {
                                $url = $this->urlRewriter->filepathToUrl($src_file);
                                if ($url !== null) {
                                    $src = $url;
                                }
                            }
                        }
                    }
                }
                if ($this->config->html->urlminify) {
                    $src = $this->urlRewriter->minify($src);
                }
                $srcitem = ($params === null) ? $src : "$src $params";
            }
        }
        return implode(', ', $srclist);
    }

    /**
     * @param string $src
     * @param string $src_optimized
     * @return ?string
     */
    protected function imgSrcsetGenerate($src, $src_optimized)
    {
        if (strncmp($src, 'data:', 5) === 0 || count($this->config->img->srcsetwidths) === 0) {
            return null;
        }

        $src_file = $this->urlRewriter->urlToFilepath($src);
        if ($src_file === null) {
            return null;
        }

        $widths = $this->config->img->srcsetwidths;
        $srcset = $this->di->imgOptimizer->rescaleBatch($src_file, $this->config->var->imagenextgenformat, $widths);
        if ($srcset === false) {
            return null;
        }

        $srclist = array();
        $count = count($widths);
        for ($i = 0; $i < $count; $i++) {
            if ($srcset[$i] !== false) {
                $url = $this->urlRewriter->filepathToUrl($srcset[$i]);
                if ($url !== null) {
                    $srclist[] = $url . ' ' . $widths[$i] . 'w';
                }
            }
        }

        if (count($srclist) === 0) {
            return null;
        }

        list($src_width, $src_height) = getimagesize($src_file);
        if ($src_width > 0) {
            $srclist[] = "{$src_optimized} {$src_width}w";
        }

        return implode(', ', $srclist);
    }

    /**
     * @param string $scriptBlob
     * @return string
     */
    protected function scriptCleanInlined($scriptBlob)
    {

        $scriptBlob = preg_replace(array('#^\s*<!--.*?[\r\n]+#', '#//\s*<!--.*$#m', '#//\s*-->.*$#m', '#\s*-->\s*$#'), '', $scriptBlob);
        $scriptBlob = preg_replace('#^\s*(?:(?://\s*)?<!\[CDATA\[|/\*\s*<!\[CDATA\[\s*\*/)\s*(.*?)\s*(?:(?://\s*)?\]\]>|/\*\s*\]\]>\s*\*/)\s*$#', '\1', $scriptBlob);
        return $scriptBlob;
    }

    /**
     * @param IRessio_HtmlNode $node
     * @param Ressio_ConfigExcludeRules $rule
     * @return bool
     */
    public function matchExcludeRule($node, $rule)
    {
        if (isset($rule->content)) {
            $content = $this->nodeGetInnerText($node);
            if (!empty($content) && preg_match($rule->content, $content)) {
                return true;
            }
        }
        if (isset($rule->attrs)) {
            foreach ($rule->attrs as $attr => $regex) {
                if ($node->hasAttribute($attr) && preg_match($regex, $node->getAttribute($attr))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param string $scriptBlob
     * @return bool
     */
    protected function isInitScript($scriptBlob)
    {
        if (preg_match('/^\s*(?:var|const|let)\s*\w+\s*=\s*(.*);?\s*$/', $scriptBlob, $match)) {
            $value = trim($match[1]);
            if (json_decode($value) !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,string|false> $attribs
     * @return int
     */
    protected function getJsMode($attribs)
    {
        if (isset($attribs['async'])) {
            return self::JS_MODE_ASYNC;
        }
        if (isset($attribs['defer'])) {
            return self::JS_MODE_DEFER;
        }
        return self::JS_MODE_MOVABLE;
    }

    /**
     * @param int $mode
     * @return IRessio_HtmlNode|null
     */
    protected function getJsListNode($mode)
    {
        switch ($mode) {
            case self::JS_MODE_MOVABLE:
                return $this->lastJsNode;
            case self::JS_MODE_DEFER:
                return $this->lastDeferJsNode;
            case self::JS_MODE_ASYNC:
                return $this->lastAsyncJsNode;
        }
        return null;
    }

    /**
     * @param int $mode
     * @param IRessio_HtmlNode|null $jsNode
     * @return void
     */
    protected function setJsListNode($mode, $jsNode)
    {
        switch ($mode) {
            case self::JS_MODE_MOVABLE:
                $this->lastJsNode = $jsNode;
                break;

            case self::JS_MODE_DEFER:
                $this->lastDeferJsNode = $jsNode;
                break;

            case self::JS_MODE_ASYNC:
                $this->lastAsyncJsNode = $jsNode;
                break;
        }
    }

    /**
     * @param string $url
     * @param array<string,string|false>|null $attribs
     * @param IRessio_HtmlNode|null $before
     * @return void
     */
    public function appendScript($url, $attribs = null, $before = null)
    {
        $mode = $this->getJsMode($attribs);
        $jsListNode = $this->getJsListNode($mode);
        if (!$jsListNode) {
            $jsListNode = $this->createJsListNode();
            $this->insertJsListNode($jsListNode, $before);
            $this->setJsListNode($mode, $jsListNode);
        }

        $this->appendJsList($jsListNode, array(
            'type' => 'ref',
            'src' => $url,
            'async' => isset($attribs['async']),
            'defer' => isset($attribs['defer'])
        ));
    }

    /**
     * @param string $content
     * @param array<string,string|false>|null $attribs
     * @param IRessio_HtmlNode|null $before
     * @return void
     */
    public function appendScriptDeclaration($content, $attribs = null, $before = null)
    {
        $mode = $this->getJsMode($attribs);
        $jsListNode = $this->getJsListNode($mode);
        if (!$jsListNode) {
            $jsListNode = $this->createJsListNode();
            $this->insertJsListNode($jsListNode, $before);
            $this->setJsListNode($mode, $jsListNode);
        }

        $this->appendJsList($jsListNode, array(
            'type' => 'inline',
            'script' => $content,
            'async' => isset($attribs['async']),
            'defer' => isset($attribs['defer'])
        ));
    }

    /**
     * @return IRessio_HtmlNode
     */
    abstract protected function createJsListNode();

    /**
     * @param IRessio_HtmlNode $jsListNode
     * @param IRessio_HtmlNode|null $before
     * @return void
     */
    abstract protected function insertJsListNode($jsListNode, $before);

    /**
     * @param IRessio_HtmlNode $jsListNode
     * @param array $data
     * @return void
     */
    abstract protected function appendJsList($jsListNode, $data);

    /**
     * @param string $url
     * @param array<string,string|false>|null $attribs
     * @param IRessio_HtmlNode|null $before
     * @return void
     */
    public function appendStylesheet($url, $attribs = null, $before = null)
    {
        $cssListNode = $this->lastAsyncCssNode;
        if (!$cssListNode) {
            $cssListNode = $this->createCssListNode();
            $this->insertCssListNode($cssListNode, $before);
            $this->lastCssNode = $this->lastAsyncCssNode = $cssListNode;
        }

        $this->appendCssList($cssListNode, array(
            'type' => 'ref',
            'src' => $url,
            'media' => isset($attribs['media']) ? $attribs['media'] : 'all'
        ));
    }

    /**
     * @param string $content
     * @param array<string,string|false>|null $attribs
     * @param IRessio_HtmlNode|null $before
     * @return void
     */
    public function appendStyleDeclaration($content, $attribs = null, $before = null)
    {
        $cssListNode = $this->lastAsyncCssNode;
        if (!$cssListNode) {
            $cssListNode = $this->createCssListNode();
            $this->insertCssListNode($cssListNode, $before);
            $this->lastCssNode = $this->lastAsyncCssNode = $cssListNode;
        }

        $this->appendCssList($cssListNode, array(
            'type' => 'inline',
            'style' => $content,
            'media' => isset($attribs['media']) ? $attribs['media'] : 'all'
        ));
    }

    /**
     * @return IRessio_HtmlNode
     */
    abstract protected function createCssListNode();

    /**
     * @param IRessio_HtmlNode $cssListNode
     * @param IRessio_HtmlNode|null $before
     * @return void
     */
    abstract protected function insertCssListNode($cssListNode, $before);

    /**
     * @param IRessio_HtmlNode $cssListNode
     * @param array $data
     * @return void
     */
    abstract protected function appendCssList($cssListNode, $data);

    /**
     * @param int $mode
     * @return void
     */
    protected function breakJs($mode = self::JS_MODE_MOVABLE)
    {
        switch ($mode) {
            case self::JS_MODE_MOVABLE:
                $this->lastJsNode = null;
                break;
            case self::JS_MODE_ASYNC:
                $this->lastAsyncJsNode = null;
                break;
            case self::JS_MODE_DEFER:
                $this->lastDeferJsNode = null;
                break;
            case self::JS_MODE_ALL:
                $this->lastJsNode = null;
                $this->lastDeferJsNode = null;
                $this->lastAsyncJsNode = null;
                break;
        }
        $this->breakJsNextNode = false;
    }

    /** #return void */
    protected function breakCss()
    {
        $this->lastCssNode = null;
    }

    /**
     * @param IRessio_HtmlNode $node
     * @param bool $inline
     * @return void
     */
    abstract protected function addJs($node, $inline = false);

    /**
     * @param IRessio_HtmlNode $node
     * @param bool $inline
     * @return void
     */
    abstract protected function addCss($node, $inline = false);
}
