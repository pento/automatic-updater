=== Automatic Updater ===
Contributors: pento
Donate link: http://pento.net/donate/
Tags: updates, core, plugins, themes
Requires at least: 3.1
Tested up to: 3.5
Stable tag: 0.1

Automatically update WordPress, your themes and plugins! Never have to click the update button again!

== Description ==

Automatic Updater keeps your WordPress install up to date with the latest releases automatically, as soon as the update is available.

While this will be useful for the vast majority of sites, please exercise caution, particularly if you have any custom themes or plugins running on your site.

You should also be aware that this will only work on WordPress installs that have the appropriate file permissions to update through the web interface - it will not work if you usually FTP updates to your server.

== Installation ==

= The Good Way =

1. In your WordPress Admin, go to the Add New Plugins page
1. Search for: automatic updater
1. Automatic Updater should be the first result. Click the Install link.

= The Old Way =

1. Upload the plugin to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

= The Living-On-The-Edge Way =

(Please don't do this in production, you will almost certainly break something!)

1. Checkout the current development version from http://plugins.svn.wordpress.org/automatic-updater/trunk/
1. Subscribe to the [RSS feed](http://plugins.trac.wordpress.org/log/automatic-updater?limit=100&mode=stop_on_copy&format=rss) to be notified of changes

== Changelog ==

= 0.2 =
* FIXED: s/automattic/automatic/g
* FIXED: Support forums link

= 0.1 =
* Initial release