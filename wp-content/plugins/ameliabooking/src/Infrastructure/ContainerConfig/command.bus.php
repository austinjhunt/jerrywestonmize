<?php

defined('ABSPATH') or die('No script kiddies please!');

use AmeliaBooking\Infrastructure\CommandBus\LoggingMiddleware;
use AmeliaBooking\Infrastructure\Licence;
use League\Tactician\CommandBus;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\CommandNameExtractor\ClassNameExtractor;
use League\Tactician\Handler\Locator\InMemoryLocator;
use League\Tactician\Handler\MethodNameInflector\HandleInflector;
use League\Tactician\Plugins\LockingMiddleware;

// @codingStandardsIgnoreStart
$entries['command.bus'] = function ($c) {
    $handlerMiddleware = new CommandHandlerMiddleware(
        new ClassNameExtractor(),
        new InMemoryLocator(Licence\Licence::getCommands($c)),
        new HandleInflector()
    );

    return new CommandBus([
        new LoggingMiddleware($c->get('infrastructure.logger')),
        new LockingMiddleware(),
        $handlerMiddleware,
    ]);
};
// @codingStandardsIgnoreEnd
