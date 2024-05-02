<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();



class Ressio_Actor_CompressMulti extends Ressio_Actor
{
    /**
     * @param array $params
     * @return void
     */
    public function run($params)
    {
        extract($params, EXTR_OVERWRITE);
        /** @var string $src_path */

        if (!is_file($src_path)) {
            return;
        }

        $fs = $this->di->filesystem;
        $mtime = $fs->getModificationTime($src_path);
        $content = $fs->getContents($src_path);

        if (function_exists('gzencode')) {
            $compressed = gzencode($content, 9);
            $dest_path = $src_path . '.gz';
            $fs->putContents($dest_path, $compressed);
            $fs->touch($dest_path, $mtime);
        }

        if (function_exists('brotli_compress')) {
            $compressed = brotli_compress($content, BROTLI_COMPRESS_LEVEL_MAX, BROTLI_TEXT);
            $dest_path = $src_path . '.br';
            $fs->putContents($dest_path, $compressed);
            $fs->touch($dest_path, $mtime);
        }
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