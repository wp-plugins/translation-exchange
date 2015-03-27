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

use tml\tokens\DataToken;

class Translation extends Base {

    /**
     * @var TranslationKey
     */
    public $translation_key;

    /**
     * @var Language
     */
    public $language;

    /**
     * @var string
     */
    public $locale;

    /**
     * @var string
     */
    public $label;

    /**
     * @var array[]
     */
    public $context;

    /**
     * @var integer
     */
    public $precedence;

    /**
     * @return bool
     */
    public function hasContextRules() {
        return ($this->context != null and count($this->context) > 0);
    }

    /**
     * @param $token_values
     * @return bool
     */
    public function isValidTranslation($token_values) {
       if (!$this->hasContextRules())
           return true;

        foreach($this->context as $token_name=>$rules) {
            $token_object = DataToken::tokenObject($token_values, $token_name);
            if ($token_object === null)
                return false;

            foreach($rules as $context_key=>$rule_key) {
                if ($rule_key == "other") continue;

                $context = $this->language->contextByKeyword($context_key);
                if ($context == null) return false; // unsupported context type

                $rule = $context->findMatchingRule($token_object);
                if ($rule == null || $rule->keyword != $rule_key) return false;
            }
        }

        return true;
    }

}
