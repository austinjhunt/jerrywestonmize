<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_ImgOptimizer
{
    /**
     * @param string $ext
     * @param string $action
     * @return bool
     */
    public function hasSupport($ext, $action);

    /**
     * @param string $src_imagepath
     * @param string|false $dest_imagepath
     * @return string|false
     */
    public function optimize($src_imagepath, $dest_imagepath = false);

    /**
     * @param array $params
     * @return void
     */
    public function runOptimize($params);

    /**
     * @param string $src_imagepath
     * @param string $format
     * @param string|false $dest_imagepath
     * @return string|false
     */
    public function convert($src_imagepath, $format, $dest_imagepath = false);

    /**
     * @param array $params
     * @return void
     */
    public function runConvert($params);

    /**
     * @param string $src_imagepath
     * @param string|null $format
     * @param int $width
     * @param int $height
     * @param string|false $dest_imagepath
     * @return string|false
     */
    public function rescale($src_imagepath, $format, $width, $height, $dest_imagepath = false);

    /**
     * @param string $src_imagepath
     * @param string|null $format
     * @param int[] $dest_widths
     * @return array|false
     */
    public function rescaleBatch($src_imagepath, $format, $dest_widths);

    /**
     * @param array $params
     * @return void
     */
    public function runRescale($params);
}
