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
use tml\Logger;
use tml\utils\ArrayUtils;

class DataToken {

    /**
     * @var string
     */
    public $label;

    /**
     * @var string
     */
    public $full_name;

    /**
     * @var string
     */
    public $short_name;

    /**
     * @var string[]
     */
    public $case_keys;

    /**
     * @var string[]
     */
    public $context_keys;

    /**
     * @return string
     */
    public static function expression() {
        return '/(\{[^_:][\w]*(:[\w]+)*(::[\w]+)*\})/';
    }

    public static function tokenWithName($name) {
        $class = get_called_class();
        return new $class("", $name);
    }

    /**
     * @param string $label
     * @param string $token
     */
    function __construct($label, $token) {
        $this->label = $label;
        $this->full_name = $token;
        $this->parse();
    }

    /**
     * Parses token name elements
     */
    function parse() {
        $name_without_parens = preg_replace('/[{}]/', '', $this->full_name);

        $parts = explode('::', $name_without_parens);
        $name_without_case_keys = trim($parts[0]);
        array_shift($parts);
        $this->case_keys = array_map('trim', $parts);

        $parts = explode(':', $name_without_case_keys);
        $this->short_name = trim($parts[0]);
        array_shift($parts);
        $this->context_keys = array_map('trim', $parts);
    }

    /**
     * @param array $opts
     * @return string
     */
    public function name($opts = array()) {
        $val = $this->short_name;
        if (isset($opts["context_keys"]) and count($this->context_keys) > 0)
            $val = $val . ":" . implode(':', $this->context_keys);

        if (isset($opts["case_keys"]) and count($this->case_keys) > 0)
            $val = $val . "::" . implode('::', $this->case_keys);

        if (isset($opts["parens"]))
            $val = "{" . $val . "}";

        return $val;
    }

    /**
     * For transform tokens, we can only use the first context key, if it is not mapped in the context itself.
     *
     * {user:gender | male: , female: ... }
     *
     * It is not possible to apply multiple context rules on a single token at the same time:
     *
     * {user:gender:value | .... hah?}
     *
     * It is still possible to setup dependencies on multiple contexts.
     *
     * {user:gender:value}   - just not with transform tokens
     *
     * @param \tml\Language $language
     * @param array $opts
     * @return \tml\LanguageContext|null
     * @throws \tml\TmlException
     */
    public function contextForLanguage($language, $opts = array()) {
        if (count($this->context_keys) > 0) {
            $ctx = $language->contextByKeyword($this->context_keys[0]);
        } else {
            $ctx = $language->contextByTokenName($this->short_name);
        }

        return $ctx;
    }

    /**
     * Returns an object from values hash.
     *
     * @param mixed[] $token_values
     * @param string $token_name
     * @return mixed
     */
    public static function tokenObject($token_values, $token_name) {
        if ($token_values == null)
            return null;

        if (!array_key_exists($token_name, $token_values))
            return null;

        $token_object = $token_values[$token_name];

        if (is_array($token_object)) {
            if (\tml\Utils\ArrayUtils::isHash($token_object)) {
                if (!array_key_exists('object', $token_object)) return null;
                return $token_object['object'];
            }
            if (count($token_object) == 0)
                return null;
            return $token_object[0];
        }

        return $token_object;
    }

    /**
     * gets the value based on various evaluation methods
     *
     * examples:
     *
     * tr("Hello {user}", array("user" => array($current_user, $current_user->name)))
     * tr("Hello {user}", array("user" => array($current_user, "@name")))
     * tr("Hello {user}", array("user" => array($current_user, "@@firstName")))
     *
     * tr("Hello {user}", {:user => array(array("name" => "Michael", "gender" => "male"), "Michael")))
     * tr("Hello {user}", {:user => array(array("name" => "Michael", "gender" => "male"), "@name")))
     *
     * @param $array
     * @param $language
     * @param $options
     */

    public function tokenValueFromArrayParam($token_data, $language, $options = array()) {
        // if you provided an array, it better have some values
        if (count($token_data) == 0)
            return $this->error("Invalid number of params of an array");

        $object = $token_data[0];
        $method = count($token_data) > 1 ? $token_data[1] : null;

        // if the first value of an array is an array handle it here
        if (is_array($object) && !(ArrayUtils::isHash($object))) {
            return $this->tokenValuesFromArray($token_data, $language, $options);
        }

        if ($method == null)
            return $this->sanitize("" . $object, $object, $language, array_merge($options, array("safe" => true)));

        if (is_string($method)) {
            if (preg_match('/^@/', $method))
                return $this->tokenValueFromObjectUsingAttributeMethod($object, $method, $language, $options);
            return $this->sanitize($method, $object, $language, array_merge($options, array("safe" => true)));
        }

        return $this->error("Unsupported second array value");
    }

