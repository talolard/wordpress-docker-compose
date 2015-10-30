<?php

function wp_rp_update_related_posts_callback() {
	check_ajax_referer('wp_rp_ajax_nonce');
	if (!current_user_can('edit_posts')) {
		die('error');
	}

	$options = wp_rp_get_options();

	if (!isset($_POST['related_posts']) || !isset($_POST['post_id'])) {
		die('error');
	}

	global $wpdb;

	$post_id = intval(stripslashes($_POST['post_id']));

	$articles_json = stripslashes($_POST['related_posts']);
	if ($articles_json) {
		$articles = json_decode($articles_json);
	} else {
		$articles = '';
	}

	update_post_meta($post_id, '_wp_rp_selected_related_posts', $articles);
	delete_post_meta($post_id, '_wp_rp_related_posts_query_result_cache_'.$options['max_related_posts']); // Delete existing caches upon update, so that the new recs get calculated
	die('ok');
}
add_action('wp_ajax_rp_update_related_posts', 'wp_rp_update_related_posts_callback');
