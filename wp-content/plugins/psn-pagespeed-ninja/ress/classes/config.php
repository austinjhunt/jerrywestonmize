<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

/**
 * Stub class Ressio_Config for IDE autocomplete
 */
class Ressio_Config
{
    /** @var string */
    public $webrootpath;
    /** @var string */
    public $webrooturi;
    /** @var string */
    public $staticdir;
    /** @var string */
    public $cachedir;
    /** @var int */
    public $cachettl;
    /** @var bool */
    public $cachefast;
    /** @var string[] */
    public $cachedeps;
    /** @var int */
    public $logginglevel;

    /** @var string ('file'|'php') */
    public $fileloader;
    /** @var string */
    public $fileloaderphppath;
    /** @var int */
    public $filehashsize;

    /** @var Ressio_ConfigHtml */
    public $html;
    /** @var Ressio_ConfigImg */
    public $img;
    /** @var Ressio_ConfigJs */
    public $js;
    /** @var Ressio_ConfigCss */
    public $css;

    /** @var Ressio_ConfigUrlLoader */
    public $urlloader;
    /** @var ?string */
    public $cafile;

    /** @var Ressio_ConfigAmdd */
    public $amdd;
    /** @var Ressio_ConfigRddb */
    public $rddb;

    /** @var array<string,mixed> */
    public $plugins;
    /** @var array<string,mixed> */
    public $di;

    /** @var Ressio_ConfigDB */
    public $db;

    /** @var ?Ressio_ConfigRedis */
    public $redis;

    /** @var Ressio_ConfigWorker */
    public $worker;
    /** @var string|int|null */
    public $change_group;
    /** @var bool */
    public $use_symlink;

    /** @var Ressio_ConfigVar */
    public $var;
}

class Ressio_ConfigHtml
{
    /** @var bool */
    public $forcehtml5;
    /** @var bool */
    public $mergespace;
    /** @var bool */
    public $removecomments;
    /** @var int */
    public $gzlevel;
    /** @var bool */
    public $urlminify;
    /** @var bool */
    public $sortattr;
    /** @var bool */
    public $removedefattr;
    /** @var bool */
    public $removeiecond;

    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_safe_exclude;
}

class Ressio_ConfigImg
{
    /** @var bool */
    public $minify;
    /** @var bool */
    public $minifyrescaled;
    /** @var stdClass */
    public $execoptim;
    /** @var int */
    public $jpegquality;
    /** @var int */
    public $webpquality;
    /** @var int */
    public $avifquality;
    /** @var bool */
    public $chroma420;
    /** @var bool */
    public $avif;
    /** @var bool */
    public $webp;
    /** @var bool */
    public $srcsetgeneration;
    /** @var int[] */
    public $srcsetwidths;

    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_minify_exclude;
}

class Ressio_ConfigJs
{
    /** @var bool */
    public $mergeheadbody;
    /** @var int */
    public $inlinelimit;
    /** @var bool */
    public $crossfileoptimization;
    /** @var bool */
    public $wraptrycatch;
    /** @var bool */
    public $automove;
    /** @var bool */
    public $forceasync;
    /** @var bool */
    public $forcedefer;
    /** @var bool */
    public $merge;
    /** @var bool */
    public $checkattributes;
    /** @var bool|string */
    public $mergeinline;
    /** @var bool */
    public $minifyattribute;
    /** @var bool */
    public $skipinits;
    /** @var ?string */
    public $exec;

    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_merge_exclude;
    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_merge_include;
    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_merge_bypass;
    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_merge_stop;
    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_merge_startgroup;
    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_merge_stopgroup;

    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_minify_exclude;

    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_move_exclude;

    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_async_exclude;
    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_async_include;

    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_defer_exclude;
    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_defer_include;

    /** @var string|null */
    public $nonce;
    /** @var string[] */
    public $minifychain;
}

class Ressio_ConfigCss
{
    /** @var bool */
    public $mergeheadbody;
    /** @var bool */
    public $merge;
    /** @var int */
    public $inlinelimit;
    /** @var bool */
    public $crossfileoptimization;
    /** @var bool */
    public $checklinkattributes;
    /** @var bool */
    public $checkstyleattributes;
    /** @var bool|string */
    public $mergeinline;
    /** @var bool */
    public $minifyattribute;
    /** @var ?string */
    public $exec;

    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_merge_exclude;
    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_merge_include;
    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_merge_bypass;
    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_merge_stop;
    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_merge_startgroup;
    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_merge_stopgroup;

    /** @var ?Ressio_ConfigExcludeRules */
    public $rules_minify_exclude;

    /** @var string|null */
    public $nonce;
    /** @var string[] */
    public $minifychain;
}

class Ressio_ConfigUrlLoader
{
    /** @var int */
    public $timeout;
}

class Ressio_ConfigAmdd
{
    /** @var string */
    public $handler;
    /** @var string */
    public $dbPath;
}

class Ressio_ConfigRddb
{
    /** @var string */
    public $apiurl;
    /** @var int */
    public $timeout;
    /** @var bool */
    public $proxy;
    /** @var string */
    public $proxy_url;
    /** @var string|false */
    public $proxy_login;
    /** @var string */
    public $proxy_pass;
}

class Ressio_ConfigWorker
{
    /** @var bool */
    public $enabled;
    /** @var int */
    public $maxworkers;
    /** @var int */
    public $maxexecutiontime;
    /** @var string */
    public $memorylimit;
    /** @var ?string */
    public $lockdir;
    /** @var array<string,string> */
    public $actors;
    /** @var Ressio_ConfigWorkerDB */
    public $db;
}

class Ressio_ConfigDB
{
    /** @var string */
    public $host;
    /** @var string */
    public $database;
    /** @var string */
    public $user;
    /** @var string */
    public $password;
    /** @var bool */
    public $persistent;
    /** @var ?JDatabaseDriver */
    public $joomlaDriver;
}

class Ressio_ConfigWorkerDB
{
    /** @var string */
    public $tablename;
}

class Ressio_ConfigVar
{
    /** @var ?string */
    public $imagenextgenformat;
    /** @var bool */
    public $queued;
    /** @var bool */
    public $workermode;
}

class Ressio_ConfigExcludeRules
{
    /** @var ?string */
    public $content;
    /** @var ?stdClass */
    public $attrs;
}

class Ressio_ConfigRedis
{
    /** @var ?Redis */
    public $instance;
    /** @var ?Ressio_ConfigRedisConfig */
    public $config;
    /** @var string */
    public $key_prefix;
}

class Ressio_ConfigRedisConfig
{
    /** @var ?string */
    public $host;
    /** @var ?int */
    public $port;
    /** @var ?int */
    public $timeout;
    /** @var ?int */
    public $retry_interval;
    /** @var ?int */
    public $read_timeout;
    /** @var ?array */
    public $context;
    /** @var string|array|null */
    public $auth;
    /** @var ?int */
    public $dbindex;
}
