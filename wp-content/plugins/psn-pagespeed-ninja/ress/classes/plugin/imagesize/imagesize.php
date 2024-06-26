<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Plugin_Imagesize extends Ressio_Plugin
{
    /**
     * @param Ressio_DI $di
     * @param null|stdClass $params
     */
    public function __construct($di, $params = null)
    {
        parent::__construct($di);
        $this->loadConfig(__DIR__ . '/config.json', $params);
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagIMG($event, $optimizer, $node)
    {
        if ($node->hasAttribute('ress-nosize')) {
            $node->removeAttribute('ress-nosize');
            return;
        }

        if ($optimizer->nodeIsDetached($node) || $optimizer->isNoscriptState()) {
            return;
        }
        if ($node->hasAttribute('width') || $node->hasAttribute('height') || !$node->hasAttribute('src')) {
            return;
        }

        $src = $node->getAttribute('src');
        $src_imagepath = $this->di->urlRewriter->urlToFilepath($src);
        if ($src_imagepath === null) {
            return;
        }

        if ($this->di->filesystem->isFile($src_imagepath)) {
            $src_width = false;
            $src_height = false;
            if (pathinfo($src_imagepath, PATHINFO_EXTENSION) === 'svg') {
                $f = fopen($src_imagepath, 'rb');
                // default fread buffer size is 8192, but we don't need such a long string to grab the header tag
                $chunk = fread($f, 1024);
                fclose($f);
                if (preg_match('/<svg\s.*?>/s', $chunk, $match)) {
                    $tag = $match[0];
                    if (preg_match('/\bwidth=[\'"](\d+(?:\.\d+)?)[\'"]/', $tag, $match)) {
                        $src_width = $match[1];
                    }
                    if (preg_match('/\bheight=[\'"](\d+(?:\.\d+)?)[\'"]/', $tag, $match)) {
                        $src_height = $match[1];
                    }
                    if (
                        $src_width === false && $src_height === false &&
                        preg_match('/\bviewBox=[\'"](\d+(?:\.\d+)?) (\d+(?:\.\d+)?) (\d+(?:\.\d+)?) (\d+(?:\.\d+)?)[\'"]/', $tag, $match)
                    ) {
                        $src_width = $this->formatFloatNumber((float)$match[3] - (float)$match[1]);
                        $src_height = $this->formatFloatNumber((float)$match[4] - (float)$match[2]);
                    }
                }
            } else {
                $src_imagesize = getimagesize($src_imagepath);
                if ($src_imagesize !== false) {
                    list($src_width, $src_height) = $src_imagesize;
                }
            }
            if ($src_width && $src_height) {
                $node->setAttribute('width', $src_width);
                $node->setAttribute('height', $src_height);
            }
        }
    }

    /**
     * @param float $x
     * @return string
     */
    private function formatFloatNumber($x)
    {
        $s = number_format($x, 3, '.', '');
        return rtrim(rtrim($s, '0'), '.');
    }
}