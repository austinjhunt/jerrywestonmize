<?php
/**
 * JSMin.php - modified PHP implementation of Douglas Crockford's JSMin.
 *
 * <code>
 * $minifiedJs = JSMin::minify($js);
 * </code>
 *
 * This is a modified port of jsmin.c. Improvements:
 *
 * Does not choke on some regexp literals containing quote characters. E.g. /'/
 *
 * Spaces are preserved after some add/sub operators, so they are not mistakenly
 * converted to post-inc/dec. E.g. a + ++b -> a+ ++b
 *
 * Preserves multi-line comments that begin with /*!
 *
 * PHP 5 or higher is required.
 *
 * Permission is hereby granted to use this version of the library under the
 * same terms as jsmin.c, which has the following license:
 *
 * --
 * Copyright (c) 2002 Douglas Crockford  (www.crockford.com)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * The Software shall be used for Good, not Evil.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * --
 *
 * @package JSMin
 * @author Ryan Grove <ryan@wonko.com> (PHP port)
 * @author Steve Clay <steve@mrclay.org> (modifications + cleanup)
 * @author Andrea Giammarchi <http://www.3site.eu> (spaceBeforeRegExp)
 * @author Denis Ryabov <https://www.mobilejoomla.com> (performance optimization, fix regexp parsing)
 * @copyright 2002 Douglas Crockford <douglas@crockford.com> (jsmin.c)
 * @copyright 2008 Ryan Grove <ryan@wonko.com> (PHP port)
 * @license http://opensource.org/licenses/mit-license.php MIT License
 * @link http://code.google.com/p/jsmin-php/
 */

class JSMin {
    const ACTION_KEEP_A   = 1;
    const ACTION_DELETE_A = 2;
    const ACTION_DELETE_B = 3;

    /** @var string */
    protected $a           = "\n";
    /** @var string */
    protected $b           = '';
    /** @var string */
    protected $input;
    /** @var int */
    protected $inputIndex  = 0;
    /** @var int */
    protected $inputLength;
    /** @var string */
    protected $lookAhead;
    /** @var string */
    protected $output      = '';
    /** @var string */
    protected $lastByteOut;

    /** @var bool[] */
    protected $keepSpace = array('+' => true, '-' => true);

    /**
     * Minify Javascript.
     *
     * @param string $js Javascript to be minified
     *
     * @return string
     * @throws JSMin_UnterminatedRegExpException
     * @throws JSMin_UnterminatedStringException
     * @throws JSMin_UnterminatedCommentException
     */
    public static function minify($js)
    {
        $jsmin = new JSMin($js);
        return $jsmin->min();
    }

    /**
     * @param string $input
     */
    public function __construct($input)
    {
        $this->input = $input;
    }

