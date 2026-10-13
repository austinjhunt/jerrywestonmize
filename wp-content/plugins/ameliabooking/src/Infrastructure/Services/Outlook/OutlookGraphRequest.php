<?php

namespace AmeliaBooking\Infrastructure\Services\Outlook;

use Microsoft\Graph\Generated\Models\Calendar;
use Microsoft\Graph\Generated\Models\CalendarCollectionResponse;
use Microsoft\Graph\Generated\Models\Event;
use Microsoft\Graph\Generated\Models\EventCollectionResponse;
use Microsoft\Graph\Generated\Models\Subscription;
use Microsoft\Graph\Generated\Models\SubscriptionCollectionResponse;
use Microsoft\Graph\Generated\Models\User;
use Microsoft\Graph\GraphRequestAdapter;
use Microsoft\Kiota\Abstractions\RequestInformation;
use Microsoft\Kiota\Abstractions\Serialization\Parsable;

class OutlookGraphRequest
{
    /** @var GraphRequestAdapter */
    private $requestAdapter;

    /** @var string */
    private $method;

    /** @var string */
    private $endpoint;

    /** @var mixed */
    private $body;

    /** @var string|null */
    private $returnType;

    /** @var int|null */
    private $pageSize;

    /** @var array<string,string> */
    private $headers = [];

    /**
     * @param GraphRequestAdapter $requestAdapter
     * @param string $method
     * @param string $endpoint
     */
    public function __construct(GraphRequestAdapter $requestAdapter, $method, $endpoint)
    {
        $this->requestAdapter = $requestAdapter;
        $this->method = strtoupper($method);
        $this->endpoint = $endpoint;
    }

    /**
     * @param mixed $body
     *
     * @return self
     */
    public function attachBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @param string $returnType
     *
     * @return self
     */
    public function setReturnType($returnType)
    {
        $this->returnType = $returnType;

        return $this;
    }

    /**
     * @param int $pageSize
     *
     * @return self
     */
    public function setPageSize($pageSize)
    {
        $this->pageSize = (int)$pageSize;

        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return self
     */
    public function setHeader($name, $value)
    {
        $this->headers[(string)$name] = (string)$value;

        return $this;
    }

    /**
     * @return mixed|null
     */
    public function execute()
    {
        $request = $this->buildRequestInformation();

        if ($this->body !== null) {
            if ($this->body instanceof Parsable) {
                $request->setContentFromParsable($this->requestAdapter, 'application/json', $this->body);
            } else {
                $request->setContentFromScalar($this->requestAdapter, 'application/json', $this->body);
            }
        }

        if ($this->method === 'DELETE' || !$this->returnType) {
            return $this->requestAdapter->sendNoContentAsync($request)->wait();
        }

        return $this->requestAdapter
            ->sendAsync($request, [$this->returnType, 'createFromDiscriminatorValue'])
            ->wait();
    }

    /**
     * @return array
     */
    public function getPage()
    {
        $items = [];
        $endpoint = $this->endpoint;

        do {
            $response = $this->fetchCollectionResponse($endpoint);

            if (!$response) {
                break;
            }

            foreach ($response->getValue() ?: [] as $item) {
                $items[] = $item;

                if ($this->pageSize && count($items) >= $this->pageSize) {
                    return $items;
                }
            }

            $endpoint = $response->getOdataNextLink();
        } while ($endpoint);

        return $items;
    }

    /**
     * @param string $endpoint
     *
     * @return mixed|null
     */
    private function fetchCollectionResponse($endpoint)
    {
        $request = $this->buildRequestInformation($endpoint);
        $responseType = $this->getCollectionResponseType();

        return $this->requestAdapter
            ->sendAsync($request, [$responseType, 'createFromDiscriminatorValue'])
            ->wait();
    }

    /**
     * @param string|null $endpoint
     *
     * @return RequestInformation
     */
    private function buildRequestInformation($endpoint = null)
    {
        $request = new RequestInformation();
        $request->httpMethod = $this->method;
        $request->setUri($this->buildUrl($endpoint ?: $this->endpoint));
        $request->addHeaders(array_merge(['Accept' => 'application/json'], $this->headers));

        return $request;
    }

    /**
     * @param string $endpoint
     *
     * @return string
     */
    private function buildUrl($endpoint)
    {
        $url = strpos($endpoint, 'http') === 0
            ? $endpoint
            : 'https://graph.microsoft.com/v1.0' . $endpoint;

        if ($this->pageSize && strpos($url, '$top=') === false) {
            $separator = strpos($url, '?') === false ? '?' : '&';
            $url .= $separator . '$top=' . $this->pageSize;
        }

        return $url;
    }

    /**
     * @return string
     */
    private function getCollectionResponseType()
    {
        switch ($this->returnType) {
            case Event::class:
                return EventCollectionResponse::class;
            case Calendar::class:
                return CalendarCollectionResponse::class;
            case Subscription::class:
                return SubscriptionCollectionResponse::class;
            case User::class:
            default:
                return EventCollectionResponse::class;
        }
    }
}
