<?php

namespace VersionPress\Api\BundledWpApi;

use WP_Error;
use JsonSerializable;

class WP_REST_Server {
    

    const METHOD_GET    = 'GET';

    const METHOD_POST   = 'POST';

    const METHOD_PUT    = 'PUT';

    const METHOD_PATCH  = 'PATCH';

    const METHOD_DELETE = 'DELETE';

    const READABLE   = 'GET';

    const CREATABLE  = 'POST';

    const EDITABLE   = 'POST, PUT, PATCH';

    const DELETABLE  = 'DELETE';

    const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';

    const ACCEPT_RAW = 64;

    const ACCEPT_JSON = 128;

    const HIDDEN_ENDPOINT = 256;

    public static $method_map = array(
        'HEAD'   => self::METHOD_GET,
        'GET'    => self::METHOD_GET,
        'POST'   => self::METHOD_POST,
        'PUT'    => self::METHOD_PUT,
        'PATCH'  => self::METHOD_PATCH,
        'DELETE' => self::METHOD_DELETE,
    );

    protected $namespaces = array();

    protected $endpoints = array();

    protected $route_options = array();

    public function __construct() {
        $this->endpoints = array(
            
            '/' => array(
                'callback' => array( $this, 'get_index' ),
                'methods' => 'GET',
            ),
        );
    }

    public function check_authentication() {
        

        return apply_filters( 'vp_rest_authentication_errors', null );
    }

    protected function error_to_response( $error ) {
        $error_data = $error->get_error_data();
        if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
            $status = $error_data['status'];
        } else {
            $status = 500;
        }

        $data = array();
        foreach ( (array) $error->errors as $code => $messages ) {
            foreach ( (array) $messages as $message ) {
                $data[] = array( 'code' => $code, 'message' => $message, 'data' => $error->get_error_data( $code ) );
            }
        }
        $response = new WP_REST_Response( $data, $status );

