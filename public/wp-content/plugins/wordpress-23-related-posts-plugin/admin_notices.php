<?php

add_action('wp_rp_admin_notices', 'wp_rp_display_admin_notices');

function wp_rp_display_admin_notices() {
	global $wp_rp_admin_notices;

	foreach ((array) $wp_rp_admin_notices as $notice) {
		echo '<div id="message" class="' . $notice[0] . ' below-h2"><p>' . $notice[1] . '</p></div>';
	}
}

function wp_rp_add_admin_notice($type = 'updated', $message = '') {
	global $wp_rp_admin_notices;
	
	if (strtolower($type) == 'updated' && $message != '') {
		$wp_rp_admin_notices[] = array('updated', $message);
		return true;
	}
	
	if (strtolower($type) == 'error' && $message != '') {
		$wp_rp_admin_notices[] = array ('error', $message);
		return true;
	}
	
	return false;
}
