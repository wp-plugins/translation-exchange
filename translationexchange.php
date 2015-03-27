<?php
/*
  Plugin Name: Translation Exchange
  Plugin URI: http://wordpress.org/plugins/translationexchange/
  Description: Translate your WordPress site into any language in minutes.
  Author: Translation Exchange, Inc
  Version: 0.2.6
  Author URI: http://translationexchange.com/
  License: GPLv2 (http://www.gnu.org/licenses/old-licenses/gpl-2.0.html)
 */

/*
  Copyright (c) 2015 Translation Exchange, Inc

   _______                  _       _   _             ______          _
  |__   __|                | |     | | (_)           |  ____|        | |
     | |_ __ __ _ _ __  ___| | __ _| |_ _  ___  _ __ | |__  __  _____| |__   __ _ _ __   __ _  ___
     | | '__/ _` | '_ \/ __| |/ _` | __| |/ _ \| '_ \|  __| \ \/ / __| '_ \ / _` | '_ \ / _` |/ _ \
     | | | | (_| | | | \__ \ | (_| | |_| | (_) | | | | |____ >  < (__| | | | (_| | | | | (_| |  __/
     |_|_|  \__,_|_| |_|___/_|\__,_|\__|_|\___/|_| |_|______/_/\_\___|_| |_|\__,_|_| |_|\__, |\___|
                                                                                         __/ |
                                                                                        |___/
    GNU General Public License, version 2

    This program is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License
    as published by the Free Software Foundation; either version 2
    of the License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

    http://www.gnu.org/licenses/gpl-2.0.html
*/

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );

define( 'TML_DEBUG', false );

if ( ! defined( 'ABSPATH' ) ) exit;

add_option('tml_mode', 'client');
add_option('tml_cache_version', '0');
add_option('tml_host', 'https://api.translationexchange.com');

if (TML_DEBUG) {
    update_option('tml_host', 'http://0.0.0.0:3000');
} else {
    update_option('tml_host', 'https://api.translationexchange.com');
}

add_option('tml_cache_path', plugin_dir_path(__FILE__) . "cache");

require_once(dirname(__FILE__).'/tml/library/tml.php');

use tml\Config;
use tml\Logger;
use tml\TmlException;
use tml\utils\ArrayUtils;
use tml\utils\StringUtils;

if (get_option('tml_mode') == "server_automated" || get_option('tml_mode') == "server_manual") {
    if (get_option('tml_cache_version') == '0') {
        Config::instance()->initCache(array("enabled" => false));
    } else {
        Config::instance()->initCache(array(
            "enabled" => true,
            "adapter" => "file",
            "path" => get_option('tml_cache_path'),
            "version" => get_option('tml_cache_version', 1)
        ));
    }

    tml_init(get_option('tml_token'), get_option('tml_host'));
}

if (Config::instance()->isEnabled()) {
    apply_filters('debug', 'Tml Initialized');
}

function tml_prepare_tokens_and_options($args) {
    $tokens = array();
    $options = array();

    if (is_string($args)) $args = array();

    $description = isset($args['description']) ? $args['description'] : null;
    if ($description == null) {
        $description = isset($args['context']) ? $args['context'] : null;
    }

    if (isset($args['tokens'])) {
        $tokens = json_decode($args['tokens'], true);
    }

    if (isset($args['options'])) {
        $options = json_decode($args['options'], true);
    }

    foreach($args as $key => $value) {
        // echo($key . " = " . $value . "<br>");

        if (StringUtils::startsWith('option:', $value)) {
            $parts = explode('=', substr($value, 7));
            $value = trim($parts[1], '\'"');

            $parts = explode('.', $parts[0]);
            if (count($parts) == 1) {
                $options[$parts[0]] = $value;
            } else {
                if (!isset($options[$parts[0]])) $options[$parts[0]] = array();
                ArrayUtils::createAttribute($options[$parts[0]], array_slice($parts,1), $value);
            }
        } else if (StringUtils::startsWith('token:', $value)) {
            $parts = explode('=', substr($value, 6));
            $value = trim($parts[1], '\'"');

            $parts = explode('.', $parts[0]);
            if (count($parts) == 1) {
                $tokens[$parts[0]] = $value;
            } else {
                if (!isset($tokens[$parts[0]])) $tokens[$parts[0]] = array();
                ArrayUtils::createAttribute($tokens[$parts[0]], array_slice($parts,1), $value);
            }
        } else {
            $tokens[$key] = $value;
        }
    }

    if (isset($args['split'])) {
        $options['split'] = $args['split'];
    }

    return array("description" => $description, "tokens" => $tokens, "options" => $options);
}