    public function tokenValueFromObjectUsingAttributeMethod($object, $method, $language, $options = array()) {
        # method
        if (preg_match('/^@@/', $method)) {
            if (ArrayUtils::isHash($object))
                return $this->error("Can't call method on a hash");

            $method = substr($method, 2);

            if (!method_exists($object, $method))
                return $this->error("Method \"$method\" does not exist");

            return $this->sanitize($object->$method(), $object, $language, array_merge($options, array("safe" => false)));
        }

        $attribute = substr($method, 1);

        if (ArrayUtils::isHash($object)) {
            if (isset($object[$attribute]))
                return $this->sanitize($object[$attribute], $object, $language, array_merge($options, array("safe" => false)));
            else
                return $this->error("Hash attribute \"$attribute\" does not exist");
        }

        if (!property_exists($object, $attribute))
            return $this->error("Object attribute \"$attribute\" does not exist");

        return $this->sanitize($object->$attribute, $object, $language, array_merge($options, array("safe" => false)));
    }


   /**
     * examples:
     *
     * tr("Hello {user}", array("user" => array("value => "Michael", "gender" => "male")))
     *
     * tr("Hello {user}", array("user" => array("object" => array("gender" => "male"}, "value" => "Michael")))
     * tr("Hello {user}", array("user" => array("object" => array("name" => "Michael", "gender" => "male"}, "property" => "name")))
     * tr("Hello {user}", array("user" => array("object" => array("name" => "Michael", "gender" => "male"}, "attribute" => "name")))
     *
     * tr("Hello {user}", array("user" => array("object" => $user, "value" => "Michael")))
     * tr("Hello {user}", array("user" => array("object" => $user, "property" => "name")))
     * tr("Hello {user}", array("user" => array("object" => $user, "attribute" => "name")))
     * tr("Hello {user}", array("user" => array("object" => $user, "method" => "name")))
     *
     * @param $token_data
     * @param $language
     * @param $options
     */
    public function tokenValueFromHashParam($hash, $language, $options = array()) {
        $value = isset($hash["value"]) ? $hash["value"] : null;
        $object = isset($hash["object"]) ? $hash["object"] : null;

        if ($value != null) {
            $object = ($object == null ? $hash : $object);
            return $this->sanitize($value, $object, $language, array_merge($options, array("safe" => true)));
        }

        if ($object == null)
            return $this->error("No object or value are provided in the hash");

        $attribute = isset($hash["attribute"]) ? $hash["attribute"] : (isset($hash["property"]) ? $hash["property"] : null);

        if (ArrayUtils::isHash($object)) {
            if ($attribute == null)
                return $this->error("No attribute is provided for the hash object");

            if (!isset($object[$attribute]))
                return $this->error("Hash does not contain such attribute");

            return $this->sanitize($object[$attribute], $object, $language, array_merge($options, array("safe" => false)));
        }

        if ($attribute == null) {
            $method = isset($hash["method"]) ? $hash["method"] : null;
            if ($method == null)
                return $this->error("No attribute or method is provided for the hash object");

            if (!method_exists($object, $method))
                return $this->error("Method \"$method\" does not exist");

            return $this->sanitize($object->$method(), $object, $language, array_merge($options, array("safe" => false)));
        }

        if (!property_exists($object, $attribute))
            return $this->error("Object attribute \"$attribute\" does not exist");

        return $this->sanitize($object->$attribute, $object, $language, array_merge($options, array("safe" => false)));
    }


