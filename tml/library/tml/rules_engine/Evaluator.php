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

namespace tml\rules_engine;

use tml\TmlException;

class Evaluator {

    /**
     * @var array
     */
    public $env;

    /**
     * @var array
     */
    public $vars;

    /**
     * Initializes evaluator
     */
    function __construct() {
        $this->vars = array();
        $this->env = array(
            "label"     => function($e, $args)      { $e->vars[$args[0]] = $e->env[$args[0]] = $args[1]; return $args[1]; },
            "quote"     => function($e, $args)      { return $args[0]; },
            "car"       => function($e, $args)      { return $args[0][1]; },
            "cdr"       => function($e, $args)      { return array_slice($args[0], 1); },
            "cons"      => function($e, $args)      { return array_merge(array($args[0]), $args[1]); },
            "eq"        => function($e, $args)      { return ($args[0] == $args[1]); },
            "atom"      => function($e, $args)      { return (is_array($args[0]) ? false : true); },
            "cond"      => function($e, $args)      { return ($e->evaluate($args[0]) ? $e->evaluate($args[1]) : $e->evaluate($args[2])); },

            "="         => function($e, $args)      { return $e->env["eq"]($e, $args); },
            "!="        => function($e, $args)      { return !$e->env["eq"]($e, $args); },
            "<"         => function($e, $args)      { return $args[0] < $args[1]; },
            ">"         => function($e, $args)      { return $args[0] > $args[1]; },
            "+"         => function($e, $args)      { return $args[0] + $args[1]; },
            "-"         => function($e, $args)      { return $args[0] - $args[1]; },
            "*"         => function($e, $args)      { return $args[0] * $args[1]; },
            "/"         => function($e, $args)      { return $args[0] / $args[1]; },

            "%"         => function($e, $args)      { return $args[0] % $args[1]; },
            "mod"       => function($e, $args)      { return $e->env["%"]($e, $args); },

            "true"      => function($e, $args)      { return true; },
            "false"     => function($e, $args)      { return false; },

            "!"         => function($e, $args)      { return !$args[0]; },
            "not"       => function($e, $args)      { return $e->env["!"]($e, $args); },

            "&&"        => function($e, $args)      {
                                                        foreach($args as $arg) {
                                                            if (!$e->evaluate($arg))
                                                                return false;
                                                        }
                                                        return true;
                                                    },
            "and"       => function($e, $args)      { return $e->env["&&"]($e, $args); },
            "||"        => function($e, $args)      {
                                                        foreach($args as $arg) {
                                                            if ($e->evaluate($arg))
                                                                return true;
                                                        }
                                                        return false;
            },
            "or"       => function($e, $args)      { return $e->env["||"]($e, $args); },

            "if"       => function($e, $args)      { return $e->env["cond"]($e, $args); },
            "let"      => function($e, $args)      { return $e->env["label"]($e, $args); },

            "date"     => function($e, $args)      { return date_parse($args[0]); },
            "today"    => function($e, $args)      { return new DateTime(); },
            "time"     => function($e, $args)      { return date_parse($args[0]); },
            "now"      => function($e, $args)      { return new DateTime(); },

            "append"   => function($e, $args)      { return "" . $args[1] . $args[0]; },
            "prepend"  => function($e, $args)      { return "" . $args[0] . $args[1]; },

            "match"    => function($e, $args)      {
                                                        $regex = $args[0];
                                                        if (!preg_match('/^\//', $regex)) {
                                                            $regex = '/' . $regex . '/';
                                                        }
                                                        return preg_match($regex, $args[1]);
            },
            "in"       => function($e, $args)      {
                                                        $search = trim($args[1]);
                                                        $values = explode(',', $args[0]);
                                                        foreach ($values as $value) {
                                                            $value = trim($value);
                                                            if (strpos($value, '..') !== false) {
                                                                $bounds = explode('..', $value);
                                                                $range = range(trim($bounds[0]), trim($bounds[1]));
                                                                if (in_array($search, $range))
                                                                    return true;
                                                            } else if ($value == $search)
                                                                return true;
                                                        }
                                                        return false;
            },
            "within"   => function($e, $args)      {
                                                        $bounds = explode('..', $args[0]);
                                                        $value = (float) $args[1];
                                                        return (trim($bounds[0]) <= $value && $value <= trim($bounds[1]));
            },
            "replace"  => function($e, $args)      {
                                                        $regex = $args[0];
                                                        if (!preg_match('/^\//', $regex)) {
                                                            $regex = '/' . $regex . '/';
                                                        }
                                                        return preg_replace($regex, $args[1], $args[2]);
            },
            "count"    => function($e, $args)      {
                                                        $list = is_string($args[0]) ? $e->vars[$args[0]] : $args[0];
                                                        return count($list);
            },
            "all"      => function($e, $args)      {
                                                        $list = is_string($args[0]) ? $e->vars[$args[0]] : $args[0];
                                                        if (count($list) == 0) return false;
                                                        foreach ($list as $item) {
                                                            if ($item != $args[1])
                                                                return false;
                                                        }
                                                        return true;
            },
            "any"      => function($e, $args)      {
                                                        $list = is_string($args[0]) ? $e->vars[$args[0]] : $args[0];
                                                        if (count($list) == 0) return false;
                                                        foreach ($list as $item) {
                                                            if ($item == $args[1])
                                                                return true;
                                                        }
                                                        return false;
            },
        );
    }

    /**
     * Resets evaluator's variables
     */
    function reset() {
        foreach ($this->vars as $key=>$val) {
            unset($this->env[$key]);
        }
        $this->vars = array();
    }

    /**
     * @param $fn
     * @return bool
     */
    function isNestedExpression($fn) {
       return in_array($fn, array("quote", "car", "cdr", "cond", "if",
           "&&", "||", "and", "or", "true", "false", "let", "count", "all", "any"));
    }

    /**
     * @param string $fn
     * @param array $args
     * @return mixed
     * @throws TmlException
     */
    function apply($fn, $args) {
        if (!isset($this->env[$fn])) {
            throw (new TmlException("Undefined expression: " . $fn));
        }

        if (is_callable($this->env[$fn])) {
            return $this->env[$fn]($this, $args);
        }

        return $this->env[$fn];
    }

    /**
     * @param $expr
     * @return mixed
     */
    function evaluate($expr) {
        if ($this->env["atom"]($this, array($expr))) {
            return (array_key_exists("" . $expr, $this->env) ? $this->env[$expr] : $expr);
        }

        $fn = $expr[0];
        $args = array_slice($expr, 1);

        if (!$this->isNestedExpression($fn)) {
            $args = array_map(array(&$this, "evaluate"), $args);
        }

        return $this->apply($fn, $args);
    }
}
