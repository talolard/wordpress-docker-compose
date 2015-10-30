<?php

namespace VersionPress\Utils;

class JsRedirect {

    public static function redirect($url, $timeout = 0) {
        $redirectionJs = <<<JS
<script type="text/javascript">
    window.setTimeout(function () {
        window.location = '$url';
    }, $timeout);
</script>
JS;
        echo $redirectionJs;
    }
} 