    /**
     *
     * tr("Hello {user_list}!", "", {:user_list => [[user1, user2, user3], :name]}}
     *
     * first element is an array, the rest of the elements are similar to the
     * regular tokens lambda, symbol, string, with parameters that follow
     *
     * if you want to pass options, then make the second parameter an array as well
     *
     * tr("{users} joined the site", {:users => [[user1, user2, user3], :name]})
     *
     * tr("{users} joined the site", {:users => [[user1, user2, user3], lambda{|user| user.name}]})
     *
     * tr("{users} joined the site", {:users => [[user1, user2, user3], {:attribute => :name})
     *
     * tr("{users} joined the site", {:users => [[user1, user2, user3], {:attribute => :name, :value => "<strong>{$0}</strong>"})
     *
     * tr("{users} joined the site", {:users => [[user1, user2, user3], "<strong>{$0}</strong>")
     *
     * tr("{users} joined the site", {:users => [[user1, user2, user3], :name, {
     *   :limit => 4,
     *   :separator => ', ',
     *   :joiner => 'and',
     *   :remainder => lambda{|elements| tr("*{count||other}", :count => elements.size)},
     *   :expandable => true,
     *   :collapsable => true
     * })
     *
     *
     * @param array $params
     * @param \tml\Language $language
     * @param array $options
     * @return string
     */
    public function tokenValuesFromArray($params, $language, $options = array()) {
        if (count($params) == 0)
            return $this->error("Invalid number of params of an array token");

        $list_options = array(
            "description" => "List joiner",
            "limit" => 4,
            "separator" => ", ",
            "joiner" => 'and',
            "less" => '{laquo} less',
            "expandable" => true,
            "collapsable" => true
        );

        $objects = $params[0];
        $method = count($params) > 1 ? $params[1] : null;

        if (count($params) > 2) {
            if (!ArrayUtils::isHash($params[2]))
                return $this->error("Array options must be a hash");
            $list_options = array_merge($list_options, $params[2]);
        }

        if (isset($options["skip_decorations"]) && $options["skip_decorations"])
            $list_options["expandable"] = false;

        $values = array();
        foreach ($objects as $object) {
            if ($method == null) {
                array_push($values, $this->sanitize('' . $object, $object, $language, array_merge($options, array("safe" => false))));
                continue;
            }

            if (is_string($method)) {
                if (preg_match('/^@/', $method)) {
                    array_push($values,
                               $this->tokenValueFromObjectUsingAttributeMethod($object, $method, $language, $options));
                } else {
                    if (ArrayUtils::isHash($object))
                            return $this->error("Hash object cannot be used with this method");

                    array_push($values, str_replace(
                            '{$0}',
                            $this->sanitize('' . $object, $object, $language, array_merge($options, array("safe" => false))),
                            $method)
                    );
                }
                continue;
            }

            if (ArrayUtils::isHash($method)) {
                $attribute = isset($method["attribute"]) ? $method["attribute"] : (isset($method["property"]) ? $method["property"] : null);
                $value = isset($method["value"]) ? $method["value"] : null;

                if ($attribute == null)
                    return $this->error("No attribute is provided for the hash object in the array");

                if (ArrayUtils::isHash($object)) {
                    if (!isset($object[$attribute]))
                        return $this->error("Hash object in the array does not contain such attribute");

                    $attribute = $this->sanitize($object[$attribute], $object, $language, array_merge($options, array("safe" => false)));
                } else {
                    if (!property_exists($object, $attribute))
                        return $this->error("Object attribute \"$attribute\" does not exist");

                    $attribute = $this->sanitize($object->$attribute, $object, $language, array_merge($options, array("safe" => false)));
                }

                if ($value != null)
                    array_push($values, str_replace('{$0}', $attribute, $value));
                else
                    array_push($values, $attribute);

                continue;
            }

            if (is_callable($method)) {
                array_push($values, $this->sanitize($method($object), $object, $language, array_merge($options, array("safe" => true))));
                continue;
            }
        }

        if (count($values) == 1)
            return $values[0];

        if ($list_options["joiner"] == "")
            return implode($list_options["separator"], $values);

        $joiner = $language->translate($list_options["joiner"], $list_options["description"], array(), $options);

        if (count($values) <= $list_options["limit"]) {
            $last = array_pop($values);
            return implode($list_options["separator"], $values) . " " . $joiner . " " . $last;
        }

        $displayed_values = array_slice($values, 0, $list_options["limit"]);
        $remaining_values = array_slice($values, $list_options["limit"]);

        $result = implode($list_options["separator"], $displayed_values);

        $other_values = $language->translate("{count||other}", $list_options["description"], array("count" => count($remaining_values)), $options);

        if (!isset($list_options["expandable"]) || $list_options["expandable"] !== true) {
            $result = $result . " " . $joiner . " ";
            if (isset($list_options["remainder"]) && is_callable($list_options["remainder"]))
                return $result . $list_options["remainder"]($remaining_values);
            return $result . $other_values;
        }

        $key = isset($list_options["key"]) ? $list_options["key"] : \tml\TranslationKey::generateKey($this->label, implode(",", $values));

        $result = $result . '<span id="tml_other_link_' . $key . '"> ' . $joiner . ' ';
        $result = $result . '<a href="#" class="tml_other_list_link" onClick="' . "document.getElementById('tml_other_link_$key').style.display='none'; document.getElementById('tml_other_elements_$key').style.display='inline'; return false;" . '">';

        if (isset($list_options["remainder"]) && is_callable($list_options["remainder"]))
            $result = $result . $list_options["remainder"]($remaining_values);
        else
            $result = $result . $other_values;
        $result = $result . "</a></span>";


        $result = $result . '<span id="tml_other_elements_' . $key . '" style="display:none">' . $list_options["separator"];
        $last_remaining = array_pop($remaining_values);
        $result = $result . implode($list_options["separator"], $remaining_values);
        $result = $result . " " . $joiner . " " . $last_remaining;

        if (isset($list_options["collapsable"]) && $list_options["collapsable"]) {
            $result = $result . ' <a href="#" class="tml_other_less_link" style="font-size:smaller;white-space:nowrap" onClick="' . "document.getElementById('tml_other_link_$key').style.display='inline'; document.getElementById('tml_other_elements_$key').style.display='none'; return false;" . '">';
            $result = $result . $language->translate($list_options["less"], $list_options["description"], array(), $options);
            $result = $result . "</a>";
        }

        $result = $result . "</span>";
        return $result;
    }

