<?php

namespace VersionPress\Api\BundledWpApi;

use WP_Error;

class WP_REST_Response extends WP_HTTP_Response {
    

    protected $links = array();

    protected $matched_route = '';

    protected $matched_handler = null;

    public function add_link( $rel, $href, $attributes = array() ) {
        if ( empty( $this->links[ $rel ] ) ) {
            $this->links[ $rel ] = array();
        }

        if ( isset( $attributes['href'] ) ) {
            
            unset( $attributes['href'] );
        }

        $this->links[ $rel ][] = array(
            'href'       => $href,
            'attributes' => $attributes,
        );
    }

    public function add_links( $links ) {
        foreach ( $links as $rel => $set ) {
            
            if ( isset( $set['href'] ) ) {
                $set = array( $set );
            }

            foreach ( $set as $attributes ) {
                $this->add_link( $rel, $attributes['href'], $attributes );
            }
        }
    }

    public function get_links() {
        return $this->links;
    }

    public function link_header( $rel, $link, $other = array() ) {
        $header = '<' . $link . '>; rel="' . $rel . '"';

        foreach ( $other as $key => $value ) {
            if ( 'title' === $key ) {
                $value = '"' . $value . '"';
            }
            $header .= '; ' . $key . '=' . $value;
        }
        return $this->header( 'Link', $header, false );
    }

    public function get_matched_route() {
        return $this->matched_route;
    }

    public function set_matched_route( $route ) {
        $this->matched_route = $route;
    }

    public function get_matched_handler() {
        return $this->matched_handler;
    }

    public function set_matched_handler( $handler ) {
        $this->matched_handler = $handler;
    }

    public function is_error() {
        return $this->get_status() >= 400;
    }

    public function as_error() {
        if ( ! $this->is_error() ) {
            return null;
        }

        $error = new WP_Error;

        if ( is_array( $this->get_data() ) ) {
            foreach ( $this->get_data() as $err ) {
                $error->add( $err['code'], $err['message'], $err['data'] );
            }
        } else {
            $error->add( $this->get_status(), '', array( 'status' => $this->get_status() ) );
        }

        return $error;
    }
}
