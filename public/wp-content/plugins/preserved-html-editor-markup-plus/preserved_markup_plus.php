<?php

/*
Plugin Name: Preserved HTML Editor Markup Plus
Plugin URI: http://www.marcuspope.com/wordpress/
Description: A Wordpress Plugin that preserves HTML markup in the TinyMCE editor, especially when switching between
html and visual tabs.  Also adds support for HTML5 Block Anchors.
Author: Marcus E. Pope, marcuspope, Jason Rosenbaum, J-Ro
Author URI: http://www.marcuspope.com
Version: 1.5.1

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

add_action('plugins_loaded', array('MP_WP_Preserved_Markup', 'init'), 1);

register_activation_hook( __FILE__, array('MP_WP_Preserved_Markup', 'set_activation_time'));

if (is_admin()) {
    add_action('wp_ajax_emc2pm_fix_posts', array('MP_WP_Preserved_Markup', 'fix_database_content'));
}

class MP_WP_Preserved_Markup {

    private static $valid_types = array();
    
    public static function remove_evil() {
        //remove evil: wpautop will break html5 markup!
        remove_filter('the_content', 'wpautop');
        
        //massage evil-ish wp-texturize
        if (has_filter('the_content', 'wptexturize')) {
            remove_filter('the_content', 'wptexturize');
            add_filter('the_content', array('MP_WP_Preserved_Markup', 'better_wptexturize'));
        }
    }
    
    public static function better_wptexturize($s) {
        //this is probably the first of many tweaks we'll fix in texturize,
        //but for now it's just targeting html comment tags
        
        //replace all closing html tags with temp placeholders so texturize doesn't turn them into endash's
        $s = preg_replace("/-->/m", "-mep-temp-closing-comment-tag->", $s);
        
        //now let wptexturize do its work
        //NOTICE: this function is highly volatile - it will alter intra-tag strings which is a terrible approach
        $s = wptexturize($s);
        
        //and restore original values in our temp placeholders
        $s = preg_replace("/-mep-temp-closing-comment-tag->/m", "-->", $s);
        
        return $s;
    }

    public static function init_tiny_mce($init) {
        
        $post_type = self::get_cur_post_type();
        $insert_p = 'br';
        
        if (!empty($post_type)) {
            //check post type page width specified by user setting
            $options = get_option('emc2_editor_insert_p');
            //default to 'br' for backwards compatibility/forwards consistency
            $insert_p = isset($options[$post_type]) ? $options[$post_type] : "br";
        }
        
        //Setup tinymce editor with necessary settings for better general editing
        $tags = "pre[*],iframe[*],object[*],param[*]"; //add pre and iframe to allowable tags for
        if (isset($init['extended_valid_elements'])) {
            $tags = $init['extended_valid_elements'] . "," . $tags;
        }
        $init['extended_valid_elements'] = $tags;
        $init['forced_root_block'] = false; //prevent tinymce from wrapping a root block level element (typically a <p> tag)
        $init['force_p_newlines'] = false;
        $init['remove_linebreaks'] = false;
        $init['force_br_newlines'] = false;
        
        if ($insert_p == "br") {
            //default behavior for this plugin
        }
        else if ($insert_p == "p") {
            $init['force_p_newlines'] = true;
        }
        else if ($insert_p == "both") {
            //insert p tag after two consecutive new lines
            $init['force_hybrid_newlines'] = true;
        }
        
        $init['remove_trailing_nbsp'] = false;
        $init['relative_urls'] = true;
        $init['convert_urls'] = false;
        $init['remove_linebreaks'] = false;
        $init['doctype'] = '<!DOCTYPE html>';
        $init['apply_source_formatting'] = false;
        $init['convert_newlines_to_brs'] = false;
        $init['fix_list_elements'] = false;
        $init['fix_table_elements'] = false;
        $init['verify_html'] = false;
        $init['setup'] = 'emc2_tinymce_init';
        $init['allow_script_urls'] = true;

        /*
           Allow for html5 anchor tags
           http://dev.w3.org/html5/markup/a.html
           http://dev.w3.org/html5/markup/common-models.html#common.elem.phrasing
           http://www.tinymce.com/wiki.php/Configuration:valid_children
        */
        $init['valid_children'] = "+a[em|strong|small|mark|abbr|dfn|i|b|s|u|code|var|samp|kbd|sup|sub|q|cite|span|bdo|bdi|br|wbr|ins|del|img|embed|object|iframe|map|area|noscript|ruby|video|audio|input|textarea|select|button|label|output|datalist|keygen|progress|command|canvas|time|meter|p|hr|pre|ul|ol|dl|div|h1|h2|h3|h4|h5|h6|hgroup|address|blockquote|section|nav|article|aside|header|footer|figure|table|f|m|fieldset|menu|details|style|link],+body[style|link]";

        return $init;
    }

    public static function fix_editor_content($html) {
        //this filter is added dynamically by 'the_editor' event, but it will wreck our hidden markup 
        remove_filter('the_editor_content', "wp_richedit_pre");
        return $html;
    }

    public static function content_replace_callback($a) {
        $s = $a[0];
        $s = preg_replace("/(\r\n|\n)/m", '<mep-preserve-nl>', $s);
        $s = preg_replace("/\t/m", '<mep-preserve-tab>', $s);
        $s = preg_replace("/\s/m", '<mep-preserve-space>', $s);
        return $s;
    }
    
    public static function comment_hack_callback($a) {
        return "<code style='display: none;'>" . $a[0] . "</code>";
    }
    
    public static function comment_unhack_callback($a) {
        $s = $a[0];
        $start = strpos($s, '<!--');
        $stop = strrpos($s, '-->') + 3;
        $s = substr($s, $start, $start - $stop);
        return $s;
    }

    public static function fix_wysiwyg_content($c) {
        //If the page is rendered with the WYSIWYG editor selected by default, content is processed in PHP land
        //instead of using the JS land "equivalent" logic (I quote equivalent because there are sooooo many
        //discrepancies between what JS wpautop and PHP wpautop functions do it's laughable.)
        if (wp_default_editor() == "tinymce") {
            
            //Our whitespace preservation logic breaks existing multiline html comments.
            //By wrapping them in hidden code blocks, we can preserve whitespace and hide the rendered content
            $c = preg_replace_callback(
                '/<!--[\s\S]*-->/m',
                array(
                    'MP_WP_Preserved_Markup',
                    'comment_hack_callback'
                ),
                $c);
            
            //First we inject temporary whitespace markers in pre and code elements because they won't
            //be corrupted when the user switches to html mode.*   (actually IE9 will remove the first
            //newline from a pre tag if there are no non-whitespace characters before the newline.)
            $c = preg_replace_callback(
                '/<(pre|code)[^>]*>[\s\S]+?<\/\\1>/m',
                array(
                    'MP_WP_Preserved_Markup',
                    'content_replace_callback'
                ),
                $c);

            //Now let's preserve whitespace with html comments so that they can be converted back when switching to
            //the html mode.  FIXME: assuming four spaces is bad mmkay, what if I like only two spaces for a tab?
            //and this could produce bad markup if a user had <p    class="test">hello</p> in their markup.  So
            //work on a more flexible /\s/g approach when \s is inside or outside a tag definition
            $c = preg_replace("/(\r\n|\n)/", "<!--mep-nl-->", $c); //preserve new lines
            $c = preg_replace("/(\t|\s\s\s\s)/", "<!--mep-tab-->", $c); //preserve indents

            //Now we can restore all whitespace originally escaped in pre & code tags
            $c = preg_replace("/<mep-preserve-nl>/m", "\n", $c);
            $c = preg_replace("/<mep-preserve-tab>/m", "\t", $c);
            $c = preg_replace("/<mep-preserve-space>/m", " ", $c);

            //finish up with functions that WP normally calls on the_editor_content
            if (has_filter('the_content', 'convert_chars')) {
                $c = convert_chars($c);
            }
            $c = htmlspecialchars($c, ENT_NOQUOTES);
            $c = apply_filters('richedit_pre', $c);
        }

        return $c;
    }

    public static function fix_post_content($post) {
        //If the user clicks save while in the Visual (WYSIWYG) tab, we'll need to strip the whitespace placeholders
        //before inserting the data into the database to prevent duplication of whitespace
        
        //INFO: This should not be necessary because when the user clicks save from the Visual Tab the content is passed
        //through the afterPreWpautop javascript event which we already use to handle tab switching.  I think my previous
        //issue was caused by a js error in that function that resulted in nothing being stripped out before it was
        //posted to the server here:
        if (isset($post['post_content'])) {
            $post['post_content'] = preg_replace('/<\!--mep-nl-->/m', "\r\n", $post['post_content']);
            $post['post_content'] = preg_replace('/<\!--mep-tab-->/m', "    ", $post['post_content']);
            $post['post_content'] = preg_replace_callback(
                '/<code style=[\'"]display: none;[\'"]><!--[\s\S]*?--><\/code>/m',
                array(
                    'MP_WP_Preserved_Markup',
                    'comment_unhack_callback'
                ),
                $post['post_content']
            );
        }
        return $post;
    }
    
    public static function set_activation_time() {
        update_option('emc2pm_activate_date', time());
    }
    
    public static function fix_database_content() {
        /*
            Iterate over every post in the database by specified post_type and
            update the content with wpautop.  This is essentially what WordPress
            did each time it rendered content from the database.  Since we're
            leaving the content alone from now on, this gives the user the ability
            to fix previously created content.
        */
        global $wpdb;

        //verify nonce and validate post type value
        if (wp_verify_nonce(@$_GET['nonce'], "emc2pm_fix_content") &&
            post_type_exists(@$_GET['post_type'])) {
            
            //get everything, revisions and all. I hesitated on this, but thought if a week later a user reverts a
            //post to a previous revision, they shouldn't have to re-fix the post by hand. And since the modified date
            //would be after the plugin activation date, the 'Fix XXX' feature wouldn't automatically fix it.
            $posts = $wpdb->get_results($wpdb->prepare("
                SELECT ID, post_content, post_title, post_modified FROM $wpdb->posts
                WHERE  post_type = %s", $_GET['post_type']));
            
            $errors = array();
            
            $limit = get_option('emc2pm_activate_date');
            
            foreach ($posts as $post) {
                //Don't clobber whitespace in any content created after the plugin was activated.
                //ISSUE: if the user activated the plugin, then modified content, then disabled the plugin and re-enabled it
                //we would accidently unformat some whitespace... meh, there are worse problems in the world.
                $modified = strtotime($post->post_modified);
                if (strlen($modified) != 0 && //don't think this is possible, but just in case
                    $modified > $limit) {
                    continue;
                }
                    
                $new_content = wpautop($post->post_content);
                
                if ($new_content != "") {
                    
                    $ob = ob_start(); //capture html db errors for cleaner alert
                    $wpdb->query( $wpdb->prepare("
                        UPDATE $wpdb->posts SET post_content = %s
                        WHERE ID = %d
                    ", $new_content, $post->ID) );
                    $res = ob_get_clean();
                    
                    if (strstr($res, "error")) {
                        $errors[] = $post->post_title;
                    }
                }
            }
        
            //present the processing results to user
            $res = "Content Fixed";
            if (count($errors)) {
                $res .= "\n\nBut the following posts could not be updated:\n";
                $res .= join("\n", $errors);
            }
                
            die($res);
        }
        else {
            die("ERROR: Access Denied");
        }
    }

    public static function admin_init() {
        //Add full attribute support for special tags
        add_filter( 'tiny_mce_before_init', array(
            'MP_WP_Preserved_Markup',
            'init_tiny_mce'
        ));

        /* fix WP html editor on client side */
        //TODO: __FILE__ does not work with symlinks (https://bugs.php.net/bug.php?id=46260).
        //      Create a filter for overriding so I don't have to copy this plugin from
        //      the svn repo to my own hg/git repos
        $plugin_data = get_plugin_data(__FILE__);
        $cachebuster = $plugin_data['Version'];
        
        wp_enqueue_script('emc2-pm-admin-js', WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__))."admin.js?v=".$cachebuster);
        //wp_enqueue_script('emc2-pm-admin-js', WP_PLUGIN_URL.'/sb_preserved_markup/admin.js');
        
        //provide nonce for ajax calls
        wp_localize_script('emc2-pm-admin-js', 'emc2pm', array(
            'fix_content_nonce' => wp_create_nonce('emc2pm_fix_content')
        ));        

        add_filter('the_editor', array(
            'MP_WP_Preserved_Markup',
            'fix_editor_content'
        ), 1);

        add_filter('the_editor_content', array(
            'MP_WP_Preserved_Markup',
            'fix_wysiwyg_content'
        ), 1);

        add_filter('wp_insert_post_data', array(
            'MP_WP_Preserved_Markup',
            'fix_post_content'
        ), 1);
        
        self::admin_settings_init();
    }

    public static function init() {
        add_action('init', array(
            'MP_WP_Preserved_Markup',
            'remove_evil'
        ));

        add_action('admin_init', array(
            'MP_WP_Preserved_Markup',
            'admin_init'
        ));
    }
    
    /*
     * The following set of functions mostly apply to the settings page under Admin > Settings > Writing
     */
    static function get_cur_post_type() {
        global $pagenow;
        
        //Get type of post we're currently editing (are even editing something?)
        $post_id = (int) @$_GET['post'];
       
        //Yep, consistency would be nice here, but oh well
        if (!empty($post_id)) $post_type = get_post_type($post_id);
        if (empty($post_type)) $post_type = sanitize_key(@$_GET['post_type']);
        if (empty($post_type)) $post_type = $pagenow == "post-new.php" ? "post" : "";
        
        return $post_type;
    }
    
    static function admin_settings_init() {
        global $pagenow;
        
        //Unlike the wp.org code sample, we'll only waste resources when necessary
        if ($pagenow == 'options-writing.php' || //register when viewing the writing options page
            $pagenow == 'options.php') { //and when clicking save (required or it won't know to save the settings)

            register_setting('writing', 'emc2_editor_insert_p', array('MP_WP_Preserved_Markup', 'validate_settings'));
            
            //give this setting its own section
            add_settings_section('emc2_editor_insert_p', 'WYSIWYG New Line Behavior', array('MP_WP_Preserved_Markup', 'render_setting_section'), 'writing');

            //Add settings fields for each custom post type that supports a wysiwyg editor
            $types = get_post_types(array(), "objects");

            foreach ($types as $id => $type) {
                if (post_type_supports($id, 'editor')) {
                    
                    //cache valid types for fixit buttons
                    array_push(self::$valid_types, array(
                        'id' => $id,
                        'label' => $type->label));
                    
                    add_settings_field(
                        'emc2_editor_insert_p_' . $id, //setting id
                        $type->label, //setting title
                        array('MP_WP_Preserved_Markup', 'render_setting_input'), //render callback
                        'writing', //page
                        'emc2_editor_insert_p', //section (default = top)
                        array(
                            'label_for' => 'emc2_editor_insert_p_' . $id,
                            'id' => $id //pass id into render callback
                        )
                    );
                }
            }
        }
    }

    static function render_setting_section($t) {
        //add description of section to page
        echo
        "<p>By default the WordPress editor will automatically inject a new paragraph tag when the enter key is pressed.
            The Preserved HTML Editor Markup plugin now gives you three options to chose from when a new line is created:</p>
            
            <ul><li><b>P-Tag</b>: This option will continue using the default behavior of WordPress, while still preserving HTML whitespace &amp; allowing for block-level anchor tags.</li>
                <li><b>BR-Tag</b>: This option will inject HTML line-breaks (&lt;BR&gt; tags) instead of new paragraph tags.<b>*</b></li>
                <li><b>Both</b>: This option will inject HTML line-breaks by default, but will start a new paragraph tag if two consecutive enter keys are pressed. <b>**</b></li></ul>
            
        <p><i>*This was the default behavior of this plugin prior to version 1.2.  You may continue to use this setting if you prefer, but I recommend the 'Both' setting as a compromise for Visual Tab and HTML Tab users.</i></p>
        <p><i>**This feature is currently not 100% compatible with FireFox browsers.  It will not cause problems with the editor, but it will only insert a new paragraph tag after two consecutive returns if the current line is already wrapped in a paragraph tag. However FireFox does allow you to change the Format option for a single line of unwrapped text in the Visual Editor, so users can easily add paragraph tags around unformatted text via the toolbar.</i></p>
        <h3>Fixing Existing Content</h3>
        <p><b style='color: red;'>BE SURE TO BACK UP YOUR DATABASE</b>, as the changes are permanent otherwise.</p>
        <p>If you have existing content that isn't displaying correctly with this plugin enabled, you should use the 'Fix ...' buttons below.  This will modify the content in the database with <a href='http://codex.wordpress.org/Function_Reference/wpautop'>wpautop</a>, including past revisions.</p>
        <p>Fixing a content type multiple times should be harmless as WordPress does this by default. Content that you have modified via the editor after the plugin activation date will not be affected. But it is recommended that you fix your existing content soon after enabling the plugin.</p>
        "; 
        
        echo "<p class='pressthis'>";
        foreach (self::$valid_types as $o) {
            echo "<a style='width: auto; margin-bottom: 10px; padding-right: 8px; cursor: pointer;' onclick='emc2pm_fix_content(\"{$o['id']}\"); return false;' href='#'><span>Fix {$o['label']}</span></a> &nbsp; ";
        }
        echo "</p>";
        
        echo "<h3>Configure New Line Behavior Per Post Type</h3>";
    }

    static function render_setting_input($attr) {
        //Display a list of option boxes for specifying the new line insertion behavior
        $options = get_option('emc2_editor_insert_p');
        $value = isset($options[$attr['id']]) ? $options[$attr['id']] : 'br';
        ?>
            <input id="<?php echo $attr['label_for'] . "_p"; ?>" name="emc2_editor_insert_p[<?php echo $attr['id']; ?>]" type="radio" value="p" <?php echo ($value == "p" ? "checked" : ""); ?> class="small-text" /> P-Tag<br>
            <input id="<?php echo $attr['label_for'] . "_br"; ?>" name="emc2_editor_insert_p[<?php echo $attr['id']; ?>]" type="radio" value="br" <?php echo ($value == "br" ? "checked" : ""); ?> class="small-text" /> BR-Tag<br>
            <input id="<?php echo $attr['label_for'] . "_both"; ?>" name="emc2_editor_insert_p[<?php echo $attr['id']; ?>]" type="radio" value="both" <?php echo ($value == "both" ? "checked" : ""); ?> class="small-text" /> Both
        <?php
    }
    
    static function validate_settings($in) {
        return $in; //it's an option box, I don't think people can mess that up
    }
}
