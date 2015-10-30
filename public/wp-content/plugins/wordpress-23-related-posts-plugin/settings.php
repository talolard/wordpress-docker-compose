<?php

/**
* Place menu icons at admin head
**/
add_action('admin_head', 'wp_rp_admin_head');
function wp_rp_admin_head() {
	$menu_icon = plugins_url('static/img/menu_icon.png', __FILE__);
	$menu_icon_retina = plugins_url('static/img/menu_icon_2x.png', __FILE__);
	include wp_rp_get_template('admin_head');
}

/**
* Add settings link to installed plugins list
**/
function wp_rp_add_link_to_settings($links) {
	return array_merge( array(
		'<a href="' . admin_url('admin.php?page=wordpress-related-posts') . '">' . __('Settings', 'wp_related_posts') . '</a>',
	), $links);
}
add_filter('plugin_action_links_' . WP_RP_PLUGIN_FILE, 'wp_rp_add_link_to_settings', 10, 2);

function wp_rp_subscription($email_or_unsubscribe, $subscription_types) {
	$meta = wp_rp_get_meta();
	$options = wp_rp_get_options();

	if (! $subscription_types) {
		if ($email_or_unsubscribe) { return false; }
		$subscription_types = "activityreport,newsletter";
	}

	if (! $meta['subscribed'] && $meta['email'] && !$email_or_unsubscribe) {
		// Not processed yet
		$meta['email'] = false;
		$options['subscription_types'] = false;
		wp_rp_update_meta($meta);
		wp_rp_update_options($options);
		return true;
	}
	
	if($meta['zemanta_api_key']) {
		$post = array(
			'api_key' => $meta['zemanta_api_key'],
			'platform' => 'wordpress-wprp',
			'url' => get_site_url(),
			'subscriptions' => $subscription_types
		);

		if ($email_or_unsubscribe) {
			$post['email'] = $email_or_unsubscribe;
		}
		$response = wp_remote_post(WP_RP_ZEMANTA_SUBSCRIPTION_URL . 'subscribe/', array(
			'body' => $post,
			'timeout' => 30
		));
		if (wp_remote_retrieve_response_code($response) == 200) {
			$body = wp_remote_retrieve_body($response);
			if ($body) {
				$response_json = json_decode($body);
				
				if ($response_json->status !== 'ok') {
					$waiting = $response_json->reason == 'user-missing';
					if ($email_or_unsubscribe && $waiting) {
						$meta['email'] = $email_or_unsubscribe;
						$meta['subscribed'] = false;
						$options['subscription_types'] = $subscription_types;
						wp_rp_update_meta($meta);
						wp_rp_update_options($options);
						return true;
// We will try again when 
					}
					return false;
				}
				$meta['email'] = $email_or_unsubscribe;
				$meta['subscribed'] = (int) !!$email_or_unsubscribe;
				$options['subscription_types'] = $subscription_types;
				wp_rp_update_meta($meta);
				wp_rp_update_options($options);
				return true; // don't subscribe to bf if zem succeeds
			}
		}
	}
	return false;
}

function wp_rp_ajax_subscribe_callback () {
	check_ajax_referer('wp_rp_ajax_nonce');

	$email = (!empty($_POST['email']) && $_POST['email'] !== '0') ? $_POST['email'] : false;
	$types = empty($_POST['subscription']) ? array() : explode(",", $_POST['subscription']);
	$valid_types = array();
	foreach($types as $tp) {
		if ($tp && in_array($tp, array('activityreport', 'newsletter'))) {
			$valid_types[] = $tp;
		}
	}
	$valid_types = $valid_types ? implode(',', $valid_types) : false;
	if (wp_rp_subscription($email, $valid_types)) {
		print "1";
	}
	else {
		print "0";
	}
	die();
}

add_action('wp_ajax_wprp_subscribe', 'wp_rp_ajax_subscribe_callback');


/**
* Settings
**/

add_action('admin_menu', 'wp_rp_settings_admin_menu');

