<?php

// File generated from our OpenAPI spec

namespace AmeliaStripe\Service\TestHelpers;

/**
 * @phpstan-import-type RequestOptionsArray from \AmeliaStripe\Util\RequestOptions
 *
 * @psalm-import-type RequestOptionsArray from \AmeliaStripe\Util\RequestOptions
 */
class CustomerService extends \AmeliaStripe\Service\AbstractService
{
    /**
     * Create an incoming testmode bank transfer.
     *
     * @param string $id
     * @param null|array{amount: int, currency: string, expand?: string[], reference?: string} $params
     * @param null|RequestOptionsArray|\AmeliaStripe\Util\RequestOptions $opts
     *
     * @return \AmeliaStripe\CustomerCashBalanceTransaction
     *
     * @throws \AmeliaStripe\Exception\ApiErrorException if the request fails
     */
    public function fundCashBalance($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/test_helpers/customers/%s/fund_cash_balance', $id), $params, $opts);
    }
}
