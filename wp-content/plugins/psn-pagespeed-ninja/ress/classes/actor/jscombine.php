<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Actor_JsCombine extends Ressio_Actor
{
    /**
     * @param array $params
     * @return void
     */
    public function run($params)
    {
        extract($params, EXTR_OVERWRITE);
        /** @var array $scriptList */

        foreach ($scriptList as $i => $script) {
            $scriptList[$i] = (array)$script;
        }

        $this->di->jsCombiner->combineToNodes($scriptList);
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