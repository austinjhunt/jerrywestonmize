<?php
/**
* Copyright (c) Microsoft Corporation.  All Rights Reserved.  Licensed under the MIT License.  See License in the project root for license information.
* 
* SelfSignedCertificate File
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
* SelfSignedCertificate class
*
* @category  Model
* @package   Microsoft.Graph
* @copyright (c) Microsoft Corporation. All rights reserved.
* @license   https://opensource.org/licenses/MIT MIT License
* @link      https://graph.microsoft.com
*/
class SelfSignedCertificate extends Entity
{

    /**
    * Gets the customKeyIdentifier
    * Custom key identifier.
    *
    * @return \AmeliaGuzzleHttp\Psr7\Stream|null The customKeyIdentifier
    */
    public function getCustomKeyIdentifier()
    {
        if (array_key_exists("customKeyIdentifier", $this->_propDict)) {
            if (is_a($this->_propDict["customKeyIdentifier"], "\AmeliaGuzzleHttp\Psr7\Stream") || is_null($this->_propDict["customKeyIdentifier"])) {
                return $this->_propDict["customKeyIdentifier"];
            } else {
                $this->_propDict["customKeyIdentifier"] = \AmeliaGuzzleHttp\Psr7\Utils::streamFor($this->_propDict["customKeyIdentifier"]);
                return $this->_propDict["customKeyIdentifier"];
            }
        }
        return null;
    }

    /**
    * Sets the customKeyIdentifier
    * Custom key identifier.
    *
    * @param \AmeliaGuzzleHttp\Psr7\Stream $val The value to assign to the customKeyIdentifier
    *
    * @return SelfSignedCertificate The SelfSignedCertificate
    */
    public function setCustomKeyIdentifier($val)
    {
        $this->_propDict["customKeyIdentifier"] = $val;
         return $this;
    }
    /**
    * Gets the displayName
    * The friendly name for the key.
    *
    * @return string|null The displayName
    */
    public function getDisplayName()
    {
        if (array_key_exists("displayName", $this->_propDict)) {
            return $this->_propDict["displayName"];
        } else {
            return null;
        }
    }

    /**
    * Sets the displayName
    * The friendly name for the key.
    *
    * @param string $val The value of the displayName
    *
    * @return SelfSignedCertificate
    */
    public function setDisplayName($val)
    {
        $this->_propDict["displayName"] = $val;
        return $this;
    }

    /**
    * Gets the endDateTime
    * The date and time at which the credential expires. The Timestamp type represents date and time information using ISO 8601 format and is always in UTC time. For example, midnight UTC on Jan 1, 2014 is 2014-01-01T00:00:00Z.
    *
    * @return \DateTime|null The endDateTime
    */
    public function getEndDateTime()
    {
        if (array_key_exists("endDateTime", $this->_propDict)) {
            if (is_a($this->_propDict["endDateTime"], "\DateTime") || is_null($this->_propDict["endDateTime"])) {
                return $this->_propDict["endDateTime"];
            } else {
                $this->_propDict["endDateTime"] = new \DateTime($this->_propDict["endDateTime"]);
                return $this->_propDict["endDateTime"];
            }
        }
        return null;
    }

    /**
    * Sets the endDateTime
    * The date and time at which the credential expires. The Timestamp type represents date and time information using ISO 8601 format and is always in UTC time. For example, midnight UTC on Jan 1, 2014 is 2014-01-01T00:00:00Z.
    *
    * @param \DateTime $val The value to assign to the endDateTime
    *
    * @return SelfSignedCertificate The SelfSignedCertificate
    */
    public function setEndDateTime($val)
    {
        $this->_propDict["endDateTime"] = $val;
         return $this;
    }

    /**
    * Gets the key
    * The value for the key credential. Should be a base-64 encoded value.
    *
    * @return \AmeliaGuzzleHttp\Psr7\Stream|null The key
    */
    public function getKey()
    {
        if (array_key_exists("key", $this->_propDict)) {
            if (is_a($this->_propDict["key"], "\AmeliaGuzzleHttp\Psr7\Stream") || is_null($this->_propDict["key"])) {
                return $this->_propDict["key"];
            } else {
                $this->_propDict["key"] = \AmeliaGuzzleHttp\Psr7\Utils::streamFor($this->_propDict["key"]);
                return $this->_propDict["key"];
            }
        }
        return null;
    }

