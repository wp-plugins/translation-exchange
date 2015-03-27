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

use tml\ApiClient;
use tml\Config;

class ChdbGenerator extends Base {

    /**
     * @var mixed[]
     */
    public $cache;

    /**
     * @var array[]
     */
    public $translations;

    /**
     * @var string
     */
    private $chdb_path;

    /**
     * @return ChdbGenerator
     */
    public static function instance() {
        static $inst = null;
        if ($inst === null) {
            $inst = new ChdbGenerator();
        }
        return $inst;
    }

    /**
     *
     */
    public function run() {
        $this->started_at = new \DateTime();
        $this->chdb_path = $this->baseCachePath() . DIRECTORY_SEPARATOR . $this->datedFileName() . ".chdb";

        $this->cache = array();
        $this->cacheApplication();
        $this->cacheLanguages();
        $this->cacheTranslations();

        $this->generateChdb();

        $this->generateSymlink();
        $this->finalize();
    }

    /**
     * @param string|array $key
     * @param string|null $data
     */
    public function cache($key, $data = null) {
        if (is_array($key)) {
            $this->cache = array_merge($this->cache, $key);
        } else {
            $this->cache[$key] = $data;
        }
    }

    /**
     * Caches translation keys
     */
    private function cacheTranslations() {
        $this->log("Downloading translations...");

        stream_wrapper_register("chdb", '\tml\cache\generators\ChdbStream') or die("Failed to register Chdb protocol for streaming Tml translation keys");
        $fp = fopen("chdb://ChdbInMemory", "r+");

        $ch = curl_init();
        $url = Config::instance()->application->host . ApiClient::API_PATH . "applications/current/translations?stream=true";
        $this->log("GET: " . $url);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 256);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FILE, $fp);

        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }

    /**
     * Generates chdb database
     */
    public function generateChdb() {
        $this->extracted_at = new \DateTime();

        $this->log("Writing chdb file...");
        $this->log("File: " . $this->chdb_path);

        $success = chdb_create($this->chdb_path, $this->cache);

        if (!$success) {
            fprintf(STDERR, "Failed to create chdb file $this->chdb_path\n");
            return;
        }
    }

    /**
     * @return string
     */
    function symlinkPath() {
        return $this->baseCachePath() . DIRECTORY_SEPARATOR . "current.chdb";
    }

    function generateSymlink() {
        if (file_exists($this->symlinkPath()))
            unlink($this->symlinkPath());
        symlink($this->chdb_path, $this->symlinkPath());
    }

}
