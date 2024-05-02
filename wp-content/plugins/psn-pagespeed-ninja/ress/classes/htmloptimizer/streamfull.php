<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_HtmlOptimizer_StreamFull extends Ressio_HtmlOptimizer_Stream
{
    /** @var string */
    const TAG_REGEX = '#<(!(?:--\[if\b|--|\[if\b)|(?:!doctype|/?\w+)(?=[>\s]))#i';
}
