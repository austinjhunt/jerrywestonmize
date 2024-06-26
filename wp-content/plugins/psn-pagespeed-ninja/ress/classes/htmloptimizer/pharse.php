<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_LIBS') || die();

include_once RESSIO_LIBS . '/pharse/pharse_parser_html.php';
class Ressio_HtmlOptimizer_Pharse extends Ressio_HtmlOptimizer_Base
{
    /** @var string */
    public $origDoctype;

    /** @var HTML_Node */
    public $dom;

    /** @var HTML_Node|null */
    private $cursorNode;
    /** @var HTML_Node|null */
    private $prevCursorNode;

    /** @var bool */
    private $baseFound = false;

    /** @var Ressio_HtmlOptimizer_Pharse_JSList[] */
    private $jsNodes;
    /** @var Ressio_HtmlOptimizer_Pharse_JSList|null */
    protected $lastJsNode;
    /** @var Ressio_HtmlOptimizer_Pharse_JSList|null */
    protected $lastDeferJsNode;
    /** @var Ressio_HtmlOptimizer_Pharse_JSList|null */
    protected $lastAsyncJsNode;

    /** @var Ressio_HtmlOptimizer_Pharse_CSSList[] */
    private $cssNodes;
    /** @var Ressio_HtmlOptimizer_Pharse_CSSList|null */
    protected $lastCssNode;
    /** @var Ressio_HtmlOptimizer_Pharse_CSSList|null */
    protected $lastAsyncCssNode;

    /** @var int */
    public $noscriptCounter = 0;
    /** @var int */
    public $pictureCounter = 0;

    /** @var bool */
    public $headMode;

    /** @var string */
    public $classNodeCssList = Ressio_HtmlOptimizer_Pharse_CSSList::class;
    /** @var string */
    public $classNodeJsList = Ressio_HtmlOptimizer_Pharse_JSList::class;

    /** @var HTML_Node */
    private $prependHeadNode;
    /** @var int */
    private $prependHeadOffset;

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        parent::__construct($di);

