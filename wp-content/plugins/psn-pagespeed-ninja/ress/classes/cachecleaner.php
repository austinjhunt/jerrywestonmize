<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_CacheCleaner
{
    /**
     * @param Ressio_DI $di
     * @return void
     */
    public static function clean($di)
    {

        $filelock = $di->filelock;
        $fs = $di->filesystem;

        $cachedir = $di->config->cachedir;
        $ttl = $di->config->cachettl;
        if ($ttl <= 0) {
            $ttl = (int)ini_get('max_execution_time');
        }

        $cachecleaner_ttl = min($ttl, 24 * 60 * 60); // run daily even for larger TTL (@todo make it adjustable)

        $now = time();
        $aging_time = $now - $cachecleaner_ttl;

        $lock = $cachedir . '/filecachecleaner.stamp';
        if (!$fs->isFile($lock)) {
            // create the file with $aging_time timestamp to bypass getModificationTime check below
            $fs->touch($lock, $aging_time);
        }
        if (!$filelock->lock($lock)) {
            return;
        }
        if ($fs->getModificationTime($lock) > $aging_time) {
            // skip if it has just been processed (or is processing) by other script
            $filelock->unlock($lock);
            return;
        }
        $fs->touch($lock);
        $filelock->unlock($lock);

        $file_list = $di->cache->clearExpired();
        if ($file_list === null) {
            return;
        }

        // wait for double ttl to clear cache (1 TTL for cache + 1 TTL for pagecache)
        $aging_time = $now - 2 * $ttl;

        $staticdir = $di->config->webrootpath . $di->config->staticdir;

        if (is_dir($staticdir)) {
            $iterator = new AppendIterator();
            // iterate static files
            $iterator->append(new DirectoryIterator($staticdir));
            // iterate loaded files
            if (is_dir("$staticdir/loaded")) {
                $iterator->append(new DirectoryIterator("$staticdir/loaded"));
            }
            foreach ($iterator as $file) {
                /** @var DirectoryIterator $file */
                if (!$file->isFile()) {
                    continue;
                }
                $file_name = $file->getFilename();
                $file_path = $file->getPathname();
                if (
                    $file_name[0] === '.' ||
                    $fs->getModificationTime($file_path) >= $aging_time ||
                    substr_compare($file_name, '.php', -4) === 0 // preserve f.php file
                ) {
                    continue;
                }
                $src_file = str_replace(array('.br', '.gz'), '', substr($file_path, strlen($staticdir) + 1));
                if (isset($file_list[$src_file])) {
                    continue;
                }
                unlink($file_path);
            }
        }
    }

    /**
     * @param string $id
     * @param string $value
     * @param string $staticdir
     * @return array<string,int>
     */
    public static function collectRefs($id, $value, $staticdir)
    {
        $file_list = array();
        $group = explode('_', $id, 2)[1];
        switch ($group) {
            case 'js':
                foreach (@json_decode($value) as $node) {
                    if (isset($node->attributes->src)) {
                        if (preg_match('#[/?]([0-9a-f]+\.js)$#', $node->attributes->src, $matches)) {
                            $file_ref = $matches[1];
                            $file_list[$file_ref] = 1;
                        }
                    }
                }
                break;
            case 'css':
                foreach (@json_decode($value) as $node) {
                    if (isset($node->attributes->href)) {
                        if (preg_match('#[/?]([0-9a-f]+\.css)$#', $node->attributes->href, $matches)) {
                            $file_ref = $matches[1];
                            $file_list[$file_ref] = 1;
                        }
                    }
                }
                break;
            case 'htmljs':
                if (preg_match_all('#src="[^">]+[/?]([0-9a-f]+\.js)#', $value, $matches)) {
                    foreach ($matches[1] as $file_ref) {
                        $file_list[$file_ref] = 1;
                    }
                }
                break;
            case 'htmlcss':
                if (preg_match_all('#href="[^">]+[/?]([0-9a-f]+\.css)#', $value, $matches)) {
                    foreach ($matches[1] as $file_ref) {
                        $file_list[$file_ref] = 1;
                    }
                }
                break;
            case 'file':
                $data = @json_decode($value);
                if (isset($data->filename) && strncmp($data->filename, $staticdir, strlen($staticdir)) === 0) {
                    $file_ref = substr($data->filename, strlen($staticdir) + 1);
                    $file_list[$file_ref] = 1;
                }
        }

        return $file_list;
    }
}