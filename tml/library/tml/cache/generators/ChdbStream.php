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

class ChdbStream {
    protected $buffer;
    private $key_count;
    private $translation_count;
    private $translations;
    private $started_at;

    function log($msg) {
        ChdbGenerator::instance()->log($msg);
    }

    function stream_open($path, $mode, $options, &$opened_path) {
        $this->key_count = 0;
        $this->translation_count = 0;
        $this->translations = array();
        $this->started_at = new \DateTime();

        $this->log("Initializing stream protocol...");

        return true;
    }

    function stream_close() {
        $extracted_at = new \DateTime();
        $since_start = $this->started_at->diff($extracted_at);
        $minutes = $since_start->days * 24 * 60;
        $minutes += $since_start->h * 60;
        $minutes += $since_start->i;

        if ($minutes > 0)
            $this->log("Extraction took " . $minutes .  " minutes");
        else
            $this->log("Extraction took " . $since_start->s . " seconds");

        $this->log("Received total of " . $this->key_count . " keys and " . $this->translation_count . " translations");

        $this->log("Closing stream...");

        ChdbGenerator::instance()->cache($this->translations);
    }

    public function stream_write($data) {
        // Extract the lines ; on y tests, data was 8192 bytes long ; never more
        $lines = explode("\n", $data);

        // The buffer contains the end of the last line from previous time
        // => Is goes at the beginning of the first line we are getting this time
        $lines[0] = $this->buffer . $lines[0];

        // And the last line is only partial
        // => save it for next time, and remove it from the list this time
        $nb_lines = count($lines);
        $this->buffer = $lines[$nb_lines-1];
        unset($lines[$nb_lines-1]);

        foreach($lines as $line) {
            $this->key_count += 1;
            $tkey = json_decode($line, true);
//            print_r($tkey);
//            return (($languageSet == self::COMPANY_LANGUAGE_SET) ? 'C' : 'F')  . '_' . $languageCode . '_' . $category . '_' . $phrase;

            $label = $tkey["label"];
            $description = (isset($tkey["description"]) ? $tkey["description"] : "");


            if (isset($tkey["translations"])) {
                foreach($tkey["translations"] as $locale => $translations) {
                    $key = \tml\TranslationKey::cacheKey($locale, $label, $description);

                    // no translations, do nothing
                    if (count($translations) == 0)
                        continue;

                    $translations_data = array();

                    if (count($translations) == 1 && !isset($translations[0]["context"])) {
                        $this->translation_count += 1;
                        $translations_data = $translations[0]["label"];
                    } else {
                        // one translation, use a hash or a string
                        foreach($translations as $translation) {
                            $t = array("label" => $translation["label"]);
                            if (isset($translation["context"])) {
                                $t["context"] = $translation["context"];
                            }
                            $this->translation_count += 1;
                            array_push($translations_data, $t);
                        }
                        $translations_data = json_encode($translations_data);
                    }

//                    print_r($key . "\n");
//                    print_r("------------------------------------------------------------------------------------------\n");
//                    print_r($key . "\n");
//                    print_r($translations_data . "\n\n\n");

                    $this->translations[$key] = $translations_data;
                }
            }

            if ($this->key_count % 10 == 0) {
                $this->log("Processed " . $this->key_count . " keys, " . $this->translation_count . " translations");
            }
        }

        return strlen($data);
    }
}