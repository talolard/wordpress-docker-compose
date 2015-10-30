<?php

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

/**
 * Deactivates the plugin
 *
 * @return bool
 */
function dlm_mailchimp_lock_deactivate_self() {

	if( ! current_user_can( 'activate_plugins' ) ) {
		return false;
	}

	$dir = basename( dirname( __FILE__ ) );

	// deactivate self
	deactivate_plugins( "$dir/dlm-mailchimp-lock.php" );

	// get rid of "Plugin activated" notice
	if( isset( $_GET['activate'] ) ) {
		unset( $_GET['activate'] );
	}

	// show notice to user
	add_action( 'admin_notices', 'dlm_mailchimp_lock_php_requirement_notice' );

	return true;
}

/**
 * Outputs a notice telling the user that the plugin deactivated itself
 */
function dlm_mailchimp_lock_php_requirement_notice() {

	// load translations
	$dir = basename( dirname( __FILE__ ) );
	load_plugin_textdomain( 'dlm-mailchimp-lock', false, "$dir/languages" );

	?>
	<div class="updated">
		<p><?php _e( 'Download Monitor - MailChimp Lock did not activate because it requires your server to run PHP 5.3 or higher.', 'dlm-mailchimp-lock' ); ?></p>
	</div>
	<?php
}

// Hook into `admin_init`
add_action( 'admin_init', 'dlm_mailchimp_lock_deactivate_self' );
