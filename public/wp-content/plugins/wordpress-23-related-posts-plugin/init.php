<?php
define('WP_RP_VERSION', '3.5.3');

define('WP_RP_PLUGIN_FILE', plugin_basename(__FILE__));

include_once(dirname(__FILE__) . '/config.php');
include_once(dirname(__FILE__) . '/lib/stemmer.php');
include_once(dirname(__FILE__) . '/lib/mobile_detect.php');

include_once(dirname(__FILE__) . '/admin_notices.php');
include_once(dirname(__FILE__) . '/widget.php');
include_once(dirname(__FILE__) . '/thumbnailer.php');
include_once(dirname(__FILE__) . '/settings.php');
include_once(dirname(__FILE__) . '/recommendations.php');
include_once(dirname(__FILE__) . '/edit_related_posts.php');
include_once(dirname(__FILE__) . '/compatibility.php');

register_activation_hook(__FILE__, 'wp_rp_activate_hook');
register_deactivation_hook(__FILE__, 'wp_rp_deactivate_hook');

add_action('wp_head', 'wp_rp_head_resources');

add_action('plugins_loaded', 'wp_rp_init_zemanta');


function wp_rp_init_zemanta() {
	include_once(dirname(__FILE__) . '/zemanta/zemanta.php');
	if (wp_rp_is_classic()) {
		$wprp_zemanta = new WPRPZemanta();
	}
}


function wp_rp_get_template($file) {
	return dirname(__FILE__) . "/views/$file.php";
}

function wp_rp_admin_style() {
	wp_enqueue_style('wp_rp_admin_style', plugins_url('static/css/dashboard.css', __FILE__));
}
add_action( 'admin_enqueue_scripts', 'wp_rp_admin_style');
  
function wp_rp_global_notice() {
	global $pagenow, $wp_rp_global_notice_pages;
	if (!current_user_can('delete_users')) {
		return;
	}
	
	$meta = wp_rp_get_meta();
	$close_url = add_query_arg( array(
		'page' => 'wordpress-related-posts',
		'wprp_global_notice' => 0,
	), admin_url( 'admin.php' ));
	$notice = $meta['global_notice'];
	if ($notice && in_array($pagenow, $wp_rp_global_notice_pages)) {
		include(wp_rp_get_template('global_notice'));
	}
}
add_action('all_admin_notices', 'wp_rp_global_notice' );
  
function wp_rp_extend_adminbar() {
	global $wp_admin_bar;

	if(!is_super_admin() || !is_admin_bar_showing())
		return;

	$wp_admin_bar->add_menu(array(
		'id' => 'wp_rp_adminbar_menu',
		'title' => __('Related Posts', 'wp_related_posts'),
		'href' => admin_url('admin.php?page=wordpress-related-posts&ref=adminbar')
	));
}

global $wp_rp_output;
$wp_rp_output = array();
function wp_rp_add_related_posts_hook($content) {
	global $wp_rp_output, $post;
	
	if( !is_object($post) ) {
		return $content;
	}
	
	$options = wp_rp_get_options();

	if ($content != "" && $post->post_type === 'post' && (($options["on_single_post"] && is_single()) || (is_feed() && $options["on_rss"]))) {
		if (!isset($wp_rp_output[$post->ID])) {
			$wp_rp_output[$post->ID] = wp_rp_get_related_posts();
		}
		$content = str_replace('%RELATEDPOSTS%', '', $content); // used for gp
		$content = $content . $wp_rp_output[$post->ID];
	}

	return $content;
}
add_filter('the_content', 'wp_rp_add_related_posts_hook', 10);

global $wp_rp_is_phone;
function wp_rp_is_phone() {
	global $wp_rp_is_phone;

	if (!isset($wp_rp_is_phone)) {
		$detect = new WpRpMobileDetect();
		$wp_rp_is_phone = $detect->isMobile() && !$detect->isTablet();
	}

	return $wp_rp_is_phone;
}

function wp_rp_get_platform_options() {
	$options = wp_rp_get_options();

	$thumb_options = array('custom_size_thumbnail_enabled' => false);

	if (!empty($options['custom_size_thumbnail_enabled'])) {
		$thumb_options['custom_size_thumbnail_enabled'] = $options['custom_size_thumbnail_enabled'];
		$thumb_options['custom_thumbnail_width'] = $options['custom_thumbnail_width'];
		$thumb_options['custom_thumbnail_height'] = $options['custom_thumbnail_height'];
	}
	return $options['desktop'] + $thumb_options;
}