function wp_rp_settings_admin_menu() {
	if (!current_user_can('delete_users')) {
		return;
	}

	$title = __('Wordpress Related Posts', 'wp_related_posts');
	
	$page = add_options_page(__('Wordpress Related Posts', 'wp_related_posts'), $title, 
				'manage_options', 'wordpress-related-posts', 'wp_rp_settings_page');
	add_action('admin_print_scripts-' . $page, 'wp_rp_settings_scripts');
}

function wp_rp_settings_scripts() {
	wp_enqueue_script('wp_rp_themes_script', plugins_url('static/js/themes.js', __FILE__), array('jquery'), WP_RP_VERSION);
	wp_enqueue_script("wp_rp_dashboard_script", plugins_url('static/js/dashboard.js', __FILE__), array('jquery'), WP_RP_VERSION);
	wp_enqueue_script("wp_rp_extras_script", plugins_url('static/js/extras.js', __FILE__), array('jquery'), WP_RP_VERSION);
}
function wp_rp_settings_styles() {
	wp_enqueue_style("wp_rp_dashboard_style", plugins_url("static/css/dashboard.css", __FILE__), array(), WP_RP_VERSION);
}

function wp_rp_ajax_dismiss_notification_callback() {
	check_ajax_referer('wp_rp_ajax_nonce');

	if(isset($_REQUEST['id'])) {
		wp_rp_dismiss_notification((int)$_REQUEST['id']);
	}
	if(isset($_REQUEST['noredirect'])) {
		die('ok');
	}
	wp_redirect(admin_url('admin.php?page=wordpress-related-posts'));
}

add_action('wp_ajax_rp_dismiss_notification', 'wp_rp_ajax_dismiss_notification_callback');

function wp_rp_get_api_key() {
	$meta = wp_rp_get_meta();
	if($meta['zemanta_api_key']) return $meta['zemanta_api_key'];

	$zemanta_options = get_option('zemanta_options');
	if ($zemanta_options && !empty($zemanta_options['api_key'])) {
		$meta['zemanta_api_key'] = $zemanta_options['api_key'];
		wp_rp_update_meta($meta);
		return $meta['zemanta_api_key'];
	}
	return false;
}

function wp_rp_register() {
	$meta = wp_rp_get_meta();
	if ($meta['registered']) {
		return;
	}
	$api_key = wp_rp_get_api_key();
	if(! $api_key) {
		$wprp_zemanta = new WPRPZemanta();
		$wprp_zemanta->init(); // we have to do this manually because the admin_init hook was already triggered
		$wprp_zemanta->register_options();
		
		$api_key = $wprp_zemanta->api_key;
		$meta['zemanta_api_key'] = $api_key;
	}
	if (!$api_key) { return false; }

	$url = urlencode(get_bloginfo('wpurl'));
	$post = array(
		'api_key' => $api_key,
		'platform' => 'wordpress-wprp',
		'post_rid' => '',
		'post_url' => $url,
		'current_url' => $url,
		'format' => 'json',
		'method' => 'zemanta.post_published_ping'
	);
	$response = wp_remote_post(WP_RP_ZEMANTA_API_URL, array(
		'body' => $post,
		'timeout' => 30
	));
	if (wp_remote_retrieve_response_code($response) == 200) {
		$body = wp_remote_retrieve_body($response);
		if ($body) {
			$response_json = json_decode($body);
			$meta['registered'] = $response_json->status === 'ok';
		}
	}

	wp_rp_update_meta($meta);
	return $meta['registered'];
}



