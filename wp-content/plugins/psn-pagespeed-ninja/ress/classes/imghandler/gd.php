<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

/**
 * Images minification using GD
 */
class Ressio_ImgHandler_GD implements IRessio_ImgHandlerOptimize, IRessio_ImgHandlerConvert, IRessio_ImgHandlerRescale, IRessio_DIAware
{
    public $supported_formats = array('jpg', 'gif', 'png');

    /** @var bool */
    protected $open_basedir_enabled;

    /** @var Ressio_DI */
    protected $di;

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->di = $di;

        // support broken JPEGs
        ini_set('gd.jpeg_ignore_warning', '1');

        // Support of WebP images in PHP 5.5+
        if (function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $this->supported_formats[] = 'webp';
        }

        // Support of BMP images in PHP 7.2+
        if (function_exists('imagecreatefrombmp') && function_exists('imagebmp')) {
            $this->supported_formats[] = 'bmp';
        }

        // Support of AVIF images in PHP 8.1+
        if (function_exists('imagecreatefromavif') && function_exists('imageavif')) {
            $this->supported_formats[] = 'avif';
        }

        $open_basedir = ini_get('open_basedir');
        $this->open_basedir_enabled = !empty($open_basedir);
    }

    /**
     * @param string $format
     * @return bool
     */
    public function isSupportedFormat($format)
    {
        return in_array($format, $this->supported_formats, true);
    }

    /**
     * @param string $srcFile
     * @param string $destFile
     * @return bool
     */
    public function optimize($srcFile, $destFile)
    {
        $src_ext = pathinfo($srcFile, PATHINFO_EXTENSION);
        if ($src_ext === 'jpeg') {
            $src_ext = 'jpg';
        }

        return $this->doConvert($srcFile, $src_ext, $destFile, $src_ext);
    }

    /**
     * @param string $srcFile
     * @param string $destFile
     * @param string $format
     * @return bool
     */
    public function convert($srcFile, $destFile, $format)
    {
        $src_ext = pathinfo($srcFile, PATHINFO_EXTENSION);
        if ($src_ext === 'jpeg') {
            $src_ext = 'jpg';
        }

        return $this->doConvert($srcFile, $src_ext, $destFile, $format);
    }

    /**
     * @param string $src_file
     * @param string $src_format
     * @param string $dest_file
     * @param string $dest_format
     * @return bool
     */
    protected function doConvert($src_file, $src_format, $dest_file, $dest_format)
    {
        if (!$this->isSupportedFormat($src_format)) {
            $this->di->logger->notice(__METHOD__ . ": unsupported source format $src_format");
            return false;
        }

        if (!$this->isSupportedFormat($dest_format)) {
            $this->di->logger->notice(__METHOD__ . ": unsupported destination format $dest_format");
            return false;
        }

        $fs = $this->di->filesystem;

        if (!$fs->isFile($src_file)) {
            $this->di->logger->notice(__METHOD__ . ": file not found $src_file");
            return false;
        }

        $src_filesize = $fs->size($src_file);
        $src_timestamp = $fs->getModificationTime($src_file);

        $src_imagesize = getimagesize($src_file);
        if ($src_imagesize === false) {
            $this->di->logger->notice(__METHOD__ . ": unable to get image size of $src_file");
            return false;
        }

        if (function_exists('memory_get_usage')) {
            $max_mem_size = ini_get('memory_limit');
            if ($max_mem_size < 0) {
                $max_mem_size = '2G';
            }
            $max_mem_size = Ressio_Helper::str2int($max_mem_size);
            $src_image_mem_size = 8300 + 6 * $src_imagesize[0] * $src_imagesize[1];
            $extra_mem_size = 16 * 1024 + 2 * $src_filesize; // variables and other things
            $required_mem_size = memory_get_usage() + $src_image_mem_size + $extra_mem_size;
            if ($required_mem_size > $max_mem_size) {

                //ini_set('memory_limit', $required_mem_size);
                $this->di->logger->notice(__METHOD__ . ": not enough memory to process $src_file");
                return false;
            }
        }

        $src_image = $this->loadImage($src_format, $src_file);

        if ($src_image === false) {
            $this->di->logger->notice(__METHOD__ . ": unable to load $src_format image file $src_file");
            return false;
        }

        imagesavealpha($src_image, true);

        // Note: we cannot use "ob_" functions to get results of image* functions
        // as the formers "may not be called from a callback function"
        // (see http://phpweb.hostnet.com.br/manual/en/function.ob-start.php)
        $tmp_dir = sys_get_temp_dir();
        if ($this->open_basedir_enabled || !is_writable($tmp_dir)) {
            $tmp_dir = dirname($src_file);
        }
        if (!is_writable($tmp_dir)) {
            $this->di->logger->warning(__METHOD__ . ": temporary directory $tmp_dir is not writeable");
            return false;
        }
        $tmp_filename = tempnam($tmp_dir, 'Ressio');
        $data = false;
        switch ($dest_format) {
            case 'jpg':
                $jpegQuality = $this->di->config->img->jpegquality;
                if ($jpegQuality < 0) {
                    // for LQIP only
                    $jpegQuality = 0;
                    imagefilter($src_image, IMG_FILTER_SMOOTH, 1);
                    imagefilter($src_image, IMG_FILTER_SMOOTH, 1);
                }
                imageinterlace($src_image, true);
                $ok = imagejpeg($src_image, $tmp_filename, $jpegQuality);
                if ($ok) {
                    $data = file_get_contents($tmp_filename);
                    $data = $this->jpeg_clean($data);
                }
                break;
            case 'gif':
                $ok = imagegif($src_image, $tmp_filename);
                break;
            case 'png':
                $ok = imagepng($src_image, $tmp_filename, 9, PNG_ALL_FILTERS);
                break;
            case 'webp':
                if (!imageistruecolor($src_image)) {
                    imagepalettetotruecolor($src_image);
                }
                imagealphablending($src_image, true);
                $ok = imagewebp($src_image, $tmp_filename, $this->di->config->img->webpquality);
                break;
            case 'avif':
                if (!imageistruecolor($src_image)) {
                    imagepalettetotruecolor($src_image);
                }
                imagealphablending($src_image, true);
                $ok = imageavif($src_image, $tmp_filename, $this->di->config->img->avifquality);
                break;
        }
        imagedestroy($src_image);
        if (!$ok) {
            $this->di->logger->warning(__METHOD__ . ": failed converting $src_file to $dest_format format");
            @unlink($tmp_filename);
            return false;
        }

        if ($data === false) {
            $data = file_get_contents($tmp_filename);
        }
        unlink($tmp_filename);

        if ($src_format === $dest_format && (empty($data) || strlen($data) >= $src_filesize)) {
            // keep link to original file
            return false;
        }

        $ret = $fs->putContents($dest_file, $data);
        $fs->touch($dest_file, $src_timestamp);

        return $ret;
    }

    /**
     * Rescale Image
     * @param string $src_imagepath
     * @param string $dest_imagepath
     * @param int $dest_width
     * @param int $dest_height
     * @param ?string $dest_ext
     * @return bool
     */
    public function rescale($src_imagepath, $dest_imagepath, $dest_width, $dest_height, $dest_ext = null)
    {
        $src_ext = strtolower(pathinfo($src_imagepath, PATHINFO_EXTENSION));
        if ($src_ext === 'jpeg') {
            $src_ext = 'jpg';
        }

        if (!$this->isSupportedFormat($src_ext)) {
            $this->di->logger->notice(__METHOD__ . ": format $src_ext of $src_imagepath is not supported");
            return false;
        }

        if ($dest_ext === null) {
            $dest_ext = $src_ext;
        }

        $config = $this->di->config->img;
        $fs = $this->di->filesystem;

        $src_mtime = $fs->getModificationTime($src_imagepath);

        $src_imagesize = getimagesize($src_imagepath);
        if ($src_imagesize === false) {
            $this->di->logger->notice(__METHOD__ . ": unable to get image size of $src_imagepath");
            return false;
        }
        list($src_width, $src_height) = $src_imagesize;

        if (function_exists('memory_get_usage')) {
            $max_mem_size = ini_get('memory_limit');
            if ($max_mem_size < 0) {
                $max_mem_size = '2G';
            }
            $max_mem_size = Ressio_Helper::str2int($max_mem_size);
            $src_image_mem_size = 8300 + 6 * $src_width * $src_height;
            $dest_image_mem_size = 8300 + 6 * $dest_width * $dest_height;
            $extra_mem_size = 16 * 1024 + 2 * filesize($src_imagepath); // variables and other things
            $required_mem_size = memory_get_usage() + $src_image_mem_size + $dest_image_mem_size + $extra_mem_size;
            if ($required_mem_size > $max_mem_size) {

                //ini_set('memory_limit', $required_mem_size);
                $this->di->logger->notice(__METHOD__ . ": not enought memory to process $src_imagepath");
                return false;
            }
        }

        $src_image = $this->loadImage($src_ext, $src_imagepath);

        if ($src_image === false) {
            $this->di->logger->notice(__METHOD__ . ": unable to load $src_ext image file $src_imagepath");
            return false;
        }

        $dest_image = imagecreatetruecolor($dest_width, $dest_height);

        //Additional operations to preserve transparency in images
        switch ($dest_ext) {
            case 'png':
            case 'gif':
            case 'webp':
            case 'avif':
                imagealphablending($dest_image, false);
                $color = imagecolortransparent($dest_image, imagecolorallocatealpha($dest_image, 0, 0, 0, 127));
                imagefilledrectangle($dest_image, 0, 0, $dest_width, $dest_height, $color);
                imagesavealpha($dest_image, true);
                break;
            default: // jpg
                $color = imagecolorallocate($dest_image, 255, 255, 255); // white
                imagefilledrectangle($dest_image, 0, 0, $dest_width, $dest_height, $color);
                break;
        }

        $ret = imagecopyresampled($dest_image, $src_image, 0, 0, 0, 0, $dest_width, $dest_height, $src_width, $src_height);

        imagedestroy($src_image);

        if (!$ret) {
            imagedestroy($dest_image);
            $this->di->logger->warning(__METHOD__ . ": failed rescaling $src_imagepath to {$dest_width}x{$dest_height}");
            return false;
        }

        // Note: we cannot use "ob_" functions to get results of image* functions
        // as the formers "may not be called from a callback function"
        // (see http://phpweb.hostnet.com.br/manual/en/function.ob-start.php)
        $tmp_dir = sys_get_temp_dir();
        if ($this->open_basedir_enabled || !is_writable($tmp_dir)) {
            $tmp_dir = dirname($dest_imagepath);
        }
        if (!is_writable($tmp_dir)) {
            $this->di->logger->warning(__METHOD__ . ": temporary directory $tmp_dir is not writeable");
            return false;
        }
        $tmp_filename = tempnam($tmp_dir, 'Ressio');
        $data = false;
        switch ($dest_ext) {
            case 'jpg':
                imageinterlace($dest_image, true);
                $ok = imagejpeg($dest_image, $tmp_filename, $config->jpegquality);
                if ($ok) {
                    $data = file_get_contents($tmp_filename);
                    $data = $this->jpeg_clean($data);
                }
                break;
            case 'gif':
                imagetruecolortopalette($dest_image, true, 256);
                $ok = imagegif($dest_image, $tmp_filename);
                break;
            case 'png':
                $ok = imagepng($dest_image, $tmp_filename, 9, PNG_ALL_FILTERS);
                break;
            case 'webp':
                $ok = imagewebp($dest_image, $tmp_filename, $config->webpquality);
                break;
            case 'avif':

                $ok = imageavif($dest_image, $tmp_filename, $config->avifquality);
                break;
        }
        imagedestroy($dest_image);
        if (!$ok) {
            $this->di->logger->warning(__METHOD__ . ": failed rescaling $src_imagepath to {$dest_width}x{$dest_height} $dest_ext format");
            @unlink($tmp_filename);
            return false;
        }
        if ($data === false) {
            $data = file_get_contents($tmp_filename);
        }
        unlink($tmp_filename);
        $fs->putContents($dest_imagepath, $data);
        $imgOptimizer = $this->di->imgOptimizer;
        if ($config->minifyrescaled && $imgOptimizer) {
            $imgOptimizer->optimize($dest_imagepath, $dest_imagepath);
        }

        $fs->touch($dest_imagepath, $src_mtime);

        return true;
    }

    /**
     * Remove JFIF and Comment headers from GD2-generated jpeg (saves 79 bytes)
     * @param string $jpeg_src
     * @return bool|string
     */
    private function jpeg_clean($jpeg_src)
    {
        // Start of Image (SOI)
        $jpeg_clr = "\xFF\xD8";
        if (strncmp($jpeg_src, $jpeg_clr, 2) !== 0) {
            return false;
        }
        $pos = 2;
        $size = strlen($jpeg_src);
        while ($pos < $size) {
            if ($jpeg_src[$pos] !== "\xFF") {
                return false;
            }
            $b = $jpeg_src[$pos + 1];
            if ($b === "\xDA") {
                // Start of Scan (SOS)
                return $jpeg_clr . substr($jpeg_src, $pos);
            }
            $len = unpack('n', substr($jpeg_src, $pos + 2, 2))[1];
            if ($b !== "\xE0" && $b !== "\xFE") {
                // not [Application Field 0 (APP0) || Comment (COM)]
                $jpeg_clr .= substr($jpeg_src, $pos, $len + 2);
            }
            $pos += $len + 2;
        }
        return false;
    }

    /**
     * Count animation frames in gif file, return TRUE if two or more
     * @param string $content
     * @return bool
     */
    private function is_gif_ani($content)
    {
        $count = preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $content);
        return $count > 1;
    }

    /**
     * Detect Animation Control Chunk in APNG, return TRUE if found
     * @param string $content
     * @return bool
     */
    private function is_png_ani($content)
    {
        $pos = strpos($content, 'IDAT');
        return $pos !== false && strpos(substr($content, 0, $pos), 'acTL') !== false;
    }

    /**
     * @param string $content
     * @return resource|false
     */
    private function recoverPNG($content)
    {
        // try restore PNG image
        $png_header = "\x89PNG\r\n\x1A\n";
        $png_end_chunk = 'IEND';
        if (strncmp($content, $png_header, 8) === 0 && strpos($content, $png_end_chunk) === false) {
            $content .= $png_end_chunk;
            return imagecreatefromstring($content);
        }
        return false;
    }

    /**
     * @param $src_format
     * @param $src_file
     * @return GdImage|false
     */
    protected function loadImage($src_format, $src_file)
    {
        switch ($src_format) {
            case 'jpg':
                return imagecreatefromjpeg($src_file);

            case 'gif':
                $content = $this->di->filesystem->getContents($src_file);
                if ($this->is_gif_ani($content)) {
                    $this->di->logger->debug(__METHOD__ . ": skip animated GIF $src_file");
                    return false;
                }
                return imagecreatefromstring($content);

            case 'png':
                $content = $this->di->filesystem->getContents($src_file);
                if ($this->is_png_ani($content)) {
                    $this->di->logger->debug(__METHOD__ . ": skip animated APNG $src_file");
                    return false;
                }
                $src_image = @imagecreatefromstring($content);
                if ($src_image !== false) {
                    return $src_image;
                }
                // try restore image
                return $this->recoverPNG($content);

            case 'bmp':
                return imagecreatefrombmp($src_file);

            case 'webp':

                $h = fopen($src_file, 'rb');
                if ($h !== false) {
                    $header = fread($h, 12);
                    fclose($h);
                    if (preg_match('/RIFF.{4}WEBP/sA', $header)) {
                        return imagecreatefromwebp($src_file);
                    }
                }
                break;

            case 'avif':
                return imagecreatefromavif($src_file);
        }

        $this->di->logger->notice(__METHOD__ . ": loading $src_file with unknown image format $src_format");
        return false;
    }
}