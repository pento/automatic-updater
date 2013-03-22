<?php
/*
 * Plugin Name: Automatic Updater
 * Plugin URI: http://pento.net/projects/automatic-updater-for-wordpress/
 * Description: Automatically update your WordPress site, as soon as updates are released! Never worry about falling behing on updating again!
 * Author: pento
 * Version: 0.8.4
 * Author URI: http://pento.net/
 * License: GPL2+
 * Text Domain: automatic-updater
 * Domain Path: /languages/
 */

$automatic_updater_file = __FILE__;

if ( isset( $plugin ) )
	$automatic_updater_file = $plugin;
else if ( isset( $mu_plugin ) )
	$automatic_updater_file = $mu_plugin;
else if ( isset( $network_plugin ) )
	$automatic_updater_file = $network_plugin;

Automatic_Updater::$basename = plugin_basename( $automatic_updater_file );

class Automatic_Updater {
	private $running = false;
	private $options = array();

	public static $basename;

	function init() {
		static $instance = false;

		if( ! $instance )
			$instance = new Automatic_Updater;

		return $instance;
	}

	function __construct() {
		// Load the translations
		load_plugin_textdomain( 'automatic-updater', false, dirname( self::$basename ) . '/languages/' );

		// Load the options, and check that they're all up to date.
		$this->options = get_option( 'automatic-updater', array() );
		$this->plugin_upgrade();

		add_action( 'shutdown', array( &$this, 'shutdown' ) );

		add_action( 'auto_updater_core_event', array( &$this, 'update_core' ) );
		add_action( 'auto_updater_plugins_event', array( &$this, 'update_plugins' ) );
		add_action( 'auto_updater_themes_event', array( &$this, 'update_themes' ) );
		add_action( 'auto_updater_svn_event', array( &$this, 'update_svn' ) );

		// Nothing else matters if we're on WPMS and not on the main site
		if ( is_multisite() && ! is_main_site() )
			return;

		if ( is_admin() ) {
			include_once( dirname( __FILE__ ) . '/admin.php' );
			Automatic_Updater_Admin::init( $this );
		}

		add_action( 'admin_init', array( &$this, 'check_wordpress_version' ) );

		// Configure SVN updates cron, if it's enabled
		if ( $this->options['svn']['core'] || ! empty( $this->options['svn']['plugins'] ) || ! empty( $this->options['svn']['themes'] ) ) {
			if ( ! wp_next_scheduled( 'auto_updater_svn_event' ) )
				wp_schedule_event( time(), 'hourly', 'auto_updater_svn_event' );
		} else {
			if ( $timestamp = wp_next_scheduled( 'auto_updater_svn_event' ) )
				wp_unschedule_event( $timestamp, 'auto_updater_svn_event' );
		}

		// If the update check was one we called manually, don't get into a crazy recursive loop.
		if ( $this->running )
			return;

		$types = array(
					'wordpress' => 'core',
					'plugins'   => 'plugins',
					'themes'    => 'themes'
		);
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			// We're in a cron, do updates now
			foreach ( $types as $type ) {
				if ( ! empty( $this->options['update'][ $type ] ) ) {
					add_action( "set_site_transient_update_$type", array( &$this, "update_$type" ) );
					add_action( "set_site_transient__site_transient_update_$type", array( &$this, "update_$type" ) );
				}
			}
		} else {
			include_once( ABSPATH . 'wp-admin/includes/update.php' );
			$update_data = $this->get_update_data();
			// Not in a cron, schedule updates to happen in the next cron run
			foreach ( $types as $internal => $type ) {
				if ( ! empty( $this->options['update'][ $type ] ) && $update_data['counts'][ $internal ] > 0 ) {
					wp_schedule_single_event( time(), "auto_updater_{$type}_event" );
				}
			}
		}
	}

	function shutdown() {
		update_option( 'automatic-updater', $this->options );
	}

	function get_option( $name ) {
		if ( array_key_exists( $name, $this->options ) )
			return $this->options[ $name ];

		return null;
	}

	function update_option( $name, $value ) {
		if ( array_key_exists( $name, $this->options ) )
			return $this->options[ $name ] = $value;

		return null;
	}

	function check_wordpress_version() {
		if ( version_compare( $GLOBALS['wp_version'], '3.4', '<' ) ) {
			if ( is_plugin_active( self::$basename ) ) {
				deactivate_plugins( self::$basename );
				add_action( 'admin_notices', array( &$this, 'disabled_notice' ) );
				if ( isset( $_GET['activate'] ) )
					unset( $_GET['activate'] );
			}
		}
	}

	function disabled_notice() {
		echo '<div class="updated"><p><strong>' . esc_html__( 'Automatic Updater requires WordPress 3.4 or higher! Please upgrade WordPress manually, then reactivate Automatic Updater.', 'automatic-updater' ) . '</strong></p></div>';
	}

	function plugin_upgrade() {
		if ( empty( $this->options ) ) {
			// Don't automatically enable core updates in installs coming from a repo
			$core_updates_enabled = true;
			if ( is_dir( ABSPATH . '/.svn' ) || is_dir( ABSPATH . '/.git' ) )
				$core_updates_enabled = false;

			$this->options = array(
						'update'                  => array(
									                  'core'    => $core_updates_enabled,
									                  'plugins' => false,
									                  'themes'  => false,
						),
						'retries-limit'           => 3,
						'tries'                   => array(
									                  'core' => array(
												                 'version' => 0,
												                 'tries'   => 0,
									                   ),
									                  'plugins' => array(),
									                  'themes' => array(),
						),
						'svn'                     => array(
														'core'    => false,
														'plugins' => array(),
														'themes'  => array(),
						),
						'svn-success-email'       => true,
						'debug'                   => false,
						'next-development-update' => time(),
						'override-email'          => '',
						'disable-email'           => false,
					);
		}

		// 'debug' option added in version 0.3
		if ( ! array_key_exists( 'debug', $this->options ) )
			$this->options['debug'] = false;

		// SVN updates added in version 0.5
		if ( ! array_key_exists( 'svn', $this->options ) )
			$this->options['svn'] = false;

		// Development version updates added in version 0.6
		if ( ! array_key_exists( 'next-development-update', $this->options ) )
			$this->options['next-development-update'] = time();

		// Override contact email added in version 0.7
		if ( ! array_key_exists( 'override-email', $this->options ) )
			$this->options['override-email'] = '';

		// Ability to disable email added in version 0.7
		if ( ! array_key_exists( 'disable-email', $this->options ) )
			$this->options['disable-email'] = false;

		// Ability to limit retries added in version 0.8
		if ( ! array_key_exists( 'retries-limit', $this->options ) ) {
			$this->options['retries-limit'] = 3;
			$this->options['tries'] = array(
									'core'    => array(
												   'version' => 0,
												   'tries'   => 0,
									),
									'plugins' => array(),
									'themes'  => array(),
								);
		}

		// Ability to only send SVN update emails on failure added in 0.8
		if ( ! array_key_exists( 'svn-success-email', $this->options ) )
			$this->options['svn-success-email'] = true;

		// SVN support for themes and plugins added in 0.8
		if ( ! is_array( $this->options['svn'] ) )
			$this->options['svn'] =  array(
										'core'    => $this->options['svn'],
										'plugins' => array(),
										'themes'  => array(),
								);
	}

	function update_core() {
		if ( $this->running )
			return;

		if ( $this->options['svn']['core'] )
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

		// Check that we haven't failed to upgrade to the updated version in the past
		if ( version_compare( $update->current, $this->options['retries']['core']['version'], '>=' ) && $this->options['retries']['core']['tries'] >= $this->options['retries-limit'] )
			return;

		$old_version = $GLOBALS['wp_version'];

		// Sanity check that the new upgrade is actually an upgrade
		if ( 'development' != $update->response && version_compare( $old_version, $update->current, '>=' ) )
			return;

		// Only do development version updates once every 24 hours
		if ( 'development' == $update->response ) {
			if ( time() < $this->options['next-development-update'] )
				return;

			$this->options['next-development-update'] = strtotime( '+24 hours' );
		}

		$this->running = true;

		do_action( 'auto_updater_before_update', 'core' );

		$upgrade_failed = false;

		$skin = new Auto_Updater_Skin();
		$upgrader = new Core_Upgrader( $skin );
		$result = $upgrader->upgrade( $update );

		do_action( 'auto_updater_after_update', 'core' );

		if ( is_wp_error( $result ) ) {
			if ( $this->options['tries']['core']['version'] != $update->current )
				$this->options['tries']['core']['version'] = $update->current;

			$this->options['tries']['core']['tries']++;

			$upgrade_failed = true;

			$message = esc_html__( "While trying to upgrade WordPress, we ran into the following error:", 'automatic-updater' );
			$message .= '<br><br>' . $result->get_error_message() . '<br><br>';
			$message .= sprintf( esc_html__( 'We\'re sorry it didn\'t work out. Please try upgrading manually, instead. This is attempt %1$d of %2$d.', 'automatic-updater' ),
							$this->options['tries']['core']['tries'],
							$this->option['retries-limit'] );
		} else if( 'development' == $update->response ) {
			$message = esc_html__( "We've successfully upgraded WordPress to the latest nightly build!", 'automatic-updater' );
			$message .= '<br><br>' . esc_html__( 'Have fun!', 'automatic-updater' );

			$this->options['tries']['core']['version'] = 0;
			$this->options['tries']['core']['tries'] = 0;
		} else {
			$message = sprintf( esc_html__( 'We\'ve successfully upgraded WordPress from version %1$s to version %2$s!', 'automatic-updater' ), $old_version, $update->current );
			$message .= '<br><br>' . esc_html__( 'Have fun!', 'automatic-updater' );

			$this->options['tries']['core']['version'] = 0;
			$this->options['tries']['core']['tries'] = 0;
		}

		$message .= '<br>';

		$debug = join( "<br>\n", $skin->messages );

		$this->notification( $message, $debug, $upgrade_failed );

		wp_version_check();
	}

	function update_plugins() {
		if ( $this->running )
			return;

		include_once( ABSPATH . 'wp-admin/includes/update.php' );
		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		include_once( ABSPATH . 'wp-admin/includes/file.php' );

		include_once( dirname( __FILE__ ) . '/updater-skin.php' );

		$plugins = apply_filters( 'auto_updater_plugin_updates', get_plugin_updates() );

		foreach ( $plugins as $id => $plugin ) {
			// Remove any plugins from the list that may've already been updated
			if ( version_compare( $plugin->Version, $plugin->update->new_version, '>=' ) )
				unset( $plugins[ $id ] );

			// Remove any plugins that are marked for SVN update
			if ( array_key_exists( $id, $this->options['svn']['plugins'] ) )
				unset( $plugins[ $id ] );

			// Remove any plugins that have failed to upgrade
			if ( ! empty( $this->options['retries']['plugins'][ $id ] ) ) {
				// If there's a new version of a failed plugin, we should give it another go.
				if ( $this->options['retries']['plugins'][ $id ]['version'] != $plugin->update->new_version )
					unset( $this->options['retries']['plugins'][ $id ] );
				// If the plugin has already had it's chance, move on.
				else if ($this->options['retries']['plugins'][ $id ]['tries'] > $this->options['retries-limit'] )
					unset( $plugins[ $id ] );
			}
		}

		if ( empty( $plugins ) )
			return;

		$this->running = true;

		do_action( 'auto_updater_before_update', 'plugins' );

		$skin = new Auto_Updater_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result = $upgrader->bulk_upgrade( array_keys( $plugins ) );

		do_action( 'auto_updater_after_update', 'plugins' );

		$message = esc_html( _n( 'We found a plugin upgrade!', 'We found upgrades for some plugins!', count( $plugins ), 'automatic-updater' ) );
		$message .= '<br><br>';

		$upgrade_failed = false;

		foreach ( $plugins as $id => $plugin ) {
			if ( is_wp_error( $result[ $id ] ) ) {
				if ( empty( $this->options['retries']['plugins'][ $id ] ) )
					$this->options['retries']['plugins'][ $id ] = array(
															'tries' => 1,
															'version' => $plugin->update->new_version,
														);
				else
					$this->options['retries']['plugins'][ $id ]['tries']++;

				$upgrade_failed = true;

				/* translators: First argument is the plugin url, second argument is the Plugin name, third argument is the error encountered while upgrading. The fourth and fifth arguments refer to how many retries we've had at installing this plugin. */
				$message .= wp_kses( sprintf( __( '<a href="%1$s">%2$s</a>: We encounted an error upgrading this plugin: %3$s (Attempt %4$d of %5$d)', 'automatic-updater' ),
											$plugin->update->url,
											$plugin->Name,
											$result[ $id ]->get_error_message(),
											$this->options['retries']['plugins'][ $id ]['tries'],
											$this->options['retries-limit'] ),
									array( 'a' => array( 'href' => array() ) ) );
			} else {
				/* translators: First argument is the plugin url, second argument is the Plugin name, third argument is the old version number, fourth argument is the new version number */
				$message .= wp_kses( sprintf( __( '<a href="%1$s">%2$s</a>: Successfully upgraded from version %3$s to %4$s!', 'automatic-updater' ),
											$plugin->update->url,
											$plugin->Name,
											$plugin->Version,
											$plugin->update->new_version ), array( 'a' => array( 'href' => array() ) ) );

				if ( ! empty( $this->options['retries']['plugins'][ $id ] ) )
					unset( $this->options['retries']['plugins'][ $id ] );
			}

			$message .= '<br>';
		}

		$message .= '<br>' . esc_html__( 'Plugin authors depend on your feedback to make their plugins better, and the WordPress community depends on plugin ratings for checking the quality of a plugin. If you have a couple of minutes, click on the plugin names above, and leave a Compatibility Vote or a Rating!', 'automatic-updater' ) . '<br>';

		$debug = join( "<br>\n", $skin->messages );

		$this->notification( $message, $debug, $upgrade_failed );

		wp_update_plugins();
	}

	function update_themes() {
		if ( $this->running )
			return;

		include_once( ABSPATH . 'wp-admin/includes/update.php' );
		include_once( ABSPATH . 'wp-admin/includes/file.php' );

		include_once( dirname( __FILE__ ) . '/updater-skin.php' );

		$themes = apply_filters( 'auto_updater_theme_updates', get_theme_updates() );

		foreach ( $themes as $id => $theme ) {
			// Remove any themes from the list that may've already been updated
			if ( version_compare( $theme->Version, $theme->update['new_version'], '>=' ) )
				unset( $themes[ $id ] );

			// Remove any themes that are marked for SVN update
			if ( array_key_exists( $id, $this->options['svn']['themes'] ) )
				unset( $themes[ $id ] );

			// Remove any themes that have failed to upgrade
			if ( ! empty( $this->options['retries']['themes'][ $id ] ) ) {
				// If there's a new version of a failed theme, we should give it another go.
				if ( $this->options['retries']['themes'][ $id ]['version'] != $theme->update['new_version'] )
					unset( $this->options['retries']['themes'][ $id ] );
				// If the themes has already had it's chance, move on.
				else if ($this->options['retries']['themes'][ $id ]['tries'] > $this->options['retries-limit'] )
					unset( $themes[ $id ] );
			}
		}

		if ( empty( $themes ) )
			return;

		$this->running = true;

		do_action( 'auto_updater_before_update', 'themes' );

		$skin = new Auto_Updater_Skin();
		$upgrader = new Theme_Upgrader( $skin );
		$result = $upgrader->bulk_upgrade( array_keys( $themes ) );

		do_action( 'auto_updater_after_update', 'themes' );

		$message = esc_html( _n( 'We found a theme upgrade!', 'We found upgrades for some themes!', count( $themes ), 'automatic-updater' ) );
		$message .= '<br><br>';

		$upgrade_failed = false;

		foreach ( $themes as $id => $theme ) {
			if ( is_wp_error( $result[ $id ] ) ) {
				if ( empty( $this->options['retries']['themes'][ $id ] ) )
					$this->options['retries']['themes'][ $id ] = array(
															'tries' => 1,
															'version' => $themes->update['new_version'],
														);
				else
					$this->options['retries']['themes'][ $id ]['tries']++;

				$upgrade_failed = true;

				/* translators: First argument is the theme URL, second argument is the Theme name, third argument is the error encountered while upgrading. The fourth and fifth arguments refer to how many retries we've had at installing this theme. */
				$message .= wp_kses( sprintf( __( '<a href="%1$s">%2$s</a>: We encounted an error upgrading this theme: %3$s (Attempt %4$d of %5$d)', 'automatic-updater' ),
											$theme->update['url'],
											$theme->name,
											$result[ $id ]->get_error_message(),
											$this->options['retries']['plugins'][ $id ]['tries'],
											$this->options['retries-limit'] ),
									array( 'a' => array( 'href' => array() ) ) );
			} else {
				/* translators: First argument is the theme URL, second argument is the Theme name, third argument is the old version number, fourth argument is the new version number */
				$message .= wp_kses( sprintf( __( '<a href="%1$s">%2$s</a>: Successfully upgraded from version %3$s to %4$s!', 'automatic-updater' ),
											$theme->update['url'],
											$theme->name,
											$theme->version,
											$theme->update['new_version'] ), array( 'a' => array( 'href' => array() ) ) );

				if ( ! empty( $this->options['retries']['themes'][ $id ] ) )
					unset( $this->options['retries']['themes'][ $id ] );
			}

			$message .= '<br>';
		}

		$message .= '<br>' . esc_html__( 'Theme authors depend on your feedback to make their plugins better, and the WordPress community depends on theme ratings for checking the quality of a theme. If you have a couple of minutes, click on the theme names above, and leave a Compatibility Vote or a Rating!', 'automatic-updater' ) . '<br>';

		$debug = join( "<br>\n", $skin->messages );

		$this->notification( $message, $debug, $upgrade_failed );

		wp_update_themes();
	}

	function update_svn() {
		$output       = array();
		$return       = null;

		$message      = '';

		$found_error  = false;
		$found_update = false;

		$source_control = $this->under_source_control();

		if ( $source_control['core'] && ! empty( $this->options['svn']['core'] ) ) {
			$output[] = esc_html__( 'WordPress Core:', 'automatic-updater' );
			exec( 'svn up ' . ABSPATH, $output, $return );

			$update = end( $output );

			if ( 0 !== strpos( $update, "At revision" ) ) {
				$found_update = true;

				if ( 0 === $return ) {
					$message .= esc_html__( 'We successfully upgraded WordPress Core from SVN!', 'automatic-updater' );
					$message .= "<br><a href='http://core.trac.wordpress.org/log/'>http://core.trac.wordpress.org/log/</a>";
					$message .= "<br><br>$update";
				} else {
					$found_error = true;

					$message .= esc_html__( 'While upgrading WordPress Core from SVN, we ran into the following error:', 'automatic-updater' );
					$message .= "<br><br>$update<br><br>";
					$message .= esc_html__( "We're sorry it didn't work out. Please try upgrading manually, instead.", 'automatic-updater' );
				}
			}
		}

		if ( ! empty( $source_control['plugins'] ) && ! empty( $this->options['svn']['plugins'] ) ) {
			$plugin_message = '';

			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

			$plugin_upgrades = 0;
			foreach ( $this->options['svn']['plugins'] as $id => $type ) {
				// We only support SVN at the moment
				if ( 'svn' !== $type )
					continue;

				// Check that this plugin is still under source control
				if ( ! array_key_exists( $id, $source_control['plugins'] ) )
					continue;

				$plugin = get_plugin_data( WP_PLUGIN_DIR . '/' . $id );

				$output[] = '';
				$output[] = "{$plugin['Name']} ($id):";

				exec( 'svn up ' . WP_PLUGIN_DIR . '/' . plugin_dir_path( $id ), $output, $return );

				$update = end( $output );

				if ( 0 !== strpos( $update, "At revision" ) ) {
					$plugin_upgrades++;
					$found_update = true;

					if ( 0 !== $return )
						$found_error = true;

					$plugin_message .= "{$plugin['Name']}: $update<br>";
				}
			}

			if ( ! empty( $plugin_message ) ) {
				$message .= esc_html( _n( 'We upgraded the following plugin:', 'We upgraded the following plugins:', $plugin_upgrades, 'automatic-updater' ) );
				$message .= "<br><br>$plugin_message";
			}
		}

		if ( ! empty( $source_control['themes'] ) && ! empty( $this->options['svn']['themes'] ) ) {
			$theme_message = '';

			$theme_upgrades = 0;
			foreach ( $this->options['svn']['themes'] as $id => $type ) {
				// We only support SVN at the moment
				if ( 'svn' !== $type )
					continue;

				// Check that this theme is still under source control
				if ( ! array_key_exists( $id, $source_control['themes'] ) )
					continue;

				$theme = wp_get_theme( $id );

				$output[] = '';
				$output[] = "{$theme->name} ($id):";

				exec( 'svn up ' . $theme->get_stylesheet_directory(), $output, $return );

				$update = end( $output );

				if ( 0 !== strpos( $update, "At revision" ) ) {
					$theme_upgrades++;
					$found_update = true;

					if ( 0 !== $return )
						$found_error = true;

					$theme_message .= "{$theme->name}: $update<br>";
				}
			}

			if ( ! empty( $theme_message ) ) {
				$message .= esc_html( _n( 'We upgraded the following theme:', 'We upgraded the following themes:', $theme_upgrades, 'automatic-updater' ) );
				$message .= "<br><br>$theme_message";
			}
		}

		// No need to email if there were no updates.
		if ( ! $found_update )
			return;

		// If we're only sending emails on failure, check if any errors were found
		if( ! $found_error && ! $this->options['svn-success-email'] )
			return;

		$message .= '<br>';

		$debug = join( "<br>\n", $output );

		$this->notification( $message, $debug );
	}

	function notification( $info = '', $debug = '', $upgrade_failed = false ) {
		if ( $this->options['disable-email'] )
			return;

		$site = get_home_url();
		$subject = sprintf( esc_html__( 'WordPress Update: %s', 'automatic-updater' ), $site );

		$message = '<html>';
		$message .= '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
		$message .= '<body>';

		$message .= esc_html__( 'Howdy!', 'automatic-updater' );
		$message .= '<br><br>';
		$message .= wp_kses( sprintf( __( 'Automatic Updater just ran on your site, <a href="%1$s">%1$s</a>, with the following result:', 'automatic-updater' ), $site ), array( 'a' => array( 'href' => array() ) ) );
		$message .= '<br><br>';

		$message .= $info;

		$message .= '<br>';

		if ( $upgrade_failed ) {
			$message .= esc_html__( 'It looks like something went wrong during the update. Note that, if Automatic Updater continues to encounter problems, it will stop trying to do this update, and will not try again until after you manually update.', 'automatic-updater' );
			$message .= '<br><br>';
		}

		$message .= esc_html__( 'Thanks for using the Automatic Updater plugin!', 'automatic-updater' );

		if ( ! empty( $this->options['debug'] ) ) {
			$message .= "<br><br><br>";
			$message .= esc_html__( 'Debug Information:', 'automatic-updater' );
			$message .= "<br><br>$debug";
		}

		$message .= '</body></html>';

		$email = get_option( 'admin_email' );
		if ( ! empty( $this->options['override-email'] ) )
			$email = $this->options['override-email'];

		$email = apply_filters( 'auto_updater_notification_email_address', $email );

		$headers = array(
						'MIME-Version: 1.0',
						'Content-Type: text/html; charset=UTF-8'
					);

		add_filter( 'wp_mail_content_type', array( &$this, 'wp_mail_content_type' ) );
		wp_mail( $email, $subject, $message, $headers );
		remove_filter( 'wp_mail_content_type', array( &$this, 'wp_mail_content_type' ) );
	}

	function wp_mail_content_type() {
		return 'text/html';
	}

	function get_update_data() {
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

	function under_source_control( $types = array( 'svn' ) ) {
		$return = array(
					'core'    => false,
					'plugins' => array(),
					'themes'  => array()
		);

		$supported_checks = array( 'svn', 'git' );
		foreach ( $types as $id => $type )
			if ( ! in_array( $type, $supported_checks ) )
				unset( $types[ $id ] );

		if ( empty( $types ) )
			return $return;

		include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		$plugins = get_plugins();
		$themes  = wp_get_themes();

		foreach ( $types as $type ) {
			if ( is_dir( ABSPATH . "/.$type" ) )
				$return['core'] = $type;

			foreach ( $plugins as $id => $plugin )
				if ( plugin_dir_path( $id ) != './' && is_dir( WP_PLUGIN_DIR . '/' . plugin_dir_path( $id ) . ".$type" ) )
					$return['plugins'][ $id ] = array(
													'type'   => $type,
													'plugin' => $plugin
					);

			foreach ( $themes as $id => $theme )
				if ( is_dir( $theme->get_stylesheet_directory() . "/.$type" ) )
					$return['themes'][ $id ] = array(
													'type'  => $type,
													'theme' => $theme
					);
		}

		return $return;
	}
}

add_action( 'init', array( 'Automatic_Updater', 'init' ) );
