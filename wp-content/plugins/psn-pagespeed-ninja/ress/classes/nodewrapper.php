<?php
/*
 * RESSIO Responsive Server Side Optimizer
 * https://github.com/ressio/
 *
 * @copyright   Copyright (C) 2013-2024 Kuneri Ltd. / Denis Ryabov, PageSpeed Ninja Team. All rights reserved.
 * @license     GNU General Public License version 2
 */

defined('RESSIO_PATH') || die();

class Ressio_NodeWrapper implements IRessio_HtmlNode
{
    /** @var string */
    public $tagName;
    /** @var string|null */
    public $content;
    /** @var array */
    public $attributes;
    /** @var string */
    public $self_close_str;

    /**
     * @param string $tag
     * @param string|null $content
     * @param array $attributes
     * @param string $self_close_str
     */
    public function __construct($tag, $content = null, $attributes = array(), $self_close_str = '')
    {
        $this->tagName = $tag;
        $this->content = $content;
        $this->attributes = $attributes;
        $this->self_close_str = $self_close_str;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $s = "<{$this->tagName}";
        foreach ($this->attributes as $key => $value) {
            if ($value === false) {
                $s .= " $key";
            } else {
                $q = (strpos($value, '"') === false) ? '"' : "'";
                $s .= " $key=$q$value$q";
            }
        }
        if ($this->content === null) {
            $s .= "{$this->self_close_str}>";
        } else {
            $s .= ">{$this->content}</{$this->tagName}>";
        }
        return $s;
    }

    /**
     * @return string
     */
    public function getTag()
    {
        return $this->tagName;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasAttribute($name)
    {
        return isset($this->attributes[$name]);
    }

    /**
     * @param string $name
     * @return string
     */
    public function getAttribute($name)
    {
        return $this->attributes[$name];
    }

    /**
     * @param string $name
     * @param string $value
     * @return void
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * @param string $name
     * @return void
     */
    public function removeAttribute($name)
    {
        unset($this->attributes[$name]);
    }

    /**
     * @param string $class
     * @return void
     */
    public function addClass($class)
    {
        if (!isset($this->attributes['class']) || $this->attributes['class'] === '') {
            $this->attributes['class'] = $class;
            return;
        }
        if (strpos(' ' . $this->attributes['class'] . ' ', " $class ") === false) {
            $this->attributes['class'] .= " $class";
        }
    }

    /**
     * @param string $class
     * @return void
     */
    public function removeClass($class)
    {
        if (isset($this->attributes['class'])) {
            $this->attributes['class'] = trim(str_replace(" $class ", ' ', ' ' . $this->attributes['class'] . ' '));
        }
    }
}
