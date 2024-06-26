<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_HtmlOptimizer_Dom extends Ressio_HtmlOptimizer_Base
{
    /** @var string */
    public $origDoctype;

    /** @var Ressio_HtmlOptimizer_Dom_Document */
    public $dom;

    /** @var DOMNode|null */
    private $cursorNode;
    /** @var DOMNode|null */
    private $prevCursorNode;

    /** @var bool */
    private $baseFound = false;

    /** @var Ressio_HtmlOptimizer_Dom_Element|null */
    protected $lastJsNode;
    /** @var Ressio_HtmlOptimizer_Dom_Element|null */
    protected $lastDeferJsNode;
    /** @var Ressio_HtmlOptimizer_Dom_Element|null */
    protected $lastAsyncJsNode;

    /** @var Ressio_HtmlOptimizer_Dom_Element|null */
    protected $lastCssNode;
    /** @var Ressio_HtmlOptimizer_Dom_Element|null */
    protected $lastAsyncCssNode;

    /** @var int */
    public $noscriptCounter = 0;
    /** @var int */
    public $pictureCounter = 0;

    /** @var bool */
    public $headMode;

    /** @var array */
    private $jscssLists = array();
    /** @var int */
    private $jscssIndex = -1;

    private $prependHeadNode;
    private $prependHeadRefNode;

    /**
     * @pure
     * @return string
     */
    public function getQueryDelim()
    {
        return '&';
    }

    /**
     * @param string $buffer
     * @return string
     * @throws ERessio_UnknownDiKey
     */
    public function run($buffer)
    {


        // parse html
        $dom = new Ressio_HtmlOptimizer_Dom_Document();
        $dom->loadHTML($buffer);

        $this->dom = $dom;

        $this->lastJsNode = $this->lastDeferJsNode = $this->lastAsyncJsNode = null;
        $this->lastCssNode = $this->lastAsyncCssNode = null;

        $this->breakJsNextNode = false;

        $this->headMode = true;

        $this->dispatcher->triggerEvent('HtmlIterateBefore', array($this));

        $this->domIterate($dom, $this->config->html->mergespace);

        if ($this->origDoctype === null && $this->config->html->forcehtml5) {
            $dom->addDoctype('html');
        }

        $this->dispatcher->triggerEvent('HtmlIterateAfter', array($this));

        $nodes = $dom->getElementsByTagName('ressscript');
        if ($nodes->length) {
            $this->injectCombinedNodes($nodes, $this->di->jsCombiner);
        }

        $nodes = $dom->getElementsByTagName('resscss');
        if ($nodes->length) {
            $this->injectCombinedNodes($nodes, $this->di->cssCombiner);
        }

        $this->dispatcher->triggerEvent('HtmlBeforeStringify', array($this));

        $this->cursorNode = null;
        $this->prevCursorNode = null;

        $buffer = $dom->saveHTML();

        $this->dom = null;
        $this->lastJsNode = $this->lastDeferJsNode = $this->lastAsyncJsNode = null;
        $this->lastCssNode = $this->lastAsyncCssNode = null;
        $this->jscssLists = array();
        $this->jscssIndex = -1;

        return $buffer;
    }

    /**
     * @param DOMNodeList $nodes
     * @param IRessio_CssCombiner|IRessio_JsCombiner $combiner
     * @return void
     */
    private function injectCombinedNodes($nodes, $combiner)
    {
        $removeList = array();
        foreach ($nodes as $node) {
            /** @var DOMElement $node */
            $index = (int)$node->getAttribute('index');
            $nodeList = $combiner->combineToNodes($this->jscssLists[$index]);
            /** @var Ressio_HtmlOptimizer_Dom_Element $parent */
            $parent = $node->parentNode;

            // DOMDocument unables to correctly parse inlined content in $combiner->nodesToHtml($nodeList);
            foreach ($nodeList as $wrapper) {
                $child = $this->dom->createElement($wrapper->tagName);
                foreach ($wrapper->attributes as $key => $value) {
                    $child->setAttribute($key, $value);
                }
                if ($wrapper->content !== null) {
                    $child->appendChild($this->dom->createTextNode($wrapper->content));
                }
                $parent->insertBefore($this->dom->importNode($child, true), $node);
            }

            // convert iterator (getElementsByTagName) to actual array
            $removeList[] = $node;
        }
        foreach ($removeList as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * @return Ressio_HtmlOptimizer_Dom_Element
     */
    protected function createJsListNode()
    {
        /** @var Ressio_HtmlOptimizer_Dom_Element $jsListNode */
        $jsListNode = $this->dom->createElement('ressscript');
        $index = ++$this->jscssIndex;
        $jsListNode->setAttribute('index', (string)$index);
        $this->jscssLists[$index] = array();
        return $jsListNode;
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $jsListNode
     * @param Ressio_HtmlOptimizer_Dom_Element|null $before
     */
    protected function insertJsListNode($jsListNode, $before)
    {
        $this->insertJsCssListNode($jsListNode, $before);
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $jsListNode
     * @param array $data
     */
    protected function appendJsList($jsListNode, $data)
    {
        $this->appendJsCssList($jsListNode, $data);
    }

    /**
     * @return Ressio_HtmlOptimizer_Dom_Element
     */
    protected function createCssListNode()
    {
        /** @var Ressio_HtmlOptimizer_Dom_Element $cssListNode */
        $cssListNode = $this->dom->createElement('resscss');
        $index = ++$this->jscssIndex;
        $cssListNode->setAttribute('index', (string)$index);
        $this->jscssLists[$index] = array();
        return $cssListNode;
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $cssListNode
     * @param Ressio_HtmlOptimizer_Dom_Element|null $before
     */
    protected function insertCssListNode($cssListNode, $before)
    {
        $this->insertJsCssListNode($cssListNode, $before);
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $cssListNode
     * @param array $data
     */
    protected function appendCssList($cssListNode, $data)
    {
        $this->appendJsCssList($cssListNode, $data);
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $jscssListNode
     * @param Ressio_HtmlOptimizer_Dom_Element|null $before
     */
    private function insertJsCssListNode($jscssListNode, $before)
    {
        if ($before !== null) {
            $before->parentNode->insertBefore($jscssListNode, $before);
        } elseif (isset($this->cursorNode->parentNode->parentNode->parentNode)) {
            // document > html > (head|body) > cursor
            $this->cursorNode->parentNode->insertBefore($jscssListNode, $this->cursorNode);
        } elseif ($this->prevCursorNode) {
            $node = $this->prevCursorNode;
            while ($node !== null && !($node instanceof DOMElement)) {
                $node = $node->parentNode;
            }
            $node->parentNode->insertBefore($jscssListNode, $node->nextSibling);
        } else {
            $node = $this->dom->lastChild; // html
            if (isset($node->lastChild)) {
                $node = $node->lastChild; // body
            }
            $node->appendChild($jscssListNode);
        }
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $jscssListNode
     * @param array $data
     */
    private function appendJsCssList($jscssListNode, $data)
    {
        $index = (int)$jscssListNode->getAttribute('index');
        $this->jscssLists[$index][] = $data;
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element|Ressio_HtmlOptimizer_Dom_Document $node
     * @param bool $mergeSpace
     * @return bool
     * @throws ERessio_UnknownDiKey
     */
    protected function domProcess($node, $mergeSpace)
    {

        $config = $this->config;

        // doctype
        if ($node instanceof DOMDocumentType) {
            $this->origDoctype = $node->name . ($node->publicId ? ' ' . $node->publicId : '') . ($node->systemId ? ' ' . $node->systemId : '');
            if ($config->html->forcehtml5) {
                if ($this->origDoctype !== 'html') {
                    $this->dom->addDoctype('html');
                }
            } elseif (strpos($node->name, 'DTD HTML') !== false) {
                $this->doctype = self::DOCTYPE_HTML4;
            } elseif (strpos($node->name, 'DTD XHTML') !== false) {
                $this->doctype = self::DOCTYPE_XHTML;
            }
            return false;
        }

        $isCDATASection = $node instanceof Ressio_HtmlOptimizer_Dom_CdataSection;
        // CDATA is text in xhtml and comment in html
        if (($node instanceof Ressio_HtmlOptimizer_Dom_Text && !$isCDATASection) ||
            ($this->doctype === self::DOCTYPE_XHTML && $isCDATASection)
        ) {
            /** @var Ressio_HtmlOptimizer_Dom_Text $node */
            if ($mergeSpace) {
                $node->textContent = preg_replace('/(?<= ) +/', '', strtr($node->textContent, "\n\r\t\f", '    '));
                if ($node->textContent === ' ' && isset($this->tags_nospaces[$node->parentNode->nodeName])) {
                    $this->nodeDetach($node);
                }
            }
            return false;
        }

        // remove comments
        if ($node instanceof Ressio_HtmlOptimizer_Dom_Comment ||
            ($this->doctype !== self::DOCTYPE_XHTML && $isCDATASection)
        ) {
            /** @var Ressio_HtmlOptimizer_Dom_Comment $node */
            if ($config->html->removecomments) {
                if ($node->textContent === '' || ($node->textContent[0] !== '!' &&
                        strncmp($node->textContent, '[if ', 4) !== 0 &&
                        strncmp($node->textContent, '[endif]', 7) !== 0 && strncmp($node->textContent, '<![endif]', 9) !== 0 &&
                        strncmp($node->textContent, '!RESS![if ', 10) !== 0 && strncmp($node->textContent, '!RESS![endif]', 13) !== 0)
                ) {
                    $this->nodeDetach($node);
                } else {
                    // check comments (keep IE ones on IE, [if, <![ : <!--[if IE]>, <!--<![endif]--> )
                    // stop css/style combining in IE cond block
                    if ($config->html->removeiecond) {
                        $vendor = $this->di->deviceDetector->vendor();
                        if ($vendor !== 'ms' && $vendor !== 'unknown') { // if not IE browser
                            $this->nodeDetach($node);
                            return false;
                        }
                    }
                    $this->breakCss();
                    $this->breakJs(self::JS_MODE_ALL);
                    if ($mergeSpace) {
                        $inner = $node->textContent;
                        $inner = preg_replace('#\s+<!--$#', '<!--', ltrim($inner));
                        $node->textContent = $inner;
                    }
                }
            }
            return false;
        }

        if ($config->html->rules_safe_exclude && $this->matchExcludeRule($node, $config->html->rules_safe_exclude)) {
            return false;
        }


        /** @var Ressio_HtmlOptimizer_Dom_Element $node */

        $iterateChildren = !isset($this->tags_selfclose[$node->nodeName]);

        $tagName = strtoupper($node->nodeName);
        $this->dispatcher->triggerEvent("HtmlIterateTag{$tagName}Before", array($this, $node));
        if ($node->parentNode === null) {
            return false;
        }

        switch ($node->nodeName) {
            case 'a':
            case 'area':
                if ($config->js->minifyattribute && $node->hasAttribute('href')) {
                    $uri = $node->getAttribute('href');
                    if (strncmp($uri, 'javascript:', 11) === 0) {
                        $node->setAttribute('href', 'javascript:' . $this->jsMinifyInline(substr($uri, 11)));
                    }
                }
                break;

            case 'base':
                // save base href (use first tag only)
                if (!$this->baseFound && $node->hasAttribute('href')) {
                    $base = $node->getAttribute('href');
                    if (substr($base, -1) !== '/') {
                        $base = dirname($base);
                        if ($base === '.') {
                            $base = '';
                        }
                        $base .= '/';
                    }
                    $this->urlRewriter->setBase($base);
                    $node->setAttribute('href', $this->urlRewriter->getBase());
                    $this->baseFound = true;
                }
                break;

            case 'body':
                $this->headMode = false;
                // set css break point to preserve css files order after dynamically adding styles to head using js
                if (!$config->css->mergeheadbody) {
                    $this->breakCss();
                }
                if (!$config->js->mergeheadbody) {
                    $this->breakJs(self::JS_MODE_ALL);
                }
                break;

            case 'img':
                if ($this->noscriptCounter) {
                    break;
                }
                if ($config->img->rules_minify_exclude && $this->matchExcludeRule($node, $config->img->rules_minify_exclude)) {
                    break;
                }

                $hasSrc = $node->hasAttribute('src');
                $hasSrcset = $node->hasAttribute('srcset');
                $src_orig = $node->getAttribute('src');
                $src_new = $src_orig;

                if ($hasSrc && $config->img->minify) {
                    $src_new = $this->imgSrcOptimize($src_orig);
                    if ($src_new !== $src_orig) {
                        $node->setAttribute('src', $src_new);
                    }
                }

                if ($hasSrcset && ($config->img->minify || $config->html->urlminify)) {
                    $srcset_orig = $node->getAttribute('srcset');
                    $srcset_new = $this->imgSrcsetOptimize($srcset_orig);
                    if ($srcset_new !== $srcset_orig) {
                        $node->setAttribute('srcset', $srcset_new);
                    }
                }

                if ($hasSrc && !$hasSrcset && $config->img->srcsetgeneration) {
                    $srcset_new = $this->imgSrcsetGenerate($src_orig, $src_new);
                    if ($srcset_new !== null) {
                        $node->setAttribute('srcset', $srcset_new);
                    }
                }

                break;

            case 'video':
                if ($this->noscriptCounter) {
                    break;
                }
                if ($config->img->rules_minify_exclude && $this->matchExcludeRule($node, $config->img->rules_minify_exclude)) {
                    break;
                }
                if ($node->hasAttribute('poster') && $config->img->minify) {
                    $src_orig = $node->getAttribute('poster');
                    $src_new = $this->imgSrcOptimize($src_orig);
                    if ($src_new !== $src_orig) {
                        $node->setAttribute('poster', $src_new);
                    }
                }
                break;

            case 'input':
                if ($this->noscriptCounter) {
                    break;
                }
                if ($config->img->rules_minify_exclude && $this->matchExcludeRule($node, $config->img->rules_minify_exclude)) {
                    break;
                }
                if ($config->img->minify && $node->hasAttribute('src') &&
                    $node->hasAttribute('type') && $node->getAttribute('type') === 'image'
                ) {
                    $src_orig = $node->getAttribute('src');
                    $src_new = $this->imgSrcOptimize($src_orig);
                    if ($src_new !== $src_orig) {
                        $node->setAttribute('src', $src_new);
                    }
                }
                break;

            case 'picture':

                break;

            case 'source':
                if ($this->pictureCounter && !$this->noscriptCounter) {
                    if ($config->img->minify && $node->hasAttribute('srcset')) {
                        $srcset = $node->getAttribute('srcset');
                        $srcset_new = $this->imgSrcsetOptimize($srcset);
                        if ($srcset_new !== $srcset) {
                            $node->setAttribute('srcset', $srcset_new);
                        }
                    }
                }
                break;

            case 'script':
                $iterateChildren = false; // don't change script sources
                if ($this->noscriptCounter) {
                    // scripts aren't executed in the noscript context, so it's safe to remove them
                    $this->nodeDetach($node);
                    break;
                }

                if ($config->js->rules_merge_bypass && $this->matchExcludeRule($node, $config->js->rules_merge_bypass)) {
                    break;
                }

                if ($config->js->rules_merge_stop && $this->matchExcludeRule($node, $config->js->rules_merge_stop)) {
                    $this->breakJs(self::JS_MODE_ALL);
                    break;
                }

                if ($config->js->rules_merge_startgroup && $this->matchExcludeRule($node, $config->js->rules_merge_startgroup)) {
                    $this->breakJs(self::JS_MODE_ALL);
                }

                if ($node->hasAttribute('type') && $node->getAttribute('type') === 'module') {
                    if ($node->hasAttribute('async')) {
                        // async module may depend on previous scripts (non-async ones are always deferred)
                        $this->breakJs();
                    } else {
                        // break defer js sequence to preserve ordering
                        $this->breakJs(self::JS_MODE_DEFER);
                    }
                    break;
                }

                if ($node->hasAttribute('nomodule')) {
                    $this->breakJs();
                    break;
                }

                $automove = $config->js->automove &&
                    !($config->js->rules_move_exclude && $this->matchExcludeRule($node, $config->js->rules_move_exclude));

                if (
                    ($config->js->forceasync &&
                        !($config->js->rules_async_exclude && $this->matchExcludeRule($node, $config->js->rules_async_exclude))
                    ) || ($config->js->rules_async_include && $this->matchExcludeRule($node, $config->js->rules_async_include))
                ) {
                    $node->setAttribute('async', '');
                }
                if (
                    ($config->js->forcedefer &&
                        !($config->js->rules_defer_exclude && $this->matchExcludeRule($node, $config->js->rules_defer_exclude))
                    ) || ($config->js->rules_defer_include && $this->matchExcludeRule($node, $config->js->rules_defer_include))
                ) {
                    $node->setAttribute('defer', '');
                }

                // break if there attributes other than type=text/javascript, defer, async, integrity
                if ($node->attributes->item(0) !== null) {
                    $attributes = array();
                    foreach ($node->attributes as $name => $anode) {
                        $attributes[$name] = $anode->nodeValue;
                    }
                    if ($config->js->checkattributes) {
                        if (isset($attributes['type'], $this->jsMime[$attributes['type']])) {
                            unset($attributes['type']);
                            if ($config->html->removedefattr) {
                                $node->removeAttribute('type');
                            }
                        }
                        if (isset($attributes['language']) && strcasecmp($attributes['language'], 'javascript') === 0) {
                            unset($attributes['language']);
                            if ($config->html->removedefattr) {
                                $node->removeAttribute('language');
                            }
                        }
                        if (isset($attributes['nonce']) && $attributes['nonce'] === $config->js->nonce) {
                            unset($attributes['nonce']);
                        }
                        unset($attributes['defer'], $attributes['async'], $attributes['src'], $attributes['integrity']);
                        if (count($attributes) > 0) {
                            $attributes = array();
                            foreach ($node->attributes as $name => $anode) {
                                $attributes[$name] = $anode->nodeValue;
                            }
                            $mode = $this->getJsMode($attributes);
                            $this->breakJs($mode);
                            break;
                        }
                    } else {
                        if (isset($attributes['type']) && !isset($this->jsMime[$attributes['type']])) {
                            $this->breakJs();
                            break;
                        }
                        if (isset($attributes['nonce']) && $attributes['nonce'] !== $config->js->nonce) {
                            $this->breakJs();
                            break;
                        }
                    }
                }

                // set type=text/javascript in html4 and remove in html5
                if ($this->doctype !== self::DOCTYPE_HTML5 && !$node->hasAttribute('type')) {
                    $node->setAttribute('type', 'text/javascript');
                }

                if ($node->hasAttribute('src')) { // external
                    if ($config->js->rules_merge_exclude && $this->matchExcludeRule($node, $config->js->rules_merge_exclude)) {
                        $merge = false;
                    } elseif ($config->js->rules_merge_include && $this->matchExcludeRule($node, $config->js->rules_merge_include)) {
                        $merge = true;
                    } else {
                        $merge = $config->js->merge;
                    }

                    if ($merge) {
                        $src = $node->getAttribute('src');
                        $srcFile = $this->urlRewriter->urlToFilepath($src);
                        $merge = ($srcFile !== null) && (pathinfo($srcFile, PATHINFO_EXTENSION) === 'js') && $this->di->filesystem->isFile($srcFile);
                    }

                    if ($merge) {
                        $this->addJs($node, false);
                    } else {
                        $attributes = array();
                        foreach ($node->attributes as $name => $anode) {
                            $attributes[$name] = $anode->nodeValue;
                        }
                        $mode = $this->getJsMode($attributes);
                        $this->breakJs($mode);
                        $this->addJs($node, false);
                        $this->breakJs($mode);
                    }
                } else { // inline
                    $scriptBlob = $node->textContent;

                    if (empty($scriptBlob)) {
                        if ($config->js->merge) {
                            $this->nodeDetach($node);
                        }
                        return false;
                    }

                    $scriptBlob = $this->scriptCleanInlined($scriptBlob);
                    $node->textContent = $scriptBlob;

                    if ($config->js->skipinits && $this->isInitScript($scriptBlob)) {
                        // skip (probable page-dependent) js variables initialization from merging
                        break;
                    }

                    if ($config->js->rules_merge_exclude && $this->matchExcludeRule($node, $config->js->rules_merge_exclude)) {
                        $merge = false;
                    } elseif ($config->js->rules_merge_include && $this->matchExcludeRule($node, $config->js->rules_merge_include)) {
                        $merge = true;
                    } else {
                        $merge =
                            is_bool($config->js->mergeinline)
                                ? $config->js->mergeinline
                                : $this->headMode;
                        if ($merge && $node->hasAttribute('id')) {
                            $id = $node->getAttribute('id');
                            if (preg_match('/([\'"])#?' . preg_quote($id, '/') . '\1/', $scriptBlob)) {
                                $merge = false;
                            }
                        }
                    }

                    if ($merge) {
                        $this->addJs($node, true);
                    } else {
                        $attributes = array();
                        foreach ($node->attributes as $name => $anode) {
                            $attributes[$name] = $anode->nodeValue;
                        }
                        $mode = $this->getJsMode($attributes);
                        $this->breakJs($mode);
                        $this->addJs($node, true);
                        $this->breakJs($mode);
                    }
                }

                if (!$automove && $this->lastJsNode) {
                    $this->breakJsNextNode = true;
                }

                if ($config->js->rules_merge_stopgroup && $this->matchExcludeRule($node, $config->js->rules_merge_stopgroup)) {
                    $this->breakJs(self::JS_MODE_ALL);
                }
                break;

            case 'noscript':
                break;

            case 'link':
                // break if there attributes other than type=text/css, rel=stylesheet, href
                if (!$node->hasAttribute('href') || $node->getAttribute('rel') !== 'stylesheet') {
                    break;
                }
                if ($this->noscriptCounter) {
                    break;
                }

                if ($config->css->rules_merge_bypass && $this->matchExcludeRule($node, $config->css->rules_merge_bypass)) {
                    break;
                }

                if ($config->css->rules_merge_stop && $this->matchExcludeRule($node, $config->css->rules_merge_stop)) {
                    $this->breakCss();
                    break;
                }

                if ($config->css->rules_merge_startgroup && $this->matchExcludeRule($node, $config->css->rules_merge_startgroup)) {
                    $this->breakCss();
                }

                $attributes = array();
                foreach ($node->attributes as $name => $anode) {
                    $attributes[$name] = $anode->nodeValue;
                }
                if ($config->css->checklinkattributes) {
                    if (isset($attributes['type']) && $attributes['type'] === 'text/css') {
                        unset($attributes['type']);
                    }
                    unset($attributes['rel'], $attributes['media'], $attributes['href']);
                    if (count($attributes) > 0) {
                        if (!preg_match('#^(https?:)?//fonts\.googleapis\.com/css#', $node->getAttribute('href'))) {
                            $this->breakCss();
                        }
                        break;
                    }
                } else {
                    if (isset($attributes['type']) && $attributes['type'] !== 'text/css') {
                        break;
                    }
                }

                // set type=text/css in html4 and remove in html5
                if ($this->doctype !== self::DOCTYPE_HTML5 && !$node->hasAttribute('type')) {
                    $node->setAttribute('type', 'text/css');
                }

                if ($config->css->rules_merge_exclude && $this->matchExcludeRule($node, $config->css->rules_merge_exclude)) {
                    $merge = false;
                } elseif ($config->css->rules_merge_include && $this->matchExcludeRule($node, $config->css->rules_merge_include)) {
                    $merge = true;
                } else {
                    $merge = $config->css->merge;
                }

                if ($merge) {
                    $src = $node->getAttribute('href');
                    $srcFile = $this->urlRewriter->urlToFilepath($src);
                    $merge = ($srcFile !== null) && (pathinfo($srcFile, PATHINFO_EXTENSION) === 'css') && $this->di->filesystem->isFile($srcFile);
                }

                if ($merge) {
                    $this->addCss($node);
                } else {
                    $this->breakCss();
                }

                if ($config->css->rules_merge_stopgroup && $this->matchExcludeRule($node, $config->css->rules_merge_stopgroup)) {
                    $this->breakCss();
                }
                break;

            case 'style':
                $iterateChildren = false; // don't change style sources
                if ($this->noscriptCounter) {
                    break;
                }

                if ($config->css->rules_merge_bypass && $this->matchExcludeRule($node, $config->css->rules_merge_bypass)) {
                    break;
                }

                if ($config->css->rules_merge_stop && $this->matchExcludeRule($node, $config->css->rules_merge_stop)) {
                    $this->breakCss();
                    break;
                }

                if ($config->css->rules_merge_startgroup && $this->matchExcludeRule($node, $config->css->rules_merge_startgroup)) {
                    $this->breakCss();
                }

                $attributes = array();
                foreach ($node->attributes as $name => $anode) {
                    $attributes[$name] = $anode->nodeValue;
                }
                if ($config->css->checkstyleattributes) {
                    // break if there attributes other than type=text/css
                    if (isset($attributes['type']) && $attributes['type'] === 'text/css') {
                        unset($attributes['type']);
                    }
                    if (isset($attributes['nonce']) && $attributes['nonce'] === $config->css->nonce) {
                        unset($attributes['nonce']);
                    }
                    unset($attributes['media']);
                    if (count($attributes) > 0) {
                        $this->breakCss();
                        break;
                    }
                } else {
                    if (isset($attributes['type']) && $attributes['type'] !== 'text/css') {
                        break;
                    }
                    if (isset($attributes['nonce']) && $attributes['nonce'] !== $config->js->nonce) {
                        $this->breakCss();
                        break;
                    }
                }

                if ($node->childNodes->item(0) === null) {
                    if ($config->css->mergeinline) {
                        $this->nodeDetach($node);
                    }
                    return false;
                }

                // set type=text/css in html4 and remove in html5
                if ($this->doctype !== self::DOCTYPE_HTML5 && !$node->hasAttribute('type')) {
                    $node->setAttribute('type', 'text/css');
                }
                // remove the media attribute if it is empty or "all"
                if ($config->html->removedefattr && $node->hasAttribute('media')) {
                    $media = $node->getAttribute('media');
                    // $media = $this->filterMedia($media);
                    if ($media === '' || $media === 'all') {
                        $node->removeAttribute('media');
                    }
                }
                // css break point if scoped=... attribute
                if ($node->hasAttribute('scoped')) {
                    $this->breakCss();
                }

                if ($config->css->rules_merge_exclude && $this->matchExcludeRule($node, $config->css->rules_merge_exclude)) {
                    $merge = false;
                } elseif ($config->css->rules_merge_include && $this->matchExcludeRule($node, $config->css->rules_merge_include)) {
                    $merge = true;
                } else {
                    $merge =
                        is_bool($config->css->mergeinline)
                            ? $config->css->mergeinline
                            : $this->headMode;
                }

                if ($merge) {
                    $this->addCss($node, true);
                } else {
                    $this->breakCss();
                }

                if ($config->css->rules_merge_stopgroup && $this->matchExcludeRule($node, $config->css->rules_merge_stopgroup)) {
                    $this->breakCss();
                }
                break;

            case 'math':
            case 'svg':
            case 'template':
            case 'textarea':
                $iterateChildren = false;
                break;
        }

        $this->dispatcher->triggerEvent('HtmlIterateTag' . $tagName, array($this, $node));
        if ($node->parentNode === null) {
            return false;
        }

        if ($this->breakJsNextNode && ($node->nodeName !== 'script') && ($node->nodeName !== 'ressscript')) {
            $this->breakJs();
        }

        if ($node->hasAttributes()) {
            if ($node->hasAttribute('onload') || $node->hasAttribute('onerror')) {
                $this->breakJs();
            }

            // minify uri in attributes
            if ($config->html->urlminify && isset($this->uriAttrs[$node->nodeName]) &&
                // allow full URL in <link> tags except of stylesheet (e.g. canonical, amphtml, etc.)
                !($node->nodeName === 'link' && $node->hasAttribute('rel') && $node->getAttribute('rel') !== 'stylesheet')
            ) {
                foreach ($this->uriAttrs[$node->nodeName] as $attrName) {
                    if ($node->hasAttribute($attrName)) {
                        $uri = $node->getAttribute($attrName);
                        if (!empty($uri) && strncmp($uri, 'data:', 5) !== 0) {
                            $node->setAttribute($attrName, $this->urlRewriter->minify($uri));
                        }
                    }
                }
            }

            //minify style attribute (css)
            if ($config->css->minifyattribute && $node->hasAttribute('style')) {
                $node->setAttribute('style', $this->cssMinifyInline($node->getAttribute('style'), $this->urlRewriter->getBase()));
            }

            //minify on* handlers (js)
            if ($config->js->minifyattribute) {
                foreach ($node->attributes as $name => $anode) {
                    if (isset($this->jsEvents[$name])) {
                        $node->setAttribute($name, $this->jsMinifyInline($anode->nodeValue));
                    }
                }
            }

            //compress class attribute
            if ($node->hasAttribute('class')) {
                $node->setAttribute('class', preg_replace('/(?<= ) +/', '', strtr($node->getAttribute('class'), "\n\r\t\f", '    ')));
            }

            //remove default attributes with default values (type=text for input etc.)
            if ($config->html->removedefattr) {
                switch ($this->doctype) {
                    case self::DOCTYPE_HTML5:
                        $defaultAttrs = $this->defaultAttrsHtml5;
                        break;
                    case self::DOCTYPE_HTML4:
                        $defaultAttrs = $this->defaultAttrsHtml4;
                        break;
                    default:
                        $defaultAttrs = array();
                }
                if (isset($defaultAttrs[$node->nodeName])) {
                    foreach ($defaultAttrs[$node->nodeName] as $attrName => $attrValue) {
                        if ($node->getAttribute($attrName) === $attrValue) {
                            $node->removeAttribute($attrName);
                        }
                    }
                }
            }

            // rearrange attributes to improve gzip compression
            // (e.g. always use <input type=" or <option value=", etc.)
            if ($config->html->sortattr && isset($this->attrFirst[$node->nodeName]) && $node->attributes->item(1) !== null) {
                $this->cmpAttrFirst = $this->attrFirst[$node->nodeName];
                $attributes = array();
                foreach ($node->attributes as $name => $anode) {
                    $attributes[$name] = $anode->nodeValue;
                }
                uksort($attributes, array($this, 'attrFirstCmp'));
                foreach ($attributes as $name => $value) {
                    $node->removeAttribute($name);
                    $node->setAttribute($name, $value);
                }
            }
        }

        $this->dispatcher->triggerEvent("HtmlIterateTag{$tagName}After", array($this, $node));
        if ($node->parentNode === null) {
            return false;
        }

        return $iterateChildren;
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element|Ressio_HtmlOptimizer_Dom_Document $node
     * @param bool $mergeSpace
     * @return void
     * @throws ERessio_UnknownDiKey
     */
    protected function domIterate($node, $mergeSpace)
    {
        $mergeSpace = $mergeSpace && !isset($this->tags_preservespaces[$node->nodeName]);
        $child = $node->childNodes->item(0);
        while ($child !== null) {
            $this->cursorNode = $child;
            $nextChild = $child->nextSibling;
            $tagName = strtolower($child->nodeName);
            $this->dispatcher->triggerEvent('HtmlIterateNodeBefore', array($this, $child, $tagName));
            if ($child->parentNode !== null && $this->domProcess($child, $mergeSpace)) {
                if ($tagName === 'noscript') {
                    $this->noscriptCounter++;
                } elseif ($tagName === 'picture') {
                    $this->pictureCounter++;
                }

                $saveCursor = $this->cursorNode;
                $this->domIterate($child, $mergeSpace);
                $this->cursorNode = $saveCursor;

                if ($tagName === 'noscript') {
                    $this->noscriptCounter--;
                } elseif ($tagName === 'picture') {
                    $this->pictureCounter--;
                }

                if ($tagName === 'body') {
                    // move movable scripts to the end
                    if ($this->lastJsNode !== null) {
                        $child->appendChild($this->lastJsNode);
                    }
                }

                if ($tagName) {
                    $tagName_uc = strtoupper($tagName);
                    $this->dispatcher->triggerEvent("HtmlIterateTag{$tagName_uc}AfterEnd", array($this, $child, $tagName));
                }
                $this->dispatcher->triggerEvent('HtmlIterateNodeAfter', array($this, $child, $tagName));
            }
            $child = $nextChild;
            if (isset($this->cursorNode->parentNode->parentNode->parentNode)) {
                $this->prevCursorNode = $this->cursorNode;
            }
        }
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $node
     * @param bool $inline
     * @return void
     */
    protected function addJs($node, $inline = false)
    {
        $attributes = array();
        foreach ($node->attributes as $name => $anode) {
            $attributes[$name] = $anode->nodeValue;
        }

        $async = isset($attributes['async']);
        $defer = isset($attributes['defer']);

        $mode = $this->getJsMode($attributes);
        $jsListNode = $this->getJsListNode($mode);

        if ($jsListNode === null) {
            $jsListNode = $this->createJsListNode();
            $this->setJsListNode($mode, $jsListNode);
            $node->parentNode->replaceChild($jsListNode, $node);
        } else {
            if ($mode === self::JS_MODE_DEFER) {
                $node->parentNode->removeChild($node);
            } else {
                $node->parentNode->replaceChild($jsListNode, $node);
            }
        }

        $this->appendJsList($jsListNode, $inline ? array(
            'type' => 'inline',
            'script' => $node->textContent,
            'async' => $async,
            'defer' => $defer
        ) : array(
            'type' => 'ref',
            'src' => $node->getAttribute('src'),
            'async' => $async,
            'defer' => $defer
        ));
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $node
     * @param bool $inline
     * @return void
     */
    protected function addCss($node, $inline = false)
    {
        $cssListNode = $this->lastCssNode;

        if ($cssListNode === null) {
            $this->lastCssNode = $this->lastAsyncCssNode = $cssListNode = $this->createCssListNode();
        }

        $node->parentNode->replaceChild($cssListNode, $node);

        $media = $node->hasAttribute('media') ? $node->getAttribute('media') : 'all';

        $this->appendCssList($cssListNode, $inline ? array(
            'type' => 'inline',
            'style' => $node->textContent,
            'media' => $media
        ) : array(
            'type' => 'ref',
            'src' => $node->getAttribute('href'),
            'media' => $media
        ));
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $node
     * @return string
     */
    public function nodeToString($node)
    {
        // @note returns node instead of text (to allow inserting into other nodes)
        // return $node->cloneNode();
        return $this->dom->saveHTML($node);
    }

    /**
     * @param DOMNode $node
     * @return void
     */
    public function nodeDetach($node)
    {
        $node->parentNode->removeChild($node);
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $node
     * @return bool
     */
    public function nodeIsDetached($node)
    {
        return $node->parentNode === null;
    }

    /**
     * @param DOMNode $node
     * @return bool
     */
    public function nodeIsElement($node)
    {
        return $node instanceof Ressio_HtmlOptimizer_Dom_Element;
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $node
     * @param string $text
     * @return void
     */
    public function nodeSetInnerText($node, $text)
    {
        $node->textContent = $text;
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $node
     * @return string
     */
    public function nodeGetInnerText($node)
    {
        return $node->textContent;
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $node
     * @param string $tag
     * @param array $attribs
     * @return void
     */
    public function nodeWrap($node, $tag, $attribs = null)
    {
        $newNode = $this->dom->createElement($tag);
        if ($attribs) {
            foreach ($attribs as $name => $value) {
                $newNode->setAttribute($name, $value);
            }
        }
        $node->parentNode->insertBefore($newNode, $node);
        $newNode->appendChild($node);
        if ($node === $this->cursorNode) {
            $this->cursorNode = $newNode;
        }
    }

    /**
     * @param string $tag
     * @param string[]|null $attribs
     * @param string|null|false $content
     * @return DOMElement
     */
    protected function createNode($tag, $attribs = null, $content = null)
    {
        $newNode = $this->dom->createElement($tag);
        if ($attribs !== null) {
            /** @var array $attribs */
            foreach ($attribs as $name => $value) {
                $newNode->setAttribute($name, $value);
            }
        }
        if ($content !== false) {
            // if ($content === null) { $newNode->nodeValue = ''; }
            $newNode->appendChild($this->dom->createTextNode($content));
        }
        return $newNode;
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $node
     * @param string $tag
     * @param array $attribs
     * @param string $content
     * @return void
     */
    public function nodeInsertBefore($node, $tag, $attribs = null, $content = null)
    {
        $newNode = $this->createNode($tag, $attribs, $content);
        $node->parentNode->insertBefore($newNode, $node);
    }

    /**
     * @param Ressio_HtmlOptimizer_Dom_Element $node
     * @param string $tag
     * @param array $attribs
     * @param string $content
     * @return void
     */
    public function nodeInsertAfter($node, $tag, $attribs = null, $content = null)
    {
        $newNode = $this->createNode($tag, $attribs, $content);
        $node->parentNode->insertBefore($newNode, $node->nextSibling);
    }

    /**
     * @param array (string $tag, array $attribs, string $content) ...$nodedata
     * @return bool return false if no <head> found
     */
    public function prependHead($nodedata)
    {
        if ($this->prependHeadNode) {
            $injectParent = $this->prependHeadNode;
            $injectBeforeNode = $this->prependHeadRefNode;
        } else {
            $injectParent = $this->dom->getElementsByTagName('head')->item(0);
            if ($injectParent) {
                $injectBeforeNode = $injectParent->firstChild;
                while ($injectBeforeNode) {
                    if ($injectBeforeNode instanceof DOMElement) {
                        $tagName = strtoupper($injectBeforeNode->tagName);
                        if ($tagName !== 'META' && $tagName !== 'TITLE' && $tagName !== 'RESSSCRIPT' && $tagName !== 'RESSCSS') {
                            break;
                        }
                    }
                    $injectBeforeNode = $injectBeforeNode->nextSibling;
                }
            } else {
                $injectBeforeNode = null;
            }
            $this->prependHeadNode = $injectParent;
            $this->prependHeadRefNode = $injectBeforeNode;
        }

        if ($injectParent) {
            foreach (func_get_args() as $arg) {
                list($tag, $attribs, $content) = $arg;
                if ($tag === '!--') {
                    $newNode = $this->dom->createComment($content);
                } else {
                    $newNode = $this->createNode($tag, $attribs, $content);
                }
                $injectParent->insertBefore($newNode, $injectBeforeNode);
            }
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isNoscriptState()
    {
        return $this->noscriptCounter > 0;
    }

    /**
     * @return bool
     */
    public function isPictureState()
    {
        return $this->pictureCounter > 0;
    }
}