function tml_translate($atts, $content = null) {
    if (Config::instance()->isDisabled()) {
        return $content;
    }

    if ($content == null) return $content;

    $label = trim($content);
    $atts = tml_prepare_tokens_and_options($atts);

//    \Tml\Logger::instance()->info("translating: \"" . $content . "\"", $tokens);

    try {
        return tr($label, $atts["description"], $atts["tokens"], $atts["options"]);
    } catch(TmlException $e) {
        Logger::instance()->info($e->getMessage());
        return $content;
    }
}
add_shortcode('tml:tr', 'tml_translate', 2);

function tml_translate_html($attrs, $content = null) {
    $attrs = tml_prepare_tokens_and_options($attrs);

//    \Tml\Logger::instance()->debug($content);
    return trh($content, $attrs["description"], $attrs["tokens"], $attrs["options"]);
}
add_shortcode('tml:trh', 'tml_translate_html', 2);

function tml_block($atts, $content = null) {
    if (Config::instance()->isDisabled()) {
        return do_shortcode($content);
    }

    $options = array();
    if (isset($atts['source'])) {
        $options['source'] = $atts['source'];
    }
    if (isset($atts['locale'])) {
        $options['locale'] = $atts['locale'];
    }
    Config::instance()->beginBlockWithOptions($options);
    $content = do_shortcode($content);
    Config::instance()->finishBlockWithOptions();
    return $content;
}
add_shortcode('tml:block', 'tml_block', 2);

function tml_title($title, $id) {
    if (get_option('tml_mode') == "server_automated") {
        if ($title != strip_tags($title)) {
            return trh($title);
        }
        return tr($title);
    }

    return do_shortcode($title);
}
add_filter('the_title', 'tml_title', 10, 2);
add_filter('wp_title', 'tml_title', 10, 2);

// function tml_wp_title_filter($title, $id) {
//     return do_shortcode($title);
// }
// add_filter('wp_title', 'tml_wp_title_filter', 10, 2);

function tml_the_content_filter($content) {
    if (get_option('tml_mode') == "server_automated") {
        if (strstr($content, 'tml:manual') !== false)
            return $content;
        return trh($content);
    }
    // Logger::instance()->debug($content);
    return $content;
}
add_filter('the_content', 'tml_the_content_filter');

function tml_widget_text_filter($content) {
    return do_shortcode($content);
}
add_filter('widget_text', 'tml_widget_text_filter');

function tml_the_excerpt_filter($content) {
//    \Tml\Logger::instance()->debug($content);
    return $content;
}
add_filter('the_excerpt', 'tml_the_excerpt_filter');

function tml_comment_text_filter($content) {
    if (get_option('tml_mode') == "server_automated") {
        return trh($content);
    }
//    \Tml\Logger::instance()->debug($content);
    return $content;
}
add_filter('comment_text ', 'tml_comment_text_filter');


function tml_request_shutdown() {
    tml_complete_request();
//    \Tml\Config::instance()->application->submitMissingKeys();
}
add_action('shutdown', 'tml_request_shutdown');

/*
 * Javascript
 */

