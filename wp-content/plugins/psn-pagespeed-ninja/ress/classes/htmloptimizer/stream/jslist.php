<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_HtmlOptimizer_Stream_JSList implements IRessio_DIAware
{
    /** @var Ressio_DI */
    public $di;

    public $scriptList = array();
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
        if ($this->nodeList === null && count($this->scriptList)) {
            $this->nodeList = $this->di->jsCombiner->combineToNodes($this->scriptList);
        }
    }

    /**
     * Returns the node as string
     * @return string
     */
    public function __toString()
    {
        if (!count($this->scriptList)) {
            return '';
        }

        $this->prepare();
        return $this->di->jsCombiner->nodesToHtml($this->nodeList);
    }
}
