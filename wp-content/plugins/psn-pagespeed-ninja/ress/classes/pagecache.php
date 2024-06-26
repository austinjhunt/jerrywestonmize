<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

abstract class Ressio_PageCache
{
    /** @var string */
    public $uri;
    /** @var string */
    public $cache_dir;
    /** @var int */
    public $ttl;
    /** @var bool */
    public $devicedependent;

    /** @var bool */
    public $caching = true;
    /** @var int */
    public $now;

    /** @var string */
    public $uri_hash;
    /** @var string */
    public $cache_file;
    /** @var array */
    public $headers = array();
    /** @var array */
    public $tags = array();

    /** @var string[] */
    protected static $exts = array(
        'br' => 'br',
        'deflate' => 'zz',
        'gzip' => 'gz',
        'x-gzip' => 'gz'
    );

    /**
     * Ressio_PageCache constructor.
     * @param ?string $uri
     * @param string $uri_cache_dir
     * @param int $ttl
     * @param bool $devicedependent
     */
    public function __construct($uri, $uri_cache_dir, $ttl, $devicedependent)
    {
        $this->now = (int)microtime(true);

        $this->uri = $uri;
        $this->cache_dir = $uri_cache_dir;
        $this->ttl = $ttl;
        $this->devicedependent = $devicedependent;

        if ($uri === null || $this->disabledCaching()) {
            $this->caching = false;
            return;
        }

        $this->uri_hash = $this->getRequestHash();

        $uri_cache_dir = $this->cache_dir . '/' . substr($this->uri_hash, 0, 2);
        if (!is_dir($uri_cache_dir) && !@mkdir($uri_cache_dir, 0775, true) && !is_dir($uri_cache_dir)) {
            // cannot create directory

            $this->caching = false;
            return;
        }
        $tags_dir = $this->cache_dir . '/tags';
        if (!is_dir($tags_dir) && !@mkdir($tags_dir, 0775) && !is_dir($tags_dir)) {
            // cannot create tags directory
            $this->caching = false;
            return;
        }

        $this->cache_file = "{$uri_cache_dir}/{$this->uri_hash}";

        // get cached content
        $cache = $this->readData();

        if ($cache === false) {
            return;
        }

        $client_etag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) : false;
        $etag = $cache['etag'];

        // check ETag
        if ($client_etag === $etag) {
            $httpVer = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
            header($httpVer . ' 304 Not Modified', true, 304);
            header('Status: 304 Not Modified');
            header('ETag: ' . $etag);
            exit(0);
        }

        // print, set Ressio's caching headers & exit
        foreach ($cache['headers'] as $header) {
            header($header);
        }
        header('ETag: ' . $etag);
        header('Cache-Control: public');

