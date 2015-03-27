<?php include dirname(__FILE__)."/"."LanguageSelectorJs.php" ?>

<?php
    $element = isset($opts['element']) ? $opts['element'] : 'div';
    $style = isset($opts['style']) ? $opts['style'] : '';
    $toggle = isset($opts['toggle']) ? $opts['toggle'] : true;
    $toggle_label = isset($opts['toggle_label']) ? $opts['toggle_label'] : "Help Us Translate";
    $powered_by = isset($opts['powered_by']) ? $opts['powered_by'] : true;
?>

<style>
    .trex-language-selector {position: relative;display: inline-block;vertical-align: middle;}
    .trex-language-toggle,
    .trex-language-toggle:hover,
    .trex-language-toggle:focus {cursor:pointer;text-decoration:none;outline:none;}
    .trex-dropup .trex-dropdown-menu {top: auto;bottom: 100%;margin-bottom: 1px;-webkit-transform: scale(0.8) translateY(10%);transform: scale(0.8) translateY(10%);}
    .trex-dropleft .trex-dropdown-menu {left: auto; right: 0;}
    .trex-dropdown-menu {
        -webkit-transform: scale(0.8) translateY(10%);transform: scale(0.8) translateY(10%);transition: 0.13s cubic-bezier(0.3, 0, 0, 1.3);opacity: 0;pointer-events: none;
        display: block;font-family:Arial, sans-serif;position: absolute;
        top: 100%;left: 0;z-index: 1000;float: left;list-style: none;background-color: #FFF;height:0px;width:0px;padding:0;overflow:hidden;
    }
    .trex-language-selector.trex-open .trex-dropdown-menu {
        opacity: 1;height:auto;width:auto;overflow:hidden;min-width: 250px;margin: 2px 0 0;font-size: 13px;
        background-clip: padding-box;border: 1px solid rgba(0, 0, 0, 0.15);box-shadow: 0 2px 0 rgba(0, 0, 0, 0.05);
        border-radius: 4px;color: #6D7C88;text-align: left;padding: 5px 0;
        display:block;pointer-events: auto;-webkit-transform: none;transform: none;
    }
    .trex-dropdown-menu > li > a {
        display: block;padding: 3px 10px;margin:0 5px;clear: both;font-weight: normal;line-height: 1.42857143;color: #333;border-radius:3px;white-space: nowrap;cursor:pointer;
    }
    .trex-dropdown-menu > li > a .trex-flag {margin-right:3px;width:23px;}
    .trex-dropdown-menu > li.trex-language-item > a:hover,
    .trex-dropdown-menu > li.trex-language-item > a:focus {text-decoration:none;background: #F0F2F4;}
    .trex-dropdown-menu > li.trex-language-item > a .trex-native-name {font-size: 11px;color: #A9AFB8;margin-left: 3px;}
    .trex-dropdown-menu > li.trex-selected a:after {content: "\2713";float: right;font-weight: bold;font-size: 16px;margin: 0px 5px 0 0;color: #13CF80;}
    .trex-dropdown-menu .trex-credit a {border-top: solid 1px #DDD;font-size: 13px;padding: 7px 0 0;margin: 5px 15px 5px;color: #9FA7AE;font-weight: 400;}
</style>


<div class="trex-language-selector">
    <a class="trex-language-toggle" data-toggle="language-selector" tabindex="0">
        <?php tml_language_name_tag(tml_current_language(), array("flag" => true)) ?>
    </a>
    <ul class="trex-dropdown-menu">
        <?php $languages = \tml\Config::instance()->application->languages; ?>

        <?php foreach($languages as $lang) { ?>
            <li class="trex-language-item">
                <a href='javascript:void(0);' onclick='tml_change_locale("<?php echo $lang->locale ?>")'>
                    <?php tml_language_name_tag($lang, array("flag" => true)) ?>
                </a>
            </li>
        <?php } ?>

        <?php if ($toggle) { ?>
            <li class='trex-credit'>
                <a href='javascript:void(0);' onclick='Tml.Utils.toggleInlineTranslations()'>
                    <?php tre($toggle_label) ?>
                </a>
            </li>
        <?php } ?>

        <?php if ($powered_by) { ?>
            <li class="trex-credit">
                <a href="http://translationexchange.com">
                    Powered by Translation Exchange
                </a>
            </li>
        <?php } ?>
    </ul>
</div>


<script>
    (function() {
        'use strict';

        // utilities
        function addEvent(evnt, elem, func) {
            if (elem.addEventListener) elem.addEventListener(evnt,func,false);
            else if (elem.attachEvent) elem.attachEvent("on"+evnt, func);
            else elem[evnt] = func;
        }

        function hasClass(elem, cls) {
            return elem.className.match(new RegExp('(\\s|^)' + cls + '(\\s|$)'));
        }

        function addClass(elem, cls) {
            if (!hasClass(elem, cls)) elem.className += " " + cls;
        }

        function removeClass(elem, cls) {
            if (hasClass(elem, cls)) {
                var reg = new RegExp('(\\s|^)' + cls + '(\\s|$)');
                elem.className = elem.className.replace(reg, ' ');
            }
        }

        function toggleClass(elem, cls) {
            if (!hasClass(elem, cls)) addClass(elem, cls);
            else removeClass(elem, cls);
        }

        var LanguageSelector = function(element) {
            this.element = element;
            this.element.setAttribute('tabindex', '0');
            addEvent('click', this.element, this.open.bind(this))
            addEvent('blur',  this.element, this.close.bind(this))
        };

        LanguageSelector.VERSION = "0.1.0";
        LanguageSelector.prototype = {
            adjustMenu: function(parent){
                removeClass(parent, "trex-dropup");
                removeClass(parent, "trex-dropleft");
                var
                        menu      = parent.querySelectorAll('.trex-dropdown-menu')[0],
                        bounds    = menu.getBoundingClientRect(),
                        vHeight   = Math.max(document.documentElement.clientHeight, window.innerHeight || 0),
                        vWidth    = Math.max(document.documentElement.clientWidth, window.innerWidth || 0),
                        buffer    = 10;

                if(bounds.top  + menu.offsetHeight + buffer > vHeight) addClass(parent, "trex-dropup");
                if(bounds.left + menu.offsetWidth  + buffer > vWidth)  addClass(parent, "trex-dropleft");
            },
            open: function(e){
                e = e || window.event;
                e.stopPropagation();
                e.preventDefault();
                var target = e.currentTarget || e.srcElement;
                if(hasClass(target.parentElement,'trex-open')) {
                    return this.close(e);
                }
                addClass(target.parentElement,'trex-open');
                this.adjustMenu(target.parentElement);
                return false;
            },
            close: function(e) {
                e = e || window.event;
                var target = e.currentTarget || e.srcElement;
                removeClass(target.parentElement, 'trex-open');

                if(e.relatedTarget && e.relatedTarget.getAttribute('data-toggle') !== 'language-selector') {
                    e.relatedTarget.click();
                }
                return false;
            }
        };
        var selectorList = document.querySelectorAll('[data-toggle=language-selector]');
        for (var i = 0, el, l = selectorList.length; i < l; i++) {
            el = selectorList[i];
            el.languageSelector = new LanguageSelector(el);
        }
    })();
</script>