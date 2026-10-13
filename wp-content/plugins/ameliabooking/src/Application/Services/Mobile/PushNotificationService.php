<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Services\Mobile;

use AmeliaBooking\Application\Commands\Command;
use AmeliaBooking\Application\Services\User\UserApplicationService;
use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Booking\Appointment\Appointment;
use AmeliaBooking\Domain\Entity\Entities;
use AmeliaBooking\Domain\Entity\Mobile\MobileNotificationPrefs;
use AmeliaBooking\Domain\Entity\User\AbstractUser;
use AmeliaBooking\Domain\Repository\Booking\Appointment\AppointmentRepositoryInterface;
use AmeliaBooking\Domain\Repository\Mobile\MobileDeviceRepositoryInterface;
use AmeliaBooking\Domain\Repository\Mobile\MobileNotificationPrefsRepositoryInterface;
use AmeliaBooking\Domain\Repository\Mobile\MobilePushReminderLogRepositoryInterface;
use AmeliaBooking\Domain\Services\DateTime\DateTimeService;
use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\Services\Mobile\MobileNotificationPreferences;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Domain\ValueObjects\String\BookingStatus;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\WP\Translations\MobilePushNotificationStrings;

/**
 * Class PushNotificationService
 *
 * @package AmeliaBooking\Application\Services\Mobile
 */
class PushNotificationService
{
    /** @var Container */
    private $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $status
     *
     * @return string|null
     */
    public static function resolveStatusCategory($status): ?string
    {
        if (
            in_array(
                $status,
                [
                BookingStatus::CANCELED,
                BookingStatus::REJECTED,
                BookingStatus::APPROVED,
                BookingStatus::PENDING,
                ],
                true
            )
        ) {
            return MobileNotificationPreferences::CATEGORY_STATUS_CHANGES;
        }

        return null;
    }

    /**
     * Skip new-booking, status, and change pushes when the acting employee is the recipient.
     * Reminders still send — they are not triggered by the employee's own action.
     *
     * @param string   $category
     * @param int|null $providerUserId
     * @param int|null $actingProviderId
     *
     * @return bool
     */
    public static function shouldSkipActorNotification($category, ?int $providerUserId, ?int $actingProviderId): bool
    {
        if ($providerUserId === null || $actingProviderId === null || $providerUserId !== $actingProviderId) {
            return false;
        }

        return in_array(
            $category,
            [
                MobileNotificationPreferences::CATEGORY_NEW_BOOKINGS,
                MobileNotificationPreferences::CATEGORY_STATUS_CHANGES,
                MobileNotificationPreferences::CATEGORY_CHANGES,
            ],
            true
        );
    }

    /**
     * @param Appointment $appointment
     * @param string      $category
     *
     * @return void
     */
    public function sendFromEntity(Appointment $appointment, $category): void
    {
        $this->sendFromArray($appointment->toArray(), $category);
    }

    /**
     * Send upcoming-appointment reminders based on each provider's reminder_time_before preference.
     *
     * @return void
     */
    public function sendScheduledReminders(): void
    {
        try {
            $this->sendScheduledRemindersInternal();
        } catch (\Throwable $e) {
            $this->logThrowable('Failed to process scheduled push reminders', $e);
        }
    }

