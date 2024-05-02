<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

abstract class Ressio_Actor implements IRessio_Actor, IRessio_DIAware
{
    /** @var Ressio_DI */
    public $di;

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->di = $di;
    }
}
