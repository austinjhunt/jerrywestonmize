<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Entity\Mobile;

use AmeliaBooking\Domain\Services\Mobile\MobileNotificationPreferences;
use AmeliaBooking\Domain\ValueObjects\Json;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;

/**
 * Class MobileNotificationPrefs
 *
 * @package AmeliaBooking\Domain\Entity\Mobile
 */
class MobileNotificationPrefs
{
    /** @var Id */
    private $id;

    /** @var Id */
    private $userId;

    /** @var Json */
    private $preferences;

    /**
     * @param Id   $userId
     * @param Json $preferences
     */
    public function __construct(
        Id $userId,
        Json $preferences
    ) {
        $this->userId       = $userId;
        $this->preferences  = $preferences;
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
     * @return Json
     */
    public function getPreferences()
    {
        return $this->preferences;
    }

    /**
     * @param Json $preferences
     */
    public function setPreferences(Json $preferences)
    {
        $this->preferences = $preferences;
    }

    /**
     * @return array<string, bool|int>
     */
    public function getPreferencesArray()
    {
        $decoded = json_decode($this->getPreferences()->getValue(), true);

        if (!is_array($decoded)) {
            return MobileNotificationPreferences::getDefaults();
        }

        return MobileNotificationPreferences::mergeWithDefaults($decoded);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id'          => $this->getId() ? $this->getId()->getValue() : null,
            'userId'      => $this->getUserId()->getValue(),
            'preferences' => $this->getPreferences()->getValue(),
        ];
    }
}
