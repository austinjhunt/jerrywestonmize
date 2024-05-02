<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Database_Wordpress extends Ressio_Database_MySQL
{
    /** @var Ressio_ConfigDB */
    protected $config;

    /**
     * Database object constructor
     * @param Ressio_DI $di
     * @throws ERessio_DBError
     */
    public function __construct($di)
    {
        parent::__construct($di);

        if (!defined('DB_NAME')) {
            throw new ERessio_DBError('No WordPress config loaded');
        }

        $this->config = new Ressio_ConfigDB();

        $this->config->host = DB_HOST;
        $this->config->user = DB_USER;
        $this->config->password = DB_PASSWORD;
        $this->config->database = DB_NAME;
        $this->config->persistent = false;
    }

    /**
     * @return Ressio_ConfigDB
     */
    protected function getConfig()
    {
        return $this->config;
    }
}
