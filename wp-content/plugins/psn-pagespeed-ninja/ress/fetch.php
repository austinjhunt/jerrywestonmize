<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

/*
 * In previous RESS releases this file was named as get.php, but such a name is blocked by Apache's ModSecurity module.
 * That's why it was renamed to fetch.php.
 */

/** @return never */
function sendError404()
{
    sendResponseCode(404);
    echo '<h1>404 Not Found</h1>';
    exit();
}

/**
 * @param int $code
 * @return void
 */
function sendResponseCode($code)
{
    static $messages = array(
        200 => 'OK',
        304 => 'Not Modified',
        404 => 'Not Found'
    );

    $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0';
    $message = isset($messages[$code]) ? $messages[$code] : '';
    header("$protocol $code $message", true, $code);
    header('Status: $code $message');
}
$filename = !empty($_SERVER['PATH_INFO']) ? substr($_SERVER['PATH_INFO'], 1) : $_SERVER['QUERY_STRING'];
if (($pos = strpos($filename, '&')) !== false) {
    $filename = substr($filename, 0, $pos);
}

if (ini_get('expose_php')) {
    // override PHP's header
    header('X-Powered-By: ' . (defined('RESSIO_POWERED_BY') ? RESSIO_POWERED_BY : 'RESSIO'));
}

if ($filename === '' || $filename === false || strpos($filename, '/') !== false || strpos($filename, '..') !== false) {
    sendError404();
}

if (!defined('RESSIO_STATICDIR')) {
    define('RESSIO_PATH', __DIR__);
    include_once RESSIO_PATH . '/ressio.php';
    $config = Ressio::loadConfig();
    define('RESSIO_STATICDIR', $config->webrootpath . $config->staticdir);
}

$fullFilename = RESSIO_STATICDIR . '/' . $filename;

if (!is_file($fullFilename)) {
    sendError404();
}

$ext = pathinfo($filename, PATHINFO_EXTENSION);
switch ($ext) {
    case 'js':
        header('Content-Type: text/javascript');
        break;
    case 'css':
        header('Content-Type: text/css');
        break;
    default:
        sendError404();
}

/*
$etag = '"'.$filename.'"';
if(isset($_SERVER['HTTP_IF_NONE_MATCH']) ){
    $client_etag = stripslashes($_SERVER['HTTP_IF_NONE_MATCH']);
    if($client_etag===$etag)
    {
        sendResponseCode(304);
        header('ETag: '.$etag);
        exit();
    }
} else {
    header('ETag: ' . $etag);
}
*/
// expiration: +100 days
$cacheTTL = 100 * 24 * 60 * 60;
//header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $cacheTTL));
//header('Pragma: public');
//header('Cache-Control: public, must-revalidate, proxy-revalidate');
header("Cache-Control: public, max-age=$cacheTTL, immutable");

header('Vary: Accept-Encoding');

$brSupport = isset($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'br') !== false);
if ($brSupport) {
    $brFile = $fullFilename . '.br';
    if (is_file($brFile)) {
        header('Content-Encoding: br');
        header('Content-Length: ' . filesize($brFile));
        readfile($brFile);
        exit();
    }
}
// @TODO Add support of deflate encoding (equal to gzip without 10 bytes header and trailing 8 bytes CRC)
$gzSupport = isset($_SERVER['HTTP_ACCEPT_ENCODING']) && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false);
if ($gzSupport) {
    $gzFile = $fullFilename . '.gz';
    if (is_file($gzFile)) {
        header('Content-Encoding: gzip');
        header('Content-Length: ' . filesize($gzFile));
        readfile($gzFile);
        exit();
    }
}

readfile($fullFilename);
