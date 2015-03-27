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

error_reporting(E_ALL);
ini_set('display_errors', 1);

$files = array(
    "tml/utils",
    "tml/Base.php",
    "tml",
    "tml/tokens",
    "tml/rules_engine",
    "tml/decorators/Base.php",
    "tml/decorators",
    "tml/cache/Base.php",
    "tml/cache",
    "tml/cache/generators/Base.php",
    "tml/cache/generators",
    "tml/includes/Tags.php"
);

foreach($files as $dir) {
    $path = dirname(__FILE__)."/".$dir;
    if (is_dir($path)) {
        foreach (scandir($path) as $filename) {
            $file = $path . "/" . $filename;
            if (is_file($file)) {
                require_once $file;
            }
        }
    } else {
        require_once $path;
    }
}

use tml\Application;
use tml\Config;
use tml\Logger;
use tml\TmlException;
use tml\Translator;
use tml\utils\ArrayUtils;
use tml\Utils\BrowserUtils;
use tml\utils\HtmlTranslator;

/**
 * Initializes the TML library
 *
 * @param null $host
 * @param null $key
 * @param null $secret
 * @return bool
 */
function tml_init($token = null, $host = null) {
    global $tml_page_t0;
    $tml_page_t0 = microtime(true);

    try {
        Config::instance()->initApplication($token, $host);
    } catch (Exception $e) {
        Logger::instance()->error("Application failed to initialize.");
    }

    $locale = null;
    $translator = null;
    $cookie_params = null;

    if (Config::instance()->isEnabled()) {
        $cookie_name = "trex_" . Config::instance()->application->key;

        if (isset($_COOKIE[$cookie_name])) {
            Logger::instance()->info("Cookie file $cookie_name found!");
            $cookie_params = Config::instance()->decode($_COOKIE[$cookie_name]);
            $locale = $cookie_params['locale'];
            if (isset($cookie_params['translator'])) {
                $translator = new Translator(array_merge($cookie_params["translator"], array('application' => Config::instance()->application)));
            }
        } else {
            Logger::instance()->info("Cookie file $cookie_name not found!");
        }

        if (isset($_GET["locale"])) {
            $locale =  $_GET["locale"];
            if (!$cookie_params) $cookie_params = array();
            $cookie_params["locale"] = $_GET["locale"];
            setcookie($cookie_name, Config::instance()->encode($cookie_params), null, "/");
        }

        if (!$locale) $locale = tml_browser_default_locale();
        if (!$locale) $locale = Config::instance()->default_locale;

    } else {
        Logger::instance()->error("Application failed to initialize.");
    }

    $source = null;

    if (isset($_SERVER["REQUEST_URI"])) {
        $source = $_SERVER["REQUEST_URI"];
        $source = explode("#", $source);
        $source = $source[0];
        $source = explode("?", $source);
        $source = $source[0];
        $source = str_replace('.php', '', $source);
        $source = preg_replace('/\/$/', '', $source);
    }

    if ($source === null || $source == '' || $source == '/') $source = "index";

    if (Config::instance()->isEnabled()) {
        Config::instance()->initRequest(array('locale' => $locale, 'translator' => $translator, 'source' => $source));
    }
    return true;
}

/**
 * @param array $options
 */
function tml_complete_request($options = array()) {
    Config::instance()->completeRequest($options);
    global $tml_page_t0;
    $milliseconds = round(microtime(true) - $tml_page_t0,3)*1000;
    Logger::instance()->info("Page loaded in " . $milliseconds . " milliseconds");
}

/**
 * Finds the first available language based on browser and application combination
 */
function tml_browser_default_locale() {
    if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) return null;

    $accepted = BrowserUtils::parseLanguageList($_SERVER['HTTP_ACCEPT_LANGUAGE']);
//    var_dump($accepted);

    $locales = array();
    foreach (tml_application()->languages as $lang) array_push($locales, $lang->locale);

    $available = BrowserUtils::parseLanguageList(implode(', ', $locales));
//    var_dump($available);

    $matches = BrowserUtils::findMatches($accepted, $available);
//    var_dump($matches);

    $keys = array_keys($matches);
    if (count($keys) == 0)
        $locale = Config::instance()->default_locale;
    else
        $locale = $matches[$keys[0]][0];

    return $locale;
}

/**
 * Includes Tml JavaScript library
 */
function tml_scripts() {
  include(__DIR__ . '/tml/includes/HeaderScripts.php');
}