    /**
     * Perform minification, return result
     *
     * @return string
     * @throws JSMin_UnterminatedRegExpException
     * @throws JSMin_UnterminatedStringException
     * @throws JSMin_UnterminatedCommentException
     */
    public function min()
    {
        $mbIntEnc = null;
        if (function_exists('mb_strlen') && ((int)ini_get('mbstring.func_overload') & 2)) {
            $mbIntEnc = mb_internal_encoding();
            mb_internal_encoding('8bit');
        }
        $this->input = str_replace("\r\n", "\n", $this->input);
        $this->inputLength = strlen($this->input);

        $this->action(self::ACTION_DELETE_B);

        while (($a = $this->a) !== '') {
            // determine next command
            $command = self::ACTION_KEEP_A; // default
            if ($a === ' ') {
                if (isset($this->keepSpace[$this->lastByteOut])) {
                    if ($this->b === $this->lastByteOut) {
                        // Don't delete this space. If we do, the addition/subtraction
                        // could be parsed as a post-increment
                    } else {
                        $command = self::ACTION_DELETE_A;
                    }
                } elseif (! $this->isAlphaNum($this->b)) {
                    $command = self::ACTION_DELETE_A;
                }
            } elseif ($a === "\n") {
                if (isset($this->keepSpace[$this->lastByteOut]) && $this->b === $this->lastByteOut) {
                    // Don't delete this space. If we do, the addition/subtraction
                    // could be parsed as a post-increment
                } elseif ($this->b === ' ') {
                    $command = self::ACTION_DELETE_B;
                    // in case of mbstring.func_overload & 2, must check for null b,
                    // otherwise mb_strpos will give WARNING
                } elseif ($this->b !== ''
                        && false === strpos('{[(+-!~', $this->b)
                        && ! $this->isAlphaNum($this->b)) {
                    $command = self::ACTION_DELETE_A;
                }
            } elseif (! $this->isAlphaNum($a)) {
                if (($this->b === ' ' && !isset($this->keepSpace[$a]))
                    || ($this->b === "\n" && (false === strpos('}])+-/"\'`', $a)))) {
                    $command = self::ACTION_DELETE_B;
                } elseif ($a === ';' && $this->b === '}' && $this->lastByteOut !== ')' && substr($this->output, -4) !== 'else') {
                    $command = self::ACTION_DELETE_A;
                }
            }
            $this->action($command);
        }
        $this->output = trim($this->output);

        if ($mbIntEnc !== null) {
            mb_internal_encoding($mbIntEnc);
        }
        return $this->output;
    }

    /**
     * ACTION_KEEP_A = Output A. Copy B to A. Get the next B.
     * ACTION_DELETE_A = Copy B to A. Get the next B.
     * ACTION_DELETE_B = Get the next B.
     *
     * @param int $command
     * @return void
     * @throws JSMin_UnterminatedRegExpException
     * @throws JSMin_UnterminatedStringException
     * @throws JSMin_UnterminatedCommentException
     */
    protected function action($command)
    {
        switch ($command) {
            case self::ACTION_KEEP_A:
                $this->output .= $this->a;
                $this->lastByteOut = $this->a;
                // fallthrough
            case self::ACTION_DELETE_A:
                $this->a = $this->b;
                if ($this->a !== '' && strpos('\'"`', $this->a) !== false) { // string literal
                    $str = $this->a; // in case needed for exception
                    while (true) {
                        $this->output .= $this->a;
                        $this->lastByteOut = $this->a;

                        $this->a       = $this->get();
                        if ($this->a === $this->b) { // end quote
                            break;
                        }
                        if ($this->a === '' || ($this->b !== '`' && $this->a === "\n")) {
                            throw new JSMin_UnterminatedStringException(
                                'JSMin: Unterminated String at byte '
                                . $this->inputIndex . ": {$str}");
                        }
                        $str .= $this->a;
                        if ($this->a === '\\') {
                            $this->output .= $this->a;
                            $this->lastByteOut = $this->a;

                            $this->a       = $this->get();
                            $str .= $this->a;
                        }
                    }
                }
            // fallthrough
            case self::ACTION_DELETE_B:
                $this->b = $this->next();
                if ($this->b === '/' && $this->isRegexpLiteral()) { // RegExp literal
                    if ($this->a !== ' ') {
                        $this->output .= $this->a;
                        if ($this->a === '/') {
                            $this->output .= ' ';
                        }
                    }
                    $this->output .= $this->b;
                    if ($this->lookAhead !== '') {
                        $this->lookAhead = '';
                        $this->inputIndex--;
                    }
                    if (!preg_match('#(?:\[(?:\\\\.|[^]\\\\])*\]|\\\\.|[^[/\\\\]++)+(?=/)#As', $this->input, $match, 0, $this->inputIndex)) {
                        throw new JSMin_UnterminatedRegExpException(
                            'JSMin: Unterminated RegExp at byte '
                            . $this->inputIndex . ': ' . substr($this->input, $this->inputIndex));
                    }
                    $this->output .= $match[0];
                    $this->inputIndex += strlen($match[0]) + 1;
                    $this->lastByteOut = substr($match[0], -1);
                    $this->a = '/';
                    $this->b = $this->next();
                }
            // end case ACTION_DELETE_B
        }
    }

