<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Services\Payment;

use AmeliaBooking\Domain\Services\Logger\LoggerInterface;
use AmeliaBooking\Domain\Services\Settings\SettingsService;
use AmeliaBooking\Infrastructure\Services\Payment\CurrencyService;

/**
 * Class AbstractPaymentService
 *
 * @package AmeliaBooking\Domain\Services\Payment
 */
class AbstractPaymentService
{
    /**
     * @var SettingsService $settingsService
     */
    protected $settingsService;

    /**
     * @var CurrencyService $currencyService
     */
    protected $currencyService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * AbstractPaymentService constructor.
     *
     * @param SettingsService $settingsService
     * @param CurrencyService $currencyService
     * @param LoggerInterface $logger
     */
    public function __construct(
        SettingsService $settingsService,
        CurrencyService $currencyService,
        LoggerInterface $logger
    ) {
        $this->settingsService = $settingsService;
        $this->currencyService = $currencyService;
        $this->logger          = $logger->channel(LoggerInterface::CHANNEL_PAYMENT);
    }

    /**
     * @param array $data
     *
     * @return mixed|null
     */
    public function complete($data)
    {
        return null;
    }

    /**
     * @param string $sessionId
     * @param string $method
     * @param string $accountId
     *
     * @return array|null
     */
    public function getPaymentIntent($sessionId, $method, $accountId)
    {
        return null;
    }
}
