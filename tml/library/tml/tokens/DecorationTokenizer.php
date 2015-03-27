<?php

/**
 * Copyright (c) 2015 Translation Exchange, Inc
 *
 *  _______                  _       _   _             ______          _
 * |__   __|                | |     | | (_)           |  ____|        | |
 *    | |_ __ __ _ _ __  ___| | __ _| |_ _  ___  _ __ | |__  __  _____| |__   __ _ _ __   __ _  ___
 *    | | '__/ _` | '_ \/ __| |/ _` | __| |/ _ \| '_ \|  __| \ \/ / __| '_ \ / _` | '_ \ / _` |/ _ \
 *    | | | | (_| | | | \__ \ | (_| | |_| | (_) | | | | |____ >  < (__| | | | (_| | | | | (_| |  __/
 *    |_|_|  \__,_|_| |_|___/_|\__,_|\__|_|\___/|_| |_|______/_/\_\___|_| |_|\__,_|_| |_|\__, |\___|
 *                                                                                        __/ |
 *                                                                                       |___/
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

namespace tml\tokens;

use tml\Config;
use tml\TmlException;
use tml\utils\ArrayUtils;

class DecorationTokenizer {

    const RESERVED_TOKEN       = 'tml';

    const RE_SHORT_TOKEN_START = '\[[\w]*:';
    const RE_SHORT_TOKEN_END   = '\]';
    const RE_LONG_TOKEN_START  = '\[[\w]*\]';
    const RE_LONG_TOKEN_END    = '\[\/[\w]*\]';
    const RE_TEXT              = '[^\[\]]+'; #'[\w\s!.:{}\(\)\|,?]*'

    /**
     * @var string[]
     */
    public $tokens;

    /**
     * @var string[]
     */
    public $fragments;

    /**
     * @var array[]
     */
    public $context;

    /**
     * @var string
     */
    public $text;

    /**
     * @var array[]
     */
    public $opts;

    /**
     * @param string $text
     * @param array $context
     * @param array $opts
     */
    function __construct($text, $context = array(), $opts = array()) {
        $this->text = "[".self::RESERVED_TOKEN."]".$text."[/".self::RESERVED_TOKEN."]";
        $this->context = $context;
        $this->opts = $opts;
        $this->fragmentize();
    }

    /**
     * Splits texts into fragments, where some fragments are tokens, others are text
     */
    public function fragmentize() {
        $re = implode('|', array(
            self::RE_SHORT_TOKEN_START, self::RE_SHORT_TOKEN_END,
            self::RE_LONG_TOKEN_START, self::RE_LONG_TOKEN_END,
            self::RE_TEXT
        ));

        preg_match_all('/'.$re.'/', $this->text, $matches);
        $this->fragments = $matches[0];
        $this->tokens = array();
    }

    /**
     * @return null|string
     */
    function peek() {
        if (count($this->fragments) == 0) return null;
        return $this->fragments[0];
    }

    /**
     * @return mixed|null
     */
    function nextFragment() {
        if (count($this->fragments) == 0) return null;
        return array_shift($this->fragments);
    }

    /**
     * @return array|mixed|null
     */
    function parse() {
        $token = $this->nextFragment();

        if (preg_match('/'.self::RE_SHORT_TOKEN_START.'/', $token)) {
            return $this->parseTree(trim($token, '[:'), "short");
        }

        if (preg_match('/'.self::RE_LONG_TOKEN_START.'/', $token)) {
            return $this->parseTree(trim($token, '[]'), "long");
        }

        return $token;
    }

    /**
     * @param string $name
     * @param string $type
     * @return array
     */
    function parseTree($name, $type = 'short') {
        $tree = array($name);
        if (!in_array($name, $this->tokens) && $name != self::RESERVED_TOKEN) {
            array_push($this->tokens, $name);
        }

        if ($type == 'short') {
            $first = true;
            while ($this->peek()!=null && !preg_match('/'.self::RE_SHORT_TOKEN_END.'/', $this->peek())) {
                $value = $this->parse();
                if ($first && is_string($value)) {
                    $value = ltrim($value);
                    $first = false;
                }
                array_push($tree, $value);
            }
        } else if ($type == 'long') {
            while ($this->peek()!=null && !preg_match('/'.self::RE_LONG_TOKEN_END.'/', $this->peek())) {
                $value = $this->parse();
                array_push($tree, $value);
            }
        }

        $this->nextFragment();
        return $tree;
    }

    /**
     * @param string $token
     * @return bool
     */
    function isTokenAllowed($token) {
       if (!isset($this->opts["allowed_tokens"]))
           return true;
        return in_array($token, $this->opts["allowed_tokens"]);
    }

    /**
     * @param string $token
     * @return bool
     */
    function isDefaultDecoration($token) {
        return (Config::instance()->defaultToken($token, 'decoration') != null);
    }

    /**
     * @param string $token
     * @param string $value
     * @return string
     * @throws \tml\TmlException
     */
    public function defaultDecoration($token, $value) {
        if (!$this->isDefaultDecoration($token)) {
            throw new TmlException("The token is neither default decoration, nor has a value");
        }

        $default_decoration = '' . Config::instance()->defaultToken($token, 'decoration');
        if (isset($this->context[$token])) {
            $decoration_token_values = $this->context[$token];
        } else {
            $decoration_token_values = array();
        }

        // span: "<span style='{$style}' class='{$class}'>{$0}</span>"
        // tr("[span: Hello world]", :span => array('style' => 'font-weight:bold', 'class' => 'tml_class'))
        if (ArrayUtils::isHash($decoration_token_values)) {
            $default_decoration =  str_replace('{$0}', $value, $default_decoration);
            foreach($decoration_token_values as $key=>$value) {
                $default_decoration = str_replace('{$' . $key . '}', $value, $default_decoration);
            }
            return $default_decoration;
        }

        return $value;
    }

    /**
     * @param string $token
     * @param string $value
     * @return string
     * @throws \tml\TmlException
     */
    function apply($token, $value) {
        if ($token == self::RESERVED_TOKEN) return $value;
        if (!$this->isTokenAllowed($token)) return $value;

        if (!isset($this->context[$token])) {
            if ($this->isDefaultDecoration($token)) {
                return $this->defaultDecoration($token, $value);
            }
            return $value;
        }

        $method = $this->context[$token];

        if (is_callable($method)) {
            return $method($value);
        }

        if (is_string($method)) {
            return str_replace('{$0}', $value, $method);
        }

        return $this->defaultDecoration($token, $value);
    }

    /**
     * @param mixed[] $expr
     * @return string
     */
    function evaluate($expr) {
        if (!is_array($expr)) {
            return $expr;
        }

        $token = $expr[0];
        $args = array_slice($expr, 1);
        $value = implode('', array_map(array(&$this, "evaluate"), $args));

        return $this->apply($token, $value);
    }

    /**
     * @return string
     */
    function substitute() {
        return $this->evaluate($this->parse());
    }

}
