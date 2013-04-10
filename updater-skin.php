<?php

include_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

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

		if ( ! empty( $this->upgrader->strings[ $string ] ) )
			$string = $this->upgrader->strings[ $string ];

		if ( strpos($string, '%') !== false ) {
			$args = func_get_args();
			$args = array_splice( $args, 1 );
			if ( ! empty( $args ) )
				$string = vsprintf( $string, $args );
		}

		$string = trim( $string );

		// Only allow basic HTML in the messages
		$string = wp_kses( $string, array( 'a' => array( 'href' => array() ), 'br' => array(), 'em' => array(), 'strong' => array() ) );

		if ( empty( $string ) )
			return;

		$this->messages[] = $string;
	}

	function header() {
		ob_start();
	}

	function footer() {
		$output = ob_get_contents();
		if ( ! empty( $output ) )
			$this->feedback( $output );
		ob_end_clean();
	}

	function bulk_header() {}
	function bulk_footer() {}
	function before() {}
	function after() {}
}
