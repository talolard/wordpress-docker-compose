<?php
/*
Plugin Name: WordPress Related Posts
Version: 3.5.3
Plugin URI: http://wordpress.org/extend/plugins/wordpress-23-related-posts-plugin/
Description: Quickly increase your readers' engagement with your posts by adding Related Posts in the footer of your content. Click on <a href="admin.php?page=wordpress-related-posts">Related Posts tab</a> to configure your settings.
Author: Zemanta Ltd.
Author URI: http://www.zemanta.com
*/

if (! function_exists('wp_rp_init_zemanta')) {
	function wp_rp_init_error() {
		?>
		<div class="updated">
        <p><?php _e('Wordpress Related Posts couldn\'t initialize.'); ?></p>
		</div>
		<?php
	}
	
	try {
		include_once(dirname(__FILE__) . '/init.php');
	}
	catch (Exception $e) {
		add_action( 'admin_notices', 'wp_rp_init_error' );
	}
}
else {
	function wp_rp_multiple_plugins_notice() {
		?>
		<div class="updated">
        <p><?php _e( 'Oh, it\'s OK, looks like you\'ve already got one related posts plugin installed, so no need for another one.', 'wp_wp_related_posts' ); ?></p>
		</div>
		<?php
	}
	add_action( 'admin_notices', 'wp_rp_multiple_plugins_notice' );
}

