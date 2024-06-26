<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_CssCombiner implements IRessio_CssCombiner, IRessio_DIAware
{
    /** @var Ressio_DI */
    private $di;
    /** @var Ressio_Config */
    private $config;

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->di = $di;
        $this->config = $di->config;
    }

    /**
     * Returns list of combined nodes
     * @param array[] $styleList
     * @return Ressio_NodeWrapper[]
     */
    public function combineToNodes($styleList)
    {
        /** @var string[] $deps */
        $deps = array(
            'css',
            get_class($this->di->cssMinify),
            // $this->di->deviceDetector->vendor(),
            $this->config->var->imagenextgenformat,
            $this->config->css->inlinelimit
        );
        foreach ($styleList as &$item) {
            if ($item['type'] !== 'inline') {
                $filename = $this->di->urlRewriter->urlToFilepath($item['src']);
                if ($filename !== null) {
                    $item['filename'] = $filename;
                    // add file's timestamp to hash
                    $item['time'] = $this->di->filesystem->getModificationTime($filename);
                }
            }
            $deps[] = json_encode($item);
        }
        unset($item);

        $cache = $this->di->cache;
        $cache_id = $cache->id($deps, 'css');

        // recreate cache in worker mode
        $result = $this->config->var->workermode
            ? $cache->lock($cache_id)
            : $cache->getOrLock($cache_id);

        if (is_string($result)) {
            $result = json_decode($result);
            foreach ($result as $i => $node) {
                $result[$i] = new Ressio_NodeWrapper($node->tagName, $node->content, (array)$node->attributes, $node->self_close_str);
            }
        } else {
            $asyncMode = $this->config->worker->enabled && !$this->config->var->workermode;
            $minifyCss = $asyncMode ? new Ressio_CssMinify_None($this->di) : $this->di->cssMinify;
            $crossFileOptimization = !$asyncMode && $this->config->css->crossfileoptimization;

            switch ($this->config->fileloader) {
                case 'php':
                    $targetDir = dirname($this->config->fileloaderphppath) . '/';
                    $targetDirUrl = $this->di->urlRewriter->filepathToUrl($targetDir);
                    break;
                case 'file':
                default:
                    $targetDirUrl = "{$this->config->webrooturi}{$this->config->staticdir}/";
            }

            $result = $this->combine($styleList, $targetDirUrl, $minifyCss, $crossFileOptimization);
            if ($result) {
                $cache->storeAndUnlock($cache_id, json_encode($result));
            }

            if ($asyncMode && !($this->di->cssMinify instanceof Ressio_CssMinify_None)) {
                $imagenextgenformat = $this->config->var->imagenextgenformat;
                // add off-request optimization
                $params = compact('styleList', 'imagenextgenformat');
                // inlined styles might be extra-optimized
                $this->di->worker->runTask('cssCombine', $params);
            }
        }
        $wrapper = new stdClass();
        $wrapper->nodes = $result;
        $this->di->dispatcher->triggerEvent('CssCombinerNodeList', array($wrapper));

        return $wrapper->nodes;
    }

    /**
     * @param Ressio_NodeWrapper[] $nodes
     * @return string
     */
    public function nodesToHtml($nodes)
    {
        if ($this->config->css->nonce !== null) {
            foreach ($nodes as $node) {
                if ($node->tagName === 'style') {
                    $node->attributes['nonce'] = $this->config->css->nonce;
                }
            }
        }

        $s = implode($nodes);
        $wrapper = new stdClass();
        $wrapper->content = $s;

        // Note: DOM parser doesn't call nodesToHtml, and so we cannot rely on CssCombinerHtml event
        //$this->di->dispatcher->triggerEvent('CssCombinerHtml', array($wrapper));

        return $wrapper->content;
    }

    /**
     * @param array[] $styleList
     * @param string $targetUrl
     * @param IRessio_CssMinify $minifyCss
     * @param bool $crossFileOptimization
     * @return Ressio_NodeWrapper[]
     */
    private function combine($styleList, $targetUrl, $minifyCss, $crossFileOptimization = false)
    {
        $dispatcher = $this->di->dispatcher;
        $fs = $this->di->filesystem;
        $urlRewriter = $this->di->urlRewriter;
        $cssRelocator = $this->di->cssRelocator;

        $dispatcher->triggerEvent('CssCombineBefore', array(&$styleList, &$targetUrl));
        $rules = $this->config->css->rules_minify_exclude;






        //$hash_prefix = get_class($this->di->cssMinify);

        $base = $urlRewriter->getBase();

        $targetBase = dirname($targetUrl . 'x');
        if ($targetBase === '\\') {
            $targetBase = '/';
        }

        $result = array();

        $item_content = '';
        $count = 0;

        foreach ($styleList as $item) {
            $content = '';
            try {
                $media = $item['media'];
                if ($item['type'] === 'inline') {
                    $content = $item['style'];
                    $content = preg_replace('/(^\s*<!--\s*|\s*-->\s*$)/', '', $content);

                    $dispatcher->triggerEvent('CssInlineRelocateBefore', array(&$content));
                    $content = $cssRelocator->run($content, $base, $targetBase, $media);
                    $dispatcher->triggerEvent('CssInlineRelocateAfter', array(&$content));

                    if (!isset($rules->content) || !preg_match($rules->content, $content)) {
                        $dispatcher->triggerEvent('CssInlineMinifyBefore', array(&$content));
                        $content = $minifyCss->minify($content);
                        $dispatcher->triggerEvent('CssInlineMinifyAfter', array(&$content));
                    }
                } else {
                    $src = $item['src'];
                    $path = isset($item['filename']) ? $item['filename'] : $urlRewriter->urlToFilepath($src);
                    if ($path === null || pathinfo($path, PATHINFO_EXTENSION) !== 'css') {
                        // external or not-a-css file
                        throw new ERessio_InvalidCss('File ' . $path . ' is skipped.', 1);
                    }
                    // local css file
                    $content = $fs->getContents($path);
                    if ($content === false) {
                        throw new ERessio_InvalidCss('File ' . $path . ' not found.');
                    }
                    $content = Ressio_Helper::removeBOM($content);
                    //if (strncmp($content, "\x1f\x8b", 2) === 0) {
                    //    $content = gzinflate(substr($content, 10, -8));
                    //}

                    $dispatcher->triggerEvent('CssFileRelocateBefore', array($src, &$content));
                    $hasImports = (strpos($content, '@import') !== false);
                    $content = $cssRelocator->run($content, dirname($src), $targetBase, $media);
                    $dispatcher->triggerEvent('CssFileRelocateAfter', array($src, &$content));

                    if (!isset($rules->attrs->href) || !preg_match($rules->attrs->href, $src)) {
                        // Skip .min.css files (@todo make it optional)
                        $isMinified = !$hasImports && substr_compare($path, '.min.css', -8) === 0;
                        if ($isMinified) {
                            // remove source mapping
                            $content = preg_replace('|/\*#\s+sourceMappingURL=\S+\s+\*/$|', '', $content);
                        } else {
                            $dispatcher->triggerEvent('CssFileMinifyBefore', array($src, &$content, $targetBase));
                            $content = $minifyCss->minify($content);
                            $dispatcher->triggerEvent('CssFileMinifyAfter', array($src, &$content));
                        }
                    }
                }
                // Unprocessed @import
                if ($count > 0 && strpos($content, '@import') !== false) {
                    if ($item_content !== '') {
                        $result[] = $this->saveStyleNode($crossFileOptimization && $count > 1, $minifyCss, $item_content);
                        $item_content = '';
                    }
                    $count = 0;
                }
            } catch (ERessio_InvalidCss $e) {
                $level = $e->getCode() === 1 ? 'notice' : 'warning';
                $message = 'Catched error in ' . __METHOD__ . ': ' . $e->getMessage();
                if ($item['type'] === 'ref') {
                    $message .= ' [in file: ' . $item['src'] . ']';
                }
                $this->di->logger->log($level, $message);

                if ($item_content !== '') {
                    $result[] = $this->saveStyleNode($crossFileOptimization && $count > 1, $minifyCss, $item_content);
                }

                $result[] = $this->createStyleNode($item['type'], isset($item['src']) ? $item['src'] : null, $content, $item['media']);

                $item_content = '';
                $count = 0;

                continue;
            }

            $content = rtrim($content, "; \t\n\r\0\x0B");
            if ($content !== '' && $content[strlen($content) - 1] !== '}' && !preg_match('#\}(?>\s+|/\*.*?\*/)*$#', $content)) {
                $content .= ';';
            }
            $comment_start = strrpos($content, '/*');
            if ($comment_start !== false) {
                $comment_end = strrpos($content, '*/', $comment_start + 2);
                if ($comment_end === false) {
                    $content .= "/**/\n";
                }
            }

            $item_content .= $content;
            $count++;
        }

        if ($item_content !== '') {
            $result[] = $this->saveStyleNode($crossFileOptimization && $count > 1, $minifyCss, $item_content);
        }

        return $result;
    }

    /**
     * @param string $type
     * @param ?string $src
     * @param ?string $content
     * @param string $media
     * @return Ressio_NodeWrapper
     */
    private function createStyleNode($type, $src, $content, $media = '')
    {
        if ($type === 'inline') {
            $node = new Ressio_NodeWrapper('style');
            $node->content = $content;
        } else {
            $node = new Ressio_NodeWrapper('link');
            $node->attributes['rel'] = 'stylesheet';
            $node->attributes['href'] = $src;
        }
        if ($media !== '' && $media !== 'all') {
            $node->attributes['media'] = $media;
        }
        return $node;
    }

    /**
     * @param bool $reminify
     * @param IRessio_CssMinify $minifyCss
     * @param string $item_content
     * @return Ressio_NodeWrapper
     */
    private function saveStyleNode($reminify, $minifyCss, $item_content)
    {
        if ($reminify) {
            try {
                $item_content = $minifyCss->minify($item_content);
            } catch (ERessio_InvalidCss $e) {
                $this->di->logger->warning('Catched error in ' . __METHOD__ . ': ' . $e->getMessage());
            }
        }
        $this->di->dispatcher->triggerEvent('CssCombineAfter', array(&$item_content));

        if (strlen($item_content) <= $this->config->css->inlinelimit) {
            return $this->createStyleNode('inline', null, $item_content);
        }

        $hash = substr(sha1($item_content), 0, $this->config->filehashsize);

        $cacheFile = "{$this->config->webrootpath}{$this->config->staticdir}/{$hash}.css";

        $fs = $this->di->filesystem;
        $fs->putContents($cacheFile, $item_content);
        $params = array('src_path' => $cacheFile);
        $this->di->worker->runTask('compressMulti', $params);

        if ($this->config->fileloader === 'php') {
            $cacheFile = "{$this->config->fileloaderphppath}?{$hash}.css";
        }
        // DO NOT minify src URL (for cache to be page-independent)
        return $this->createStyleNode('ref', $this->di->urlRewriter->filepathToUrl($cacheFile), null);
    }
}
