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

use tml\Cache;

class ApiClient {
    const API_HOST = 'https://api.translationexchange.com';
    const API_PATH = '/v1/';

    /**
     * @var Application
     */
    private $application;

    public static $CURL_OPTS = array(
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'tml-php',
    );


    /**
     * @param Application $app
     */
    function __construct($app) {
        $this->application = $app;
    }

    /**
     * @param string $path
     * @param array $params
     * @param array $options
     * @return array
     * @throws TmlException
     */
    public static function executeRequest($path, $params = array(), $options = array()) {
        $t0 = microtime(true);

        $ch = curl_init();

        if (FALSE === $ch) {
            Logger::instance()->error("Failed to initialize curl");
            throw new TmlException('Failed to initialize curl');
        }

        $opts = self::$CURL_OPTS;

        $api_host = isset($options['host']) ? $options['host'] : self::API_HOST;

        if (!isset($options['method']))
            $options['method'] = 'GET';

        if ($options['method'] == 'POST') {
            $opts[CURLOPT_URL] = $api_host . self::API_PATH . $path;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params, null, '&');
            Logger::instance()->info("POST: " . $opts[CURLOPT_URL]);
            Logger::instance()->info("DATA: ", $params);
        } else {
            $opts[CURLOPT_URL] = $api_host . self::API_PATH . $path . '?' . http_build_query($params, null, '&');
            Logger::instance()->info("GET: " . $opts[CURLOPT_URL]);
        }

        curl_setopt_array($ch, $opts);

        $result = curl_exec($ch);

        if (FALSE === $result) {
            Logger::instance()->error(curl_error($ch) . ": " . curl_errno($ch));
            throw new TmlException("" . curl_error($ch) . ": " . curl_errno($ch));
        }

        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

//        Logger::instance()->info($result);

        if ($http_status != 200) {
            Logger::instance()->error("Got HTTP response: $http_status");
            throw new TmlException("Got HTTP response: $http_status");
        }

        curl_close($ch);

        $t1 = microtime(true);
        $milliseconds = round($t1 - $t0,3)*1000;
        Logger::instance()->info("Received " . strlen($result) . " chars in " . $milliseconds . " milliseconds");

        return $result;
    }

    /**
     * @param $path
     * @param array $params
     * @param array $options
     * @return array
     * @throws TmlException
     */
    public static function fetch($path, $params = array(), $options = array()) {
        if (Config::instance()->isCacheEnabled() && isset($options["cache_key"])) {
            $data = Cache::fetch($options["cache_key"]);
            if ($data === null) {
                if (Cache::isReadOnly()) return null;
                $data = self::executeRequest($path, $params, $options);
                $json = json_decode($data, true);
                if (!isset($json['error']))
                    Cache::store($options["cache_key"], $data);
            } else {
                $json = json_decode($data, true);
            }
        } else {
            $data = self::executeRequest($path, $params, $options);
            $json = json_decode($data, true);
        }

        if (isset($json['error'])) {
            throw (new TmlException("Error: " . $json['error']));
        }

        return self::processResponse($json, $options);
    }

    /**
     * @param string $data
     * @param array $options
     * @return array
     */
    public static function processResponse($data, $options = array()) {
        if (isset($data['results'])) {
            Logger::instance()->info("Received " . count($data["results"]) ." result(s)");

            if (!isset($options["class"])) return $data["results"];

            $objects = array();
            foreach($data["results"] as $json) {
                array_push($objects, self::createObject($json, $options));
            }
            return $objects;
        }

        if (!isset($options["class"])) return $data;
        return self::createObject($data, $options);
    }

    /**
     * @param $data
     * @param $options
     * @return mixed
     */
    public static function createObject($data, $options) {
        if ($options != null && array_key_exists('attributes', $options)) {
            $data = array_merge($data, $options['attributes']);
        }
        return new $options["class"]($data);
    }

    /*
     * @param string $path
     * @param array $params
     * @param array $options
     * @return array
     */
    public function get($path, $params = array(), $options = array()) {
        return $this->api($path, $params, $options);
    }

    /**
     * @param string $path
     * @param array $params
     * @param array $options
     * @return array
     */
    public function post($path, $params = array(), $options = array()) {
        $options["method"] = 'POST';
        return $this->api($path, $params, $options);
    }

    /**
     * @param string $path
     * @param array $params
     * @param array $options
     * @return array
     */
    public function api($path, $params = array(), $options = array()) {
        $options["host"] = $this->application->host ? $this->application->host : self::API_HOST;
        $params["access_token"] = $this->application->access_token;

        return self::fetch($path, $params, $options);
    }

}