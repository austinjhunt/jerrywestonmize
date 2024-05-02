<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Logger implements IRessio_Logger, IRessio_DIAware
{
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    /** @var int[] */
    protected static $levels = array(
        'emergency' => 1,
        'alert' => 2,
        'critical' => 3,
        'error' => 4,
        'warning' => 5,
        'notice' => 6,
        'info' => 7,
        'debug' => 8
    );

    /**
     * @var int
     */
    protected $minLoggingLevel = 3;

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->minLoggingLevel = $di->config->logginglevel;
    }

    /**
     * Detailed debug information.
     *
     * @param string|callable $message
     * @param array $context
     * @return void
     */
    public function debug($message, $context = null)
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Interesting events.
     *
     * @param string|callable $message
     * @param array $context
     * @return void
     */
    public function info($message, $context = null)
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string|callable $message
     * @param array $context
     * @return void
     */
    public function notice($message, $context = null)
    {
        $this->log(self::NOTICE, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string|callable $message
     * @param array $context
     * @return void
     */
    public function warning($message, $context = null)
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string|callable $message
     * @param array $context
     * @return void
     */
    public function error($message, $context = null)
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * @param string|callable $message
     * @param array $context
     * @return void
     */
    public function critical($message, $context = null)
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * @param string|callable $message
     * @param array $context
     * @return void
     */
    public function alert($message, $context = null)
    {
        $this->log(self::ALERT, $message, $context);
    }

    /**
     * System is unusable.
     *
     * @param string|callable $message
     * @param array $context
     * @return void
     */
    public function emergency($message, $context = null)
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param string $level
     * @param string|callable $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, $context = null)
    {
        if (self::$levels[$level] > $this->minLoggingLevel) {
            return;
        }

        if (!is_string($message) && is_callable($message)) {
            $message = $message();
        }

        if (is_array($context) && count($context) > 0) {
            $message = $this->interpolate($message, $context);
            if (isset($context['exception']) && ($context['exception'] instanceof Exception)) {
            }
        }

        error_log('[' . date('d-m-Y H:i:s') . "] RESSIO : $level : $message");
    }

    /**
     * @param string $level
     * @return bool
     */
    public function enabled($level)
    {
        return self::$levels[$level] <= $this->minLoggingLevel;
    }

    /**
     * @param string $message
     * @param array $context
     * @return string
     */
    protected function interpolate($message, $context)
    {
        $replace = array();
        foreach ($context as $key => $val) {
            if (!(is_array($val) || (is_object($val) && !method_exists($val, '__toString')))) {
                $replace["{{$key}}"] = $val;
            }
        }
        return strtr($message, $replace);
    }
}