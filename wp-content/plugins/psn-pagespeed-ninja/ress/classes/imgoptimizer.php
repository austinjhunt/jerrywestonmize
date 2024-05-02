<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();
class Ressio_ImgOptimizer implements IRessio_ImgOptimizer, IRessio_DIAware
{
    /** @var Ressio_DI */
    protected $di;
    /** @var IRessio_Filesystem */
    protected $fs;
    /** @var Ressio_Config */
    public $config;

    private $optimistic = true;

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->di = $di;
        $this->fs = $di->filesystem;
        $this->config = $di->config;
    }

    /**
     * @param string $srcFile
     * @return string
     */
    protected function getFiletype($srcFile)
    {
        $ext = strtolower(pathinfo($srcFile, PATHINFO_EXTENSION));
        return ($ext === 'jpeg') ? 'jpg' : $ext;
    }

    /**
     * @param string $ext
     * @param string $action
     * @return ?IRessio_ImgHandler
     */
    protected function getHandler($ext, $action)
    {
        $imgOptimizer = null;
        try {
            $imgOptimizer = $this->di->get("imgOptimizer.{$ext}.{$action}");
        } catch (ERessio_UnknownDiKey $e) {
        }
        if ($imgOptimizer === null) {
            try {
                $imgOptimizer = $this->di->get("imgOptimizer.{$ext}");
            } catch (ERessio_UnknownDiKey $e) {
                $this->di->logger->warning(__METHOD__ . ": No handler found for '$ext' image format");
            }
        }
        return ($imgOptimizer instanceof IRessio_ImgHandler) ? $imgOptimizer : null;
    }

    /**
     * @param string $ext
     * @param string $action
     * @return bool
     */
    public function hasSupport($ext, $action)
    {
        return $this->getHandler($ext, $action) !== null;
    }

    /*** OPTIMIZER ***/

    /**
     * @param string $src_imagepath
     * @param string|false $dest_imagepath
     * @return string|false
     */
    public function optimize($src_imagepath, $dest_imagepath = false)
    {
        $fs = $this->fs;

        if (!$fs->isFile($src_imagepath)) {
            return false;
        }

        $ext = $this->getFiletype($src_imagepath);

        // check there is an optimizer for this filetype
        /** @var IRessio_ImgHandlerOptimize $imgOptimizer */
        $imgOptimizer = $this->getHandler($ext, 'optimize');
        if ($imgOptimizer === null) {
            $this->di->logger->notice("No optimize handler for image $src_imagepath");
            return false;
        }
        // $src_filesize = $fs->size($src_imagepath);

        if ($dest_imagepath === false) {
            $dest_imagepath = $this->getPathOptimized($src_imagepath);
            if ($dest_imagepath === false) {
                $this->di->logger->warning("No optimized path for image $src_imagepath");
                return false;
            }
        }

        $inplace = ($src_imagepath === $dest_imagepath);

        if (!$inplace) {
            $src_mtime = $fs->getModificationTime($src_imagepath);

            // check the file is optimized
            if (file_exists($dest_imagepath) /* file or symlink */ && $src_mtime === filemtime($dest_imagepath)) {
                return $dest_imagepath;
            }

            $fs->makeDir(dirname($dest_imagepath));
            $fs->symlink($src_imagepath, $dest_imagepath);
        }

        $params = compact('src_imagepath', 'dest_imagepath');
        switch ($ext) {
            case 'jpg':
                $params['quality'] = $this->config->img->jpegquality;
                break;
            case 'webp':
                $params['quality'] = $this->config->img->webpquality;
                break;
            case 'avif':
                $params['quality'] = $this->config->img->avifquality;
                break;
            case 'svg':
                $params['then'] = array('compressMulti', array(
                    'src_path' => $dest_imagepath,
                ));
                break;
        }
        $this->di->worker->runTask('imgOptimize', $params);

        return $dest_imagepath;
    }

    /**
     * @param array $params
     * @return void
     */
    public function runOptimize($params)
    {
        extract($params, EXTR_OVERWRITE);
        /** @var string $src_imagepath */
        /** @var string $dest_imagepath */
        /** @var int $quality */


        // check there is an optimizer for this filetype
        $ext = $this->getFiletype($dest_imagepath);
        /** @var IRessio_ImgHandlerOptimize $imgOptimizer */
        $imgOptimizer = $this->getHandler($ext, 'optimize');
        if ($imgOptimizer === null) {
            $this->di->logger->notice("No optimize handler for image $src_imagepath");
            return;
        }

        switch ($ext) {
            case 'jpg':
                $this->config->img->jpegquality = $quality;
                break;
            case 'webp':
                $this->config->img->webpquality = $quality;
                break;
            case 'avif':
                $this->config->img->avifquality = $quality;
                break;
        }
        $ok = false;
        try {
            $ok = $imgOptimizer->optimize($src_imagepath, $dest_imagepath);
        } catch (Exception $e) {
            $this->di->logger->error('Exception in ' . __METHOD__ . ': ' . $e->getMessage() . ' in ' . $e->getTraceAsString());
        }

        if (!$ok) {
            $this->di->logger->notice("Cannot optimize image $src_imagepath");
            if ($src_imagepath !== $dest_imagepath && !is_link($dest_imagepath)) {
                // restore symlink after fail
                $fs = $this->di->filesystem;
                $fs->symlink($src_imagepath, $dest_imagepath);
            }
        }
    }

    /**
     * @param string $src_imagepath
     * @return string|false
     */
    protected function getPathOptimized($src_imagepath)
    {
        // /image/path.ext => /s/img/image/path.ext
        $webrootpath = $this->config->webrootpath;
        $webrootpath_len = strlen($webrootpath);
        if (strncmp($src_imagepath, "$webrootpath/", $webrootpath_len + 1) !== 0) {
            return false;
        }
        $rel_path = substr($src_imagepath, $webrootpath_len);
        return "{$webrootpath}{$this->config->staticdir}/img{$rel_path}";
    }

    /*** CONVERTER ***/

    /**
     * @param string $src_imagepath
     * @param string $format
     * @param string|false $dest_imagepath
     * @return string|false
     */
    public function convert($src_imagepath, $format, $dest_imagepath = false)
    {
        $fs = $this->fs;

        if (!$fs->isFile($src_imagepath)) {
            return false;
        }

        $ext = $this->getFiletype($src_imagepath);
        if ($ext === $format) {
            return false;
        }

        // check there is a converter for this filetype
        /** @var IRessio_ImgHandlerConvert $imgConverter */
        $imgConverter = $this->getHandler($ext, 'convert');
        if ($imgConverter === null || !$imgConverter->isSupportedFormat($format)) {
            $this->di->logger->notice("No convert handler for image $src_imagepath");
            return false;
        }

        if ($dest_imagepath === false) {
            $dest_imagepath = $this->getPathConverted($src_imagepath, $format);
            if ($dest_imagepath === false) {
                $this->di->logger->warning("No converted path for image $src_imagepath");
                return false;
            }
        }

        $src_mtime = $fs->getModificationTime($src_imagepath);

        // check file is converted
        if (is_file($dest_imagepath) && $src_mtime === filemtime($dest_imagepath)) {
            // fail if converted file is zero-sized
            if (filesize($dest_imagepath) === 0) {
                return false;
            }
            return $dest_imagepath;
        }

        $fs->makeDir(dirname($dest_imagepath));
        if ($this->optimistic) {
            $fs->symlink($src_imagepath, $dest_imagepath);
        } else {
            $fs->makeEmpty($dest_imagepath);
            $fs->touch($dest_imagepath, $src_mtime);
        }

        $params = compact('src_imagepath', 'dest_imagepath', 'format');
        switch ($format) {
            case 'jpg':
                $params['quality'] = $this->config->img->jpegquality;
                break;
            case 'webp':
                $params['quality'] = $this->config->img->webpquality;
                break;
            case 'avif':
                $params['quality'] = $this->config->img->avifquality;
                break;
        }
        $this->di->worker->runTask('imgConvert', $params);
        if (($this->optimistic || !$this->config->worker->enabled) && filesize($dest_imagepath) > 0) {
            return $dest_imagepath;
        }
        return false;
    }

    /**
     * @param array $params
     * @return void
     */
    public function runConvert($params)
    {
        extract($params, EXTR_OVERWRITE);
        /** @var string $src_imagepath */
        /** @var string $dest_imagepath */
        /** @var string $format */
        /** @var int $quality */

        // check there is a converter for this filetype
        $ext = $this->getFiletype($src_imagepath);
        /** @var IRessio_ImgHandlerConvert $imgConverter */
        $imgConverter = $this->getHandler($ext, 'convert');
        if ($imgConverter === null || !$imgConverter->isSupportedFormat($format)) {
            // converter has been removed or changed
            $this->di->logger->notice("No convert handler for image $src_imagepath");
            return;
        }

        switch ($format) {
            case 'jpg':
                $this->config->img->jpegquality = $quality;
                break;
            case 'webp':
                $this->config->img->webpquality = $quality;
                break;
            case 'avif':
                $this->config->img->avifquality = $quality;
                break;
        }
        $ok = false;
        try {
            $ok = $imgConverter->convert($src_imagepath, $dest_imagepath, $format);
        } catch (Exception $e) {
            $this->di->logger->error('Exception in ' . __METHOD__ . ': ' . $e->getMessage() . ' in ' . $e->getTraceAsString());
        }

        if (!$ok) {
            $this->di->logger->notice("Cannot convert image $src_imagepath to $format");
            if (filesize($dest_imagepath) > 0) {
                // restore empty file after fail
                $fs = $this->di->filesystem;
                $fs->makeEmpty($dest_imagepath);
                $fs->touch($dest_imagepath, $fs->getModificationTime($src_imagepath));
            }
        }
    }

    /**
     * @param string $src_imagepath
     * @param string $format
     * @return string|false
     */
    protected function getPathConverted($src_imagepath, $format)
    {
        // /image/path.ext => /s/img/image/path.ext.format
        $webrootpath = $this->config->webrootpath;
        $webrootpath_len = strlen($webrootpath);
        if (strncmp($src_imagepath, "$webrootpath/", $webrootpath_len + 1) !== 0) {
            return false;
        }
        $rel_path = substr($src_imagepath, $webrootpath_len);
        return "{$webrootpath}{$this->config->staticdir}/img{$rel_path}.{$format}";
    }

    /*** RESCALER ***/

    /**
     * @param string $src_imagepath
     * @param string|null $format
     * @param int $width
     * @param int $height
     * @param string|false $dest_imagepath
     * @return string|false
     */
    public function rescale($src_imagepath, $format, $width, $height, $dest_imagepath = false)
    {
        $fs = $this->fs;

        if (!$fs->isFile($src_imagepath)) {
            return false;
        }

        $ext = $this->getFiletype($src_imagepath);

        // check there is an optimizer for this filetype
        /** @var IRessio_ImgHandlerRescale $imgRescaler */
        $imgRescaler = $this->getHandler($ext, 'rescale');
        if ($imgRescaler === null) {
            $this->di->logger->notice("No rescale handler for image $src_imagepath");
            return false;
        }

        if ($format === null) {
            $format = $ext;
        } elseif (!$imgRescaler->isSupportedFormat($format)) {
            return false;
        }

        if ($dest_imagepath === false) {
            $dest_imagepath = $this->getPathRescaled($src_imagepath, $format, $width, $height);
            if ($dest_imagepath === false) {
                $this->di->logger->warning("No rescaled path for image $src_imagepath");
                return false;
            }
        }

        $src_mtime = $fs->getModificationTime($src_imagepath);

        // check the file is rescaled
        if (file_exists($dest_imagepath) /* is_file or is_symlink */ && $src_mtime === filemtime($dest_imagepath)) {
            // fail if rescaled file is zero-sized
            if (filesize($dest_imagepath) === 0) {
                return false;
            }
            return $dest_imagepath;
        }

        $fs->makeDir(dirname($dest_imagepath));
        if ($this->optimistic || $format === $ext) {
            $fs->symlink($src_imagepath, $dest_imagepath);
        } else {
            $fs->makeEmpty($dest_imagepath);
            $fs->touch($dest_imagepath, $src_mtime);
        }

        $params = compact('src_imagepath', 'dest_imagepath', 'format', 'width', 'height');
        switch ($format) {
            case 'jpg':
                $params['quality'] = $this->config->img->jpegquality;
                break;
            case 'webp':
                $params['quality'] = $this->config->img->webpquality;
                break;
            case 'avif':
                $params['quality'] = $this->config->img->avifquality;
                break;
        }
        $this->di->worker->runTask('imgRescale', $params);
        if (($this->optimistic || !$this->config->worker->enabled) && filesize($dest_imagepath) > 0) {
            return $dest_imagepath;
        }
        return false;
    }

    /**
     * @param string $src_imagepath
     * @param string|null $format
     * @param int[] $dest_widths
     * @return array|false
     */
    public function rescaleBatch($src_imagepath, $format, $dest_widths)
    {
        $fs = $this->fs;

        if (!$fs->isFile($src_imagepath)) {
            return false;
        }

        $ext = $this->getFiletype($src_imagepath);

        // check there is an optimizer for this filetype
        /** @var IRessio_ImgHandlerRescale $imgRescaler */
        $imgRescaler = $this->getHandler($ext, 'rescale');
        if ($imgRescaler === null) {
            $this->di->logger->notice("No rescale handler for image $src_imagepath");
            return false;
        }

        if ($format === null) {
            $format = $ext;
        } elseif (!$imgRescaler->isSupportedFormat($format)) {
            return false;
        }

        $size = getimagesize($src_imagepath);
        if ($size === false) {
            return false;
        }

        list($src_width, $src_height) = $size;

        if ($src_width <= $dest_widths[0]) {
            return false;
        }

        $src_mtime = $fs->getModificationTime($src_imagepath);

        $results = array();
        $widths = array($src_width => $src_imagepath);

        switch ($format) {
            case 'jpg':
                $quality = $this->config->img->jpegquality;
                break;
            case 'webp':
                $quality = $this->config->img->webpquality;
                break;
            case 'avif':
                $quality = $this->config->img->avifquality;
                break;
            default:
                $quality = false;
        }

        foreach (array_reverse($dest_widths) as $width) {
            if ($width >= $src_width) {
                $results[] = false;
                continue;
            }

            $height = max(1, (int)round($src_height * $width / $src_width));

            $dest_imagepath = $this->getPathRescaled($src_imagepath, $format, $width, $height);
            if ($dest_imagepath === false) {
                $results[] = false;
                continue;
            }

            // check the file is rescaled
            if (file_exists($dest_imagepath) /* is_file or is_symlink */ && $src_mtime === filemtime($dest_imagepath)) {
                // fail if rescaled file is zero-sized
                if (filesize($dest_imagepath) > 0) {
                    $results[] = $dest_imagepath;
                } else {
                    $results[] = false;
                }
                continue;
            }

            $fs->makeDir(dirname($dest_imagepath));
            if ($this->optimistic || $format === $ext) {
                $fs->symlink($src_imagepath, $dest_imagepath);
            } else {
                $fs->makeEmpty($dest_imagepath);
                $fs->touch($dest_imagepath, $src_mtime);
            }

            $params = compact('dest_imagepath', 'format', 'width', 'height', 'quality');
            if (isset($widths[2 * $width])) {
                $params['src_imagepath'] = $widths[2 * $width];
            } elseif (isset($widths[3 * $width])) {
                $params['src_imagepath'] = $widths[3 * $width];
            } else {
                $params['src_imagepath'] = $src_imagepath;
            }

            $this->di->worker->runTask('imgRescale', $params);
            if (($this->optimistic || !$this->config->worker->enabled) && filesize($dest_imagepath) > 0) {
                $results[] = $dest_imagepath;
                $widths[$width] = $dest_imagepath;
            } else {
                $results[] = false;
            }
        }
        return array_reverse($results);
    }

    /**
     * @param array $params
     * @return void
     */
    public function runRescale($params)
    {
        extract($params, EXTR_OVERWRITE);
        /** @var string $src_imagepath */
        /** @var string $dest_imagepath */
        /** @var string $format */
        /** @var int $width */
        /** @var int $height */
        /** @var int $quality */

        // check there is a converter for this filetype
        $ext = $this->getFiletype($src_imagepath);
        /** @var IRessio_ImgHandlerRescale $imgRescaler */
        $imgRescaler = $this->getHandler($ext, 'rescale');
        if ($imgRescaler === null || !$imgRescaler->isSupportedFormat($format)) {
            // rescaler has been removed or changed
            $this->di->logger->notice("No rescale handler for image $src_imagepath");
            return;
        }

        switch ($format) {
            case 'jpg':
                $this->config->img->jpegquality = $quality;
                break;
            case 'webp':
                $this->config->img->webpquality = $quality;
                break;
            case 'avif':
                $this->config->img->avifquality = $quality;
                break;
        }
        $ok = false;
        try {
            $ok = $imgRescaler->rescale($src_imagepath, $dest_imagepath, $width, $height, $format);
        } catch (Exception $e) {
            $this->di->logger->error('Exception in ' . __METHOD__ . ': ' . $e->getMessage() . ' in ' . $e->getTraceAsString());
        }

        if (!$ok) {
            $this->di->logger->notice("Cannot rescale image $src_imagepath");
            // restore symlink/empty file after fail
            $fs = $this->di->filesystem;
            if ($format === $ext) {
                $fs->symlink($src_imagepath, $dest_imagepath);
            } else {
                $fs->makeEmpty($dest_imagepath);
                $fs->touch($dest_imagepath, $fs->getModificationTime($src_imagepath));
            }
        }
    }

    /**
     * @param string $src_imagepath
     * @param string $format
     * @param int $width
     * @param int $height
     * @return string|false
     */
    protected function getPathRescaled($src_imagepath, $format, $width, $height)
    {
        // /image/path.ext => /s/img-r/W/image/path.WxH.format
        $webrootpath = $this->config->webrootpath;
        $webrootpath_len = strlen($webrootpath);
        if (strncmp($src_imagepath, "$webrootpath/", $webrootpath_len + 1) !== 0) {
            return false;
        }
        $rel_path = substr($src_imagepath, $webrootpath_len, -strlen(pathinfo($src_imagepath, PATHINFO_EXTENSION)));
        return "{$webrootpath}{$this->config->staticdir}/img-r/{$width}{$rel_path}{$width}x{$height}.{$format}";
    }
}
