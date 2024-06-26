<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

// Ressio optimizer supports PHP 5.6+,
if (!defined('RESSIO_PATH')) {
    define('RESSIO_PATH', __DIR__);
}
if (!defined('RESSIO_LIBS')) {
    define('RESSIO_LIBS', RESSIO_PATH . '/vendor');
}

class Ressio
{
    /** @var Ressio_DI */
    public $di;
    /** @var Ressio_Config */
    public $config;
    /** @var array */
    public static $classmap = array();

    /** @var bool */
    protected static $registeredAutoloading = false;

    /**
     * @param array $override_config
     * @param bool $prepend_autoloader
     * @param array $classmap
     * @throws Exception
     */
    public function __construct($override_config = null, $prepend_autoloader = true, $classmap = null)
    {
        if (empty($override_config['disable_autoload'])) {
            self::registerAutoloading($prepend_autoloader, $classmap);
        } elseif ($classmap !== null) {
            self::$classmap = array_merge(self::$classmap, $classmap);
        }

        $this->di = new Ressio_DI();

        $config = self::loadConfig($override_config);

        $this->config = $config;
        $this->di->set('config', $config);

        if (isset($config->di)) {
            foreach ($config->di as $key => $call) {
                $this->di->set($key, $call);
            }
        }

        if (isset($config->plugins)) {
            $dispatcher = $this->di->dispatcher;
            foreach ($config->plugins as $pluginClassname => &$plugin) {
                /** @var Ressio_Plugin $plugin */
                $plugin = new $pluginClassname($this->di, $plugin);
                $priorities = $plugin->getEventPriorities();
                foreach (get_class_methods($plugin) as $method) {
                    if (strncmp($method, 'on', 2) === 0) {
                        $eventName = substr($method, 2);
                        $priority = isset($priorities[$eventName]) ? $priorities[$eventName] : 0;
                        $dispatcher->addListener($eventName, array($plugin, $method), $priority);
                    }
                }
            }
            // unset($plugin);
            $dispatcher->triggerEvent('Initialise');
        }
    }

    /**
     * @param bool $prepend_autoloader
     * @param array $classmap
     * @return void
     */
    public static function registerAutoloading($prepend_autoloader = false, $classmap = null)
    {
        if ($classmap !== null) {
            self::$classmap = array_merge(self::$classmap, $classmap);
        }

        if (self::$registeredAutoloading) {
            return;
        }
        self::$registeredAutoloading = true;

        if (PHP_VERSION_ID < 80000 && function_exists('__autoload')) {
            spl_autoload_register('__autoload');
        }
        if ($prepend_autoloader) {
            spl_autoload_register(array(__CLASS__, 'autoloader'), true, true);
        } else {
            spl_autoload_register(array(__CLASS__, 'autoloader'));
        }
    }

    /** @return void */
    public static function unregisterAutoloading()
    {
        spl_autoload_unregister(array(__CLASS__, 'autoloader'));
    }

    /**
     * @param string $class
     * @return void
     */
    public static function autoloader($class)
    {
        if (isset(self::$classmap[$class])) {
            $path = self::$classmap[$class];
        } else {
            $pos = strpos($class, 'Ressio_');
            if ($pos === false) {
                return;
            }

            if ($pos === 0) {
                if (strncmp($class, 'Ressio_Plugin_', 14) === 0) {
                    // Ressio_Plugin_Name -> Ressio_Plugin_Name_Name -> plugin/name/name.php
                    $class = preg_replace('#(?<=^Ressio_Plugin_)([^_]+)$#', '\1_\1', $class);
                }
                $dir = '/classes/';
            } elseif ($pos === 1) {
                switch ($class[0]) {
                    case 'I':
                        $dir = '/classes/interfaces/';
                        break;
                    case 'E':
                        $dir = '/classes/exceptions/';
                        break;
                    default:
                        return;
                }
            } else {
                return;
            }

            $path = RESSIO_PATH . $dir . str_replace('_', '/', strtolower(substr($class, $pos + 7))) . '.php';
        }

        if (is_file($path)) {
            include $path;
        }
    }

