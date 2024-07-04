<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_Cache
{
    /**
     * @param string|array $deps
     * @param string $suffix
     * @return string
     */
    public function id($deps, $suffix = '');

    /**
     * @param string $id
     * @return string|bool
     */
    public function getOrLock($id);

    /**
     * @param string $id
     * @return bool
     */
    public function lock($id);

    /**
     * @param string $id
     * @param string $data
     * @return bool
     */
    public function storeAndUnlock($id, $data);

    /**
     * @param string $id
     * @return bool
     */
    public function delete($id);

    /**
     * @return ?stdClass
     */
    public function getParams();

    /**
     * @param stdClass $params
     * @return bool
     */
    public function setParams($params);

    /**
     * @return ?array<string,int>
     */
    public function clearExpired();
}

