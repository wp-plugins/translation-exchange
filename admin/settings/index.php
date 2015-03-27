<?php

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

use tml\Cache;
use tml\Config;
use tml\utils\FileUtils;

if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

if (isset($_POST['action']) && $_POST['action'] == 'delete_cache') {
    $version_path = get_option('tml_cache_path') . "/" . $_POST['version'];
    FileUtils::rrmdir($version_path);
}

if (isset($_POST['action']) && $_POST['action'] == 'use_cache') {
    update_option("tml_cache_version", $_POST['version']);
}

if (isset($_POST['action']) && $_POST['action'] == 'download_cache') {
    echo "<p>Downloading latest cache snapshot from Translation Exchange.... </p>";
    try {
        $snapshot = file_get_contents(get_option('tml_host') . "/v1/snapshots/current?access_token=" . get_option('tml_token'));
        $snapshot = json_decode($snapshot, true);

        if (!$snapshot) {
            throw new Exception("Failed to download snapshot");
        }

        if (isset($snapshot['status']) && $snapshot['status'] == 'none') {
            echo "<p>You current don't have any snapshots.</p>";
            echo "<p>To generate a snapshot, please visit your <a href='https://dashboard.translationexchange.com'>your dashboard</a>, choose <strong>Snapshots</strong> section and click on <strong>Generate Snapshot</strong> button.</p>";
        } else {
            $data = file_get_contents($snapshot['url']);
            $version_path = get_option('tml_cache_path') . "/" . $snapshot['version'];
            $file_path = $version_path . ".tar.gz";

            try {
                $result = file_put_contents($file_path, $data);
            } catch (Exception $e) {
                $result = false;
            }

            if (!$result) {
                echo "<p>Failed to store snapshot. Please make sure that <strong>" . get_option('tml_cache_path') . "</strong> has write permissions.</p>";
            } else {
                echo "<p>Downloaded version <strong>" . $snapshot['version'] . "</strong> ($result bytes). Extracting content...</p>";
                echo "<p>Summary: " . $snapshot['metrics']['language_count'] . " languages, " . $snapshot['metrics']['key_count'] . " phrases, " . $snapshot['metrics']['translation_count'] . " translations </p>";
                echo "<p>Extracting content...</p>";
                try {
                    $phar = new PharData($file_path);
                    FileUtils::rrmdir($version_path);
                    $phar->extractTo($version_path);
                    unlink($file_path);
                    $result = true;
                } catch (Exception $e) {
                    $result = false;
                }

                if ($result) {
                    update_option("tml_cache_version", $snapshot['version']);
                    echo "<p>Snapshot has been extracted and is ready for use.</p>";
                } else {
                    echo "<p>Failed to extract snapshot. Please make sure that <strong>" . get_option('tml_cache_path') . "</strong> has write permissions and has enough space..</p>";
                }
            }
        }
    } catch (Exception $e) {
        echo "<p>We were unable to download the latest snapshot. Please ensure that you are using a correct access token, and you have a write permission to the cache folder.</p>";
    }

} else { // snapshot generation end

    $submit_field_name = 'tml_submit_hidden';
    $cache_field_name = 'tml_update_cache_hidden';

    $application_fields = array(
        'tml_host' => array("title" => __('Host:'), "value" => get_option('tml_host'), "default" => "https://api.translationexchange.com", "style" => "display:none"),
        'tml_token' => array("title" => __('Project Token:'), "value" => get_option('tml_token'), "default" => ""),
        'tml_mode' => array("title" => __('Mode:'), "value" => get_option('tml_mode'), "default" => "", "type" => "radio", "options" => array(
            array("title" => __('Client-side (fully automated)'), "value" => "client"),
            array("title" => __('Server-side (fully automated)'), "value" => "server_automated"),
            array("title" => __('Server-side (manual, using shortcodes)'), "value" => "server_manual"),
        )),
    );

    $translation_fields = array(
        'tml_translate_html' => array("title" => __('Automatic Translations:'), "value" => get_option('tml_translate_html'), "type" => "checkbox", "notes" => __('If enabled, the content will be automatically converted to TML and translated. Otherwise you should use tml:tr, tml:trh and tml:block tags to indicate translation keys and source blocks.')),
        'tml_translate_wordpress' => array("title" => __('Translate Wordpress:'), "value" => get_option('tml_translate_wordpress'), "type" => "checkbox", "notes" => __('(Beta) If enabled, the Wordpress text itself will be registered as TML and translated using Tml.')),
    );

    if (isset($_POST[ $submit_field_name ]) && $_POST[ $submit_field_name ] == 'Y') {
        foreach($application_fields as $key => $attributes) {
            update_option( $key, $_POST[ $key ] );
            $application_fields[$key] = array_merge($attributes, array("value" => $_POST[$key]));
        }
        foreach($translation_fields as $key => $attributes) {
            $value = isset($_POST[ $key ]) ? "true" : "false";
            update_option( $key, $value);
            $translation_fields[$key] = array_merge($attributes, array("value" => $value));
        }
        ?>

        <div class="updated"><p><strong><?php _e('Settings have been saved.'); ?></strong></p></div>
    <?php
    }

    //$field_sets = array($application_fields, $translation_fields);
    $field_sets = array($application_fields);

    ?>

    <div class="wrap">
        <h2>
            <img src="<?php echo plugins_url( 'translationexchange/assets/images/logo.png' ) ?>" style="width: 30px; vertical-align:middle; margin: 0px 5px;">
            <?php echo __( 'Translation Exchange Project Settings' ); ?>
        </h2>

        <hr />

        <p style="padding-left:10px; color:#888;">
            To get your project token, visit your <a href="https://dashboard.translationexchange.com">Translation Exchange Dashboard</a> and choose <strong>Integration Section</strong> from the navigation menu.
        </p>

        <form name="form1" method="post" action="">
            <input type="hidden" name="<?php echo $cache_field_name; ?>" id="<?php echo $cache_field_name; ?>" value="N">
            <input type="hidden" name="<?php echo $submit_field_name; ?>" id="<?php echo $submit_field_name; ?>" value="Y">

            <table style="margin-top: 10px; width: 100%">
            <?php foreach($field_sets as $field_set) { ?>
                <?php foreach($field_set as $key => $field) { ?>
                    <?php $type = (!isset($field['type']) ? 'text' : $field['type']); ?>
                    <?php $style = (!isset($field['style']) ? '' : $field['style']); ?>
                    <tr style="<?php echo($style) ?>">
                        <td style="width:100px; padding:10px;"><?php echo($field["title"]) ?></td>
                        <td style="padding:10px;">
                            <?php if ($type == 'text') {  ?>
                                <input type="text" name="<?php echo($key) ?>" value="<?php echo($field["value"]) ?>" placeholder="<?php echo($field["default"]) ?>"  size="80">
                            <?php } else if ($type == 'radio' && isset($field["options"])) { ?>
                                <?php foreach($field["options"] as $option) { ?>
                                    <input type="radio" name="<?php echo($key) ?>" value="<?php echo($option["value"]) ?>" <?php if ($field["value"] == $option["value"]) echo("checked"); ?> >
                                    <?php echo($option["title"]) ?>
                                    &nbsp;&nbsp;
                                <?php } ?>
                            <?php } else if ($type == 'checkbox') { ?>
                                <?php
                                    $value = $field["value"];
                                ?>
                                <input type="checkbox" name="<?php echo($key) ?>" value="true" <?php if ($value == "true") echo("checked"); ?> >
                                <?php if (isset($field['notes'])) { ?>
                                     <span style="padding-left:15px;color:#666;"><?php echo $field['notes'] ?></span>
                                <?php } ?>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
                <tr>
                    <td colspan="2"><hr /></td>
                </tr>
            <?php } ?>

                <tr>
                    <td>

                    </td>
                    <td style="padding-top:20px;padding-bottom:40px;">
                        <button class="button-primary">
                            <?php echo __('Save Changes') ?>
                        </button>

                        <a class="button" href='https://dashboard.translationexchange.com'>
                            <?php echo __('Visit Your Dashboard') ?>
                        </a>

                        <a class="button" href='https://translation-center.translationexchange.com'>
                            <?php echo __('Visit Translation Center') ?>
                        </a>

                    </td>
                </tr>
            </table>
        </form>

        <?php if (get_option("tml_token") != "") { ?>
            <h2>
                <?php echo __( 'Translation Cache Settings' ); ?>
            </h2>

            <hr />

            <div style="padding-left:10px; color: #888">
                <?php echo(__("For better performance, you should cache all your translations locally.")) ?>
                <a href="http://welcome.translationexchange.com/docs/plugins/wordpress" target="_new">Click here</a> to learn more about cache options.
            </div>

            <form id="cache_form" method="post" action="">
                <input type="hidden" name="action" id="cache_action" value="download_cache">
                <input type="hidden" name="version" id="cache_version" value="">


                <table style="margin-top: 10px; width: 100%">
                    <tr>
                        <td style="width:100px; padding:10px; vertical-align: top;"><?php echo __("Cache Options:") ?></td>
                        <td style="padding:10px; vertical-align: top;">
                            <?php
                                $folders = array_reverse(scandir(get_option('tml_cache_path')));
                                $snapshots = array();
                                foreach ($folders as $folder) {
                                    $path = get_option('tml_cache_path') . "/" . $folder;
                                    if (!is_dir($path)) continue;
                                    if ($folder == '.' || $folder == '..') continue;

                                    $data = file_get_contents($path . "/snapshot.json");
                                    $snapshot = json_decode($data, true);
                                    $snapshot['path'] = $path;
                                    array_push($snapshots, $snapshot);
                                }
                            ?>
                                <div style="border: 1px solid #ccc; width: 600px; margin-bottom: 10px;">
                                    <div style="background:#fefefe; padding: 5px; ">
                                        <div style="float:right; color:#888;">
                                            <?php
                                                if (get_option("tml_cache_version") == "0") {
                                                    echo "<strong>" . __("current") . "</strong>";
                                                } else {
                                                    ?> <a href="#" onclick="useCache('0')" style="text-decoration: none"><?php echo __("use") ?></a> <?php
                                                }
                                            ?>
                                        </div>

                                        <?php
                                            if (get_option("tml_cache_version") == "0")
                                                echo "<strong>";

                                            echo __("No local cache - data is requested directly from the service");

                                            if (get_option("tml_cache_version") == "0")
                                                echo "</strong>";
                                        ?>
                                    </div>
                                </div>

                            <?php if (count($snapshots) === 0) { ?>
                                <div style="color:#888">
                                    You have not downloaded any snapshots yet.
                                </div>
                            <?php } ?>

                            <?php
                                foreach ($snapshots as $snapshot) {
                                    ?>
                                        <div style="border: 1px solid #ccc; width: 600px; margin-bottom: 10px;">
                                            <div style="background:#fefefe; padding: 5px; border-bottom: 1px solid #ccc;">
                                                <div style="float:right; color:#888;">
                                                    <?php
                                                    if ($snapshot['version'] === get_option("tml_cache_version")) {
                                                       echo "<strong>" . __("current") . "</strong>";
                                                    } else {
                                                        ?> <a href="#" onclick="useCache('<?php echo $snapshot['version']; ?>')" style="text-decoration: none"><?php echo __("use") ?></a> <?php
                                                    }
                                                    ?>
                                                    <span style="color:#ccc;">|</span>
                                                    <a href="#" onclick="deleteCache('<?php echo $snapshot['version']; ?>')" style="text-decoration: none"><?php echo __("remove") ?></a>
                                                </div>

                                                <?php
                                                    if ($snapshot['version'] === get_option("tml_cache_version")) {
                                                        echo "<strong>" . __("Generated On:") . " " . $snapshot['created_at'] . "</strong>";
                                                    } else {
                                                        echo "<span style='color: #888;'>" . __("Generated On:") . " " . $snapshot['created_at'] . "</span>";
                                                    }
                                                ?>
                                            </div>
                                            <div style="padding: 5px;">
                                                <table style="width:100%; font-size:12px;" cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        <td style="border-right: 1px solid #ccc; padding:3px; width: 20%; color: #888;"><?php echo __("Languages") ?></td>
                                                        <td style="border-right: 1px solid #ccc; padding:3px; width: 20%; color: #888;"><?php echo __("Phrases") ?></td>
                                                        <td style="border-right: 1px solid #ccc; padding:3px; width: 20%; color: #888;"><?php echo __("Translations") ?></td>
                                                        <td style="border-right: 1px solid #ccc; padding:3px; width: 20%; color: #888;"><?php echo __("Translated") ?></td>
                                                        <td style="padding:3px; width: 20%; color: #888;"><?php echo __("Approved") ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td style="border-right: 1px solid #ccc; padding:3px;"><?php echo $snapshot['metrics']['language_count']; ?></td>
                                                        <td style="border-right: 1px solid #ccc; padding:3px;"><?php echo $snapshot['metrics']['key_count']; ?></td>
                                                        <td style="border-right: 1px solid #ccc; padding:3px;"><?php echo $snapshot['metrics']['translation_count']; ?></td>
                                                        <td style="border-right: 1px solid #ccc; padding:3px;"><?php echo $snapshot['metrics']['percent_translated']; ?>%</td>
                                                        <td style="padding:3px;"><?php echo $snapshot['metrics']['percent_locked']; ?>%</td>
                                                    </tr>
                                                </table>
                                                <!-- ?php var_dump($snapshot['metrics']) ? -->
                                            </div>
                                        </div>

                                <?php
                                }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td></td>
                        <td style="padding-top:20px;padding-bottom:40px;">
                            <button class="button" onClick="return downloadSnapshot();">
                                <?php echo __('Download Latest Snapshot') ?>
                            </button>

                            <?php if (get_option("tml_mode") == "client") { ?>
                                <button class="button" onClick="return resetBrowserCache();" style="margin-right:15px;">
                                    <?php echo __('Reset Browser Cache') ?>
                                </button>
                            <?php } ?>
                        </td>
                    </tr>
                </table>
            </form>


            <div style="color: #888">
                <?php echo __("Don't forget to configure the <strong>Language Selector widget</strong> under Appearance > Widgets."); ?>
            </div>
        <?php } ?>
    </div>


    <script>
        function resetBrowserCache() {
            if (!confirm("<?php echo __("Are you sure you want to reset browser cache?") ?>"))
                return false;

            var cache = window.localStorage;
            for (var key in cache){
                if(key.match(/^tml_/)) cache.removeItem(key);
            }
            window.location.reload();
            return false;
        }

        function downloadSnapshot() {
            if (!confirm("<?php echo __("Are you sure you want to download the latest snapshot from Translation Exchange?") ?>"))
                return false;
            document.getElementById("cache_form").submit();
            return true;
        }

        function deleteCache(version) {
            if (!confirm("<?php echo __("Are you sure you want to remove this cache version?") ?>"))
                return false;

            jQuery("#cache_action").val("delete_cache");
            jQuery("#cache_version").val(version);
            document.getElementById("cache_form").submit();
        }

        function useCache(version) {
            jQuery("#cache_action").val("use_cache");
            jQuery("#cache_version").val(version);
            document.getElementById("cache_form").submit();
        }

    </script>

<?php } ?>