/**
 * Includes Tml footer scripts
 */
function tml_footer() {
  include(__DIR__ . '/tml/includes/FooterScripts.php');
}

/**
 * @return null|Application
 */
function tml_application() {
    return Config::instance()->application;
}

/**
 * @return \tml\Language
 */
function tml_current_language() {
    return Config::instance()->current_language;
}

/**
 * @return Translator
 */
function tml_current_translator() {
    return Config::instance()->current_translator;
}

/**
 * @param array $options
 */
function tml_begin_block_with_options($options = array()) {
    Config::instance()->beginBlockWithOptions($options);
}

/**
 * @return null
 */
function tml_finish_block_with_options() {
    return Config::instance()->finishBlockWithOptions();
}

/**
 * There are three ways to call this method:
 *
 * 1. tr($label, $description = "", $tokens = array(), options = array())
 * 2. tr($label, $tokens = array(), $options = array())
 * 3. tr($params = array("label" => label, "description" => "", "tokens" => array(), "options" => array()))
 *
 * @param string $label
 * @param string $description
 * @param array $tokens
 * @param array $options
 * @return mixed
 */
function tr($label, $description = "", $tokens = array(), $options = array()) {
    $params = ArrayUtils::normalizeTmlParameters($label, $description, $tokens, $options);

    try {
        // Translate individual sentences
        if (isset($params["options"]['split'])) {
            $sentences = \tml\Utils\StringUtils::splitSentences($params["label"]);
            foreach($sentences as $sentence) {
                $params["label"] = str_replace($sentence, tml_current_language()->translate($sentence, $params["description"], $params["tokens"], $params["options"]), $params["label"]);
            }
            return $label;
        }

        // Remove html and translate the content
        if (isset($params["options"]["strip"])) {
            $stripped_label = str_replace(array("\r\n", "\n"), '', strip_tags(trim($params["label"])));
            $translation = tml_current_language()->translate($stripped_label, $params["description"], $params["tokens"], $params["options"]);
            $label = str_replace($stripped_label, $translation, $params["label"]);
            return $label;
        }

        return tml_current_language()->translate($params["label"], $params["description"], $params["tokens"], $params["options"]);
    } catch(TmlException $ex) {
        Logger::instance()->error("Failed to translate " . $params["label"] . ": " . $ex);
        return $label;
    } catch(\Exception $ex) {
        Logger::instance()->error("ERROR: Failed to translate " . $params["label"] . ": " . $ex);
        throw $ex;
    }
}

/**
 * Translates a label and prints it to the page
 *
 * @param string $label
 * @param string $description
 * @param array $tokens
 * @param array $options
 */
function tre($label, $description = "", $tokens = array(), $options = array()) {
    echo tr($label, $description, $tokens, $options);
}

/**
 * Translates a label while suppressing its decorations
 * The method is useful for translating alt tags, list options, etc...
 *
 * @param string $label
 * @param string $description
 * @param array $tokens
 * @param array $options
 * @return mixed
 */
function trl($label, $description = "", $tokens = array(), $options = array()) {
    $params = ArrayUtils::normalizeTmlParameters($label, $description, $tokens, $options);
    $params["options"]["skip_decorations"] = true;
	return tr($params);
}

/**
 * Same as trl, but with printing it to the page
 *
 * @param string $label
 * @param string $description
 * @param array $tokens
 * @param array $options
 */
function trle($label, $description = "", $tokens = array(), $options = array()) {
    echo trl($label, $description, $tokens, $options);
}

/**
 * Translates a block of html, converts it to TML, translates it and then converts it back to HTML
 *
 * @param string $html
 * @param string $description
 * @param array $tokens
 * @param array $options
 */
function trh($label, $description = "", $tokens = array(), $options = array()) {
    $params = ArrayUtils::normalizeTmlParameters($label, $description, $tokens, $options);

    $html = trim($params["label"]);
    $ht = new HtmlTranslator($html, array(), $params["options"]);
    return $ht->translate();
}

/**
 * Translates a block of html, converts it to TML, translates it and then converts it back to HTML
 *
 * @param string $html
 * @param string $description
 * @param array $tokens
 * @param array $options
 */
function trhe($label, $description = "", $tokens = array(), $options = array()) {
    $params = ArrayUtils::normalizeTmlParameters($label, $description, $tokens, $options);
    echo trh($params);
}
