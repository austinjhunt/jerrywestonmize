<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Actor_ImgConvert extends Ressio_Actor
{
    /**
     * @param array $params
     * @return void
     */
    public function run($params)
    {
        $this->di->imgOptimizer->runConvert($params);
    }

    /**
     * @param array $params
     * @return void
     */
    public function fail($params)
    {
        // do nothing
    }
}