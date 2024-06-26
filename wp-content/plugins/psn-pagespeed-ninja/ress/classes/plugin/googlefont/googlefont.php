<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Plugin_GoogleFont extends Ressio_Plugin
{
    /** @var string[] */
    protected $googlefonts = array();

    /** @var string[] */
    protected $bunnyfonts = array();

    /**
     * @param Ressio_DI $di
     * @param ?stdClass $params
     */
    public function __construct($di, $params = null)
    {
        parent::__construct($di);
        $this->loadConfig(__DIR__ . '/config.json', $params);
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagLINKBefore($event, $optimizer, $node)
    {
        if ($node->hasAttribute('rel') && $node->hasAttribute('href') && $node->getAttribute('rel') === 'stylesheet'
            && (!$node->hasAttribute('type') || $node->getAttribute('type') === 'text/css')
        ) {
            $url = $node->getAttribute('href');

            if (preg_match('#^(?:https?:)?//fonts\.googleapis\.com/css\?family=([^&]+)#', $url, $match)) {
                $fonts = explode('|', $match[1]);
                $this->googlefonts = array_merge($this->googlefonts, $fonts);
                $optimizer->nodeDetach($node);
            } elseif (preg_match('#^(?:https?:)?//fonts\.bunny\.net/css\?family=([^&]+)#', $url, $match)) {
                $fonts = explode('|', $match[1]);
                $this->bunnyfonts = array_merge($this->bunnyfonts, $fonts);
                $optimizer->nodeDetach($node);
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param Ressio_HtmlOptimizer_Base $optimizer
     * @return void
     */
    public function onHtmlBeforeStringify($event, $optimizer)
    {
        $isHtml5 = ($optimizer->doctype === IRessio_HtmlOptimizer::DOCTYPE_HTML5);
        $query_delim = $optimizer->getQueryDelim();

        if (count($this->googlefonts) > 0) {
            $optimizer->prependHead(
                //array('link', array('rel' => 'dns-prefetch', 'href' => '//fonts.googleapis.com'), false),
                array('link', array('rel' => 'dns-prefetch', 'href' => '//fonts.gstatic.com'), false),
                //array('link', array('rel' => 'preconnect', 'href' => '//fonts.googleapis.com'), false),
                array('link', array('rel' => 'preconnect', 'href' => '//fonts.gstatic.com', 'crossorigin' => false), false)
            );
            $attrs = $this->generateLinkTagAttrs('https://fonts.googleapis.com/css?family=', $this->googlefonts, $isHtml5, $query_delim);
            $this->injectLinkTag($optimizer, $attrs, $isHtml5);
        }

        if (count($this->bunnyfonts) > 0) {
            //$optimizer->prependHead(
            //    array('link', array('rel' => 'dns-prefetch', 'href' => '//fonts.bunny.net'), false),
            //    array('link', array('rel' => 'preconnect', 'href' => '//fonts.bunny.net'), false),
            //    array('link', array('rel' => 'preconnect', 'href' => '//fonts.bunny.net', 'crossorigin' => false), false)
            //);
            $attrs = $this->generateLinkTagAttrs('https://fonts.bunny.net/css?family=', $this->bunnyfonts, $isHtml5, $query_delim);
            $this->injectLinkTag($optimizer, $attrs, $isHtml5);
        }
    }

    /**
     * @param string $baseurl
     * @param string[] $fonts
     * @param bool $isHtml5
     * @param string $query_delim
     * @return string[]
     */
    private function generateLinkTagAttrs($baseurl, $fonts, $isHtml5, $query_delim)
    {
        $url = $baseurl . implode('%7C', $fonts);

        switch ($this->params->method) {
            case 'block':
                $url .= $query_delim . 'display=block';
                break;
            case 'swap':
                $url .= $query_delim . 'display=swap';
                break;
            case 'fallback':
                $url .= $query_delim . 'display=fallback';
                break;
            case 'optional':
                $url .= $query_delim . 'display=optional';
                break;
            case 'auto':
                break;
        }

        $attrs = array(
            'rel' => 'stylesheet',
            'href' => $url
        );
        if (!$isHtml5) {
            $attrs['type'] = 'text/css';
        }

        return $attrs;
    }

    /**
     * @param Ressio_HtmlOptimizer_Base $optimizer
     * @param string[] $attrs
     * @param bool $isHtml5
     * @return void
     */
    private function injectLinkTag($optimizer, $attrs, $isHtml5)
    {
        if ($this->params->async) {
            $stylesheet_attrs = $attrs;
            $stylesheet_attrs['media'] = 'print';
            $stylesheet_attrs['onload'] = "this.media='all'";

            $noscript_node = new Ressio_NodeWrapper('link', null, $attrs, $isHtml5 ? '' : '/');

            $optimizer->prependHead(
                array('link', $stylesheet_attrs, false),
                array('noscript', array(), (string)$noscript_node)
            );
        } else {
            $optimizer->prependHead(array('link', $attrs, false));
        }
    }
}