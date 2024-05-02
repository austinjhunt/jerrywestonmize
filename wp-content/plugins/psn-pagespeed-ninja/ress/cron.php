<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

// Allow CLI execution only
if (PHP_SAPI === 'cli') {
    include_once __DIR__ . '/ressio.php';
    $ressio = new Ressio(array(
        'worker' => array(
            'enabled' => true
        )
    ));
    $ressio->di->worker->run();
}