function tml_enqueue_scripts() {
    if (get_option('tml_mode') == "server_automated" || get_option('tml_mode') == "server_manual") {
        wp_register_script('tml_init', plugins_url('/assets/javascripts/init_server.js', __FILE__) , false, null, true);
        wp_enqueue_script('tml_init');
        wp_localize_script('tml_init', 'TmlConfig', array(
            "host" => get_option('tml_host'),
            "key" => tml_application()->key,
            "tools" => tml_application()->tools["host"],
            "stylesheet" => tml_application()->tools["stylesheet"],
            "css" => tml_application()->css,
            "javascript" => tml_application()->tools["javascript"],
            "default_locale" => tml_application()->default_locale,
            "page_locale" => Config::instance()->current_language->locale,
            "locale" => Config::instance()->current_language->locale,
            "shortcuts" => (tml_application()->isFeatureEnabled("shortcuts") ? tml_application()->shortcuts : null)
        ));
    } else if (get_option('tml_mode') == "client") {
        if (TML_DEBUG)
            wp_register_script('tml_js', ( '//localhost:8080/tml.js' ), false, null, false);
        else
            wp_register_script('tml_js', ( '//cdn.translationexchange.com/tml.js' ), false, null, false);

        wp_register_script('tml_init', plugins_url('/assets/javascripts/init_client.js', __FILE__) , false, null, false);
        wp_enqueue_script('tml_js');
        wp_enqueue_script('tml_init');
        $options = array(
            "host" => get_option('tml_host'),
            "token" => get_option('tml_token')
        );

        if (get_option("tml_cache_version") != '0') {
            $options['cache'] = array(
                "path" => plugins_url("translationexchange/cache/" . get_option("tml_cache_version")),
                "version" => get_option('tml_cache_version')
            );
        }

        wp_localize_script('tml_init', 'TmlConfig', $options);
    }
}
add_action('wp_enqueue_scripts', 'tml_enqueue_scripts');

/*
 * Admin Settings
 */

function tml_menu_pages() {
    // Add the top-level admin menu
    $page_title = 'Translation Exchange Settings';
    $menu_title = 'Translation Exchange';
    $capability = 'manage_options';
    $menu_slug = 'tml-admin';
    $function = 'tml_settings';
    add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function,  plugin_dir_url(__FILE__) . "assets/images/icon.png");

    $sub_menu_title = __('Settings');
    add_submenu_page($menu_slug, $page_title, $sub_menu_title, $capability, $menu_slug, $function);

//    $submenu_page_title = __('Dashboard');
//    $submenu_title = __('Dashboard');
//    $submenu_slug = 'tml-dashboard';
//    $submenu_function = 'tml_dashboard';
//    add_submenu_page($menu_slug, $submenu_page_title, $submenu_title, $capability, $submenu_slug, $submenu_function);
//
//    $submenu_page_title = __('Translation Center');
//    $submenu_title = __('Translation Center');
//    $submenu_slug = 'tml-tools';
//    $submenu_function = 'tml_tools';
//    add_submenu_page($menu_slug, $submenu_page_title, $submenu_title, $capability, $submenu_slug, $submenu_function);

//    $submenu_page_title = __('Tml Help');
//    $submenu_title = __('Help');
//    $submenu_slug = 'tml-help';
//    $submenu_function = 'tml_help';
//    add_submenu_page($menu_slug, $submenu_page_title, $submenu_title, $capability, $submenu_slug, $submenu_function);
}
add_action('admin_menu', 'tml_menu_pages');

function tml_settings() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    include('admin/settings/index.php');
}

function tml_help() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    include('admin/help/index.php');
}

function tml_tools() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    include('admin/tools/index.php');
}

function tml_dashboard() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    include('admin/dashboard/index.php');
}

function tml_plugin_action_links($links, $file) {
    if (preg_match('/tml/', $file)) {
        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=tml-admin">Settings</a>';
        array_unshift($links, $settings_link);
    }
    return $links;
}
add_filter('plugin_action_links', 'tml_plugin_action_links', 10, 2);

/*
 * Widgets
 */
require_once('widgets/LanguageSelectorWidget.php');

function tml_register_widgets() {
    register_widget('LanguageSelectorWidget');
}
add_action('widgets_init', 'tml_register_widgets');

/**
 * Change labels from default to tml translated
 *
 * @link http://codex.wordpress.org/Plugin_API/Filter_Reference/gettext
 */
function tml_translate_field_names( $translated_text, $text, $domain ) {
    if (!Config::instance()->isEnabled()) {
        return $translated_text;
    }

    if (get_option('tml_mode') == "server_automated") {
        foreach(array('%s', 'http://', '%1', '%2', '%3', '%4', '&#', '%d', '&gt;') as $token) {
            if (strpos($text, $token) !== FALSE) return $translated_text;
        }
        return trl($text, null, array(), array("source" => "wordpress"));
    }
    return $translated_text;
}
add_filter( 'gettext', 'tml_translate_field_names', 20, 3 );
