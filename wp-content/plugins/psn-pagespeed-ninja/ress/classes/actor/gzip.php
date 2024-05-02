<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();



class Ressio_Actor_Gzip extends Ressio_Actor
{
    /**
     * @param array $params
     * @return void
     */
    public function run($params)
    {
        extract($params, EXTR_OVERWRITE);
        /** @var string $src_path */
        /** @var string $dest_path */

        if (is_file($src_path)) {
            $fs = $this->di->filesystem;
            $mtime = $fs->getModificationTime($src_path);
            $content = $fs->getContents($src_path);
            $content = gzencode($content, 9);
            $fs->putContents($dest_path, $content);
            $fs->touch($dest_path, $mtime); // for *.svg.gz files
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