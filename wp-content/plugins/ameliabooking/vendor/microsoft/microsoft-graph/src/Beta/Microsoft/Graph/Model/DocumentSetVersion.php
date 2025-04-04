<?php
/**
* Copyright (c) Microsoft Corporation.  All Rights Reserved.  Licensed under the MIT License.  See License in the project root for license information.
* 
* DocumentSetVersion File
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
* DocumentSetVersion class
*
* @category  Model
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
class DocumentSetVersion extends ListItemVersion
{
    /**
    * Gets the comment
    *
    * @return string|null The comment
    */
    public function getComment()
    {
        if (array_key_exists("comment", $this->_propDict)) {
            return $this->_propDict["comment"];
        } else {
            return null;
        }
    }

    /**
    * Sets the comment
    *
    * @param string $val The comment
    *
    * @return DocumentSetVersion
    */
    public function setComment($val)
    {
        $this->_propDict["comment"] = $val;
        return $this;
    }

    /**
    * Gets the createdBy
    *
    * @return IdentitySet|null The createdBy
    */
    public function getCreatedBy()
    {
        if (array_key_exists("createdBy", $this->_propDict)) {
            if (is_a($this->_propDict["createdBy"], "\Beta\Microsoft\Graph\Model\IdentitySet") || is_null($this->_propDict["createdBy"])) {
                return $this->_propDict["createdBy"];
            } else {
                $this->_propDict["createdBy"] = new IdentitySet($this->_propDict["createdBy"]);
                return $this->_propDict["createdBy"];
            }
        }
        return null;
    }

    /**
    * Sets the createdBy
    *
    * @param IdentitySet $val The createdBy
    *
    * @return DocumentSetVersion
    */
    public function setCreatedBy($val)
    {
        $this->_propDict["createdBy"] = $val;
        return $this;
    }

    /**
    * Gets the createdDateTime
    *
    * @return \DateTime|null The createdDateTime
    */
    public function getCreatedDateTime()
    {
        if (array_key_exists("createdDateTime", $this->_propDict)) {
            if (is_a($this->_propDict["createdDateTime"], "\DateTime") || is_null($this->_propDict["createdDateTime"])) {
                return $this->_propDict["createdDateTime"];
            } else {
                $this->_propDict["createdDateTime"] = new \DateTime($this->_propDict["createdDateTime"]);
                return $this->_propDict["createdDateTime"];
            }
        }
        return null;
    }

    /**
    * Sets the createdDateTime
    *
    * @param \DateTime $val The createdDateTime
    *
    * @return DocumentSetVersion
    */
    public function setCreatedDateTime($val)
    {
        $this->_propDict["createdDateTime"] = $val;
        return $this;
    }


     /**
     * Gets the items
     *
     * @return array|null The items
     */
    public function getItems()
    {
        if (array_key_exists("items", $this->_propDict)) {
           return $this->_propDict["items"];
        } else {
            return null;
        }
    }

    /**
    * Sets the items
    *
    * @param DocumentSetVersionItem[] $val The items
    *
    * @return DocumentSetVersion
    */
    public function setItems($val)
    {
        $this->_propDict["items"] = $val;
        return $this;
    }

    /**
    * Gets the shouldCaptureMinorVersion
    *
    * @return bool|null The shouldCaptureMinorVersion
    */
    public function getShouldCaptureMinorVersion()
    {
        if (array_key_exists("shouldCaptureMinorVersion", $this->_propDict)) {
            return $this->_propDict["shouldCaptureMinorVersion"];
        } else {
            return null;
        }
    }

    /**
    * Sets the shouldCaptureMinorVersion
    *
    * @param bool $val The shouldCaptureMinorVersion
    *
    * @return DocumentSetVersion
    */
    public function setShouldCaptureMinorVersion($val)
    {
        $this->_propDict["shouldCaptureMinorVersion"] = boolval($val);
        return $this;
    }

}
