<?php

declare(strict_types=1);

namespace OpenTelemetry\SDK\Common\Http\Psr\Message;

use AmeliaVendor\Psr\Http\Message\RequestFactoryInterface;
use AmeliaVendor\Psr\Http\Message\RequestInterface;
use AmeliaVendor\Psr\Http\Message\ResponseFactoryInterface;
use AmeliaVendor\Psr\Http\Message\ResponseInterface;
use AmeliaVendor\Psr\Http\Message\ServerRequestFactoryInterface;
use AmeliaVendor\Psr\Http\Message\ServerRequestInterface;

final class MessageFactory implements MessageFactoryInterface
{
    private RequestFactoryInterface $requestFactory;
    private ResponseFactoryInterface $responseFactory;
    private ServerRequestFactoryInterface $serverRequestFactory;

    public function __construct(
        RequestFactoryInterface $requestFactory,
        ResponseFactoryInterface $responseFactory,
        ServerRequestFactoryInterface $serverRequestFactory
    ) {
        $this->requestFactory = $requestFactory;
        $this->responseFactory = $responseFactory;
        $this->serverRequestFactory = $serverRequestFactory;
    }

    public static function create(
        RequestFactoryInterface $requestFactory,
        ResponseFactoryInterface $responseFactory,
        ServerRequestFactoryInterface $serverRequestFactory
    ): self {
        return new self($requestFactory, $responseFactory, $serverRequestFactory);
    }

    public function createRequest(string $method, $uri): RequestInterface
    {
        return $this->requestFactory->createRequest($method, $uri);
    }

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return $this->responseFactory->createResponse($code, $reasonPhrase);
    }

    public function createServerRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return $this->serverRequestFactory->createServerRequest($method, $uri, $serverParams);
    }
}