    /**
     * Returns a value from values hash.
     *
     * @param mixed[] $token_values
     * @param \tml\Language $language
     * @param array $options
     * @return string
     * @throws \tml\TmlException
     */
    public function tokenValue($token_values, $language, $options = array()) {
        if (isset($token_values[$this->short_name])) {
            $object = $token_values[$this->short_name];
        } else {
            $object = Config::instance()->defaultToken($this->short_name, 'data');
        }

        if ($object === null)
            return $this->error("Missing token value");

        if (is_string($object) || is_numeric($object) || is_double($object)) {
            return $this->sanitize($object, $object, $language, array_merge($options, array("safe" => true)));
        }

        if (is_array($object)) {
            if (\tml\Utils\ArrayUtils::isHash($object))
                return $this->tokenValueFromHashParam($object, $language, $options);
            return $this->tokenValueFromArrayParam($object, $language, $options);
        }

        return $this->sanitize($object, $object, $language, array_merge($options, array("safe" => false)));
    }

    /**
     * @param string $msg
     * @return string
     */
    public function error($msg) {
        Logger::instance()->error($this->full_name . " in \"" . $this->label . "\" : " . $msg);
        return $this->full_name;
    }


    /**
     * Applies a language case. The case is identified with ::
     *
     * tr("Hello {user::nom}", "", :user => current_user)
     * tr("{actor} gave {target::dat} a present", "", :actor => user1, :target => user2)
     * tr("This is {user::pos} toy", "", :user => current_user)
     *
     * @param \tml\LanguageCase $case
     * @param mixed $token_value
     * @param mixed[] $token_values
     * @param \tml\Language $language
     * @param array $options
     * @return string
     */
    public function applyCase($key, $value, $object, $language, $options) {
        $case = $language->languageCase($key);
        if ($case == null) return $value;
        return $case->apply($value, $object, $options);
    }


    /**
     * @param mixed $token_object
     * @param mixed[] $token_values
     * @param \tml\Language $language
     * @param mixed[] $options
     * @return string
     */
    public function sanitize($value, $object, $language, $options) {
        $value = "" . $value;

        if (!isset($options["safe"]) || !$options["safe"]) {
            $value = htmlspecialchars($value);
        }

        if (isset($this->case_keys)) {
            foreach($this->case_keys as $case) {
//                Logger::instance()->debug("Applying $case in " . $language->locale);
                $value = $this->applyCase($case, $value, $object, $language, $options);
            }
        }

        return $value;
    }

    /**
     * Main substitution function
     *
     * @param string $label
     * @param mixed[] $token_values
     * @param \tml\Language $language
     * @param mixed[] $options
     * @return mixed
     */
    public function substitute($label, $token_values, $language, $options = array()) {
//        Logger::instance()->debug("Substituting $label in " . $language->locale);
        $token_value = $this->tokenValue($token_values, $language, $options);
        return str_replace($this->full_name, $token_value, $label);
    }

    /**
     * @return string
     */
    function __toString() {
        return $this->full_name;
    }
}

