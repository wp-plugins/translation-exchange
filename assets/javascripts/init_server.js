function tml_add_css(doc, value, inline) {
    var css = null;
    if (inline) {
        css = doc.createElement('style'); css.type = 'text/css';
        if (css.styleSheet) css.styleSheet.cssText = value;
        else css.appendChild(document.createTextNode(value));
    } else {
        css = doc.createElement('link'); css.setAttribute('type', 'text/css');
        css.setAttribute('rel', 'stylesheet'); css.setAttribute('media', 'screen');
        if (value.indexOf('//') != -1) css.setAttribute('href', value);
        else css.setAttribute('href', TmlConfig.host + value);
    }
    doc.getElementsByTagName('head')[0].appendChild(css);
    return css;
}

function tml_add_script(doc, id, src, onload) {
    var script = doc.createElement('script');
    script.setAttribute('id', id); script.setAttribute('type', 'application/javascript');
    if (src.indexOf('//') != -1)  script.setAttribute('src', src);
    else script.setAttribute('src', TmlConfig.host + src);
    script.setAttribute('charset', 'UTF-8');
    if (onload) script.onload = onload;
    doc.getElementsByTagName('head')[0].appendChild(script);
    return script;
}

(function() {
    if (window.addEventListener) window.addEventListener('load', tml_init, false); // Standard
    else if (window.attachEvent) window.attachEvent('onload', tml_init); // Microsoft
    window.setTimeout(function() { tml_init(); }, 1000);

    function tml_init() {
        if (window.tml_already_initialized) return;
        window.tml_already_initialized = true;
        tml_add_css(window.document, TmlConfig.stylesheet, false);
        tml_add_css(window.document, TmlConfig.css, true);

        tml_add_script(window.document, 'tml-jssdk', TmlConfig.javascript, function() {
            Tml.app_key = TmlConfig.key;
            Tml.host = TmlConfig.tools;
            Tml.default_locale = TmlConfig.default_locale;
            Tml.page_locale = TmlConfig.page_locale;
            Tml.locale = TmlConfig.locale;
            if (TmlConfig.shortcuts) {
                var shortcutFn = function(sc){ return function() { eval(TmlConfig.shortcuts[sc]); }; };
                for (var sc in TmlConfig.shortcuts) {
                    shortcut.add(sc, shortcutFn(sc));
                }
            }
        });
    }
})();