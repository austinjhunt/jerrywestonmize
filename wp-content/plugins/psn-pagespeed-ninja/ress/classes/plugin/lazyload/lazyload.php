<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Plugin_Lazyload extends Ressio_Plugin
{
    /** @var string */
    public static $blankImage = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    /** @var string */
    public static $blankIframe = 'about:blank';

    /** @var int */
    private $numImages = 0;
    /** @var int */
    private $numBgImages = 0;
    /** @var int */
    private $numVideos = 0;
    /** @var int */
    private $numIframes = 0;
    /** @var bool */
    private $lazyVideoState = false;

    /**
     * @param Ressio_DI $di
     * @param null|stdClass $params
     */
    public function __construct($di, $params = null)
    {
        parent::__construct($di);
        $this->loadConfig(__DIR__ . '/config.json', $params);

        if ($this->params->image) {
            $di->dispatcher->addListener('HtmlIterateTagIMG', array($this, 'processHtmlIterateTagIMG'));
        }
        if ($this->params->video) {
            $di->dispatcher->addListener('HtmlIterateTagVIDEO', array($this, 'processHtmlIterateTagVIDEO'));
            $di->dispatcher->addListener('HtmlIterateTagSOURCE', array($this, 'processHtmlIterateTagSOURCE'));
            $di->dispatcher->addListener('HtmlIterateTagVIDEOAfterEnd', array($this, 'processHtmlIterateTagVIDEOAfterEnd'));
        }
        if ($this->params->iframe || $this->params->youtube) {
            $di->dispatcher->addListener('HtmlIterateTagIFRAME', array($this, 'processHtmlIterateTagIFRAME'));
        }
        if ($this->params->background_image) {
            $di->dispatcher->addListener('HtmlIterateNodeBefore', array($this, 'processHtmlIterateNodeBefore'));
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function processHtmlIterateTagIMG($event, $optimizer, $node)
    {
        if ($this->params->rules_img_exclude && $optimizer->matchExcludeRule($node, $this->params->rules_img_exclude)) {
            return;
        }

        $this->numImages++;
        if ($this->numImages > $this->params->skipimages) {
            $this->lazifyNode($node, $optimizer);
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function processHtmlIterateTagVIDEO($event, $optimizer, $node)
    {
        if ($optimizer->nodeIsDetached($node) || $optimizer->isNoscriptState()) {
            return;
        }
        if ($this->params->rules_video_exclude && $optimizer->matchExcludeRule($node, $this->params->rules_video_exclude)) {
            return;
        }

        $this->numVideos++;
        if ($this->numVideos <= $this->params->skipvideos) {
            return;
        }

        if ($this->hasDataAttribute($node)) {
            return;
        }

        $this->lazyVideoState = true;

        if ($node->hasAttribute('src') && !$node->hasAttribute('data-src')) {
            $src = $node->getAttribute('src');
            if (strncmp($src, 'data:', 5) !== 0) {
                $this->di->dispatcher->triggerEvent('CDNTransform', array(&$src));
                $node->setAttribute('data-src', $src);
                $node->removeAttribute('src');
            }
        }

        if ($node->hasAttribute('poster') && !$node->hasAttribute('data-poster')) {
            $src = $node->getAttribute('poster');
            if (strncmp($src, 'data:', 5) !== 0) {
                $this->di->dispatcher->triggerEvent('CDNTransform', array(&$src));
                $node->setAttribute('data-poster', $src);
                $node->removeAttribute('poster');
            }
        }

        $node->addClass('lazy-video');
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function processHtmlIterateTagSOURCE($event, $optimizer, $node)
    {
        if (!$this->lazyVideoState || $optimizer->nodeIsDetached($node) || $optimizer->isNoscriptState()) {
            return;
        }

        if ($node->hasAttribute('src') && !$node->hasAttribute('data-src')) {
            $src = $node->getAttribute('src');
            if (strncmp($src, 'data:', 5) !== 0) {
                $this->di->dispatcher->triggerEvent('CDNTransform', array(&$src));
                $node->setAttribute('data-src', $src);
                $node->removeAttribute('src');
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function processHtmlIterateTagVIDEOAfterEnd($event, $optimizer, $node)
    {
        $this->lazyVideoState = false;
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function processHtmlIterateTagIFRAME($event, $optimizer, $node)
    {
        if ($this->params->rules_iframe_exclude && $optimizer->matchExcludeRule($node, $this->params->rules_iframe_exclude)) {
            return;
        }

        if ($this->params->youtube) {
            if ($optimizer->nodeIsDetached($node) || $optimizer->isNoscriptState()) {
                return;
            }
            $src = $node->getAttribute('src');
            if (preg_match('@^(?:https?:)?//(?:www\.)?youtube(?:-nocookie)?\.com/embed/([\w-]+)(?:[?#/]|$)@', $src, $match)) {
                $id = $match[1];
                $src .= (strpos($src, '?') === false ? '?' : '&amp;') . 'mute=1&amp;autoplay=1';
                $node->removeAttribute('src');
                $node->setAttribute('data-src', $src);
                $node->setAttribute('data-youtube', $id);
                $this->di->dispatcher->triggerEvent('PreconnectDomain', array($src));
                $this->di->dispatcher->triggerEvent('PreconnectDomain', array('https://i.ytimg.com/'));
                return;
            }
        }

        if ($this->params->iframe) {
            $this->numIframes++;
            if ($this->numIframes > $this->params->skipiframes) {
                $this->lazifyNode($node, $optimizer);
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function processHtmlIterateNodeBefore($event, $optimizer, $node)
    {
        if (!$optimizer->nodeIsElement($node) || $optimizer->nodeIsDetached($node) || $optimizer->isNoscriptState()) {
            return;
        }

        if (!$node->hasAttribute('style')) {
            return;
        }

        $style = $node->getAttribute('style');
        if (strpos($style, 'background-image') === false) {
            return;
        }

        if ($this->params->rules_bg_exclude && $optimizer->matchExcludeRule($node, $this->params->rules_bg_exclude)) {
            return;
        }

        $this->numBgImages++;
        if ($this->numBgImages <= $this->params->skipbgimages) {
            return;
        }

        if (!preg_match_all('/(<=\bbackground-image:)\s*url\(\'([^\']+)\'\)/', $style, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return;
        }

        $match = array_pop($matches);
        $src = trim($match[1][0]);

        if (strncmp($src, 'data:', 5) === 0) {
            return;
        }

        $this->di->dispatcher->triggerEvent('CDNTransform', array(&$src));
        $node->setAttribute('data-lazybg', $src);

        $bg = 'none';
        if ($this->params->lqip === 'full') {
            $src = $this->getLQIP($src);
            if ($src !== false) {
                $bg = "url('" . $src . "')";
            }
        }

        $pos = $match[0][1];
        $len = strlen($match[0][0]);
        $style = substr($style, 0, $pos) . $bg . substr($style, $pos + $len);
        $node->setAttribute('style', $style);

    }

    /**
     * @param IRessio_HtmlNode $node
     * @param IRessio_HtmlOptimizer $optimizer
     * @return void
     */
    private function lazifyNode($node, $optimizer)
    {
        if ($optimizer->nodeIsDetached($node) || $optimizer->isNoscriptState() || !$node->hasAttribute('src')) {
            return;
        }

        $src = $node->getAttribute('src');
        if (strncmp($src, 'data:', 5) === 0) {
            return;
        }

        // 1x1 tracking pixel analytics
        if ($node->hasAttribute('width') && $node->hasAttribute('height')
            && $node->getAttribute('width') === '1' && $node->getAttribute('height') === '1') {
            return;
        }

        // skip for data attributes (sliders, etc.)
        if ($this->hasDataAttribute($node)) {
            return;
        }

        if ($this->params->method === 'native') {
            $node->setAttribute('loading', 'lazy');
            if ($this->params->lqip !== 'none' && $node->getTag() === 'img' && !$node->hasAttribute('onload')) {
                $src = $this->getLQIP($src);
                if ($src !== false) {
                    $style = $node->hasAttribute('style') ? $node->getAttribute('style') . ';' : '';
                    $style .= "background-image:url('{$src}');background-repeat:no-repeat;background-size:cover";
                    $node->setAttribute('style', $style);
                    $node->setAttribute('onload', "this.style.removeProperty('background-image')");
                }
            }
        } else {
            switch ($this->params->noscriptpos) {
                case 'none':
                    break;
                case 'before':
                    $optimizer->nodeInsertBefore($node, 'noscript', null, $optimizer->nodeToString($node));
                    break;
                case 'after':
                    $optimizer->nodeInsertAfter($node, 'noscript', null, $optimizer->nodeToString($node));
                    break;
            }

            $node->addClass('lazy');
            if ($node->getTag() === 'img') {
                // img
                $srcset = $node->getAttribute($node->hasAttribute('srcset') ? 'srcset' : 'src');
                $this->di->dispatcher->triggerEvent('CDNTransformSrcset', array(&$srcset));
                $node->setAttribute('data-src', $srcset);
                switch ($this->params->lqip) {
                    case 'none':
                        $src = false;
                        break;
                    case 'full':
                        $src = $this->getLQIP($src);
                        break;
                    case 'low':
                        $low_src = $this->getLQIP($src, $src_width, $src_height);
                        if ($low_src !== false) {
                            $style = $node->hasAttribute('style') ? $node->getAttribute('style') . ';' : '';
                            $style .= "aspect-ratio:{$src_width}/{$src_height}";
                            $node->setAttribute('style', $style);
                            $src = $low_src;
                        }
                        break;
                }
                if ($src === false) {
                    $src = self::$blankImage;
                } else {
                    $this->di->dispatcher->triggerEvent('CDNTransform', array(&$src));
                }
                $node->setAttribute('srcset', $src);
            } else {
                // iframe
                $node->setAttribute('src', self::$blankIframe);
                $node->setAttribute('data-src', $src);
            }
        }
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagHEADAfterEnd($event, $optimizer, $node)
    {
        if ($this->params->method === 'native') {
            return;
        }

        if ($this->params->noscriptpos !== 'none') {
            $optimizer->prependHead(
                array('noscript', array(), '<style>.lazy{display:none}</style>')
            );
        }
        if ($this->params->lqip !== 'none' && $this->params->lqip_blur) {
            // Note: clip-path:inset(0) doesn't prevent outer blur in the case of border-radius
            $optimizer->appendStyleDeclaration('img.lazy-hidden{-webkit-filter:blur(8px);filter:blur(8px);-webkit-mask:linear-gradient(#000 0 0) content-box;mask:linear-gradient(#000 0 0) content-box}');
        }

        $defer = array('defer' => true);

        $path_prefix = __DIR__ . '/js/lazyloadxt';
        $suffix = $this->params->debug ? '.v3.js' : '.v3.min.js';

        if ($this->params->edgey >= 0) {
            $optimizer->appendScriptDeclaration('lazyLoadXT={edgeY:"' . (int)$this->params->edgey . 'px"};', $defer);
        }
        $optimizer->appendScriptDeclaration(file_get_contents($path_prefix . $suffix), $defer);

        $addons = (isset($this->params->addons) && is_array($this->params->addons)) ? $this->params->addons : array();
        if ($this->params->background_image && !in_array('bg', $addons, true)) {
            $addons[] = 'bg';
        }
        if ($this->params->video && !in_array('video', $addons, true)) {
            $addons[] = 'video';
        }
        if ($this->params->youtube && !in_array('youtube', $addons, true)) {
            $addons[] = 'youtube';
        }
        //if (($this->params->video || $this->params->iframe) && !in_array('video', $addons, true)) {
        //    $addons[] = 'video';
        //}
        //if ($this->params->srcset && !in_array('srcset', $addons, true)) {
        //    $addons[] = 'srcset';
        //}
        foreach ($addons as $addon) {
            $filename = "{$path_prefix}.{$addon}{$suffix}";
            if (is_file($filename)) {
                $optimizer->appendScriptDeclaration(file_get_contents($filename), $defer);
            } else {
                $this->di->logger->warning("Cannot load $filename in " . __METHOD__);
            }
        }
    }

    /**
     * @param string $src_url
     * @param ?int $src_width
     * @param ?int $src_height
     * @return string
     */
    public function getLQIP($src_url, &$src_width = null, &$src_height = null)
    {
        $src_ext = strtolower(pathinfo($src_url, PATHINFO_EXTENSION));
        if ($src_ext === 'jpeg') {
            $src_ext = 'jpg';
        }

        $urlRewriter = $this->di->urlRewriter;
        $src_imagepath = $urlRewriter->urlToFilepath($src_url);
        if ($src_imagepath !== null && $this->di->filesystem->isFile($src_imagepath)) {
            if ($src_ext === 'svg') {
                $xml = simplexml_load_file($src_imagepath);
                if ($xml->getName() === 'svg') {
                    $svg = '<svg xmlns="http://www.w3.org/2000/svg"';
                    if (isset($xml['width'])) {
                        $svg .= " width=\"{$xml['width']}\"";
                    }
                    if (isset($xml['height'])) {
                        $svg .= " height=\"{$xml['height']}\"";
                    }
                    if (isset($xml['viewBox'])) {
                        $svg .= " viewBox=\"{$xml['viewBox']}\"";
                    }
                    $svg .= '></svg>';
                    return 'data:image/svg+xml,' . rawurlencode($svg);
                }
            } else {
                $type = $this->params->lqip;
                $dest_imagepath = $this->getLQIPPath($src_imagepath, $type);

                switch ($type) {
                    case 'full':
                        $jpegquality = $this->config->img->jpegquality;
                        $this->config->img->jpegquality = -1;
                        if ($src_ext === 'jpg') {
                            $dest_imagepath = $this->di->imgOptimizer->optimize($src_imagepath, $dest_imagepath);
                        } else {
                            $dest_imagepath = $this->di->imgOptimizer->convert($src_imagepath, 'jpg', $dest_imagepath);

                        }
                        $this->config->img->jpegquality = $jpegquality;

                        if ($dest_imagepath !== false && is_file($dest_imagepath) && !is_link($dest_imagepath)) {
                            if ($this->params->lqip_embed) {
                                $data = file_get_contents($dest_imagepath);
                                if ($data !== false && $data !== '') {
                                    return 'data:image/jpeg;base64,' . base64_encode($data);
                                }
                            } else {
                                return $urlRewriter->filepathToUrl($dest_imagepath);
                            }
                        }
                        break;

                    case 'low':
                        // fast check $dest_imagepath exists and has same timestamp
                        if (file_exists($dest_imagepath) && filemtime($src_imagepath) === filemtime($dest_imagepath)) {
                            if (filesize($dest_imagepath) === 0) {
                                return false;
                            }
                        } else {
                            list($src_width, $src_height) = getimagesize($src_imagepath);
                            if ($src_width === false) {
                                $this->di->logger->notice(__METHOD__ . ": unable to get image size of $src_imagepath");
                                return false;
                            }
                            if ($src_width > 0 && $src_height > 0) {
                                if ($src_width > $src_height) {
                                    $width = $this->params->lqip_low_res;
                                    $height = max(2, (int)round($width * $src_height / $src_width));
                                } else {
                                    $height = $this->params->lqip_low_res;
                                    $width = max(2, (int)round($height * $src_width / $src_height));
                                }

                                $dest_imagepath = $this->di->imgOptimizer->rescale($src_imagepath, 'gif', $width, $height, $dest_imagepath);
                            }
                        }
                        if ($dest_imagepath !== false && is_file($dest_imagepath) && !is_link($dest_imagepath)) {
                            $data = file_get_contents($dest_imagepath);
                            if ($data !== false && $data !== '') {
                                return 'data:image/gif;base64,' . base64_encode($data);
                            }
                        }
                        break;
                }
            }
        }

        return false;
    }

    /**
     * @param string $src_imagepath
     * @param string $type
     * @return string
     */
    public function getLQIPPath($src_imagepath, $type)
    {
        $hash_path = substr(sha1(dirname($src_imagepath)), 0, 8);

        $dest_imagedir = "{$this->config->webrootpath}{$this->config->staticdir}/img-lqip/{$hash_path}";
        if (!$this->di->filesystem->makeDir($dest_imagedir)) {
            $this->di->logger->error('Unable to create directory in ' . __METHOD__ . ': ' . $dest_imagedir);
        }

        $src_imagename = pathinfo($src_imagepath, PATHINFO_FILENAME);
        return "{$dest_imagedir}/{$src_imagename}.lqip.{$type}";
    }

    /**
     * @param IRessio_HtmlNode $node
     * @return bool
     */
    private function hasDataAttribute($node)
    {
        if ($node instanceof DOMElement) {
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attr) {
                    if (strncmp($attr->nodeName, 'data-', 5) === 0) {
                        return true;
                    }
                }
            }
        } else {
            if (count($node->attributes)) {
                foreach ($node->attributes as $name => $value) {
                    if (strncmp($name, 'data-', 5) === 0) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
