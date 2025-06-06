<?php

/*
 * This file is part of Pimple.
 *
 * Copyright (c) 2009 Fabien Potencier
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * Modified by code-atlantic on 27-May-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace ContentControl\Vendor\Pimple\Tests\Psr11;

use PHPUnit\Framework\TestCase;
use ContentControl\Vendor\Pimple\Container;
use ContentControl\Vendor\Pimple\Psr11\ServiceLocator;
use ContentControl\Vendor\Pimple\Tests\Fixtures;

/**
 * ServiceLocator test case.
 *
 * @author Pascal Luna <skalpa@zetareticuli.org>
 */
class ServiceLocatorTest extends TestCase
{
    public function testCanAccessServices()
    {
        $pimple = new Container();
        $pimple['service'] = function () {
            return new Fixtures\Service();
        };
        $locator = new ServiceLocator($pimple, ['service']);

        $this->assertSame($pimple['service'], $locator->get('service'));
    }

    public function testCanAccessAliasedServices()
    {
        $pimple = new Container();
        $pimple['service'] = function () {
            return new Fixtures\Service();
        };
        $locator = new ServiceLocator($pimple, ['alias' => 'service']);

        $this->assertSame($pimple['service'], $locator->get('alias'));
    }

    public function testCannotAccessAliasedServiceUsingRealIdentifier()
    {
        $this->expectException(\ContentControl\Vendor\Pimple\Exception\UnknownIdentifierException::class);
        $this->expectExceptionMessage('Identifier "service" is not defined.');

        $pimple = new Container();
        $pimple['service'] = function () {
            return new Fixtures\Service();
        };
        $locator = new ServiceLocator($pimple, ['alias' => 'service']);

        $service = $locator->get('service');
    }

    public function testGetValidatesServiceCanBeLocated()
    {
        $this->expectException(\ContentControl\Vendor\Pimple\Exception\UnknownIdentifierException::class);
        $this->expectExceptionMessage('Identifier "foo" is not defined.');

        $pimple = new Container();
        $pimple['service'] = function () {
            return new Fixtures\Service();
        };
        $locator = new ServiceLocator($pimple, ['alias' => 'service']);

        $service = $locator->get('foo');
    }

    public function testGetValidatesTargetServiceExists()
    {
        $this->expectException(\ContentControl\Vendor\Pimple\Exception\UnknownIdentifierException::class);
        $this->expectExceptionMessage('Identifier "invalid" is not defined.');

        $pimple = new Container();
        $pimple['service'] = function () {
            return new Fixtures\Service();
        };
        $locator = new ServiceLocator($pimple, ['alias' => 'invalid']);

        $service = $locator->get('alias');
    }

    public function testHasValidatesServiceCanBeLocated()
    {
        $pimple = new Container();
        $pimple['service1'] = function () {
            return new Fixtures\Service();
        };
        $pimple['service2'] = function () {
            return new Fixtures\Service();
        };
        $locator = new ServiceLocator($pimple, ['service1']);

        $this->assertTrue($locator->has('service1'));
        $this->assertFalse($locator->has('service2'));
    }

    public function testHasChecksIfTargetServiceExists()
    {
        $pimple = new Container();
        $pimple['service'] = function () {
            return new Fixtures\Service();
        };
        $locator = new ServiceLocator($pimple, ['foo' => 'service', 'bar' => 'invalid']);

        $this->assertTrue($locator->has('foo'));
        $this->assertFalse($locator->has('bar'));
    }
}