function wp_rp_ajax_load_articles_callback() {
	global $post;

	$platform_options = wp_rp_get_platform_options();
    
	$getdata = stripslashes_deep($_GET);
	if (!isset($getdata['post_id'])) {
		die('error');
	}

	$post = get_post($getdata['post_id']);
	if(!$post) {
		die('error');
	}

	$from = (isset($getdata['from']) && is_numeric($getdata['from'])) ? intval($getdata['from']) : 0;
	$count = (isset($getdata['count']) && is_numeric($getdata['count'])) ? intval($getdata['count']) : 50;

	$search = isset($getdata['search']) && $getdata['search'] ? $getdata['search'] : false;

	$image_size = isset($getdata['size']) ? $getdata['size'] : 'thumbnail';
	if(!($image_size == 'thumbnail' || $image_size == 'full')) {
		die('error');
	}

	$limit = $count + $from;

	if ($search) {
		$the_query = new WP_Query(array(
			's' => $search,
			'post_type' => 'post',
			'post_status'=>'publish',
			'post_count' => $limit));
		$related_posts = $the_query->get_posts();
	} else {
		$related_posts = array();
		wp_rp_append_posts($related_posts, 'wp_rp_fetch_related_posts_v2', $limit);
		wp_rp_append_posts($related_posts, 'wp_rp_fetch_related_posts', $limit);
		wp_rp_append_posts($related_posts, 'wp_rp_fetch_random_posts', $limit);
	}

	if(function_exists('qtrans_postsFilter')) {
		$related_posts = qtrans_postsFilter($related_posts);
	}

	$response_list = array();

	foreach (array_slice($related_posts, $from) as $related_post) {
		$excerpt_max_length = $platform_options["excerpt_max_length"];
		
		$excerpt = $related_post->post_excerpt;
		if (!$excerpt) {
			$excerpt = strip_shortcodes(strip_tags($related_post->post_content));
		}
		if ($excerpt) {
			if (strlen($excerpt) > $excerpt_max_length) {
				$excerpt = wp_rp_text_shorten($excerpt, $excerpt_max_length);
			}
		}

		array_push($response_list, array(
				'id' => $related_post->ID,
				'url' => get_permalink($related_post->ID),
				'title' => $related_post->post_title,
				'excerpt' => $excerpt,
				'date' => $related_post->post_date,
				'comments' => $related_post->comment_count,
				'img' => wp_rp_get_post_thumbnail_img($related_post, $image_size)
			));
	}

	header('Content-Type: text/javascript');
	die(json_encode($response_list));
}
add_action('wp_ajax_wp_rp_load_articles', 'wp_rp_ajax_load_articles_callback');
add_action('wp_ajax_nopriv_wp_rp_load_articles', 'wp_rp_ajax_load_articles_callback');

function wp_rp_append_posts(&$related_posts, $fetch_function_name, $limit) {
	$options = wp_rp_get_options();

	$len = sizeof($related_posts);
	$num_missing_posts = $limit - $len;
	if ($num_missing_posts > 0) {
		$exclude_ids = array_map(create_function('$p', 'return $p->ID;'), $related_posts);

		$posts = call_user_func($fetch_function_name, $num_missing_posts, $exclude_ids);
		if ($posts) {
			$related_posts = array_merge($related_posts, $posts);
		}
	}
}

function wp_rp_fetch_posts_and_title() {
	$options = wp_rp_get_options();

	$limit = $options['max_related_posts'];

	// quirky stuff due to WPML compatibility
	$title_option = get_option('wp_rp_options', false);
	$title = __($title_option['related_posts_title'],'wp_related_posts');

	$related_posts = array();

	wp_rp_append_posts($related_posts, 'wp_rp_fetch_related_posts_v2', $limit);
	wp_rp_append_posts($related_posts, 'wp_rp_fetch_related_posts', $limit);
	wp_rp_append_posts($related_posts, 'wp_rp_fetch_random_posts', $limit);

	if(function_exists('qtrans_postsFilter')) {
		$related_posts = qtrans_postsFilter($related_posts);
	}

	return array(
		"posts" => $related_posts,
		"title" => $title
	);
}

