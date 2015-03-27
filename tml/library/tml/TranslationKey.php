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

use tml\tokens\DataTokenizer;
use tml\tokens\DecorationTokenizer;

class TranslationKey extends Base {
    /**
     * @var Application
     */
    public $application;

    /**
     * @var Language
     */
    public $language;

    /**
     * @var Translation[]
     */
    public $translations;

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $key;

    /**
     * @var string
     */
    public $label;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $locale;

    /**
     * @var int
     */
    public $level;

    /**
     * @var bool
     */
    public $locked;

    /**
     * @var Tokens\DataToken[]
     */
    public $tokens;

    /**
     * @var string[]
     */
    private $decoration_tokens;

    /**
     * @var object[]
     */
    private $data_tokens;

    /**
     * @var string[]
     */
    private $data_token_names;

    /**
     * @param array $attributes
     */
    public function __construct($attributes=array()) {
        parent::__construct($attributes);

        if ($this->key == null) {
		    $this->key = self::generateKey($this->label, $this->description);
        }

        if ($this->locale == null) {
            $this->locale = \tml\Config::instance()->blockOption("locale");
            if ($this->locale == null && $this->application)
                $this->locale = $this->application->default_locale;
        }

        if ($this->language == null && $this->application) {
            $this->language = $this->application->language($this->locale);
        }

        $this->translations = array();
        if (isset($attributes['translations'])) {
            $this->addTranslations($attributes['translations']);
        }
    }

    public function addTranslation($locale, $translation_json) {
        if ($this->translations == null)
            $this->translations = array();

        if (!isset($this->translations[$locale]))
            $this->translations[$locale] = array();

        $t = new Translation(array_merge($translation_json, array("translation_key"=>$this, "locale"=>$locale)));
        array_push($this->translations[$locale], $t);
    }

    public function addTranslations($translations_data) {
        foreach($translations_data as $locale => $translations) {
            foreach($translations as $translation_json) {
                $this->addTranslation($locale, $translation_json);
            }
        }
    }

    public function setTranslations($locale, $translations) {
        if ($this->translations == null)
            $this->translations = array();

        foreach($translations as $translation) {
            $translation->locale = $locale;
            $translation->translation_key = $this;
            $translation->language = $this->application->language($locale);
        }

        $this->translations[$locale] = $translations;
    }

    /**
     * @param string $label
     * @param string $description
     * @param string $locale
     * @return string
     */
    public static function cacheKey($locale, $label, $description) {
        return $locale . DIRECTORY_SEPARATOR . self::generateKey($label, $description);
    }

    /**
     * @param string $label
     * @param string $description
     * @return string
     */
    public static function generateKey($label, $description) {
		return md5($label . ";;;" . $description);
	}

    /**
     * @return bool
     */
    public function isLocked() {
        return ($this->locked == true);
    }

    /**
     * @param Language $language
     * @return bool
     */
    public function hasTranslations($language) {
        return count($this->translations($language)) > 0;
    }

    /**
     * @param Language $language
     * @param array $options
     * @return $this|null|TranslationKey
     */
    public function fetchTranslations($language, $options = array()) {
        if ($this->id && $this->hasTranslations($language))
            return $this;

        if (array_key_exists("dry", $options) ? $options["dry"] : Config::instance()->blockOption("dry")) {
            return $this->application->cacheTranslationKey($this);
        }

        if (Config::instance()->isCacheEnabled()) {
            $data = Cache::fetch(self::cacheKey($language->locale, $this->label, $this->description));
            if ($data == null)
                return $this;

            if (strstr($data, '},{') === false) {
                $this->addTranslation($language->locale, array("label" => $data));
            } else {
                $translations_json = json_decode($data, true);
                foreach($translations_json as $translation_json) {
                    $this->addTranslation($language->locale, $translation_json);
                }
            }
        }

        /** @var $translation_key TranslationKey */
        return $this->application->cacheTranslationKey($this);
    }

