<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_HtmlOptimizer_Pharse_JSList extends HTML_Node implements IRessio_DIAware
{
    /** @var Ressio_DI */
    public $di;

    public $scriptList = array();
    /** @var Ressio_NodeWrapper[] */
    public $nodeList;

    /**
     * Class constructor
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        parent::__construct('script', null);
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
     * @param bool $attributes Print attributes (of child tags)
     * @param bool|int $recursive How many sublevels of childtags to print. True for all.
     * @param bool|int $content_only Only print text, false will print tags too.
     * @return string
     */
    public function toString($attributes = true, $recursive = true, $content_only = false)
    {
        if (!count($this->scriptList)) {
            return '';
        }

        $this->prepare();
        return $this->di->jsCombiner->nodesToHtml($this->nodeList);
    }
}
