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

namespace tml\utils;

class ArrayUtils {

    public static function flatten(array $array) {
        $return = array();
        array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
        return $return;
    }

    public static function split($value, $delimiter = ',') {
        if (!$value) return null;
        return array_map('trim', explode($delimiter, $value));
    }

    public static function isHash($arr) {
        if (!is_array($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public static function createAttribute(&$target, $parts, $value) {
        foreach ($parts as $sub) {
            if (! isset($target[$sub])) {
                $target[$sub] = array();
            }
            $target = & $target[$sub];
        }
        $target = $value;
    }

    public static function toHTMLAttributes($arr) {
        $attrs = array();
        foreach($arr as $key=>$value) {
             array_push($attrs, $key . '="' . $value . '"');
        }
        return implode($attrs, " ");
    }

    public static function trim($array) {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = self::trim($value);
            }
        }

        return array_filter($array);
    }

    public static function normalizeTmlParameters($label, $description = "", $tokens = array(), $options = array()) {
        if (is_array($label)) return $label;

        if (is_array($description)) {
            return array(
                "label" => $label,
                "description" => "",
                "tokens" => $description,
                "options" => $tokens
            );
        }

        return array(
            "label" => $label,
            "description" => $description,
            "tokens" => $tokens,
            "options" => $options
        );
    }
}