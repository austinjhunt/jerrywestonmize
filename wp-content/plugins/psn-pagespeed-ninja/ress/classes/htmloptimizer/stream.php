<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_HtmlOptimizer_Stream extends Ressio_HtmlOptimizer_Base
{
    /** @var string */
    const TAG_REGEX = '#<(!(?:--\[if\b|--|\[if\b)|(?:!doctype|base|body|/body|head|/head|iframe|img|link|math|meta|noscript|/noscript|pre|picture|/picture|script|source|style|svg|template|textarea|title|video|/video)(?=[>\s]))#i';

    /** @var array */
    public $dom;

    /** @var bool */
    private $baseFound = false;

    /** @var Ressio_HtmlOptimizer_Stream_JSList[] */
    private $jsNodes;
    /** @var Ressio_HtmlOptimizer_Stream_JSList|null */
    protected $lastJsNode;
    /** @var Ressio_HtmlOptimizer_Stream_JSList|null */
    protected $lastDeferJsNode;
    /** @var Ressio_HtmlOptimizer_Stream_JSList|null */
    protected $lastAsyncJsNode;

    /** @var Ressio_HtmlOptimizer_Stream_CSSList[] */
    private $cssNodes;
    /** @var Ressio_HtmlOptimizer_Stream_CSSList|null */
    protected $lastCssNode;
    /** @var Ressio_HtmlOptimizer_Stream_CSSList|null */
    protected $lastAsyncCssNode;

    /** @var int */
    public $noscriptCounter = 0;
    /** @var int */
    public $pictureCounter = 0;

    /** @var bool */
    public $headMode;

    /** @var string */
    public $classNodeCssList = Ressio_HtmlOptimizer_Stream_CSSList::class;
    /** @var string */
    public $classNodeJsList = Ressio_HtmlOptimizer_Stream_JSList::class;

    /** @var int */
    private $prependHeadOffset;

    /** @var string[] */
    protected static $block_end = array(
        '!--[if' => '<![endif]-->',
        '![if' => '<![endif]>',
        '!--' => '-->',
        'script' => '</script>',
        'style' => '</style>',
        'pre' => '</pre>',
        'textarea' => '</textarea>',
        'math' => '</math>',
        'svg' => '</svg>',
        'template' => '</template>',
    );

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
     */
    public function run($buffer)
    {
        $this->lastJsNode = $this->lastAsyncJsNode = $this->lastDeferJsNode = null;
        $this->lastCssNode = $this->lastAsyncCssNode = null;

        $this->breakJsNextNode = false;

        $this->headMode = true;

        $this->dispatcher->triggerEvent('HtmlIterateBefore', array($this));

        $this->dom = array();
        $this->jsNodes = array();
        $this->cssNodes = array();

        $this->htmlIterate($buffer);

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

        $buffer = implode($this->dom);

        $this->dom = null;
        $this->jsNodes = null;
        $this->cssNodes = null;
        $this->lastJsNode = $this->lastAsyncJsNode = $this->lastDeferJsNode = null;
        $this->lastCssNode = $this->lastAsyncCssNode = null;

        return $buffer;
    }

    /**
     * @return Ressio_HtmlOptimizer_Stream_JSList
     */
    protected function createJsListNode()
    {
        return $this->jsNodes[] = new $this->classNodeJsList($this->di);
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_JSList $jsListNode
     * @param HTML_Node|null $before
     */
    protected function insertJsListNode($jsListNode, $before)
    {
        $this->dom[] = $jsListNode;
        $jsListNode->index = key(array_slice($this->dom, -1, 1, true));
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_JSList $jsListNode
     * @param array $data
     */
    protected function appendJsList($jsListNode, $data)
    {
        $jsListNode->scriptList[] = $data;
    }

    /**
     * @return Ressio_HtmlOptimizer_Stream_CSSList
     */
    protected function createCssListNode()
    {
        return $this->cssNodes[] = new $this->classNodeCssList($this->di);
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_CSSList $cssListNode
     * @param HTML_Node|null $before
     */
    protected function insertCssListNode($cssListNode, $before)
    {
        $this->dom[] = $cssListNode;
        $cssListNode->index = key(array_slice($this->dom, -1, 1, true));
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_CSSList $cssListNode
     * @param array $data
     */
    protected function appendCssList($cssListNode, $data)
    {
        $cssListNode->styleList[] = $data;
    }

    /**
     * @param string $buffer
     * @return void
     */
    protected function htmlIterate($buffer)
    {
        $config = $this->config;

        $mergeSpace = $config->html->mergespace;
        $pos = 0;

        $node = new Ressio_HtmlOptimizer_Stream_NodeWrapper('');
        while (preg_match(static::TAG_REGEX, $buffer, $matches, PREG_OFFSET_CAPTURE, $pos)) {
            $start = $matches[0][1];
            $tag = substr($buffer, $pos, $start - $pos);
            if ($mergeSpace) {
                $tag = preg_replace('/(?<= ) +/', '', strtr($tag, "\n\r\t\f", '    '));
            }
            $this->dom[] = $tag;
            if ($this->breakJsNextNode && strpos($tag, '<') !== false) {
                $this->breakJs();
            }

            $pos = $start;
            $end = strpos($buffer, '>', $start + 1);
            if ($end === false) {
                break;
            }
            $tagName = strtolower($matches[1][0]);
            $tagName_uc = strtoupper($tagName);
            if (strncmp($tagName, '!--', 3) === 0) {
                $tag = $tagName;
                $end = $start + 3;
            } else {
                $tag = substr($buffer, $start, $end - $start + 1);
            }

            $attributes = array();

            $attr_pos = $start + strlen($tagName) + 1;
            if ($attr_pos < $end && $tagName[0] !== '!') {
                $attributes = $this->parseAttributes(substr($buffer, $attr_pos, $end - $attr_pos));
            }

            $node->tagName = $tagName;
            $node->attributes =& $attributes;
            $node->prepend = '';
            $node->tag = $tag;
            $node->content = false;
            $node->append = '';

            if (isset(self::$block_end[$tagName])) {
                $block_end = strpos($buffer, self::$block_end[$tagName], $end + 1);
                if ($block_end === false) {
                    break;
                }
                $node->content = substr($buffer, $end + 1, $block_end - $end - 1);
                $block_end += strlen(self::$block_end[$tagName]) - 1;
            } else {
                $block_end = $end;
            }

            if ($config->html->rules_safe_exclude && $this->matchExcludeRule($node, $config->html->rules_safe_exclude)) {
                $this->dom[] = substr($buffer, $start, $block_end - $start + 1);
                $pos = $block_end + 1;
                continue;
            }

            $this->dispatcher->triggerEvent('HtmlIterateNodeBefore', array($this, $node, $tagName));
            $this->dispatcher->triggerEvent("HtmlIterateTag{$tagName_uc}Before", array($this, $node));
            if ($node->tag === null) {
                $pos = $end + 1;
                continue;
            }

            switch ($tagName) {
                case '!--[if': // IE conds
                    $remove = false;
                    if ($config->html->removeiecond) {
                        $vendor = $this->di->deviceDetector->vendor();
                        // if IE browser
                        $remove = ($vendor !== 'ms' && $vendor !== 'unknown');
                    }
                    if (!$remove) {
                        $this->dom[] = substr($buffer, $start, $block_end - $start + 1);
                        $this->breakCss();
                        $this->breakJs(self::JS_MODE_ALL);
                    }
                    $node->tag = null;
                    break;

                case '![if': // IE conds
                    $remove = false;
                    if ($config->html->removeiecond) {
                        $vendor = $this->di->deviceDetector->vendor();
                        // if IE browser
                        $remove = ($vendor !== 'ms' && $vendor !== 'unknown');
                    }
                    if ($remove) {
                        $this->dom[] = substr($buffer, $end + 1, $block_end - $end - 10);
                    } else {
                        $this->dom[] = substr($buffer, $start, $block_end - $start + 1);
                        $this->breakCss();
                        $this->breakJs(self::JS_MODE_ALL);
                    }
                    $node->tag = null;
                    break;

                case '!--':
                    // remove comments
                    if (!$config->html->removecomments || $block_end - $start <= 6 || $buffer[$start + 4] === '!') {
                        $this->dom[] = substr($buffer, $start, $block_end - $start + 1);
                    }
                    $node->tag = null;
                    break;

                case '!doctype':
                    if (strpos($node->tag, 'DTD HTML')) {
                        $this->doctype = self::DOCTYPE_HTML4;
                    } elseif (strpos($node->tag, 'DTD XHTML')) {
                        $this->doctype = self::DOCTYPE_XHTML;
                    }
                    $this->dom[] = $node->prepend;
                    $this->dom[] = $node->tag;
                    $this->dom[] = $node->append;
                    $node->tag = null;
                    break;

                case 'base':
                    // save base href (use first tag only)
                    if (!$this->baseFound && isset($attributes['href'])) {
                        $base = $attributes['href'];
                        if (substr($base, -1) !== '/') {
                            $base = dirname($base);
                            if ($base === '.') {
                                $base = '';
                            }
                            $base .= '/';
                        }
                        $this->urlRewriter->setBase($base);
                        $attributes['href'] = $this->urlRewriter->getBase();
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
                    $this->dom[] = $node->prepend;
                    $this->dom[] = $node->tag;
                    $this->dom[] = $node->append;
                    $node->tag = null;
                    break;

                case '/body':
                    if ($this->lastJsNode !== null) {
                        // move movable scripts to the end
                        $index = $this->lastJsNode->index;
                        unset($this->dom[$index]);
                        $this->dom[] = $this->lastJsNode;
                        // array_key_last
                        $this->lastJsNode->index = key(array_slice($this->dom, -1, 1, true));
                    }
                    /** @fallthrough */
                case '/head':
                    // add empty script and style nodes
                    if ($this->lastAsyncCssNode === null) {
                        $cssNode = new $this->classNodeCssList($this->di);
                        $this->dom[] = $this->cssNodes[] = $this->lastCssNode = $this->lastAsyncCssNode = $cssNode;
                        // array_key_last
                        $cssNode->index = key(array_slice($this->dom, -1, 1, true));
                    }
                    if ($this->lastAsyncJsNode === null) {
                        $jsNode = new $this->classNodeJsList($this->di);
                        $this->dom[] = $this->jsNodes[] = $this->lastAsyncJsNode = $jsNode;
                        // array_key_last
                        $jsNode->index = key(array_slice($this->dom, -1, 1, true));
                    }
                    break;

                case 'img':
                    if ($this->noscriptCounter) {
                        break;
                    }
                    if ($config->img->rules_minify_exclude && $this->matchExcludeRule($node, $config->img->rules_minify_exclude)) {
                        break;
                    }

                    $hasSrc = isset($attributes['src']);
                    $hasSrcset = isset($attributes['srcset']);
                    $src_orig = $hasSrc ? $node->getAttribute('src') : null;

                    if ($hasSrc && $config->img->minify) {
                        $attributes['src'] = $this->imgSrcOptimize($src_orig);
                    }

                    if ($hasSrcset && ($config->img->minify || $config->html->urlminify)) {
                        $attributes['srcset'] = $this->imgSrcsetOptimize($attributes['srcset']);
                    }

                    if ($hasSrc && !$hasSrcset && $config->img->srcsetgeneration) {
                        $srcset = $this->imgSrcsetGenerate($src_orig, $attributes['src']);
                        if ($srcset !== null) {
                            $attributes['srcset'] = $srcset;
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
                    if (isset($attributes['poster']) && $config->img->minify) {
                        $attributes['poster'] = $this->imgSrcOptimize($attributes['poster']);
                    }
                    break;

                case 'input':
                    if ($this->noscriptCounter) {
                        break;
                    }
                    if ($config->img->rules_minify_exclude && $this->matchExcludeRule($node, $config->img->rules_minify_exclude)) {
                        break;
                    }
                    if ($config->img->minify && isset($attributes['src'], $attributes['type']) && $attributes['type'] === 'image') {
                        $attributes['src'] = $this->imgSrcOptimize($attributes['src']);
                    }
                    break;

                case 'picture':
                    $this->pictureCounter++;
                    break;

                case '/picture':
                    if ($this->pictureCounter) {
                        $this->pictureCounter--;
                    }
                    break;

                case 'source':
                    if ($this->pictureCounter && !$this->noscriptCounter) {
                        if (isset($attributes['srcset']) && $config->img->minify) {
                            $attributes['srcset'] = $this->imgSrcsetOptimize($attributes['srcset']);
                        }
                    }
                    break;

                case 'script':
                    if ($this->noscriptCounter) {
                        // scripts aren't executed in the noscript context, so it's safe to remove them
                        $node->tag = null;
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

                    if (isset($attributes['type']) && $attributes['type'] === 'module') {
                        if (isset($attributes['async'])) {
                            // async module may depend on previous scripts (non-async ones are always deferred)
                            $this->breakJs();
                        } else {
                            // break defer js sequence to preserve ordering
                            $this->breakJs(self::JS_MODE_DEFER);
                        }
                        break;
                    }

                    if (isset($attributes['nomodule'])) {
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
                        $attributes['async'] = false;
                    }
                    if (
                        ($config->js->forcedefer &&
                            !($config->js->rules_defer_exclude && $this->matchExcludeRule($node, $config->js->rules_defer_exclude))
                        ) || ($config->js->rules_defer_include && $this->matchExcludeRule($node, $config->js->rules_defer_include))
                    ) {
                        $attributes['defer'] = false;
                    }

                    // break if there attributes other than type=text/javascript, defer, async, integrity
                    if (count($attributes)) {
                        if ($config->js->checkattributes) {
                            $attributes_copy = $attributes;
                            if (isset($attributes_copy['type'], $this->jsMime[$attributes['type']])) {
                                unset($attributes_copy['type']);
                                if ($config->html->removedefattr) {
                                    unset($attributes['type']);
                                }
                            }
                            if (isset($attributes_copy['language']) && strcasecmp($attributes_copy['language'], 'javascript') === 0) {
                                unset($attributes_copy['language']);
                                if ($config->html->removedefattr) {
                                    unset($attributes['language']);
                                }
                            }
                            if (isset($attributes_copy['nonce']) && $attributes_copy['nonce'] === $config->js->nonce) {
                                unset($attributes_copy['nonce']);
                            }
                            unset($attributes_copy['defer'], $attributes_copy['async'], $attributes_copy['src'], $attributes_copy['integrity']);
                            if (count($attributes_copy) > 0) {
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
                    if ($this->doctype !== self::DOCTYPE_HTML5 && !isset($attributes['type'])) {
                        $attributes['type'] = 'text/javascript';
                    }

                    if (isset($attributes['src'])) { // external
                        if ($config->js->rules_merge_exclude && $this->matchExcludeRule($node, $config->js->rules_merge_exclude)) {
                            $merge = false;
                        } elseif ($config->js->rules_merge_include && $this->matchExcludeRule($node, $config->js->rules_merge_include)) {
                            $merge = true;
                        } else {
                            $merge = $config->js->merge;
                        }

                        if ($merge) {
                            $src = $attributes['src'];
                            $srcFile = $this->urlRewriter->urlToFilepath($src);
                            $merge = ($srcFile !== null) && (pathinfo($srcFile, PATHINFO_EXTENSION) === 'js') && $this->di->filesystem->isFile($srcFile);
                        }

                        if ($merge) {
                            $this->addJs($attributes, null);
                            $node->tag = null;
                        } else {
                            $mode = $this->getJsMode($attributes);
                            $this->breakJs($mode);
                            $this->addJs($attributes, null);
                            $node->tag = null;
                            $this->breakJs($mode);
                        }
                    } else { // inline
                        $scriptBlob = $this->scriptCleanInlined($node->content);
                        $node->content = $scriptBlob;

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
                            $this->addJs($attributes, $node->content);
                            $node->tag = null;
                        } else {
                            $mode = $this->getJsMode($attributes);
                            $this->breakJs($mode);
                            $this->addJs($attributes, $node->content);
                            $node->tag = null;
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
                    $this->noscriptCounter++;

                    break;

                case '/noscript':
                    if ($this->noscriptCounter) {
                        $this->noscriptCounter--;
                    }
                    break;

                case 'link':
                    // break if there attributes other than type=text/css, rel=stylesheet, href
                    if (!isset($attributes['rel'], $attributes['href']) || $attributes['rel'] !== 'stylesheet') {
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

                    if ($config->css->checklinkattributes) {
                        $attributes_copy = $attributes;
                        if (isset($attributes_copy['type']) && $attributes_copy['type'] === 'text/css') {
                            unset($attributes_copy['type']);
                        }
                        unset($attributes_copy['rel'], $attributes_copy['media'], $attributes_copy['href']);
                        if (count($attributes_copy) > 0) {
                            if (!preg_match('#^(https?:)?//fonts\.googleapis\.com/css#', $attributes['href'])) {
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
                    if ($this->doctype !== self::DOCTYPE_HTML5 && !isset($attributes['type'])) {
                        $attributes['type'] = 'text/css';
                    }

                    if ($config->css->rules_merge_exclude && $this->matchExcludeRule($node, $config->css->rules_merge_exclude)) {
                        $merge = false;
                    } elseif ($config->css->rules_merge_include && $this->matchExcludeRule($node, $config->css->rules_merge_include)) {
                        $merge = true;
                    } else {
                        $merge = $config->css->merge;
                    }

                    if ($merge) {
                        $src = $attributes['href'];
                        $srcFile = $this->urlRewriter->urlToFilepath($src);
                        $merge = ($srcFile !== null) && (pathinfo($srcFile, PATHINFO_EXTENSION) === 'css') && $this->di->filesystem->isFile($srcFile);
                    }

                    if ($merge) {
                        $this->addCss($attributes, null);
                        $node->tag = null;
                    } else {
                        if (!preg_match('#^(https?:)?//fonts\.googleapis\.com/css#', $attributes['href'])) {
                            $this->breakCss();
                        }
                    }

                    if ($config->css->rules_merge_stopgroup && $this->matchExcludeRule($node, $config->css->rules_merge_stopgroup)) {
                        $this->breakCss();
                    }
                    break;

                case 'style':
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

                    if ($config->css->checkstyleattributes) {
                        $attributes_copy = $attributes;
                        // break if there attributes other than type=text/css
                        if (isset($attributes_copy['type']) && $attributes_copy['type'] === 'text/css') {
                            unset($attributes_copy['type']);
                        }
                        if (isset($attributes_copy['nonce']) && $attributes_copy['nonce'] === $config->css->nonce) {
                            unset($attributes_copy['nonce']);
                        }
                        unset($attributes_copy['media']);
                        if (count($attributes_copy) > 0) {
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

                    // set type=text/css in html4 and remove in html5
                    if ($this->doctype !== self::DOCTYPE_HTML5 && !isset($attributes['type'])) {
                        $attributes['type'] = 'text/css';
                    }
                    // remove the media attribute if it is empty or "all"
                    if (isset($attributes['media']) && $config->html->removedefattr) {
                        $media = $attributes['media'];
                        // $media = $this->filterMedia($media);
                        if ($media === '' || $media === 'all') {
                            unset($attributes['media']);
                        }
                    }
                    // css break point if scoped=... attribute
                    if (isset($attributes['scoped'])) {
                        $this->breakCss();
                        break;
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
                        $this->addCss($attributes, $node->content);
                        $node->tag = null;
                    } else {
                        $this->breakCss();
                    }

                    if ($config->css->rules_merge_stopgroup && $this->matchExcludeRule($node, $config->css->rules_merge_stopgroup)) {
                        $this->breakCss();
                    }
                    break;

                case 'pre': // bypass pre tags to don't merge spaces inside
                case 'math':
                case 'svg':
                case 'template':
                case 'textarea':
                    break;
            }

            if ($node->tag !== null) {
                $this->dispatcher->triggerEvent('HtmlIterateTag' . $tagName_uc, array($this, $node));
            }

            if (count($node->attributes)) {
                if (isset($node->attributes['onload']) || isset($node->attributes['onerror'])) {
                    $this->breakJs();
                }

                // minify uri in attributes
                if ($config->html->urlminify && isset($this->uriAttrs[$tagName]) &&
                    // allow full URL in <link> tags except of stylesheet (e.g. canonical, amphtml, etc.)
                    !($tagName === 'link' && isset($node->attributes['rel']) && $node->attributes['rel'] !== 'stylesheet')
                ) {
                    foreach ($this->uriAttrs[$tagName] as $attrName) {
                        if (isset($node->attributes[$attrName])) {
                            $uri = $node->attributes[$attrName];
                            if (!empty($uri) && strncmp($uri, 'data:', 5) !== 0) {
                                $node->attributes[$attrName] = $this->urlRewriter->minify($uri);
                            }
                        }
                    }
                }
            }

            if ($tagName[0] !== '/') {
                $this->dispatcher->triggerEvent("HtmlIterateTag{$tagName_uc}After", array($this, $node));
            }
            if ($tagName[0] === '/' || isset($this->tags_selfclose[$tagName])) {
                $this->dispatcher->triggerEvent('HtmlIterateTag' . ltrim($tagName_uc, '/') . 'AfterEnd', array($this, $node));
                $this->dispatcher->triggerEvent('HtmlIterateNodeAfter', array($this, $node, $tagName));
            }

            if ($node->tag !== null) {
                $this->dom[] = $node->prepend;
                $this->dom[] = $this->tagToString($tagName, $node->attributes, $node->content);
                $this->dom[] = $node->append;
            }

            if ($this->breakJsNextNode && $tagName !== 'script') {
                $this->breakJs();
            }
            $pos = $block_end + 1;
        }

        $last_piece = substr($buffer, $pos);
        if ($last_piece !== false) {
            if ($mergeSpace) {
                $last_piece = preg_replace('/(?<= ) +/', '', strtr($last_piece, "\n\r\t\f", '    '));
            }
            $this->dom[] = $last_piece;
        }
    }

    /**
     * @param array $attributes
     * @param string|null $blob
     * @return void
     */
    protected function addJs($attributes, $blob = null)
    {
        $inline = ($blob !== null);

        $async = isset($attributes['async']);
        $defer = isset($attributes['defer']);

        $mode = $this->getJsMode($attributes);
        /** @var Ressio_HtmlOptimizer_Stream_JSList $jsListNode */
        $jsListNode = $this->getJsListNode($mode);

        if ($jsListNode) {
            if ($mode !== self::JS_MODE_DEFER) {
                unset($this->dom[$jsListNode->index]);
                $this->insertJsListNode($jsListNode, null);
            }
        } else {
            $jsListNode = $this->createJsListNode();
            $this->setJsListNode($mode, $jsListNode);
            $this->insertJsListNode($jsListNode, null);
        }

        $this->appendJsList($jsListNode, $inline ? array(
            'type' => 'inline',
            'script' => $blob,
            'async' => $async,
            'defer' => $defer
        ) : array(
            'type' => 'ref',
            'src' => $attributes['src'],
            'async' => $async,
            'defer' => $defer
        ));
    }

    /**
     * @param array $attributes
     * @param string|null $blob
     * @return void
     */
    protected function addCss($attributes, $blob = null)
    {
        $inline = ($blob !== null);

        $cssListNode = $this->lastCssNode;

        if ($cssListNode) {
            unset($this->dom[$cssListNode->index]);
        } else {
            $this->lastCssNode = $cssListNode = $this->createCssListNode();
        }
        $this->insertCssListNode($cssListNode, null);

        $media = isset($attributes['media']) ? $attributes['media'] : 'all';

        $this->appendCssList($cssListNode, $inline ? array(
            'type' => 'inline',
            'style' => $blob,
            'media' => $media
        ) : array(
            'type' => 'ref',
            'src' => $attributes['href'],
            'media' => $media
        ));
    }

    /**
     * @param string $tagName
     * @param array<string|false> $attributes
     * @param string|false|null $content
     * @return string
     */
    protected function tagToString($tagName, $attributes, $content)
    {
        if ($tagName === '!--') {
            return "<!--{$content}-->";
        }
        if ($tagName[0] === '/') {
            return "<{$tagName}>";
        }
        $out = "<{$tagName}";
        if (is_array($attributes)) {
            foreach ($attributes as $key => $value) {
                if ($value === false) {
                    $out .= " $key";
                } else {
                    $q = (strpos($value, '"') === false) ? '"' : "'";
                    $out .= " $key=$q$value$q";
                }
            }
        }
        if ($content === false && $this->doctype === self::DOCTYPE_XHTML) {
            $out .= '/';
        }
        $out .= '>';
        if ($content !== false) {
            if ($content !== null) {
                $out .= $content;
            }
            $out .= "</{$tagName}>";
        }
        return $out;
    }

    /**
     * @param $str
     * @return array
     * @note Used by AboveTheFoldCss plugin
     */
    public function parseAttributes($str)
    {
        preg_match_all('#\s+([a-z0-9_\-]+)(?:\s*=\s*(?|"([^"]*)"|\'([^\']*)\'|([^"\'\s]+)))?#i', $str, $matches, PREG_SET_ORDER);
        $attributes = array();
        foreach ($matches as $match) {
            $name = $match[1];
            if (!isset($attributes[$name])) {
                $attributes[$name] = isset($match[2]) ? $match[2] : false;
            }
        }
        return $attributes;
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_NodeWrapper $node
     * @return string
     */
    public function nodeToString($node)
    {
        return $this->tagToString($node->tagName, $node->attributes, $node->content);
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_NodeWrapper $node
     * @return void
     */
    public function nodeDetach($node)
    {
        $node->tag = null;
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_NodeWrapper $node
     * @return bool
     */
    public function nodeIsDetached($node)
    {
        return $node->tag === null;
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_NodeWrapper $node
     * @return bool
     */
    public function nodeIsElement($node)
    {
        return true;
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_NodeWrapper $node
     * @param string $text
     * @return void
     */
    public function nodeSetInnerText($node, $text)
    {
        $node->content = $text;
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_NodeWrapper $node
     * @return string
     */
    public function nodeGetInnerText($node)
    {
        return $node->content;
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_NodeWrapper $node
     * @param string $tag
     * @param array $attribs
     * @return void
     */
    public function nodeWrap($node, $tag, $attribs = null)
    {
        $prepend = "<{$tag}";
        if ($attribs !== null) {
            foreach ($attribs as $key => $value) {
                if ($value === false) {
                    $prepend .= " $key";
                } else {
                    $q = (strpos($value, '"') === false) ? '"' : "'";
                    $prepend .= " $key=$q$value$q";
                }
            }
        }
        $prepend .= '>';
        $node->prepend .= $prepend;

        $node->append = "</{$tag}>{$node->append}";
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_NodeWrapper $node
     * @param string $tag
     * @param array $attribs
     * @param string $content
     * @return void
     */
    public function nodeInsertBefore($node, $tag, $attribs = null, $content = null)
    {
        $node->prepend .= $this->tagToString($tag, $attribs, $content);
    }

    /**
     * @param Ressio_HtmlOptimizer_Stream_NodeWrapper $node
     * @param string $tag
     * @param array $attribs
     * @param string $content
     * @return void
     */
    public function nodeInsertAfter($node, $tag, $attribs = null, $content = null)
    {
        $node->append = $this->tagToString($tag, $attribs, $content) . $node->append;
    }

    /**
     * @param array (string $tag, array $attribs, string $content) ...$nodedata
     * @return bool return false if no <head> found
     */
    public function prependHead($nodedata)
    {
        if ($this->prependHeadOffset) {
            $injectOffset = $this->prependHeadOffset;
        } else {
            $injectOffset = 0;
            $count = count($this->dom);
            // step#1: find <head> node
            while ($injectOffset < $count) {
                if (!isset($this->dom[$injectOffset])) {
                    $injectOffset++;
                    continue;
                }
                $node = $this->dom[$injectOffset];
                $injectOffset++;
                if (is_string($node) && strncmp($node, '<head', 5) === 0) {
                    break;
                }
            }
            while ($injectOffset < $count) {
                if (isset($this->dom[$injectOffset])) {
                    $node = $this->dom[$injectOffset];
                    if (is_string($node) && trim($node) !== '') {
                        if (strncasecmp($node, '<title', 6) === 0) {
                            $injectOffset++;
                            while ($injectOffset < $count && (!isset($this->dom[$injectOffset]) || !preg_match('#^</(?:title|head|html)\b#i', $this->dom[$injectOffset]))) {
                                $injectOffset++;
                            }
                            $injectOffset++;
                            break;
                        }
                        if (!preg_match('/^<(?:meta|!--)/i', $node)) {
                            break;
                        }
                    }
                }
                $injectOffset++;
            }
            $this->prependHeadOffset = $injectOffset;
        }

        if ($injectOffset) {
            $html = '';
            foreach (func_get_args() as $arg) {
                list($tag, $attribs, $content) = $arg;
                $html .= $this->tagToString($tag, $attribs, $content);
            }
            $this->dom[$injectOffset - 1] .= $html;
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
