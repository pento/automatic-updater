<?php
/*
 * Plugin Name: Automatic Updater
 * Plugin URI: http://pento.net/projects/automatic-updater-for-wordpress/
 * Description: Automatically update your WordPress site, as soon as updates are released! Never worry about falling behing on updating again!
 * Author: pento
 * Version: 0.3.2
 * Author URI: http://pento.net/
 * License: GPL2+
 * Text Domain: automatic-updater
 * Domain Path: /languages/
 */

global $auto_updater_running;
$auto_updater_running = false;

define( 'AUTOMATIC_UPDATER_BASENAME', plugin_basename( __FILE__ ) );

function auto_updater_requires_wordpress_version() {
	if ( version_compare( $GLOBALS['wp_version'], '3.4', '<' ) ) {
		if ( is_plugin_active( AUTOMATIC_UPDATER_BASENAME ) ) {
			deactivate_plugins( AUTOMATIC_UPDATER_BASENAME );
			add_action( 'admin_notices', 'auto_updater_disabled_notice' );
			if ( isset( $_GET['activate'] ) )
				unset( $_GET['activate'] );
		}
	}
}
add_action( 'admin_init', 'auto_updater_requires_wordpress_version' );

function auto_updater_disabled_notice() {
	echo '<div class="updated"><p><strong>' . __( 'Automatic Updater requires WordPress 3.4 or higher! Please upgrade WordPress manually, then reactivate Automatic Updater.', 'automatic-updater' ) . '</strong></p></div>';
}

function auto_updater_init() {
	if ( is_admin() )
		include_once( dirname( __FILE__ ) . '/admin.php' );

	$options = get_option( 'automatic-updater', array() );

	if ( empty( $options ) ) {
		$options = array(
					'update' => array(
								'core' => true,
								'plugins' => false,
								'themes' => false,
							),
					'debug' => false,
				);
		update_option( 'automatic-updater', $options );
	}

	// 'debug' option added in version 0.3
	if ( ! array_key_exists( 'debug', $options ) ) {
		$options['debug'] = false;
		update_option( 'automatic-updater', $options );
	}

	// Load the translations
	load_plugin_textdomain( 'automatic-updater', false, dirname( AUTOMATIC_UPDATER_BASENAME ) . '/languages/' );

	global $auto_updater_running;
	// If the update check was one we called manually, don't get into a crazy recursive loop.
	if ( $auto_updater_running )
		return;

	$types = array( 'wordpress' => 'core', 'plugins' => 'plugins', 'themes' => 'themes' );
	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		// We're in a cron, do updates now
		foreach ( $types as $type ) {
			if ( ! empty( $options['update'][$type] ) ) {
				add_action( "set_site_transient_update_$type", "auto_updater_$type" );
				add_action( "set_site_transient__site_transient_update_$type", "auto_updater_$type" );
			}
		}
	}
	else {
		include_once( ABSPATH . 'wp-admin/includes/update.php' );
		$update_data = wp_get_update_data();
		// Not in a cron, schedule updates to happen in the next cron run
		foreach ( $types as $internal => $type ) {
			if ( ! empty( $options['update'][$type] ) && $update_data['counts'][$internal] > 0 ) {
				wp_schedule_single_event( time(), "auto_updater_{$type}_event" );
			}
		}
	}
}
add_action( 'init', 'auto_updater_init' );

function auto_updater_core() {
	global $auto_updater_running;
	if ( $auto_updater_running )
		return;

	// Forgive me father, for I have sinned. I have included wp-admin files in a plugin.
	include_once( ABSPATH . 'wp-admin/includes/update.php' );
	include_once( ABSPATH . 'wp-admin/includes/file.php' );

	include_once( dirname( __FILE__ ) . '/updater-skin.php' );

	$updates = get_core_updates();
	if ( empty( $updates ) )
		return;

	$update = apply_filters( 'auto_updater_core_updates', find_core_update( $updates[0]->current, $updates[0]->locale ) );
	if ( empty( $update ) )
		return;

	$old_version = $GLOBALS['wp_version'];

	// Sanity check that the new upgrade is actually an upgrade
	if ( version_compare( $old_version, $update->current, '>=' ) )
		return;

	$auto_updater_running = true;

	do_action( 'auto_updater_before_update', 'core' );

	$skin = new Auto_Updater_Skin();
	$upgrader = new Core_Upgrader( $skin );
	$result = $upgrader->upgrade( $update );

	do_action( 'auto_updater_after_update', 'core' );

	if ( is_wp_error( $result ) ) {
		$message = __( "While trying to upgrade WordPress, we ran into the following error:", 'automatic-updater' );
		$message .= "\r\n\r\n" . $result->get_error_message() . "\r\n\r\n";
		$message .= __( "We're sorry it didn't work out. Please try upgrading manually, instead.", 'automatic-updater' );
	}
	else {
		$message = sprintf( __( "We've successfully upgraded WordPress from version %1s to version %2s!", 'automatic-updater' ), $old_version, $update->current );
		$message .= "\r\n\r\n" . __( 'Have fun!', 'automatic-updater' );
	}

	$message .= "\r\n";

	$debug = join( "\r\n", $skin->messages );

	auto_updater_notification( $message, $debug );

	wp_version_check();
}
add_action( 'auto_updater_core_event', 'auto_updater_core' );

