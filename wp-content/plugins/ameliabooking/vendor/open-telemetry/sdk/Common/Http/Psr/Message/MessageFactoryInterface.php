<?php

declare(strict_types=1);

namespace OpenTelemetry\SDK\Common\Http\Psr\Message;

use AmeliaVendor\Psr\Http\Message\RequestFactoryInterface;
use AmeliaVendor\Psr\Http\Message\ResponseFactoryInterface;
use AmeliaVendor\Psr\Http\Message\ServerRequestFactoryInterface;

interface MessageFactoryInterface extends RequestFactoryInterface, ServerRequestFactoryInterface, ResponseFactoryInterface
{
}
