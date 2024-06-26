<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

/** A worker queue class */
abstract class Ressio_Worker implements IRessio_Worker, IRessio_DIAware
{
    /** @var Ressio_DI */
    protected $di;

    /** @var bool */
    protected $async_enabled;
    /** @var int */
    protected $maxWorkers;
    /** @var int */
    protected $maxExecutionTime;
    /** @var string */
    protected $maxMemoryLimit;
    /** @var string */
    protected $lockDir;
    /** @var array */
    protected $actionMap;
    /** @var string|int */
    protected $change_group;

    /** @var int */
    protected $pid;
    /** @var float */
    protected $initTime;
    /** @var int */
    protected $nextCleanupTime = 0;

    /** @var string */
    protected $action;
    /** @var array */
    protected $params;

    /**
     * Ressio_Worker constructor.
     * @param Ressio_DI $di
     * @throws Exception
     */
    public function __construct($di)
    {
        $this->initTime = microtime(true);
        $this->di = $di;

        $config = $di->config->worker;
        $this->async_enabled = $config->enabled;
        $this->maxWorkers = $config->maxworkers;
        $this->maxExecutionTime = $config->maxexecutiontime;
        $this->maxMemoryLimit = $config->memorylimit;
        $this->lockDir = isset($config->lockdir) ? $config->lockdir : RESSIO_PATH . '/cache';
        $this->actionMap = (array)$config->actors;
        // preload all classes from actionMap to work properly in case of updates
        foreach ($this->actionMap as $className) {
            class_exists($className);
        }

        $this->change_group = isset($di->config->change_group) ? $di->config->change_group : null;

        if (!is_dir($this->lockDir)) {
            $this->di->logger->error(__METHOD__ . ': Lock directory not found: ' . var_export($this->lockDir, true));
        } elseif (!is_writable($this->lockDir)) {
            $this->di->logger->error(__METHOD__ . ': Lock directory is not writable: ' . var_export($this->lockDir, true));
        }
    }

    /**
     * @return bool
     */
    public function isInitialized()
    {
        return $this->isInitializedStorage();
    }

    /** @return void */
    public function initialize()
    {
        if (!$this->isInitialized()) {
            $this->di->logger->info(__METHOD__ . ': initializing worker storage');
            $this->initializeStorage();
            $this->di->logger->info(__METHOD__ . ': worker storage has been initialized');
        }
    }

    /**
     * Enqueue or run an action
     * @param string $action
     * @param array $params
     * @return void
     */
    public function runTask($action, $params)
    {
        if ($this->async_enabled) {
            $this->runTaskAsync($action, $params);
        } else {
            $this->runTaskSync($action, $params);
        }
    }

    /**
     * Enqueue an action into queue
     * @param string $action
     * @param array $params
     * @return void
     */
    public function runTaskAsync($action, $params)
    {
        $params['chgrp'] = $this->change_group;

        $params_json = json_encode($params);
        $hash = sha1("{$action}\0{$params_json}");
        $added = time();

        $this->addTaskToStorage($hash, $action, $params_json, $added);

        $this->di->config->var->queued = true;
    }

    /** @return void */
    public function run()
    {
        $result = $this->getFreePid();
        if ($result === null) {
            return;
        }

        list($pid, $lock_file_handler) = $result;

        $process_id = getmypid();
        fwrite($lock_file_handler, (string)$process_id);
        fflush($lock_file_handler);

        $this->di->logger->info(__METHOD__ . ": run worker instance id=$pid, pid=$process_id");

        $this->pid = $pid;
        $this->di->config->var->workermode = true;

        $max_execution_time = $this->maxExecutionTime;
        set_time_limit(2 * $max_execution_time);

        $old_memory_limit = ini_get('memory_limit');
        $new_memory_limit = $this->maxMemoryLimit;
        if ($old_memory_limit
            && Ressio_Helper::str2int($old_memory_limit) < Ressio_Helper::str2int($new_memory_limit)
        ) {
            ini_set('memory_limit', $new_memory_limit);
        }

        $finalTime = $this->initTime + $max_execution_time;
        $update_file = $this->lockDir . '/worker_config.stamp';
        $update_timestamp = @filemtime($update_file);

        while (microtime(true) < $finalTime) {
            if (!$this->pickTaskFromStorage()) {
                $now = time();
                if ($now > $this->nextCleanupTime) {
                    $this->nextCleanupTime = $now + 60; /* once per minute */
                    $this->cleanupWorkers();
                }
                usleep(10000); // 0.01 sec (100 fps)
                continue;
            }

            $this->runTaskSync($this->action, $this->params);
            gc_collect_cycles();

            $this->setTaskDoneInStorage();

            clearstatcache();
            if (@filemtime($update_file) !== $update_timestamp) {
                break;
            }
        }

        ftruncate($lock_file_handler, 0);
        flock($lock_file_handler, LOCK_UN);
        fclose($lock_file_handler);

        $this->di->logger->info(__METHOD__ . ": exit worker instance id=$pid, pid=$process_id");
    }

