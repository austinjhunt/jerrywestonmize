<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_CssRelocator
{
    /**
     * @param string $buffer
     * @param string $srcBase
     * @param string $targetBase
     * @param string $media
     * @return string
     */
    public function run($buffer, $srcBase = null, $targetBase = null, $media = null);
}
