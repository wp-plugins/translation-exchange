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

use DateTime;
use tml\Application;
use tml\Config;
use tml\Language;

abstract class Base extends \tml\Base {

    /**
     * @var String
     */
    private $base_cache_path;

    /**
     * @var DateTime
     */
    protected $started_at;

    public abstract function run();
    abstract function cache($key, $data);
    abstract function symlinkPath();

    function log($msg) {
        $date = new DateTime();
        print($date->format('Y:m:d H:i:s') . ": " . $msg . "\n");
    }

    function finalize() {
        $this->log("Cache has been created");

        $finished_at = new DateTime();
        $since_start = $this->started_at->diff($finished_at);
        $minutes = $since_start->days * 24 * 60;
        $minutes += $since_start->h * 60;
        $minutes += $since_start->i;

        if ($minutes > 0)
            $this->log("Cache generation took " . $minutes .  " minutes");
        else
            $this->log("Cache generation took " . $since_start->s . " seconds");

        $this->log("Done.");
    }

    function baseCachePath() {
        if ($this->base_cache_path == null) {
            $this->base_cache_path = Config::instance()->configValue("cache.path", Config::instance()->rootPath() . DIRECTORY_SEPARATOR . "cache");
            if (!file_exists($this->base_cache_path)) mkdir($this->base_cache_path, 0777, true);
            $this->log("Base cache path: " . $this->base_cache_path);
        }
        return $this->base_cache_path;
    }

    function datedFileName() {
        return $this->started_at->format('YmdHi');
    }

    /**
     * Caches application data
     */
    function cacheApplication() {
        $this->log("Downloading application...");
        $app = Config::instance()->application->apiClient()->get("applications/current", array("definition" => "true"));
        $this->cache(Application::cacheKey(), json_encode($app));
        $this->log("Application has been cached.");
        return $app;
    }

    /**
     * Caches application languages with full definition
     */
    function cacheLanguages() {
        $this->log("Downloading languages...");
        $count = 0;
        $languages = Config::instance()->application->apiClient()->get("applications/current/languages");
        foreach ($languages as $lang) {
            $data = Config::instance()->application->apiClient()->get("languages/" . $lang["locale"], array("definition" => "true"));
            $this->cache(Language::cacheKey($lang["locale"]), json_encode($data));
            $count += 1;
        }
        $this->log("$count languages have been cached.");
        return $languages;
    }

}