    /**
     * @param string $action
     * @param array $params
     * @return void
     */
    public function runTaskSync($action, $params)
    {
        if (!isset($this->actionMap[$action])) {
            $this->di->logger->error(__METHOD__ . ": Unknown action '$action' for worker. Parameters: " . var_export($params, true));
            return;
        }

        $className = $this->actionMap[$action];

        if (!class_exists($className)) {
            $this->di->logger->error(__METHOD__ . ": Class name '$className' not found in worker. Action: '$action'; parameters: " . var_export($params, true));
            return;
        }

        $this->di->filesystem->useGroup(isset($params['chgrp']) ? $params['chgrp'] : $this->change_group);

        $then = isset($params['then']) ? $params['then'] : null;
        $fail = isset($params['fail']) ? $params['fail'] : null;
        unset($params['then'], $params['fail']);

        try {
            /** @var IRessio_Actor $actor */
            $actor = new $className($this->di);
            $actor->run($params);
            if ($then !== null) {
                $this->runTask($then[0], $then[1]);
            }
        } catch (Exception $e) {
            $this->di->logger->error(
                'Exception in ' . __METHOD__ . "('$action', " . var_export($params, true) . '): ' .
                $e->getMessage() . ' in ' . $e->getTraceAsString()
            );
            if ($fail !== null) {
                $this->runTask($fail[0], $fail[1]);
            }
        }
    }

    /** @return void */
    protected function cleanupWorkers()
    {
        $maxPid = $this->maxWorkers;

        $pids = $this->getWorkersListFromStorage();
        if (count($pids)) {
            $maxPid = max($maxPid, max($pids));
        }

        for ($i = 1; $i <= $maxPid; $i++) {
            $lock_file = $this->lockDir . "/worker.lock.{$i}.pid";
            $f = fopen($lock_file, 'cb');
            if ($f) {
                if (flock($f, LOCK_EX | LOCK_NB)) {
                    ftruncate($f, 0);
                    if (in_array($i, $pids, true)) {
                        $this->cleanupWorkerInStorage($i);
                    }
                    flock($f, LOCK_UN);
                }
                fclose($f);
            }
        }
    }

    /**
     * @return array
     */
    protected function getFreePid()
    {
        $maxWorkers = $this->maxWorkers;
        $pids = $this->getWorkersListFromStorage();
        $result = null;

        for ($i = 1; $i <= $maxWorkers; $i++) {
            $lock_file = $this->lockDir . "/worker.lock.{$i}.pid";
            $f = fopen($lock_file, 'cb');
            if ($f) {
                if (flock($f, LOCK_EX | LOCK_NB, $wouldblock)) {
                    ftruncate($f, 0);
                    if (in_array($i, $pids, true)) {
                        $this->cleanupWorkerInStorage($i);
                    }
                    if ($result === null) {
                        $result = array($i, $f);
                        continue;
                    }
                    flock($f, LOCK_UN);
                }
                if (!$wouldblock) {
                    $this->di->logger->info(__METHOD__ . ": fail locking worker instance on $lock_file");
                }
                fclose($f);
            } else {
                $this->di->logger->info(__METHOD__ . ": fail create worker lock file $lock_file");
            }
        }
        return $result;
    }

    /**
     * @return void
     */
    abstract protected function initializeStorage();

    /**
     * @return bool
     */
    abstract protected function isInitializedStorage();

    /**
     * @param string $hash
     * @param string $action
     * @param string $params
     * @param int $added
     * @return void
     */
    abstract protected function addTaskToStorage($hash, $action, $params, $added);

    /**
     * @return bool
     */
    abstract protected function pickTaskFromStorage();

    /**
     * @return void
     */
    abstract protected function setTaskDoneInStorage();

    /**
     * @return int[]
     */
    abstract protected function getWorkersListFromStorage();

    /**
     * @param int $pid
     * @return void
     */
    abstract protected function cleanupWorkerInStorage($pid);
}
