<?php

define( 'VP_REST_API_VERSION', '2.0-beta3' );

include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-jsonserializable.php' );

include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-rest-server.php' );

include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-http-responseinterface.php' );
include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-http-response.php' );
include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-rest-response.php' );
require_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-rest-request.php' );

include_once( dirname( __FILE__ ) . '/extras.php' );

use VersionPress\Api\BundledWpApi\WP_REST_Server;
use VersionPress\Api\BundledWpApi\WP_REST_Response;
use VersionPress\Api\BundledWpApi\WP_REST_Request;
use VersionPress\Api\BundledWpApi\WP_HTTP_ResponseInterface;

function register_vp_rest_route( $namespace, $route, $args = array(), $override = false ) {

    global $wp_vp_rest_server;

    if ( isset( $args['callback'] ) ) {
        
        $args = array( $args );
    }

    $defaults = array(
        'methods'         => 'GET',
        'callback'        => null,
        'args'            => array(),
    );
    foreach ( $args as &$arg_group ) {
        $arg_group = array_merge( $defaults, $arg_group );
    }

    $full_route = '/' . trim( $namespace, '/' ) . '/' . trim( $route, '/' );
    $wp_vp_rest_server->register_route( $namespace, $full_route, $args, $override );
}

function vp_rest_api_init() {
    vp_rest_api_register_rewrites();

    global $wp;
    $wp->add_query_var( 'vp_rest_route' );
}
add_action( 'init', 'vp_rest_api_init' );

function vp_rest_api_register_rewrites() {
    add_rewrite_rule( '^' . vp_rest_get_url_prefix() . '/?$','index.php?vp_rest_route=/','top' );
    add_rewrite_rule( '^' . vp_rest_get_url_prefix() . '(.*)?','index.php?vp_rest_route=$matches[1]','top' );
}

function vp_rest_api_maybe_flush_rewrites() {
    $version = get_option( 'vp_rest_api_plugin_version', null );

    if ( empty( $version ) || VP_REST_API_VERSION !== $version ) {
        flush_rewrite_rules();
        update_option( 'vp_rest_api_plugin_version', VP_REST_API_VERSION );
    }

}
add_action( 'init', 'vp_rest_api_maybe_flush_rewrites', 999 );

function vp_rest_api_default_filters( $server ) {
    
    add_action( 'deprecated_function_run', 'vp_rest_handle_deprecated_function', 10, 3 );
    add_filter( 'deprecated_function_trigger_error', '__return_false' );
    add_action( 'deprecated_argument_run', 'vp_rest_handle_deprecated_argument', 10, 3 );
    add_filter( 'deprecated_argument_trigger_error', '__return_false' );

    add_filter( 'vp_rest_pre_serve_request', 'vp_rest_send_cors_headers' );
    add_filter( 'vp_rest_post_dispatch', 'vp_rest_send_allow_header', 10, 3 );

    add_filter( 'vp_rest_pre_dispatch', 'vp_rest_handle_options_request', 10, 3 );

}
add_action( 'vp_rest_api_init', 'vp_rest_api_default_filters', 10, 1 );

function vp_rest_api_loaded() {
    if ( empty( $GLOBALS['wp']->query_vars['vp_rest_route'] ) ) {
        return;
    }

    define( 'XMLRPC_REQUEST', true );

    define( 'VP_REST_REQUEST', true );

    global $wp_vp_rest_server;

    $wp_vp_rest_server_class = apply_filters( 'wp_vp_rest_server_class', 'VersionPress\\Api\\BundledWpApi\\WP_REST_Server' );
    $wp_vp_rest_server = new $wp_vp_rest_server_class;

    do_action( 'vp_rest_api_init', $wp_vp_rest_server );

    $wp_vp_rest_server->serve_request( $GLOBALS['wp']->query_vars['vp_rest_route'] );

    die();
}
add_action( 'parse_request', 'vp_rest_api_loaded' );

function vp_rest_api_activation( $network_wide ) {
    if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {
        $mu_blogs = wp_get_sites();

        foreach ( $mu_blogs as $mu_blog ) {
            switch_to_blog( $mu_blog['blog_id'] );

            vp_rest_api_register_rewrites();
            update_option( 'vp_rest_api_plugin_version', null );
        }

        restore_current_blog();
    } else {
        vp_rest_api_register_rewrites();
        update_option( 'vp_rest_api_plugin_version', null );
    }
}
register_activation_hook( __FILE__, 'vp_rest_api_activation' );

function vp_rest_api_deactivation( $network_wide ) {
    if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {

        $mu_blogs = wp_get_sites();

        foreach ( $mu_blogs as $mu_blog ) {
            switch_to_blog( $mu_blog['blog_id'] );
            delete_option( 'vp_rest_api_plugin_version' );
        }

        restore_current_blog();
    } else {
        delete_option( 'vp_rest_api_plugin_version' );
    }
}
register_deactivation_hook( __FILE__, 'vp_rest_api_deactivation' );

function vp_rest_get_url_prefix() {
    

    return apply_filters( 'vp_rest_url_prefix', 'vp-json' );
}

function get_vp_rest_url( $blog_id = null, $path = '', $scheme = 'json' ) {
    if ( get_option( 'permalink_structure' ) ) {
        $url = trailingslashit( get_home_url( $blog_id, vp_rest_get_url_prefix(), $scheme ) );

        if ( ! empty( $path ) && is_string( $path ) && strpos( $path, '..' ) === false ) {
            $url .= '/' . ltrim( $path, '/' );
        }
    } else {
        $url = trailingslashit( get_home_url( $blog_id, '', $scheme ) );

        if ( empty( $path ) ) {
            $path = '/';
        } else {
            $path = '/' . ltrim( $path, '/' );
        }

        $url = add_query_arg( 'vp_rest_route', $path, $url );
    }

    return apply_filters( 'vp_rest_url', $url, $path, $blog_id, $scheme );
}