        return $response;
    }

    protected function json_error( $code, $message, $status = null ) {
        if ( $status ) {
            $this->set_status( $status );
        }
        $error = compact( 'code', 'message' );

        return wp_json_encode( array( $error ) );
    }

    public function serve_request( $path = null ) {
        $content_type = isset( $_GET['_jsonp'] ) ? 'application/javascript' : 'application/json';
        $this->send_header( 'Content-Type', $content_type . '; charset=' . get_option( 'blog_charset' ) );

        $this->send_header( 'X-Content-Type-Options', 'nosniff' );

        $enabled = apply_filters( 'vp_rest_enabled', true );

        $jsonp_enabled = apply_filters( 'vp_rest_jsonp_enabled', true );

        if ( ! $enabled ) {
            echo $this->json_error( 'vp_rest_disabled', __( 'The REST API is disabled on this site.' ), 404 );
            return false;
        }
        if ( isset( $_GET['_jsonp'] ) ) {
            if ( ! $jsonp_enabled ) {
                echo $this->json_error( 'vp_rest_callback_disabled', __( 'JSONP support is disabled on this site.' ), 400 );
                return false;
            }

            if ( ! is_string( $_GET['_jsonp'] ) || preg_match( '/\W\./', $_GET['_jsonp'] ) ) {
                echo $this->json_error( 'vp_rest_callback_invalid', __( 'The JSONP callback function is invalid.' ), 400 );
                return false;
            }
        }

        if ( empty( $path ) ) {
            if ( isset( $_SERVER['PATH_INFO'] ) ) {
                $path = $_SERVER['PATH_INFO'];
            } else {
                $path = '/';
            }
        }

        $request = new WP_REST_Request( $_SERVER['REQUEST_METHOD'], $path );
        $request->set_query_params( $_GET );
        $request->set_body_params( $_POST );
        $request->set_file_params( $_FILES );
        $request->set_headers( $this->get_headers( $_SERVER ) );
        $request->set_body( $this->get_raw_data() );

        if ( isset( $_GET['_method'] ) ) {
            $request->set_method( $_GET['_method'] );
        } elseif ( isset( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ) ) {
            $request->set_method( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] );
        }

        $result = $this->check_authentication();

        if ( ! is_wp_error( $result ) ) {
            

            $result = apply_filters( 'vp_rest_pre_dispatch', null, $this, $request );
        }

        if ( empty( $result ) ) {
            $result = $this->dispatch( $request );
        }

        $result = vp_rest_ensure_response( $result );

        if ( is_wp_error( $result ) ) {
            $result = $this->error_to_response( $result );
        }

        $result = apply_filters( 'vp_rest_post_dispatch', vp_rest_ensure_response( $result ), $this, $request );

        if ( isset( $_GET['_envelope'] ) ) {
            $result = $this->envelope_response( $result, isset( $_GET['_embed'] ) );
        }

        $headers = $result->get_headers();
        $this->send_headers( $headers );

        $code = $result->get_status();
        $this->set_status( $code );

        $served = apply_filters( 'vp_rest_pre_serve_request', false, $result, $request, $this );

        if ( ! $served ) {
            if ( 'HEAD' === $request->get_method() ) {
                return;
            }

            $result = $this->response_to_data( $result, isset( $_GET['_embed'] ) );

            $result = wp_json_encode( $result );

            $json_error_message = $this->get_json_last_error();
            if ( $json_error_message ) {
                $json_error_obj = new WP_Error( 'vp_rest_encode_error', $json_error_message, array( 'status' => 500 ) );
                $result = $this->error_to_response( $json_error_obj );
                $result = wp_json_encode( $result->data[0] );
            }

            if ( isset( $_GET['_jsonp'] ) ) {
                
                
                echo '/**/' . $_GET['_jsonp'] . '(' . $result . ')';
            } else {
                echo $result;
            }
        }
    }

    public function response_to_data( $response, $embed ) {
        $data  = $this->prepare_response( $response->get_data() );
        $links = $this->get_response_links( $response );

        if ( ! empty( $links ) ) {
            
            $data['_links'] = $links;

            if ( $embed ) {
                $data = $this->embed_links( $data );
            }
        }

        return $data;
    }

    public static function get_response_links( $response ) {
        $links = $response->get_links();

        if ( empty( $links ) ) {
            return array();
        }

        $data = array();
        foreach ( $links as $rel => $items ) {
            $data[ $rel ] = array();

            foreach ( $items as $item ) {
                $attributes = $item['attributes'];
                $attributes['href'] = $item['href'];
                $data[ $rel ][] = $attributes;
            }
        }

        return $data;
    }

    protected function embed_links( $data ) {
        if ( empty( $data['_links'] ) ) {
            return $data;
        }

        $embedded = array();
        $api_root = vp_rest_url();
        foreach ( $data['_links'] as $rel => $links ) {
            
            if ( 'self' === $rel ) {
                continue;
            }

            $embeds = array();

            foreach ( $links as $item ) {
                
                if ( empty( $item['embeddable'] ) || strpos( $item['href'], $api_root ) !== 0 ) {
                    
                    $embeds[] = array();
                    continue;
                }

                $route = substr( $item['href'], strlen( untrailingslashit( $api_root ) ) );
                $query_params = array();

                $parsed = parse_url( $route );
                if ( empty( $parsed['path'] ) ) {
                    $embeds[] = array();
                    continue;
                }

                if ( ! empty( $parsed['query'] ) ) {
                    parse_str( $parsed['query'], $query_params );

                    if ( get_magic_quotes_gpc() ) {
                        $query_params = stripslashes_deep( $query_params );
                    }
                    
                }

                $query_params['context'] = 'embed';

                $request = new WP_REST_Request( 'GET', $parsed['path'] );
                $request->set_query_params( $query_params );
                $response = $this->dispatch( $request );

                $embeds[] = $response;
            }

            $has_links = count( array_filter( $embeds ) );
            if ( $has_links ) {
                $embedded[ $rel ] = $embeds;
            }
        }

        if ( ! empty( $embedded ) ) {
            $data['_embedded'] = $embedded;
        }

        return $data;
    }

    public function envelope_response( $response, $embed ) {
        $envelope = array(
            'body'    => $this->response_to_data( $response, $embed ),
            'status'  => $response->get_status(),
            'headers' => $response->get_headers(),
        );

        $envelope = apply_filters( 'vp_rest_envelope_response', $envelope, $response );

        return vp_rest_ensure_response( $envelope );
    }

    public function register_route( $namespace, $route, $route_args, $override = false ) {
        if ( ! isset( $this->namespaces[ $namespace ] ) ) {
            $this->namespaces[ $namespace ] = array();

            $this->register_route( $namespace, '/' . $namespace, array(
                array(
                    'methods' => self::READABLE,
                    'callback' => array( $this, 'get_namespace_index' ),
                    'args' => array(
                        'namespace' => array(
                            'default' => $namespace,
                        ),
                    ),
                ),
            ) );
        }

        $this->namespaces[ $namespace ][ $route ] = true;
        $route_args['namespace'] = $namespace;

        if ( $override || empty( $this->endpoints[ $route ] ) ) {
            $this->endpoints[ $route ] = $route_args;
        } else {
            $this->endpoints[ $route ] = array_merge( $this->endpoints[ $route ], $route_args );
        }
    }

    public function get_routes() {

        $endpoints = apply_filters( 'vp_rest_endpoints', $this->endpoints );

        $defaults = array(
            'methods'       => '',
            'accept_json'   => false,
            'accept_raw'    => false,
            'show_in_index' => true,
            'args'          => array(),
        );
        foreach ( $endpoints as $route => &$handlers ) {
            if ( isset( $handlers['callback'] ) ) {
                
                $handlers = array( $handlers );
            }
            if ( ! isset( $this->route_options[ $route ] ) ) {
                $this->route_options[ $route ] = array();
            }

            foreach ( $handlers as $key => &$handler ) {
                if ( ! is_numeric( $key ) ) {
                    
                    $this->route_options[ $route ][ $key ] = $handler;
                    unset( $handlers[ $key ] );
                    continue;
                }
                $handler = wp_parse_args( $handler, $defaults );

                if ( is_string( $handler['methods'] ) ) {
                    $methods = explode( ',', $handler['methods'] );
                } else if ( is_array( $handler['methods'] ) ) {
                    $methods = $handler['methods'];
                }

                $handler['methods'] = array();
                foreach ( $methods as $method ) {
                    $method = strtoupper( trim( $method ) );
                    $handler['methods'][ $method ] = true;
                }
            }
        }
        return $endpoints;
    }

    public function get_namespaces() {
        return array_keys( $this->namespaces );
    }

    public function dispatch( $request ) {
        $method = $request->get_method();
        $path   = $request->get_route();

        foreach ( $this->get_routes() as $route => $handlers ) {
            foreach ( $handlers as $handler ) {
                $callback  = $handler['callback'];
                $supported = $handler['methods'];
                $response = null;

                if ( empty( $handler['methods'][ $method ] ) ) {
                    continue;
                }

                $match = preg_match( '@^' . $route . '$@i', $path, $args );

                if ( ! $match ) {
                    continue;
                }

                if ( ! is_callable( $callback ) ) {
                    $response = new WP_Error( 'vp_rest_invalid_handler', __( 'The handler for the route is invalid' ), array( 'status' => 500 ) );
                }

                if ( ! is_wp_error( $response ) ) {

                    $request->set_url_params( $args );
                    $request->set_attributes( $handler );

                    $request->sanitize_params();

                    $defaults = array();

                    foreach ( $handler['args'] as $arg => $options ) {
                        if ( isset( $options['default'] ) ) {
                            $defaults[ $arg ] = $options['default'];
                        }
                    }

                    $request->set_default_params( $defaults );

                    $check_required = $request->has_valid_params();
                    if ( is_wp_error( $check_required ) ) {
                        $response = $check_required;
                    }
                }

                if ( ! is_wp_error( $response ) ) {
                    
                    if ( ! empty( $handler['permission_callback'] ) ) {
                        $permission = call_user_func( $handler['permission_callback'], $request );

                        if ( is_wp_error( $permission ) ) {
                            $response = $permission;
                        } else if ( false === $permission || null === $permission ) {
                            $response = new WP_Error( 'vp_rest_forbidden', __( "You don't have permission to do this." ), array( 'status' => 403 ) );
                        }
                    }
                }

                if ( ! is_wp_error( $response ) ) {
                    

                    $dispatch_result = apply_filters( 'vp_rest_dispatch_request', null, $request );

                    if ( null !== $dispatch_result ) {
                        $response = $dispatch_result;
                    } else {
                        $response = call_user_func( $callback, $request );
                    }
                }

                if ( is_wp_error( $response ) ) {
                    $response = $this->error_to_response( $response );
                } else {
                    $response = vp_rest_ensure_response( $response );
                }

                $response->set_matched_route( $route );
                $response->set_matched_handler( $handler );

                return $response;
            }
        }

        return $this->error_to_response( new WP_Error( 'vp_rest_no_route', __( 'No route was found matching the URL and request method' ), array( 'status' => 404 ) ) );
    }

    protected function get_json_last_error( ) {
        
        if ( ! function_exists( 'json_last_error' ) ) {
            return false;
        }

        $last_error_code = json_last_error();
        if ( ( defined( 'JSON_ERROR_NONE' ) && JSON_ERROR_NONE === $last_error_code ) || empty( $last_error_code ) ) {
            return false;
        }

        return json_last_error_msg();
    }

    public function get_index() {
        
        $available = array(
            'name'           => get_option( 'blogname' ),
            'description'    => get_option( 'blogdescription' ),
            'url'            => get_option( 'siteurl' ),
            'namespaces'     => array_keys( $this->namespaces ),
            'authentication' => array(),
            'routes'         => $this->get_route_data( $this->get_routes() ),
        );

        $response = new WP_REST_Response( $available );
        $response->add_link( 'help', 'http://v2.wp-api.org/' );

        return apply_filters( 'vp_rest_index', $response );
    }

    public function get_namespace_index( $request ) {
        $namespace = $request['namespace'];

        if ( ! isset( $this->namespaces[ $namespace ] ) ) {
            return new WP_Error( 'vp_rest_invalid_namespace', __( 'The specified namespace could not be found.' ), array( 'status' => 404 ) );
        }

        $routes = $this->namespaces[ $namespace ];
        $endpoints = array_intersect_key( $this->get_routes(), $routes );

        $data = array(
            'namespace' => $namespace,
            'routes' => $this->get_route_data( $endpoints ),
        );
        $response = vp_rest_ensure_response( $data );

        $response->add_link( 'up', vp_rest_url( '/' ) );

        return apply_filters( 'vp_rest_namespace_index', $response, $request );
    }

    protected function get_route_data( $routes ) {
        $available = array();
        
        foreach ( $routes as $route => $callbacks ) {
            $data = array(
                'namespace' => '',
                'methods' => array(),
            );
            if ( isset( $this->route_options[ $route ] ) ) {
                $options = $this->route_options[ $route ];
                if ( isset( $options['namespace'] ) ) {
                    $data['namespace'] = $options['namespace'];
                }
            }

            $route = preg_replace( '#\(\?P<(\w+?)>.*?\)#', '{$1}', $route );

            foreach ( $callbacks as $callback ) {
                
                if ( empty( $callback['show_in_index'] ) ) {
                    continue;
                }

                $data['methods'] = array_merge( $data['methods'], array_keys( $callback['methods'] ) );

                if ( strpos( $route, '{' ) === false ) {
                    $data['_links'] = array(
                        'self' => vp_rest_url( $route ),
                    );
                }
            }

            if ( empty( $data['methods'] ) ) {
                
                continue;
            }

            $available[ $route ] = apply_filters( 'vp_rest_endpoints_description', $data );
        }

        return apply_filters( 'vp_rest_route_data', $available, $routes );
    }

    protected function set_status( $code ) {
        status_header( $code );
    }

    public function send_header( $key, $value ) {
        
        
        
        
        $value = preg_replace( '/\s+/', ' ', $value );
        header( sprintf( '%s: %s', $key, $value ) );
    }

    public function send_headers( $headers ) {
        foreach ( $headers as $key => $value ) {
            $this->send_header( $key, $value );
        }
    }

    public function get_raw_data() {
        global $HTTP_RAW_POST_DATA;

        if ( ! isset( $HTTP_RAW_POST_DATA ) ) {
            $HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
        }

        return $HTTP_RAW_POST_DATA;
    }

    public function prepare_response( $data ) {
        if ( ! defined( 'WP_REST_SERIALIZE_COMPATIBLE' ) || WP_REST_SERIALIZE_COMPATIBLE === false ) {
            return $data;
        }

        switch ( gettype( $data ) ) {
            case 'boolean':
            case 'integer':
            case 'double':
            case 'string':
            case 'NULL':
                
                return $data;

            case 'array':
                
                return array_map( array( $this, 'prepare_response' ), $data );

            case 'object':
                if ( $data instanceof JsonSerializable ) {
                    $data = $data->jsonSerialize();
                } else {
                    $data = get_object_vars( $data );
                }

                return $this->prepare_response( $data );

            default:
                return null;
        }
    }

    public function get_headers( $server ) {
        $headers = array();

        $additional = array( 'CONTENT_LENGTH' => true, 'CONTENT_MD5' => true, 'CONTENT_TYPE' => true );

        foreach ( $server as $key => $value ) {
            if ( strpos( $key, 'HTTP_' ) === 0 ) {
                $headers[ substr( $key, 5 ) ] = $value;
            } elseif ( isset( $additional[ $key ] ) ) {
                $headers[ $key ] = $value;
            }
        }

        return $headers;
    }
}
