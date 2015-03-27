<script>
    function tml_change_locale(locale) {
        var query_parts = window.location.href.split('#');
        var anchor = query_parts.length > 1 ? query_parts[1] : null;
        query_parts = query_parts[0].split('?');
        var query = query_parts.length > 1 ? query_parts[1] : null;

        var params = {};
        if (query) {
            var vars = query.split('&');
            for (var i = 0; i < vars.length; i++) {
                var pair = vars[i].split('=');
                params[pair[0]] = pair[1];
            }
        }
        params['locale'] = locale;

        query = [];
        var keys = Object.keys(params);
        for (i = 0; i < keys.length; i++) {
            query.push(keys[i] + "=" + params[keys[i]]);
        }

        var destination = query_parts[0];
        if (query.length > 0)
            destination = destination + '?' + query.join("&");
        if (anchor)
            destination = destination + '#' + anchor;

        window.location = destination;
    }
</script>
