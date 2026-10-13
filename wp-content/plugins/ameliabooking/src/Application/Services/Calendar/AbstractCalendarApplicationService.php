<?php

namespace AmeliaBooking\Application\Services\Calendar;

use AmeliaBooking\Infrastructure\Common\Container;

/**
 * Class AbstractCalendarApplicationService
 *
 * @package AmeliaBooking\Application\Services\Calendar
 */
abstract class AbstractCalendarApplicationService
{
    protected $container;

    /**
     * AbstractCalendarApplicationService constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }
}
