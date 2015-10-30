=== Preserved HTML Editor Markup Plus ===
Contributors: marcuspope, j-ro
Donate link: http://www.marcuspope.com/wordpress
Tags: wpautop, editor, markup, html, white space, HTML5, WYSIWYG, visual, developer
Requires at least: 3.2.1
Tested up to: 4.0
Stable tag: 1.5.1
License: GPLv2 or later


Preserves white space and developer edits in HTML AND WYSIWYG tab.  Supports inline scripts/css, JavaScript code blocks and HTML5 content editing


== Description ==

This plugin preserves the user-generated HTML markup in the TinyMCE editor.  Unlike other plugins this one allows developers to work in the HTML tab AND end-users to work in the WYSIWYG Visual tab at the same time!  No longer will your HTML markup be completely munged into an unrecognizable form when you switch between those tabs.  And you don't have to hang your users/editors out to dry when you hand off the project with a disabled Visual tab.

#### IMPORTANT: Please read the installation instructions carefully.  If you have existing content it will not render properly after activating this plugin until you use the Fix It Tools.

(One user didn't read or follow these steps and panicked thinking I ruined their website.)

It also supports HTML5 Block Anchor tags in addition to other HTML5 elements, something that is currently not supported in WordPress via any existing plugins.


== Installation ==

1. Upload the plugin contents to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in Wordpress Admin
1. If you have existing content that needs fixing, use the "Fix Posts" feature under Admin > Settings > Writing: Fix Existing Content.
1. You're done!

== Frequently Asked Questions ==

= When will code tag issues be resolve? =

This is a tough one.  Not only do I have no idea why they're being trumped, but I also have a daughter that will be born pretty soon :D, and a project at work that is about to get hectic :(  I'll try to fix it when I can but if you have the skills to help debug the help would be greatly appreciated.

= Does this plugin actually disable wpautop? =

Yes.  And unlike virtually every other "disable wpautop" plugin this one will actually disable the client-side version of wpautop that runs when you switch between the Visual and HTML tabs. Even when using the P Tag mode or hybrid mode, wpautop is disabled and custom code is being used to inject paragraphs a little more intelligently.

= What exactly do the "Fix Posts" or "Fix XXX" buttons do to my content? =

Firstly, only use this feature if you are starting new with version 1.2. And definitely backup your database before running these tools, they have only been tested on two sites so far.  And although in theory it is safe, you should still protect yourself.

The fix actually just runs wpautop one final time on the posts in the database.  By default WordPress runs that function every time it displays content, so the raw data in the database is free of any paragraph tags & other formatting tweaks.  The Fix buttons update the raw content in the database with the formatted version wpautop produces.  And fortunately wpautop was designed in a way that it can be run multiple times so it shouldn't mangle your content.

All of your post content will be converted, including past revisions.  So if you need to revert a page or post after you activate this feature, you won't have to reformat the previous version by hand.

The plugin also keeps track of when it was activated, so it will only modify content that was edited before the plugin was activated.  So if you created some new content after activating the plugin and later realized all of your other content wasn't displaying correctly it's safe to use the Fix buttons without ruining your new content.

== Upgrade Notice ==

If you used version 1.0 or 1.1 to create content, do not use the Fix it features unless you are ok with losing the white space preservation of those posts.

== Screenshots ==

1. No screenshots

== Changelog ==
= 1.5.1 =
* New TinyMCE version updates
= 1.4 =
* Removed 'show_ui' filter for fix custom post type buttons.
= 1.3 =
* Added support for inline JavaScript and CSS, as long as the wptexturize and convert_chars filters are disabled. (Thanks to ViennaMex for pointed out the problem.)
* Added cache-buster for this plugin's JavaScript includes to prevent upgrade issues seen in version 1.2 (Thanks to dreamdancerdesign, peterdub & abbyj for troubleshooting support.)
* Special thanks to dreamdancerdesign for providing a live testing server - above and beyond.
= 1.2 =
* Added support for user-specified newline behavior per post type
* Added support for multi-line html comments (Thanks cwlee_klagroup!)
* Fixed a bug found in TinyMCE related to Format drop down
* Added tools to convert existing site content programmatically by post type.
= 1.1 =
* Refactored for support of < php5.3 by replacing function references with static function array refs
= 1.0 =
* Initial creation of plugin