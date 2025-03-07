<?php
/**
* Copyright (c) Microsoft Corporation.  All Rights Reserved.  Licensed under the MIT License.  See License in the project root for license information.
* 
* ListItem File
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
* ListItem class
*
* @category  Model
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
class ListItem extends BaseItem
{
    /**
    * Gets the contentType
    * The content type of this list item
    *
    * @return ContentTypeInfo|null The contentType
    */
    public function getContentType()
    {
        if (array_key_exists("contentType", $this->_propDict)) {
            if (is_a($this->_propDict["contentType"], "\Beta\Microsoft\Graph\Model\ContentTypeInfo") || is_null($this->_propDict["contentType"])) {
                return $this->_propDict["contentType"];
            } else {
                $this->_propDict["contentType"] = new ContentTypeInfo($this->_propDict["contentType"]);
                return $this->_propDict["contentType"];
            }
        }
        return null;
    }

    /**
    * Sets the contentType
    * The content type of this list item
    *
    * @param ContentTypeInfo $val The contentType
    *
    * @return ListItem
    */
    public function setContentType($val)
    {
        $this->_propDict["contentType"] = $val;
        return $this;
    }

    /**
    * Gets the deleted
    *
    * @return Deleted|null The deleted
    */
    public function getDeleted()
    {
        if (array_key_exists("deleted", $this->_propDict)) {
            if (is_a($this->_propDict["deleted"], "\Beta\Microsoft\Graph\Model\Deleted") || is_null($this->_propDict["deleted"])) {
                return $this->_propDict["deleted"];
            } else {
                $this->_propDict["deleted"] = new Deleted($this->_propDict["deleted"]);
                return $this->_propDict["deleted"];
            }
        }
        return null;
    }

    /**
    * Sets the deleted
    *
    * @param Deleted $val The deleted
    *
    * @return ListItem
    */
    public function setDeleted($val)
    {
        $this->_propDict["deleted"] = $val;
        return $this;
    }

    /**
    * Gets the sharepointIds
    * Returns identifiers useful for SharePoint REST compatibility. Read-only.
    *
    * @return SharepointIds|null The sharepointIds
    */
    public function getSharepointIds()
    {
        if (array_key_exists("sharepointIds", $this->_propDict)) {
            if (is_a($this->_propDict["sharepointIds"], "\Beta\Microsoft\Graph\Model\SharepointIds") || is_null($this->_propDict["sharepointIds"])) {
                return $this->_propDict["sharepointIds"];
            } else {
                $this->_propDict["sharepointIds"] = new SharepointIds($this->_propDict["sharepointIds"]);
                return $this->_propDict["sharepointIds"];
            }
        }
        return null;
    }

    /**
    * Sets the sharepointIds
    * Returns identifiers useful for SharePoint REST compatibility. Read-only.
    *
    * @param SharepointIds $val The sharepointIds
    *
    * @return ListItem
    */
    public function setSharepointIds($val)
    {
        $this->_propDict["sharepointIds"] = $val;
        return $this;
    }


     /**
     * Gets the activities
    * The list of recent activities that took place on this item.
     *
     * @return array|null The activities
     */
    public function getActivities()
    {
        if (array_key_exists("activities", $this->_propDict)) {
           return $this->_propDict["activities"];
        } else {
            return null;
        }
    }

    /**
    * Sets the activities
    * The list of recent activities that took place on this item.
    *
    * @param ItemActivityOLD[] $val The activities
    *
    * @return ListItem
    */
    public function setActivities($val)
    {
        $this->_propDict["activities"] = $val;
        return $this;
    }

    /**
    * Gets the analytics
    * Analytics about the view activities that took place on this item.
    *
    * @return ItemAnalytics|null The analytics
    */
    public function getAnalytics()
    {
        if (array_key_exists("analytics", $this->_propDict)) {
            if (is_a($this->_propDict["analytics"], "\Beta\Microsoft\Graph\Model\ItemAnalytics") || is_null($this->_propDict["analytics"])) {
                return $this->_propDict["analytics"];
            } else {
                $this->_propDict["analytics"] = new ItemAnalytics($this->_propDict["analytics"]);
                return $this->_propDict["analytics"];
            }
        }
        return null;
    }

    /**
    * Sets the analytics
    * Analytics about the view activities that took place on this item.
    *
    * @param ItemAnalytics $val The analytics
    *
    * @return ListItem
    */
    public function setAnalytics($val)
    {
        $this->_propDict["analytics"] = $val;
        return $this;
    }


     /**
     * Gets the documentSetVersions
     *
     * @return array|null The documentSetVersions
     */
    public function getDocumentSetVersions()
    {
        if (array_key_exists("documentSetVersions", $this->_propDict)) {
           return $this->_propDict["documentSetVersions"];
        } else {
            return null;
        }
    }

    /**
    * Sets the documentSetVersions
    *
    * @param DocumentSetVersion[] $val The documentSetVersions
    *
    * @return ListItem
    */
    public function setDocumentSetVersions($val)
    {
        $this->_propDict["documentSetVersions"] = $val;
        return $this;
    }

    /**
    * Gets the driveItem
    * For document libraries, the driveItem relationship exposes the listItem as a [driveItem][]
    *
    * @return DriveItem|null The driveItem
    */
    public function getDriveItem()
    {
        if (array_key_exists("driveItem", $this->_propDict)) {
            if (is_a($this->_propDict["driveItem"], "\Beta\Microsoft\Graph\Model\DriveItem") || is_null($this->_propDict["driveItem"])) {
                return $this->_propDict["driveItem"];
            } else {
                $this->_propDict["driveItem"] = new DriveItem($this->_propDict["driveItem"]);
                return $this->_propDict["driveItem"];
            }
        }
        return null;
    }

    /**
    * Sets the driveItem
    * For document libraries, the driveItem relationship exposes the listItem as a [driveItem][]
    *
    * @param DriveItem $val The driveItem
    *
    * @return ListItem
    */
    public function setDriveItem($val)
    {
        $this->_propDict["driveItem"] = $val;
        return $this;
    }

    /**
    * Gets the fields
    * The values of the columns set on this list item.
    *
    * @return FieldValueSet|null The fields
    */
    public function getFields()
    {
        if (array_key_exists("fields", $this->_propDict)) {
            if (is_a($this->_propDict["fields"], "\Beta\Microsoft\Graph\Model\FieldValueSet") || is_null($this->_propDict["fields"])) {
                return $this->_propDict["fields"];
            } else {
                $this->_propDict["fields"] = new FieldValueSet($this->_propDict["fields"]);
                return $this->_propDict["fields"];
            }
        }
        return null;
    }

    /**
    * Sets the fields
    * The values of the columns set on this list item.
    *
    * @param FieldValueSet $val The fields
    *
    * @return ListItem
    */
    public function setFields($val)
    {
        $this->_propDict["fields"] = $val;
        return $this;
    }


     /**
     * Gets the versions
    * The list of previous versions of the list item.
     *
     * @return array|null The versions
     */
    public function getVersions()
    {
        if (array_key_exists("versions", $this->_propDict)) {
           return $this->_propDict["versions"];
        } else {
            return null;
        }
    }

    /**
    * Sets the versions
    * The list of previous versions of the list item.
    *
    * @param ListItemVersion[] $val The versions
    *
    * @return ListItem
    */
    public function setVersions($val)
    {
        $this->_propDict["versions"] = $val;
        return $this;
    }

}
