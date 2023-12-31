<?php
/**
 * @copyright © TMS-Plugins. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\Services\Payment;

/**
 * Interface PaymentServiceInterface
 *
 * @package AmeliaBooking\Domain\Services\Payment
 */
interface PaymentServiceInterface
{
    /**
     * @param array $data
     *
     * @return mixed
     */
    public function execute($data);

    /**
     * @param array $data
     *
     * @return array
     */
    public function getPaymentLink($data);

    /**
     * @param array $data
     *
     * @return array
     */
    public function refund($data);

    /**
     * @param string $id
     *
     * @return mixed
     */
    public function getTransactionAmount($id);
}
