<div id="tml-translation-center-loading" style="text-align:center; padding-top: 100px; font-size: 20px;">
    <img src="<?php echo plugins_url( 'translationexchange/assets/images/logo.png' ) ?>" style="width: 120px; margin: 20px;">
    <br>
    Initializing Translation Center....
</div>

<iframe id="tml-translation-center" src="http://localhost:3002/login?app_token=<?php echo get_option('tml_token')?>"
        style="display:none; margin-left:-20px; width: calc(100% + 20px); height: 400px;"
        onload="showTranslationCenter()"></iframe>

<script>
    function showTranslationCenter() {
        jQuery("#tml-translation-center-loading").hide();
        jQuery("#tml-translation-center").show();
    }

    function resizeIframe() {
        jQuery("#tml-translation-center").css('height',window.innerHeight - 100);
    }
    resizeIframe();
    jQuery(window).on('resize',resizeIframe )
</script>