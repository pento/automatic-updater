<?php
/*
 * Plugin Name: Advanced Automatic Updates
 * Plugin URI: http://pento.net/projects/automatic-updater-for-wordpress/
 * Description: Adds extra options to WordPress' built-in Automatic Updates feature.
 * Author: pento
 * Version: 1.0
 * Author URI: http://pento.net/
 * License: GPL2+
 * Text Domain: automatic-updater
 * Domain Path: /languages/
 */

// Don't allow the plugin to be loaded directly
if ( ! function_exists( 'add_action' ) ) {
	echo "Please enable this plugin from your wp-admin.";
	exit;
}

$automatic_updater_file = __FILE__;

if ( isset( $plugin ) )
	$automatic_updater_file = $plugin;
else if ( isset( $mu_plugin ) )
	$automatic_updater_file = $mu_plugin;
else if ( isset( $network_plugin ) )
	$automatic_updater_file = $network_plugin;

Automatic_Updater::$basename = plugin_basename( $automatic_updater_file );

class Automatic_Updater {
	private $options = array();
	private $options_serialized = '';

	public static $basename;

	static function init() {
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
		$this->options_serialized = serialize( $this->options );
		$this->plugin_upgrade();

		add_action( 'shutdown', array( $this, 'shutdown' ) );

		// Nothing else matters if we're on WPMS and not on the main site
		if ( is_multisite() && ! is_main_site() )
			return;

		if ( is_admin() ) {
			include_once( dirname( __FILE__ ) . '/admin.php' );
			Automatic_Updater_Admin::init( $this );
		}

		if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED )
			return;

		add_action( 'admin_init', array( $this, 'check_wordpress_version' ) );

		// Override the core auto update options. Do this at priority 1, so others can easily override them.
		if ( $this->options['update']['core']['major'] )
			add_filter( 'allow_major_auto_core_updates', '__return_true', 1 );
		else
			add_filter( 'allow_major_auto_core_updates', '__return_false', 1 );

		if ( $this->options['update']['core']['minor'] )
			add_filter( 'allow_minor_auto_core_updates', '__return_true', 1 );
		else
			add_filter( 'allow_minor_auto_core_updates', '__return_false', 1 );

		if ( $this->options['update']['plugins'] )
			add_filter( 'auto_update_plugin', '__return_true', 1 );
		else
			add_filter( 'auto_update_plugin', '__return_false', 1 );

		if ( $this->options['update']['themes'] )
			add_filter( 'auto_update_theme', '__return_true', 1 );
		else
			add_filter( 'auto_update_theme', '__return_false', 1 );

		if ( $this->options['disable-email'] )
			add_filter( 'auto_core_update_send_email', '__return_false', 1 );
		else
			add_filter( 'auto_core_update_send_email', '__return_true', 1 );

		if ( ! empty( $this->options['override-email'] ) ) {
			add_filter( 'auto_core_update_email', array( $this, 'override_update_email' ), 1, 1 );
			add_filter( 'auto_update_debug_email', array( $this, 'override_update_email' ), 1, 1 );
		}

		// Default is to send the debug email with dev builds, so we don't need to filter for that
		if ( 'always' === $this->options['debug'] )
			add_filter( 'automatic_updates_send_debug_email', '__return_true', 1 );
		else if ( 'never' === $this->options['debug'] )
			add_filter( 'automatic_updates_send_debug_email', '__return_false', 1 );

