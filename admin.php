<?php

class Automatic_Updater_Admin {
	private $automatic_updater;

	function init( $automatic_updater ) {
		static $instance = false;

		if( ! $instance )
			$instance = new Automatic_Updater_Admin( $automatic_updater );

		return $instance;
	}

	function __construct( $automatic_updater ) {
		$this->automatic_updater = $automatic_updater;

		if ( is_multisite() )
			add_action( 'network_admin_menu', array( &$this, 'plugin_menu' ) );
		else
			add_action( 'admin_menu', array( &$this, 'plugin_menu' ) );

		add_filter( 'plugin_action_links_' . Automatic_Updater::$basename, array( &$this, 'plugin_row_links' ) );
		add_filter( 'network_admin_plugin_action_links_' . Automatic_Updater::$basename, array( &$this, 'plugin_row_links' ) );
	}

	function plugin_menu() {
		$slug = 'options-general.php';
		if ( is_multisite() )
			$slug = 'settings.php';

		$hook = add_submenu_page( $slug, esc_html__( 'Automatic Updater', 'automatic-updater' ), esc_html__( 'Automatic Updater', 'automatic-updater' ), 'update_core', 'automatic-updater', array( &$this, 'settings' ) );

		add_action( "load-$hook", array( &$this, 'settings_loader' ) );
	}

	function settings_loader() {
		get_current_screen()->add_help_tab( array(
		'id'		=> 'overview',
		'title'		=> esc_html__( 'Overview', 'automatic-updater' ),
		'content'	=>
			'<p>' . esc_html__( 'This settings page allows you to select whether you would like WordPress Core, your plugins, and your themes to be automatically updated.', 'automatic-updater' ) . '</p>' .
			'<p>' . esc_html__( 'It is very important to keep your WordPress installation up to date for security reasons, so unless you have a specific reason not to, we recommend allowing everything to automatically update.', 'automatic-updater' ) . '</p>'
		) );

		get_current_screen()->set_help_sidebar(
			'<p><strong>' . wp_kses( sprintf( __( 'A Plugin By <a href="%s" target="_blank">Gary</a>', 'automatic-updater' ), 'http://pento.net/' ), array( 'a' => array( 'href' => array(), 'target' => array() ) ) ) . '</strong></p>' .
			'<p><a href="http://pento.net/donate/">' . esc_html__( 'Donations', 'automatic-updater' ) . '</a></p>' .
			'<p><a href="http://wordpress.org/support/plugin/automatic-updater">' . esc_html__( 'Support Forums', 'automatic-updater' ) . '</a></p>'
		);

		wp_enqueue_style( 'automatic-updater-admin', plugins_url( 'css/admin.css', __FILE__ ) );
	}

