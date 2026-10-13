<?php

namespace AmeliaBooking\Domain\Entity\Google;

use AmeliaBooking\Domain\ValueObjects\DateTime\DateTimeValue;
use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;
use AmeliaBooking\Domain\ValueObjects\String\Name;
use AmeliaBooking\Domain\ValueObjects\String\Token;

/**
 * Class GoogleCalendar
 *
 * @package AmeliaBooking\Domain\Entity\Google
 */
class GoogleCalendar
{
    /** @var Id */
    private $id;

    /** @var Token */
    private $token;

    /** @var Name */
    private $calendarId;

    /** @var bool */
    private $insertPendingAppointments;

    /** @var bool */
    private $includeBufferTime;

    /** @var array */
    private $title;

    /** @var array */
    private $description;

    /** @var Id */
    private $userId;

    /** @var string|null Google account this calendar id belongs to */
    private $accountId;

    /** @var Id|null calendar_sync row that owns the watch for this calendar */
    private $syncId;

    /**
     * GoogleCalendar constructor.
     *
     * @param Token $token
     * @param Name $calendarId
     */
    public function __construct(
        Token $token,
        Name $calendarId
    ) {
        $this->token      = $token;
        $this->calendarId = $calendarId;
        $this->insertPendingAppointments = false;
        $this->includeBufferTime = false;
        $this->title = ['appointment' => '', 'event' => ''];
        $this->description = ['appointment' => '', 'event' => ''];
    }

    /**
     * @return Id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param Id $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return Token
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param Token $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @return Name
     */
    public function getCalendarId()
    {
        return $this->calendarId;
    }

    /**
     * @param Name $calendarId
     */
    public function setCalendarId($calendarId)
    {
        $this->calendarId = $calendarId;
    }

    /**
     * @return bool
     */
    public function getInsertPendingAppointments()
    {
        return $this->insertPendingAppointments;
    }

    /**
     * @param bool $insertPendingAppointments
     */
    public function setInsertPendingAppointments($insertPendingAppointments)
    {
        $this->insertPendingAppointments = $insertPendingAppointments;
    }

    /**
     * @return bool
     */
    public function getIncludeBufferTime()
    {
        return $this->includeBufferTime;
    }

    /**
     * @param bool $includeBufferTime
     */
    public function setIncludeBufferTime($includeBufferTime)
    {
        $this->includeBufferTime = $includeBufferTime;
    }

    /**
     * @return array
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param array $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return array
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param array $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
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
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * @return string|null
     */
    public function getAccountId()
    {
        return $this->accountId;
    }

    /**
     * @param string|null $accountId
     */
    public function setAccountId($accountId)
    {
        $this->accountId = $accountId;
    }

    /**
     * @return Id|null
     */
    public function getSyncId()
    {
        return $this->syncId;
    }

    /**
     * @param Id|null $syncId
     */
    public function setSyncId($syncId)
    {
        $this->syncId = $syncId;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'id'                         => null !== $this->getId() ? $this->getId()->getValue() : null,
            'token'                      => $this->getToken()->getValue(),
            'calendarId'                 => null !== $this->getCalendarId() ? $this->getCalendarId()->getValue() : null,
            'insertPendingAppointments'  => $this->getInsertPendingAppointments(),
            'includeBufferTime'          => $this->getIncludeBufferTime(),
            'title'                      => $this->getTitle(),
            'description'                => $this->getDescription(),
            'userId'     => null !== $this->getUserId() ? $this->getUserId()->getValue() : null,
            'accountId'  => $this->getAccountId(),
            'syncId'     => null !== $this->getSyncId() ? $this->getSyncId()->getValue() : null,
        ];
    }
}
