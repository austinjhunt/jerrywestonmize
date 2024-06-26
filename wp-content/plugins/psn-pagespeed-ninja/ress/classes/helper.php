<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

/**
 * Helper class
 */
abstract class Ressio_Helper
{
    /**
     * Remove UTF-8 byte-order-mark (BOM)
     * @param string $str
     * @return string
     */
    public static function removeBOM($str)
    {
        // other BOM sequences are not used in webdev
        if (strncmp($str, "\xEF\xBB\xBF", 3) === 0) {
            return substr($str, 3);
        }
        return $str;
    }

    /**
     * @param string $str
     * @return int
     */
    public static function str2int($str)
    {
        $unit = strtoupper(substr($str, -1));
        $num = (int)$str;
        switch ($unit) {
            case 'G':
                $num *= 1024 * 1024 * 1024;
                break;
            case 'M':
                $num *= 1024 * 1024;
                break;
            case 'K':
                $num *= 1024;
        }
        return $num;
    }

    /**
     * Get requested compression mode
     * @return string|bool
     * false - no compression
     * 'br' - brotli method (if php-ext-brotli is loaded)
     * 'deflate' - deflate method
     * 'gzip'/'x-gzip' - gzip method
     * 'compress'/'x-compress' - compress method
     * @todo move to Ressio_HttpCompressOutput class???
     * Used in Ressio_PageCache and Ressio_HttpCompressOutput
     * @static
     */
    public static function getRequestedCompression()
    {
        /** @var string|bool $method */
        static $method;
        if ($method !== null) {
            return $method;
        }

        $method = false;

        // list methods in decreasing compression level order
        static $supportedMethods;
        if (!isset($supportedMethods)) {
            // check zlib
            $supportedMethods =
                extension_loaded('zlib') ?
                    array('deflate', 'gzip', 'x-gzip', 'compress', 'x-compress') :
                    array();
            // check brotli
            if (function_exists('brotli_compress')) {
                array_unshift($supportedMethods, 'br');
            }
        }

        // parse Accept-Encoding header

        $acceptEncoding = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '';

        $bestQ = 0.0;
        $bestMethodId = -1;
        foreach (explode(',', $acceptEncoding) as $encoding) {
            $encoding = preg_split('#;\s*q=#', $encoding, 2);
            $q = isset($encoding[1]) ? (float)$encoding[1] : 1.0;
            $encoding = strtolower(trim($encoding[0]));

            $methodId = array_search($encoding, $supportedMethods, true);
            if ($methodId === false) {
                continue;
            }

            if ($q > $bestQ) {
                $bestQ = $q;
                $bestMethodId = $methodId;
            } elseif ($q === $bestQ && $methodId < $bestMethodId) {
                $bestMethodId = $methodId;
            }
        }
        if ($bestMethodId >= 0) {
            $method = $supportedMethods[$bestMethodId];
        }

        return $method;
    }

    /**
     * @param string $command
     * @param string[] $params
     * @return string
     */
    public static function buildCommand($command, $params)
    {
        $search = array();
        $replace = array();
        foreach ($params as $key => $value) {
            $search[] = '{{' . $key . '}}';
            $replace[] = escapeshellarg($value);
        }
        return str_replace($search, $replace, $command);
    }
}
