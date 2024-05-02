<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

interface IRessio_ImgHandlerOptimize extends IRessio_ImgHandler
{
    /**
     * @param string $srcFile
     * @param string $destFile
     * @return bool
     */
    public function optimize($srcFile, $destFile);
}
