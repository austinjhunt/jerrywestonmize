<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Plugin_InlineJsCss extends Ressio_Plugin
{
    /**
     * @param Ressio_DI $di
     * @param null|stdClass $params
     */
    public function __construct($di, $params = null)
    {
        parent::__construct($di);
        $this->loadConfig(__DIR__ . '/config.json', $params);
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @return void
     */
    public function onHtmlIterateBefore($event, $optimizer)
    {
        if (!isset($_COOKIE[$this->params->cookie])) {
            $this->di->httpHeaders->setCookie($this->params->cookie, '1', time() + $this->params->cookietime, '/', $_SERVER['HTTP_HOST'], false, true);
            if ($this->params->css) {
                $this->di->dispatcher->addListener('HtmlIterateTagLINK', array($this, 'processHtmlIterateTagLINK'));
                $this->di->dispatcher->addListener('CssCombinerNodeList', array($this, 'processCssCombinerNodeList'), -10);
            }
            if ($this->params->js) {
                $this->di->dispatcher->addListener('HtmlIterateTagScript', array($this, 'processHtmlIterateTagScript'));
                $this->di->dispatcher->addListener('JsCombinerNodeList', array($this, 'processJsCombinerNodeList'), -10);
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function processHtmlIterateTagLINK($event, $optimizer, $node)
    {
        if ($optimizer->nodeIsDetached($node)) {
            return;
        }

        if ($node->hasAttribute('rel') && $node->hasAttribute('href')
            && $node->getAttribute('rel') === 'stylesheet'
            && (!$node->hasAttribute('type') || $node->getAttribute('type') === 'text/css')
            && !$node->hasAttribute('onload')
        ) {
            $media = $node->hasAttribute('media') ? $node->getAttribute('media') : 'all';
            if ($media !== 'print') {
                $href = $node->getAttribute('href');
                $content = $this->getStyles($href);
                if ($content !== false) {
                    $attrs = array();
                    if ($media !== 'all') {
                        $attrs['media'] = $media;
                    }
                    $optimizer->nodeInsertBefore($node, 'style', $attrs, $content);
                    $optimizer->nodeInsertBefore($node, 'link', array('rel' => 'prefetch-delayed', 'href' => $href));
                    $optimizer->nodeDetach($node);
                }
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param stdClass $wrapper
     * @return void
     */
    public function processCssCombinerNodeList($event, $wrapper)
    {
        $styleList = array();
        foreach ($wrapper->nodes as $node) {
            /** Ressio_NodeWrapper $node */
            if ($node->tagName === 'link') {
                $media = isset($node->attributes['media']) ? $node->attributes['media'] : 'all';
                if ($media !== 'print') {
                    $href = $node->getAttribute('href');
                    $content = $this->getStyles($href);
                    if ($content !== false) {
                        $attrs = array();
                        if ($media !== 'all') {
                            $attrs['media'] = $media;
                        }
                        $node = new Ressio_NodeWrapper('style', $content, $attrs);
                        $styleList[] = new Ressio_NodeWrapper('link', null, array('rel' => 'prefetch-delayed', 'href' => $href));
                    }
                }
            }
            $styleList[] = $node;
        }
        $wrapper->nodes = $styleList;
    }

    /**
     * @param string $href
     * @return string|false
     */
    private function getStyles($href)
    {
        $filename = $this->di->urlRewriter->urlToFilepath($href);
        if ($filename === null) {
            return false;
        }

        $deps = array(
            'inlinecss',
            $filename,
            $this->di->filesystem->getModificationTime($filename),
            $this->config->var->imagenextgenformat,
        );

        $cache = $this->di->cache;
        $cache_id = $cache->id($deps, 'inlinecss');
        $content = $cache->getOrLock($cache_id);
        if (is_string($content)) {
            $content = json_decode($content);
        } else {
            $content = $this->di->filesystem->getContents($filename);
            if ($content !== false) {
                $abs_url = $this->di->urlRewriter->filepathToUrl($filename);
                $content = $this->di->cssRelocator->run($content, dirname($abs_url), '/');
            }
            $cache->storeAndUnlock($cache_id, json_encode($content));
        }

        return $content;
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function processHtmlIterateTagSCRIPT($event, $optimizer, $node)
    {
        if ($optimizer->nodeIsDetached($node)) {
            return;
        }

        if ($node->hasAttribute('src')
            && (!$node->hasAttribute('type') || $optimizer->isJavaScriptMime($node->getAttribute('type')))
            && !$node->hasAttribute('nomodule') && !$node->hasAttribute('onload')
        ) {
            $src = $node->getAttribute('src');
            $filename = $this->di->urlRewriter->urlToFilepath($src);
            if ($filename !== null) {
                $content = $this->di->filesystem->getContents($filename);
                if ($content !== false) {
                    $optimizer->nodeInsertBefore($node, 'link', array('rel' => 'prefetch-delayed', 'href' => $src));
                    $attrs = array();
                    if ($node->hasAttribute('defer')) {
                        $attrs['defer'] = false;
                    }
                    if ($node->hasAttribute('async')) {
                        $attrs['async'] = false;
                    }
                    $optimizer->nodeInsertBefore($node, 'script', $attrs, $content);
                    $optimizer->nodeDetach($node);
                }
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param stdClass $wrapper
     * @return void
     */
    public function processJsCombinerNodeList($event, $wrapper)
    {
        $scriptList = array();
        foreach ($wrapper->nodes as $node) {
            /** Ressio_NodeWrapper $node */
            if ($node->hasAttribute('src')) {
                $src = $node->getAttribute('src');
                $filename = $this->di->urlRewriter->urlToFilepath($src);
                if ($filename !== null) {
                    $content = $this->di->filesystem->getContents($filename);
                    if ($content !== false) {
                        $attrs = array();
                        if ($node->hasAttribute('async')) {
                            $attrs['async'] = false;
                        } elseif ($node->hasAttribute('defer')) {
                            $attrs['defer'] = false;
                            $attrs['src'] = 'data:text/javascript,' . rawurlencode($content);
                            $content = '';
                        }
                        $node = new Ressio_NodeWrapper('script', $content, $attrs);
                        $scriptList[] = new Ressio_NodeWrapper('link', null, array('rel' => 'prefetch-delayed', 'href' => $src));
                    }
                }
            }
            $scriptList[] = $node;
        }
        $wrapper->nodes = $scriptList;
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @return void
     */
    public function onHtmlIterateAfter($event, $optimizer)
    {
        $scriptData = file_get_contents(__DIR__ . '/js/prefetch-delayed.min.js');
        $optimizer->appendScriptDeclaration($scriptData, array('defer' => true));
    }
}
