<?php

// File generated from our OpenAPI spec

namespace AmeliaStripe\TestHelpers;

/**
 * A test clock enables deterministic control over objects in testmode. With a test clock, you can create
 * objects at a frozen time in the past or future, and advance to a specific future time to observe webhooks and state changes. After the clock advances,
 * you can either validate the current state of your scenario (and test your assumptions), change the current state of your scenario (and test more complex scenarios), or keep advancing forward in time.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property int $deletes_after Time at which this clock is scheduled to auto delete.
 * @property int $frozen_time Time at which all objects belonging to this clock are frozen.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property null|string $name The custom name supplied at creation.
 * @property string $status The status of the Test Clock.
 * @property (object{advancing?: (object{target_frozen_time: int}&\AmeliaStripe\StripeObject)}&\AmeliaStripe\StripeObject) $status_details
 */
class TestClock extends \AmeliaStripe\ApiResource
{
    const OBJECT_NAME = 'test_helpers.test_clock';

    const STATUS_ADVANCING = 'advancing';
    const STATUS_INTERNAL_FAILURE = 'internal_failure';
    const STATUS_READY = 'ready';

    /**
     * Creates a new test clock that can be attached to new customers and quotes.
     *
     * @param null|array{expand?: string[], frozen_time: int, name?: string} $params
     * @param null|array|string $options
     *
     * @return TestClock the created resource
     *
     * @throws \AmeliaStripe\Exception\ApiErrorException if the request fails
     */
    public static function create($params = null, $options = null)
    {
        self::_validateParams($params);
        $url = static::classUrl();

        list($response, $opts) = static::_staticRequest('post', $url, $params, $options);
        $obj = \AmeliaStripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }

    /**
     * Deletes a test clock.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @return TestClock the deleted resource
     *
     * @throws \AmeliaStripe\Exception\ApiErrorException if the request fails
     */
    public function delete($params = null, $opts = null)
    {
        self::_validateParams($params);

        $url = $this->instanceUrl();
        list($response, $opts) = $this->_request('delete', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * Returns a list of your test clocks.
     *
     * @param null|array{ending_before?: string, expand?: string[], limit?: int, starting_after?: string} $params
     * @param null|array|string $opts
     *
     * @return \AmeliaStripe\Collection<TestClock> of ApiResources
     *
     * @throws \AmeliaStripe\Exception\ApiErrorException if the request fails
     */
    public static function all($params = null, $opts = null)
    {
        $url = static::classUrl();

        return static::_requestPage($url, \AmeliaStripe\Collection::class, $params, $opts);
    }

    /**
     * Retrieves a test clock.
     *
     * @param array|string $id the ID of the API resource to retrieve, or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @return TestClock
     *
     * @throws \AmeliaStripe\Exception\ApiErrorException if the request fails
     */
    public static function retrieve($id, $opts = null)
    {
        $opts = \AmeliaStripe\Util\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh();

        return $instance;
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @return TestClock the advanced test clock
     *
     * @throws \AmeliaStripe\Exception\ApiErrorException if the request fails
     */
    public function advance($params = null, $opts = null)
    {
        $url = $this->instanceUrl() . '/advance';
        list($response, $opts) = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }
}
