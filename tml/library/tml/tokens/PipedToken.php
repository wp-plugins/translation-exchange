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

use tml\TmlException;
use tml\utils\ArrayUtils;
use tml\Logger;

#######################################################################
#
# Transform Token Form
#
# {count:number || one: message, many: messages}
# {count:number || one: сообщение, few: сообщения, many: сообщений, other: много сообщений}   in other case the number is not displayed#
#
# {count | message}   - will not include {count}, resulting in "messages" with implied {count}
# {count | message, messages}
#
# {count:number | message, messages}
#
# {user:gender | he, she, he/she}
#
# {user:gender | male: he, female: she, other: he/she}
#
# {now:date | did, does, will do}
# {users:list | all male, all female, mixed genders}
#
# {count || message, messages}  - will include count:  "5 messages"
#
#######################################################################

class PipedToken extends DataToken {

    /**
     * @var string
     */
    public $separator;

    /**
     * @var string[]
     */
    public $parameters;

    /**
     * @return string
     */
    public static function  expression() {
        return '/(\{[^_:|][\w]*(:[\w]+)*(::[\w]+)*\s*\|\|?[^{^}]+\})/';
    }

    /**
     * Parses token elements
     */
    public function parse() {
        $name_without_parens = preg_replace('/[{}]/', '', $this->full_name);

        $parts = explode('|', $name_without_parens);
        $name_without_pipes = trim($parts[0]);

        $parts = explode('::', $name_without_pipes);
        $name_without_case_keys = trim($parts[0]);
        array_shift($parts);
        $this->case_keys = array_map('trim', $parts);

        $parts = explode(':', $name_without_case_keys);
        $this->short_name = trim($parts[0]);
        array_shift($parts);
        $this->context_keys = array_map('trim', $parts);

        $this->separator = (strpos($this->full_name,'||') !== false) ? '||' : '|';

        $this->parameters = array();
        $parts = explode($this->separator, $name_without_parens);
        if (count($parts) > 1) {
            $parts = explode(',', $parts[1]);
            foreach($parts as $part) {
                array_push($this->parameters, trim($part));
            }
        }
    }

    /**
     * @return bool
     */
    public function isValueDisplayedInTranslation() {
        return ($this->separator == '||');
    }

    /**
     * token:      {count|| one: message, many: messages}
     * results in: {"one": "message", "many": "messages"}
     *
     * token:      {count|| message}
     * transform:  [{"one": "{$0}", "other": "{$0::plural}"}, {"one": "{$0}", "other": "{$1}"}]
     * results in: {"one": "message", "other": "messages"}
     *
     * token:      {count|| message, messages}
     * transform:  [{"one": "{$0}", "other": "{$0::plural}"}, {"one": "{$0}", "other": "{$1}"}]
     * results in: {"one": "message", "other": "messages"}
     *
     * token:      {user| Dorogoi, Dorogaya}
     * transform:  ["unsupported", {"male": "{$0}", "female": "{$1}", "other": "{$0}/{$1}"}]
     * results in: {"male": "Dorogoi", "female": "Dorogaya", "other": "Dorogoi/Dorogaya"}
     *
     * token:      {actors:|| likes, like}
     * transform:  ["unsupported", {"one": "{$0}", "other": "{$1}"}]
     * results in: {"one": "likes", "other": "like"}
     *
     *
     * @param string[] $params
     * @param \tml\LanguageContext $context
     * @return array
     * @throws TmlException
     */
    public function generateValueMapForContext($context, $options = array()) {
        $values = array();

        if (strstr($this->parameters[0], ':')) {
            foreach($this->parameters as $param) {
                $name_value = explode(':', $param);
                $values[trim($name_value[0])] = trim($name_value[1]);
            }
            return $values;
        }

        $token_mapping = $context->token_mapping;

        if ($token_mapping == null) {
            $this->error("The token context ". $context->keyword . " does not support transformation for unnamed params");
            return null;
        }

        // "unsupported"
        if (is_string($token_mapping)) {
            $this->error("The token mapping $token_mapping does not support " . count($this->parameters));
            return null;
        }

        // ["unsupported", {}]
        if (is_array($token_mapping) && !ArrayUtils::isHash($token_mapping)) {
            if (count($this->parameters) > count($token_mapping)) {
                Logger::instance()->error("Mapping ",  $context->token_mapping);
                $this->error("The token mapping does not support " . count($this->parameters) . " parameters");
                return null;
            }

            $token_mapping = $token_mapping[count($this->parameters)-1];
            if (is_string($token_mapping)) {
                Logger::instance()->error("Mapping ",  $context->token_mapping);
                $this->error("The token mapping does not support " . count($this->parameters) . " parameters");
                return null;
            }
        }

        // {}
        foreach($token_mapping as $key => $value) {
            $values[$key] = $value;

            // token form {$0::plural} - number followed by language cases
            $keys = array();
            preg_match_all('/\{\$\d(::[\w]+)*\}/', $value, $keys);
            $keys = $keys[0];

            foreach($keys as $tkey) {
                $token = $tkey;
                $token_without_parens = preg_replace('/[{}]/', '', $token);
                $parts = explode('::', $token_without_parens);
                $index = preg_replace('/[$]/', '', $parts[0]);

                if (count($this->parameters) < $index) {
                    $this->error("The index inside " . $token_mapping . " is out of bound: " . $this->full_name);
                    return null;
                }

                $val = $this->parameters[$index];

                // TODO: check if language cases are enabled
                foreach(array_slice($parts, 1) as $case_key) {
                    $lcase = $context->language->languageCase($case_key);
                    if ($lcase === null) {
                        $this->error("Language case " . $case_key . " for context " . $context->keyword . "  mapping " . $key . " is not defined");
                        return null;
                    }

                    $val = $lcase->apply($val, null, $options);
                }

                $values[$key] = str_replace($tkey, $val, $values[$key]);
            }
        }

        return $values;
    }

    /**
     * @param string $label
     * @param \mixed[] $token_values
     * @param \tml\Language $language
     * @param array $options
     * @return mixed|string
     * @throws TmlException
     */
    public function substitute($label, $token_values, $language, $options = array()) {
        if (!array_key_exists($this->name(), $token_values)) {
            $this->error("Missing value");
            return $label;
        }

        $object = self::tokenObject($token_values, $this->name());

        if (count($this->parameters) == 0) {
            $this->error("Piped params may not be empty");
            return $label;
        }

        $context = $this->contextForLanguage($language);

        $piped_values = $this->generateValueMapForContext($context, $options);

        if ($piped_values == null)
            return $label;

        $rule = $context->findMatchingRule($object);

        if ($rule == null) return $label;

        if (isset($piped_values[$rule->keyword])) {
            $value = $piped_values[$rule->keyword];
        } else {
            $fallback_rule = $context->fallbackRule();
            if ($fallback_rule && isset($piped_values[$fallback_rule->keyword])) {
                $value = $piped_values[$fallback_rule->keyword];
            } else {
                return $label;
            }
        }

        $token_value = array();
        if ($this->isValueDisplayedInTranslation()) {
            array_push($token_value, $this->tokenValue($token_values, $language, $options));
            array_push($token_value, " ");
        } else {
            $value = str_replace("#" . $this->short_name . "#", $this->tokenValue($token_values, $language, $options), $value);
        }

        array_push($token_value, $value);

        return str_replace($this->full_name, implode("", $token_value), $label);
    }

}
