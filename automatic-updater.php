<?php
/*
 * Plugin Name: Automatic Updater
 * Plugin URI: http://pento.net/projects/automatic-updater-for-wordpress/
 * Description: Automatically update your WordPress site, as soon as updates are released! Never worry about falling behing on updating again!
 * Author: pento
 * Version: 0.6.3
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
	echo '<div class="updated"><p><strong>' . esc_html__( 'Automatic Updater requires WordPress 3.4 or higher! Please upgrade WordPress manually, then reactivate Automatic Updater.', 'automatic-updater' ) . '</strong></p></div>';
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
					'svn' => false,
					'debug' => false,
					'next-development-update' => time(),
				);
		update_option( 'automatic-updater', $options );
	}

	// 'debug' option added in version 0.3
	if ( ! array_key_exists( 'debug', $options ) ) {
		$options['debug'] = false;
		update_option( 'automatic-updater', $options );
	}

	// SVN updates added in version 0.5
	if ( ! array_key_exists( 'svn', $options ) ) {
		$options['svn'] = false;
		update_option( 'automatic-updater', $options );
	}

	// Development version updates added in version 0.6
	if ( ! array_key_exists( 'next-development-update', $options ) ) {
		$options['next-development-update'] = time();
		update_option( 'automatic-updater', $options );
	}

	// Override contact email added in version 0.7
	if ( ! array_key_exists( 'override-email', $options ) ) {
		$options['override-email'] = '';
		update_option( 'automatic-updater', $options );
	}

	// Configure SVN updates cron, if it's enabled
	if ( $options['svn'] ) {
		if ( ! wp_next_scheduled( 'auto_updater_svn_event' ) )
			wp_schedule_event( time(), 'hourly', 'auto_updater_svn_event' );
	}
	else {
		if ( $timestamp = wp_next_scheduled( 'auto_updater_svn_event' ) )
			wp_unschedule_event( $timestamp, 'auto_updater_svn_event' );
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
		$update_data = auto_updater_get_update_data();
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

	$options = get_option( 'automatic-updater', array() );
	if ( $options['svn'] )
		return;

	// Forgive me father, for I have sinned. I have included wp-admin files in a plugin.
	include_once( ABSPATH . 'wp-admin/includes/update.php' );
	include_once( ABSPATH . 'wp-admin/includes/file.php' );

	include_once( dirname( __FILE__ ) . '/updater-skin.php' );

	$updates = get_core_updates();
	if ( empty( $updates ) )
		return;

	if ( 'development' == $updates[0]->response )
		$update = $updates[0];
	else
		$update = find_core_update( $updates[0]->current, $updates[0]->locale );

	$update = apply_filters( 'auto_updater_core_updates', $update );
	if ( empty( $update ) )
		return;

	$old_version = $GLOBALS['wp_version'];

	// Sanity check that the new upgrade is actually an upgrade
	if ( 'development' != $update->response && version_compare( $old_version, $update->current, '>=' ) )
		return;

	// Only do development version updates once every 24 hours
	if ( 'development' == $update->response ) {
		if ( time() < $options['next-development-update'] )
			return;

		$options['next-development-update'] = strtotime( '+24 hours' );
		update_option( 'automatic-updater', $options );
	}

	$auto_updater_running = true;

	do_action( 'auto_updater_before_update', 'core' );

	$skin = new Auto_Updater_Skin();
	$upgrader = new Core_Upgrader( $skin );
	$result = $upgrader->upgrade( $update );

	do_action( 'auto_updater_after_update', 'core' );

	if ( is_wp_error( $result ) ) {
		$message = esc_html__( "While trying to upgrade WordPress, we ran into the following error:", 'automatic-updater' );
		$message .= '<br><br>' . $result->get_error_message() . '<br><br>';
		$message .= esc_html__( "We're sorry it didn't work out. Please try upgrading manually, instead.", 'automatic-updater' );
	}
	else if( 'development' == $update->response ) {
		$message = esc_html__( "We've successfully upgraded WordPress to the latest nightly build!", 'automatic-updater' );
		$message .= '<br><br>' . esc_html__( 'Have fun!', 'automatic-updater' );
	}
	else {
		$message = sprintf( esc_html__( 'We\'ve successfully upgraded WordPress from version %1$s to version %2$s!', 'automatic-updater' ), $old_version, $update->current );
		$message .= '<br><br>' . esc_html__( 'Have fun!', 'automatic-updater' );
	}

	$message .= '<br>';

	$debug = join( '<br>', $skin->messages );

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

	// Remove any plugins from the list that may've already been updated
	foreach ( $plugins as $id => $plugin ) {
		if ( version_compare( $plugin->Version, $plugin->update->new_version, '>=' ) )
			unset( $plugins[$id] );
	}

	if ( empty( $plugins ) )
		return;

	$auto_updater_running = true;

	do_action( 'auto_updater_before_update', 'plugins' );

	$skin = new Auto_Updater_Skin();
	$upgrader = new Plugin_Upgrader( $skin );
	$result = $upgrader->bulk_upgrade( array_keys( $plugins ) );

	do_action( 'auto_updater_after_update', 'plugins' );

	$message = esc_html( _n( 'We found a plugin upgrade!', 'We found upgrades for some plugins!', count( $plugins ), 'automatic-updater' ) );
	$message .= '<br><br>';

	foreach ( $plugins as $id => $plugin ) {
		if ( is_wp_error( $result[$id] ) ) {
			/* translators: First argument is the Plugin name, second argument is the error encountered while upgrading */
			$message .= sprintf( esc_html__( '%1$s: We encounted an error upgrading this plugin: %2$s', 'automatic-updater' ),
										$plugin->Name,
										$result[$id]->get_error_message() );
		}
		else {
			/* tranlators: First argument is the Plugin name, second argument is the old version number, third argument is the new version number */
			$message .= sprintf( esc_html__( '%1$s: Successfully upgraded from version %2$s to %3$s!', 'automatic-updater' ),
										$plugin->Name,
										$plugin->Version,
										$plugin->update->new_version );
		}

		$message .= '<br>';
	}

	$debug = join( '<br>', $skin->messages );

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

	// Remove any themes from the list that may've already been updated
	foreach ( $themes as $id => $theme ) {
		if ( version_compare( $theme->Version, $theme->update['new_version'], '>=' ) )
			unset( $themes[$id] );
	}

	if ( empty( $themes ) )
		return;

	$auto_updater_running = true;

	do_action( 'auto_updater_before_update', 'themes' );

	$skin = new Auto_Updater_Skin();
	$upgrader = new Theme_Upgrader( $skin );
	$result = $upgrader->bulk_upgrade( array_keys( $themes ) );

	do_action( 'auto_updater_after_update', 'themes' );

	$message = esc_html( _n( 'We found a theme upgrade!', 'We found upgrades for some themes!', count( $themes ), 'automatic-updater' ) );
	$message .= '<br><br>';

	foreach ( $themes as $id => $theme ) {
		if ( is_wp_error( $result[$id] ) ) {
			/* translators: First argument is the Theme name, second argument is the error encountered while upgrading */
			$message .= sprintf( esc_html__( '%1$s: We encounted an error upgrading this theme: %2$s', 'automatic-updater' ),
										$theme->name,
										$result[$id]->get_error_message() );
		}
		else {
			/* tranlators: First argument is the Theme name, second argument is the old version number, third argument is the new version number */
			$message .= sprintf( esc_html__( '%1$s: Successfully upgraded from version %2$s to %3$s!', 'automatic-updater' ),
										$theme->name,
										$theme->version,
										$theme->update['new_version'] );
		}

		$message .= '<br>';
	}

	$debug = join( '<br>', $skin->messages );

	auto_updater_notification( $message, $debug );

	wp_update_themes();
}
add_action( 'auto_updater_themes_event', 'auto_updater_themes' );