        $encoding = false;
        if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
            include_once __DIR__ . '/helper.php';
            $encoding = Ressio_Helper::getRequestedCompression();
        }

        $content = $cache['content'];

        $encoded = false;
        if (isset(self::$exts[$encoding])) {
            $cache_file_encoded = $this->cache_file . self::$exts[$encoding];
            $encoded = $this->readFileThreadSafe($cache_file_encoded);
            if ($encoded === false) {
                switch ($encoding) {
                    case 'br':
                        $encoded = brotli_compress($content, 11, BROTLI_TEXT);
                        break;
                    case 'deflate':
                        $encoded = gzdeflate($content, 9);
                        break;
                    case 'gzip':
                    case 'x-gzip':
                        $encoded = gzencode($content, 9);
                        break;
                }
                if ($encoded !== false) {
                    $this->writeFileThreadSafe($cache_file_encoded, $encoded);
                }
            }
        }

        if ($encoded !== false) {
            header('Vary: Accept-Encoding');
            header('Content-Encoding: ' . $encoding);
            header('Content-Length: ' . strlen($encoded));
            echo $encoded;
        } else {
            echo $content;
        }

        flush();
        exit(0);
    }

    /**
     * @param string $content
     * @return ?string
     */
    public function save($content)
    {
        if (!$this->caching) {
            return null;
        }

        // ETag should be changed after related document has been modified, that's why time is used in sha1 input
        $etag = '"' . sha1($this->uri_hash . $this->now) . '"';
        $data = json_encode(array(
            'timestamp' => $this->now,
            'tags' => array_keys($this->tags),
            'etag' => $etag,
            // Randomize TTL (reduce up to 20%)
            'ttlfactor' => 1.0 - 0.2 * mt_rand() / mt_getrandmax(),
            'headers' => $this->headers,
        )) . "\0" . $content;

        if (!$this->writeData($data)) {
            return null;
        }

        return $etag;
    }

    /**
     * @param string $name
     * @return void
     */
    public function updateTag($name)
    {
        if (!is_dir($this->cache_dir)) {
            // cache directory doesn't exist
            return;
        }

        $timestamp = (string)$this->now;

        $tag_file = "{$this->cache_dir}/tags/{$name}";
        $this->writeFileThreadSafe($tag_file, $timestamp);

        $tag_file = "{$this->cache_dir}/tags/ANY";
        $this->writeFileThreadSafe($tag_file, $timestamp);
    }

    /**
     * @param string|string[] $names
     * @return void
     */
    public function addTagDependence($names)
    {
        if (is_string($names)) {
            $this->tags[$names] = 1;
        } elseif (is_array($names)) {
            foreach ($names as $name) {
                $this->tags[$name] = 1;
            }
        }
    }

    /**
     * Purge old cache files
     * @param int $ttl
     * @return void
     */
    public function purgeCache($ttl = null)
    {
        if ($ttl === null) {
            $ttl = $this->ttl;
        }

        $now = time();
        $global_tag = $this->cache_dir . '/tags/GLOBAL';
        $cache_timestamp = is_file($global_tag) ? @filemtime($global_tag) : $now;
        // -1h to fix mtime with DST on Windows FAT32
        $aging_time = ($ttl === 0) ? ($now - 1) : max($cache_timestamp, $now - $ttl) - 60 * 60;

        // iterate cache directory
        foreach (scandir($this->cache_dir, SCANDIR_SORT_NONE) as $subdir) {
            $subdir_path = "{$this->cache_dir}/{$subdir}";
            if ($subdir[0] === '.' || !is_dir($subdir_path)) {
                continue;
            }
            $h = opendir($subdir_path);
            $files = 0;
            while (($file = readdir($h)) !== false) {
                if ($file[0] === '.') {
                    continue;
                }
                $file_path = "$subdir_path/$file";
                if ($file_path === $global_tag) {
                    $files++;
                    continue;
                }
                $orig_file_path = preg_replace('/(?:br|gz|zz)$/', '', $file_path);
                if (!is_file($orig_file_path) || @filemtime($orig_file_path) < $aging_time) {
                    unlink($file_path);
                } else {
                    $files++;
                }
            }
            closedir($h);
            if ($files === 0 && $subdir !== 'tags') {
                @rmdir($subdir_path);
            }
        }
    }

    /**
     * @param string $uri_hash
     * @return void
     */
    public function removeCacheEntry($uri_hash)
    {
        $cache_dir = $this->cache_dir . '/' . substr($uri_hash, 0, 2);
        $cache_file = "$cache_dir/$uri_hash";
        if (is_file($cache_file)) {
            unlink($cache_file);
            foreach (self::$exts as $ext) {
                $cache_file_compressed = $cache_file . $ext;
                if (is_file($cache_file_compressed)) {
                    unlink($cache_file_compressed);
                }
            }
        }
    }

    /**
     * @return bool
     */
    protected function disabledCaching()
    {
        return
            // not GET request
            (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') ||

            // AJAX request
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||

            // headers sent (error/warning/etc.)
            headers_sent();
    }

    /**
     * @return string
     */
    protected function getRequestHash()
    {
        return sha1(implode('|', $this->getRequestHashArray()));
    }

    /**
     * @return array
     */
    protected function getRequestHashArray()
    {
        $hash_data = array();

        $hash_data[] = isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : '';
        $hash_data[] = $_SERVER['HTTP_HOST'];
        $hash_data[] = isset($_SERVER['HTTP_PORT']) ? $_SERVER['HTTP_PORT'] : '';
        $hash_data[] = $this->uri;

        if (isset($_SERVER['HTTP_ACCEPT'])) {
//            if ($this->config->img->avif && strpos($_SERVER['HTTP_ACCEPT'], 'image/avif') !== false) {
//                $hash_data[] = 'avif';
//            } elseif ($this->config->img->webp && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false) {
//                $hash_data[] = 'webp';
//            }
        }

        if ($this->devicedependent) {
            $hash_data[] = $this->getDeviceDependentHash();
        }

        return $hash_data;
    }

    /**
     * @return string
     */
    protected function getDeviceDependentHash()
    {
        $ua = @$_SERVER['HTTP_USER_AGENT'];

        $hash = '';

        include_once __DIR__ . '/devicedetector/base.php';
        $detector = new Ressio_DeviceDetector_Base($ua);
        $hash .= ($detector->vendor() === 'ms') ? 'ms' : '';

        return $hash;
    }

    /**
     * @param string $data
     * @return bool
     */
    protected function writeData($data)
    {
        return $this->writeFileThreadSafe($this->cache_file, $data);
    }

    /**
     * @return array|false
     */
    protected function readData()
    {
        if (!file_exists($this->cache_file)) {
            return false;
        }

        $timestamp = filemtime($this->cache_file);
        $cache_timestamp = (int)@filemtime($this->cache_dir . '/tags/GLOBAL');
        if ($timestamp < $cache_timestamp) {
            return false;
        }

        $lifetime = $this->now - $timestamp;
        if ($lifetime > $this->ttl) {
            return false;
        }

        $cache_data = $this->readFileThreadSafe($this->cache_file);
        $cache_data = explode("\0", $cache_data, 2);
        if (isset($cache_data[1])) {
            $json = $cache_data[0];
            $content = $cache_data[1];
            $cache_data = json_decode($json, true);
            $cache_data['content'] = $content;
        } else {
            $cache_data = json_decode($cache_data[0], true);
        }
        if (!isset($cache_data['timestamp'], $cache_data['tags'], $cache_data['etag'], $cache_data['ttlfactor'], $cache_data['headers'], $cache_data['content'])) {
            return false;
        }

        // Use TTL randomization
        if ($lifetime > $cache_data['ttlfactor'] * $this->ttl) {
            return false;
        }

        // check tags
        $data_timestamp = $cache_data['timestamp'];
        foreach ($cache_data['tags'] as $tag_name) {
            $tag_file = "{$this->cache_dir}/tags/{$tag_name}";
            if (is_file($tag_file)) {
                $tag_timestamp = (float)$this->readFileThreadSafe($tag_file);
                if ($tag_timestamp > $data_timestamp) {
                    return false;
                }
            }
        }

        return $cache_data;
    }

    /**
     * @param string $filename
     * @param string $data
     * @return bool
     */
    protected function writeFileThreadSafe($filename, $data)
    {
        $dir = dirname($filename);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
        if (file_put_contents($filename, $data, LOCK_EX) === strlen($data)) {
            return true;
        }

        @unlink($filename);
        return false;
    }

    /**
     * @param string $filename
     * @return bool|string
     */
    protected function readFileThreadSafe($filename)
    {
        if (!is_file($filename)) {
            return false;
        }

        $f = fopen($filename, 'rb');
        if (!flock($f, LOCK_SH)) {
            fclose($f);
            return false;
        }
        $data = stream_get_contents($f);
        flock($f, LOCK_UN);
        fclose($f);

        return $data;
    }
}