function wp_rp_get_next_post(&$related_posts, &$selected_related_posts, &$inserted_urls, &$special_urls, $default_post_type) {
	$post = false;

	while (!($post && $post->ID) && !(empty($related_posts) && empty($selected_related_posts))) {
		$post_type = $default_post_type;

		$post = array_shift($selected_related_posts);

		if ($post && $post->type) {
			$post_type = $post->type;
		}

		if (!$post || !$post->ID) {
			while (!empty($related_posts) && (!($post = array_shift($related_posts)) || isset($special_urls[get_permalink($post->ID)])));
		}

		if ($post && $post->ID) {
			$post_url = property_exists($post, 'post_url') ? $post->post_url : get_permalink($post->ID);
			if (isset($inserted_urls[$post_url])) {
				$post = false;
			} else {
				$post->type = $post_type;
			}
		}
	}

	if (!$post || !$post->ID) {
		return false;
	}

	$inserted_urls[$post_url] = true;

	return $post;
}

function wp_rp_text_shorten($text, $max_chars) {
	$shortened_text = mb_substr($text, 0, $max_chars - strlen(WP_RP_EXCERPT_SHORTENED_SYMBOL));
	$shortened_words = explode(" ", $shortened_text);
	$shortened_size = count($shortened_words);
	if ($shortened_size > 1) {
		$shortened_words = array_slice($shortened_words, 0, $shortened_size - 1);
		$shortened_text = implode(" ", $shortened_words);
	}
	return $shortened_text . WP_RP_EXCERPT_SHORTENED_SYMBOL; //'...';
}
  
function wp_rp_generate_related_posts_list_items($related_posts, $selected_related_posts) {
	$options = wp_rp_get_options();
	$platform_options = wp_rp_get_platform_options();
	$output = "";

	$limit = $options['max_related_posts'];

	$inserted_urls = array(); // Used to prevent duplicates
	$special_urls = array();

	foreach ($selected_related_posts as $post) {
		if (property_exists($post, 'post_url') && $post->post_url) {
			$special_urls[$post->post_url] = true;
		}
	}

	$default_post_type = empty($selected_related_posts) ? 'none' : 'empty';

	$image_size = ($platform_options['theme_name'] == 'pinterest.css') ? 'full' : 'thumbnail';

	for ($i = 0; $i < $limit; $i++) {
		$related_post = wp_rp_get_next_post($related_posts, $selected_related_posts, $inserted_urls, $special_urls, $default_post_type);

		if (!$related_post) {
			break;
		}

		if (property_exists($related_post, 'type')) {
			$post_type = $related_post->type;
		} else {
			$post_type = $default_post_type;
		}

		if (in_array($post_type, array('empty', 'none'))) {
			$post_id = 'in-' . $related_post->ID;
		} else {
			$post_id = 'ex-' . $related_post->ID;
		}

		$data_attrs = 'data-position="' . $i . '" data-poid="' . $post_id . '" data-post-type="' . $post_type . '" ';

		$output .= '<li ' . $data_attrs . '>';

		$post_url = property_exists($related_post, 'post_url') ? $related_post->post_url : get_permalink($related_post->ID);

		$img = wp_rp_get_post_thumbnail_img($related_post, $image_size);
		if ($img) {
			$output .=  '<a href="' . $post_url . '" class="wp_rp_thumbnail">' . $img . '</a>';
		}

		if ($platform_options["display_publish_date"]) {
			$dateformat = get_option('date_format');
			$output .= '<small class="wp_rp_publish_date">' . mysql2date($dateformat, $related_post->post_date) . '</small> ';
			//$output .= mysql2date($dateformat, $related_post->post_date) . " -- ";
		}

		$output .= '<a href="' . $post_url . '" class="wp_rp_title">' . wptexturize($related_post->post_title) . '</a>';

		if ($platform_options["display_comment_count"] && property_exists($related_post, 'comment_count')){
			$output .=  '<small class="wp_rp_comments_count"> (' . $related_post->comment_count . ')</small><br />';
		}

		if ($platform_options["display_excerpt"]){
			$excerpt_max_length = $platform_options["excerpt_max_length"];
			$excerpt = '';

			if ($related_post->post_excerpt){
				$excerpt = strip_shortcodes(strip_tags($related_post->post_excerpt));
			}
			if (!$excerpt) {
				$excerpt = strip_shortcodes(strip_tags($related_post->post_content));
			}

			if ($excerpt) {
				if (strlen($excerpt) > $excerpt_max_length) {
					$excerpt = wp_rp_text_shorten($excerpt, $excerpt_max_length);
				}
				$output .= ' <small class="wp_rp_excerpt">' . $excerpt . '</small>';
			}
		}
		$output .=  '</li>';
	}

	return $output;
}