    /**
    * Sets the key
    * The value for the key credential. Should be a base-64 encoded value.
    *
    * @param \AmeliaGuzzleHttp\Psr7\Stream $val The value to assign to the key
    *
    * @return SelfSignedCertificate The SelfSignedCertificate
    */
    public function setKey($val)
    {
        $this->_propDict["key"] = $val;
         return $this;
    }
    /**
    * Gets the keyId
    * The unique identifier (GUID) for the key.
    *
    * @return string|null The keyId
    */
    public function getKeyId()
    {
        if (array_key_exists("keyId", $this->_propDict)) {
            return $this->_propDict["keyId"];
        } else {
            return null;
        }
    }

    /**
    * Sets the keyId
    * The unique identifier (GUID) for the key.
    *
    * @param string $val The value of the keyId
    *
    * @return SelfSignedCertificate
    */
    public function setKeyId($val)
    {
        $this->_propDict["keyId"] = $val;
        return $this;
    }

    /**
    * Gets the startDateTime
    * The date and time at which the credential becomes valid. The Timestamp type represents date and time information using ISO 8601 format and is always in UTC time. For example, midnight UTC on Jan 1, 2014 is 2014-01-01T00:00:00Z.
    *
    * @return \DateTime|null The startDateTime
    */
    public function getStartDateTime()
    {
        if (array_key_exists("startDateTime", $this->_propDict)) {
            if (is_a($this->_propDict["startDateTime"], "\DateTime") || is_null($this->_propDict["startDateTime"])) {
                return $this->_propDict["startDateTime"];
            } else {
                $this->_propDict["startDateTime"] = new \DateTime($this->_propDict["startDateTime"]);
                return $this->_propDict["startDateTime"];
            }
        }
        return null;
    }

    /**
    * Sets the startDateTime
    * The date and time at which the credential becomes valid. The Timestamp type represents date and time information using ISO 8601 format and is always in UTC time. For example, midnight UTC on Jan 1, 2014 is 2014-01-01T00:00:00Z.
    *
    * @param \DateTime $val The value to assign to the startDateTime
    *
    * @return SelfSignedCertificate The SelfSignedCertificate
    */
    public function setStartDateTime($val)
    {
        $this->_propDict["startDateTime"] = $val;
         return $this;
    }
    /**
    * Gets the thumbprint
    * The thumbprint value for the key.
    *
    * @return string|null The thumbprint
    */
    public function getThumbprint()
    {
        if (array_key_exists("thumbprint", $this->_propDict)) {
            return $this->_propDict["thumbprint"];
        } else {
            return null;
        }
    }

    /**
    * Sets the thumbprint
    * The thumbprint value for the key.
    *
    * @param string $val The value of the thumbprint
    *
    * @return SelfSignedCertificate
    */
    public function setThumbprint($val)
    {
        $this->_propDict["thumbprint"] = $val;
        return $this;
    }
    /**
    * Gets the type
    * The type of key credential. 'AsymmetricX509Cert'.
    *
    * @return string|null The type
    */
    public function getType()
    {
        if (array_key_exists("type", $this->_propDict)) {
            return $this->_propDict["type"];
        } else {
            return null;
        }
    }

    /**
    * Sets the type
    * The type of key credential. 'AsymmetricX509Cert'.
    *
    * @param string $val The value of the type
    *
    * @return SelfSignedCertificate
    */
    public function setType($val)
    {
        $this->_propDict["type"] = $val;
        return $this;
    }
    /**
    * Gets the usage
    * A string that describes the purpose for which the key can be used. For example, 'Verify'.
    *
    * @return string|null The usage
    */
    public function getUsage()
    {
        if (array_key_exists("usage", $this->_propDict)) {
            return $this->_propDict["usage"];
        } else {
            return null;
        }
    }

    /**
    * Sets the usage
    * A string that describes the purpose for which the key can be used. For example, 'Verify'.
    *
    * @param string $val The value of the usage
    *
    * @return SelfSignedCertificate
    */
    public function setUsage($val)
    {
        $this->_propDict["usage"] = $val;
        return $this;
    }
}
