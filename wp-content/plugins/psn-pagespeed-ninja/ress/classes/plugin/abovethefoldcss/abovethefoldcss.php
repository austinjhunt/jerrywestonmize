<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Plugin_AboveTheFoldCSS extends Ressio_Plugin
{
    /** @var bool */
    protected $relayout = false;

    /**
     * @param Ressio_DI $di
     * @param null|stdClass $params
     */
    public function __construct($di, $params)
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
        $loadAboveTheFoldCSS = false;
        if (empty($this->params->cookie)) {
            $loadAboveTheFoldCSS = true;
        } elseif (!isset($_COOKIE[$this->params->cookie])) {
            $loadAboveTheFoldCSS = true;

            $this->di->httpHeaders->setCookie($this->params->cookie, '1', time() + $this->params->cookietime, '/', $_SERVER['HTTP_HOST'], false, true);
        }

        if ($loadAboveTheFoldCSS) {
            $this->di->dispatcher->addListener('HtmlIterateTagLINK', array($this, 'processHtmlIterateTagLINK'), IRessio_Dispatcher::ORDER_FIRST);
            $this->di->dispatcher->addListener('HtmlIterateTagSCRIPTBefore', array($this, 'processHtmlIterateTagSCRIPTBefore'));
            $this->di->dispatcher->addListener('HtmlIterateAfter', array($this, 'processHtmlIterateAfter'));
            $this->di->dispatcher->addListener('CssCombinerNodeList', array($this, 'processCssCombinerNodeList'), IRessio_Dispatcher::ORDER_FIRST);
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
                $optimizer->nodeInsertAfter($node, 'noscript', null, $optimizer->nodeToString($node));
                $node->setAttribute('rel', 'ress-css');
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function processHtmlIterateTagSCRIPTBefore($event, $optimizer, $node)
    {
        if ($this->relayout) {
            return;
        }

        if ($node->hasAttribute('type') && !$optimizer->isJavaScriptMime($node->getAttribute('type'))) {
            return;
        }

        if ($node->hasAttribute('src')) {
            $src = $node->getAttribute('src');
            if (strpos($src, 'masonry') !== false) {
                $this->relayout = true;
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @return void
     */
    public function processHtmlIterateAfter($event, $optimizer)
    {
        $scriptData = file_get_contents(__DIR__ . '/js/async_css_loader.min.js');
        $optimizer->appendScriptDeclaration($scriptData, array('defer' => true));

        if ($this->relayout) {
            $scriptData = file_get_contents(__DIR__ . '/js/relayout.min.js');
            $optimizer->appendScriptDeclaration($scriptData, array('defer' => true));
        }

        // Process CSS with image optimizer and FontDisplaySwap plugin
        $abovethefoldcss = $this->di->cssRelocator->run($this->params->abovethefoldcss);
        $optimizer->prependHead(array('style', array('id' => 'psn-critical-css'), $abovethefoldcss));

        /*
        $optimizer->prependHead(array('script', array(
            'defer' => false,
            'src' => 'data:text/javascript,' . rawurlencode('document.body.appendChild(document.getElementById("psn-critical-css"));'),
        ), ''));
        */
    }

    /**
     * @param Ressio_Event $event
     * @param stdClass $wrapper
     * @return void
     */
    public function processCssCombinerNodeList($event, $wrapper)
    {
        $noscript = '';
        foreach ($wrapper->nodes as $node) {
            /** Ressio_NodeWrapper $node */
            if ($node->tagName === 'link' && $node->attributes['rel'] === 'stylesheet') {
                $media = isset($node->attributes['media']) ? $node->attributes['media'] : 'all';
                if ($media !== 'print') {
                    $noscript .= $node;
                    $node->attributes['media'] = 'print';
                    $node->attributes['onload'] = "this.media='$media'";
                }
            }
        }
        if ($noscript !== '') {
            $wrapper->nodes[] = new Ressio_NodeWrapper('noscript', $noscript);
        }
    }
}