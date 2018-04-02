function scllgn_user_registration_default() {
	(function($) {
		$( "#scllgn_allow_user_registration_notice" ).css( 'display', 'none' );
		$( "#scllgn_deny_user_registration_notice" ).css( 'display', 'none' );
	})(jQuery);
}
function scllgn_allow_user_registration_setting_notice() {
	(function($) {
		$( "#scllgn_allow_user_registration_notice" ).css( 'display', 'block' );
		$( "#scllgn_deny_user_registration_notice" ).css( 'display', 'none' );
	})(jQuery);
}
function scllgn_deny_user_registration_setting_notice() {
	(function($) {
		$( "#scllgn_allow_user_registration_notice" ).css( 'display', 'none' );
		$( "#scllgn_deny_user_registration_notice" ).css( 'display', 'block' );
	})(jQuery);
}
( function( $ ) {
	$( document ).ready( function() {

		/**
		 * add notice about User Registration
		 */
		$( ".scllgn_registration_default" ).click( function() {
			scllgn_user_registration_default();
		});
		$( ".scllgn_allow_registration" ).click( function() {
			scllgn_allow_user_registration_setting_notice();
		});
		$( ".scllgn_deny_registration" ).click( function() {
			scllgn_deny_user_registration_setting_notice();
		});

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