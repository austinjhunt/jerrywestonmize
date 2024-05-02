<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_HtmlOptimizer_Stream_NodeWrapper extends Ressio_NodeWrapper
{
    /** @var string */
    public $prepend;
    /** @var string */
    public $tag;
    /** @var string */
    public $append;

    /**
     * @return string
     */
    public function toString()
    {
        // This NodeWrapper is not for stringification
        return '<>';
    }
}
