<?php

namespace AmeliaBooking\Application\Services\Cache;

use AmeliaBooking\Domain\Entity\Cache\Cache;
use AmeliaBooking\Infrastructure\Common\Exceptions\QueryExecutionException;
use AmeliaBooking\Infrastructure\Common\Container;
use AmeliaBooking\Infrastructure\Repository\Cache\CacheRepository;
use AmeliaBooking\Infrastructure\WP\Integrations\WooCommerce\WooCommerceService;
use Interop\Container\Exception\ContainerException;
use InvalidArgumentException;
use Slim\Exception\ContainerValueNotFoundException;

/**
 * Class CacheApplicationService
 *
 * @package AmeliaBooking\Application\Services\Cache
 */
class CacheApplicationService
{
    private $container;

    /**
     * CacheApplicationService constructor.
     *
     * @param Container $container
     *
     * @throws InvalidArgumentException
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param string $name
     *
     * @return array|null
     *
     * @throws ContainerValueNotFoundException
     * @throws QueryExecutionException
     */
    public function getCacheByName($name)
    {
        /** @var CacheRepository $cacheRepository */
        $cacheRepository = $this->container->get('domain.cache.repository');

        /** @var Cache $cache */
        $cache = ($data = explode('_', $name)) && isset($data[0], $data[1]) ?
            $cacheRepository->getByIdAndName($data[0], $data[1]) : null;

        if ($cache && $cache->getData()) {
            $cacheData = json_decode($cache->getData()->getValue(), true);

            return $this->withNonce(apply_filters('amelia_mollie_cache_data_filter', $cacheData));
        }

        return null;
    }

    /**
     * @param string $name
     *
     * @return array|null
     *
     * @throws ContainerValueNotFoundException
     * @throws ContainerException
     */
    public function getWcCacheByName($name)
    {
        $cacheData = ($data = explode('_', $name)) && isset($data[0], $data[1]) ?
            WooCommerceService::getCacheData($data[0]) : null;
        return $this->withNonce(apply_filters('amelia_woocommerce_cache_data_filter', $cacheData));
    }

    /**
     * The page that restores the cache is reached after a redirect from the payment gateway, so the nonce the
     * booking request handed back is long gone. Mint a new one here - this runs while rendering the page for the
     * returning visitor, so it is valid for the requests the restored form fires (i.e. "/bookings/success").
     *
     * @param array|null $cacheData
     *
     * @return array|null
     */
    private function withNonce($cacheData)
    {
        if (!is_array($cacheData)) {
            return $cacheData;
        }

        $cacheData['wpAmeliaNonce'] = wp_create_nonce('ajax-nonce');

        return $cacheData;
    }
}
