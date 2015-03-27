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

class Source extends Base {

    /**
     * @var Application
     */
    public $application;

    /**
     * @var string
     */
    public $key;

    /**
     * @var string
     */
    public $source;

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $description;

    /**
     * @var array
     */
    public $translations;

    /**
     * @param array $attributes
     */
    function __construct($attributes=array()) {
        parent::__construct($attributes);

        $this->translations = array();
        $this->key = self::generateKey($attributes['source']);
	}

    /**
     * @param string $source
     * @return string
     */
    public static function generateKey($source) {
        return md5($source);
    }

    /**
     * @param string $source_key
     * @param string $locale
     * @return string
     */
    public static function cacheKey($source_key, $locale) {
        if ($source_key[0] != DIRECTORY_SEPARATOR) $source_key = DIRECTORY_SEPARATOR . $source_key;
        $source_key = str_replace('.php', '', $source_key);
        if ($source_key == "" || $source_key == "/") $source_key = "index";
        return $locale . DIRECTORY_SEPARATOR . 'sources' . $source_key;
    }

    /**
     * @param $locale
     */
    public function fetchTranslations($locale) {
        if (!$this->translations)
            $this->translations = array();

        if (isset($this->translations[$locale]))
            return;

        $this->translations[$locale] = array();

        try {
            $results = $this->application->apiClient()->get(
                'sources/' . $this->key . '/translations',
                array('locale' => $locale, 'per_page' => 10000),
                array('cache_key' => self::cacheKey($this->source, $locale))
            );
        } catch (TmlException $e) {
//            Logger::instance()->debug("Failed to get the source: $e");
            return;
        }

        if ($results === null) return;

        foreach($results as $key => $data) {
            if (isset($data['translations']))
                $data = $data['translations'];

            $this->translations[$locale][$key] = array();

            foreach($data as $t) {
                array_push($this->translations[$locale][$key], new Translation(array(
                    "locale" => isset($t["locale"]) ? $t["locale"] : $locale,
                    "label" => isset($t["label"]) ? $t["label"] : '',
                    "context" => isset($t["context"]) ? $t["context"] : null
                )));
            }
        }
    }

    /**
     * @param string $locale
     * @param string $key
     * @return null|Translation[]
     */
    public function cachedTranslations($locale, $key) {
        if (!isset($this->translations[$locale])) return false;
        if (!array_key_exists($key, $this->translations[$locale])) return false;
        return $this->translations[$locale][$key];
    }

    /**
     *
     */
    public function resetCache() {
        foreach($this->application->languages as $lang) {
            Cache::instance()->delete(self::cacheKey($lang->locale, $this->source));
        }
    }

}
