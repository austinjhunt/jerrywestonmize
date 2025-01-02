<?php

/**
 * ECPrivateKey
 *
 * From: https://tools.ietf.org/html/rfc5915
 *
 * PHP version 5
 *
 * @author    Jim Wigginton <terrafrost@php.net>
 * @copyright 2016 Jim Wigginton
 * @license   http://www.opensource.org/licenses/mit-license.html  MIT License
 * @link      http://phpseclib.sourceforge.net
 */
namespace Google\Site_Kit_Dependencies\phpseclib3\File\ASN1\Maps;

use Google\Site_Kit_Dependencies\phpseclib3\File\ASN1;
/**
 * ECPrivateKey
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 */
abstract class ECPrivateKey
{
    const MAP = ['type' => \Google\Site_Kit_Dependencies\phpseclib3\File\ASN1::TYPE_SEQUENCE, 'children' => ['version' => ['type' => \Google\Site_Kit_Dependencies\phpseclib3\File\ASN1::TYPE_INTEGER, 'mapping' => [1 => 'ecPrivkeyVer1']], 'privateKey' => ['type' => \Google\Site_Kit_Dependencies\phpseclib3\File\ASN1::TYPE_OCTET_STRING], 'parameters' => ['constant' => 0, 'optional' => \true, 'explicit' => \true] + \Google\Site_Kit_Dependencies\phpseclib3\File\ASN1\Maps\ECParameters::MAP, 'publicKey' => ['type' => \Google\Site_Kit_Dependencies\phpseclib3\File\ASN1::TYPE_BIT_STRING, 'constant' => 1, 'optional' => \true, 'explicit' => \true]]];
}