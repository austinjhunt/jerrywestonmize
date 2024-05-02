<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Filesystem_Native implements IRessio_Filesystem, IRessio_DIAware
{
    /** @var string|int|null */
    protected $change_group;
    /** @var bool */
    protected $use_symlink;

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        // Note: symlink on Windows requires elevated admin priviledges
        $this->use_symlink = $di->config->use_symlink && (DIRECTORY_SEPARATOR === '/') && function_exists('symlink');
        $this->change_group = $di->config->change_group;
    }

    /**
     * Check file exists
     * @param string $filename
     * @return bool
     */
    public function isFile($filename)
    {
        return is_file($filename);
    }

    /**
     * Check directory exists
     * @param string $path
     * @return bool
     */
    public function isDir($path)
    {
        return is_dir($path);
    }

    /**
     * @param string $filename
     * @return int|bool
     */
    public function size($filename)
    {
        return filesize($filename);
    }

    /**
     * Load content from file
     * @param string $filename
     * @return string|false
     */
    public function getContents($filename)
    {
        return @file_get_contents($filename);
    }

    /**
     * Save content to file
     * @param string $filename
     * @param string $content
     * @return bool
     */
    public function putContents($filename, $content)
    {
        $size = strlen($content);
        $dir = dirname($filename);

        // inherit permissions for new files
        $mode = is_file($filename) ? @fileperms($filename) : (@fileperms($dir) & 0666);
        if ($this->change_group !== null) {
            $mode |= 0060;
        }

        $success = true;

        // save to a temporary file and do an atomic update via rename
        $tmp = tempnam($dir, basename($filename));
        if (
            (file_put_contents($tmp, $content, LOCK_EX) !== $size) ||
            !rename($tmp, $filename)
        ) {
            // otherwise, try to overwrite directly
            @unlink($tmp);
            @unlink($filename); // for hardlink target
            $success = (file_put_contents($filename, $content, LOCK_EX) === $size);
        }

        $success = chmod($filename, $mode) && $success;
        if ($this->change_group !== null) {
            $success = chgrp($filename, $this->change_group) && $success;
        }

        return $success;
    }

    /**
     * Make directory
     * @param string $path
     * @param int $chmod
     * @return bool
     */
    public function makeDir($path, $chmod = 0777)
    {
        if ($this->change_group !== null) {
            $chmod |= 0770;
        }
        $success = is_dir($path) || @mkdir($path, $chmod, true) || is_dir($path);
        if ($this->change_group !== null) {
            $success = chgrp($path, $this->change_group) && $success;
        }
        return $success;
    }

    /**
     * Get file timestamp
     * @param string $path
     * @return int
     */
    public function getModificationTime($path)
    {
        $time = @filemtime($path);
//        if (strncasecmp(PHP_OS, 'win', 3) === 0) {
//            // fix mtime on Windows (seems to be fixed in PHP5.3)
//            $time += 3600 * (date('I') - date('I', $time));
//        }
        return $time;
    }

    /**
     * Update file timestamp
     * @param string $filename
     * @param int $time
     * @return bool
     */
    public function touch($filename, $time = null)
    {
        if ($time === null) {
            // Note: null is processed as 0 by touch()
            $time = time();
        }
        return touch($filename, $time);
    }

    /**
     * Delete file or empty directory
     * @param string $path
     * @return bool
     */
    public function delete($path)
    {
        return @unlink($path);
    }

    /**
     * Copy file
     * @param string $src
     * @param string $target
     * @return bool
     */
    public function copy($src, $target)
    {
        // inherit permissions
        $mode = @fileperms($src);
        if ($this->change_group !== null) {
            $mode |= 0060;
        }

        if (!copy($src, $target) && !$this->putContents($target, $this->getContents($src))) {
            return false;
        }

        chmod($target, $mode);
        if ($this->change_group !== null) {
            chgrp($target, $this->change_group);
        }

        return true;
    }

    /**
     * Rename file
     * @param string $src
     * @param string $target
     * @return bool
     */
    public function rename($src, $target)
    {
        // inherit permissions
        $mode = @fileperms($src);
        if ($this->change_group !== null) {
            $mode |= 0060;
        }

        if (!rename($src, $target)) {
            if (!$this->putContents($target, $this->getContents($src))) {
                return false;
            }
            @unlink($src);
        }

        chmod($target, $mode);
        if ($this->change_group !== null) {
            chgrp($target, $this->change_group);
        }

        return true;
    }

    /**
     * Create symlink to a file
     * @param string $target
     * @param string $path
     * @return bool
     */
    public function symlink($target, $path)
    {
        $success = $this->use_symlink && @symlink($target, $path);
        if (!$success) {
            // copy on fail
            $success = $this->copy($target, $path);
            @touch($path, filemtime($target));
        }
        if ($success && $this->change_group !== null) {
            $success = lchgrp($path, $this->change_group);
        }
        return $success;
    }

    /**
     * Create/truncate the file
     * @param $filename
     * @return bool
     */
    public function makeEmpty($filename)
    {
        $success = ($fh = fopen($filename, 'cb')) && fclose($fh);
        // inherit permissions for new files
        $mode = is_file($filename) ? @fileperms($filename) : (@fileperms(dirname($filename)) & 0666);
        if ($this->change_group !== null) {
            $mode |= 0060;
        }
        $success = chmod($filename, $mode) && $success;
        if ($this->change_group !== null) {
            $success = chgrp($filename, $this->change_group) && $success;
        }
        return $success;
    }

    /**
     * @param string|int|null $group
     * @return void
     */
    public function useGroup($group)
    {
        $this->change_group = $group;
    }
}