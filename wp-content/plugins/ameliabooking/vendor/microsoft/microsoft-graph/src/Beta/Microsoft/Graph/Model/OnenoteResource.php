<?php
/**
* Copyright (c) Microsoft Corporation.  All Rights Reserved.  Licensed under the MIT License.  See License in the project root for license information.
* 
* OnenoteResource File
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
* OnenoteResource class
*
* @category  Model
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
class OnenoteResource extends OnenoteEntityBaseModel
{
    /**
    * Gets the content
    * The content stream
    *
    * @return \AmeliaGuzzleHttp\Psr7\Stream|null The content
    */
    public function getContent()
    {
        if (array_key_exists("content", $this->_propDict)) {
            if (is_a($this->_propDict["content"], "\AmeliaGuzzleHttp\Psr7\Stream") || is_null($this->_propDict["content"])) {
                return $this->_propDict["content"];
            } else {
                $this->_propDict["content"] = \AmeliaGuzzleHttp\Psr7\Utils::streamFor($this->_propDict["content"]);
                return $this->_propDict["content"];
            }
        }
        return null;
    }

    /**
    * Sets the content
    * The content stream
    *
    * @param \AmeliaGuzzleHttp\Psr7\Stream $val The content
    *
    * @return OnenoteResource
    */
    public function setContent($val)
    {
        $this->_propDict["content"] = $val;
        return $this;
    }

    /**
    * Gets the contentUrl
    * The URL for downloading the content
    *
    * @return string|null The contentUrl
    */
    public function getContentUrl()
    {
        if (array_key_exists("contentUrl", $this->_propDict)) {
            return $this->_propDict["contentUrl"];
        } else {
            return null;
        }
    }

    /**
    * Sets the contentUrl
    * The URL for downloading the content
    *
    * @param string $val The contentUrl
    *
    * @return OnenoteResource
    */
    public function setContentUrl($val)
    {
        $this->_propDict["contentUrl"] = $val;
        return $this;
    }

}
