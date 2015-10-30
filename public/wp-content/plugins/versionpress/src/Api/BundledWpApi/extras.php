<?php

add_action( 'xmlrpc_rsd_apis', 'vp_rest_output_rsd' );
add_action( 'wp_head', 'vp_rest_output_link_wp_head', 10, 0 );
add_action( 'template_redirect', 'vp_rest_output_link_header', 11, 0 );
add_action( 'auth_cookie_malformed',    'vp_rest_cookie_collect_status' );
add_action( 'auth_cookie_expired',      'vp_rest_cookie_collect_status' );
add_action( 'auth_cookie_bad_username', 'vp_rest_cookie_collect_status' );
add_action( 'auth_cookie_bad_hash',     'vp_rest_cookie_collect_status' );
add_action( 'auth_cookie_valid',        'vp_rest_cookie_collect_status' );
add_filter( 'vp_rest_authentication_errors', 'vp_rest_cookie_check_errors', 100 );

function vp_rest_output_rsd() {
    $api_root = get_vp_rest_url();

    if ( empty( $api_root ) ) {
        return;
    }
    ?>
    <api name="VP-API" blogID="1" preferred="false" apiLink="<?php echo esc_url( $api_root ); ?>" />
    <?php
}

function vp_rest_output_link_wp_head() {
    $api_root = get_vp_rest_url();

    if ( empty( $api_root ) ) {
        return;
    }

    echo "<link rel='https://github.com/WP-API/WP-API' href='" . esc_url( $api_root ) . "' />\n";
}

function vp_rest_output_link_header() {
    if ( headers_sent() ) {
        return;
    }

    $api_root = get_vp_rest_url();

    if ( empty( $api_root ) ) {
        return;
    }

    header( 'Link: <' . esc_url_raw( $api_root ) . '>; rel="https://github.com/WP-API/WP-API"', false );
}

function vp_rest_cookie_check_errors( $result ) {
    if ( ! empty( $result ) ) {
        return $result;
    }

    global $wp_vp_rest_auth_cookie;

    if ( true !== $wp_vp_rest_auth_cookie && is_user_logged_in() ) {
        return $result;
    }

    $nonce = null;
    if ( isset( $_REQUEST['_wp_vp_rest_nonce'] ) ) {
        $nonce = $_REQUEST['_wp_vp_rest_nonce'];
    } elseif ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
        $nonce = $_SERVER['HTTP_X_WP_NONCE'];
    }

    if ( null === $nonce ) {
        
        wp_set_current_user( 0 );
        return true;
    }

    $result = wp_verify_nonce( $nonce, 'wp_rest' );
    if ( ! $result ) {
        return new WP_Error( 'vp_rest_cookie_invalid_nonce', __( 'Cookie nonce is invalid' ), array( 'status' => 403 ) );
    }

    return true;
}

function vp_rest_cookie_collect_status() {
    global $wp_vp_rest_auth_cookie;

    $status_type = current_action();

    if ( 'auth_cookie_valid' !== $status_type ) {
        $wp_vp_rest_auth_cookie = substr( $status_type, 12 );
        return;
    }

    $wp_vp_rest_auth_cookie = true;
}

function vp_rest_get_avatar_urls( $email ) {
    $avatar_sizes = vp_rest_get_avatar_sizes();

    $urls = array();
    foreach ( $avatar_sizes as $size ) {
        $urls[ $size ] = get_avatar_url( $email, array( 'size' => $size ) );
    }

    return $urls;
}

function vp_rest_get_avatar_sizes() {
    return apply_filters( 'vp_rest_avatar_sizes', array( 24, 48, 96 ) );
}

function vp_rest_parse_date( $date, $force_utc = false ) {
    if ( $force_utc ) {
        $date = preg_replace( '/[+-]\d+:?\d+$/', '+00:00', $date );
    }

    $regex = '#^\d{4}-\d{2}-\d{2}[Tt ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}(?::\d{2})?)?$#';

    if ( ! preg_match( $regex, $date, $matches ) ) {
        return false;
    }

    return strtotime( $date );
}

function vp_rest_get_date_with_gmt( $date, $force_utc = false ) {
    $date = vp_rest_parse_date( $date, $force_utc );

    if ( empty( $date ) ) {
        return null;
    }

    $utc = date( 'Y-m-d H:i:s', $date );
    $local = get_date_from_gmt( $utc );

    return array( $local, $utc );
}

function vp_rest_mysql_to_rfc3339( $date_string ) {
    $formatted = mysql2date( 'c', $date_string, false );

    return preg_replace( '/(?:Z|[+-]\d{2}(?::\d{2})?)$/', '', $formatted );
}

function vp_rest_get_timezone() {
    static $zone = null;

    if ( null !== $zone ) {
        return $zone;
    }

    $tzstring = get_option( 'timezone_string' );

    if ( ! $tzstring ) {
        
        $current_offset = get_option( 'gmt_offset' );
        if ( 0 === $current_offset ) {
            $tzstring = 'UTC';
        } elseif ( $current_offset < 0 ) {
            $tzstring = 'Etc/GMT' . $current_offset;
        } else {
            $tzstring = 'Etc/GMT+' . $current_offset;
        }
    }
    $zone = new DateTimeZone( $tzstring );

    return $zone;
}

function vp_rest_get_avatar_url( $email ) {
    _deprecated_function( 'vp_rest_get_avatar_url', 'WPAPI-2.0', 'vp_rest_get_avatar_urls' );
    

    if ( function_exists( 'get_avatar_url' ) ) {
        return esc_url_raw( get_avatar_url( $email ) );
    }
    $avatar_html = get_avatar( $email );

    preg_match( '/src=["|\'](.+)[\&|"|\']/U', $avatar_html, $matches );

    if ( isset( $matches[1] ) && ! empty( $matches[1] ) ) {
        return esc_url_raw( $matches[1] );
    }

    return '';
}
