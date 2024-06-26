<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_JsCombiner implements IRessio_JsCombiner, IRessio_DIAware
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
     * @param array $scriptList
     * @return Ressio_NodeWrapper[]
     */
    public function combineToNodes($scriptList)
    {
        /** @var string[] $deps */
        $deps = array(
            'js',
            get_class($this->di->jsMinify),
            $this->config->js->inlinelimit
        );
        foreach ($scriptList as &$item) {
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
        $cache_id = $cache->id($deps, 'js');

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
            $minifyJs = $asyncMode ? new Ressio_JsMinify_None() : $this->di->jsMinify;
            $crossFileOptimization = !$asyncMode && $this->config->js->crossfileoptimization;

            $result = $this->combine($scriptList, $minifyJs, $crossFileOptimization);
            if ($result) {
                $cache->storeAndUnlock($cache_id, json_encode($result));
            }

            if ($asyncMode && !($this->di->jsMinify instanceof Ressio_JsMinify_None)) {
                // add off-request optimization
                $params = compact('scriptList');
                // inlined scripts might be extra-optimized
                $this->di->worker->runTask('jsCombine', $params);
            }
        }
        $wrapper = new stdClass();
        $wrapper->nodes = $result;
        $this->di->dispatcher->triggerEvent('JsCombinerNodeList', array($wrapper));

        return $wrapper->nodes;
    }

    /**
     * @param Ressio_NodeWrapper[] $nodes
     * @return string
     */
    public function nodesToHtml($nodes)
    {
        if ($this->config->js->nonce !== null) {
            foreach ($nodes as $node) {
                if (!isset($node->attributes['src'])) {
                    $node->attributes['nonce'] = $this->config->js->nonce;
                }
            }
        }

        $s = implode($nodes);
        $wrapper = new stdClass();
        $wrapper->content = $s;

        // Note: DOM parser doesn't call nodesToHtml, and so we cannot rely on JsCombinerHtml event
        //$this->di->dispatcher->triggerEvent('JsCombinerHtml', array($wrapper));

        return $wrapper->content;
    }

    /**
     * @param array $scriptList
     * @param IRessio_JsMinify $minifyJs
     * @param bool $crossFileOptimization
     * @return Ressio_NodeWrapper[]
     */
    private function combine($scriptList, $minifyJs, $crossFileOptimization = false)
    {
        $dispatcher = $this->di->dispatcher;
        $fs = $this->di->filesystem;
        $urlRewriter = $this->di->urlRewriter;

        $dispatcher->triggerEvent('JsCombineBefore', array(&$scriptList));
        $rules = $this->config->js->rules_minify_exclude;
        $wraptrycatch = $this->config->js->wraptrycatch;

        //$hashPrefix = get_class($this->di->jsMinify);

        $result = array();

        $item_content = array();
        $async = true;
        $defer = true;
        $src = '';

        foreach ($scriptList as $item) {
            $content = '';
            try {
                if ($item['type'] === 'inline') {
                    $content = $item['script'];
                    if (isset($rules->content) && preg_match($rules->content, $content)) {
                        $minified = $content;
                    } else {
                        $dispatcher->triggerEvent('JsInlineMinifyBefore', array(&$content));
                        $content = $this->removeUseScript($content);
                        $minified = $minifyJs->minifyInline($content);
                        $dispatcher->triggerEvent('JsInlineMinifyAfter', array(&$minified));
                    }
                } else {
                    $src = $item['src'];
                    $path = isset($item['filename']) ? $item['filename'] : $urlRewriter->urlToFilepath($src);
                    if ($path === null || pathinfo($path, PATHINFO_EXTENSION) !== 'js') {
                        // external or not-a-js file
                        throw new ERessio_InvalidJs('File ' . $path . ' is skipped.', 1);
                    }
                    // local js file
                    $content = $fs->getContents($path);
                    if ($content === false) {
                        throw new ERessio_InvalidJs('File ' . $path . ' not found.');
                    }
                    $content = Ressio_Helper::removeBOM($content);
                    //if (strncmp($content, "\x1f\x8b", 2) === 0) {
                    //    $content = gzinflate(substr($content, 10, -8));
                    //}
                    $isMinified = substr_compare($path, '.min.js', -7) === 0;
                    if ($isMinified || (isset($rules->attrs->src) && preg_match($rules->attrs->src, $src))) {
                        $content = $this->removeUseScript($content);
                        // remove source mapping
                        $content = preg_replace('|//#\s+sourceMappingURL=\S+;$|', '', $content);
                        $minified = $content;
                    } else {
                        $dispatcher->triggerEvent('JsFileMinifyBefore', array($item['src'], &$content));
                        $content = $this->removeUseScript($content);
                        $minified = $minifyJs->minify($content);
                        $dispatcher->triggerEvent('JsFileMinifyAfter', array($item['src'], &$minified));
                    }
                }
            } catch (ERessio_InvalidJs $e) {
                $level = $e->getCode() === 1 ? 'notice' : 'warning';
                $message = 'Catched error in ' . __METHOD__ . ': ' . $e->getMessage();
                if ($item['type'] === 'ref') {
                    $message .= ' [in file: ' . $item['src'] . ']';
                }
                $this->di->logger->log($level, $message);

                if (count($item_content) > 0) {
                    $joined = $this->implode_scripts($item_content, $wraptrycatch);
                    $result[] = $this->saveScriptNode($crossFileOptimization && count($item_content) > 1, $minifyJs, $joined, $async, $defer);
                }

                $result[] = $this->createScriptNode($item['type'], $src, $content, $item['async'], $item['defer']);

                $item_content = array();
                $async = true;
                $defer = true;

                continue;
            }
            $minified = rtrim($minified, "; \t\n\r\0\x0B") . ';';

            $comment_start = strrpos($minified, '//');
            if ($comment_start !== false) {
                $comment_end = strrpos($minified, "\n", $comment_start + 2);
                if ($comment_end === false) {
                    $minified .= "\n";
                }
            }
            $comment_start = strrpos($minified, '/*');
            if ($comment_start !== false) {
                $comment_end = strrpos($minified, '*/', $comment_start + 2);
                if ($comment_end === false) {
                    $minified .= "//*/\n";
                }
            }

            if ($minified !== '') {
                $item_content[] = $minified;
            }

            $async = $async && $item['async'];
            $defer = $defer && $item['defer'];
        }

        if (count($item_content) > 0) {
            $joined = $this->implode_scripts($item_content, $wraptrycatch);
            $result[] = $this->saveScriptNode($crossFileOptimization && count($item_content) > 1, $minifyJs, $joined, $async, $defer);
        }

        return $result;
    }

    /**
     * @param string $type
     * @param ?string $src
     * @param ?string $content
     * @param bool $async
     * @param bool $defer
     * @return Ressio_NodeWrapper
     */
    private function createScriptNode($type, $src, $content, $async, $defer)
    {
        $node = new Ressio_NodeWrapper('script');

        if ($type === 'inline' && ($async || $defer)) {
            $type = 'ref';
            $src = 'data:text/javascript,' . rawurlencode($content);
            $content = '';
        }

        if ($type === 'inline') {
            $node->content = $content;
        } else {
            $node->content = '';
            $node->attributes['src'] = $src;
        }

        if ($async) {
            $node->attributes['async'] = false;
        }
        if ($defer) {
            $node->attributes['defer'] = false;
        }

        return $node;
    }

    /**
     * @param bool $reminify
     * @param IRessio_JsMinify $minifyJs
     * @param string $item_content
     * @param bool $async
     * @param bool $defer
     * @return Ressio_NodeWrapper
     */
    private function saveScriptNode($reminify, $minifyJs, $item_content, $async, $defer)
    {
        if ($reminify) {
            try {
                $item_content = $minifyJs->minify($item_content);
            } catch (ERessio_InvalidJs $e) {
                $this->di->logger->warning('Catched error in ' . __METHOD__ . ': ' . $e->getMessage());
            }
        }
        $this->di->dispatcher->triggerEvent('JsCombineAfter', array(&$item_content));

        if (strlen($item_content) <= $this->config->js->inlinelimit) {
            return $this->createScriptNode('inline', null, $item_content, $async, $defer);
        }

        $hash = substr(sha1($item_content), 0, $this->config->filehashsize);

        $cacheFile = "{$this->config->webrootpath}{$this->config->staticdir}/{$hash}.js";

        $fs = $this->di->filesystem;
        $fs->putContents($cacheFile, $item_content);
        $params = array('src_path' => $cacheFile);
        $this->di->worker->runTask('compressMulti', $params);

        if ($this->config->fileloader === 'php') {
            $cacheFile = "{$this->config->fileloaderphppath}?{$hash}.js";
        }
        // DO NOT minify src URL (for cache to be page-independent)
        return $this->createScriptNode('ref', $this->di->urlRewriter->filepathToUrl($cacheFile), null, $async, $defer);
    }

    /**
     * @param string $content
     * @return string
     */
    private function removeUseScript($content)
    {
        if (strpos($content, 'use strict') === false) {
            return $content;
        }
        $content = preg_replace('#^((?:\s++|/\*(?>[^*]+|\*(?!/))*\*/|//[^\n]*+\n)*)([\'"])use strict\2;#', '\1', $content);
        return $content;
    }


    /**
     * @param string[] $item_content
     * @param bool $wraptrycatch
     * @return string
     */
    private function implode_scripts($item_content, $wraptrycatch)
    {
        if ($wraptrycatch && count($item_content) > 1) {
            $content = '';
            foreach ($item_content as $minified) {
                $minified = rtrim($minified, ';');
                if ($minified !== '') {
                    $content .= 'try{' . $minified . '}catch(e){console.log(e)}';
                }
            }
            return $content;
        }
        return implode($item_content);
    }
}