    /**
     * @param \DateTime      $bookingStart
     * @param int            $timeBeforeSeconds
     * @param \DateTime      $now
     * @param \DateTime|null $bookingCreated
     *
     * @return bool
     */
    public static function isWithinReminderWindow(
        \DateTime $bookingStart,
        int $timeBeforeSeconds,
        \DateTime $now,
        ?\DateTime $bookingCreated = null
    ): bool {
        if ($timeBeforeSeconds <= 0) {
            return false;
        }

        $windowStart = clone $bookingStart;
        $windowStart->modify('-' . $timeBeforeSeconds . ' seconds');

        if ($now < $windowStart || $now >= $bookingStart) {
            return false;
        }

        if ($bookingCreated !== null) {
            $createdPlusOffset = clone $bookingCreated;
            $createdPlusOffset->modify('+' . $timeBeforeSeconds . ' seconds');

            if ($bookingStart <= $createdPlusOffset) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed>      $appointmentArray
     * @param array<string, mixed>|null $bookingArray
     *
     * @return void
     */
    public function sendStatusChange(array $appointmentArray, ?array $bookingArray = null): void
    {
        $status   = $bookingArray['status'] ?? $appointmentArray['status'] ?? null;
        $category = self::resolveStatusCategory($status);

        if ($category === null) {
            return;
        }

        $this->sendFromArray($appointmentArray, $category, $bookingArray);
    }

    /**
     * @param array<string, mixed>      $appointmentArray
     * @param string                    $category
     * @param array<string, mixed>|null $bookingArray
     *
     * @return bool
     */
    public function sendFromArray(array $appointmentArray, $category, ?array $bookingArray = null): bool
    {
        if (empty($appointmentArray['type']) || $appointmentArray['type'] !== Entities::APPOINTMENT) {
            return false;
        }

        try {
            return $this->sendToProvider($appointmentArray, $category, $bookingArray);
        } catch (\Throwable $e) {
            $this->logThrowable('Failed to send push notification', $e);

            return false;
        }
    }

    /**
     * @param array<string, mixed>      $appointmentArray
     * @param string                    $category
     * @param array<string, mixed>|null $bookingArray
     *
     * @return bool
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    private function sendToProvider(array $appointmentArray, $category, ?array $bookingArray = null): bool
    {
        $providerUserId = $this->getProviderUserId($appointmentArray);

        if ($providerUserId === null) {
            return false;
        }

        if (self::shouldSkipActorNotification($category, $providerUserId, $this->getActingProviderId())) {
            return false;
        }

        list($title, $body) = $this->buildMessage($category, $appointmentArray, $bookingArray);

        /** @var ExpoPushNotificationService $pushService */
        $pushService = $this->container->get('application.mobile.expoPushNotification.service');

        $bookingStart = $this->resolveBookingStart($appointmentArray);
        $appointmentId = (int) $appointmentArray['id'];

        return $pushService->sendToProvider(
            $providerUserId,
            $category,
            $title,
            $body,
            [
                'type'          => Entities::APPOINTMENT,
                'appointmentId' => $appointmentId,
                'start'         => $bookingStart ?? '',
                'url'           => '/appointments/' . $appointmentId,
            ]
        );
    }

    /**
     * @param array<string, mixed> $appointmentArray
     *
     * @return int|null
     */
    private function getProviderUserId(array $appointmentArray)
    {
        if (!empty($appointmentArray['providerId'])) {
            return (int) $appointmentArray['providerId'];
        }

        if (!empty($appointmentArray['provider']['id'])) {
            return (int) $appointmentArray['provider']['id'];
        }

        return null;
    }

    /**
     * @return int|null
     */
    private function getActingProviderId(): ?int
    {
        try {
            /** @var UserApplicationService $userAS */
            $userAS = $this->container->get('application.user.service');
            $request = $this->container->get('request');
            $user = $userAS->authorization(
                Command::getCabinetToken(
                    $request->getHeaderLine('Authorization'),
                    $request->getCookieParams()
                ),
                'provider'
            );
        } catch (\Throwable $e) {
            return null;
        }

        if (
            $user instanceof AbstractUser
            && $user->getType() === AbstractUser::USER_ROLE_PROVIDER
            && $user->getId() !== null
        ) {
            return (int) $user->getId()->getValue();
        }

        return null;
    }

    /**
     * @param string                    $category
     * @param array<string, mixed>      $appointmentArray
     * @param array<string, mixed>|null $bookingArray
     *
     * @return array{0: string, 1: string}
     */
    private function buildMessage($category, array $appointmentArray, ?array $bookingArray = null)
    {
        $status = $appointmentArray['status'] ?? null;

        if (
            $category === MobileNotificationPreferences::CATEGORY_STATUS_CHANGES
            && $bookingArray !== null
            && !empty($bookingArray['status'])
        ) {
            $status = $bookingArray['status'];
        }

        return MobilePushNotificationStrings::getMessage(
            $category,
            $this->getCustomerName($appointmentArray, $bookingArray),
            $this->getServiceName($appointmentArray),
            $this->formatStart($appointmentArray),
            $status
        );
    }

    /**
     * @param array<string, mixed>      $appointmentArray
     * @param array<string, mixed>|null $bookingArray
     *
     * @return string
     */
    private function getCustomerName(array $appointmentArray, ?array $bookingArray = null)
    {
        if ($bookingArray !== null) {
            if (!empty($bookingArray['customer']['firstName']) || !empty($bookingArray['customer']['lastName'])) {
                return trim(
                    ($bookingArray['customer']['firstName'] ?? '') . ' ' . ($bookingArray['customer']['lastName'] ?? '')
                );
            }
        }

        if (!empty($appointmentArray['bookings']) && is_array($appointmentArray['bookings'])) {
            $booking = $appointmentArray['bookings'][count($appointmentArray['bookings']) - 1];

            if (!empty($booking['customer']['firstName']) || !empty($booking['customer']['lastName'])) {
                return trim(
                    ($booking['customer']['firstName'] ?? '') . ' ' . ($booking['customer']['lastName'] ?? '')
                );
            }
        }

        return MobilePushNotificationStrings::customerFallback();
    }

    /**
     * @param array<string, mixed> $appointmentArray
     *
     * @return string
     */
    private function getServiceName(array $appointmentArray)
    {
        if (!empty($appointmentArray['service']['name'])) {
            return (string) $appointmentArray['service']['name'];
        }

        return MobilePushNotificationStrings::serviceFallback();
    }

    /**
     * @param array<string, mixed> $appointmentArray
     *
     * @return string
     */
    private function resolveBookingStart(array $appointmentArray): ?string
    {
        if (!empty($appointmentArray['bookingStart'])) {
            return (string) $appointmentArray['bookingStart'];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $appointmentArray
     *
     * @return string
     */
    private function formatStart(array $appointmentArray): string
    {
        $bookingStart = $this->resolveBookingStart($appointmentArray);

        if ($bookingStart === null) {
            return '';
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $dateFormat = $settingsService->getSetting('wordpress', 'dateFormat');
        $timeFormat = $settingsService->getSetting('wordpress', 'timeFormat');
        $timestamp  = DateTimeService::getCustomDateTimeObject($bookingStart)->getTimestamp();
        $timeZone   = DateTimeService::getTimeZone();
        $format     = $dateFormat . ' ' . $timeFormat;

        if (function_exists('wp_date')) {
            return wp_date($format, $timestamp, $timeZone);
        }

        return DateTimeService::getCustomDateTimeObject($bookingStart)->format($format);
    }

    /**
     * @param int $appointmentId
     *
     * @return void
     */
    public function invalidateScheduledReminders(int $appointmentId): void
    {
        try {
            /** @var MobilePushReminderLogRepositoryInterface $logRepository */
            $logRepository = $this->container->get('domain.mobile.pushReminderLog.repository');
            $logRepository->deleteByAppointmentId($appointmentId);
        } catch (\Throwable $e) {
            $this->logThrowable('Failed to invalidate scheduled push reminders', $e);
        }
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    private function sendScheduledRemindersInternal(): void
    {
        /** @var MobileDeviceRepositoryInterface $deviceRepository */
        $deviceRepository = $this->container->get('domain.mobile.device.repository');
        $providerIds = $deviceRepository->getUserIdsWithPushTokens();

        if (empty($providerIds)) {
            return;
        }

        /** @var MobileNotificationPrefsRepositoryInterface $prefsRepository */
        $prefsRepository = $this->container->get('domain.mobile.notificationPrefs.repository');
        $prefsByUserId = $prefsRepository->getByUserIds($providerIds);

        $timeBeforeByProvider = [];
        $maxTimeBefore = 0;

        foreach ($providerIds as $providerId) {
            $preferences = $this->resolvePreferences($prefsByUserId, $providerId);

            if (!MobileNotificationPreferences::isCategoryEnabled($preferences, MobileNotificationPreferences::CATEGORY_REMINDERS)) {
                continue;
            }

            $timeBefore = MobileNotificationPreferences::getReminderTimeBefore($preferences);
            $timeBeforeByProvider[$providerId] = $timeBefore;
            $maxTimeBefore = max($maxTimeBefore, $timeBefore);
        }

        if ($maxTimeBefore <= 0 || empty($timeBeforeByProvider)) {
            return;
        }

        /** @var SettingsService $settingsService */
        $settingsService = $this->container->get('domain.settings.service');

        $statuses = [BookingStatus::APPROVED];

        if ($settingsService->getSetting('notifications', 'pendingReminder')) {
            $statuses[] = BookingStatus::PENDING;
        }

        $nowUtc = DateTimeService::getNowDateTimeObjectInUtc();
        $endUtc = clone $nowUtc;
        $endUtc->modify('+' . $maxTimeBefore . ' seconds');

        /** @var AppointmentRepositoryInterface $appointmentRepository */
        $appointmentRepository = $this->container->get('domain.booking.appointment.repository');
        $appointments = $appointmentRepository->getUpcomingForPushReminders(
            array_keys($timeBeforeByProvider),
            $nowUtc->format('Y-m-d H:i:s'),
            $endUtc->format('Y-m-d H:i:s'),
            $statuses
        );

        if (empty($appointments)) {
            return;
        }

        $appointmentIds = [];

        foreach ($appointments as $appointmentArray) {
            $appointmentIds[] = (int) $appointmentArray['id'];
        }

        /** @var MobilePushReminderLogRepositoryInterface $logRepository */
        $logRepository = $this->container->get('domain.mobile.pushReminderLog.repository');
        $sentAppointmentIds = $logRepository->getSentAppointmentIds($appointmentIds);
        $now = DateTimeService::getNowDateTimeObject();

        foreach ($appointments as $appointmentArray) {
            $providerId = (int) $appointmentArray['providerId'];
            $appointmentId = (int) $appointmentArray['id'];

            if (!isset($timeBeforeByProvider[$providerId]) || in_array($appointmentId, $sentAppointmentIds, true)) {
                continue;
            }

            $bookingStart = DateTimeService::getCustomDateTimeObject($appointmentArray['bookingStart']);
            $bookingCreated = $this->resolveLatestBookingCreated($appointmentArray);

            if (
                !self::isWithinReminderWindow(
                    $bookingStart,
                    $timeBeforeByProvider[$providerId],
                    $now,
                    $bookingCreated
                )
            ) {
                continue;
            }

            if (!$logRepository->claim($providerId, $appointmentId)) {
                continue;
            }

            $sent = $this->sendFromArray(
                $appointmentArray,
                MobileNotificationPreferences::CATEGORY_REMINDERS
            );

            if (!$sent) {
                $logRepository->release($providerId, $appointmentId);
            }
        }
    }

    /**
     * @param array<int, MobileNotificationPrefs> $prefsByUserId
     * @param int                                 $providerId
     *
     * @return array<string, bool|int>
     */
    private function resolvePreferences(array $prefsByUserId, int $providerId): array
    {
        if (isset($prefsByUserId[$providerId])) {
            return $prefsByUserId[$providerId]->getPreferencesArray();
        }

        return MobileNotificationPreferences::normalize([]);
    }

    /**
     * @param array<string, mixed> $appointmentArray
     *
     * @return \DateTime|null
     */
    private function resolveLatestBookingCreated(array $appointmentArray): ?\DateTime
    {
        if (empty($appointmentArray['bookings']) || !is_array($appointmentArray['bookings'])) {
            return null;
        }

        $created = null;

        foreach ($appointmentArray['bookings'] as $booking) {
            if (empty($booking['created'])) {
                continue;
            }

            $candidate = DateTimeService::getCustomDateTimeObject($booking['created']);

            if ($created === null || $candidate > $created) {
                $created = $candidate;
            }
        }

        return $created;
    }

    /**
     * @param string     $message
     * @param \Throwable $exception
     *
     * @return void
     */
    private function logThrowable(string $message, \Throwable $exception): void
    {
        try {
            $this->container->getLoggerService()
                ->channel(LoggerInterface::CHANNEL_NOTIFICATION)
                ->error(
                    $message,
                    [
                        'exception' => $exception->getMessage(),
                        'file'      => $exception->getFile(),
                        'line'      => $exception->getLine(),
                    ]
                );
        } catch (\Throwable $ignored) {
        }
    }
}
