<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_CssRelocator implements IRessio_CssRelocator, IRessio_DIAware
{
    /** @var Ressio_DI */
    protected $di;
    /** @var Ressio_Config */
    public $config;

    /** @var string */
    public $srcBase;
    /** @var ?string */
    public $targetBase;

    /** @var int */
    protected $level;

    /** @var array<string,string> */
    protected $saved = array();
    /** @var int */
    protected $saved_idx = 10000; // to have keys of equal length (speed up strtr, see php_strtr_array sources)

    /**
     * @param Ressio_DI $di
     */
    public function __construct($di)
    {
        $this->di = $di;
        $this->config = $di->config;
    }

    /**
     * @param string $buffer
     * @param ?string $srcBase
     * @param ?string $targetBase
     * @param string $media
     * @return string
     * @throws ERessio_InvalidCss
     */
    public function run($buffer, $srcBase = null, $targetBase = null, $media = '')
    {
        $this->targetBase = $targetBase;
        $this->level = 2;
        $this->saved = array();
        return $this->optimize($buffer, $srcBase, $media);
    }

    /**
     * @param string $buffer
     * @param string $srcBase
     * @param string $media
     * @return string
     * @throws ERessio_InvalidCss
     */
    protected function optimize($buffer, $srcBase = null, $media = '')
    {
        $this->srcBase = $srcBase;

        /* 3.3. Preprocessing the input stream */
        $buffer = strtr(
            str_replace(
                array("\r\n", "\x00"), /* \0 is not a valid UTF codepoint */
                array("\n", "\xEF\xBF\xBD" /* UTF 0FFFD */),
                $buffer
            ), "\r\x0C", "\n\n");

        $prev = 0;
        $result = '';

        $paren_nesting_level = 0;
        $imageset_mode = false;

        while (preg_match('/[\\\\()\'"]|\/\*|(?<=[^\w-])(?:url|image|image-set|-webkit-image-set)\(/i', $buffer, $matches, PREG_OFFSET_CAPTURE, $prev)) {
            list($token, $pos) = $matches[0];
            $result .= substr($buffer, $prev, $pos - $prev);
            $prev = $pos;
            switch (strtolower($token)) {
                case '\\':
                    // escape
                    $result .= '\\' . $buffer[++$prev];
                    $prev++;
                    break;

                case '(':
                    if ($imageset_mode) {
                        $paren_nesting_level++;
                    }
                    $result .= '(';
                    $prev++;
                    break;
                case ')':
                    if ($imageset_mode) {
                        $paren_nesting_level--;
                        if ($paren_nesting_level === 0) {
                            $imageset_mode = false;
                        }
                    }
                    $result .= ')';
                    $prev++;
                    break;

                case '/*':
                    // comment
                    $start_comment = $pos;
                    $pos = strpos($buffer, '*/', $prev + 2);
                    if ($pos === false) {
                        $pos = strlen($buffer);
                    }
                    $prev = $pos + 2;

                    $key = "/*C{$this->saved_idx}*/";
                    $this->saved_idx++;
                    $this->saved[$key] = substr($buffer, $start_comment, $prev - $start_comment);

                    $result .= $key;
                    break;

                case '"':
                case "'":
                    // quoted string
                    if (!preg_match("/(?:\\\\.|[^\\\\$token]++)*+/A", $buffer, $m, 0, $prev + 1)) {
                        throw new ERessio_InvalidCss("Unclosed $token-string");
                    }
                    $next = $prev + 1 + strlen($m[0]) + 1;

                    $str = substr($buffer, $prev, $next - $prev);
                    $prev = $next;

                    if ($imageset_mode) {
                        $relurl = stripslashes(substr($str, 1, -1));
                        $relurl = $this->relocate_url($relurl);
                        $str = $token . addcslashes($relurl, $token) . $token;
                    }

                    $key = "/*{$token}{$this->saved_idx}*/";
                    $this->saved_idx++;
                    $this->saved[$key] = $str;

                    $result .= $key;
                    break;

                case 'url(':
                    // url
                    if (!preg_match('/\s*+(?:\'(?:\\\\.|[^\'\\\\]++)*+\'|"(?:\\\\.|[^"\\\\]++)*+"|[^\'")]*+)\s*\)/A', $buffer, $m, 0, $prev + 4)) {
                        throw new ERessio_InvalidCss('Unclosed url()');
                    }
                    $prev += 4 + strlen($m[0]);
                    $relurl = trim(substr($m[0], 0, -1));

                    if ($relurl !== '') {
                        if ($relurl[0] === "'" || $relurl[0] === '"') {
                            $relurl = substr($relurl, 1, -1);
                        }
                        $relurl = stripslashes($relurl);
                        $relurl = $this->relocate_url($relurl);
                    }

                    $key = "/*u{$this->saved_idx}*/";
                    $this->saved_idx++;
                    $this->saved[$key] = 'url(' . $this->escapeUrl($relurl) . ')';

                    $result .= $key;
                    break;

                case 'image(':
                case 'image-set(':
                case '-webkit-image-set(':
                    $imageset_mode = true;
                    $paren_nesting_level++;
                    $result .= $token;
                    $prev += strlen($token);
                    break;

                default:
                    throw new ERessio_InvalidCss('Something went wrong...');
            }
        }

        if ($imageset_mode) {
            throw new ERessio_InvalidCss('Unclosed image() or image-set() expression.');
        }

        $result .= substr($buffer, $prev);
        $buffer = $result;
        unset($result);

        $this->di->dispatcher->triggerEvent('CssRelocatorBefore', array(&$buffer, &$this->saved));

        // remove @charset rules (utf8 is the only supported)
        $buffer = preg_replace('/@charset\s++[^;\\n]++;/i', '', $buffer);

        // recursively resolve and inject @import (up to specified level)
        $this->level--;
        $importProcessed = ($this->level >= 0);
        $buffer = preg_replace_callback('/@import\s++([^;\\n]++);/i', array($this, 'at_import_inject'), $buffer);
        $this->level++;

        // wrapping media
        $mediaProcessed = ($media !== '' && $media !== 'all');
        if ($mediaProcessed) {
            $buffer = "@media $media{{$buffer}}";
        }

        // flatting of nested @media
        // Note: preg_match_all is faster than substr_count(strtolower) for large buffer
        //       and in my tests it is faster than stripos(stripos)
        if (($importProcessed || $mediaProcessed) && preg_match_all('/@media/i', $buffer) > 1) {
            $buffer = implode($this->flat_media($buffer));
        }

        $this->di->dispatcher->triggerEvent('CssRelocatorAfterProcessed', array(&$buffer, &$this->saved));

        // restore from saved
        $buffer = strtr($buffer, $this->saved);

        $this->di->dispatcher->triggerEvent('CssRelocatorAfter', array(&$buffer));

        return $buffer;
    }

    /**
     * @param string[] $matches
     * @return string
     */
    private function at_import_inject($matches)
    {
        $import = trim($matches[1]);
        // remove comments (@todo keep them!!!)
        $import = preg_replace('/\/\*C\d+\*\//', '', $import);

        if (preg_match('/^(?:(\/\*[u\'"]\d+\*\/)|(\S+)\s)\s*(.*?)$/', $import, $match)) {
            $media = $match[3];

            if ($match[1] !== '') {
                $key = $match[1];
                $relurl = $this->saved[$key];
                if ($key[2] === 'u') {
                    $relurl = trim(substr($relurl, 4, -1));
                }
                if ($relurl[0] === '"' || $relurl[0] === "'") {
                    $relurl = substr($relurl, 1, -1);
                }
            } else {
                $relurl = trim($match[2]);
            }
            $relurl = stripslashes($relurl);

            $urlRewriter = $this->di->urlRewriter;
            if (strpos($relurl, '://') === false) {
                $relurl = $urlRewriter->getRebasedUrl($relurl, $this->srcBase, $this->targetBase);
            }

            // keep rules like '@import url("fallback-layout.css") supports(not (display: flex));' AS IS!!!
            if (
                $this->level >= 0 &&
                strpos($relurl, '://') === false &&
                strncasecmp($media, 'support', 7) !== 0 // skip all "support" expressions
            ) {
                if ($relurl[0] === '/') {
                    $url = $relurl;
                } else {
                    $url = rtrim($this->targetBase, '/') . '/' . $relurl;
                }
                $path = $urlRewriter->urlToFilepath($url);
                $fs = $this->di->filesystem;
                if ($fs->isFile($path)) {
                    $content = $fs->getContents($path);
                    if ($content !== false) {
                        $srcBase = dirname($url);
                        if ($srcBase === '.') {
                            $srcBase = '';
                        }
                        try {
                            $saveSrcBase = $this->srcBase;
                            $content = $this->optimize($content, $srcBase, $media);
                            $this->srcBase = $saveSrcBase;
                            return $content;
                        } catch (ERessio_InvalidCss $e) {
                            $this->srcBase = $saveSrcBase;
                            $this->di->logger->warning('Catched error in ' . __METHOD__ . ': ' . $e->getMessage());
                        }
                    }
                }
            }

            $key = "/*u{$this->saved_idx}*/";
            $this->saved_idx++;
            $this->saved[$key] = 'url(' . $this->escapeUrl($relurl) . ')';
            return '@import ' . $key . ($media === '' ? '' : " $media") . ';';
        }

        // return original rule on fail
        return $matches[0];
    }

    /**
     * @param string $buffer
     * @return array
     * @throws ERessio_InvalidCss
     */
    protected function flat_media($buffer)
    {
        $result = array();

        $prev = 0;
        while (preg_match('/@media(?=[ (])([^{]+)(?=\{)/i', $buffer, $matches, PREG_OFFSET_CAPTURE, $prev)) {
            $pos = $matches[0][1];
            $media = trim($matches[1][0]);

            if ($pos !== $prev) {
                $result[] = substr($buffer, $prev, $pos - $prev);
            }

            $pos += strlen($matches[0][0]);
            if (!preg_match('/\{(?:(?:[^\\\\{}]++|\\\\.)++|(?=\{)(?R))*+\}/A', $buffer, $matches, 0, $pos)) {
                throw new ERessio_InvalidCss('Unclosed group in flat_media');
            }
            $prev = $pos + strlen($matches[0]);

            foreach ($this->flat_media(substr($matches[0], 1, -1)) as $block) {
                $block = trim($block);
                if ($block !== '') {
                    if (preg_match('/^(?:\/\*C\d+\*\/\s*+)++$/', $block)) {
                        $result[] = $block;
                    } elseif (preg_match('/^@media(?=[ (])([^{]+)(?=\{)/i', $block, $matches)) {
                        $media_inner = trim($matches[1]);
                        if (strpos($media_inner, ',') === false) {
                            $media_merged = "$media and $media_inner";
                        } else {
                            $media_merged = array();
                            foreach (explode(',', $media_inner) as $media_inner_single) {
                                $media_merged[] = "$media and " . trim($media_inner_single);
                            }
                            $media_merged = implode(',', $media_merged);
                        }
                        $result[] = "@media $media_merged" . substr($block, strlen($matches[0]));
                    } else {
                        $result[] = "@media $media{{$block}}";
                    }
                }
            }
        }
        $result[] = substr($buffer, $prev);

        return $result;
    }

    /**
     * @param string $url
     * @return string
     */
    protected function escapeUrl($url)
    {
        static $toEscape = ' "\'()';

        if (!strpbrk($url, $toEscape)) {
            return $url;
        }

        /** @var int[] $c */
        $c = count_chars($url);
        $url1_extra = $c[32/* */] + $c[34/*"*/] + $c[39/*'*/] + $c[40/*(*/] + $c[41/*)*/];
        $url2_extra = 2 + $c[39/*'*/];
        $url3_extra = 2 + $c[34/*"*/];

        // some css optimizers don't support escaped space in url
        if ($url1_extra < $url2_extra && $c[32/* */] === 0) {
            if ($url1_extra < $url3_extra) {
                return addcslashes($url, $toEscape);
            }
            return '"' . addcslashes($url, '"') . '"';
        }
        if ($url2_extra < $url3_extra) {
            return "'" . addcslashes($url, "'") . "'";
        }
        return '"' . addcslashes($url, '"') . '"';
    }

    /**
     * @param string $relurl
     * @return string
     */
    protected function relocate_url($relurl)
    {
        if ($relurl === '' || $relurl[0] === '#' || strncasecmp($relurl, 'data:', 5) === 0) {
            return $relurl;
        }

        $urlRewriter = $this->di->urlRewriter;

        if (strpos($relurl, '://') === false) {
            $relurl = $urlRewriter->getRebasedUrl($relurl, $this->srcBase, $this->targetBase);
        }
        if ($this->config->img->minify || $this->config->html->urlminify) {
            $src_file = $urlRewriter->urlToFilepath($relurl);
            if ($src_file !== null) {
                $relurl_new = null;
                if ($this->config->img->minify && preg_match('/\.(png|gif|jpe?g|svg)$/', $src_file, $match)) {
                    if ($this->config->var->imagenextgenformat && $match[1] !== 'svg') {
                        $optimized_file = $this->di->imgOptimizer->convert($src_file, $this->config->var->imagenextgenformat);
                        if ($optimized_file !== $src_file && $optimized_file !== false) {
                            $relurl_new = $urlRewriter->filepathToUrl($optimized_file);
                        }
                    } else {
                        $optimized_file = $this->di->imgOptimizer->optimize($src_file);
                        if ($optimized_file !== false) {
                            $relurl_new = $urlRewriter->filepathToUrl($optimized_file);
                        }
                    }
                } elseif ($this->config->html->urlminify) {
                    $relurl_new = $urlRewriter->filepathToUrl($src_file);
                }
                if ($relurl_new !== null && $relurl_new !== $relurl) {
                    $hash = (strpos($relurl, '#') !== false) ? parse_url($relurl, PHP_URL_FRAGMENT) : false;
                    $relurl = $relurl_new;
                    if ($hash !== false) {
                        $relurl .= '#' . $hash;
                    }
                }
            }
        }

        return $relurl;
    }
}