	function settings() {
		if ( ! current_user_can( 'update_core' ) )
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'automatic-updater' ) );

		$message = '';
		if ( ! empty( $_REQUEST['submit'] ) ) {
			check_admin_referer( 'automatic-updater-settings' );

			$this->save_settings();
			$message = esc_html__( 'Settings updated', 'automatic-updater' );
		}

		$messages = array(
						'core' => wp_kses( __( 'Update WordPress Core automatically? <strong>(Strongly Recommended)</strong>', 'automatic-updater' ), array( 'strong' => array() ) ),
						'plugins' => esc_html__( 'Update your plugins automatically?', 'automatic-updater' ),
						'themes' => esc_html__( 'Update your themes automatically?', 'automatic-updater' )
					);
		?>
		<div class="wrap">
			<?php screen_icon( 'tools' ); ?>
			<h2><?php esc_html_e( 'Automatic Updater', 'automatic-updater' ); ?></h2>

			<?php if ( ! empty( $message ) ) { ?>
				<div class="updated">
					<p><?php echo $message; ?></p>
				</div>
			<?php } ?>

			<form method="post">
				<?php wp_nonce_field( 'automatic-updater-settings' ); ?>

				<?php
				foreach ( $this->automatic_updater->get_option( 'update' ) as $type => $enabled ) {
					$checked = '';
					if ( $enabled )
						$checked = ' checked="checked"';

					echo "<p><input type='checkbox' id='$type' name='$type' value='1'$checked> <label for='$type'>{$messages[ $type ]}</label></p>";
				}
				?>

				<br>
				<h3><?php esc_html_e( 'Notification Email', 'automatic-updater' ); ?></h3>
				<p><?php esc_html_e( 'By default, Automatic Updater will send an email to the Site Admin when an update is performed. If you would like to send that email to a different address, you can set it here.', 'automatic-updater' ); ?></p>
				<p><label for="override-email"><?php esc_html_e( 'Override Email Address', 'automatic-updater' ); ?>:</label> <input type="text" name="override-email" id="override-email" value="<?php echo esc_attr( $this->automatic_updater->get_option( 'override-email' ) ); ?>"></p>

				<?php
				$checked = '';
				if ( $this->automatic_updater->get_option( 'disable-email' ) )
					$checked = ' checked="checked"';
				?>

				<p><?php esc_html_e( "If you don't want to receive an email when updates are installed, you can disable them completely.", 'automatic-updater' ); ?></p>
				<p><input type="checkbox" name="disable-email" id="disable-email" value="1"> <label for="disable-email"><?php esc_html_e( 'Disable email notifications.', 'automatic-updater' ); ?></label></p>

				<br>
				<h3><?php esc_html_e( 'Retries', 'automatic-updater' ); ?></h3>
				<p><?php esc_html_e( 'To avoid updating when something is broken, Automatic Updater can limit the number of times it will attempt to update WordPress Core, a plugin or a theme. If, for example, a plugin update reaches this limit, Automatic Updater will stop trying to update it until you manually install the update, or a new version is released.', 'automatic-updater' ); ?></p>
				<p><label for="retries-limit"><?php esc_html_e( 'Retries Limit', 'automatic-updater' ); ?>:</label> <input type="number" step="1" name="retries-limit" id="retries-limit" class="small-text" value="<?php echo esc_attr( $this->automatic_updater->get_option( 'retries-limit' ) ); ?>"></p>

				<?php
				$source_control = $this->automatic_updater->under_source_control();
				if ( $source_control['core'] || ! empty( $source_control['plugins'] ) || ! empty( $source_control['themes'] ) ) {
					$svn_options = $this->automatic_updater->get_option( 'svn' );
					$writable_error = false;
				?>

					<br>
					<h3><?php esc_html_e( 'SVN Support', 'automatic-updater' ); ?></h3>
					<?php
					if ( $source_control['core'] ) {
						$svn_core_checked = '';
						if ( $svn_options['core'] )
							$svn_core_checked = ' checked="checked"';

						if ( ! is_writable( ABSPATH . '/.svn' ) )
							$writable_error = true;
					?>
						<h4><?php esc_html_e( 'WordPress Core', 'automatic-updater' ); ?></h4>
						<p><?php echo wp_kses( __( "It looks like you're running an SVN version of WordPress, that's cool! Automatic Updater can run <tt>svn up</tt> once an hour, to keep you up-to-date. For safety, enabling this option will disable the normal WordPress core updates.", 'automatic-updater' ), array( 'tt' => array() ) ); ?></p>
					<p><input type="checkbox" id="svn-core" name="svn-core" value="1"<?php echo $svn_core_checked; ?>> <label for="svn-core"><?php esc_html_e( 'Update WordPress Core hourly?', 'automatic-updater' ); ?></label></p>

					<?php
					}

					if ( ! empty( $source_control['plugins'] ) ) {
					?>
						<h4><?php esc_html_e( 'Plugins', 'automatic-updater' ); ?></h4>
						<p><?php esc_html_e( "Running plugins from SVN is great for helping plugin devs fine tune them before release, so on behalf of all of us, thanks! If you see Akismet here and don't have it coming from a custom repository, it will probably automatically update when the WordPress Core SVN update occurs.", 'automatic-updater' ); ?></p>
					<?php
						foreach ( $source_control['plugins'] as $id => $plugin ) {
							if ( ! is_writable( WP_PLUGIN_DIR . '/' . plugin_dir_path( $id ) . '/.svn' ) )
								$writable_error = true;

							$checked = '';
							if ( array_key_exists( $id, $svn_options['plugins'] ) )
								$checked = ' checked="checked"';
							echo "<input type='checkbox' name='svn-plugins[]' id='$id' value='$id'$checked /> <label for='$id'>{$plugin['plugin']['Name']} ($id)</label><br/>";
						}
					}

					if ( ! empty( $source_control['themes'] ) ) {
					?>
						<h4><?php esc_html_e( 'Themes', 'automatic-updater' ); ?></h4>
						<p><?php esc_html_e( "Running themes from SVN makes you an excellent person who makes the WordPress community better - thank you! If you see any of the default Twenty Ten, Eleven or Twelve themes, these will probably automatically update when the WordPress Core SVN update occurs.", 'automatic-updater' ); ?></p>
					<?php
						foreach ( $source_control['themes'] as $id => $theme ) {
							if ( ! is_writable( $theme['theme']->get_stylesheet_directory() . '/.svn' ) )
								$writable_error = true;

							$checked = '';
							if ( array_key_exists( $id, $svn_options['themes'] ) )
								$checked = ' checked="checked"';
							echo "<input type='checkbox' name='svn-themes[]' id='$id' value='$id'$checked /> <label for='$id'>{$theme['theme']->name} ($id)</label><br/>";
						}
					}

					if ( $writable_error ) {
						$uid = posix_getuid();
						$user = posix_getpwuid( $uid );
						echo '<div class="automatic-updater-notice"><p>' . wp_kses( sprintf( __( "The items marked in red don't have their .svn directory writable, so <tt>svn up</tt> will probably fail when the web server runs it. You need to give the user <tt>%s</tt> write permissions to your entire WordPress install, including .svn directories.", 'automatic-updater' ), $user['name'] ), array( 'tt' => array() ) ) . '</p></div>';
					}

					$svn_success_email_checked = '';
					if ( $this->automatic_updater->get_option( 'svn-success-email' ) )
						$svn_success_email_checked = ' checked="checked"';
					?>
					<h4><?php esc_html_e( 'SVN Options', 'automatic-updater' ); ?></h4>
					<p><input type="checkbox" id="svn-success-email" name="svn-success-email" value="1"<?php echo $svn_success_email_checked; ?>> <label for="svn-success-email"><?php echo wp_kses( __( 'Send email on <tt>svn up</tt> success? Disabling this will cause notification emails to only be sent if the <tt>svn up</tt> fails.', 'automatic-updater' ), array( 'tt' => array() ) ); ?></label></p>

				<?php
				} else {
				?>
					<input type="hidden" name="svn-core" value="0">
					<input type="hidden" name="svn-plugins" value="0">
					<input type="hidden" name="svn-themes" value="0">
					<input type="hidden" name="svn-success-email" value="0">
				<?php
				}

				$checked = '';
				if ( $this->automatic_updater->get_option( 'debug' ) )
					$checked = ' checked="checked"';
				?>

				<br>
				<h3><?php esc_html_e( 'Debug Information', 'automatic-updater' ); ?></h3>
				<p><input type="checkbox" id="debug" name="debug" value="1"<?php echo $checked; ?>> <label for="debug"><?php esc_html_e( 'Show debug information in the notification email.', 'automatic-updater' ); ?></label></p>
				<p><input class="button button-primary" type="submit" name="submit" id="submit" value="<?php esc_attr_e( 'Save Changes', 'automatic-updater' ); ?>" /></p>
			</form>
		</div>
	<?php
	}

	function save_settings() {
		$types = array( 'core', 'plugins', 'themes' );
		$update = array();
		foreach ( $types as $type ) {
			if ( ! empty( $_REQUEST[ $type ] ) )
				$update[ $type ] = true;
			else
				$update[ $type ] = false;
		}
		$this->automatic_updater->update_option( 'update', $update );

		$top_bool_options = array( 'debug', 'disable-email' );
		foreach ( $top_bool_options as $option ) {
			if ( ! empty( $_REQUEST[ $option ] ) )
				$this->automatic_updater->update_option( $option, true );
			else
				$this->automatic_updater->update_option( $option, false );
		}

		$top_options = array( 'override-email', 'retries-limit' );
		foreach ( $top_options as $option )
			$this->automatic_updater->update_option( $option, $_REQUEST[ $option ] );

		$svn_options = array(
							'core'    => false,
							'plugins' => array(),
							'themes'  => array()
		);

		$source_control = $this->automatic_updater->under_source_control();

		if ( $source_control['core'] && ! empty( $_REQUEST['svn-core'] ) )
			$svn_options['core'] = true;

		if ( ! empty( $source_control['plugins'] ) && ! empty( $_REQUEST['svn-plugins'] ) && is_array( $_REQUEST['svn-plugins'] ) ) {
			foreach ( $_REQUEST['svn-plugins'] as $plugin ) {
				if ( array_key_exists( $plugin, $source_control['plugins'] ) )
					$svn_options['plugins'][ $plugin ] = $source_control['plugins'][ $plugin ]['type'];
			}
		}

		if ( ! empty( $source_control['themes'] ) && ! empty( $_REQUEST['svn-themes'] ) && is_array( $_REQUEST['svn-themes'] ) ) {
			foreach ( $_REQUEST['svn-themes'] as $theme ) {
				if ( array_key_exists( $theme, $source_control['themes'] ) )
					$svn_options['themes'][ $theme ] = $source_control['themes'][ $theme ]['type'];
			}
		}

		$this->automatic_updater->update_option( 'svn', $svn_options );
	}

	function plugin_row_links( $links ) {
		$url = admin_url( 'options-general.php?page=automatic-updater' );
		if ( is_multisite() )
			$url = network_admin_url( 'settings.php?page=automatic-updater' );

		array_unshift( $links, "<a href='$url'>" . esc_html__( 'Settings', 'automatic-updater' ) . '</a>' );

		return $links;
	}
}
