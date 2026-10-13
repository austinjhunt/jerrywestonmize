<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Entity\Calendar;

use AmeliaBooking\Domain\ValueObjects\Number\Integer\Id;

/**
 * Class CalendarSync
 *
 * The watch on one external calendar: which provider it belongs to, which connection's credentials drive
 * it, and the state the remote side gave us for it.
 *
 * @package AmeliaBooking\Domain\Entity\Calendar
 */
class CalendarSync
{
    public const TYPE_GOOGLE  = 'google';
    public const TYPE_OUTLOOK = 'outlook';

    /** @var Id */
    private $id;

    /** @var string google|outlook */
    private $type;

    /**
     * Mailbox or account the calendar id belongs to. Google calendar ids are globally unique, but Graph
     * ids are scoped to a mailbox, so the account is part of a calendar's identity for both.
     *
     * @var string
     */
    private $accountId;

    /** @var string */
    private $calendarId;

    /**
     * providers_to_*_calendar row whose token registers and renews the watch. Null means the watch runs on
     * the global token from the plugin settings, which is the case for employees connected on the Settings
     * page and therefore having no connection row of their own.
     *
     * @var int|null
     */
    private $ownerConnectionId;

    /** @var string|null Google channel id, or Graph subscription id */
    private $subscriptionId;

    /** @var string|null Google only: the resource the channel watches */
    private $resourceId;

    /** @var string|null Outlook only: secret echoed back on each notification */
    private $clientState;

    /** @var string|null Google only: cursor over the calendar's change feed */
    private $syncToken;

    /** @var string|null 'Y-m-d H:i:s' in UTC */
    private $expiresAt;

    /**
     * @param string $type
     * @param string $accountId
     * @param string $calendarId
     */
    public function __construct($type, $accountId, $calendarId)
    {
        $this->type       = $type;
        $this->accountId  = $accountId;
        $this->calendarId = $calendarId;
    }

    /**
     * @return Id|null
     */
    public function getId(): ?Id
    {
        return $this->id;
    }

    /**
     * @param Id $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getAccountId(): string
    {
        return $this->accountId;
    }

    /**
     * @return string
     */
    public function getCalendarId(): string
    {
        return $this->calendarId;
    }

    /**
     * @param string $calendarId
     */
    public function setCalendarId($calendarId): void
    {
        $this->calendarId = $calendarId;
    }

    /**
     * @return int|null
     */
    public function getOwnerConnectionId(): ?int
    {
        return $this->ownerConnectionId;
    }

    /**
     * @param int|null $ownerConnectionId
     */
    public function setOwnerConnectionId($ownerConnectionId): void
    {
        $this->ownerConnectionId = $ownerConnectionId !== null ? (int)$ownerConnectionId : null;
    }

    /**
     * @return string|null
     */
    public function getSubscriptionId(): ?string
    {
        return $this->subscriptionId;
    }

    /**
     * @param string|null $subscriptionId
     */
    public function setSubscriptionId($subscriptionId): void
    {
        $this->subscriptionId = $subscriptionId;
    }

    /**
     * @return string|null
     */
    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    /**
     * @param string|null $resourceId
     */
    public function setResourceId($resourceId): void
    {
        $this->resourceId = $resourceId;
    }

    /**
     * @return string|null
     */
    public function getClientState(): ?string
    {
        return $this->clientState;
    }

    /**
     * @param string|null $clientState
     */
    public function setClientState($clientState): void
    {
        $this->clientState = $clientState;
    }

    /**
     * @return string|null
     */
    public function getSyncToken(): ?string
    {
        return $this->syncToken;
    }

    /**
     * @param string|null $syncToken
     */
    public function setSyncToken($syncToken): void
    {
        $this->syncToken = $syncToken;
    }

    /**
     * @return string|null
     */
    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    /**
     * @param string|null $expiresAt
     */
    public function setExpiresAt($expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    /**
     * Whether a watch is registered and still has time left on it.
     *
     * @param int $marginSeconds treat a watch expiring within this window as needing renewal
     *
     * @return bool
     */
    public function hasLiveSubscription($marginSeconds = 0): bool
    {
        if (!$this->subscriptionId || !$this->expiresAt) {
            return false;
        }

        $expiresAt = strtotime($this->expiresAt . ' UTC');

        if (!$expiresAt) {
            return false;
        }

        return $expiresAt - $marginSeconds > time();
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'                => null !== $this->getId() ? $this->getId()->getValue() : null,
            'type'              => $this->getType(),
            'accountId'         => $this->getAccountId(),
            'calendarId'        => $this->getCalendarId(),
            'ownerConnectionId' => $this->getOwnerConnectionId(),
            'subscriptionId'    => $this->getSubscriptionId(),
            'resourceId'        => $this->getResourceId(),
            'clientState'       => $this->getClientState(),
            'syncToken'         => $this->getSyncToken(),
            'expiresAt'         => $this->getExpiresAt(),
        ];
    }
}
