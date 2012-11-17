<?php

function auto_updater_plugin_menu() {
	$hook = add_options_page( __( 'Automatic Updater', 'automatic-updater' ), __( 'Automatic Updater', 'automatic-updater' ), 'update_core', 'automatic-updater', 'auto_updater_settings' );

	add_action( "load-$hook", 'auto_updater_settings_loader' );
}
add_action( 'admin_menu', 'auto_updater_plugin_menu' );

function auto_updater_settings_loader() {
	get_current_screen()->add_help_tab( array(
	'id'		=> 'overview',
	'title'		=> __( 'Overview', 'automatic-updater' ),
	'content'	=>
		'<p>' . __( 'This settings page allows you to select whether you would like WordPress Core, your plugins, and your themes to be automatically updated.', 'automatic-updater' ) . '</p>' .
		'<p>' . __( 'It is very important to keep your WordPress installation up to date for security reasons, so unless you have a specific reason not to, we recommend allowing everything to automatically update.', 'automatic-updater' ) . '</p>'
	) );

	get_current_screen()->set_help_sidebar(
		'<p><strong>' . sprintf( __( 'A Plugin By <a href="%s" target="_blank">Gary</a>', 'automatic-updater' ), 'http://pento.net/' ) . '</strong></p>' .
		'<p><a href="http://pento.net/donate/">' . __( 'Donations', 'automatic-updater' ) . '</a></p>' .
		'<p><a href="http://wordpress.org/support/plugin/automatic-updater">' . __( 'Support Forums', 'automatic-updater' ) . '</a></p>'
	);

	wp_enqueue_style( 'automatic-updater-admin', plugins_url( 'css/admin.css', __FILE__ ) );
}

function auto_updater_settings() {
	if ( ! current_user_can( 'update_core' ) )
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'automatic-updater' ) );

	$message = '';
	if ( ! empty( $_REQUEST['submit'] ) ) {
		check_admin_referer( 'automatic-updater-settings' );

		auto_updater_save_settings();
		$message = __( 'Settings updated', 'automatic-updater' );
	}
	$options = get_option( 'automatic-updater' );
	$messages = array(
					'core' => __( 'Update WordPress Core automatically? <strong>(Strongly Recommended)</strong>', 'automatic-updater' ),
					'plugins' => __( 'Update your plugins automatically?', 'automatic-updater' ),
					'themes' => __( 'Update your themes automatically?', 'automatic-updater' )
				);
?>
	<div class="wrap">
		<?php screen_icon('tools'); ?>
		<h2><?php _e( 'Automatic Updater', 'automatic-updater' ); ?></h2>
<?php
	if ( ! empty( $message ) ) {
?>
		<div class="updated">
			<p><?php echo $message; ?></p>
		</div>
<?php
	}
?>
		<form method="post">
		<?php wp_nonce_field( 'automatic-updater-settings' ); ?>
<?php
	foreach ( $options['update'] as $type => $enabled ) {
		$checked = '';
		if ( $enabled )
			$checked = ' checked="checked"';

		echo "<p><input type='checkbox' id='$type' name='$type' value='1'$checked> <label for='$type'>{$messages[$type]}</label></p>";
	}
?>
	<br>
	<h3><?php _e( 'Contact', 'automatic-updater' ); ?></h3>
	<p><?php _e( 'By default, Automatic Updater will send an email to the Site Admin when an update is performed. If you would like to send that email to a different address, you can set it here.', 'automatic-updater' ); ?></p>
	<p><label for="override-email"><?php _e( 'Override Email Address', 'automatic-updater' ); ?></label> <input type="text" name="override-email" id="override-email" value="<?php echo esc_attr( $options['override-email'] ); ?>"></p>
<?php
	if ( is_dir( ABSPATH . '/.svn' ) ) {
		$checked = '';
		if ( $options['svn'] )
			$checked = ' checked="checked"';
?>
	<br>
	<h3><?php _e( 'SVN Support', 'automatic-updater' ); ?></h3>
	<p><?php _e( "It looks like you're running an SVN version of WordPress, that's cool! Automatic Updater can run <tt>svn up</tt> once an hour, to keep you up-to-date. For safety, enabling this option will disable the normal WordPress core updates.", 'automatic-updater' ) ?></p>
<?php
	if ( !is_writable( ABSPATH . '/.svn' ) ) {
		$uid = posix_getuid();
		$user = posix_getpwuid( $uid );
		echo '<div class="automatic-updater-notice"><p>' . sprintf( __( "The .svn directory isn't writable, so <tt>svn up</tt> will probably fail when the web server runs it. You need to give the user <tt>%1s</tt> write permissions to your entire WordPress install, including .svn directories.", 'automatic-updater' ), $user['name'] ) . '</p></div>';
	}
?>
	<p><input type="checkbox" id="svn" name="svn" value="1"<?php echo $checked; ?>> <label for="svn"><?php _e( 'Run <tt>svn up</tt> hourly?', 'automatic-updater' ); ?></label></p>
<?php
	}
	else {
		echo '<input type="hidden" name="svn" value="0">';
	}

	$checked = '';
	if ( $options['debug'] )
		$checked = ' checked="checked"';
?>
		<br>
		<h3><?php _e( 'Debug Information', 'automatic-updater' ); ?></h3>
		<p><input type="checkbox" id="debug" name="debug" value="1"<?php echo $checked; ?>> <label for="debug"><?php _e( 'Show debug information in the notification email.', 'automatic-updater' ); ?></label></p>
		<p><input class="button button-primary" type="submit" name="submit" id="submit" value="<?php esc_attr_e( 'Save Changes', 'automatic-updater' ); ?>" /></p>
		</form>
	</div>
<?php
}

function auto_updater_save_settings() {
	$options = get_option( 'automatic-updater' );

	$types = array( 'core', 'plugins', 'themes' );
	foreach ( $types as $type ) {
		if ( ! empty( $_REQUEST[$type] ) )
			$options['update'][$type] = true;
		else
			$options['update'][$type] = false;
	}

	$top_options = array( 'debug', 'svn', 'override-email' );
	foreach ( $top_options as $option ) {
		if ( ! empty( $_REQUEST[$option] ) )
			$options[$option] = true;
		else
			$options[$option] = false;
	}

	update_option( 'automatic-updater', $options );
}

function auto_updater_plugin_row_meta( $links, $file ) {
	if( AUTOMATIC_UPDATER_BASENAME == $file ) {
		$links[] = '<a href="options-general.php?page=automatic-updater">' . __( 'Settings', 'automatic-updater' ) . '</a>';
	}

	return $links;
}
add_filter( 'plugin_row_meta', 'auto_updater_plugin_row_meta', 10, 2 );
