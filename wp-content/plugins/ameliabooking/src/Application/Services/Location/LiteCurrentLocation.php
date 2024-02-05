<?php
/**
 * @copyright © TMS-Plugins. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Application\Services\Location;

/**
 * Class LiteCurrentLocation
 *
 * @package AmeliaBooking\Application\Services\Location
 */
class LiteCurrentLocation extends AbstractCurrentLocation
{
    /**
     * Get country ISO code by public IP address
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function getCurrentLocationCountryIso()
    {
        try {
            $response = wp_remote_get('https://www.iplocate.io/api/lookup/' . $_SERVER['REMOTE_ADDR'], []);

            if (is_array($response) && isset($response['body'])) {
                $result = json_decode($response['body']);

                return !property_exists($result, 'country_code') ? '' : strtolower($result->country_code ?: '');
            } else {
                return '';
            }
        } catch (\Exception $e) {
            return '';
        }
    }
}
