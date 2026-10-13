<?php

namespace AmeliaBooking\Infrastructure\Services\Outlook;

use AmeliaHttp\Promise\FulfilledPromise;
use AmeliaHttp\Promise\Promise;
use Microsoft\Kiota\Abstractions\Authentication\AccessTokenProvider;
use Microsoft\Kiota\Abstractions\Authentication\AllowedHostsValidator;

class OutlookGraphAccessTokenProvider implements AccessTokenProvider
{
    private string $accessToken;

    private AllowedHostsValidator $allowedHostsValidator;

    /**
     * @param string $accessToken
     */
    public function __construct($accessToken)
    {
        $this->accessToken = (string)$accessToken;
        $this->allowedHostsValidator = new AllowedHostsValidator(['graph.microsoft.com']);
    }

    /**
     * @param string              $url
     * @param array<string,mixed> $additionalAuthenticationContext
     *
     * @return Promise<string|null>
     */
    public function getAuthorizationTokenAsync(string $url, array $additionalAuthenticationContext = []): Promise
    {
        return new FulfilledPromise($this->accessToken);
    }

    /**
     * @return AllowedHostsValidator
     */
    public function getAllowedHostsValidator(): AllowedHostsValidator
    {
        return $this->allowedHostsValidator;
    }
}
