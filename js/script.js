( function( $ ) {
	$( document ).ready( function() {
		var providers = ['google', 'facebook', 'twitter', 'linkedin'];

		$( '.scllgn_provider_checkbox' ).on( 'change', function() {
			for ( var i = 0; i < providers.length; i++ ) {
				if ( $( 'input[name="scllgn_' + providers[i] + '_is_enabled"]' ).is( ':checked' ) ) {
					$( '.scllgn_' + providers[i] + '_client_data input, .scllgn_' + providers[i] + '_forms input' ).removeAttr( 'disabled' ).filter( '.scllgn_' + providers[i] + '_client_data input' ).attr( 'required', 'required' );
					$( '.scllgn_' + providers[i] + '_client_data, .scllgn_' + providers[i] + '_forms' ).show();
				} else {
					$( '.scllgn_' + providers[i] + '_client_data, .scllgn_' + providers[i] + '_forms' ).hide();
					$( '.scllgn_' + providers[i] + '_client_data input, .scllgn_' + providers[i] + '_forms input' ).attr( 'disabled', 'disabled' ).filter( '.scllgn_' + providers[i] + '_client_data input' ).removeAttr( 'required' );
				}
			}
		} ).trigger( 'change' );
	} );
} )( jQuery );