    /**
     * @param array $override_config
     * @return Ressio_Config
     */
    public static function loadConfig($override_config = null)
    {



        /** @var Ressio_Config $config */
        $config = new stdClass();
        $config->var = new stdClass();

        self::merge_objects($config, include RESSIO_PATH . '/config.default.php');

        if (is_file(RESSIO_PATH . '/config.user.php')) {
            self::merge_objects($config, include RESSIO_PATH . '/config.user.php');
        }

        if ($override_config !== null) {
            self::merge_objects($config, $override_config);
        }

        if (empty($config->webrootpath)) {
            $config->webrootpath = substr($_SERVER['SCRIPT_FILENAME'], 0, -strlen($_SERVER['SCRIPT_NAME']));
        }

        if (!preg_match('#^\.?/[^/]#', $config->staticdir)) {
            $config->staticdir = './s'; // don't write to root
        }
        if (strncmp($config->staticdir, './', 2) === 0) {
            $ress_uri = strtr(substr(RESSIO_PATH, strlen($config->webrootpath)), DIRECTORY_SEPARATOR, '/');
            $config->staticdir = $ress_uri . substr($config->staticdir, 1);
        }

        if (strncmp($config->cachedir, './', 2) === 0) {
            $config->cachedir = RESSIO_PATH . substr($config->cachedir, 1);
        }
        if (strncmp($config->fileloaderphppath, './', 2) === 0) {
            $config->fileloaderphppath = RESSIO_PATH . substr($config->fileloaderphppath, 1);
        }
        if (strncmp($config->amdd->dbPath, './', 2) === 0) {
            $config->amdd->dbPath = RESSIO_PATH . substr($config->amdd->dbPath, 1);
        }

        if (DIRECTORY_SEPARATOR !== '/') {
            // convert paths to unix-style
            $config->webrootpath = strtr($config->webrootpath, DIRECTORY_SEPARATOR, '/');
            $config->fileloaderphppath = strtr($config->fileloaderphppath, DIRECTORY_SEPARATOR, '/');
            $config->cachedir = strtr($config->cachedir, DIRECTORY_SEPARATOR, '/');
        }

        return $config;
    }

    /**
     * @param Ressio_Config $obj
     * @param stdClass|array $obj2
     * @return void
     */
    private static function merge_objects(&$obj, $obj2)
    {
        if (is_array($obj)) {
            $obj = (object)$obj;
        }
        foreach ($obj2 as $key => $value) {
            if ((is_array($value) && count($value) && !isset($value[0])) || is_object($value)) {
                if (!isset($obj->$key)) {
                    $obj->$key = new stdClass();
                }
                self::merge_objects($obj->$key, $value);
            } else {
                $obj->$key = $value;
            }
        }
    }

    /**
     * @param string $buffer
     * @return string
     */
    public function ob_callback($buffer)
    {
        // disable any output in ob handler
        $display_errors = ini_get('display_errors');
        ini_set('display_errors', '0');

        $result = $this->run($buffer);

        ini_set('display_errors', $display_errors);
        return $result;
    }

    /**
     * @param string $content
     * @return string
     */
    public function run($content)
    {
        $buffer = Ressio_Helper::removeBOM($content);

        $cached = false;

        $this->config->var->imagenextgenformat = null;
        if (isset($_SERVER['HTTP_ACCEPT'])) {
            if ($this->config->img->avif && strpos($_SERVER['HTTP_ACCEPT'], 'image/avif') !== false) {
                $this->config->var->imagenextgenformat = 'avif';
            } elseif ($this->config->img->webp && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false) {
                $this->config->var->imagenextgenformat = 'webp';
            }
        }

        try {
            if ($this->config->cachefast) {
                $cache = $this->di->cache;
                $cache_id = $cache->id(array(json_encode($this->config), $buffer), 'fast');
                $headers_list = $cache->getOrLock($cache_id);
                if (is_string($headers_list)) {
                    $cached = true;

                    list($headers, $buffer) = explode("\n\n", $headers_list, 2);
                    $buffer = gzdecode($buffer);
                    $headers = explode("\n", $headers);

                    $httpHeaders = $this->di->httpHeaders;
                    foreach ($headers as $header) {
                        $httpHeaders->setHeader($header, false);
                    }
                }
            }

            if (!$cached) {
                $this->config->var->queued = false;
                $this->config->var->workermode = false;

                $optimizer = $this->di->htmlOptimizer;
                $this->di->dispatcher->triggerEvent('RunBefore', array(&$buffer, $optimizer));
                $buffer = $optimizer->run($buffer);
                $this->di->dispatcher->triggerEvent('RunAfter', array(&$buffer));

                if ($this->config->cachefast) {
                    $headers_list = array();
                    $headers = $this->di->httpHeaders->getHeaders();
                    foreach ($headers as $line) {
                        if (is_array($line)) {
                            foreach ($line as $header_line) {
                                $headers_list[] = $header_line;
                            }
                        } else {
                            $headers_list[] = $line;
                        }
                    }
                    $cache_data = implode("\n", $headers_list) . "\n\n" . gzencode($buffer, 5);
                    $cache->storeAndUnlock($cache_id, $cache_data);
                }
            }

            if ($this->config->html->gzlevel) {
                $this->di->httpCompressOutput->init($this->config->html->gzlevel, false);
                $buffer = $this->di->httpCompressOutput->compress($buffer);
            }

            $this->di->dispatcher->triggerEvent('RunBeforeSendHeaders', array(&$buffer));
            $this->di->httpHeaders->sendHeaders();
        } catch (Exception $e) {
            $this->di->logger->warning('Catched error in ' . __METHOD__ . ': ' . $e->getMessage() . ' in ' . $e->getTraceAsString());
            return $content;
        }

        return $buffer;
    }
}