function auto_updater_plugins() {
	global $auto_updater_running;
	if ( $auto_updater_running )
		return;

	include_once( ABSPATH . 'wp-admin/includes/update.php' );
	include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
	include_once( ABSPATH . 'wp-admin/includes/file.php' );

	include_once( dirname( __FILE__ ) . '/updater-skin.php' );

	$plugins = apply_filters( 'auto_updater_plugin_updates', get_plugin_updates() );
	if ( empty( $plugins ) )
		return;

	$auto_updater_running = true;

	do_action( 'auto_updater_before_update', 'plugins' );

	$skin = new Auto_Updater_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$result = $upgrader->bulk_upgrade( array_keys( $plugins ) );

	do_action( 'auto_updater_after_update', 'plugins' );

	$message = _n( 'We found a plugin upgrade!', 'We found upgrades for some plugins!', count( $plugins ), 'automatic-updater' );
	$message .= "\r\n\r\n";

	foreach ( $plugins as $id => $plugin ) {
		if ( is_wp_error( $result[$id] ) ) {
			/* translators: First argument is the Plugin name, second argument is the error encountered while upgrading */
			$message .= sprintf( __( "%1s: We encounted an error upgrading this plugin: %2s", 'automatic-updater' ),
										$plugin->Name,
										$result[$id]->get_error_message() );
		}
		else {
			/* tranlators: First argument is the Plugin name, second argument is the old version number, third argument is the new version number */
			$message .= sprintf( __( "%1s: Successfully upgraded from version %2s to %3s!", 'automatic-updater' ),
										$plugin->Name,
										$plugin->Version,
										$plugin->update->new_version );
		}

		$message .= "\r\n";
	}

	$debug = join( "\r\n", $skin->messages );

	auto_updater_notification( $message, $debug );

	wp_update_plugins();
}
add_action( 'auto_updater_plugins_event', 'auto_updater_plugins' );

function auto_updater_themes() {
	global $auto_updater_running;
	if ( $auto_updater_running )
		return;

	include_once( ABSPATH . 'wp-admin/includes/update.php' );
	include_once( ABSPATH . 'wp-admin/includes/file.php' );

	include_once( dirname( __FILE__ ) . '/updater-skin.php' );

	$themes = apply_filters( 'auto_updater_theme_updates', get_theme_updates() );
	if ( empty( $themes ) )
		return;

	$auto_updater_running = true;

	do_action( 'auto_updater_before_update', 'themes' );

	$skin = new Auto_Updater_Skin();
	$upgrader = new Theme_Upgrader( $skin );
	$result = $upgrader->bulk_upgrade( array_keys( $themes ) );

	do_action( 'auto_updater_after_update', 'themes' );

	$message = _n( 'We found a theme upgrade!', 'We found upgrades for some themes!', count( $themes ), 'automatic-updater' );
	$message .= "\r\n\r\n";

	foreach ( $themes as $id => $theme ) {
		if ( is_wp_error( $result[$id] ) ) {
			/* translators: First argument is the Theme name, second argument is the error encountered while upgrading */
			$message .= sprintf( __( "%1s: We encounted an error upgrading this theme: %2s", 'automatic-updater' ),
										$theme->name,
										$result[$id]->get_error_message() );
		}
		else {
			/* tranlators: First argument is the Theme name, second argument is the old version number, third argument is the new version number */
			$message .= sprintf( __( "%1s: Successfully upgraded from version %2s to %3s!", 'automatic-updater' ),
										$theme->name,
										$theme->version,
										$theme->update['new_version'] );
		}

		$message .= "\r\n";
	}

	$debug = join( "\r\n", $skin->messages );

	auto_updater_notification( $message, $debug );

	wp_update_themes();
}
add_action( 'auto_updater_themes_event', 'auto_updater_themes' );

function auto_updater_notification( $info = '', $debug = '' ) {
	$options = get_option( 'automatic-updater', array() );
	$site = get_home_url();
	$subject = sprintf( __( 'WordPress Update: %1s', 'automatic-updater' ), $site );

	$message = __( 'Howdy!', 'automatic-updater' );
	$message .= "\r\n\r\n";
	$message .= sprintf( __( 'Automatic Updater just ran on your site, %1s, with the following result:', 'automatic-updater' ), $site );
	$message .= "\r\n\r\n";

	$message .= $info;

	$message .= "\r\n";
	$message .= __( 'Thanks for using the Automatic Updater plugin!', 'automatic-updater' );

	if ( ! empty( $options['debug'] ) ) {
		$message .= "\r\n\r\n\r\n\r\n";
		$message .= __( 'Debug Information:', 'automatic-updater' );
		$message .= "\r\n\r\n$debug";
	}

	wp_mail( get_option( 'admin_email' ), $subject, $message );
}

