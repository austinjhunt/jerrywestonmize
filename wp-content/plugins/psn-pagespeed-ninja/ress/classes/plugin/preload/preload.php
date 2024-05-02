<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();


class Ressio_Plugin_Preload extends Ressio_Plugin
{
    /** @var array */
    public $preloads = array(
        'style' => array(),
        'font' => array(),
        'script' => array(),
        'image' => array()
    );
    /** @var array */
    public $modulepreloads = array();

    /**
     * @param Ressio_DI $di
     * @param ?stdClass $params
     */
    public function __construct($di, $params)
    {
        $params = $this->loadConfig(__DIR__ . '/config.json', $params);

        parent::__construct($di, $params);

        foreach ($params->style as $url) {
            $this->addURL($url, 'style');
        }
        foreach ($params->font as $url) {
            $this->addURL($url, 'font', array('crossorigin' => false));
        }
        foreach ($params->script as $url) {
            $this->addURL($url, 'script');
        }
        foreach ($params->image as $url) {
            $this->addURL($url, 'image');
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @return void
     */
    public function onHtmlBeforeStringify($event, $optimizer)
    {
        if (!count($this->preloads)) {
            return;
        }

        $tags = array();

        foreach ($this->preloads as $as => $items) {
            foreach ($items as $href => $extras) {
                $attributes = array('rel' => 'preload', 'href' => $href, 'as' => $as);
                if ($extras !== false) {
                    $attributes += $extras;
                }
                //if ($as === 'font') {
                //    $attributes['crossorigin'] = false;
                //    $type = pathinfo(parse_url($href, PHP_URL_PATH), PATHINFO_EXTENSION);
                //    $attributes['type'] = "font/$type"; // valid for otf/ttf/woff/woff2 (see //www.iana.org/assignments/media-types/media-types.xhtml)
                //}
                $tags[] = array('link', $attributes, false);
            }
        }

        foreach ($this->modulepreloads as $href => $as) {
            if (strpos($href, '//') === false) { // skip external modules (@todo need extra check for CORS etc.)
                $attributes = array('rel' => 'modulepreload', 'href' => $href);
                $tags[] = array('link', $attributes, false);
            }
        }

        if ($this->params->insertlink && count($tags)) {
            $optimizer->prependHead(...$tags);
        }

        if ($this->params->linkheader) {

            foreach ($tags as $tag) {
                $attrs = $tag[1];
                $href = $attrs['href'];
                if (strpos($href, '//') !== false) {
                    if (isset($attrs['as'])) {
                        // preload
                        $header = "Link: <{$href}>; rel={$attrs['rel']}; as={$attrs['as']}";
                    } else {
                        // modulepreload
                        $header = "Link: <{$href}>; rel={$attrs['rel']}";
                    }
                    $this->di->httpHeaders->setHeader($header, false);
                }
            }
        }
    }

    /**
     * @param string $url
     * @param string $as
     * @param string[]|false $extras
     * @return void
     */
    public function addURL($url, $as, $extras = false)
    {
        if (strncmp($url, 'data:', 5) === 0) {
            return;
        }
        $this->preloads[$as][$url] = $extras;
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagSCRIPTAfter($event, $optimizer, $node)
    {
        if ($optimizer->isNoscriptState() || $optimizer->nodeIsDetached($node)) {
            return;
        }
        if ($node->hasAttribute('src') && !$node->hasAttribute('nomodule')) {
            if ($node->hasAttribute('type') && $node->getAttribute('type') === 'module') {
                $this->modulepreloads[$node->getAttribute('src')] = 'script';
            } elseif (!$node->hasAttribute('type') || $node->getAttribute('type') === 'text/javascript') {
                $this->addURL($node->getAttribute('src'), 'script');
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagLINKAfter($event, $optimizer, $node)
    {
        if ($optimizer->isNoscriptState() || $optimizer->nodeIsDetached($node)) {
            return;
        }
        if (
            $node->hasAttribute('href') &&
            $node->hasAttribute('rel') && $node->getAttribute('rel') === 'stylesheet' &&
            (!$node->hasAttribute('type') || $node->getAttribute('type') === 'text/css')
        ) {
            $this->addURL($node->getAttribute('href'), 'style');
        }
    }

    /**
     * @param Ressio_Event $event
     * @param stdClass $wrapper
     * @return void
     */
    public function onJsCombinerNodeList($event, $wrapper)
    {
        foreach ($wrapper->nodes as $node) {
            /** Ressio_NodeWrapper $node */
            if (isset($node->attributes['src'])) {
                $this->addURL($node->attributes['src'], 'script');
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param stdClass $wrapper
     * @return void
     */
    public function onCssCombinerNodeList($event, $wrapper)
    {
        foreach ($wrapper->nodes as $node) {
            /** Ressio_NodeWrapper $node */
            if (isset($node->attributes['rel'], $node->attributes['href']) && $node->attributes['rel'] === 'stylesheet') {
                $this->addURL($node->attributes['href'], 'style');
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param string $url
     * @param string $type
     * @param string[]|false $extra_attributes
     * @return void
     */
    public function onPreloadResource($event, $url, $type, $extra_attributes = false)
    {
        $this->addURL($url, $type, $extra_attributes);
    }
}