<?php
/**
 * @copyright © TMS-Plugins. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Infrastructure\Services\Payment;

use AmeliaBooking\Domain\Services\Payment\AbstractPaymentService;
use AmeliaBooking\Domain\Services\Payment\PaymentServiceInterface;
use Exception;

/**
 * Class StarterPaymentService
 */
class StarterPaymentService extends AbstractPaymentService implements PaymentServiceInterface
{
    /**
     * @param array $data
     *
     * @return array
     * @throws Exception
     */
    public function execute($data)
    {
        return [];
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
    public function complete($data)
    {
        return null;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function getPaymentLink($data)
    {
        return [];
    }

    /**
     * @param array $data
     *
     * @return array
     * @throws Exception
     */
    public function refund($data)
    {
        return [];
    }

    /**
     * @param string $sessionId
     *
     * @return string|null
     */
    public function getPaymentIntent($sessionId)
    {
        return null;
    }

    /**
     * @return mixed|null
     */
    public function getTransactionAmount($id)
    {
        return null;
    }
}
