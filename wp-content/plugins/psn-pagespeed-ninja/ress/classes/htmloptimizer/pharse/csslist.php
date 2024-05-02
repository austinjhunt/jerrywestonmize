<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_HtmlOptimizer_Pharse_CSSList extends HTML_Node implements IRessio_DIAware
{
    /** @var Ressio_DI */
    public $di;
    /** @var string */
    public $self_close_str = '';

    public $styleList = array();
    /** @var Ressio_NodeWrapper[] */
    public $nodeList;

    /**
     * Class constructor
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        parent::__construct('~stylesheet~', null);
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
     * @param bool $attributes Print attributes (of child tags)
     * @param bool|int $recursive How many sublevels of childtags to print. True for all.
     * @param bool|int $content_only Only print text, false will print tags too.
     * @return string
     */
    public function toString($attributes = true, $recursive = true, $content_only = false)
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
