<?php

namespace AmeliaBooking\Application\Services\Stripe;

use AmeliaBooking\Infrastructure\Common\Container;

/**
 * Class AbstractStripeApplicationService
 *
 * @package AmeliaBooking\Application\Services\Stripe
 */
abstract class AbstractStripeApplicationService
{
    protected $container;

    /**
     * AbstractStripeApplicationService constructor.
     *
     * @param Container $container
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @return void
     */
    abstract public function reconcilePendingStripePayments(): void;
}
