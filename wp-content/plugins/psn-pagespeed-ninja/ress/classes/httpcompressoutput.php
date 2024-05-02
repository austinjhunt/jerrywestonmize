<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_HttpCompressOutput implements IRessio_HttpCompressOutput, IRessio_DIAware
{
    /** @var Ressio_DI */
    private $di;

    /** @var int */
    private $gzLevel;

    /** @var string|false */
    private $encoding;

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->di = $di;
    }

    /**
     * @param int $gzLevel Compression level
     * @param bool $autostart Set $this->compress as output handler
     * @return void
     */
    public function init($gzLevel, $autostart = true)
    {
        if (!extension_loaded('zlib') || ini_get('zlib.output_compression')) {
            $gzLevel = 0;
        } else {
            $this->encoding = Ressio_Helper::getRequestedCompression();
            if ($this->encoding === false) {
                $gzLevel = 0;
            }
        }

        $this->gzLevel = $gzLevel;

        if ($autostart && $gzLevel) {
            ob_start(array($this, 'compress'), 0, 0);
        }
    }

    /**
     * Content compressing by requesting method
     * @param string $content
     * @return string
     */
    public function compress($content)
    {
        if ($content === '' || $this->gzLevel === 0 || headers_sent()) {
            return $content;
        }

        $encoding = $this->encoding;
        /** @var string|false $encoded */
        $encoded = false;
        switch ($encoding) {
            case 'br':
                $encoded = brotli_compress($content, 11, BROTLI_TEXT);
                break;
            case 'deflate':
                $encoded = gzdeflate($content, $this->gzLevel);
                break;
            case 'gzip':
            case 'x-gzip':
                $encoded = gzencode($content, $this->gzLevel);
                break;
            case 'compress':
            case 'x-compress':
                $encoded = gzcompress($content, $this->gzLevel);
                break;
        }
        if ($encoded === false) {
            return $content;
        }

        $this->di->httpHeaders->setHeaders(array(
            'Vary: Accept-Encoding',
            'Content-Encoding: ' . $this->encoding,
            'Content-Length: ' . strlen($encoded)
        ));
        return $encoded;
    }
}
