<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_CssMinify_Simple extends Ressio_CssMinify_Base
{
    /** @var array */
    protected $saved = array();
    /** @var int */
    protected $saved_idx = 0;

    /**
     * Minify CSS
     * @param string $str
     * @return string
     * @throws ERessio_InvalidCss
     */
    public function minify($str)
    {
        return $this->optimize($str);
    }

    /**
     * Minify CSS in style=""
     * @param string $str
     * @param ?string $srcBase
     * @return string
     * @throws ERessio_InvalidCss
     */
    public function minifyInline($str, $srcBase = null)
    {
        $wrap = '*{' . $str . '}';
        $wrap = $this->optimize($wrap);
        if (preg_match('/^\*\{(.*)\}$/', $wrap, $match)) {
            return $match[1];
        }
        return $str;
    }

    /**
     * @param string $buffer
     * @return string
     * @throws ERessio_InvalidCss
     */
    protected function optimize($buffer)
    {
        // 0 - don't save
        // 1 - save important (/*!...*/)
        // 2 - save all
        $save_comments = 1;

        /* 3.3. Preprocessing the input stream */
        $buffer = str_replace(
            array("\r\n", "\r", "\x0C", "\x00"),
            array("\n", "\n", "\n", "\xEF\xBF\xBD" /* UTF 0FFFD */),
            $buffer);

        $prev = 0;
        $result = '';

        $nesting_level = 0;
        $atrule_mode = false;
        $selector_mode = true;

        while (preg_match('/[\\\\{}\'"@;]|\/\*|(?<=[^\w-])(?:url|calc)\(/i', $buffer, $matches, PREG_OFFSET_CAPTURE, $prev)) {
            list($token, $pos) = $matches[0];
            $result .= $this->optimize_chunk(substr($buffer, $prev, $pos - $prev), $selector_mode, $atrule_mode);
            $prev = $pos;
            switch (strtolower($token)) {
                case '\\':
                    // escape
                    $result .= '\\' . $buffer[++$prev];
                    $prev++;
                    break;

                case '{':
                    if ($atrule_mode) {
                        $atrule_mode = false;
                        $selector_mode = true; // depends on atrule, some switch to selector mode, others to rules mode
                    } else {
                        $selector_mode = false;
                    }
                    // start block
                    $nesting_level++;
                    $result .= '{';
                    $prev++;
                    break;
                case '}':
                    $selector_mode = true;
                    // end block
                    $nesting_level--;
                    if ($nesting_level < 0) {
                        throw new ERessio_InvalidCss('Unexpected "}" character');
                    }
                    $result .= '}';
                    $prev++;
                    break;

                case '@':

                    $atrule_mode = true;
                    $result .= '@';
                    $prev++;
                    break;
                case ';':
                    $atrule_mode = false;
                    $result .= ';';
                    $prev++;
                    break;

                case '/*':
                    // comment
                    $start_comment = $pos + 2;
                    $pos = strpos($buffer, '*/', $start_comment);
                    if ($pos === false) {
                        $pos = strlen($buffer);
                    }
                    $prev = $pos + 2;
                    if ($save_comments && ($save_comments === 2 || ($pos > $start_comment && $buffer[$start_comment] === '!'))) {
                        $key = "/*C{$this->saved_idx}*/";
                        $this->saved_idx++;
                        $result .= $key;
                        $this->saved[$key] = '/*' . substr($buffer, $start_comment, $pos - $start_comment) . '*/';
                    }
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

                    $key = "/*{$token}{$this->saved_idx}*/";
                    $this->saved[$key] = $str;
                    $this->saved_idx++;
                    $result .= $key;
                    break;

                case 'url(':
                    // url
                    if (!preg_match('/\s*+(?:\'(?:\\\\.|[^\'\\\\]++)*+\'|"(?:\\\\.|[^"\\\\]++)*+"|[^\'")]*+)\s*\)/A', $buffer, $m, 0, $prev + 4)) {
                        throw new ERessio_InvalidCss('Unclosed url()');
                    }
                    $prev += 4 + strlen($m[0]);
                    $relurl = trim(substr($m[0], 0, -1));

                    $key = "/*u{$this->saved_idx}*/";
                    $this->saved_idx++;
                    $result .= $key;
                    $this->saved[$key] = "url($relurl)";
                    break;

                case 'calc(':
                    // calc expression
                    if (!preg_match('/\((?:[^()]++|(?=\()(?R))*+\)/A', $buffer, $matches, 0, $prev + 4)) {
                        throw new ERessio_InvalidCss('Unclosed calc() expression.');
                    }
                    $next = $prev + 4 + strlen($matches[0]);
                    $key = "/*c{$this->saved_idx}*/";
                    $this->saved_idx++;
                    $result .= $key;
                    $this->saved[$key] = substr($buffer, $prev, $next - $prev);
                    $prev = $next;
                    break;

                default:
                    throw new ERessio_InvalidCss('Something went wrong...');
            }
        }

        if ($nesting_level) {
            throw new ERessio_InvalidCss('Unclosed group.');
        }
        $result .= $this->optimize_chunk(substr($buffer, $prev), $selector_mode, $atrule_mode);
        $buffer = $result;
        unset($result);

        // merge whitespaces (if exists after merging of chunks)
        $buffer = preg_replace('/\s{2,}/', ' ', $buffer);
        // remove whitespaces around "{", "}", ";"
        $buffer = preg_replace('/\s?([{};])\s?/', '\1', $buffer);
        // remove semicolons before "}"
        $buffer = preg_replace('/;+(?=\})/', '', $buffer);
        // remove rules with empty declarations
        $buffer = preg_replace('/[^{};]+\\{\\}/', '', $buffer);

        // restore from saved
        $buffer = trim(strtr($buffer, $this->saved));
        return $buffer;
    }

    /**
     * @param string $css
     * @param bool $selector_mode
     * @param bool $atrule_mode
     * @return string
     */
    protected function optimize_chunk($css, $selector_mode, $atrule_mode)
    {
        if ($atrule_mode) {
            // currently don't optimize
            return $css;
        }

        $css = strtr($css, "\n\r\t\x0B", '    ');
        // merge whitespaces
        $css = preg_replace('/(?<=\s)\s+/', '', $css);

        if ($selector_mode) {
            // optimize selectors
            $css = preg_replace('/\s?([>+,~])\s?/', '\1', $css);
        } else {
            // optimize rules
            $css = preg_replace('/\s?([:;,])\s?/', '\1', $css);
        }

        return $css;
    }

}
