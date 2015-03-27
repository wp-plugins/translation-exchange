<?php include dirname(__FILE__)."/"."LanguageSelectorJs.php" ?>

<?php
    $element = isset($opts['element']) ? $opts['element'] : 'div';
    $class = isset($opts['class']) ? $opts['class'] : 'dropdown';
    $style = isset($opts['style']) ? $opts['style'] : '';
    $toggle = isset($opts['toggle']) ? $opts['toggle'] : true;
    $toggle_label = isset($opts['toggle_label']) ? $opts['toggle_label'] : "Help Us Translate";
    $powered_by = isset($opts['powered_by']) ? $opts['powered_by'] : true;
?>

<?php echo "<$element class='$class' style='$style'>" ?>
  <a href='#' role='button' class='<?php echo $class ?>-toggle' data-toggle='<?php echo $class ?>'>
    <?php tml_language_name_tag(tml_current_language(), array("flag" => true)) ?>
  </a>

  <ul class='<?php echo $class ?>-menu' role='menu'>
    <?php $languages = \tml\Config::instance()->application->languages; ?>

    <?php foreach($languages as $lang) { ?>
        <li role='presentation'>
            <a href='javascript:void(0);' onclick='tml_change_locale("<?php echo $lang->locale ?>")'>
                <?php tml_language_name_tag($lang, array("flag" => true)) ?>
            </a>
        </li>
    <?php } ?>

    <?php if ($toggle) { ?>
        <li role='presentation' class='divider'></li>
        <li role='presentation'><a href='javascript:void(0);' onclick='Tml.Utils.toggleInlineTranslations()'><?php tre($toggle_label) ?></a></li>
    <?php } ?>

    <?php if ($powered_by) { ?>
        <li role='presentation' class='divider'></li>

        <div style='font-size:8px;color:#ccc;text-align: center; background: #eee; padding:8px; margin-top: -10px; margin-bottom: -5px; border-bottom-left-radius: 5px; border-bottom-right-radius: 5px;'>
            <a href='http://translationexchange.com' style='color: #888;'>
                Powered By Translation Exchange
            </a>
        </div>
    <?php } ?>

  </ul>
<?php echo "</$element>" ?>