    /*
     * Re-assigns the ownership of the application and translation key
     *
     * @param Application $application
     */
    public function setApplication($application) {
        $this->application = $application;
        foreach($this->translations as $locale => $translations) {
            foreach($translations as $translation) {
                $translation->translation_key = $this;
            }
        }
    }

    /**
     * @param Language $language
     * @param Translation[] $translations
     */
    public function setLanguageTranslations($language, $translations) {
        foreach($translations as $translation) {
            $translation->setTranslationKey($this);
        }
        $this->translations[$language->locale] = $translations;
    }

    /**
     * @param Language $language
     * @return Translation[]
     */
    public function translations($language) {
        if ($this->translations == null) return array();
        if (!array_key_exists($language->locale, $this->translations)) return array();
        return $this->translations[$language->locale];
    }

    /**
     * @param Translation $a
     * @param Translation $b
     * @return bool
     */
    public function compareTranslations($a, $b) {
        return $a->precedence >= $b->precedence;
    }

    /**
     * @param Language $language
     * @param mixed[] $token_values
     * @return null|Translation
     */
    protected function findFirstValidTranslation($language, $token_values) {
        $translations = $this->translations($language);

        usort($translations, array($this, 'compareTranslations'));

        foreach($translations as $translation) {
            if ($translation->isValidTranslation($token_values)) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * @param Language $language
     * @param mixed[] $token_values
     * @param array $options
     * @return string
     */
    public function translate($language, $token_values = array(), $options = array()) {
//        Logger::instance()->debug("Translating $this->label from $this->locale to $language->locale");

        if (Config::instance()->isDisabled() || ($language->locale == $this->locale)) {
            return $this->substituteTokens($this->label, $token_values, $language, $options);
        }

        $translation = $this->findFirstValidTranslation($language, $token_values);
        $decorator = Decorators\Base::decorator();

        if ($translation != null) {
            $translated_label = $this->substituteTokens($translation->label, $token_values, $translation->language, $options);
            return $decorator->decorate($translated_label, $translation->language, $language, $this, $options);
        }

        $translated_label =  $this->substituteTokens($this->label, $token_values, $this->language, $options);
        return $decorator->decorate($translated_label, $this->language, $language, $this, $options);
	}

    /**
     * Returns an array of decoration tokens from the translation key
     * @return \string[]
     */
    public function decorationTokens() {
        if ($this->decoration_tokens == null) {
            $dt = new DecorationTokenizer($this->label);
            $dt->parse();
            $this->decoration_tokens = $dt->tokens;
        }

        return $this->decoration_tokens;
    }

    /**
     * Returns an array of data tokens from the translation key
     *
     * @return \mixed[]
     */
    public function dataTokens() {
        if ($this->data_tokens == null) {
            $dt = new DataTokenizer($this->label);
            $this->data_tokens = $dt->tokens;
        }

        return $this->data_tokens;
    }

    /**
     * @return array|\string[]
     */
    public function dataTokenNamesMap() {
        if ($this->data_token_names == null) {
            $this->data_token_names = array();
            foreach($this->dataTokens() as $token) {
                $this->data_token_names[$token->name()] = true;
            }
        }

        return $this->data_token_names;
    }

    /**
     * @param string $label
     * @param mixed[] $token_values
     * @param Language $language
     * @param array $options
     * @return string
     */
    public function substituteTokens($label, $token_values, $language, $options = array()) {
        if (strpos($label, '{') !== FALSE) {
            $dt = new DataTokenizer($label, $token_values, array("allowed_tokens" => $this->dataTokenNamesMap()));
            $label = $dt->substitute($language, $options);
        }

        if (strpos($label, '[') === FALSE) return $label;
        $dt = new DecorationTokenizer($label, $token_values, array("allowed_tokens" => $this->decorationTokens()));
        return $dt->substitute();
    }


}
