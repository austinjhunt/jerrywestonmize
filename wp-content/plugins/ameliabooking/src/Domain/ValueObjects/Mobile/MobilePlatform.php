<?php

/**
 * @copyright © Melograno Ventures. All rights reserved.
 * @licence   See LICENCE.md for license details.
 */

namespace AmeliaBooking\Domain\ValueObjects\Mobile;

use AmeliaBooking\Domain\Common\Exceptions\InvalidArgumentException;

/**
 * Class MobilePlatform
 *
 * @package AmeliaBooking\Domain\ValueObjects\Mobile
 */
final class MobilePlatform
{
    public const IOS     = 'ios';
    public const ANDROID = 'android';

    /** @var string */
    private $value;

    /**
     * @param string $value
     *
     * @throws InvalidArgumentException
     */
    public function __construct($value)
    {
        if (!in_array($value, [self::IOS, self::ANDROID], true)) {
            throw new InvalidArgumentException("Invalid mobile platform '$value'");
        }

        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}
