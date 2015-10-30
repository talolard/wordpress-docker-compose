<?php

namespace VersionPress\Api\BundledWpApi;

use ArrayAccess;
use WP_Error;

class WP_REST_Request implements ArrayAccess {
    

    protected $method = '';

    protected $params;

    protected $headers = array();

    protected $body = null;

    protected $route;

    protected $attributes = array();

    protected $parsed_json = false;

    protected $parsed_body = false;

    public function __construct( $method = '', $route = '', $attributes = array() ) {
        $this->params = array(
            'URL'   => array(),
            'GET'   => array(),
            'POST'  => array(),
            'FILES' => array(),

            'JSON'  => null,

            'defaults' => array(),
        );

        $this->set_method( $method );
        $this->set_route( $route );
        $this->set_attributes( $attributes );
    }

    public function get_method() {
        return $this->method;
    }

    public function set_method( $method ) {
        $this->method = strtoupper( $method );
    }

    public function get_headers() {
        return $this->headers;
    }

    public static function canonicalize_header_name( $key ) {
        $key = strtolower( $key );
        $key = str_replace( '-', '_', $key );

        return $key;
    }

    public function get_header( $key ) {
        $key = $this->canonicalize_header_name( $key );

        if ( ! isset( $this->headers[ $key ] ) ) {
            return null;
        }

        return implode( ',', $this->headers[ $key ] );
    }

    public function get_header_as_array( $key ) {
        $key = $this->canonicalize_header_name( $key );

        if ( ! isset( $this->headers[ $key ] ) ) {
            return null;
        }

        return $this->headers[ $key ];
    }

    public function set_header( $key, $value ) {
        $key = $this->canonicalize_header_name( $key );
        $value = (array) $value;

        $this->headers[ $key ] = $value;
    }

    public function add_header( $key, $value ) {
        $key = $this->canonicalize_header_name( $key );
        $value = (array) $value;

        if ( ! isset( $this->headers[ $key ] ) ) {
            $this->headers[ $key ] = array();
        }

        $this->headers[ $key ] = array_merge( $this->headers[ $key ], $value );
    }

    public function remove_header( $key ) {
        unset( $this->headers[ $key ] );
    }

    public function set_headers( $headers, $override = true ) {
        if ( true === $override ) {
            $this->headers = array();
        }

        foreach ( $headers as $key => $value ) {
            $this->set_header( $key, $value );
        }
    }

    public function get_content_type() {
        $value = $this->get_header( 'content-type' );
        if ( empty( $value ) ) {
            return null;
        }

        $parameters = '';
        if ( strpos( $value, ';' ) ) {
            list( $value, $parameters ) = explode( ';', $value, 2 );
        }

        $value = strtolower( $value );
        if ( strpos( $value, '/' ) === false ) {
            return null;
        }

        list( $type, $subtype ) = explode( '/', $value, 2 );

        $data = compact( 'value', 'type', 'subtype', 'parameters' );
        $data = array_map( 'trim', $data );

        return $data;
    }

    protected function get_parameter_order() {
        $order = array();
        $order[] = 'JSON';

        $this->parse_json_params();

        $body = $this->get_body();
        if ( $this->method !== 'POST' && ! empty( $body ) ) {
            $this->parse_body_params();
        }

        $accepts_body_data = array( 'POST', 'PUT', 'PATCH' );
        if ( in_array( $this->method, $accepts_body_data ) ) {
            $order[] = 'POST';
        }

        $order[] = 'GET';
        $order[] = 'URL';
        $order[] = 'defaults';

        return apply_filters( 'rest_request_parameter_order', $order, $this );
    }

    public function get_param( $key ) {
        $order = $this->get_parameter_order();

        foreach ( $order as $type ) {
            
            if ( isset( $this->params[ $type ][ $key ] ) ) {
                return $this->params[ $type ][ $key ];
            }
        }

        return null;
    }

    public function set_param( $key, $value ) {
        switch ( $this->method ) {
            case 'POST':
                $this->params['POST'][ $key ] = $value;
                break;

            default:
                $this->params['GET'][ $key ] = $value;
                break;
        }
    }

    public function get_params() {
        $order = $this->get_parameter_order();
        $order = array_reverse( $order, true );

        $params = array();
        foreach ( $order as $type ) {
            $params = array_merge( $params, (array) $this->params[ $type ] );
        }

        return $params;
    }

    public function get_url_params() {
        return $this->params['URL'];
    }

    public function set_url_params( $params ) {
        $this->params['URL'] = $params;
    }

    public function get_query_params() {
        return $this->params['GET'];
    }

    public function set_query_params( $params ) {
        $this->params['GET'] = $params;
    }

    public function get_body_params() {
        return $this->params['POST'];
    }

    public function set_body_params( $params ) {
        $this->params['POST'] = $params;
    }

