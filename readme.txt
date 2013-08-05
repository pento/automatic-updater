=== Automatic Updater ===
Contributors: pento
Donate link: http://pento.net/donate/
Tags: updates, core, plugins, themes, stable, nightly, svn, wordpress automatic upgrader
Requires at least: 3.4
Tested up to: 3.6
Stable tag: 0.9
License: GPL2+

Automatically update WordPress, your themes and plugins! Never have to click the update button again!

== Description ==

Automatic Updater keeps your WordPress install up to date with the latest releases automatically, as soon as the update is available. It supports updating stable releases, nightly releases, or even regular SVN checkouts!

If you're working on a WordPress Multisite install, it will properly restrict the options page to your Network Admin.

While this will be useful for the vast majority of sites, please exercise caution, particularly if you have any custom themes or plugins running on your site.

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

= 0.9.1 =
* UPDATED: Language POT file
* UPDATED: German (de_DE) translation. Props [Alexander Pfabel](http://alexander.pfabel.de/)
* UPDATED: Dutch (nl_NL) translation. Props Trifon Rijksen
* FIXED: If the `svn up` output was empty, don't send an update email
* FIXED: Removed pass-by-reference, it's too old school for @wonderboymusic
* FIXED: The settings link was incorrect in Multisite

= 0.9 =
* ADDED: Warning when Better WP Security is hiding update info
* ADDED: Warning when the user can't update directly, but hasn't defined S/FTP login details
* ADDED: AUTOMATIC_UPDATER_DISABLED wp-config option, for forcing updates to never happen
* ADDED: Sanity check to make sure the plugin isn't accessed directly
* CHANGED: For nightly build upgrade notification emails, include the build numbers
* UPDATED: Tested up to WordPress 3.6
* FIXED: Don't send a notification email if the core upgrade didn't change versions (ie, a nightly build with no changes)
* FIXED: Settings page CSS wasn't loading if the plugin was installed in a symlink directory
* FIXED: Themes and plugins in non-writeable directories weren't being highlighted correctly on the settings page
* FIXED: Core upgrade retry emails were not showing the correct retry limit
* FIXED: Nightly core upgrades can sometimes repeat more than once every 24 hours

= 0.8.5 =
* FIXED: Disable email notifications option was being set, but not showing up as set
* FIXED: Only write to the options table when options have actually change
* FIXED: Funky email layout if svn up'ing multiple things in one go
* FIXED: Possible PHP error caused by including some core class definitions multiple times

= 0.8.4 =
* ADDED: A link to the SVN log browser for Core, when it updates
* ADDED: Japanese (日本語) (ja) translation. Props [Tai](http://tekapo.com/)
* UPDATED: Norwegian Bokmål (nb_NO) translation. Props [Bjørn Johansen](https://twitter.com/bjornjohansen)

= 0.8.3 =
* FIXED: Bug preventing normal WordPress Core updates from occurring
* FIXED: Theme and Plugin updates not properly skipping those marked for SVN updates

= 0.8.2 =
* FIXED: SVN updates of WordPress core were not being triggered
* FIXED: Particularly large SVN updates could cause notification email corruption
* UPDATED: Dutch (nl_NL) translation. Props Trifon Rijksen
* UPDATED: German (de_DE) translation. Props [Alexander Pfabel](http://alexander.pfabel.de/)

= 0.8.1 =
* UPDATED: Language POT file
* FIXED: Some unnecessary characters appearing in Admin when SVN isn't being used
* FIXED: Sanity checking of normal updates marked for SVN updates

= 0.8 =
* ADDED: SVN support for plugins and themes
* ADDED: Retry limits, so broken updates won't keep trying to install
* ADDED: Option to only receive SVN update emails if something went wrong
* FIXED: Some HTML tags in debug messages were being incorrectly stripped
* FIXED: Don't automatically enable Core updates on installs that seem to be coming from a repo

= 0.7.2 =
* ADDED: WordPress MultiSite support
* UPDATED: German (de_DE) translation. Props [Alexander Pfabel](http://alexander.pfabel.de/)
* FIXED: Now works properly if installed in a symlink directory

= 0.7.1 =
* UPDATED: Italian (it_IT) translation. Props Stefano Giolo
* UPDATED: Dutch (nl_NL) translation. Props Trifon Rijksen
* FIXED: Override email setting wasn't saving correctly

= 0.7 =
* ADDED: Option to override where the update email is sent
* ADDED: 'auto_updater_notification_email_address' filter, for the update notification email address
* ADDED: Reminder in the notification email for users to mark the plugins/themes compatible
* ADDED: Option to disable notification emails
* CHANGED: Notification emails now send as HTML emails (for greater flexibility of information to include)
* UPDATED: Language POT file
* FIXED: Some strings were formatted incorrectly for translation
* FIXED: Escape all strings appropriately before displaying
* FIXED: SVN updates would cause hourly emails, regardless of an update occurring or not

= 0.6.3 =
* ADDED: Taiwan Traditional Chinese (Taiwan 正體中文) (zh_TW) translation. Props [Pseric](http://www.freegroup.org/)
* ADDED: Italian (it_IT) translation. Props Stefano Giolo.

= 0.6.2 =
* UPDATED: Norwegian Bokmål (nb_NO) translation. Props [Bjørn Johansen](https://twitter.com/bjornjohansen)

= 0.6.1 =
* UPDATED: German (de_DE) translation. Props [Alexander Pfabel](http://alexander.pfabel.de/)

= 0.6 =
* ADDED: Support for nightly builds
* ADDED: Dutch (nl_NL) translation. Props Trifon Rijksen
* UPDATED: Language POT file

= 0.5 =
* ADDED: SVN support for core - if you're running WordPress from SVN, you now have the option to keep it up-to-date!
* ADDED: Norwegian Bokmål (nb_NO) translation. Props [Bjørn Johansen](https://twitter.com/bjornjohansen)
* ADDED: Link to the Settings page from the Plugin list
* UPDATED: Language POT file

= 0.4.1 =
* FIXED: Stop trying to update plugins and themes that are already updated

= 0.4 =
* ADDED: German (de_DE) translation. Props [Alexander Pfabel](http://alexander.pfabel.de/)
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
