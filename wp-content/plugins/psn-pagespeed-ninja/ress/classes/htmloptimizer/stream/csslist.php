<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_HtmlOptimizer_Stream_CSSList implements IRessio_DIAware
{
    /** @var Ressio_DI */
    public $di;
    /** @var string */
    public $self_close_str = '';

    public $styleList = array();
    /** @var Ressio_NodeWrapper[] */
    public $nodeList;

    /** @var int */
    public $index = -1;

    /**
     * Class constructor
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->di = $di;
    }

    /**
     * Generate list of combined nodes
     */
    public function prepare()
    {
        if ($this->nodeList === null && count($this->styleList)) {
            $this->nodeList = $this->di->cssCombiner->combineToNodes($this->styleList);
        }
    }

    /**
     * Returns the node as string
     * @return string
     */
    public function __toString()
    {
        if (!count($this->styleList)) {
            return '';
        }

        $this->prepare();

        if ($this->self_close_str !== '') {
            foreach ($this->nodeList as $node) {
                $node->self_close_str = $this->self_close_str;
            }
        }

        return $this->di->cssCombiner->nodesToHtml($this->nodeList);
    }
}
