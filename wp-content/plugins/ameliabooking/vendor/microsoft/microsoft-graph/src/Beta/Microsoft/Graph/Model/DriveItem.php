<?php
/**
* Copyright (c) Microsoft Corporation.  All Rights Reserved.  Licensed under the MIT License.  See License in the project root for license information.
* 
* DriveItem File
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
* DriveItem class
*
* @category  Model
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
class DriveItem extends BaseItem
{
    /**
    * Gets the audio
    * Audio metadata, if the item is an audio file. Read-only. Read-only. Only on OneDrive Personal.
    *
    * @return Audio|null The audio
    */
    public function getAudio()
    {
        if (array_key_exists("audio", $this->_propDict)) {
            if (is_a($this->_propDict["audio"], "\Beta\Microsoft\Graph\Model\Audio") || is_null($this->_propDict["audio"])) {
                return $this->_propDict["audio"];
            } else {
                $this->_propDict["audio"] = new Audio($this->_propDict["audio"]);
                return $this->_propDict["audio"];
            }
        }
        return null;
    }

    /**
    * Sets the audio
    * Audio metadata, if the item is an audio file. Read-only. Read-only. Only on OneDrive Personal.
    *
    * @param Audio $val The audio
    *
    * @return DriveItem
    */
    public function setAudio($val)
    {
        $this->_propDict["audio"] = $val;
        return $this;
    }

    /**
    * Gets the bundle
    * Bundle metadata, if the item is a bundle. Read-only.
    *
    * @return Bundle|null The bundle
    */
    public function getBundle()
    {
        if (array_key_exists("bundle", $this->_propDict)) {
            if (is_a($this->_propDict["bundle"], "\Beta\Microsoft\Graph\Model\Bundle") || is_null($this->_propDict["bundle"])) {
                return $this->_propDict["bundle"];
            } else {
                $this->_propDict["bundle"] = new Bundle($this->_propDict["bundle"]);
                return $this->_propDict["bundle"];
            }
        }
        return null;
    }

    /**
    * Sets the bundle
    * Bundle metadata, if the item is a bundle. Read-only.
    *
    * @param Bundle $val The bundle
    *
    * @return DriveItem
    */
    public function setBundle($val)
    {
        $this->_propDict["bundle"] = $val;
        return $this;
    }

    /**
    * Gets the content
    * The content stream, if the item represents a file.
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
    * The content stream, if the item represents a file.
    *
    * @param \AmeliaGuzzleHttp\Psr7\Stream $val The content
    *
    * @return DriveItem
    */
    public function setContent($val)
    {
        $this->_propDict["content"] = $val;
        return $this;
    }

    /**
    * Gets the cTag
    * An eTag for the content of the item. This eTag is not changed if only the metadata is changed. Note This property is not returned if the item is a folder. Read-only.
    *
    * @return string|null The cTag
    */
    public function getCTag()
    {
        if (array_key_exists("cTag", $this->_propDict)) {
            return $this->_propDict["cTag"];
        } else {
            return null;
        }
    }

    /**
    * Sets the cTag
    * An eTag for the content of the item. This eTag is not changed if only the metadata is changed. Note This property is not returned if the item is a folder. Read-only.
    *
    * @param string $val The cTag
    *
    * @return DriveItem
    */
    public function setCTag($val)
    {
        $this->_propDict["cTag"] = $val;
        return $this;
    }

    /**
    * Gets the deleted
    * Information about the deleted state of the item. Read-only.
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
    * Information about the deleted state of the item. Read-only.
    *
    * @param Deleted $val The deleted
    *
    * @return DriveItem
    */
    public function setDeleted($val)
    {
        $this->_propDict["deleted"] = $val;
        return $this;
    }

    /**
    * Gets the file
    * File metadata, if the item is a file. Read-only.
    *
    * @return File|null The file
    */
    public function getFile()
    {
        if (array_key_exists("file", $this->_propDict)) {
            if (is_a($this->_propDict["file"], "\Beta\Microsoft\Graph\Model\File") || is_null($this->_propDict["file"])) {
                return $this->_propDict["file"];
            } else {
                $this->_propDict["file"] = new File($this->_propDict["file"]);
                return $this->_propDict["file"];
            }
        }
        return null;
    }

    /**
    * Sets the file
    * File metadata, if the item is a file. Read-only.
    *
    * @param File $val The file
    *
    * @return DriveItem
    */
    public function setFile($val)
    {
        $this->_propDict["file"] = $val;
        return $this;
    }

    /**
    * Gets the fileSystemInfo
    * File system information on client. Read-write.
    *
    * @return FileSystemInfo|null The fileSystemInfo
    */
    public function getFileSystemInfo()
    {
        if (array_key_exists("fileSystemInfo", $this->_propDict)) {
            if (is_a($this->_propDict["fileSystemInfo"], "\Beta\Microsoft\Graph\Model\FileSystemInfo") || is_null($this->_propDict["fileSystemInfo"])) {
                return $this->_propDict["fileSystemInfo"];
            } else {
                $this->_propDict["fileSystemInfo"] = new FileSystemInfo($this->_propDict["fileSystemInfo"]);
                return $this->_propDict["fileSystemInfo"];
            }
        }
        return null;
    }

    /**
    * Sets the fileSystemInfo
    * File system information on client. Read-write.
    *
    * @param FileSystemInfo $val The fileSystemInfo
    *
    * @return DriveItem
    */
    public function setFileSystemInfo($val)
    {
        $this->_propDict["fileSystemInfo"] = $val;
        return $this;
    }

    /**
    * Gets the folder
    * Folder metadata, if the item is a folder. Read-only.
    *
    * @return Folder|null The folder
    */
    public function getFolder()
    {
        if (array_key_exists("folder", $this->_propDict)) {
            if (is_a($this->_propDict["folder"], "\Beta\Microsoft\Graph\Model\Folder") || is_null($this->_propDict["folder"])) {
                return $this->_propDict["folder"];
            } else {
                $this->_propDict["folder"] = new Folder($this->_propDict["folder"]);
                return $this->_propDict["folder"];
            }
        }
        return null;
    }

    /**
    * Sets the folder
    * Folder metadata, if the item is a folder. Read-only.
    *
    * @param Folder $val The folder
    *
    * @return DriveItem
    */
    public function setFolder($val)
    {
        $this->_propDict["folder"] = $val;
        return $this;
    }

    /**
    * Gets the image
    * Image metadata, if the item is an image. Read-only.
    *
    * @return Image|null The image
    */
    public function getImage()
    {
        if (array_key_exists("image", $this->_propDict)) {
            if (is_a($this->_propDict["image"], "\Beta\Microsoft\Graph\Model\Image") || is_null($this->_propDict["image"])) {
                return $this->_propDict["image"];
            } else {
                $this->_propDict["image"] = new Image($this->_propDict["image"]);
                return $this->_propDict["image"];
            }
        }
        return null;
    }

    /**
    * Sets the image
    * Image metadata, if the item is an image. Read-only.
    *
    * @param Image $val The image
    *
    * @return DriveItem
    */
    public function setImage($val)
    {
        $this->_propDict["image"] = $val;
        return $this;
    }

    /**
    * Gets the location
    * Location metadata, if the item has location data. Read-only.
    *
    * @return GeoCoordinates|null The location
    */
    public function getLocation()
    {
        if (array_key_exists("location", $this->_propDict)) {
            if (is_a($this->_propDict["location"], "\Beta\Microsoft\Graph\Model\GeoCoordinates") || is_null($this->_propDict["location"])) {
                return $this->_propDict["location"];
            } else {
                $this->_propDict["location"] = new GeoCoordinates($this->_propDict["location"]);
                return $this->_propDict["location"];
            }
        }
        return null;
    }

    /**
    * Sets the location
    * Location metadata, if the item has location data. Read-only.
    *
    * @param GeoCoordinates $val The location
    *
    * @return DriveItem
    */
    public function setLocation($val)
    {
        $this->_propDict["location"] = $val;
        return $this;
    }

    /**
    * Gets the malware
    * Malware metadata, if the item was detected to contain malware. Read-only.
    *
    * @return Malware|null The malware
    */
    public function getMalware()
    {
        if (array_key_exists("malware", $this->_propDict)) {
            if (is_a($this->_propDict["malware"], "\Beta\Microsoft\Graph\Model\Malware") || is_null($this->_propDict["malware"])) {
                return $this->_propDict["malware"];
            } else {
                $this->_propDict["malware"] = new Malware($this->_propDict["malware"]);
                return $this->_propDict["malware"];
            }
        }
        return null;
    }

    /**
    * Sets the malware
    * Malware metadata, if the item was detected to contain malware. Read-only.
    *
    * @param Malware $val The malware
    *
    * @return DriveItem
    */
    public function setMalware($val)
    {
        $this->_propDict["malware"] = $val;
        return $this;
    }

    /**
    * Gets the media
    * Information about the media (audio or video) item. Read-write. Only on OneDrive for Business and SharePoint.
    *
    * @return Media|null The media
    */
    public function getMedia()
    {
        if (array_key_exists("media", $this->_propDict)) {
            if (is_a($this->_propDict["media"], "\Beta\Microsoft\Graph\Model\Media") || is_null($this->_propDict["media"])) {
                return $this->_propDict["media"];
            } else {
                $this->_propDict["media"] = new Media($this->_propDict["media"]);
                return $this->_propDict["media"];
            }
        }
        return null;
    }

    /**
    * Sets the media
    * Information about the media (audio or video) item. Read-write. Only on OneDrive for Business and SharePoint.
    *
    * @param Media $val The media
    *
    * @return DriveItem
    */
    public function setMedia($val)
    {
        $this->_propDict["media"] = $val;
        return $this;
    }

    /**
    * Gets the package
    * If present, indicates that this item is a package instead of a folder or file. Packages are treated like files in some contexts and folders in others. Read-only.
    *
    * @return Package|null The package
    */
    public function getPackage()
    {
        if (array_key_exists("package", $this->_propDict)) {
            if (is_a($this->_propDict["package"], "\Beta\Microsoft\Graph\Model\Package") || is_null($this->_propDict["package"])) {
                return $this->_propDict["package"];
            } else {
                $this->_propDict["package"] = new Package($this->_propDict["package"]);
                return $this->_propDict["package"];
            }
        }
        return null;
    }

    /**
    * Sets the package
    * If present, indicates that this item is a package instead of a folder or file. Packages are treated like files in some contexts and folders in others. Read-only.
    *
    * @param Package $val The package
    *
    * @return DriveItem
    */
    public function setPackage($val)
    {
        $this->_propDict["package"] = $val;
        return $this;
    }

    /**
    * Gets the pendingOperations
    * If present, indicates that one or more operations that might affect the state of the driveItem are pending completion. Read-only.
    *
    * @return PendingOperations|null The pendingOperations
    */
    public function getPendingOperations()
    {
        if (array_key_exists("pendingOperations", $this->_propDict)) {
            if (is_a($this->_propDict["pendingOperations"], "\Beta\Microsoft\Graph\Model\PendingOperations") || is_null($this->_propDict["pendingOperations"])) {
                return $this->_propDict["pendingOperations"];
            } else {
                $this->_propDict["pendingOperations"] = new PendingOperations($this->_propDict["pendingOperations"]);
                return $this->_propDict["pendingOperations"];
            }
        }
        return null;
    }

    /**
    * Sets the pendingOperations
    * If present, indicates that one or more operations that might affect the state of the driveItem are pending completion. Read-only.
    *
    * @param PendingOperations $val The pendingOperations
    *
    * @return DriveItem
    */
    public function setPendingOperations($val)
    {
        $this->_propDict["pendingOperations"] = $val;
        return $this;
    }

    /**
    * Gets the photo
    * Photo metadata, if the item is a photo. Read-only.
    *
    * @return Photo|null The photo
    */
    public function getPhoto()
    {
        if (array_key_exists("photo", $this->_propDict)) {
            if (is_a($this->_propDict["photo"], "\Beta\Microsoft\Graph\Model\Photo") || is_null($this->_propDict["photo"])) {
                return $this->_propDict["photo"];
            } else {
                $this->_propDict["photo"] = new Photo($this->_propDict["photo"]);
                return $this->_propDict["photo"];
            }
        }
        return null;
    }

    /**
    * Sets the photo
    * Photo metadata, if the item is a photo. Read-only.
    *
    * @param Photo $val The photo
    *
    * @return DriveItem
    */
    public function setPhoto($val)
    {
        $this->_propDict["photo"] = $val;
        return $this;
    }

    /**
    * Gets the publication
    * Provides information about the published or checked-out state of an item, in locations that support such actions. This property is not returned by default. Read-only.
    *
    * @return PublicationFacet|null The publication
    */
    public function getPublication()
    {
        if (array_key_exists("publication", $this->_propDict)) {
            if (is_a($this->_propDict["publication"], "\Beta\Microsoft\Graph\Model\PublicationFacet") || is_null($this->_propDict["publication"])) {
                return $this->_propDict["publication"];
            } else {
                $this->_propDict["publication"] = new PublicationFacet($this->_propDict["publication"]);
                return $this->_propDict["publication"];
            }
        }
        return null;
    }

    /**
    * Sets the publication
    * Provides information about the published or checked-out state of an item, in locations that support such actions. This property is not returned by default. Read-only.
    *
    * @param PublicationFacet $val The publication
    *
    * @return DriveItem
    */
    public function setPublication($val)
    {
        $this->_propDict["publication"] = $val;
        return $this;
    }

    /**
    * Gets the remoteItem
    * Remote item data, if the item is shared from a drive other than the one being accessed. Read-only.
    *
    * @return RemoteItem|null The remoteItem
    */
    public function getRemoteItem()
    {
        if (array_key_exists("remoteItem", $this->_propDict)) {
            if (is_a($this->_propDict["remoteItem"], "\Beta\Microsoft\Graph\Model\RemoteItem") || is_null($this->_propDict["remoteItem"])) {
                return $this->_propDict["remoteItem"];
            } else {
                $this->_propDict["remoteItem"] = new RemoteItem($this->_propDict["remoteItem"]);
                return $this->_propDict["remoteItem"];
            }
        }
        return null;
    }

    /**
    * Sets the remoteItem
    * Remote item data, if the item is shared from a drive other than the one being accessed. Read-only.
    *
    * @param RemoteItem $val The remoteItem
    *
    * @return DriveItem
    */
    public function setRemoteItem($val)
    {
        $this->_propDict["remoteItem"] = $val;
        return $this;
    }

    /**
    * Gets the root
    * If this property is non-null, it indicates that the driveItem is the top-most driveItem in the drive.
    *
    * @return Root|null The root
    */
    public function getRoot()
    {
        if (array_key_exists("root", $this->_propDict)) {
            if (is_a($this->_propDict["root"], "\Beta\Microsoft\Graph\Model\Root") || is_null($this->_propDict["root"])) {
                return $this->_propDict["root"];
            } else {
                $this->_propDict["root"] = new Root($this->_propDict["root"]);
                return $this->_propDict["root"];
            }
        }
        return null;
    }

    /**
    * Sets the root
    * If this property is non-null, it indicates that the driveItem is the top-most driveItem in the drive.
    *
    * @param Root $val The root
    *
    * @return DriveItem
    */
    public function setRoot($val)
    {
        $this->_propDict["root"] = $val;
        return $this;
    }

    /**
    * Gets the searchResult
    * Search metadata, if the item is from a search result. Read-only.
    *
    * @return SearchResult|null The searchResult
    */
    public function getSearchResult()
    {
        if (array_key_exists("searchResult", $this->_propDict)) {
            if (is_a($this->_propDict["searchResult"], "\Beta\Microsoft\Graph\Model\SearchResult") || is_null($this->_propDict["searchResult"])) {
                return $this->_propDict["searchResult"];
            } else {
                $this->_propDict["searchResult"] = new SearchResult($this->_propDict["searchResult"]);
                return $this->_propDict["searchResult"];
            }
        }
        return null;
    }

    /**
    * Sets the searchResult
    * Search metadata, if the item is from a search result. Read-only.
    *
    * @param SearchResult $val The searchResult
    *
    * @return DriveItem
    */
    public function setSearchResult($val)
    {
        $this->_propDict["searchResult"] = $val;
        return $this;
    }

    /**
    * Gets the shared
    * Indicates that the item has been shared with others and provides information about the shared state of the item. Read-only.
    *
    * @return Shared|null The shared
    */
    public function getShared()
    {
        if (array_key_exists("shared", $this->_propDict)) {
            if (is_a($this->_propDict["shared"], "\Beta\Microsoft\Graph\Model\Shared") || is_null($this->_propDict["shared"])) {
                return $this->_propDict["shared"];
            } else {
                $this->_propDict["shared"] = new Shared($this->_propDict["shared"]);
                return $this->_propDict["shared"];
            }
        }
        return null;
    }

    /**
    * Sets the shared
    * Indicates that the item has been shared with others and provides information about the shared state of the item. Read-only.
    *
    * @param Shared $val The shared
    *
    * @return DriveItem
    */
    public function setShared($val)
    {
        $this->_propDict["shared"] = $val;
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
    * @return DriveItem
    */
    public function setSharepointIds($val)
    {
        $this->_propDict["sharepointIds"] = $val;
        return $this;
    }

    /**
    * Gets the size
    * Size of the item in bytes. Read-only.
    *
    * @return int|null The size
    */
    public function getSize()
    {
        if (array_key_exists("size", $this->_propDict)) {
            return $this->_propDict["size"];
        } else {
            return null;
        }
    }

    /**
    * Sets the size
    * Size of the item in bytes. Read-only.
    *
    * @param int $val The size
    *
    * @return DriveItem
    */
    public function setSize($val)
    {
        $this->_propDict["size"] = intval($val);
        return $this;
    }

    /**
    * Gets the source
    * Information about the drive item source. Read-only. Only on OneDrive for Business and SharePoint.
    *
    * @return DriveItemSource|null The source
    */
    public function getSource()
    {
        if (array_key_exists("source", $this->_propDict)) {
            if (is_a($this->_propDict["source"], "\Beta\Microsoft\Graph\Model\DriveItemSource") || is_null($this->_propDict["source"])) {
                return $this->_propDict["source"];
            } else {
                $this->_propDict["source"] = new DriveItemSource($this->_propDict["source"]);
                return $this->_propDict["source"];
            }
        }
        return null;
    }

    /**
    * Sets the source
    * Information about the drive item source. Read-only. Only on OneDrive for Business and SharePoint.
    *
    * @param DriveItemSource $val The source
    *
    * @return DriveItem
    */
    public function setSource($val)
    {
        $this->_propDict["source"] = $val;
        return $this;
    }

    /**
    * Gets the specialFolder
    * If the current item is also available as a special folder, this facet is returned. Read-only.
    *
    * @return SpecialFolder|null The specialFolder
    */
    public function getSpecialFolder()
    {
        if (array_key_exists("specialFolder", $this->_propDict)) {
            if (is_a($this->_propDict["specialFolder"], "\Beta\Microsoft\Graph\Model\SpecialFolder") || is_null($this->_propDict["specialFolder"])) {
                return $this->_propDict["specialFolder"];
            } else {
                $this->_propDict["specialFolder"] = new SpecialFolder($this->_propDict["specialFolder"]);
                return $this->_propDict["specialFolder"];
            }
        }
        return null;
    }

    /**
    * Sets the specialFolder
    * If the current item is also available as a special folder, this facet is returned. Read-only.
    *
    * @param SpecialFolder $val The specialFolder
    *
    * @return DriveItem
    */
    public function setSpecialFolder($val)
    {
        $this->_propDict["specialFolder"] = $val;
        return $this;
    }

    /**
    * Gets the video
    * Video metadata, if the item is a video. Read-only.
    *
    * @return Video|null The video
    */
    public function getVideo()
    {
        if (array_key_exists("video", $this->_propDict)) {
            if (is_a($this->_propDict["video"], "\Beta\Microsoft\Graph\Model\Video") || is_null($this->_propDict["video"])) {
                return $this->_propDict["video"];
            } else {
                $this->_propDict["video"] = new Video($this->_propDict["video"]);
                return $this->_propDict["video"];
            }
        }
        return null;
    }

    /**
    * Sets the video
    * Video metadata, if the item is a video. Read-only.
    *
    * @param Video $val The video
    *
    * @return DriveItem
    */
    public function setVideo($val)
    {
        $this->_propDict["video"] = $val;
        return $this;
    }

    /**
    * Gets the webDavUrl
    * WebDAV compatible URL for the item.
    *
    * @return string|null The webDavUrl
    */
    public function getWebDavUrl()
    {
        if (array_key_exists("webDavUrl", $this->_propDict)) {
            return $this->_propDict["webDavUrl"];
        } else {
            return null;
        }
    }

    /**
    * Sets the webDavUrl
    * WebDAV compatible URL for the item.
    *
    * @param string $val The webDavUrl
    *
    * @return DriveItem
    */
    public function setWebDavUrl($val)
    {
        $this->_propDict["webDavUrl"] = $val;
        return $this;
    }

    /**
    * Gets the workbook
    * For files that are Excel spreadsheets, accesses the workbook API to work with the spreadsheet's contents. Nullable.
    *
    * @return Workbook|null The workbook
    */
    public function getWorkbook()
    {
        if (array_key_exists("workbook", $this->_propDict)) {
            if (is_a($this->_propDict["workbook"], "\Beta\Microsoft\Graph\Model\Workbook") || is_null($this->_propDict["workbook"])) {
                return $this->_propDict["workbook"];
            } else {
                $this->_propDict["workbook"] = new Workbook($this->_propDict["workbook"]);
                return $this->_propDict["workbook"];
            }
        }
        return null;
    }

    /**
    * Sets the workbook
    * For files that are Excel spreadsheets, accesses the workbook API to work with the spreadsheet's contents. Nullable.
    *
    * @param Workbook $val The workbook
    *
    * @return DriveItem
    */
    public function setWorkbook($val)
    {
        $this->_propDict["workbook"] = $val;
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
    * @return DriveItem
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
    * @return DriveItem
    */
    public function setAnalytics($val)
    {
        $this->_propDict["analytics"] = $val;
        return $this;
    }


     /**
     * Gets the children
    * Collection containing Item objects for the immediate children of Item. Only items representing folders have children. Read-only. Nullable.
     *
     * @return array|null The children
     */
    public function getChildren()
    {
        if (array_key_exists("children", $this->_propDict)) {
           return $this->_propDict["children"];
        } else {
            return null;
        }
    }

    /**
    * Sets the children
    * Collection containing Item objects for the immediate children of Item. Only items representing folders have children. Read-only. Nullable.
    *
    * @param DriveItem[] $val The children
    *
    * @return DriveItem
    */
    public function setChildren($val)
    {
        $this->_propDict["children"] = $val;
        return $this;
    }

    /**
    * Gets the listItem
    * For drives in SharePoint, the associated document library list item. Read-only. Nullable.
    *
    * @return ListItem|null The listItem
    */
    public function getListItem()
    {
        if (array_key_exists("listItem", $this->_propDict)) {
            if (is_a($this->_propDict["listItem"], "\Beta\Microsoft\Graph\Model\ListItem") || is_null($this->_propDict["listItem"])) {
                return $this->_propDict["listItem"];
            } else {
                $this->_propDict["listItem"] = new ListItem($this->_propDict["listItem"]);
                return $this->_propDict["listItem"];
            }
        }
        return null;
    }

    /**
    * Sets the listItem
    * For drives in SharePoint, the associated document library list item. Read-only. Nullable.
    *
    * @param ListItem $val The listItem
    *
    * @return DriveItem
    */
    public function setListItem($val)
    {
        $this->_propDict["listItem"] = $val;
        return $this;
    }


     /**
     * Gets the permissions
    * The set of permissions for the item. Read-only. Nullable.
     *
     * @return array|null The permissions
     */
    public function getPermissions()
    {
        if (array_key_exists("permissions", $this->_propDict)) {
           return $this->_propDict["permissions"];
        } else {
            return null;
        }
    }

    /**
    * Sets the permissions
    * The set of permissions for the item. Read-only. Nullable.
    *
    * @param Permission[] $val The permissions
    *
    * @return DriveItem
    */
    public function setPermissions($val)
    {
        $this->_propDict["permissions"] = $val;
        return $this;
    }


     /**
     * Gets the subscriptions
    * The set of subscriptions on the item. Only supported on the root of a drive.
     *
     * @return array|null The subscriptions
     */
    public function getSubscriptions()
    {
        if (array_key_exists("subscriptions", $this->_propDict)) {
           return $this->_propDict["subscriptions"];
        } else {
            return null;
        }
    }

    /**
    * Sets the subscriptions
    * The set of subscriptions on the item. Only supported on the root of a drive.
    *
    * @param Subscription[] $val The subscriptions
    *
    * @return DriveItem
    */
    public function setSubscriptions($val)
    {
        $this->_propDict["subscriptions"] = $val;
        return $this;
    }


     /**
     * Gets the thumbnails
    * Collection containing [ThumbnailSet][] objects associated with the item. For more info, see [getting thumbnails][]. Read-only. Nullable.
     *
     * @return array|null The thumbnails
     */
    public function getThumbnails()
    {
        if (array_key_exists("thumbnails", $this->_propDict)) {
           return $this->_propDict["thumbnails"];
        } else {
            return null;
        }
    }

    /**
    * Sets the thumbnails
    * Collection containing [ThumbnailSet][] objects associated with the item. For more info, see [getting thumbnails][]. Read-only. Nullable.
    *
    * @param ThumbnailSet[] $val The thumbnails
    *
    * @return DriveItem
    */
    public function setThumbnails($val)
    {
        $this->_propDict["thumbnails"] = $val;
        return $this;
    }


     /**
     * Gets the versions
    * The list of previous versions of the item. For more info, see [getting previous versions][]. Read-only. Nullable.
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
    * The list of previous versions of the item. For more info, see [getting previous versions][]. Read-only. Nullable.
    *
    * @param DriveItemVersion[] $val The versions
    *
    * @return DriveItem
    */
    public function setVersions($val)
    {
        $this->_propDict["versions"] = $val;
        return $this;
    }

}
