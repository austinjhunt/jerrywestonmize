<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Actor_CssCombine extends Ressio_Actor
{
    /**
     * @param array $params
     * @return void
     */
    public function run($params)
    {
        extract($params, EXTR_OVERWRITE);
        /** @var array $styleList */
        /** @var ?string $imagenextgenformat */

        foreach ($styleList as $i => $style) {
            $styleList[$i] = (array)$style;
        }
        //$self_close_str = $params['self_close_str'];
        $this->di->config->var->imagenextgenformat = $imagenextgenformat;

        $this->di->cssCombiner->combineToNodes($styleList);
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