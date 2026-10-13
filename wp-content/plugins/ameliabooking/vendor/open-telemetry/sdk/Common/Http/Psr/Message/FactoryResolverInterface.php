<?php

declare(strict_types=1);

namespace OpenTelemetry\SDK\Common\Http\Psr\Message;

use AmeliaVendor\Psr\Http\Message\RequestFactoryInterface;
use AmeliaVendor\Psr\Http\Message\ResponseFactoryInterface;
use AmeliaVendor\Psr\Http\Message\ServerRequestFactoryInterface;
use AmeliaVendor\Psr\Http\Message\StreamFactoryInterface;
use AmeliaVendor\Psr\Http\Message\UploadedFileFactoryInterface;
use AmeliaVendor\Psr\Http\Message\UriFactoryInterface;

interface FactoryResolverInterface
{
    public function resolveRequestFactory(): RequestFactoryInterface;
    public function resolveResponseFactory(): ResponseFactoryInterface;
    public function resolveServerRequestFactory(): ServerRequestFactoryInterface;
    public function resolveStreamFactory(): StreamFactoryInterface;
    public function resolveUploadedFileFactory(): UploadedFileFactoryInterface;
    public function resolveUriFactory(): UriFactoryInterface;
}