function vp_rest_url( $path = '', $scheme = 'json' ) {
    return get_vp_rest_url( null, $path, $scheme );
}

function vp_rest_do_request( $request ) {
    global $wp_vp_rest_server;
    $request = vp_rest_ensure_request( $request );
    return $wp_vp_rest_server->dispatch( $request );
}

function vp_rest_ensure_request( $request ) {
    if ( $request instanceof WP_REST_Request ) {
        return $request;
    }

    return new WP_REST_Request( 'GET', '', $request );
}

function vp_rest_ensure_response( $response ) {
    if ( is_wp_error( $response ) ) {
        return $response;
    }

    if ( $response instanceof WP_HTTP_ResponseInterface ) {
        return $response;
    }

    return new WP_REST_Response( $response );
}

function vp_rest_handle_deprecated_function( $function, $replacement, $version ) {
    if ( ! empty( $replacement ) ) {
        $string = sprintf( __( '%1$s (since %2$s; use %3$s instead)' ), $function, $version, $replacement );
    } else {
        $string = sprintf( __( '%1$s (since %2$s; no alternative available)' ), $function, $version );
    }

    header( sprintf( 'X-WP-DeprecatedFunction: %s', $string ) );
}

function vp_rest_handle_deprecated_argument( $function, $replacement, $version ) {
    if ( ! empty( $replacement ) ) {
        $string = sprintf( __( '%1$s (since %2$s; %3$s)' ), $function, $version, $replacement );
    } else {
        $string = sprintf( __( '%1$s (since %2$s; no alternative available)' ), $function, $version );
    }

    header( sprintf( 'X-WP-DeprecatedParam: %s', $string ) );
}

function vp_rest_send_cors_headers( $value ) {
    $origin = get_http_origin();

    if ( $origin ) {
        header( 'Access-Control-Allow-Origin: ' . esc_url_raw( $origin ) );
        header( 'Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE' );
        header( 'Access-Control-Allow-Credentials: true' );
    }

    return $value;
}

function vp_rest_handle_options_request( $response, $handler, $request ) {
    if ( ! empty( $response ) || $request->get_method() !== 'OPTIONS' ) {
        return $response;
    }

    $response = new WP_REST_Response();

    $accept = array();

    foreach ( $handler->get_routes() as $route => $endpoints ) {
        $match = preg_match( '@^' . $route . '$@i', $request->get_route(), $args );

        if ( ! $match ) {
            continue;
        }

        foreach ( $endpoints as $endpoint ) {
            $accept = array_merge( $accept, $endpoint['methods'] );
        }
        break;
    }
    $accept = array_keys( $accept );

    $response->header( 'Accept', implode( ', ', $accept ) );

    return $response;
}

function vp_rest_send_allow_header( $response, $server, $request ) {

    $matched_route = $response->get_matched_route();

    if ( ! $matched_route ) {
        return $response;
    }

    $routes = $server->get_routes();

    $allowed_methods = array();

    foreach ( $routes[ $matched_route ] as $_handler ) {
        foreach ( $_handler['methods'] as $handler_method => $value ) {

            if ( ! empty( $_handler['permission_callback'] ) ) {

                $permission = call_user_func( $_handler['permission_callback'], $request );

                $allowed_methods[ $handler_method ] = true === $permission;
            } else {
                $allowed_methods[ $handler_method ] = true;
            }
        }
    }

    $allowed_methods = array_filter( $allowed_methods );

    if ( $allowed_methods ) {
        $response->header( 'Allow', implode( ', ', array_map( 'strtoupper', array_keys( $allowed_methods ) ) ) );
    }

    return $response;
}

if ( ! function_exists( 'json_last_error_msg' ) ) :
    

    function json_last_error_msg() {
        
        if ( ! function_exists( 'json_last_error' ) ) {
            return false;
        }

        $last_error_code = json_last_error();

        $error_code_none = defined( 'JSON_ERROR_NONE' ) ? JSON_ERROR_NONE : 0;

        switch ( true ) {
            case $last_error_code === $error_code_none:
                return 'No error';

            case defined( 'JSON_ERROR_DEPTH' ) && JSON_ERROR_DEPTH === $last_error_code:
                return 'Maximum stack depth exceeded';

            case defined( 'JSON_ERROR_STATE_MISMATCH' ) && JSON_ERROR_STATE_MISMATCH === $last_error_code:
                return 'State mismatch (invalid or malformed JSON)';

            case defined( 'JSON_ERROR_CTRL_CHAR' ) && JSON_ERROR_CTRL_CHAR === $last_error_code:
                return 'Control character error, possibly incorrectly encoded';

            case defined( 'JSON_ERROR_SYNTAX' ) && JSON_ERROR_SYNTAX === $last_error_code:
                return 'Syntax error';

            case defined( 'JSON_ERROR_UTF8' ) && JSON_ERROR_UTF8 === $last_error_code:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';

            case defined( 'JSON_ERROR_RECURSION' ) && JSON_ERROR_RECURSION === $last_error_code:
                return 'Recursion detected';

            case defined( 'JSON_ERROR_INF_OR_NAN' ) && JSON_ERROR_INF_OR_NAN === $last_error_code:
                return 'Inf and NaN cannot be JSON encoded';

            case defined( 'JSON_ERROR_UNSUPPORTED_TYPE' ) && JSON_ERROR_UNSUPPORTED_TYPE === $last_error_code:
                return 'Type is not supported';

            default:
                return 'An unknown error occurred';
        }
    }
endif;