        $this->tags_selfclose['~stylesheet~'] = 0;
        $this->tags_nospaces['~root~'] = 0;
    }

    /**
     * @pure
     * @return string
     */
    public function getQueryDelim()
    {
        return '&amp;';
    }

    /**
     * @param string $buffer
     * @return string
     * @throws ERessio_UnknownDiKey
     */
    public function run($buffer)
    {


        // parse html
        $dom = (new HTML_Parser_HTML5($buffer))->root;
        $this->dom = $dom;

        $this->jsNodes = array();
        $this->lastJsNode = null;
        $this->lastDeferJsNode = null;
        $this->lastAsyncJsNode = null;

        $this->cssNodes = array();
        $this->lastCssNode = null;
        $this->lastAsyncCssNode = null;

        $this->breakJsNextNode = false;

        $this->headMode = true;

        $this->dispatcher->triggerEvent('HtmlIterateBefore', array($this));

        $this->domIterate($dom, $this->config->html->mergespace);

        if ($this->origDoctype === null && $this->config->html->forcehtml5) {
            $offset = 0;
            $dom->addDoctype('html', $offset);
        }

        $this->dispatcher->triggerEvent('HtmlIterateAfter', array($this));

        $this->breakJs(self::JS_MODE_ALL);
        $this->breakCss();

        foreach ($this->jsNodes as $jsNode) {
            $jsNode->prepare();
        }

        foreach ($this->cssNodes as $cssNode) {
            $cssNode->prepare();
        }

        $this->dispatcher->triggerEvent('HtmlBeforeStringify', array($this));

        $this->cursorNode = null;
        $this->prevCursorNode = null;

        $buffer = (string)$dom;

        // free memory
        $this->dom = null;
        $this->jsNodes = null;
        $this->lastJsNode = $this->lastAsyncJsNode = $this->lastDeferJsNode = null;
        $this->cssNodes = null;
        $this->lastCssNode = $this->lastAsyncCssNode = null;

        return $buffer;
    }

    /**
     * @return Ressio_HtmlOptimizer_Pharse_JSList
     */
    protected function createJsListNode()
    {
        return $this->jsNodes[] = new $this->classNodeJsList($this->di);
    }

    /**
     * @param Ressio_HtmlOptimizer_Pharse_JSList $jsListNode
     * @param HTML_Node|null $before
     */
    protected function insertJsListNode($jsListNode, $before)
    {
        $this->insertJsCssListNode($jsListNode, $before);
    }

    /**
     * @param Ressio_HtmlOptimizer_Pharse_JSList $jsListNode
     * @param array $data
     */
    protected function appendJsList($jsListNode, $data)
    {
        $jsListNode->scriptList[] = $data;
    }

    /**
     * @return Ressio_HtmlOptimizer_Pharse_CSSList
     */
    protected function createCssListNode()
    {
        return $this->cssNodes[] = new $this->classNodeCssList($this->di);
    }

    /**
     * @param Ressio_HtmlOptimizer_Pharse_CSSList $cssListNode
     * @param HTML_Node|null $before
     */
    protected function insertCssListNode($cssListNode, $before)
    {
        $this->insertJsCssListNode($cssListNode, $before);
    }

    /**
     * @param Ressio_HtmlOptimizer_Pharse_CSSList $cssListNode
     * @param array $data
     */
    protected function appendCssList($cssListNode, $data)
    {
        $cssListNode->styleList[] = $data;
    }

    /**
     * @param Ressio_HtmlOptimizer_Pharse_JSList|Ressio_HtmlOptimizer_Pharse_CSSList $jscssListNode
     * @param HTML_Node|null $before
     */
    private function insertJsCssListNode($jscssListNode, $before)
    {
        if ($before !== null) {
            $offset = $before->index();
            $before->parent->addChild($jscssListNode, $offset);
        } elseif (isset($this->cursorNode->parent->parent->parent)) {
            $offset = $this->cursorNode->index() + 1;
            $this->cursorNode->parent->addChild($jscssListNode, $offset);
        } elseif ($this->prevCursorNode) {
            $node = $this->prevCursorNode;
            while ($node->parent->parent && !($node instanceof $node->childClass)) {
                $node = $node->parent;
            }
            $offset = $node->index() + 1;
            $node->parent->addChild($jscssListNode, $offset);
        } else {
            $node = $this->dom->lastChild(); // html
            if ($node->childCount()) {
                $node = $node->lastChild(); // body
            }
            $node->addChild($jscssListNode);
        }
    }

    /**
     * @param HTML_Node $node
     * @param bool $mergeSpace
     * @return void
     * @throws ERessio_UnknownDiKey
     */
    protected function domIterate($node, $mergeSpace)
    {
        // skip xml and asp tags
        if ($node instanceof $node->childClass_XML ||
            $node instanceof $node->childClass_ASP
        ) {
            return;
        }

        $config = $this->config;

        // doctype
        if ($node instanceof $node->childClass_Doctype) {
            /** @var HTML_Node_Doctype $node */
            $this->origDoctype = $node->dtd;
            if ($config->html->forcehtml5) {
                $node->dtd = 'html';
            } elseif (strpos($node->dtd, 'DTD HTML')) {
                $this->doctype = self::DOCTYPE_HTML4;
            } elseif (strpos($node->dtd, 'DTD XHTML')) {
                $this->doctype = self::DOCTYPE_XHTML;
            }
            return;
        }

        // CDATA is text in xhtml and comment in html
        if ($node instanceof $node->childClass_Text ||
            ($this->doctype === self::DOCTYPE_XHTML && $node instanceof $node->childClass_CDATA)
        ) {
            /** @var HTML_Node_Text $node */
            if ($mergeSpace) {
                $node->text = preg_replace('/(?<= ) +/', '', strtr($node->text, "\n\r\t\f", '    '));
                if ($node->text === ' ' && isset($this->tags_nospaces[$node->parent->tag])) {
                    $node->detach();
                }
            }
            return;
        }

        // remove comments
        if ($node instanceof $node->childClass_Comment ||
            ($this->doctype !== self::DOCTYPE_XHTML && $node instanceof $node->childClass_CDATA)
        ) {
            /** @var HTML_Node_Comment $node */
            if ($config->html->removecomments && $node->text !== '' && $node->text[0] !== '!') {
                $node->detach();
            }
            return;
        }

        // check comments (keep IE ones on IE, [if, <![ : <!--[if IE]>, <!--<![endif]--> )
        // stop css/style combining in IE cond block
        if ($node instanceof $node->childClass_Conditional) {
            /** @var HTML_Node_Conditional $node */
            if ($config->html->removeiecond) {
                $vendor = $this->di->deviceDetector->vendor();
                if ($vendor !== 'ms' && $vendor !== 'unknown') { // if not IE browser
                    $node->detach();
                    return;
                }
            }
            $this->breakCss();
            $this->breakJs(self::JS_MODE_ALL);
            if ($mergeSpace) {
                $inner = $node->children[0]->text;
                $inner = preg_replace('#\s+<!--$#', '<!--', ltrim($inner));
                $node->children[0]->text = $inner;
            }
            return;
        }

        if ($config->html->rules_safe_exclude && $this->matchExcludeRule($node, $config->html->rules_safe_exclude)) {
            return;
        }

        // lowercase tags
        $tagName_uc = strtoupper($node->tag);
        $node->tag = strtolower($node->tag);


        $iterateChildren = !isset($this->tags_selfclose[$node->tag]);

        $this->dispatcher->triggerEvent("HtmlIterateTag{$tagName_uc}Before", array($this, $node));
        if ($node->parent === null && $node->tag !== '~root~') {
            return;
        }

        switch ($node->tag) {
            case 'a':
            case 'area':
                if ($config->js->minifyattribute && isset($node->attributes['href'])) {
                    $uri = $node->attributes['href'];
                    if (strncmp($uri, 'javascript:', 11) === 0) {
                        $node->attributes['href'] = 'javascript:' . $this->jsMinifyInline(substr($uri, 11));
                    }
                }
                break;

            case 'base':
                // save base href (use first tag only)
                if (!$this->baseFound && isset($node->attributes['href'])) {
                    $base = $node->attributes['href'];
                    if (substr($base, -1) !== '/') {
                        $base = dirname($base);
                        if ($base === '.') {
                            $base = '';
                        }
                        $base .= '/';
                    }
                    $this->urlRewriter->setBase($base);
                    $node->attributes['href'] = $this->urlRewriter->getBase();
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

                $hasSrc = isset($node->attributes['src']);
                $hasSrcset = isset($node->attributes['srcset']);
                $src_orig = $hasSrc ? $node->attributes['src'] : null;

                if ($hasSrc && $config->img->minify) {
                    $node->attributes['src'] = $this->imgSrcOptimize($src_orig);
                }

                if ($hasSrcset && ($config->img->minify || $config->html->urlminify)) {
                    $node->attributes['srcset'] = $this->imgSrcsetOptimize($node->attributes['srcset']);
                }

                if ($hasSrc && !$hasSrcset && $config->img->srcsetgeneration) {
                    $srcset = $this->imgSrcsetGenerate($src_orig, $node->attributes['src']);
                    if ($srcset !== null) {
                        $node->attributes['srcset'] = $srcset;
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
                if (isset($node->attributes['poster']) && $config->img->minify) {
                    $node->attributes['poster'] = $this->imgSrcOptimize($node->attributes['poster']);
                }
                break;

            case 'input':
                if ($this->noscriptCounter) {
                    break;
                }
                if ($config->img->rules_minify_exclude && $this->matchExcludeRule($node, $config->img->rules_minify_exclude)) {
                    break;
                }
                if ($config->img->minify && isset($node->attributes['src'], $node->attributes['type']) && $node->attributes['type'] === 'image') {
                    $node->attributes['src'] = $this->imgSrcOptimize($node->attributes['src']);
                }
                break;

            case 'picture':

                break;

            case 'source':
                if ($this->pictureCounter && !$this->noscriptCounter) {
                    if (isset($node->attributes['srcset']) && $config->img->minify) {
                        $node->attributes['srcset'] = $this->imgSrcsetOptimize($node->attributes['srcset']);
                    }
                }
                break;

            case 'script':
                $iterateChildren = false; // don't change script sources
                if ($this->noscriptCounter) {
                    // scripts aren't executed in the noscript context, so it's safe to remove them
                    $node->detach();
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

                if (isset($node->attributes['type']) && $node->attributes['type'] === 'module') {
                    if (isset($node->attributes['async'])) {
                        // async module may depend on previous scripts (non-async ones are always deferred)
                        $this->breakJs();
                    } else {
                        // break defer js sequence to preserve ordering
                        $this->breakJs(self::JS_MODE_DEFER);
                    }
                    break;
                }

                if (isset($node->attributes['nomodule'])) {
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
                    $node->attributes['async'] = false;
                }
                if (
                    ($config->js->forcedefer &&
                        !($config->js->rules_defer_exclude && $this->matchExcludeRule($node, $config->js->rules_defer_exclude))
                    ) || ($config->js->rules_defer_include && $this->matchExcludeRule($node, $config->js->rules_defer_include))
                ) {
                    $node->attributes['defer'] = false;
                }

                // break if there attributes other than type=text/javascript, defer, async, integrity, nonce
                if (count($node->attributes)) {
                    $attributes = $node->attributes;
                    if ($config->js->checkattributes) {
                        if (isset($attributes['type'], $this->jsMime[$attributes['type']])) {
                            unset($attributes['type']);
                            if ($config->html->removedefattr) {
                                unset($node->attributes['type']);
                            }
                        }
                        if (isset($attributes['language']) && strcasecmp($attributes['language'], 'javascript') === 0) {
                            unset($attributes['language']);
                            if ($config->html->removedefattr) {
                                unset($node->attributes['language']);
                            }
                        }
                        if (isset($attributes['nonce']) && $attributes['nonce'] === $config->js->nonce) {
                            unset($attributes['nonce']);
                        }
                        unset($attributes['defer'], $attributes['async'], $attributes['src'], $attributes['integrity']);
                        if (count($attributes) > 0) {
                            $mode = $this->getJsMode($node->attributes);
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
                if ($this->doctype !== self::DOCTYPE_HTML5 && !isset($node->attributes['type'])) {
                    $node->attributes['type'] = 'text/javascript';
                }

                if (isset($node->attributes['src'])) { // external
                    if ($config->js->rules_merge_exclude && $this->matchExcludeRule($node, $config->js->rules_merge_exclude)) {
                        $merge = false;
                    } elseif ($config->js->rules_merge_include && $this->matchExcludeRule($node, $config->js->rules_merge_include)) {
                        $merge = true;
                    } else {
                        $merge = $config->js->merge;
                    }

                    if ($merge) {
                        $src = $node->attributes['src'];
                        $srcFile = $this->urlRewriter->urlToFilepath($src);
                        $merge = ($srcFile !== null) && (pathinfo($srcFile, PATHINFO_EXTENSION) === 'js') && $this->di->filesystem->isFile($srcFile);
                    }

                    if ($merge) {
                        $this->addJs($node, false);
                    } else {
                        $mode = $this->getJsMode($node->attributes);
                        $this->breakJs($mode);
                        $this->addJs($node, false);
                        $this->breakJs($mode);
                    }
                } else { // inline
                    if (count($node->children) === 0) {
                        if ($config->js->merge) {
                            $node->detach();
                        }
                        return;
                    }

                    $scriptBlob = $this->scriptCleanInlined($node->children[0]->text);
                    $node->children[0]->text = $scriptBlob;

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
                        if ($merge && isset($node->attributes['id'])) {
                            $id = $node->attributes['id'];
                            if (preg_match('/([\'"])#?' . preg_quote($id, '/') . '\1/', $scriptBlob)) {
                                $merge = false;
                            }
                        }
                    }

                    if ($merge) {
                        $this->addJs($node, true);
                    } else {
                        $mode = $this->getJsMode($node->attributes);
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
                if (!isset($node->attributes['rel'], $node->attributes['href']) || $node->attributes['rel'] !== 'stylesheet') {
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

                $attributes = $node->attributes;
                if ($config->css->checklinkattributes) {
                    if (isset($attributes['type']) && $attributes['type'] === 'text/css') {
                        unset($attributes['type']);
                    }
                    unset($attributes['rel'], $attributes['media'], $attributes['href']);
                    if (count($attributes) > 0) {
                        if (!preg_match('#^(https?:)?//fonts\.googleapis\.com/css#', $node->attributes['href'])) {
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
                if ($this->doctype !== self::DOCTYPE_HTML5 && !isset($node->attributes['type'])) {
                    $node->attributes['type'] = 'text/css';
                }

                if ($config->css->rules_merge_exclude && $this->matchExcludeRule($node, $config->css->rules_merge_exclude)) {
                    $merge = false;
                } elseif ($config->css->rules_merge_include && $this->matchExcludeRule($node, $config->css->rules_merge_include)) {
                    $merge = true;
                } else {
                    $merge = $config->css->merge;
                }

                if ($merge) {
                    $src = $node->attributes['href'];
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

                $attributes = $node->attributes;
                if ($config->css->checkstyleattributes) {
                    // break if there attributes other than type=text/css, media, nonce
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

                if (count($node->children) === 0) {
                    if ($config->css->mergeinline) {
                        $node->detach();
                    }
                    return;
                }

                // set type=text/css in html4 and remove in html5
                if ($this->doctype !== self::DOCTYPE_HTML5 && !isset($node->attributes['type'])) {
                    $node->attributes['type'] = 'text/css';
                }
                // remove the media attribute if it is empty or "all"
                if (isset($node->attributes['media']) && $config->html->removedefattr) {
                    $media = $node->attributes['media'];
                    // $media = $this->filterMedia($media);
                    if ($media === '' || $media === 'all') {
                        unset($node->attributes['media']);
                    }
                }
                // css break point if scoped=... attribute
                if (isset($node->attributes['scoped'])) {
                    $this->breakCss();
                    return;
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

        $this->dispatcher->triggerEvent('HtmlIterateTag' . $tagName_uc, array($this, $node));
        if ($node->parent === null && $node->tag !== '~root~') {
            return;
        }

        if ($this->breakJsNextNode && ($node->tag !== 'script') && ($node->tag !== '~javascript~')) {
            $this->breakJs();
        }

        // minimal form of self-close tags
        if (isset($this->tags_selfclose[$node->tag])) {
            $node->self_close_str = ($this->doctype === self::DOCTYPE_XHTML) ? '/' : '';
        }

        if (count($node->attributes)) {
            if (isset($node->attributes['onload']) || isset($node->attributes['onerror'])) {
                $this->breakJs();
            }

            // minify uri in attributes
            if ($config->html->urlminify && isset($this->uriAttrs[$node->tag]) &&
                // allow full URL in <link> tags except of rel=stylesheet (e.g. canonical, amphtml, etc.)
                !($node->tag === 'link' && isset($node->attributes['rel']) && $node->attributes['rel'] !== 'stylesheet')
            ) {
                foreach ($this->uriAttrs[$node->tag] as $attrName) {
                    if (isset($node->attributes[$attrName])) {
                        $uri = $node->attributes[$attrName];
                        if (!empty($uri) && strncmp($uri, 'data:', 5) !== 0) {
                            $node->attributes[$attrName] = $this->urlRewriter->minify($uri);
                        }
                    }
                }
            }

            //minify style attribute (css)
            if ($config->css->minifyattribute && isset($node->attributes['style'])) {
                $node->attributes['style'] = $this->cssMinifyInline($node->attributes['style'], $this->urlRewriter->getBase());
            }

            //minify on* handlers (js)
            if ($config->js->minifyattribute) {
                foreach ($node->attributes as $name => &$value) {
                    if (isset($this->jsEvents[$name])) {
                        $value = $this->jsMinifyInline($value);
                    }
                }
                unset($value);
            }

            //compress class attribute
            if (isset($node->attributes['class'])) {
                $node->attributes['class'] = preg_replace('/(?<= ) +/', '', strtr($node->attributes['class'], "\n\r\t\f", '    '));
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
                if (isset($defaultAttrs[$node->tag])) {
                    foreach ($defaultAttrs[$node->tag] as $attrName => $attrValue) {
                        if (isset($node->attributes[$attrName]) && $node->attributes[$attrName] === $attrValue) {
                            unset($node->attributes[$attrName]);
                        }
                    }
                }
            }

            // rearrange attributes to improve gzip compression
            // (e.g. always use <input type=" or <option value=", etc.)
            if ($config->html->sortattr && count($node->attributes) >= 2 && isset($this->attrFirst[$node->tag])) {
                $this->cmpAttrFirst = $this->attrFirst[$node->tag];
                uksort($node->attributes, array($this, 'attrFirstCmp'));
            }
        }

        $this->dispatcher->triggerEvent("HtmlIterateTag{$tagName_uc}After", array($this, $node));
        if ($node->parent === null && $node->tag !== '~root~') {
            return;
        }

        if ($iterateChildren) {
            $children = $node->children;
            $mergeSpace = $mergeSpace && !isset($this->tags_preservespaces[$node->tag]);

            if ($node->tag === 'noscript') {
                $this->noscriptCounter++;
            } elseif ($node->tag === 'picture') {
                $this->pictureCounter++;
            }

            foreach ($children as $child) {
                $this->cursorNode = $child;
                $this->dispatcher->triggerEvent('HtmlIterateNodeBefore', array($this, $child, $child->tag));
                if ($child->parent === null) {
                    unset($child);
                    continue;
                }

                $saveCursor = $this->cursorNode;
                $this->domIterate($child, $mergeSpace);
                $this->cursorNode = $saveCursor;

                if ($child->tag) {
                    $tagName_uc = strtoupper($child->tag);
                    $this->dispatcher->triggerEvent("HtmlIterateTag{$tagName_uc}AfterEnd", array($this, $child, $child->tag));
                }
                $this->dispatcher->triggerEvent('HtmlIterateNodeAfter', array($this, $child, $child->tag));

                if ($child->parent === null) {
                    unset($child);
                }

                if (isset($this->cursorNode->parent->parent->parent)) {
                    $this->prevCursorNode = $this->cursorNode;
                }
            }

            if ($node->tag === 'noscript') {
                $this->noscriptCounter--;
            } elseif ($node->tag === 'picture') {
                $this->pictureCounter--;
            }

            if ($node->tag === 'body') {
                // move movable scripts to the end
                if ($this->lastJsNode !== null) {
                    $index = $node->childCount();
                    $this->lastJsNode->changeParent($node, $index);
                }
            }
        }

    }

    /**
     * @param HTML_Node $node
     * @param bool $inline
     * @return void
     */
    protected function addJs($node, $inline = false)
    {
        $async = isset($node->attributes['async']);
        $defer = isset($node->attributes['defer']);

        $mode = $this->getJsMode($node->attributes);
        $jsListNode = $this->getJsListNode($mode);

        if ($jsListNode) {
            if ($mode === self::JS_MODE_DEFER) {
                $node->detach();
            } else {
                $index = $node->index();
                $jsListNode->changeParent($node->parent, $index);
            }
        } else {
            $jsListNode = $this->createJsListNode();
            $this->insertJsListNode($jsListNode, $node);
            $this->setJsListNode($mode, $jsListNode);
        }

        $this->appendJsList($jsListNode, $inline ? array(
            'type' => 'inline',
            'script' => $node->children[0]->text,
            'async' => $async,
            'defer' => $defer
        ) : array(
            'type' => 'ref',
            'src' => $node->attributes['src'],
            'async' => $async,
            'defer' => $defer
        ));

        $node->detach();
    }

    /**
     * @param HTML_Node $node
     * @param bool $inline
     * @return void
     */
    protected function addCss($node, $inline = false)
    {
        $cssListNode = $this->lastCssNode;

        if ($cssListNode) {
            $index = $node->index();
            $cssListNode->changeParent($node->parent, $index);
        } else {
            $this->lastCssNode = $this->lastAsyncCssNode = $cssListNode = $this->createCssListNode();
            $this->insertCssListNode($cssListNode, $node);
        }

        $media = isset($node->attributes['media']) ? $node->attributes['media'] : 'all';

        $this->appendCssList($cssListNode, $inline ? array(
            'type' => 'inline',
            'style' => $node->children[0]->text,
            'media' => $media
        ) : array(
            'type' => 'ref',
            'src' => $node->attributes['href'],
            'media' => $media
        ));

        $node->detach();
    }

    /**
     * @param HTML_Node $node
     * @return string
     */
    public function nodeToString($node)
    {
        return $node->toString();
    }

    /**
     * @param HTML_Node $node
     * @return void
     */
    public function nodeDetach($node)
    {
        $node->detach();
    }

    /**
     * @param HTML_Node $node
     * @return bool
     */
    public function nodeIsDetached($node)
    {
        return $node->parent === null;
    }

    /**
     * @param HTML_Node $node
     * @return bool
     */
    public function nodeIsElement($node)
    {
        return true;
    }

    /**
     * @param HTML_Node $node
     * @param string $text
     * @return void
     */
    public function nodeSetInnerText($node, $text)
    {
        $node->children = array(
            new $node->childClass_Text($node, $text)
        );
    }

    /**
     * @param HTML_Node $node
     * @return string
     */
    public function nodeGetInnerText($node)
    {
        return isset($node->children[0]->text) ? $node->children[0]->text : '';
    }

    /**
     * @param HTML_Node $node
     * @param string $tag
     * @param array $attribs
     * @return void
     */
    public function nodeWrap($node, $tag, $attribs = null)
    {
        $newNode = $node->wrap($tag);
        if ($attribs) {
            $newNode->attributes = $attribs;
        }
        if ($node === $this->cursorNode) {
            $this->cursorNode = $newNode;
        }
    }

    /**
     * @param HTML_Node $node
     * @param string $tag
     * @param array $attribs
     * @param string $content
     * @return void
     */
    public function nodeInsertBefore($node, $tag, $attribs = null, $content = null)
    {
        /** @var HTML_Node $newNode */
        $newNode = new $node->childClass($tag, $attribs);
        if ($content !== null) {
            $newNode->addText($content);
        }
        $node->parent->insertChild($newNode, $node->index());
    }

    /**
     * @param HTML_Node $node
     * @param string $tag
     * @param array $attribs
     * @param string $content
     * @return void
     */
    public function nodeInsertAfter($node, $tag, $attribs = null, $content = null)
    {
        /** @var HTML_Node $newNode */
        $newNode = new $node->childClass($tag, $attribs);
        if ($content !== null) {
            $newNode->addText($content);
        }
        $node->parent->insertChild($newNode, $node->index() + 1);
    }

    /**
     * @param array (string $tag, array $attribs, string $content) ...$nodedata
     * @return bool return false if no <head> found
     */
    public function prependHead($nodedata)
    {
        if ($this->prependHeadNode) {
            $injectParent = $this->prependHeadNode;
            $injectOffset = $this->prependHeadOffset;
        } else {
            $heads = $this->dom->getChildrenByTag('head');
            if (count($heads)) {
                $injectParent = $heads[0];
                $injectOffset = 0;
                $count = count($injectParent->children);
                while ($injectOffset < $count) {
                    $child = $injectParent->children[$injectOffset];
                    if (
                        $child instanceof $child->childClass_Text ||
                        $child instanceof $child->childClass_Comment ||
                        preg_match('/^meta|title$/i', $child->tag)
                    ) {
                        $injectOffset++;
                        continue;
                    }
                    break;
                }
                $this->prependHeadNode = $injectParent;
                $this->prependHeadOffset = $injectOffset;
            }
        }

        if ($injectParent) {
            foreach (func_get_args() as $arg) {
                list($tag, $attribs, $content) = $arg;
                if ($tag === '!--') {
                    $injectParent->addComment($content, $injectOffset);
                } else {
                    $newNode = $injectParent->addChild($tag, $injectOffset);
                    if ($attribs) {
                        $newNode->attributes = $attribs;
                    }
                    if ($content === false) {
                        $newNode->self_close = true;
                        $newNode->self_close_str = ($this->doctype === self::DOCTYPE_XHTML) ? '/' : '';
                    }
                    if ($content !== null) {
                        $newNode->addText($content);
                    }
                }
            }
            $this->prependHeadOffset = ++$injectOffset;
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
