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
use Microsoft\Kiota\Abstractions\Authentication\BaseBearerTokenAuthenticationProvider;
use Microsoft\Kiota\Abstractions\RequestInformation;
use Microsoft\Kiota\Abstractions\Serialization\AdditionalDataHolder;
use Microsoft\Kiota\Abstractions\Serialization\ParseNodeFactoryRegistry;
use Microsoft\Kiota\Abstractions\Serialization\Parsable;
use Microsoft\Kiota\Abstractions\Serialization\SerializationWriterFactoryRegistry;
use Microsoft\Kiota\Serialization\Json\JsonParseNodeFactory;
use Microsoft\Kiota\Serialization\Json\JsonSerializationWriterFactory;

class OutlookGraphClient
{
    /** @var bool */
    private static $serializationFactoriesRegistered = false;

    /** @var GraphRequestAdapter|null */
    private $requestAdapter;

    /** @var string */
    private $accessToken = '';

    /**
     * @param string|null $accessToken
     */
    public function __construct($accessToken = null)
    {
        self::registerSerializationFactories();

        if ($accessToken) {
            $this->setAccessToken($accessToken);
        }
    }

    /**
     * @param string $accessToken
     *
     * @return self
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = (string)$accessToken;
        $this->requestAdapter = new GraphRequestAdapter(
            new BaseBearerTokenAuthenticationProvider(
                new OutlookGraphAccessTokenProvider($this->accessToken)
            )
        );

        return $this;
    }

    /**
     * @param string $method
     * @param string $endpoint
     *
     * @return OutlookGraphRequest
     */
    public function createRequest($method, $endpoint)
    {
        return new OutlookGraphRequest($this->getRequestAdapter(), $method, $endpoint);
    }

    /**
     * @param string $method
     * @param string $endpoint
     *
     * @return OutlookGraphRequest
     */
    public function createCollectionRequest($method, $endpoint)
    {
        return $this->createRequest($method, $endpoint);
    }

    /**
     * @param class-string $className
     * @param array        $data
     *
     * @return mixed
     */
    public static function modelFromArray($className, array $data)
    {
        $model = new $className();

        if ($model instanceof AdditionalDataHolder) {
            $model->setAdditionalData($data);
        }

        return $model;
    }

    /**
     * @return GraphRequestAdapter
     */
    private function getRequestAdapter()
    {
        if (!$this->requestAdapter) {
            $this->setAccessToken($this->accessToken);
        }

        return $this->requestAdapter;
    }

    /**
     * @return void
     */
    private static function registerSerializationFactories()
    {
        if (self::$serializationFactoriesRegistered) {
            return;
        }

        $jsonParseNodeFactory = new JsonParseNodeFactory();
        ParseNodeFactoryRegistry::getDefaultInstance()->contentTypeAssociatedFactories[
            $jsonParseNodeFactory->getValidContentType()
        ] = $jsonParseNodeFactory;

        $jsonSerializationWriterFactory = new JsonSerializationWriterFactory();
        SerializationWriterFactoryRegistry::getDefaultInstance()->contentTypeAssociatedFactories[
            $jsonSerializationWriterFactory->getValidContentType()
        ] = $jsonSerializationWriterFactory;

        self::$serializationFactoriesRegistered = true;
    }
}
