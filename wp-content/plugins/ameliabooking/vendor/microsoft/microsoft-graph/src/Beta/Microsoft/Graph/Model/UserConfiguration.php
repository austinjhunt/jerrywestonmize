<?php
/**
* Copyright (c) Microsoft Corporation.  All Rights Reserved.  Licensed under the MIT License.  See License in the project root for license information.
* 
* UserConfiguration File
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
* UserConfiguration class
*
* @category  Model
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
class UserConfiguration extends Entity
{
    /**
    * Gets the binaryData
    *
    * @return \AmeliaGuzzleHttp\Psr7\Stream|null The binaryData
    */
    public function getBinaryData()
    {
        if (array_key_exists("binaryData", $this->_propDict)) {
            if (is_a($this->_propDict["binaryData"], "\AmeliaGuzzleHttp\Psr7\Stream") || is_null($this->_propDict["binaryData"])) {
                return $this->_propDict["binaryData"];
            } else {
                $this->_propDict["binaryData"] = \AmeliaGuzzleHttp\Psr7\Utils::streamFor($this->_propDict["binaryData"]);
                return $this->_propDict["binaryData"];
            }
        }
        return null;
    }

    /**
    * Sets the binaryData
    *
    * @param \AmeliaGuzzleHttp\Psr7\Stream $val The binaryData
    *
    * @return UserConfiguration
    */
    public function setBinaryData($val)
    {
        $this->_propDict["binaryData"] = $val;
        return $this;
    }

}
