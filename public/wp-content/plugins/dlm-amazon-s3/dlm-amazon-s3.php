<?php
/*
	Plugin Name: Download Monitor Amazon S3
	Plugin URI: https://www.download-monitor.com/extensions/amazon-s3/
	Description: Lets you link to files hosted on Amazon s3 so that you can serve secure, expiring download links.
	Version: 1.0.6
	Author: Mike Jolley
	Author URI: http://mikejolley.com
	Requires at least: 3.5
	Tested up to: 4.1

	Copyright: © 2013 Mike Jolley.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_DLM_Amazon_S3 class.
 */
class WP_DLM_Amazon_S3 {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hooks
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'admin_notices', array( $this, 'version_check' ) );
		add_filter( 'download_monitor_settings', array( $this, 'settings' ) );
		add_filter( 'dlm_downloadable_file_version_buttons', array( $this, 'add_button' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'dlm_downloading', array( $this, 'trigger' ), 10, 3 );
		add_filter( 'dlm_extensions', array( $this, 'register_extension' ) );
	}

	/**
	 * Register this extension
	 *
	 * @param array $extensions
	 *
	 * @return array $extensions
	 */
	public function register_extension( $extensions ) {
		$extensions[] = 'dlm-amazon-s3';
		return $extensions;
	}

	/**
	 * Localisation
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'dlm_amazon_s3', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Check version
	 */
	public function version_check() {
		if ( version_compare( DLM_VERSION, '1.3.1', '>=' ) ) {
			return;
		}
		?>
	    <div class="error">
	        <p><?php _e( 'Download Monitor Amazon S3 requires at least Download Monitor version 1.3.1 to function.', 'dlm_amazon_s3' ); ?></p>
	    </div>
	    <?php
	}

	/**
	 * Add settings
	 * @param  array  $settings
	 * @return array
	 */
	public function settings( $settings = array() ) {
		$settings['amazon_s3'] = array(
			__( 'Amazon s3', 'download_monitor' ),
			array(
				array(
					'name' 		=> 'dlm_amazon_s3_access_key',
					'std' 		=> '',
					'label' 	=> __( 'AWS Access Key ID', 'dlm_amazon_s3' ),
					'desc'		=> sprintf( __( 'Your public AWS Access Key ID. To find this, go to your <a href="%s">Security Credentials page</a>.', 'dlm_amazon_s3' ), 'https://console.aws.amazon.com/iam/home?#security_credential' )
				),
				array(
					'name' 		=> 'dlm_amazon_s3_secret_access_key',
					'std' 		=> '',
					'type'      => 'password',
					'label' 	=> __( 'AWS Secret Access Key', 'dlm_amazon_s3' ),
					'desc'		=> sprintf( __( 'Your secret AWS Access Key. To find this, go to your <a href="%s">Security Credentials page</a>.', 'dlm_amazon_s3' ), 'https://console.aws.amazon.com/iam/home?#security_credential' )
				),
			)
		);

		return $settings;
	}

	/**
	 * Add amazon button to admin
	 *
	 * @param array $buttons
	 *
	 * @return array $buttons
	 */
	public function add_button( $buttons = array() ) {
		$buttons['amazon_s3'] = array(
			'text' => __( 'Amazon s3 object', 'dlm_amazon_s3' )
		);

		return $buttons;
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_scripts() {
		global $pagenow, $post;

		// Enqueue Downloadable Files Metabox JS
		if ( ( $pagenow == 'post.php' && isset( $post ) && 'dlm_download' === $post->post_type ) || ( $pagenow == 'post-new.php' && isset( $_GET['post_type'] ) && 'dlm_download' == $_GET['post_type'] ) ) {

			// Enqueue Edit Download JS
			wp_enqueue_script(
				'dlm_amazon',
				plugins_url( '/assets/js/amazon.js', __FILE__ ),
				array( 'jquery' ),
				DLM_VERSION
			);

			// Make JavaScript strings translatable
			wp_localize_script( 'dlm_amazon', 'dlm_amazon_strings', array(
				'bucket_prompt' => __( 'Amazon s3 bucket name', 'dlm_amazon_s3' ),
				'object_prompt' => __( 'Amazon s3 object path', 'dlm_amazon_s3' )
			) );
		}


	}

	/**
	 * Download has been triggered
	 * @param  DLM_Download $download  DLM_Download_Object
	 * @param  string $version   Version
	 * @param  string $file_path File path being downloaded
	 */
	public function trigger( $download, $version, $file_path ) {
		if ( strstr( $file_path, 's3.amazonaws.com' ) ) {

			// Get keys
			$access_key        = get_option( 'dlm_amazon_s3_access_key' );
			$secret_access_key = get_option( 'dlm_amazon_s3_secret_access_key' );

			// Keys are needed
			if ( ! $access_key || ! $secret_access_key ) {
				return;
			}

			// Parse the URL
			$parsed_file_path = parse_url( $file_path );

			if ( strstr( $file_path, '://s3.amazonaws.com' ) ) {
				$path   = explode( '/', trim( $parsed_file_path['path'], '/' ) );
				$bucket = current( $path );
				$object = end( $path );
			} else {
				$bucket = untrailingslashit( current( explode( '.s3', $parsed_file_path['host'] ) ) );
				$object = trim( $parsed_file_path['path'], '/' );
			}

			// If there is a query, we don't want to touch the URL
			if ( ! empty( $parsed_file_path['query'] ) ) {
				return;
			}

  			// Generate signature
			$expires        = time() + ( 5 * 60 ) * 60;
			$string_to_sign = implode( "\n", array( 'GET', null, null, $expires, '/' . $bucket . '/' . $object ) );
			$signature      = base64_encode( hash_hmac( 'sha1', $string_to_sign, $secret_access_key, true ) );

  			// Ammend file path
  			$file_path = add_query_arg( array(
				'AWSAccessKeyId' => $access_key,
				'Expires'        => $expires
  			), $file_path ) . '&Signature=' . urlencode( $signature );

			// Logging
			$logging = new DLM_Logging();

			// Check if logging is enabled
			if( $logging->is_logging_enabled() ) {

				// Create log
				$logging->create_log( 'download', 'redirected', __( 'Redirected to Amazon S3', 'download_monitor' ), $download, $version );

			}

			// Trigger download
			header( 'Location: ' . $file_path );
			exit;
		}
	}
}

$GLOBALS['dlm_amazon_s3'] = new WP_DLM_Amazon_S3();