<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();


class Ressio_UrlRewriter implements IRessio_DIAware
{
    /** @var Ressio_DI */
    protected $di;
    /** @var Ressio_Config */
    protected $config;

    /** @var string */
    protected $webrootpath;
    /** @var string */
    protected $webrootpathSlash;
    /** @var string */
    protected $webrooturi;
    /** @var string */
    protected $webrooturiSlash;

    /** @var string */
    protected $request_scheme;
    /** @var string */
    protected $request_host;

    /** @var string */
    protected $base_scheme;
    /** @var string */
    protected $base_host;
    /** @var string */
    protected $base_path; // base path with both leading and trailing slash

    /**
     * Constructor
     * Initiate base with current URL
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->di = $di;
        $this->config = $di->config; // webrootpath and webrooturi are used

        $this->webrootpath = $this->config->webrootpath;
        $this->webrootpathSlash = $this->webrootpath . '/';
        $this->webrooturi = $this->config->webrooturi;
        $this->webrooturiSlash = $this->webrooturi . '/';

        // Get scheme
        if (!empty($_SERVER['HTTPS']) && (strcasecmp($_SERVER['HTTPS'], 'off') !== 0)) {
            $this->request_scheme = 'https';
            $defPort = 443;
        } else {
            $this->request_scheme = 'http';
            $defPort = 80;
        }

        // Get host:port
        $this->request_host = $_SERVER['HTTP_HOST'];
        if (isset($_SERVER['HTTP_PORT']) && (int)$_SERVER['HTTP_PORT'] !== $defPort) {
            $this->request_host .= ':' . $_SERVER['HTTP_PORT'];
        }

        // Get default page base
        if (!empty($_SERVER['PHP_SELF']) && !empty($_SERVER['REQUEST_URI'])) {
            $path = $_SERVER['REQUEST_URI'];
        } else {
            $path = $_SERVER['SCRIPT_NAME'];
        }
        $path = dirname($path . '_');
        if (DIRECTORY_SEPARATOR !== '/') {
            $path = strtr($path, DIRECTORY_SEPARATOR, '/');
        }

        $this->base_scheme = $this->request_scheme;
        $this->base_host = $this->request_host;
        $this->base_path = rtrim($path, '/') . '/';
    }

    /**
     * @param string|array $base
     * @return Ressio_UrlRewriter
     */
    public function rebase($base)
    {
        $instance = clone $this;
        $instance->setBase($base);
        return $instance;
    }

    /**
     * Set base URL
     * @param string $url
     * @return void
     */
    public function setBase($url)
    {
        if ($url === null) {
            return;
        }

        $url = $this->expand($url);

        $parsed = parse_url($url);

        if (isset($parsed['scheme'])) {
            $this->base_scheme = $parsed['scheme'];
        }

        if (isset($parsed['host'])) {
            $host = $parsed['host'];
            if (isset($parsed['port'])) {
                $scheme = $this->base_scheme;
                if (($scheme === 'http' && $parsed['port'] !== 80) || ($scheme === 'https' && $parsed['port'] !== 443)) {
                    $host .= ':' . $parsed['port'];
                }
            }
            $this->base_host = $host;
        }

        $this->base_path = isset($parsed['path']) ? rtrim($parsed['path'], '/') . '/' : '/';
    }

    /**
     * Set parsed base URL
     * @param array $url
     * @return void
     */
    public function setBaseArray($url)
    {
        $this->base_scheme = $url['scheme'];
        $this->base_host = $url['host'];
        $this->base_path = $url['path'];
    }

    /**
     * Get base URL
     * @return string
     */
    public function getBase()
    {
        return "{$this->base_scheme}://{$this->base_host}{$this->base_path}";
    }

    /**
     * Get parsed base URL
     * @return array
     */
    public function getBaseArray()
    {
        return array(
            'scheme' => $this->base_scheme,
            'host' => $this->base_host,
            'path' => $this->base_path
        );
    }

    /**
     * Split URL to elements,
     * convert relative url to absolute,
     * and process "." and ".." in path
     * @param $url
     * @return array
     */
    protected function parse($url)
    {
        $normal_url = $this->expand($url);

        $parsed = parse_url($normal_url);


        if (isset($parsed['port'])) {
            if (!(($parsed['scheme'] === 'http' && $parsed['port'] === 80)
                || ($parsed['scheme'] === 'https' && $parsed['port'] === 443))
            ) {
                $parsed['host'] .= ':' . $parsed['port'];
            }
            unset($parsed['port']);
        }

        if (!isset($parsed['path'])) {
            $parsed['path'] = '/';
        } else {
            $in = explode('/', $parsed['path']);
            $out = array();
            foreach ($in as $dir) {
                switch ($dir) {
                    case '':
                    case '.':
                        break;
                    case '..':
                        array_pop($out);
                        break;
                    default:
                        $out[] = $dir;
                }
            }
            if (count($out) === 0) {
                $parsed['path'] = '/';
            } else {
                $parsed['path'] = '/' . implode('/', $out);
                if ($in[count($in) - 1] === '') {
                    $parsed['path'] .= '/';
                }
            }
        }

        return $parsed;
    }

