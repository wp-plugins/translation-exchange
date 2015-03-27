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

use tml\Config;

class MemcacheAdapter extends Base {
	
	protected $cache;

	public function __construct() {
		$this->cache = new \Memcache;
        $this->addHosts();
	}

    protected function addHosts() {
        if (Config::instance()->configValue("cache.servers") != null) {
            foreach (Config::instance()->configValue("cache.servers") as $host) {
                $this->cache->addServer($host['host'], $host['port']);
            }
        } else {
            $this->cache->addServer(
                Config::instance()->configValue("cache.host", 'localhost'),
                Config::instance()->configValue("cache.port", 11211)
            );
        }
    }

    public function key() {
        return "memcache";
    }

    public function fetch($key, $default = null) {
        $value = $this->cache->get($this->versionedKey($key));
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

        $this->store($key, $value);

        return $value;
    }

    public function store($key, $value) {
        $this->info("Cache store " . $key);
        return $this->cache->set(
            $this->versionedKey($key),
            $value,
            false,
            Config::instance()->configValue("cache.timeout", 3600)
        );
    }

    public function delete($key) {
        $this->info("Cache delete " . $key);
        return $this->cache->delete($this->versionedKey($key));
    }

    public function exists($key) {
        $this->info("Cache exists " . $key);
        return $this->cache->get($this->versionedKey($key));
    }

    public function isReadOnly() {
        return false;
    }

}
