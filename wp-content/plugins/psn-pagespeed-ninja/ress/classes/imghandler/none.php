<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

/**
 * Disabled images minification
 */
class Ressio_ImgHandler_None implements IRessio_ImgHandlerOptimize, IRessio_ImgHandlerConvert, IRessio_ImgHandlerRescale
{
    /**
     * @param string $format
     * @return bool
     */
    public function isSupportedFormat($format)
    {
        return true;
    }

    /**
     * @param string $srcFile
     * @param string $destFile
     * @return bool
     */
    public function optimize($srcFile, $destFile)
    {
        return false;
    }

    /**
     * @param string $srcFile
     * @param string $destFile
     * @param string $format
     * @return bool
     */
    public function convert($srcFile, $destFile, $format)
    {
        return false;
    }

    /**
     * @param string $srcFile
     * @param string $destFile
     * @param int $width
     * @param int $height
     * @param string $format
     * @return bool
     */
    public function rescale($srcFile, $destFile, $width, $height, $format = null)
    {
        return false;
    }
}
