<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

return array(
    // /path/to/web/root (without trailing slashes)
    'webrootpath' => '',
    // /sub/dir/where/files/are/processed (with leading and not trailing slashes)
    'webrooturi' => '',
    // /uri/of/static/files (with leading either slash or ./ for relative to ressio directory)
    'staticdir' => './s',

    // (absolute path or leading ./ for relative to ressio directory)
    'cachedir' => './cache',
    'cachettl' => 24 * 60 * 60,
    'cachefast' => false,
    'cachedeps' => array(),

    'logginglevel' => 5, // warning

    'fileloader' => 'file',
    // (./ - relative to ressio directory)
    'fileloaderphppath' => './fetch.php',
    'filehashsize' => 6,

    'change_group' => null,
    'use_symlink' => true,

    'html' => array(
        'gzlevel' => 5,
        'forcehtml5' => false,
        'mergespace' => true,
        'removecomments' => true,
        'urlminify' => true,
        'sortattr' => true,
        'removedefattr' => true,
        'removeiecond' => true,
        'rules_safe_exclude' => null
    ),

    'css' => array(
        'mergeheadbody' => true,
        'crossfileoptimization' => false,
        'inlinelimit' => 4096,
        'merge' => true,
        'checklinkattributes' => true,
        'checkstyleattributes' => true,
        'mergeinline' => 'head',
        'minifyattribute' => false,
        'nonce' => null,
        'exec' => null,
        'rules_merge_bypass' => null,
        'rules_merge_stop' => null, //array('attrs' => array('onload' => '//', 'data-cfasync' => '/^false$/')),
        'rules_merge_exclude' => null, //array('attrs' => array('ress-nomerge' => '//', 'href' => '/#/')),
        'rules_merge_include' => null, //array('attrs' => array('ress-merge' => '//')),
        'rules_merge_startgroup' => null,
        'rules_merge_stopgroup' => null,
        'rules_minify_exclude' => null
    ),

    'js' => array(
        'mergeheadbody' => true,
        'automove' => true,
        'forceasync' => false,
        'forcedefer' => false,
        'crossfileoptimization' => false,
        'inlinelimit' => 4096,
        'merge' => true,
        'wraptrycatch' => false,
        'checkattributes' => true,
        'mergeinline' => 'head',
        'minifyattribute' => false,
        'skipinits' => false,
        'nonce' => null,
        'exec' => null,
        'rules_merge_bypass' => null,
        'rules_merge_stop' => null, //array('attrs' => array('onload' => '//', 'data-cfasync' => '/^false$/')),
        'rules_merge_exclude' => null, //array('attrs' => array('ress-nomerge' => '//', 'src' => '/#/')),
        'rules_merge_include' => null, //array('attrs' => array('ress-merge' => '//')),
        'rules_merge_startgroup' => null,
        'rules_merge_stopgroup' => null,
        'rules_move_exclude' => null,
        'rules_async_exclude' => null,
        'rules_async_include' => null,
        'rules_defer_exclude' => null,
        'rules_defer_include' => null,
        'rules_minify_exclude' => null
    ),

    'img' => array(
        'minify' => true,
        'minifyrescaled' => false,
        'jpegquality' => 85,
        'webpquality' => 85,
        'avifquality' => 70,
        'chroma420' => false,
        'webp' => true,
        'avif' => false,
        'execoptim' => array(
            'avif' => null,
            'bmp' => null,
            'gif' => null,
            'ico' => null,
            'jpg' => null,
            'png' => null,
            'svg' => null,
            'svgz' => null,
            'tiff' => null,
            'webp' => null
        ),
        'srcsetgeneration' => false,
        'srcsetwidths' => array(360, 720, 1080, 1440, 1920),
        'rules_minify_exclude' => null
    ),

    'urlloader' => array(
        'timeout' => 5
    ),

    'amdd' => array(
        'handler' => 'plaintext',
        'cacheSize' => 1000,
        'dbPath' => './vendor/amdd/devices',
        'dbUser' => '...',
        'dbPassword' => '...',
        'dbHost' => 'localhost',
        'dbDatabase' => '...',
        'dbTableName' => 'amdd',
        'dbDriver' => 'pgsql:host=localhost;port=5432;dbname=...',
        'dbDriverOptions' => array()
    ),

    'rddb' => array(
        'timeout' => 3,
        'proxy' => false,
        'proxy_url' => 'tcp://127.0.0.1:3128',
        'proxy_login' => false,
        'proxy_pass' => ''
    ),

    'plugins' => array(
/*
        Ressio_Plugin_Lazyload::class => array(
            'image' => true,
            'iframe' => true,
            'srcset' => true
        )
*/
    ),

    'di' => array(
        'cache' => Ressio_Cache_File::class,
        'cssCombiner' => Ressio_CssCombiner::class,
        'cssMinify' => Ressio_CssMinify_Simple::class,
        'cssRelocator' => Ressio_CssRelocator::class,
        'db' => Ressio_Database_MySQL::class,
        'deviceDetector' => Ressio_DeviceDetector_None::class,
        'dispatcher' => Ressio_Dispatcher::class,
        'exec' => Ressio_Exec_Exec::class,
        'filelock' => Ressio_FileLock_flock::class,
        'filesystem' => Ressio_Filesystem_Native::class,
        'htmlOptimizer' => Ressio_HtmlOptimizer_Pharse::class,
//      'htmlOptimizer' => Ressio_HtmlOptimizer_Stream::class,
//      'htmlOptimizer' => Ressio_HtmlOptimizer_Dom::class,
        'httpCompressOutput' => Ressio_HttpCompressOutput::class,
        'httpHeaders' => Ressio_HttpHeaders::class,
        'imgOptimizer' => Ressio_ImgOptimizer::class,
        'imgOptimizer.gif' => Ressio_ImgHandler_GD::class,
        'imgOptimizer.jpg' => Ressio_ImgHandler_GD::class,
        'imgOptimizer.png' => Ressio_ImgHandler_GD::class,
        'imgOptimizer.webp' => Ressio_ImgHandler_GD::class,
        'imgOptimizer.svg' => Ressio_ImgHandler_SvgGz::class,
        'jsCombiner' => Ressio_JsCombiner::class,
        'jsMinify' => Ressio_JsMinify_JsMin::class,
        'logger' => Ressio_Logger::class,
        'urlLoader' => Ressio_UrlLoader_Stream::class,
        'urlRewriter' => Ressio_UrlRewriter::class,
        'worker' => Ressio_Worker_MySQL::class,
    ),

    'db' => array(
        'host' => 'localhost',
        'database' => '',
        'user' => '',
        'password' => '',
        'persistent' => false,
    ),

    'worker' => array(
        'enabled' => false,
        'lockdir' => null,
        'maxworkers' => 1,
        'maxexecutiontime' => 60,
        'memorylimit' => '128M',
        'actors' => array(
            'cssCombine' => Ressio_Actor_CssCombine::class,
            'gzipDo' => Ressio_Actor_Gzip::class,
            'compressMulti' => Ressio_Actor_CompressMulti::class,
            'imgConvert' => Ressio_Actor_ImgConvert::class,
            'imgOptimize' => Ressio_Actor_ImgOptimize::class,
            'imgRescale' => Ressio_Actor_ImgRescale::class,
            'jsCombine' => Ressio_Actor_JsCombine::class,
            'urlDownload' => Ressio_Actor_UrlDownload::class,
        ),
        'db' => array(
            'tablename' => 'ressio_queue',
        )
    ),
);