function wp_rp_should_exclude() {
	global $wpdb, $post;

	if (!$post || !$post->ID) {
		return true;
	}

	$options = wp_rp_get_options();

	if(!$options['exclude_categories']) { return false; }

	$q = 'SELECT COUNT(tt.term_id) FROM '. $wpdb->term_taxonomy.' tt, ' . $wpdb->term_relationships.' tr WHERE tt.taxonomy = \'category\' AND tt.term_taxonomy_id = tr.term_taxonomy_id AND tr.object_id = '. $post->ID . ' AND tt.term_id IN (' . $options['exclude_categories'] . ')';

	$result = $wpdb->get_col($q);

	$count = (int) $result[0];

	return $count > 0;
}

function wp_rp_head_resources() {
	global $post, $wpdb;

	//error_log("call to wp_rp_head_resources");

	if (wp_rp_should_exclude()) {
		return;
	}

	$meta = wp_rp_get_meta();
	$options = wp_rp_get_options();
	$platform_options = wp_rp_get_platform_options();
	//error_log('theme name 1: ' . $platform_options['theme_name']);
	$output = '';

	$output_vars = "\twindow._wp_rp_static_base_url = '" . esc_js(WP_RP_STATIC_BASE_URL) . "';\n" .
		"\twindow._wp_rp_wp_ajax_url = \"" . admin_url('admin-ajax.php') . "\";\n" .
		"\twindow._wp_rp_plugin_version = '" . WP_RP_VERSION . "';\n" .
		"\twindow._wp_rp_post_id = '" . esc_js($post->ID) . "';\n" .
		"\twindow._wp_rp_num_rel_posts = '" . $options['max_related_posts'] . "';\n";


	$tags = $wpdb->get_col("SELECT DISTINCT(label) FROM " . $wpdb->prefix . "wp_rp_tags WHERE post_id=$post->ID ORDER BY weight desc;", 0);
	if (!empty($tags)) {
		$post_tags = '[' . implode(', ', array_map(create_function('$v', 'return "\'" . urlencode(substr($v, strpos($v, \'_\') + 1)) . "\'";'), $tags)) . ']';
	} else {
		$post_tags = '[]';
	}

	$output_vars .= "\twindow._wp_rp_thumbnails = " . ($platform_options['display_thumbnail'] ? 'true' : 'false') . ";\n" .
		"\twindow._wp_rp_post_title = '" . urlencode($post->post_title) . "';\n" .
		"\twindow._wp_rp_post_tags = {$post_tags};\n" .
		"\twindow._wp_rp_promoted_content = " . ($options['promoted_content_enabled'] ? 'true' : 'false') . ";\n" .
		(current_user_can('edit_posts') ?
			"\twindow._wp_rp_admin_ajax_url = '" . admin_url('admin-ajax.php') . "';\n" .
			"\twindow._wp_rp_plugin_static_base_url = '" . esc_js(plugins_url('static/' , __FILE__)) . "';\n" .
			"\twindow._wp_rp_ajax_nonce = '" . wp_create_nonce("wp_rp_ajax_nonce") . "';\n" .
			"\twindow._wp_rp_erp_search = true;\n"
		: '');

	$output .= "<script type=\"text/javascript\">\n" . $output_vars . "</script>\n";

	$output .= '<script type="text/javascript" src="' . WP_RP_STATIC_BASE_URL . WP_RP_STATIC_LOADER_FILE . '?version=' . WP_RP_VERSION . '" async></script>' . "\n";

	$static_url = plugins_url('static/', __FILE__);
	$theme_url = plugins_url(WP_RP_STATIC_THEMES_PATH, __FILE__);
	
	if ($options['enable_themes']) {
		if ($platform_options['theme_name'] !== 'plain.css' && $platform_options['theme_name'] !== 'm-plain.css') {
			$output .= '<link rel="stylesheet" href="' . $theme_url . $platform_options['theme_name'] . '?version=' . WP_RP_VERSION . '" />' . "\n";
		}

		if ($platform_options['theme_name'] === 'm-stream.css') {
			wp_enqueue_script('wp_rp_infiniterecs', $static_url . WP_RP_STATIC_INFINITE_RECS_JS_FILE, array('jquery'), WP_RP_VERSION);
		}

		if ($platform_options['theme_name'] === 'pinterest.css') {
			wp_enqueue_script('wp_rp_pinterest', $static_url . WP_RP_STATIC_PINTEREST_JS_FILE, array('jquery'), WP_RP_VERSION);
		}
	}

	if ($platform_options['custom_theme_enabled']) {
		$output .= '<style type="text/css">' . "\n" . $platform_options['theme_custom_css'] . "</style>\n";
	}

	if (current_user_can('edit_posts')) {
		wp_enqueue_style('wp_rp_edit_related_posts_css', $theme_url . 'edit_related_posts.css', array(), WP_RP_VERSION);
		wp_enqueue_script('wp_rp_edit_related_posts_js', $static_url . 'js/edit_related_posts.js', array('jquery'), WP_RP_VERSION);
	}

	echo $output;
}