    public function get_file_params() {
        return $this->params['FILES'];
    }

    public function set_file_params( $params ) {
        $this->params['FILES'] = $params;
    }

    public function get_default_params() {
        return $this->params['defaults'];
    }

    public function set_default_params( $params ) {
        $this->params['defaults'] = $params;
    }

    public function get_body() {
        return $this->body;
    }

    public function set_body( $data ) {
        $this->body = $data;

        $this->parsed_json = false;
        $this->parsed_body = false;
        $this->params['JSON'] = null;
    }

    public function get_json_params() {
        
        $this->parse_json_params();

        return $this->params['JSON'];
    }

    protected function parse_json_params() {
        if ( $this->parsed_json ) {
            return;
        }
        $this->parsed_json = true;

        $content_type = $this->get_content_type();
        if ( empty( $content_type ) || 'application/json' !== $content_type['value'] ) {
            return;
        }

        $params = json_decode( $this->get_body(), true );

        if ( null === $params && ( ! function_exists( 'json_last_error' ) || JSON_ERROR_NONE !== json_last_error() ) ) {
            return;
        }

        $this->params['JSON'] = $params;
    }

    protected function parse_body_params() {
        if ( $this->parsed_body ) {
            return;
        }
        $this->parsed_body = true;

        $content_type = $this->get_content_type();
        if ( ! empty( $content_type ) && 'application/x-www-form-urlencoded' !== $content_type['value'] ) {
            return;
        }

        parse_str( $this->get_body(), $params );

        if ( get_magic_quotes_gpc() ) {
            $params = stripslashes_deep( $params );
        }
        

        $this->params['POST'] = array_merge( $params, $this->params['POST'] );
    }

    public function get_route() {
        return $this->route;
    }

    public function set_route( $route ) {
        $this->route = $route;
    }

    public function get_attributes() {
        return $this->attributes;
    }

    public function set_attributes( $attributes ) {
        $this->attributes = $attributes;
    }

    public function sanitize_params() {

        $attributes = $this->get_attributes();

        if ( empty( $attributes['args'] ) ) {
            return true;
        }

        $order = $this->get_parameter_order();

        foreach ( $order as $type ) {
            if ( empty( $this->params[ $type ] ) ) {
                continue;
            }
            foreach ( $this->params[ $type ] as $key => $value ) {
                
                if ( isset( $attributes['args'][ $key ] ) && ! empty( $attributes['args'][ $key ]['sanitize_callback'] ) ) {
                    $this->params[ $type ][ $key ] = call_user_func( $attributes['args'][ $key ]['sanitize_callback'], $value, $this, $key );
                }
            }
        }
    }

    public function has_valid_params() {

        $attributes = $this->get_attributes();
        $required = array();

        if ( empty( $attributes['args'] ) ) {
            return true;
        }

        foreach ( $attributes['args'] as $key => $arg ) {

            $param = $this->get_param( $key );
            if ( isset( $arg['required'] ) && true === $arg['required'] && null === $param ) {
                $required[] = $key;
            }
        }

        if ( ! empty( $required ) ) {
            return new WP_Error( 'rest_missing_callback_param', sprintf( __( 'Missing parameter(s): %s' ), implode( ', ', $required ) ), array( 'status' => 400, 'params' => $required ) );
        }

        $invalid_params = array();

        foreach ( $attributes['args'] as $key => $arg ) {

            $param = $this->get_param( $key );

            if ( null !== $param && ! empty( $arg['validate_callback'] ) ) {
                $valid_check = call_user_func( $arg['validate_callback'], $param, $this, $key );

                if ( false === $valid_check ) {
                    $invalid_params[ $key ] = __( 'Invalid param.' );
                }

                if ( is_wp_error( $valid_check ) ) {
                    $invalid_params[] = sprintf( '%s (%s)', $key, $valid_check->get_error_message() );
                }
            }
        }

        if ( $invalid_params ) {
            return new WP_Error( 'rest_invalid_param', sprintf( __( 'Invalid parameter(s): %s' ), implode( ', ', $invalid_params ) ), array( 'status' => 400, 'params' => $invalid_params ) );
        }

        return true;

    }

    public function offsetExists( $offset ) {
        
        $order = $this->get_parameter_order();

        foreach ( $order as $type ) {
            if ( isset( $this->params[ $type ][ $offset ] ) ) {
                return true;
            }
        }

        return false;
    }

    public function offsetGet( $offset ) {
        
        return $this->get_param( $offset );
    }

    public function offsetSet( $offset, $value ) {
        
        return $this->set_param( $offset, $value );
    }

    public function offsetUnset( $offset ) {
        
        $order = $this->get_parameter_order();

        foreach ( $order as $type ) {
            unset( $this->params[ $type ][ $offset ] );
        }
    }
}
