<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

interface IRessio_HttpHeaders
{
    /**
     * @param string $line
     * @param bool $override
     * @param int $http_response_code
     * @return void
     */
    public function setHeader($line, $override = true, $http_response_code = null);

    /**
     * @param string $name
     * @param string $value
     * @param int $expires
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @return void
     */
    public function setCookie($name, $value, $expires = 0, $path = '', $domain = '', $secure = false, $httponly = false);

    /**
     * @return void
     */
    public function clearHeaders();

    /**
     * @param array $headers
     * @return void
     */
    public function setHeaders($headers);

    /**
     * @return string
     */
    public function getStatus();

    /**
     * @return int
     */
    public function getStatusCode();

    /**
     * @return array
     */
    public function getHeaders();

    /**
     * @return void
     */
    public function sendHeaders();
}
