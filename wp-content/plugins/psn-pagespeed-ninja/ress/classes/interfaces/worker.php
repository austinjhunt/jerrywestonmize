<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_Worker
{
    /**
     * @return bool
     */
    public function isInitialized();

    /**
     * @return void
     */
    public function initialize();

    /**
     * @param string $action
     * @param array $params
     * @return void
     */
    public function runTask($action, $params);

    /**
     * @param string $action
     * @param array $params
     * @return void
     */
    public function runTaskAsync($action, $params);

    /**
     * @param string $action
     * @param array $params
     * @return void
     */
    public function runTaskSync($action, $params);

    /**
     * @return void
     */
    public function run();

    /**
     * @return int
     */
    public function getTasksCount();

    /**
     * @return int
     */
    public function getRunningTasksCount();

    /**
     * @return stdClass[]
     */
    public function getTasksList();

    /**
     * @param string $hash
     * @return bool
     * @todo return bool vs throw exception???
     */
    public function removeTask($hash);

    /**
     * @param string $hash
     * @return bool
     * @todo return bool vs throw exception???
     */
    public function restartTask($hash);
}
