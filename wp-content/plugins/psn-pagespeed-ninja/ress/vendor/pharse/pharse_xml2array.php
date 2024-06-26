<?php
/**
 * @author RESSIO Team
 * @package Pharse
 * @link https://github.com/ressio/pharse
 *
 * FORKED FROM
 * @author Niels A.D.
 * @package Ganon
 * @link http://code.google.com/p/ganon/
 *
 * @license Artistic License  http://dev.perl.org/licenses/artistic.html
 * @license GNU/GPL v2        http://www.gnu.org/licenses/gpl.html
 */

include_once __DIR__ . '/pharse_parser_html.php';

/**
 * Converts a XML document to an array
 */
class XML_Parser_Array extends HTML_Parser_Base
{

    /**
     * Holds the document structure
     * @var array array('name' => 'tag', 'attrs' => array('attr' => 'val'), 'childen' => array())
     */
    public $root = array(
        'name' => '',
        'attrs' => array(),
        'children' => array()
    );

    /**
     * Current parsing hierarchy
     * @var array
     * @access private
     */
    protected $hierarchy = array();

    protected function parse_hierarchy($self_close)
    {
        if ($this->status['closing_tag']) {
            $found = false;
            for ($count = count($this->hierarchy), $i = $count - 1; $i >= 0; $i--) {
                if (strcasecmp($this->hierarchy[$i]['name'], $this->status['tag_name']) === 0) {

                    for ($ii = ($count - $i - 1); $ii >= 0; $ii--) {
                        $e = array_pop($this->hierarchy);
                        if ($ii > 0) {
                            $this->addError('Closing tag "' . $this->status['tag_name'] . '" while "' . $e['name'] . '" is not closed yet');
                        }
                    }

                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $this->addError('Closing tag "' . $this->status['tag_name'] . '" which is not open');
            }
        } else {
            $tag = array(
                'name' => $this->status['tag_name'],
                'attrs' => $this->status['attributes'],
                'children' => array()
            );
            if ($this->hierarchy) {
                $current =& $this->hierarchy[count($this->hierarchy) - 1];
                $current['children'][] = $tag;
                $tag =& $current['children'][count($current['children']) - 1];
                unset($current['tagData']);
            } else {
                $this->root = $tag;
                $tag =& $this->root;
                $self_close = false;
            }
            if (!$self_close) {
                $this->hierarchy[] =& $tag;
            }
        }
    }

    protected function parse_tag_default()
    {
        if (!parent::parse_tag_default()) {
            return false;
        }

        if ($this->status['tag_name'][0] !== '?') {
            $this->parse_hierarchy($this->status['self_close'] ? true : null);
        }
        return true;
    }

    protected function parse_text()
    {
        parent::parse_text();
        if (($this->status['text'] !== '') && $this->hierarchy) {
            $current =& $this->hierarchy[count($this->hierarchy) - 1];
            if (!$current['children']) {
                $current['tagData'] = $this->status['text'];
            }
        }
    }

    public function parse_all()
    {
        return parent::parse_all() ? $this->root : false;
    }
}
