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

class HtmlTokenizer {

    /**
     * @var string
     */
    public $text;

    /**
     * @var mixed[]
     */
    public $context;

    /**
     * @var mixed[]
     */
    public $options;

    /**
     * @var \DOMDocument
     */
    public $doc;

    /**
     * @var string
     */
    public $tml;

    /**
     * @param string $html
     * @param array $context
     * @param array $options
     */
    function __construct($html, $context = array(), $options = array()) {
        $this->html = $html;
        $this->context = $context;
        $this->options = $options;
        $this->tml = null;
        $this->tokenize();
    }

    /**
     *
     */
    private function parse() {
        $this->doc = new \DOMDocument();
        $this->doc->loadHTML($this->html);
    }

    /**
     * @param string $html
     * @return array
     */
    public function tokenize($html = null) {
        if ($html!=null) $this->html = $html;

        // remove all tabs and new lines - as they mean nothing in HTML
        $this->html = trim(preg_replace('/\t\n/', '', $this->html));

        // normalize multiple spaces to one space
        $this->html = preg_replace('/\s+/', ' ', $this->html);

        $this->parse();
        $this->tml = $this->tokenizeTree($this->doc);

//        print_r($this->tml);
//        print_r($this->context);
        return array($this->tml, $this->context);
    }

    /**
     * @param $node
     * @param $value
     * @return string
     */
    private function apply($node, $value) {
        if (!isset($node->tagName)) return $value;

        if (!$this->isTokenAllowed($node->tagName)) return $value;

        $context = $this->generateContext($node->tagName, $node->attributes);
        $token = $this->adjustName($node->tagName);
        $token = $this->contextize($token, $context);

        $break = '';
        if ($this->needsLineBreak($node)) {
            $break = "\n\n";
        }

        $value = $this->sanitizeValue($value);

        if ($this->isShortToken($token, $value))
            return '['.$token.': '.$value.']'.$break;

        return '['.$token.']'.$value.'[/'.$token.']'.$break;
    }

    /**
     * @param string $value
     * @return string
     */
    private function sanitizeValue($value) {
        $value = ltrim($value);
        return $value;
    }

    /**
     * @param string $text
     * @return mixed
     */
    private function generateDataTokens($text) {
        if (isset($this->options["data_tokens"]) && $this->options["data_tokens"]) {
            preg_match_all('/(\d+)/', $text, $matches);
            $matches = array_unique($matches[0]);

            $token_name = (isset($this->options["token_name"]) ? $this->options["token_name"] : 'num');

            foreach ($matches as $match) {
                $token = $this->contextize($token_name, $match);
                $text = str_replace($match, "{" . $token . "}", $text);
            }
        }
        return $text;
    }

    /**
     * @param $node
     * @return mixed|string
     */
    private function tokenizeTree($node) {
        if (get_class($node) == 'DOMText') {
            return $this->generateDataTokens($node->wholeText);
        }

        $values = array();
        if (isset($node->childNodes)) {
            foreach($node->childNodes as $child) {
                array_push($values, $this->tokenizeTree($child));
            }
        }

        $value = implode('', $values);
        return $this->apply($node, $value);
    }

    /**
     * @param string $name
     * @param $attributes
     * @return string
     */
    private function generateContext($name, $attributes) {
        $attributes_array = array();
        foreach($attributes as $attr) {
            $attributes_array[$attr->name] = $attr->value;
        }
        if (count($attributes_array) == 0)
            return '<'.$name.'>{$0}</'.$name.'>';

        $keys = array_keys($attributes_array);
        arsort($keys);

        $attr = array();
        foreach($keys as $key) {
            $value = $attributes_array[$key];
            $quote = "'";
            if (strpos($value, $quote) !== FALSE) $quote = '"';
            array_push($attr, $key.'='.$quote.$value.$quote);
        }
        $attr = implode(' ', $attr);
        return '<'.$name.' '.$attr.'>{$0}</'.$name.'>';
    }

    /**
     * @param string $name
     * @return string
     */
    private function adjustName($name) {
        $map = array(
            'b' => 'bold',
            'i' => 'italic',
            'a' => 'link'
        );

       return (isset($map[$name]) ? $map[$name] : $name);
    }

    /**
     * @param string $name
     * @param string $context
     * @return string
     */
    private function contextize($name, $context) {
        if (isset($this->context[$name])) {
            if ($this->context[$name] != $context) {
                $index = 0;
                if (preg_match_all("/.*?(\d+)$/", $name, $matches)>0) {
                    $index = $matches[count($matches)-1][0];
                    $name = str_replace($index, '', $name);
                }
                $name = $name . ($index + 1);
                return $this->contextize($name, $context);
            }
        }

        $this->context[$name] = $context;
        return $name;
    }

    /**
     * @param $node
     * @return bool
     */
    private function needsLineBreak($node) {
        if (!isset($node->tagName)) return false;
        return (in_array($node->tagName, array('p', 'h1', 'h2', 'h3', 'h4', 'h5', 'div')));

    }

    /**
     * @param string $token
     * @param string $value
     * @return bool
     */
    private function isShortToken($token, $value) {
        if (in_array($token, array('b', 'i'))) return true;
        if (strlen($value) < 10) return true;
        return false;
    }

    /**
     * @param string $token
     * @return bool
     */
    private function isTokenAllowed($token) {
        if (in_array($token, array('html', 'body'))) return false;
        return true;
    }

    /**
     *
     */
    public function debug() {
        print_r("\n\n");
        $this->printTree($this->doc);
        print_r("\n\n");
    }

    /**
     * @param $node
     * @return mixed
     */
    private function nodeInfo($node) {
        if ($node->nodeType == 1)
            return $node->tagName;

        if ($node->nodeType == 3)
            return $node->wholeText;

        return $node->nodeType;
    }

    /**
     * @param $node
     * @param int $depth
     */
    private function printTree($node, $depth = 0) {
        $padding = str_repeat(' ', $depth);
//        print($padding . ' ' . $node->tagName);

//        print_r($node);
        print_r($padding . " => " . get_class($node) . ": " . $this->nodeInfo($node) . "\n");

        if (isset($node->childNodes)) {
            foreach($node->childNodes as $child) {
                $this->printTree($child, $depth+1);
            }
        }
    }
}