<?php

// For older (pre-2.7.2) verions of google/apiclient
if (
    file_exists(__DIR__ . '/../apiclient/src/Google/Client.php')
    && !class_exists('AmeliaGoogle_Client', false)
) {
    require_once(__DIR__ . '/../apiclient/src/Google/Client.php');
    if (
        defined('AmeliaGoogle_Client::LIBVER')
        && version_compare(AmeliaGoogle_Client::LIBVER, '2.7.2', '<=')
    ) {
        $servicesClassMap = [
            'AmeliaGoogle\\Client' => 'AmeliaGoogle_Client',
            'AmeliaGoogle\\Service' => 'AmeliaGoogle_Service',
            'AmeliaGoogle\\Service\\Resource' => 'AmeliaGoogle_Service_Resource',
            'AmeliaGoogle\\Model' => 'AmeliaGoogle_Model',
            'AmeliaGoogle\\Collection' => 'AmeliaGoogle_Collection',
        ];
        foreach ($servicesClassMap as $alias => $class) {
            class_alias($class, $alias);
        }
    }
}
spl_autoload_register(function ($class) {
    if (0 === strpos($class, 'AmeliaGoogle_Service_')) {
        // Autoload the new class, which will also create an alias for the
        // old class by changing underscores to namespaces:
        //     AmeliaGoogle_Service_Speech_Resource_Operations
        //      => AmeliaGoogle\Service\Speech\Resource\Operations
        $classExists = class_exists($newClass = str_replace('_', '\\', $class));
        if ($classExists) {
            return true;
        }
    }
}, true, true);
