<?php

namespace VersionPress\Api\BundledWpApi;

use JsonSerializable;

interface WP_HTTP_ResponseInterface extends JsonSerializable {
    

    public function get_headers();

    public function get_status();

    public function get_data();

}
