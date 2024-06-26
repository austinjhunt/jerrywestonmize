<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Plugin_DNSPrefetch extends Ressio_Plugin
{
    /**
     * @var array
     */
    public $domains_list;
    /**
     * @var string
     */
    public $current_domain;

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
     * @return array
     */
    public function getEventPriorities()
    {
        return array(
            'HtmlBeforeStringify' => -2
        );
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @return void
     */
    public function onHtmlIterateBefore($event, $optimizer)
    {
        $this->current_domain = $_SERVER['HTTP_HOST'];
        $this->domains_list = array();
        foreach ($this->params->domains as $domain) {
            $this->domains_list[$domain] = 1;
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @return void
     */
    public function onHtmlBeforeStringify($event, $optimizer)
    {
        if (!count($this->domains_list)) {
            return;
        }

        $tags = array();
        foreach (array_keys($this->domains_list) as $domain) {
            $tags[] = array('link', array('rel' => 'dns-prefetch', 'href' => '//' . $domain), false);
        }

        if (count($tags)) {
            $optimizer->prependHead(...$tags);
        }

        if ($this->params->linkheader) {
            foreach ($tags as $tag) {
                $attrs = $tag[1];
                $href = $attrs['href'];
                $this->di->httpHeaders->setHeader("Link: <{$href}>; rel=dns-prefetch", false);
            }
        }
    }

    /**
     * @param string $url
     * @return void
     */
    public function addDomainFromURL($url)
    {
        if (strpos($url, '//') === false) {
            return;
        }
        $domain = parse_url($url, PHP_URL_HOST);
        if ($domain !== null && $domain !== $this->current_domain && !isset($this->domains_list[$domain])) {
            $this->domains_list[$domain] = 1;
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagIMGAfter($event, $optimizer, $node)
    {
        if ($node->hasAttribute('src')) {
            $this->addDomainFromURL($node->getAttribute('src'));
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagSCRIPTAfter($event, $optimizer, $node)
    {
        if ($node->hasAttribute('src')) {
            $this->addDomainFromURL($node->getAttribute('src'));
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
        if ($node->hasAttribute('href') && $node->hasAttribute('rel')) {
            switch ($node->getAttribute('rel')) {
                case 'stylesheet':
                    $this->addDomainFromURL($node->getAttribute('href'));
                    break;
                case 'dns-prefetch':
                    $this->addDomainFromURL($node->getAttribute('href'));
                    $optimizer->nodeDetach($node);
                    break;
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagIFRAMEAfter($event, $optimizer, $node)
    {
        if ($node->hasAttribute('src')) {
            $this->addDomainFromURL($node->getAttribute('src'));
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagAUDIOAfter($event, $optimizer, $node)
    {
        if ($node->hasAttribute('src')) {
            $this->addDomainFromURL($node->getAttribute('src'));
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagVIDEOAfter($event, $optimizer, $node)
    {
        if ($node->hasAttribute('src')) {
            $this->addDomainFromURL($node->getAttribute('src'));
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagSOURCEAfter($event, $optimizer, $node)
    {
        if ($node->hasAttribute('src')) {
            $this->addDomainFromURL($node->getAttribute('src'));
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagTRACKAfter($event, $optimizer, $node)
    {
        if ($node->hasAttribute('src')) {
            $this->addDomainFromURL($node->getAttribute('src'));
        }
    }

    /**
     * @param Ressio_Event $event
     * @param string $url
     * @return void
     */
    public function onPreconnectDomain($event, $url)
    {
        $this->addDomainFromURL($url);
    }
}