function wp_rp_get_selected_posts() {
	global $post;

	$selected_related_posts = get_post_meta($post->ID, '_wp_rp_selected_related_posts');
	if (empty($selected_related_posts)) {
		return array();
	}

	$selected_related_posts = $selected_related_posts[0];
	if (empty($selected_related_posts)) {
		return array();
	}

	$options = wp_rp_get_options();
	$limit = $options['max_related_posts'];

	return array_slice((array)$selected_related_posts, 0, $limit);
}

global $wp_rp_is_first_widget;
$wp_rp_is_first_widget = true;
function wp_rp_get_related_posts($before_title = '', $after_title = '') {
	if (wp_rp_should_exclude()) {
		return;
	}

	global $post, $wp_rp_is_first_widget;
	global $wp_rp_test_group; // used for AB testing on mobile

	$options = wp_rp_get_options();
	$platform_options = wp_rp_get_platform_options();
	$meta = wp_rp_get_meta();

	$posts_and_title = wp_rp_fetch_posts_and_title();
	$related_posts = $posts_and_title['posts'];
	$title = $posts_and_title['title'];

	$selected_related_posts = wp_rp_get_selected_posts();

	$related_posts_content = "";

	if (!$related_posts) {
		return;
	}

	$posts_footer = '';
	if (current_user_can($options['only_admins_can_edit_related_posts'] ? 'manage_options' : 'edit_posts')) {
		$posts_footer .= '<div class="wp_rp_footer"><a class="wp_rp_edit" href="#" id="wp_rp_edit_related_posts">Edit Related Posts</a></div>';
	}
	if ($options['display_zemanta_linky']) {
		$posts_footer .= '<div class="wp_rp_footer"><a class="wp_rp_backlink" target="_blank" href="http://www.zemanta.com/?wp-related-posts" rel="nofollow">Zemanta</a></div>';
	}

	$css_classes = 'related_post wp_rp';
	$css_classes_wrap = ' ' . str_replace(array('.css', '-'), array('', '_'), esc_attr('wp_rp_' . $platform_options['theme_name']));

	$related_posts_lis = wp_rp_generate_related_posts_list_items($related_posts, $selected_related_posts);
	$related_posts_ul = '<ul class="' . $css_classes . '">' . $related_posts_lis . '</ul>';

	$related_posts_title = $title ? ($before_title ? $before_title . $title . $after_title : '<h3 class="related_post_title">' . $title . '</h3>') : '';

	$first_id_attr = '';
	if($wp_rp_is_first_widget) {
		$wp_rp_is_first_widget = false;
		$first_id_attr = 'id="wp_rp_first"';
	}
	
	$wrap_style = '';
	//error_log('test group when content:'  . $wp_rp_test_group);
	if ($wp_rp_test_group == 2) {
		$wrap_style = ' style="display:none;"';
	}
	
	$output = '<div class="wp_rp_wrap ' . $css_classes_wrap . '" ' . $first_id_attr . $wrap_style . '>' .
			'<div class="wp_rp_content">' .
				$related_posts_title .
				$related_posts_ul .
				$posts_footer .
			'</div>' .
		'</div>';

	return "\n" . $output . "\n";
}
