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

class Translator extends Base {

    /**
     * @var Application
     */
    public $application;
    /**
     * @var integer
     */
    public $id;
    /**
     * @var string
     */
    public $name;
    /**
     * @var string
     */
    public $email;
    /**
     * @var boolean
     */
    public $inline;
    /**
     * @var boolean[]
     */
    public $features;
    /**
     * @var integer
     */
    public $voting_power;
    /**
     * @var integer
     */
    public $rank;
    /**
     * @var integer
     */
    public $level;
    /**
     * @var string
     */
    public $locale;
    /**
     * @var boolean
     */
    public $manager;
    /**
     * @var string
     */
    public $code;
    /**
     * @var string
     */
    public $access_token;

    /**
     * @param array $attributes
     */
    public function __construct($attributes=array()) {
        parent::__construct($attributes);
    }

    /**
     * @return bool
     */
    public function isInlineModeEnabled() {
        return ($this->inline==true);
    }

    /**
     * @return bool
     */
    public function isManager() {
        return ($this->manager==true);
    }

    /**
     * @return null|string
     */
    public function mugshot() {
        if (!isset($this->email)) return null;
        $gravatar_id = md5(strtolower($this->email));
        return "http://gravatar.com/avatar/$gravatar_id.png?s=48";
    }

    /**
     * @param $key string
     * @return bool
     */
    public function isFeatureEnabled($key) {
        if ($this->features == null)
            return false;

        if (!isset($this->features[$key])) {
            return false;
        }
        return $this->features[$key];
    }
}
