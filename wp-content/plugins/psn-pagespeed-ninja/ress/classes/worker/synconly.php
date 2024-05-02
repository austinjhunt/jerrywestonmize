<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Worker_SyncOnly extends Ressio_Worker
{
    /** @return void */
    protected function initializeStorage()
    {
    }

    /**
     * @return bool
     */
    protected function isInitializedStorage()
    {
        return true;
    }

    /**
     * @param string $hash
     * @param string $action
     * @param string $params
     * @param int $added
     * @return void
     */
    protected function addTaskToStorage($hash, $action, $params, $added)
    {
    }

    /**
     * @return bool
     */
    protected function pickTaskFromStorage()
    {
        return false;
    }

    /** @return void */
    protected function setTaskDoneInStorage()
    {
    }

    /**
     * @return int[]
     */
    protected function getWorkersListFromStorage()
    {
        return array();
    }

    /**
     * @param int $pid
     * @return void
     */
    protected function cleanupWorkerInStorage($pid)
    {
    }

    /**
     * @return int
     */
    public function getTasksCount()
    {
        return 0;
    }

    /**
     * @return int
     */
    public function getRunningTasksCount()
    {
        return 0;
    }

    /**
     * @return stdClass[]
     */
    public function getTasksList()
    {
        return array();
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function removeTask($hash)
    {
        return true;
    }

    /**
     * @param string $hash
     * @return bool
     */
    public function restartTask($hash)
    {
        return true;
    }
}
