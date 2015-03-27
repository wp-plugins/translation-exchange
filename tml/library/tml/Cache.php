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

class Cache {

    /**
     * @return null|string
     */
    public static function cacheAdapterClass() {
        $adapter = Config::instance()->configValue("cache.adapter");

        switch($adapter) {
            case "chdb": return '\tml\cache\ChdbAdapter';
            case "file": return '\tml\cache\FileAdapter';
            case "apc": return '\tml\cache\ApcAdapter';
            case "memcache": return '\tml\cache\MemcacheAdapter';
            case "memcached": return '\tml\cache\MemcachedAdapter';
            case "redis": return '\tml\cache\RedisAdapter';
        }

        return null;
    }

    /**
     * @return \tml\Cache\Base
     */
    public static function instance() {
        static $inst = null;
        if ($inst === null) {
            $class = self::cacheAdapterClass();
            $inst = new $class();
        }
        return $inst;
    }

    /**
     * @param string $key
     * @param null $default
     * @return null
     */
    public static function fetch($key, $default = null) {
        if (!Config::instance()->isCacheEnabled()) {
            if (is_callable($default)) {
                return $default();
            }
            return $default;
        }
        return self::instance()->fetch($key, $default);
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public static function store($key, $value) {
        if (!Config::instance()->isCacheEnabled()) {
            return false;
        }
        return self::instance()->store($key, $value);
    }

    /**
     * @param $key
     * @return bool
     */
    public static function delete($key) {
        if (!Config::instance()->isCacheEnabled()) {
            return false;
        }
        return self::instance()->delete($key);
    }

    /**
     * @param $key
     * @return bool
     */
    public static function exists($key) {
        if (!Config::instance()->isCacheEnabled()) {
            return false;
        }
        return self::instance()->exists($key);
    }

    /**
     * @return bool
     */
    public static function isCachedBySource() {
        if (!Config::instance()->isCacheEnabled() || self::instance() == null)
            return true;
        return self::instance()->isCachedBySource();
    }

    /**
     * @return bool
     */
    public static function isReadOnly() {
        $adapter = Config::instance()->configValue("cache.adapter");
        return $adapter == "file" || $adapter == "chdb";
    }

    /**
     * Current cache version
     *
     * @return int
     */
    public static function version() {
        if (!Config::instance()->isCacheEnabled()) {
            return 0;
        }
        return self::instance()->version();
    }

    /**
     * Increments cache version
     *
     * @return int
     */
    public static function incrementVersion() {
        if (!Config::instance()->isCacheEnabled()) {
            return 0;
        }
        return self::instance()->incrementVersion();
    }

}
