;( function( $ ) {
	$( document ).ready( function() {

		/* Hide/Show Buttons Position settings block */
		function scllgn_toggle_position() {
			if (
				$( '.scllgn_login_form_checkbox, .scllgn_register_form_checkbox' ).filter( ':checked' ).length > 0 &&
				$( 'input[name="scllgn_google_is_enabled"]' ).is( ':checked' )
			) {
				$( '.scllgn-position-table' ).show();
				$( '.scllgn-position-table select[name="scllgn_loginform_buttons_position"]' ).removeAttr( 'disabled' );
			} else {
				$( '.scllgn-position-table' ).hide();
				$( '.scllgn-position-table select[name="scllgn_loginform_buttons_position"]' ).attr( 'disabled', 'disabled' );
			}
		}

		/* Hide/Show provider settings block */
		function scllgn_google_fields() {
			if ( $( 'input[name="scllgn_google_is_enabled"]' ).is( ':checked' ) ) {
				$( '.scllgn_google_client_data input, .scllgn_google_forms input' ).removeAttr( 'disabled' ).filter( '.scllgn_google_client_data input' ).attr( 'required', 'required' );
				$( '.scllgn_google_client_data, .scllgn_google_forms' ).show();
			} else {
				$( '.scllgn_google_client_data, .scllgn_google_forms' ).hide();
				$( '.scllgn_google_client_data input, .scllgn_google_forms input' ).attr( 'disabled', 'disabled' ).filter( '.scllgn_google_client_data input' ).removeAttr( 'required' );
			}
			scllgn_toggle_position();
		}

		scllgn_google_fields();

		$( 'input[name="scllgn_google_is_enabled"]' ).on( 'change', scllgn_google_fields );
		$( '.scllgn_login_form_checkbox, .scllgn_register_form_checkbox' ).on( 'change', scllgn_toggle_position );
	} );
} )( jQuery );