    /**
     * @return bool
     */
    protected function isRegexpLiteral()
    {
        $lastPiece = rtrim(substr($this->output, -7) . $this->a, " \n");
        if (false !== strpos('!%&(*+,-/:;<=>?[^{|}~', substr($lastPiece, -1))) { // we aren't dividing
            return true;
        }
        // you can't divide a keyword
        if (preg_match('/(?:^|[^\w$\\\\\x7F-\xFF])(?:case|delete|do|else|extends|in|new|return|throw|typeof|void|yield)$/', $lastPiece)) {
            return true;
        }
        if (' ' === $this->a && strlen($this->output) < 2) { // weird edge case
            return true;
        }
        return false;
    }

    /**
     * Get next char. Convert ctrl char to space.
     *
     * @return string
     */
    protected function get()
    {
        $c = $this->lookAhead;
        $this->lookAhead = '';
        if ($c === '') {
            if ($this->inputIndex < $this->inputLength) {
                $c = $this->input[$this->inputIndex++];
            } else {
                return '';
            }
        }
        if ($c >= ' ') {
            return $c;
        }
        if ($c === "\r" || $c === "\n") {
            return "\n";
        }
        return ' ';
    }

    /**
     * Get next char. If is ctrl character, translate to a space or newline.
     *
     * @return string
     */
    protected function peek()
    {
        return $this->lookAhead = $this->get();
    }

    /**
     * Is $c a letter, digit, underscore, dollar sign, escape, or non-ASCII?
     *
     * @param string $c
     *
     * @return bool
     */
    protected function isAlphaNum($c)
    {
        return (bool)preg_match('/[\w$\\\\\x7F-\xFF]/', $c);
    }

    /**
     * @return string
     */
    protected function singleLineComment()
    {
        $comment = '';
        while (true) {
            $get = $this->get();
            $comment .= $get;
            if ($get === '' || $get === "\n") { // EOL reached
                // if IE conditional comment
                if (preg_match('/^\\/@(?:cc_on|if|elif|else|end)\\b/', $comment)) {
                    return "/{$comment}";
                }
                return $get;
            }
        }
    }

    /**
     * @return string
     * @throws JSMin_UnterminatedCommentException
     */
    protected function multipleLineComment()
    {
        $this->get();
        $comment = '';
        while (true) {
            $get = $this->get();
            if ($get === '*') {
                if ($this->peek() === '/') { // end of comment reached
                    $this->get();
                    // if comment preserved by YUI Compressor
                    if (0 === strncmp($comment, '!', 1)) {
                        return "/*{$comment}*/";
                    }
                    // if IE conditional comment
                    if (preg_match('/^@(?:cc_on|if|elif|else|end)\\b/', $comment)) {
                        return "/*{$comment}*/";
                    }
                    return ' ';
                }
            } elseif ($get === '') {
                throw new JSMin_UnterminatedCommentException(
                    'JSMin: Unterminated comment at byte '
                    . $this->inputIndex . ": /*{$comment}");
            }
            $comment .= $get;
        }
    }

    /**
     * Get the next character, skipping over comments.
     * Some comments may be preserved.
     *
     * @return string
     * @throws JSMin_UnterminatedCommentException
     */
    protected function next()
    {
        $get = $this->get();
        if ($get !== '/') {
            return $get;
        }
        switch ($this->peek()) {
            case '/': return $this->singleLineComment();
            case '*': return $this->multipleLineComment();
            default: return $get;
        }
    }
}

class JSMin_UnterminatedStringException extends Exception {}
class JSMin_UnterminatedCommentException extends Exception {}
class JSMin_UnterminatedRegExpException extends Exception {}
