<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Services\Mobile;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;
use AmeliaBooking\Domain\Entity\Mobile\MobileDevice;
use AmeliaBooking\Domain\Repository\Mobile\MobileDeviceRepositoryInterface;
use AmeliaBooking\Domain\Repository\Mobile\MobileNotificationPrefsRepositoryInterface;
use AmeliaBooking\Domain\Services\Mobile\MobileNotificationPreferences;
use AmeliaBooking\Domain\Services\Mobile\MobilePushGatewayInterface;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Licence\Licence;

/**
 * Class ExpoPushNotificationService
 *
 * @package AmeliaBooking\Application\Services\Mobile
 */
class ExpoPushNotificationService
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
     * @param int                $userId
     * @param string             $category
     * @param string             $title
     * @param string             $body
     * @param array<string, mixed> $data
     *
     * @return bool
     * @throws InvalidArgumentException
     * @throws QueryExecutionException
     */
    public function sendToProvider($userId, $category, $title, $body, array $data = []): bool
    {
        if (!Licence::hasFeatureAccess('mobileApp')) {
            return false;
        }

        if (!in_array($category, array_keys(MobileNotificationPreferences::getDefaults()), true)) {
            throw new InvalidArgumentException("Unknown push notification category '$category'");
        }

        $preferences = $this->getProviderPreferences($userId);

        if (!MobileNotificationPreferences::isCategoryEnabled($preferences, $category)) {
            return false;
        }

        /** @var MobileDeviceRepositoryInterface $deviceRepository */
        $deviceRepository = $this->container->get('domain.mobile.device.repository');
        $devices          = $deviceRepository->getByUserId($userId);

        if ($devices->length() === 0) {
            return false;
        }

        $messages = [];

        /** @var MobileDevice $device */
        foreach ($devices->getItems() as $device) {
            $token = $device->getExpoPushToken()->getValue();

            if ($token === '') {
                continue;
            }

            $messages[] = [
                'to'    => $token,
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
                'data'  => $this->normalizePushData($data),
            ];
        }

        if (empty($messages)) {
            return false;
        }

        /** @var MobilePushGatewayInterface $pushGateway */
        $pushGateway = $this->container->get('domain.mobile.pushGateway');
        $results     = $pushGateway->send($messages);

        $this->removeStaleTokens($pushGateway, $results);

        foreach ($results as $result) {
            if (($result['status'] ?? '') === 'ok') {
                return true;
            }
        }

        return false;
    }

    /**
     * Expo/FCM require string values in the custom data payload.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function normalizePushData(array $data)
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $normalized[$key] = is_scalar($value) ? (string) $value : wp_json_encode($value);
        }

        return $normalized;
    }

    /**
     * @param int $userId
     *
     * @return array<string, bool>
     * @throws QueryExecutionException
     */
    private function getProviderPreferences($userId)
    {
        /** @var MobileNotificationPrefsRepositoryInterface $prefsRepository */
        $prefsRepository = $this->container->get('domain.mobile.notificationPrefs.repository');
        $prefs           = $prefsRepository->getByUserId($userId);

        return $prefs ? $prefs->getPreferencesArray() : MobileNotificationPreferences::getDefaults();
    }

    /**
     * @param MobilePushGatewayInterface         $pushGateway
     * @param array<int, array<string, mixed>> $results
     *
     * @return void
     * @throws QueryExecutionException
     */
    private function removeStaleTokens(MobilePushGatewayInterface $pushGateway, array $results)
    {
        /** @var MobileDeviceRepositoryInterface $deviceRepository */
        $deviceRepository = $this->container->get('domain.mobile.device.repository');

        foreach ($results as $result) {
            if (!$pushGateway->isStaleTokenResult($result)) {
                continue;
            }

            $token = isset($result['token']) ? (string) $result['token'] : '';

            if ($token === '') {
                continue;
            }

            $deviceRepository->deleteByExpoPushToken($token);
        }
    }
}
