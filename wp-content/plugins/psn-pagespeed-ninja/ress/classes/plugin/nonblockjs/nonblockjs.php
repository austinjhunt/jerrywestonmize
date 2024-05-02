<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Plugin_NonBlockJS extends Ressio_Plugin
{
    /**
     * @param Ressio_DI $di
     * @param ?stdClass $params
     */
    public function __construct($di, $params)
    {
        $params = $this->loadConfig(__DIR__ . '/config.json', $params);

        parent::__construct($di, $params);
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagSCRIPT($event, $optimizer, $node)
    {
        $jsType = $node->hasAttribute('type') ? $node->getAttribute('type') : null;

        $supportedTypes = array('text/javascript', 'module');

        if ($jsType !== null && !in_array($jsType, $supportedTypes)) {
            return;
        }
        if ($this->config->js->rules_merge_exclude && $optimizer->matchExcludeRule($node, $this->config->js->rules_merge_exclude)) {
            return;
        }

        $node->setAttribute('type', 'text/ress');
        if ($node->hasAttribute('src')) {
            $src = $node->getAttribute('src');
            $this->di->dispatcher->triggerEvent('CDNTransform', array(&$src));
            $node->setAttribute('ress-src', $src);
            $node->removeAttribute('src');
        }

        if ($jsType === 'module') {
            $node->setAttribute('ress-type', 'module');
        } elseif ($node->hasAttribute('nomodule')) {
            $node->setAttribute('ress-type', 'nomodule');
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @return void
     */
    public function onHtmlIterateAfter($event, $optimizer)
    {
        $scriptData = file_get_contents(__DIR__ . '/js/nonblockjs.min.js');
        $optimizer->prependHead(array('script', null, $scriptData));

    }

    /**
     * @param Ressio_Event $event
     * @param stdClass $wrapper
     * @return void
     */
    public function onJsCombinerNodeList($event, $wrapper)
    {
        foreach ($wrapper->nodes as $node) {
            if (isset($node->attributes['src'])) {
                $node->attributes['type'] = 'text/ress';
                $node->attributes['ress-src'] = $node->attributes['src'];
                unset($node->attributes['src']);
            }
        }
    }
}