		// Configure SVN updates cron, if it's enabled
		if ( $this->options['svn']['core'] || ! empty( $this->options['svn']['plugins'] ) || ! empty( $this->options['svn']['themes'] ) ) {
			if ( ! wp_next_scheduled( 'auto_updater_svn_event' ) )
				wp_schedule_event( time(), 'hourly', 'auto_updater_svn_event' );
		} else {
			$timestamp = wp_next_scheduled( 'auto_updater_svn_event' );
			if ( $timestamp )
				wp_unschedule_event( $timestamp, 'auto_updater_svn_event' );
		}
	}

	function shutdown() {
		// No need to write to the DB if the options haven't changed
		if ( serialize( $this->options ) === $this->options_serialized )
			return;

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
		if ( version_compare( $GLOBALS['wp_version'], '3.7', '<' ) ) {
			if ( is_plugin_active( self::$basename ) ) {
				deactivate_plugins( self::$basename );
				add_action( 'admin_notices', array( $this, 'disabled_notice' ) );
				if ( isset( $_GET['activate'] ) )
					unset( $_GET['activate'] );
			}
		}
	}

	function disabled_notice() {
		echo '<div class="updated"><p><strong>' . esc_html__( 'Automatic Updater requires WordPress 3.7 or higher! Please upgrade WordPress manually, then reactivate Automatic Updater.', 'automatic-updater' ) . '</strong></p></div>';
	}

	function plugin_upgrade() {
		if ( empty( $this->options ) ) {
			// Don't automatically enable core updates in installs coming from a repo
			if ( ! class_exists( 'WP_Automatic_Updater' ) )
				include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$wpau = new WP_Automatic_Updater();
			$core_updates_enabled = $wpau->is_vcs_checkout( ABSPATH );

			$this->options = array(
						'update'                  => array(
														'core'    => array(
																		'minor' => $core_updates_enabled,
																		'major' => $core_updates_enabled,
														),
														'plugins' => false,
														'themes'  => false,
						),
						'svn'                     => array(
														'core'    => false,
														'plugins' => array(),
														'themes'  => array(),
						),
						'svn-success-email'       => true,
						'debug'                   => 'debug',
						'next-development-update' => time(),
						'override-email'          => '',
						'disable-email'           => false,
						'upgrade-after-3.7'       => true,
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

		// Ability to only send SVN update emails on failure added in 0.8
		if ( ! array_key_exists( 'svn-success-email', $this->options ) )
			$this->options['svn-success-email'] = true;

		// SVN support for themes and plugins added in 0.8
		if ( ! is_array( $this->options['svn'] ) ) {
			$this->options['svn'] =  array(
										'core'    => $this->options['svn'],
										'plugins' => array(),
										'themes'  => array(),
								);
		}

		if ( ! array_key_exists( 'upgrade-after-3.7', $this->options ) ) {
			$this->options['upgrade-after-3.7'] = true;
			// Core is handling upgrades now, so we should unschedule our old events
			foreach ( $this->options['update'] as $type => $update ) {
				$timestamp = wp_next_scheduled( "auto_updater_{$type}_event" );
				if ( $timestamp )
					wp_unschedule_event( $timestamp, "auto_updater_{$type}_event" );
			}
		}

		// Support for different types of core upgrades added in 1.0
		if ( ! is_array( $this->options['update']['core'] ) ) {
			$this->options['update']['core'] = array(
												'major' => $this->options['update']['core'],
												'minor' => $this->options['update']['core'],
			);
		}

		// debug option changed to send debug email under varying conditions in 1.0
		if ( is_bool( $this->options['debug'] ) ) {
			if ( $this->options['debug'] )
				$this->options['debug'] = 'always';
			else
				$this->options['debug'] = 'debug';
		}
	}

	function override_update_email( $email ) {
		$email['to'] = $this->options['override-email'];
		return $email;
	}

	function update_svn() {
		$output              = array();
		$return              = null;

		$message             = '';

		$found_error         = false;
		$found_update        = false;

		$found_core_update   = false;
		$found_plugin_update = false;

		$source_control = $this->under_source_control();

		if ( $source_control['core'] && ! empty( $this->options['svn']['core'] ) ) {
			$output[] = esc_html__( 'WordPress Core:', 'automatic-updater' );
			exec( 'svn up ' . ABSPATH, $output, $return );

			$update = trim( end( $output ) );

			if ( 0 === $return && ! empty( $update ) && 0 !== strpos( $update, "At revision" ) ) {
				$found_update = true;
				$found_core_update = true;

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

				$update = trim( end( $output ) );

				if ( 0 === $return && ! empty( $update ) && 0 !== strpos( $update, "At revision" ) ) {
					$plugin_upgrades++;
					$found_update = true;
					$found_plugin_update = true;

					if ( 0 !== $return )
						$found_error = true;

					$plugin_message .= "{$plugin['Name']}: $update<br>";
				}
			}

			if ( ! empty( $plugin_message ) ) {
				if ( $found_core_update )
					$message .= '<br><br>';
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

				$update = trim( end( $output ) );

				if ( 0 === $return && ! empty( $update ) && 0 !== strpos( $update, "At revision" ) ) {
					$theme_upgrades++;
					$found_update = true;

					if ( 0 !== $return )
						$found_error = true;

					$theme_message .= "{$theme->name}: $update<br>";
				}
			}

			if ( ! empty( $theme_message ) ) {
				if ( $found_core_update || $found_plugin_update )
					$message .= '<br><br>';
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

		add_filter( 'wp_mail_content_type', array( $this, 'wp_mail_content_type' ) );
		wp_mail( $email, $subject, $message, $headers );
		remove_filter( 'wp_mail_content_type', array( $this, 'wp_mail_content_type' ) );
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
			if ( ! empty( $update_wordpress ) && 'latest' !== $update_wordpress[0]->response )
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
				if ( plugin_dir_path( $id ) !== './' && is_dir( WP_PLUGIN_DIR . '/' . plugin_dir_path( $id ) . ".$type" ) ) {
					$return['plugins'][ $id ] = array(
													'type'   => $type,
													'plugin' => $plugin
					);
				}

			foreach ( $themes as $id => $theme ) {
				if ( is_dir( $theme->get_stylesheet_directory() . "/.$type" ) ) {
					$return['themes'][ $id ] = array(
													'type'  => $type,
													'theme' => $theme
					);
				}
			}
		}

		return $return;
	}
}

add_action( 'init', array( 'Automatic_Updater', 'init' ) );
