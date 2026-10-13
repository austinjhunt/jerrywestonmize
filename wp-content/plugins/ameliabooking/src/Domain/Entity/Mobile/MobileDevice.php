<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Entity\Mobile;

use AmeliaBooking\Domain\ValueObjects\DateTime\DateTimeValue;
use AmeliaBooking\Domain\ValueObjects\Mobile\MobilePlatform;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Domain\ValueObjects\String\Name;

/**
 * Class MobileDevice
 *
 * @package AmeliaBooking\Domain\Entity\Mobile
 */
class MobileDevice
{
    /** @var Id */
    private $id;

    /** @var Id */
    private $userId;

    /** @var Name */
    private $expoPushToken;

    /** @var MobilePlatform */
    private $platform;

    /** @var Name|null */
    private $appVersion;

    /** @var DateTimeValue */
    private $lastSeen;

    /** @var DateTimeValue */
    private $createdAt;

    /**
     * @param Id             $userId
     * @param Name           $expoPushToken
     * @param MobilePlatform $platform
     */
    public function __construct(
        Id $userId,
        Name $expoPushToken,
        MobilePlatform $platform
    ) {
        $this->userId        = $userId;
        $this->expoPushToken = $expoPushToken;
        $this->platform      = $platform;
    }

    /**
     * @return Id|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param Id $id
     */
    public function setId(Id $id)
    {
        $this->id = $id;
    }

    /**
     * @return Id
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param Id $userId
     */
    public function setUserId(Id $userId)
    {
        $this->userId = $userId;
    }

    /**
     * @return Name
     */
    public function getExpoPushToken()
    {
        return $this->expoPushToken;
    }

    /**
     * @param Name $expoPushToken
     */
    public function setExpoPushToken(Name $expoPushToken)
    {
        $this->expoPushToken = $expoPushToken;
    }

    /**
     * @return MobilePlatform
     */
    public function getPlatform()
    {
        return $this->platform;
    }

    /**
     * @param MobilePlatform $platform
     */
    public function setPlatform(MobilePlatform $platform)
    {
        $this->platform = $platform;
    }

    /**
     * @return Name|null
     */
    public function getAppVersion()
    {
        return $this->appVersion;
    }

    /**
     * @param Name|null $appVersion
     */
    public function setAppVersion($appVersion)
    {
        $this->appVersion = $appVersion;
    }

    /**
     * @return DateTimeValue|null
     */
    public function getLastSeen()
    {
        return $this->lastSeen;
    }

    /**
     * @param DateTimeValue $lastSeen
     */
    public function setLastSeen(DateTimeValue $lastSeen)
    {
        $this->lastSeen = $lastSeen;
    }

    /**
     * @return DateTimeValue|null
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param DateTimeValue $createdAt
     */
    public function setCreatedAt(DateTimeValue $createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id'            => $this->getId() ? $this->getId()->getValue() : null,
            'userId'        => $this->getUserId()->getValue(),
            'expoPushToken' => $this->getExpoPushToken()->getValue(),
            'platform'      => $this->getPlatform()->getValue(),
            'appVersion'    => $this->getAppVersion() ? $this->getAppVersion()->getValue() : null,
            'lastSeen'      => $this->getLastSeen()
                ? $this->getLastSeen()->getValue()->format('Y-m-d H:i:s')
                : null,
            'createdAt'     => $this->getCreatedAt()
                ? $this->getCreatedAt()->getValue()->format('Y-m-d H:i:s')
                : null,
        ];
    }
}
