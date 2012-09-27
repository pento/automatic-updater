<?php
/*
 * Plugin Name: Automatic Updater
 * Plugin URI: http://pento.net/
 * Description: Automatically update your WordPress site, as soon as updates are released! Never worry about falling behing on updating again!
 * Author: pento
 * Version: 0.2
 * Author URI: http://pento.net
 * License: GPL2+
 * Text Domain: automatic-updater
 * Domain Path: /languages/
 */

global $auto_updater_running;
$auto_updater_running = false;

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
				);
		update_option( 'automatic-updater', $options );
	}

	global $auto_updater_running;
	// If the update check was one we called manually, don't get into a crazy recusive loop.
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
		echo "\n:{$options['update'][$type]}:{$update_data['counts'][$internal]}:$type:$internal\n";
		var_dump( $update_data );
		var_dump( $options );
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

	$auto_updater_running = true;

	do_action( 'auto_updater_before_update', 'core' );

	$skin = new Auto_Updater_Skin();
	$upgrader = new Core_Upgrader( $skin );
	$upgrader->upgrade( $update );

	do_action( 'auto_updater_after_update', 'core' );

	$message = join( "\r\n", $skin->messages );

	wp_mail( get_option( 'admin_email' ), __( 'Core Update', 'automatic-updater' ), $message );

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

	$plugins = apply_filters( 'auto_updater_plugin_updates', array_keys( get_plugin_updates() ) );
	if ( empty( $plugins ) )
		return;

	$auto_updater_running = true;

	do_action( 'auto_updater_before_update', 'plugins' );

	$skin = new Auto_Updater_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$upgrader->bulk_upgrade( $plugins );

	do_action( 'auto_updater_after_update', 'plugins' );

	$message = join( "\r\n", $skin->messages );

	wp_mail( get_option( 'admin_email' ), __( 'Plugin Update', 'automatic-updater' ), $message );

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

	$themes = apply_filters( 'auto_updater_theme_updates', array_keys( get_theme_updates() ) );
	if ( empty( $themes ) )
		return;

	$auto_updater_running = true;

	do_action( 'auto_updater_before_update', 'themes' );

	$skin = new Auto_Updater_Skin();
	$upgrader = new Theme_Upgrader( $skin );
	$upgrader->bulk_upgrade( $themes );

	do_action( 'auto_updater_after_update', 'themes' );

	$message = join( "\r\n", $skin->messages );

	wp_mail( get_option( 'admin_email' ), __( 'Theme Update', 'automatic-updater' ), $message );

	wp_update_themes();
}
add_action( 'auto_updater_themes_event', 'auto_updater_themes' );

