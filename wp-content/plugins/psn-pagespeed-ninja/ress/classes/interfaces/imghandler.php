<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_ImgHandler
{
    /**
     * Check that image format is supported by image handler
     * @param string $format
     * @return bool
     */
    public function isSupportedFormat($format);
}
