<?php

include ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

class Auto_Updater_Skin extends WP_Upgrader_Skin {
	var $messages = array();

	function __construct( $args = array() ) {
		parent::__construct( $args );
	}

	function feedback( $data ) {
		if ( is_wp_error( $data ) )
			$string = $data->get_error_message();
		else if ( is_array( $data ) ) {
			return;
		}
		else
			$string = $data;

		if ( ! empty( $this->upgrader->strings[$string] ) )
			$string = $this->upgrader->strings[$string];

		if ( strpos($string, '%') !== false ) {
			$args = func_get_args();
			$args = array_splice( $args, 1 );
			if ( ! empty( $args ) )
				$string = vsprintf( $string, $args );
		}

		if ( empty( $string ) )
			return;

		$this->messages[] = $string;
	}

	function header() {}
	function footer() {}
	function bulk_header() {}
	function bulk_footer() {}
	function before() {}
	function after() {}
}
