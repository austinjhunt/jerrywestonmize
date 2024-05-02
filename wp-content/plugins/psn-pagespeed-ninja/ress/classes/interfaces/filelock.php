<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_FileLock
{
    /**
     * @param string $filename
     * @return bool
     */
    public function lock($filename);

    /**
     * @param string $filename
     * @return bool
     */
    public function unlock($filename);

    /**
     * @param string $filename
     * @param bool $local
     * @return bool
     */
    public function isLocked($filename, $local = false);
}

