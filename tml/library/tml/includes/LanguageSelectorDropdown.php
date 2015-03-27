<?php include dirname(__FILE__)."/"."LanguageSelectorJs.php" ?>

<?php

$style = isset($opts['style']) ? $opts['style'] : '';
$class = isset($opts['class']) ? $opts['class'] : '';
$name = isset($opts['language']) ? $opts['language'] : 'english';

echo "<select id='tml_language_selector' onchange='tml_change_locale(this.options[this.selectedIndex].value)' style='$style' class='$class'>";

$languages = \tml\Config::instance()->application->languages;
foreach($languages as $lang) {
    echo "<option dir='ltr' value='$lang->locale' " . ($lang->locale == tml_current_language()->locale ? 'selected' : '') . ">";
    if ($name == "native")
        echo $lang->native_name;
    else
        echo $lang->english_name;
    echo "</option>";
}
echo "</select>";

tml_language_selector_footer_tag($opts);

