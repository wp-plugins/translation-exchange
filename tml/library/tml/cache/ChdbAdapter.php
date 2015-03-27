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

namespace tml\cache;

use tml\Application;
use tml\Component;
use tml\Language;
use tml\Source;
use tml\Translation;

class ChdbAdapter extends Base {

    private $chdb;

    /**
     *
     */
    function __construct() {
        $this->chdb = new \chdb($this->chdbPath());
    }

    public function key() {
        return "chdb";
    }

    public function chdbPath() {
        return $this->currentCachePath() . ".chdb";
    }

    /**
     * @param string $key
     * @param null $default
     * @return array|null|Application|Component|Language|Source|Translation
     */
    public function fetch($key, $default = null) {
        $value = $this->chdb->get($key);
        if ($value) {
            $this->info("Cache hit " . $key);
            return $value;
        }

        $this->info("Cache miss " . $key);

        if ($default == null)
            return null;

        if (is_callable($default)) {
            $value = $default();
        } else {
            $value = $default;
        }

        return $value;
    }

    /**
     * @param string $key
     * @param $data
     * @return array|Application|Component|Language|Source|Translation
     */
    function deserializeObject($key, $data) {
        $prefix = substr($key, 0, 2);

        if ($prefix == 't@') {
//            Logger::instance()->info("Got translations", $data);

            if (strstr($data, '},{') === false) {
                return new Translation(array("label" => $data));
            }

            $translations_json = json_decode($data, true);
            $translations = array();
            foreach($translations_json as $json) {
                $t =  new Translation(array("label" => $json["label"]));
                if (isset($json["context"]))
                    $t->context = $json["context"];
                array_push($translations, $t);
            }
            return $translations;
        }


        return $data;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public function store($key, $value) {
        $this->warn("This is a readonly cache");
    }

    /**
     * @param string $key
     */
    public function delete($key) {
        $this->warn("This is a readonly cache");
    }

    /**
     * @param string $key
     * @return bool
     */
    public function exists($key) {
        $value = $this->chdb->get($key);
        return ($value!=null);
    }

    /**
     * @return bool
     */
    public function isCachedBySource() {
        return false;
    }

    public function isReadOnly() {
        return true;
    }

}
