var automaticUpdaterAdmin;

( function( $ ) {

automaticUpdaterAdmin = {

	load: function() {
		$( document ).on( 'click', '#automatic-updater-hide-connection-warning', this.hideConnectionWarning );
	},

	hideConnectionWarning: function( e ) {
		e.preventDefault();
		e.stopPropagation();

		$.ajax( ajaxurl, {
			data: {
				action:      'automatic-updater-hide-connection-warning',
				_ajax_nonce: automaticUpdaterSettings.nonce
			}
		} );

		$( e.target ).closest( '.updated' ).slideUp( 'fast' );
	}
};

} )( jQuery );

automaticUpdaterAdmin.load();
