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

namespace tml;

class LanguageCase extends Base {

    /**
     * @var Application
     */
    public $application;

    /**
     * @var Language
     */
    public $language;

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $keyword;

    /**
     * @var string
     */
    public $latin_name;

    /**
     * @var string;
     */
    public $native_name;

    /**
     * @var string
     */
    public $description;

    /**
     * @var LanguageCaseRule[]
     */
    public $rules;

    /**
     * @param array $attributes
     */
    function __construct($attributes=array()) {
        parent::__construct($attributes);

        $this->rules = array();
        if (isset($attributes['rules'])) {
            foreach($attributes['rules'] as $rule) {
                array_push($this->rules, new LanguageCaseRule(array_merge($rule, array("language_case" => $this))));
            }
        }
    }

    /**
     * @return string
     */
    public function substitutionExpression() {
        return '/<\/?[^>]*>/';
    }

    /**
     * @param $value
     * @param null $object
     * @return null|LanguageCaseRule
     */
    public function findMatchingRule($value, $object = null) {
        foreach($this->rules as $rule) {
            if ($rule->evaluate($value, $object) == true)
                return $rule;
        }

        return null;
    }

    /**
     * @param $value
     * @param null $object
     * @param array $options
     * @return mixed
     */
    public function apply($value, $object = null, $options = array()) {
        $tags = array();
        preg_match_all($this->substitutionExpression(), $value, $tags);
        $tags = array_unique($tags[0]);
        $sanitized_value = preg_replace($this->substitutionExpression(), '', $value);

        $decorator = Decorators\Base::decorator();

        if ($this->application == 'phrase') {
            $elements = array($sanitized_value);
        } else {
            $elements = array_unique(preg_split('/[\s\/]/', $sanitized_value));
        }

        # replace html tokens with temporary placeholders {$h1}
        for($i=0; $i<count($tags); $i++) {
            $value = str_replace($tags[$i], '{$h' . $i . '}', $value);
        }

        # replace words with temporary placeholders {$w1}
        for($i=0; $i<count($elements); $i++) {
            $value = str_replace($elements[$i], '{$w' . $i . '}', $value);
        }

        $transformed_elements = array();
        foreach($elements as $element) {
            $rule = $this->findMatchingRule($element, $object);
            $case_value = ($rule==null ? $element : $rule->apply($element));
            array_push($transformed_elements, $decorator->decorateLanguageCase($this, $rule, $element, $case_value, $options));
        }

        # replace back temporary placeholders {$w1}
        for($i=0; $i<count($transformed_elements); $i++) {
            $value = str_replace('{$w' . $i . '}', $transformed_elements[$i], $value);
        }

        # replace back temporary placeholders {$h1}
        for($i=0; $i<count($tags); $i++) {
            $value = str_replace('{$h' . $i . '}', $tags[$i], $value);
        }

        return $value;
    }

}
