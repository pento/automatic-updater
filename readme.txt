=== Automatic Updater ===
Contributors: pento
Donate link: http://pento.net/donate/
Tags: updates, core, plugins, themes, wordpress automatic upgrader
Requires at least: 3.4
Tested up to: 3.5
Stable tag: 0.4.1
License: GPL2+

Automatically update WordPress, your themes and plugins! Never have to click the update button again!

== Description ==

Automatic Updater keeps your WordPress install up to date with the latest releases automatically, as soon as the update is available.

While this will be useful for the vast majority of sites, please exercise caution, particularly if you have any custom themes or plugins running on your site.

You should also be aware that this will only work on WordPress installs that have the appropriate file permissions to update through the web interface - it will not work if you usually FTP updates to your server.

There are some Actions and Filters provided, check the [Documentation](http://pento.net/projects/automatic-updater-for-wordpress/) for more details.

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

= 0.4.1 =
* FIXED: Stop trying to update plugins and themes that are already updated

= 0.4 =
* ADDED: German translation. Props [Alexander Pfabel](http://alexander.pfabel.de/)
* ADDED: Version check on activation, for compatibility
* UPDATED: Language POT file
* FIXED: Typo in the Settings page
* FIXED: Debug information in the notification email now has HTML tags stripped out
* FIXED: Core version check was a little too strong, and could cause updates to be missed. Relaxed a little
* FIXED: Checking to see if WordPress has found any updates will now occur much more frequently

= 0.3.2 =
* ADDED: Language file for translators
* FIXED: Translations should load properly now
* FIXED: Don't try to update WordPress to the same version (I'm mostly certain it's actually fixed this time)
* FIXED: Minor formatting change to the notification emails

= 0.3.1 =
* FIXED: Don't try to update WordPress to the same version (harmless, but unnecessary)
* FIXED: A PHP warning in the Settings page
* FIXED: A couple of typos

= 0.3 =
* ADDED: Extra update checks, updates will now occur as soon as is humanly possible
* ADDED: Much nicer notification emails when upgrades occur
* ADDED: Option to display debug information in the notification email
* FIXED: Use ouput buffering to ensure nothing is printed during upgrades

= 0.2 =
* ADDED: Some useful filters and actions. See the [Documentation](http://pento.net/projects/automatic-updater-for-wordpress/) for details
* FIXED: s/automattic/automatic/g
* FIXED: Support forums link

= 0.1 =
* Initial release

== Screenshots ==

1. Notification emails are sent to the site admin as soon as an update is complete, confirming if the update was successful or not.
