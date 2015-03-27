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

/**
 *
 * Decoration Token Forms:
 *
 * [link: click here]
 *
 * Decoration Tokens Allow Nesting:
 *
 * [link: {count} {_messages}]
 * [link: {count||message}]
 * [link: {count||person, people}]
 * [link: {user.name}]
 *
*/

class DataTokenizer {

    /**
     * @var object[]
     */
    public $tokens;

    /**
     * @return array
     */
    public static function supportedTokens() {
        return array(
            '\tml\tokens\DataToken',
            '\tml\tokens\MethodToken',
            '\tml\tokens\PipedToken');
    }

    /**
     * @param string $text
     * @param array $context
     * @param array $opts
     */
    function __construct($text, $context = array(), $opts = array()) {
        $this->text = $text;
        $this->context = $context;
        $this->opts = $opts;
        $this->tokenize();
    }

    /**
     *
     */
    public function tokenize() {
        $this->tokens = array();
        foreach(self::supportedTokens() as $class) {
            preg_match_all($class::expression(), $this->text, $matches);
            $matches = array_unique($matches[0]);
            foreach($matches as $token) {
                array_push($this->tokens, new $class($this->text, $token));
            }
        }
    }

    /**
     * @param string $token
     * @return bool
     */
    function isTokenAllowed($token) {
        if (!isset($this->opts["allowed_tokens"]))
            return true;
        return isset($this->opts["allowed_tokens"][$token]);
    }

    /**
     * @param \tml\Language $language
     * @param array $options
     * @return string
     */
    public function substitute($language, $options = array()) {
        $label = $this->text;
        foreach($this->tokens as $token) {
            if (!$this->isTokenAllowed($token->name())) continue;
            $label = $token->substitute($label, $this->context, $language, $options);
        }
        return $label;
    }

}
