<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

/**
 * @property-read DOMAttr[] $attributes
 *
 * @note "implements IRessio_HtmlNode" result in Fatal error in PHP 8:
 *       Declaration of DOMElement::hasAttribute(string $qualifiedName) must be compatible with IRessio_HtmlNode::hasAttribute($name)
 * @todo Drop PHP 5.6 support??? (to allow typed arguments)
 */
class Ressio_HtmlOptimizer_Dom_Element extends DOMElement /* implements IRessio_HtmlNode */
{
    /** @var Ressio_HtmlOptimizer_Dom_Attr[] $attributes */

    /**
     * @return string
     */
    public function getTag()
    {
        return $this->tagName;
    }

    /**
     * @param string $class
     */
    public function addClass($class)
    {
        if (!$this->hasAttribute('class') || ($attr_class = $this->getAttribute('class')) === '') {
            $this->setAttribute('class', $class);
            return;
        }
        if (strpos(" $attr_class ", " $class ") === false) {
            $this->setAttribute('class', "$attr_class $class");
        }
    }

    /**
     * @param string $class
     */
    public function removeClass($class)
    {
        if ($this->hasAttribute('class')) {
            $this->setAttribute('class', trim(str_replace(" $class ", ' ', ' ' . $this->getAttribute('class') . ' ')));
        }
    }
}