function auto_updater_svn() {
	$output = array();
	$return = NULL;

	exec( 'svn up ' . ABSPATH, $output, $return );

	if ( 0 === $return ) {
		$update = end( $output );
		// No need to email if there was no update.
		if ( 0 === strpos( $update, "At revision" ) )
			return;

		$message = esc_html__( 'We successfully upgraded from SVN!', 'automatic-updater' );
		$message .= "<br><br>$update";
	}
	else {
		$message = esc_html__( 'While upgrading from SVN, we ran into the following error:', 'automatic-updater' );
		$message .= '<br><br>' . end( $output ) . '<br><br>';
		$message .= esc_html__( "We're sorry it didn't work out. Please try upgrading manually, instead.", 'automatic-updater' );
	}

	$message .= '<br>';

	$debug = join( '<br>', $output );

	auto_updater_notification( $message, $debug );
}
add_action( 'auto_updater_svn_event', 'auto_updater_svn' );

function auto_updater_notification( $info = '', $debug = '' ) {
	$options = get_option( 'automatic-updater', array() );
	$site = get_home_url();
	$subject = sprintf( esc_html__( 'WordPress Update: %s', 'automatic-updater' ), $site );

	$message = '<html>';
	$message .= '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
	$message .= '<body>';

	$message .= esc_html__( 'Howdy!', 'automatic-updater' );
	$message .= '<br><br>';
	$message .= wp_kses( sprintf( __( 'Automatic Updater just ran on your site, <a href="%1$s">%1$s</a>, with the following result:', 'automatic-updater' ), $site ), array( 'a' => array( 'href' ) ) );
	$message .= '<br><br>';

	$message .= $info;

	$message .= '<br>';
	$message .= esc_html__( 'Thanks for using the Automatic Updater plugin!', 'automatic-updater' );

	if ( ! empty( $options['debug'] ) ) {
		$message .= "<br><br><br>";
		$message .= esc_html__( 'Debug Information:', 'automatic-updater' );
		$message .= "<br><br>$debug";
	}

	$message .= '</body></html>';

	$email = get_option( 'admin_email' );
	if ( ! empty( $options['override-email'] ) )
		$email = $options['override-email'];

	$email = apply_filters( 'auto_updater_notification_email_address', $email );

	$headers = array( 
					'MIME-Version: 1.0',
					'Content-Type: text/html; charset=UTF-8'
				);

	add_filter( 'wp_mail_content_type', 'auto_updater_wp_mail_content_type' );
	wp_mail( $email, $subject, $message, $headers );
	remove_filter( 'wp_mail_content_type', 'auto_updater_wp_mail_content_type' );
}

function auto_updater_wp_mail_content_type() {
	return 'text/html';
}

function auto_updater_get_update_data() {
	$counts = array( 'plugins' => 0, 'themes' => 0, 'wordpress' => 0 );

	$update_plugins = get_site_transient( 'update_plugins' );
	if ( ! empty( $update_plugins->response ) )
		$counts['plugins'] = count( $update_plugins->response );

	$update_themes = get_site_transient( 'update_themes' );
	if ( ! empty( $update_themes->response ) )
		$counts['themes'] = count( $update_themes->response );

	if ( function_exists( 'get_core_updates' ) ) {
		$update_wordpress = get_core_updates( array( 'dismissed' => false ) );
		if ( ! empty( $update_wordpress ) && 'latest' != $update_wordpress[0]->response )
			$counts['wordpress'] = 1;
	}

	$counts['total'] = $counts['plugins'] + $counts['themes'] + $counts['wordpress'];

	return apply_filters( 'auto_update_get_update_data', array( 'counts' => $counts ) );
}
