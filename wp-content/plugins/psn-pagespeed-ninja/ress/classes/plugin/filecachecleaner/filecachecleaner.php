<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Plugin_FilecacheCleaner extends Ressio_Plugin
{
    /**
     * @param Ressio_DI $di
     * @param ?stdClass $params
     */
    public function __construct($di, $params = null)
    {
        parent::__construct($di);
        $this->loadConfig(__DIR__ . '/config.json', $params);

        register_shutdown_function(array($this, 'shutdown'));
    }

    /** @return void */
    public function shutdown()
    {
        if ($this->params->detach) {
            // if (!headers_sent()) header('Connection: close');
            ignore_user_abort(true);
            while (ob_get_level()) {
                ob_end_flush();
            }
            flush();
            if (session_id()) {
                session_write_close();
            }
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }
        }

        Ressio_CacheCleaner::clean($this->di);
    }
}