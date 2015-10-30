=== Download Monitor Amazon S3 Integration ===
Contributors: mikejolley
Tags: download, amazon s3
Requires at least: 3.5
Tested up to: 4.0
Stable tag: 1.0.6
License: GNU General Public License v3.0

Lets you link to files hosted on Amazon s3 so that you can serve secure, expiring download links.

== Installation ==

To install this plugin, please refer to the guide here: [http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation)

= Usage =

After installing, head to Downloads > Settings, and inside the "Amazon S3" tab fill in your Amazon S3 Keys. You can get the keys from https://console.aws.amazon.com/iam/home?#security_credential.

Once that is done, when you create or edit a download you can either use the "amazon s3 object" button to generate an S3 link, or add the link manually.

The link format should be: http://BUCKETNAME.s3.amazonaws.com/OBJECTPATH You don't need to add in the query string for keys etc, this will be done for you when serving the download.

When a download link is used containing the s3.amazonaws.com domain, the plugin will generate the correct amazon URL using your keys, and redirect the user.

= Support Policy =

I will happily patch any confirmed bugs with this plugin, however, I will not offer support for:

1. Customisations of this plugin or any plugins it relies upon
2. Conflicts with "premium" themes from ThemeForest and similar marketplaces (due to bad practice and not being readily available to test)
3. CSS Styling (this is customisation work)

If you need help with customisation you will need to find and hire a developer capable of making the changes.

== Changelog ==

= 1.0.6 =
* Moved JS to separate file.
* Use new logger object.
* Use new DLM extension update.

= 1.0.5 =
* Append Signature without WP encoding.

= 1.0.4 =
* Fix for when bucket names contain periods

= 1.0.3 =
* Fix for different s3 formats

= 1.0.2 =
* Tweak bucket name detection

= 1.0.1 =
* Made Amazon check more generic

= 1.0.0 =
* First release.