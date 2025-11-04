<?php

if (class_exists('AmeliaGoogle_Client', false)) {
    // Prevent error with preloading in PHP 7.4
    // @see https://github.com/googleapis/google-api-php-client/issues/1976
    return;
}

$classMap = [
    'AmeliaGoogle\\Client' => 'AmeliaGoogle_Client',
    'AmeliaGoogle\\Service' => 'AmeliaGoogle_Service',
    'AmeliaGoogle\\AccessToken\\Revoke' => 'AmeliaGoogle_AccessToken_Revoke',
    'AmeliaGoogle\\AccessToken\\Verify' => 'AmeliaGoogle_AccessToken_Verify',
    'AmeliaGoogle\\Model' => 'AmeliaGoogle_Model',
    'AmeliaGoogle\\Utils\\UriTemplate' => 'AmeliaGoogle_Utils_UriTemplate',
    'AmeliaGoogle\\AuthHandler\\Guzzle6AuthHandler' => 'AmeliaGoogle_AuthHandler_Guzzle6AuthHandler',
    'AmeliaGoogle\\AuthHandler\\Guzzle7AuthHandler' => 'AmeliaGoogle_AuthHandler_Guzzle7AuthHandler',
    'AmeliaGoogle\\AuthHandler\\Guzzle5AuthHandler' => 'AmeliaGoogle_AuthHandler_Guzzle5AuthHandler',
    'AmeliaGoogle\\AuthHandler\\AuthHandlerFactory' => 'AmeliaGoogle_AuthHandler_AuthHandlerFactory',
    'AmeliaGoogle\\Http\\Batch' => 'AmeliaGoogle_Http_Batch',
    'AmeliaGoogle\\Http\\MediaFileUpload' => 'AmeliaGoogle_Http_MediaFileUpload',
    'AmeliaGoogle\\Http\\REST' => 'AmeliaGoogle_Http_REST',
    'AmeliaGoogle\\Task\\Retryable' => 'AmeliaGoogle_Task_Retryable',
    'AmeliaGoogle\\Task\\Exception' => 'AmeliaGoogle_Task_Exception',
    'AmeliaGoogle\\Task\\Runner' => 'AmeliaGoogle_Task_Runner',
    'AmeliaGoogle\\Collection' => 'AmeliaGoogle_Collection',
    'AmeliaGoogle\\Service\\Exception' => 'AmeliaGoogle_Service_Exception',
    'AmeliaGoogle\\Service\\Resource' => 'AmeliaGoogle_Service_Resource',
    'AmeliaGoogle\\Exception' => 'AmeliaGoogle_Exception',
];

foreach ($classMap as $class => $alias) {
    class_alias($class, $alias);
}

/**
 * This class needs to be defined explicitly as scripts must be recognized by
 * the autoloader.
 */
class AmeliaGoogle_Task_Composer extends \AmeliaGoogle\Task\Composer
{
}

/** @phpstan-ignore-next-line */
if (\false) {
    class AmeliaGoogle_AccessToken_Revoke extends \AmeliaGoogle\AccessToken\Revoke
    {
    }
    class AmeliaGoogle_AccessToken_Verify extends \AmeliaGoogle\AccessToken\Verify
    {
    }
    class AmeliaGoogle_AuthHandler_AuthHandlerFactory extends \AmeliaGoogle\AuthHandler\AuthHandlerFactory
    {
    }
    class AmeliaGoogle_AuthHandler_Guzzle5AuthHandler extends \AmeliaGoogle\AuthHandler\Guzzle5AuthHandler
    {
    }
    class AmeliaGoogle_AuthHandler_Guzzle6AuthHandler extends \AmeliaGoogle\AuthHandler\Guzzle6AuthHandler
    {
    }
    class AmeliaGoogle_AuthHandler_Guzzle7AuthHandler extends \AmeliaGoogle\AuthHandler\Guzzle7AuthHandler
    {
    }
    class AmeliaGoogle_Client extends \AmeliaGoogle\Client
    {
    }
    class AmeliaGoogle_Collection extends \AmeliaGoogle\Collection
    {
    }
    class AmeliaGoogle_Exception extends \AmeliaGoogle\Exception
    {
    }
    class AmeliaGoogle_Http_Batch extends \AmeliaGoogle\Http\Batch
    {
    }
    class AmeliaGoogle_Http_MediaFileUpload extends \AmeliaGoogle\Http\MediaFileUpload
    {
    }
    class AmeliaGoogle_Http_REST extends \AmeliaGoogle\Http\REST
    {
    }
    class AmeliaGoogle_Model extends \AmeliaGoogle\Model
    {
    }
    class AmeliaGoogle_Service extends \AmeliaGoogle\Service
    {
    }
    class AmeliaGoogle_Service_Exception extends \AmeliaGoogle\Service\Exception
    {
    }
    class AmeliaGoogle_Service_Resource extends \AmeliaGoogle\Service\Resource
    {
    }
    class AmeliaGoogle_Task_Exception extends \AmeliaGoogle\Task\Exception
    {
    }
    interface AmeliaGoogle_Task_Retryable extends \AmeliaGoogle\Task\Retryable
    {
    }
    class AmeliaGoogle_Task_Runner extends \AmeliaGoogle\Task\Runner
    {
    }
    class AmeliaGoogle_Utils_UriTemplate extends \AmeliaGoogle\Utils\UriTemplate
    {
    }
}
