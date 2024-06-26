<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Plugin_ViewportMetaTag extends Ressio_Plugin
{
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
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagMETABefore($event, $optimizer, $node)
    {
        if ($node->hasAttribute('name') && $node->getAttribute('name') === 'viewport') {
            $optimizer->nodeDetach($node);
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @return void
     */
    public function onHtmlBeforeStringify($event, $optimizer)
    {
        $optimizer->prependHead(array('meta', array('name' => 'viewport', 'content' => $this->params->viewport), false));
    }
}