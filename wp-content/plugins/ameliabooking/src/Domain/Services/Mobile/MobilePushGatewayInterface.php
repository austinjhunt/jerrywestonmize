<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Services\Mobile;

/**
 * Port for delivering mobile push notifications to registered devices.
 *
 * @package AmeliaBooking\Domain\Services\Mobile
 */
interface MobilePushGatewayInterface
{
    /**
     * @param array<int, array<string, mixed>> $messages
     *
     * @return array<int, array<string, mixed>>
     */
    public function send(array $messages);

    /**
     * @param array<string, mixed> $result
     *
     * @return bool
     */
    public function isStaleTokenResult(array $result);
}
