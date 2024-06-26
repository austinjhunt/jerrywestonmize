<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Cache_None implements IRessio_Cache
{
    /**
     * @param string|string[] $deps
     * @param string $suffix
     * @return string
     */
    public function id($deps, $suffix = '')
    {
        if (is_array($deps)) {
            $deps = implode("\0", $deps);
        }
        return sha1($deps) . '_' . $suffix;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function getOrLock($id)
    {
        return true;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function lock($id)
    {
        return true;
    }

    /**
     * @param string $id
     * @param string $data
     * @return bool
     */
    public function storeAndUnlock($id, $data)
    {
        return true;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function delete($id)
    {
        return true;
    }

    /**
     * @return null
     */
    public function getParams()
    {
        return null;
    }

    /**
     * @param ?stdClass $params
     * @return bool
     */
    public function setParams($params)
    {
        return true;
    }
}

