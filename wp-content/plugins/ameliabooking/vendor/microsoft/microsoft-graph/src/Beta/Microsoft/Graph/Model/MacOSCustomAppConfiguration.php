<?php
/**
* Copyright (c) Microsoft Corporation.  All Rights Reserved.  Licensed under the MIT License.  See License in the project root for license information.
* 
* MacOSCustomAppConfiguration File
* PHP version 7
*
* @category  Library
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
namespace Beta\Microsoft\Graph\Model;

/**
* MacOSCustomAppConfiguration class
*
* @category  Model
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
class MacOSCustomAppConfiguration extends DeviceConfiguration
{
    /**
    * Gets the bundleId
    * Bundle id for targeting.
    *
    * @return string|null The bundleId
    */
    public function getBundleId()
    {
        if (array_key_exists("bundleId", $this->_propDict)) {
            return $this->_propDict["bundleId"];
        } else {
            return null;
        }
    }

    /**
    * Sets the bundleId
    * Bundle id for targeting.
    *
    * @param string $val The bundleId
    *
    * @return MacOSCustomAppConfiguration
    */
    public function setBundleId($val)
    {
        $this->_propDict["bundleId"] = $val;
        return $this;
    }

    /**
    * Gets the configurationXml
    * Configuration xml. (UTF8 encoded byte array)
    *
    * @return \AmeliaGuzzleHttp\Psr7\Stream|null The configurationXml
    */
    public function getConfigurationXml()
    {
        if (array_key_exists("configurationXml", $this->_propDict)) {
            if (is_a($this->_propDict["configurationXml"], "\AmeliaGuzzleHttp\Psr7\Stream") || is_null($this->_propDict["configurationXml"])) {
                return $this->_propDict["configurationXml"];
            } else {
                $this->_propDict["configurationXml"] = \AmeliaGuzzleHttp\Psr7\Utils::streamFor($this->_propDict["configurationXml"]);
                return $this->_propDict["configurationXml"];
            }
        }
        return null;
    }

    /**
    * Sets the configurationXml
    * Configuration xml. (UTF8 encoded byte array)
    *
    * @param \AmeliaGuzzleHttp\Psr7\Stream $val The configurationXml
    *
    * @return MacOSCustomAppConfiguration
    */
    public function setConfigurationXml($val)
    {
        $this->_propDict["configurationXml"] = $val;
        return $this;
    }

    /**
    * Gets the fileName
    * Configuration file name (.plist
    *
    * @return string|null The fileName
    */
    public function getFileName()
    {
        if (array_key_exists("fileName", $this->_propDict)) {
            return $this->_propDict["fileName"];
        } else {
            return null;
        }
    }

    /**
    * Sets the fileName
    * Configuration file name (.plist
    *
    * @param string $val The fileName
    *
    * @return MacOSCustomAppConfiguration
    */
    public function setFileName($val)
    {
        $this->_propDict["fileName"] = $val;
        return $this;
    }

}
