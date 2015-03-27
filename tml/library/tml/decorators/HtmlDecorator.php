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

namespace tml\decorators;

use tml\Config;

class HtmlDecorator extends Base {

    /**
     * @param string $translated_label
     * @param \tml\Language $translation_language
     * @param \tml\Language $target_language
     * @param \tml\TranslationKey $translation_key
     * @param array $options
     * @return mixed|string|\tml\Language
     */
    public function decorate($translated_label, $translation_language, $target_language, $translation_key, $options) {
        if (array_key_exists("skip_decorations", $options)) return $translated_label;

        if ($translation_key->locale == $target_language->locale) return $translated_label;

        $config = Config::instance();

        if ($config->current_translator == null) return $translated_label;
        if (!$config->current_translator->isInlineModeEnabled()) return $translated_label;
        if ($translation_key->isLocked() && !$config->current_translator->isManager()) return $translated_label;

        $classes = array('tml_translatable');

        if ($translation_key->isLocked()) {
            if ($config->current_translator->isFeatureEnabled('show_locked_keys')) {
                array_push($classes, 'tml_locked');
            } else {
                return $translated_label;
            }
        } else if ($translation_language->locale == $translation_key->language->locale) {
            array_push($classes, 'tml_not_translated');
        } else if ($translation_language->locale == $target_language->locale) {
            array_push($classes, 'tml_translated');
        } else {
            array_push($classes, 'tml_fallback');
        }

        $element = "span";
        if (isset($options["use_div"])) {
            $element = "div";
        }

        $html = "<".$element." class='" . implode(' ', $classes);
        $html = $html . "' data-translation_key='" . $translation_key->key;
        $html = $html . "' data-target_locale='" . $target_language->locale;
        $html = $html . "'>";
        $html = $html . $translated_label;
        $html = $html . "</".$element.">";

        return $html;
    }

    /**
     * @param \tml\LanguageCase $language_case
     * @param \tml\LanguageCaseRule $rule
     * @param string $original
     * @param string $transformed
     * @param array $options
     * @return mixed
     */
    public function decorateLanguageCase($language_case, $rule, $original, $transformed, $options) {
        if (array_key_exists("skip_decorations", $options)) return $transformed;

        $config = Config::instance();

        if ($config->current_translator == null) return $transformed;
        if (!$config->current_translator->isInlineModeEnabled()) return $transformed;

        $data = array(
            'keyword'       => $language_case->keyword,
            'language_name' => $language_case->language->english_name,
            'latin_name'    => $language_case->latin_name,
            'native_name'   => $language_case->native_name,
            'conditions'    => ($rule ? $rule->conditions : ''),
            'operations'    => ($rule ? $rule->operations : ''),
            'original'      => $original,
            'transformed'   => $transformed
        );

        $attributes = array(
            'class'         => 'tml_language_case',
            'data-locale'   => $language_case->language->locale,
            'data-rule'     => urlencode(str_replace("\n", '', base64_encode(json_encode($data))))
        );

        $query = array();
        foreach($attributes as $name => $value) {
            array_push($query, $name . "=\"" . str_replace("\"", '"', $value) . "\"");
        }

        $element = "span";
        if (isset($options["use_div"])) {
            $element = "div";
        }

        $html = "<" . $element . " " . implode(" ", $query) . ">";
        $html = $html . $transformed;
        $html = $html . "</".$element.">";

        return $html;
    }

}