function wp_rp_settings_page() {
	if (!current_user_can('delete_users')) {
		die('Sorry, you don\'t have permissions to access this page.');
	}

	wp_rp_register();
	
	$options = wp_rp_get_options();
	$meta = wp_rp_get_meta();

	// Update already subscribed but in the old pipeline
	if (!empty($meta["email"]) && empty($meta["subscribed"])) {
		wp_rp_subscription($meta["email"], $options["subscription_types"]);
	}

	if ( isset( $_GET['wprp_global_notice'] ) && $_GET['wprp_global_notice'] === '0') {
		$meta['global_notice'] = null;
		wp_rp_update_meta($meta);
	}
	
	$postdata = stripslashes_deep($_POST);

	if(sizeof($_POST)) {
		if (!isset($_POST['_wp_rp_nonce']) || !wp_verify_nonce($_POST['_wp_rp_nonce'], 'wp_rp_settings') ) {
			die('Sorry, your nonce did not verify.');
		}

		$old_options = $options;
		$new_options = array(
			'on_single_post' => isset($postdata['wp_rp_on_single_post']),
			'max_related_posts' => (isset($postdata['wp_rp_max_related_posts']) && is_numeric(trim($postdata['wp_rp_max_related_posts']))) ? intval(trim($postdata['wp_rp_max_related_posts'])) : 5,
			'on_rss' => isset($postdata['wp_rp_on_rss']),
			'related_posts_title' => isset($postdata['wp_rp_related_posts_title']) ? trim($postdata['wp_rp_related_posts_title']) : '',
			'promoted_content_enabled' => isset($postdata['wp_rp_promoted_content_enabled']),
			'enable_themes' => isset($postdata['wp_rp_enable_themes']),
			'max_related_post_age_in_days' => (isset($postdata['wp_rp_max_related_post_age_in_days']) && is_numeric(trim($postdata['wp_rp_max_related_post_age_in_days']))) ? intval(trim($postdata['wp_rp_max_related_post_age_in_days'])) : 0,

			'custom_size_thumbnail_enabled' => isset($postdata['wp_rp_custom_size_thumbnail_enabled']) && $postdata['wp_rp_custom_size_thumbnail_enabled'] === 'yes',
			'custom_thumbnail_width' => isset($postdata['wp_rp_custom_thumbnail_width']) ? intval(trim($postdata['wp_rp_custom_thumbnail_width'])) : WP_RP_CUSTOM_THUMBNAILS_WIDTH ,
			'custom_thumbnail_height' => isset($postdata['wp_rp_custom_thumbnail_height']) ? intval(trim($postdata['wp_rp_custom_thumbnail_height'])) : WP_RP_CUSTOM_THUMBNAILS_HEIGHT,

			'thumbnail_use_custom' => isset($postdata['wp_rp_thumbnail_use_custom']) && $postdata['wp_rp_thumbnail_use_custom'] === 'yes',
			'thumbnail_custom_field' => isset($postdata['wp_rp_thumbnail_custom_field']) ? trim($postdata['wp_rp_thumbnail_custom_field']) : '',
			'display_zemanta_linky' => $meta['show_zemanta_linky_option'] ? isset($postdata['wp_rp_display_zemanta_linky']) : true,
			'only_admins_can_edit_related_posts' => !empty($postdata['wp_rp_only_admins_can_edit_related_posts']),

			'desktop' => array(
				'display_comment_count' => isset($postdata['wp_rp_desktop_display_comment_count']),
				'display_publish_date' => isset($postdata['wp_rp_desktop_display_publish_date']),
				'display_excerpt' => isset($postdata['wp_rp_desktop_display_excerpt']),
				'display_thumbnail' => isset($postdata['wp_rp_desktop_display_thumbnail']),
				'excerpt_max_length' => (isset($postdata['wp_rp_desktop_excerpt_max_length']) && is_numeric(trim($postdata['wp_rp_desktop_excerpt_max_length']))) ? intval(trim($postdata['wp_rp_desktop_excerpt_max_length'])) : 200,
				'custom_theme_enabled' => isset($postdata['wp_rp_desktop_custom_theme_enabled']),
			)
		);

		if(!isset($postdata['wp_rp_exclude_categories'])) {
			$new_options['exclude_categories'] = '';
		} else if(is_array($postdata['wp_rp_exclude_categories'])) {
			$new_options['exclude_categories'] = join(',', $postdata['wp_rp_exclude_categories']);
		} else {
			$new_options['exclude_categories'] = trim($postdata['wp_rp_exclude_categories']);
		}

		foreach(array('desktop') as $platform) {
			if(isset($postdata['wp_rp_' . $platform . '_theme_name'])) {		// If this isn't set, maybe the AJAX didn't load...
				$new_options[$platform]['theme_name'] = trim($postdata['wp_rp_' . $platform . '_theme_name']);				
			} else {
				$new_options[$platform]['theme_name'] = $old_options[$platform]['theme_name'];
			}
			if(isset($postdata['wp_rp_' . $platform . '_theme_custom_css'])) {
					$new_options[$platform]['theme_custom_css'] = $postdata['wp_rp_' . $platform . '_theme_custom_css'];
			} elseif (isset($postdata["wp_rp_${platform}_custom_theme_enabled"])) {
				$new_options[$platform]['theme_custom_css'] = '';
			} else {
				$new_options[$platform]['theme_custom_css'] =  $old_options[$platform]['theme_custom_css'];
			}
		}

		if (isset($postdata['wp_rp_classic_state'])) {
			$meta['classic_user'] = true;
		} else {
			$meta['classic_user'] = false;
		}
		wp_rp_update_meta($meta);

		if (isset($postdata['wp_rp_turn_on_button_pressed'])) {
			$meta['show_turn_on_button'] = false;
			$meta['turn_on_button_pressed'] = $postdata['wp_rp_turn_on_button_pressed'];
			$new_options['desktop']['display_thumbnail'] = true;
		}

		$preprocess_thumbnails = ($new_options['desktop']['display_thumbnail'] && !$old_options['desktop']['display_thumbnail']);


		$default_thumbnail_path = wp_rp_upload_default_thumbnail_file();

		if($default_thumbnail_path === false) { // no file uploaded
			if(isset($postdata['wp_rp_default_thumbnail_remove'])) {
				$new_options['default_thumbnail_path'] = false;
			} else {
				$new_options['default_thumbnail_path'] = $old_options['default_thumbnail_path'];
			}
		} else if(is_wp_error($default_thumbnail_path)) { // error while upload
			$new_options['default_thumbnail_path'] = $old_options['default_thumbnail_path'];
			wp_rp_add_admin_notice('error', $default_thumbnail_path->get_error_message());
		} else { // file successfully uploaded
			$new_options['default_thumbnail_path'] = $default_thumbnail_path;
		}

		if (((array) $old_options) != $new_options) {
			if(!wp_rp_update_options($new_options)) {
				wp_rp_add_admin_notice('error', __('Failed to save settings.', 'wp_related_posts'));
			} else {
				wp_rp_add_admin_notice('updated', __('Settings saved.', 'wp_related_posts'));
			}

			if($preprocess_thumbnails) {
				wp_rp_process_latest_post_thumbnails();
			}
		} else {
			// I should duplicate success message here
			wp_rp_add_admin_notice('updated', __('Settings saved.', 'wp_related_posts'));
		}
	}

	$input_hidden = array(
		'wp_rp_ajax_nonce' => wp_create_nonce("wp_rp_ajax_nonce"),
		'wp_rp_json_url' => esc_attr(WP_RP_CONTENT_BASE_URL . WP_RP_STATIC_JSON_PATH),
		'wp_rp_version' => esc_attr(WP_RP_VERSION),
		'wp_rp_dashboard_url' => esc_attr(WP_RP_CTR_DASHBOARD_URL),
		'wp_rp_static_base_url' => esc_attr(WP_RP_STATIC_BASE_URL),
		'wp_rp_plugin_static_base_url' => esc_attr(plugins_url("static/", __FILE__)),
	);

	$settings_file = __FILE__;

	$form_url = admin_url('admin.php?page=wordpress-related-posts');
	$form_display = 'block'; //($meta['show_turn_on_button'] && !$meta['turn_on_button_pressed'] ? 'none' : 'block');

	global $wpdb;
	$custom_fields = $wpdb->get_col( "SELECT meta_key FROM $wpdb->postmeta GROUP BY meta_key HAVING meta_key NOT LIKE '\_%' ORDER BY LOWER(meta_key)" );

	$exclude_categories = explode(',', $options['exclude_categories']);
	$categories = get_categories(array(
		'orderby' => 'name',
		'order' => 'ASC',
		'hide_empty' => false
	));

	$blog_url = get_site_url();
	
	include wp_rp_get_template('settings');
}
