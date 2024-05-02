<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_JsCombiner
{
    /**
     * Returns list of combined nodes
     * @param array $scriptList
     * @return Ressio_NodeWrapper[]
     */
    public function combineToNodes($scriptList);

    /**
     * @param Ressio_NodeWrapper[] $nodes
     * @return string
     */
    public function nodesToHtml($nodes);
}