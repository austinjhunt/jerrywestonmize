<?php
/**
* Copyright (c) Microsoft Corporation.  All Rights Reserved.  Licensed under the MIT License.  See License in the project root for license information.
* 
* MacOSTrustedRootCertificate File
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
* MacOSTrustedRootCertificate class
*
* @category  Model
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
class MacOSTrustedRootCertificate extends DeviceConfiguration
{
    /**
    * Gets the certFileName
    * File name to display in UI.
    *
    * @return string|null The certFileName
    */
    public function getCertFileName()
    {
        if (array_key_exists("certFileName", $this->_propDict)) {
            return $this->_propDict["certFileName"];
        } else {
            return null;
        }
    }

    /**
    * Sets the certFileName
    * File name to display in UI.
    *
    * @param string $val The certFileName
    *
    * @return MacOSTrustedRootCertificate
    */
    public function setCertFileName($val)
    {
        $this->_propDict["certFileName"] = $val;
        return $this;
    }

    /**
    * Gets the trustedRootCertificate
    * Trusted Root Certificate.
    *
    * @return \AmeliaGuzzleHttp\Psr7\Stream|null The trustedRootCertificate
    */
    public function getTrustedRootCertificate()
    {
        if (array_key_exists("trustedRootCertificate", $this->_propDict)) {
            if (is_a($this->_propDict["trustedRootCertificate"], "\AmeliaGuzzleHttp\Psr7\Stream") || is_null($this->_propDict["trustedRootCertificate"])) {
                return $this->_propDict["trustedRootCertificate"];
            } else {
                $this->_propDict["trustedRootCertificate"] = \AmeliaGuzzleHttp\Psr7\Utils::streamFor($this->_propDict["trustedRootCertificate"]);
                return $this->_propDict["trustedRootCertificate"];
            }
        }
        return null;
    }

    /**
    * Sets the trustedRootCertificate
    * Trusted Root Certificate.
    *
    * @param \AmeliaGuzzleHttp\Psr7\Stream $val The trustedRootCertificate
    *
    * @return MacOSTrustedRootCertificate
    */
    public function setTrustedRootCertificate($val)
    {
        $this->_propDict["trustedRootCertificate"] = $val;
        return $this;
    }

}