    /**
     * Build URL from parsed elements
     * @param array $parsed
     * @return string
     */
    protected function build($parsed)
    {
        $url = '';

        if (isset($parsed['scheme'])) {
            $url .= $parsed['scheme'] . ':';
        }

        if (isset($parsed['host'])) {
            $url .= '//' . $parsed['host'];
        }

        if (isset($parsed['path'])) {
            $url .= $parsed['path'];
        }

        if (isset($parsed['query'])) {
            $url .= '?' . $parsed['query'];
        }

        if (isset($parsed['fragment'])) {
            $url .= '#' . $parsed['fragment'];
        }

        return $url;
    }

    /**
     * Minify URL by transforming it to relative format
     * @param string|array $url
     * @return string
     */
    public function minify($url)
    {
        $parsed = is_array($url) ? $url : $this->parse($url);

        if (!in_array($parsed['scheme'], array('http', 'https'), true)) {
            return is_array($url) ? $this->build($url) : $url;
        }
        $normal_url = '';

        if ($parsed['scheme'] !== $this->base_scheme) {
            $normal_url .= $parsed['scheme'] . ':';
        }

        if ($normal_url !== '' || $parsed['host'] !== $this->base_host) {
            $normal_url .= '//' . $parsed['host'];
        }

        if ($normal_url === '' && $this->base_path !== '/' && strpos($parsed['path'], $this->base_path) === 0) {
            $normal_url = substr($parsed['path'], strlen($this->base_path));
            if ($normal_url === false) {
                $normal_url = '';
            }
        } else {
            $normal_url .= $parsed['path'];
        }

        if (isset($parsed['query'])) {
            $normal_url .= '?' . $parsed['query'];
        }

        if (isset($parsed['fragment'])) {
            $normal_url .= '#' . $parsed['fragment'];
        }

        if ($normal_url === '') {
            $normal_url = $this->base_path;
        }
        return (is_array($url) || strlen($normal_url) < strlen($url)) ? $normal_url : $url;
    }

    /**
     * Expand URL by transforming it to full schema format
     * @param string $url
     * @return string
     */
    public function expand($url)
    {
        if (isset($url[0]) && $url[0] === '/') {
            if (isset($url[1]) && $url[1] === '/') {
                return "{$this->base_scheme}:{$url}";
            }
            return "{$this->base_scheme}://{$this->base_host}{$url}";
        }
        if (strpos($url, '://') === false) {
            return $this->getBase() . $url;
        }
        return $url;
    }

    /**
     * Get path to file corresponding to URL
     * @param string $url
     * @return ?string File path
     */
    public function urlToFilepath($url)
    {
        if (preg_match('#^(\w+):#', $url, $scheme)) {
            $scheme = strtolower($scheme[1]);
            if (!in_array($scheme, array('http', 'https'), true)) {
                return null;
            }
        }

        $parsed = $this->parse($url);

        $path = $parsed['path'];

        if ($parsed['host'] !== $this->request_host || strpos($path, $this->webrooturiSlash) !== 0) {
            return null;
        }

        $path = substr($path, strlen($this->webrooturi));

        return $this->webrootpath . $path;
    }

    /**
     * Get URL of specified file
     * @param string $path
     * @return ?string
     */
    public function filepathToUrl($path)
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            $path = strtr($path, DIRECTORY_SEPARATOR, '/');
        }
        // @note strpos is faster than strncmp+strlen
        if (strpos($path, $this->webrootpathSlash) !== 0) {
            $this->di->logger->warning("Cannot convert path $path to URL, webroot path is: {$this->webrootpathSlash}");
            return null;
        }
        $url = substr($path, strlen($this->webrootpath));
        return $this->webrooturi . $url;
    }

    /**
     * Check that URL is absolute (scheme://host/path)
     * @param string $url
     * @return bool
     */
    public function isAbsoluteURL($url)
    {
        return (strpos($url, '://') !== false);
    }

    /**
     * @param string $url
     * @param string $srcBase
     * @param string $targetBase
     * @return string
     */
    public function getRebasedUrl($url, $srcBase, $targetBase)
    {
        $base = $this->getBaseArray();

        $this->setBase($srcBase);
        $parsed_url = $this->parse($url);
        $this->setBaseArray($base);

        $this->setBase($targetBase);
        $url = $this->minify($parsed_url);
        $this->setBaseArray($base);

        return $url;
    }
}