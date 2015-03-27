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

namespace tml\cache\generators;

use tml\Config;
use tml\Logger;
use tml\Source;
use tml\utils\StringUtils;

class FileGenerator extends Base {

    /**
     * @var array[]
     */
    private $languages;

    private $current_cache_path;

    private $symlink_cache_path;

    /**
     * @return FileGenerator
     */
    public static function instance() {
        static $inst = null;
        if ($inst === null) {
            $inst = new FileGenerator();
        }
        return $inst;
    }

    public function run() {
        $this->started_at = new \DateTime();

        $this->cacheApplication();
        $this->languages = $this->cacheLanguages();
        $this->cacheTranslations();

        $this->generateSymlink();
        $this->finalize();
    }


    function currentCachePath() {
        if ($this->current_cache_path == null) {
            $this->current_cache_path = $this->baseCachePath() . DIRECTORY_SEPARATOR . $this->datedFileName();
            $this->log("Current cache path: " . $this->current_cache_path);
            if (!file_exists($this->current_cache_path)) mkdir($this->current_cache_path, 0777, true);
        }
        return $this->current_cache_path;
    }

    /**
     * @param string|array $key
     * @param string|null $data
     */
    public function cache($path, $data) {
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        if (count($parts) > 1) { // if directories included in the path
            array_pop($parts);
            $file_path = $this->currentCachePath() . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
            if (!file_exists($file_path)) mkdir($file_path, 0777, true);
        }
        $file_path = $this->currentCachePath() . DIRECTORY_SEPARATOR . $path . ".json";
        $this->log("Saving file: " . $file_path);

        if (Config::instance()->configValue("cache.pretty", false)) {
            $data = StringUtils::prettyPrint($data);
        }

        file_put_contents($file_path, $data);
    }

    function generateSymlink() {
        chdir($this->baseCachePath());

        if(file_exists("current")) {
            if(is_link("current")) {
                unlink("current");
            }
        }

        symlink($this->datedFileName(), 'current');
    }

    function symlinkPath() {
        if ($this->symlink_cache_path == null) {
            $this->symlink_cache_path = $this->baseCachePath() . DIRECTORY_SEPARATOR . 'current';
        }
        return $this->symlink_cache_path;
    }

    /**
     * Caches translations
     */
    private function cacheTranslations() {
        $this->log("Downloading translations...");
        $sources = Config::instance()->application->apiClient()->get("applications/current/sources");
        foreach($this->languages as $language) {
            $this->log("--------------------------------------------------------------");
            $this->log($language["locale"]. " locale...");
            $this->log("--------------------------------------------------------------");
            foreach($sources as $source) {
                $this->log("Downloading ". $source["source"] . " in " . $language["locale"]. "...");
                $key = Source::generateKey($source["source"]);
                $data = Config::instance()->application->apiClient()->get("sources/$key/translations", array("locale" => $language["locale"], "original" => "true", "per_page" => 10000));
                $key = Source::cacheKey($source["source"], $language["locale"]);
                $this->cache($key, json_encode($data, JSON_PRETTY_PRINT));
            }
        }
    }

}
