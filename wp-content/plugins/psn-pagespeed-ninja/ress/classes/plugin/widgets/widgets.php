<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_Plugin_Widgets extends Ressio_Plugin
{
    /** @var bool */
    private $jsInjected = false;

    /** @var int[] */
    private $protocols = array('' => 0, 'http:' => 0, 'https:' => 0);

    /** @var bool[] */
    private $widgetsSrc;

    /** @var mixed[][] */
    private $widgetsEmbed = array();
    /** @var string */
    private $src_regex;

    /**
     * @param Ressio_DI $di
     * @param ?stdClass $params
     */
    public function __construct($di, $params = null)
    {
        parent::__construct($di);
        $this->loadConfig(__DIR__ . '/config.json', $params);

        if ($this->params->delay_widgets) {
            $this->generateEmbeds();
            foreach ($this->widgetsEmbed as $i => $regex_replace) {
                $this->widgetsEmbed[$i][0] = '#' . $regex_replace[0] . '#';
            }
        }

        $this->widgetsSrc = array_fill_keys($this->params->delay_scripts, true);
    }

    /**
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    private function injectJsLoader($optimizer, $node)
    {
        $attributes = $this->config->js->nonce ? array('nonce' => $this->config->js->nonce) : array();
        $optimizer->prependHead(array('script', $attributes, file_get_contents(__DIR__ . '/js/loader.min.js')));
        $this->jsInjected = true;
    }

    /**
     * @param Ressio_Event $event
     * @param IRessio_HtmlOptimizer $optimizer
     * @param IRessio_HtmlNode $node
     * @return void
     */
    public function onHtmlIterateTagSCRIPTBefore($event, $optimizer, $node)
    {
        if ($node->hasAttribute('type') && $optimizer->isJavaScriptMime($node->getAttribute('type'))) {
            return;
        }
        if ($this->config->js->rules_merge_exclude && $optimizer->matchExcludeRule($node, $this->config->js->rules_merge_exclude)) {
            return;
        }
        // external resource
        if ($node->hasAttribute('src')) {
            $src = trim($node->getAttribute('src'));

            if (($pos = strpos($src, '//')) === false) {
                return;
            }

            $protocol = substr($src, 0, $pos);
            if (!isset($this->protocols[strtolower($protocol)])) {
                return;
            }
            $src = substr($src, $pos);

            $anchor = false;
            $pos = strpos($src, '#');
            if ($pos !== false) {
                $anchor = substr($src, $pos + 1);
                $src = substr($src, 0, $pos);
            }

            $query = false;
            $pos = strpos($src, '?');
            if ($pos !== false) {
                $query = substr($src, $pos + 1);
                $src = substr($src, 0, $pos);
            }

            $lang = false;
            if (preg_match('#/\w\w[_-]\w\w/#', $src, $matches)) {
                $lang = $matches[0];
                $src2 = str_replace($lang, '/%LANG%/', $src);
                if (isset($this->widgetsSrc[$src2])) {
                    $src = $src2;
                }
            }

            $newSrc = false;
            if (isset($this->widgetsSrc[$src])) {
                $newSrc = $this->widgetsSrc[$src];
                if ($newSrc === true) {
                    $newSrc = $src;
                }
            } elseif ($this->params->delay_async_js && $node->hasAttribute('async')) {
                // @note Don't check "defer" attribute, because deferred scripts should be run before DOMContentLoaded

                $newSrc = $src;
            }

            if ($newSrc !== false) {
                $newSrc = $protocol . $newSrc;

                if ($lang !== false) {
                    $newSrc = str_replace('/%LANG%/', $lang, $newSrc);
                }

                if ($query !== false) {
                    $newSrc .= '?' . $query;
                }

                if ($anchor !== false) {
                    $newSrc .= '#' . $anchor;
                }

                if (!$this->jsInjected) {
                    $this->injectJsLoader($optimizer, $node);
                }

                $content = 'ress_js("' . $newSrc . '")';
                $this->di->dispatcher->triggerEvent('PreloadResource', array($newSrc, 'script'));
                $node->removeAttribute('src');
                $optimizer->nodeSetInnerText($node, $content);
                //$node->attributes = array();
            }
        } elseif ($this->params->delay_widgets) { // embedded javascript
            $content = $optimizer->nodeGetInnerText($node);

            foreach ($this->widgetsEmbed as $regex) {
                if (preg_match_all($regex[0], $content, $matches, PREG_SET_ORDER)) {
                    $replace = $regex[1];
                    foreach ($matches as $match) {
                        $vars = array();
                        foreach ($match as $key => $value) {
                            if (strncmp($key, 'var', 3) === 0) {
                                $vars[] = $value;
                            }
                        }
                        $src = false;
                        foreach ($match as $key => $value) {
                            if ($key !== 0 && $value !== '' && !in_array($value, $vars, true)) {
                                $src = $value;
                            }
                        }
                        if ($src !== false) {
                            // Skip GA
                            if (strpos($src, 'google-analytics.com') !== false) {
                                continue;
                            }
                            if (!$this->jsInjected) {
                                $this->injectJsLoader($optimizer, $node);
                            }
                            $newContent = 'ress_js(' . $src . ');';
                            $this->di->dispatcher->triggerEvent('PreloadResource', array($src, 'script'));
                            if ($replace !== true) {
                                $newContent = str_replace('%RESSJS%', $newContent, $replace);
                            }
                            $content = str_replace($match[0], $newContent, $content);
                        }
                    }
                }
            }

            $optimizer->nodeSetInnerText($node, $content);
        }
    }

    /** @return void */
    private function generateEmbeds()
    {
        $quoted_string = '(?:"[^"]+"|\'[^\']+\')';
        $this->src_regex = '('
            . $this->re_or(
                '(' . $quoted_string . '\s*\+\s*\w+\s*\+\s*)?' . $quoted_string,
                '\(' . $this->re_or(
                    'document\.location\.protocol\s*==\s*(?:"https:"|\'https:\')',
                    '(?:"https:"|\'https:\')\s*==\s*document\.location\.protocol'
                ) . '\s*\?\s*' . $quoted_string . '\s*:\s*' . $quoted_string . '\s*\)\s*\+\s*' . $quoted_string
            ) . ')';

        $this->widgetsEmbed = array();

        // Facebook-like widget
        $this->widgetsEmbed[] = array(
            $this->re_quote('( function ( @1 , @2 , @3 ) { ') .
            $this->re_quote('var  @4 , @5 = \1.getElementsByTagName( \2 ) [ 0 ] ; ') .
            $this->re_quote(' if ( \1.getElementById( \3 ) ) return ; ') .
            $this->re_quote('\4 = \1.createElement( \2 ) ; ') .
            $this->re_quote('\4.id = \3 ; ') .
            $this->re_quote('\4.src = %SRC% ; ') .
            $this->re_quote('\5.parentNode.insertBefore( \4 , \5 ) ') . ';?' .
            $this->re_quote(' } ') .
            $this->re_or(
                $this->re_quote('( document , "script" , "%ID%" )'),
                $this->re_quote(') ( document , "script" , "%ID%"')
            ) .
            $this->re_quote(' ) ;')
        , true);

        // General widget
        $this->widgetsEmbed[] = array(
            $this->re_quote('( function ( ) { ') .
            $this->re_quote('var  @1 = document.createElement( "script" ) ; ') .
            $this->re_shuffle(
                $this->re_quote('\1.type = "text/javascript" ; '),
                $this->re_quote('\1.async = ') . '(?:true|1)' . $this->re_quote(' ; '),
                $this->re_quote('\1.src = %SRC% ; ')
            ) .
            $this->re_or(
                $this->re_quote('var  @2 = document.getElementsByTagName( "script" ) [ 0 ] ; \2.parentNode.insertBefore( \1 , \2 ) '),
                $this->re_quote('( document.getElementsByTagName( "head" ) [ 0 ] || document.getElementsByTagName( "body" ) [ 0 ] ).appendChild( \1 ) ')
            ) . ';?\s*' .
            $this->re_quote('} ) ( ) ;')
        , true);

        // GoogleAnalytics
        /*        $this->widgetsEmbed[] = array(
                    $this->re_quote('( function ( @1 , @2 , @3 , @4 , @5 , @6 , @7 ) { ') .
                    $this->re_quote('\1') . $this->re_or($this->re_quote('[ "GoogleAnalyticsObject" ]'), $this->re_quote('.GoogleAnalyticsObject')) . $this->re_quote(' = \5 ') . '[;,]\s*' .
                    $this->re_quote('\1[ \5 ] = \1[ \5 ] || function ( ) { ( \1[ \5 ].q = \1[ \5 ].q || [] ).push( arguments ) } ') . '[;,]\s*' .
                    $this->re_quote('\1[ \5 ].l = 1 * new  Date') . '(?:\(\s*\))?' . $this->re_quote(' ') . '[;,]\s*' .
                    $this->re_quote('\6 = \2.createElement( \3 ) ') . '[;,]\s*' .
                    $this->re_quote('\7 = \2.getElementsByTagName( \3 ) [ 0 ] ') . '[;,]\s*' .
                    $this->re_quote('\6.async = ') . '(?:true|1)' . $this->re_quote(' ') . '[;,]\s*' .
                    $this->re_quote('\6.src = \4 ') . '[;,]\s*' .
                    $this->re_quote('\7.parentNode.insertBefore( \6 , \7 ) ') .
                    $this->re_quote('} ) ( window , document , "script" , %SRC% , "ga" ) ') . ';?'
                , '(function(w,g){w.GoogleAnalyticsObject=g;w[g]=w[g]||function(){(w[g].q=w[g].q||[]).push(arguments)},w[g].l=1*new Date()})(window,\'ga\');%RESSJS%');*/

        // Facebook
        $this->widgetsEmbed[] = array(
            $this->re_quote('{ var  @1 = document.createElement( "script" ); ') .
            $this->re_quote('\1.async = true; ') .
            $this->re_quote('\1.src = %SRC% ; ') .
            $this->re_quote('var  @2 = document.getElementsByTagName( "script" ) [ 0 ] ; ') .
            $this->re_quote('\2.parentNode.insertBefore( \1 , \2 ) ; ') .
            $this->re_quote('_fbq.loaded = true ; ') .
            $this->re_quote('}')
        , '{%RESSJS%_fbq.loaded=!0;}');

        // Pingdom
        $this->widgetsEmbed[] = array(
            $this->re_quote('( function ( ) { ') .
            $this->re_quote('var  @1 = document.getElementsByTagName( "script" ) [ 0 ] , ') .
            $this->re_quote('@2 = document.createElement( "script" ) ; ') .
            $this->re_quote('\2.async = "async" ; ') .
            $this->re_quote('\2.src = %SRC% ; ') .
            $this->re_quote('\1.parentNode.insertBefore( \2 , \1 ) ; ') .
            $this->re_quote('} ) ( ) ;')
        , true);
    }

    /**
     * @param string $s
     * @return string
     */
    private function re_quote($s)
    {
        $regex = preg_quote($s, '#');

        $regex = str_replace(
            array('\\\\', '  ', ' ', '%ID%'),
            array('\\', '\s+', '\s*', '[\w-]+'),
            $regex);

        $regex = preg_replace(
            array('#@(\d+)#', '#\\\\(\d+)#', '#"([^"]+)"#'),
            array('(?P<var\1>\w+)', '(?P=var\1)', '(?:"\1"|\'\1\')'),
            $regex);

        return str_replace('%SRC%', $this->src_regex, $regex);
    }

    /**
     * @return string
     */
    private function re_or()
    {
        $args = func_get_args();
        return '(?:' . implode('|', $args) . ')';
    }

    /**
     * @return string
     */
    private function re_shuffle()
    {
        switch (func_num_args()) {
            case 1:
                return func_get_arg(0);
            case 2:
                $a = func_get_arg(0);
                $b = func_get_arg(1);
                return '(?:' . $a . $b . '|' . $b . $a . ')';
            case 3:
                $a = func_get_arg(0);
                $b = func_get_arg(1);
                $c = func_get_arg(2);
                return '(?:' . $a . $b . $c . '|' . $a . $c . $b . '|' . $b . $a . $c . '|' . $b . $c . $a . '|' . $c . $a . $b . '|' . $c . $b . $a . ')';
        }
        return '';
    }
}