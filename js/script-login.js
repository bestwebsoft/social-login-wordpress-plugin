;( function( $ ) {
	$( document ).ready( function() {
		var form = $( '#loginform, #registerform, #setupform' ),
			buttons = $( '.scllgn_login_button' ),
			position;

		form.append( '<div style="clear:both;"></div>' )

		buttons.each( function() {
			position = $( this ).data( 'scllgn-position' );
			if ( 'top' == position ) {
				$( this ).prependTo( form );
			} else if ( 'bottom' == position ) {
				$( this ).appendTo( form );
			}
		} );

		/* Remember me and redirect functionality */
		$( '#scllgn_google_button, #scllgn_facebook_button, #scllgn_twitter_button, #scllgn_linkedin_button' ).on( 'click', function( event ) {
			event.preventDefault();
			var scllgn_url = window.location.search.substr( 1 );
			var redirect_url = null,
			click_url = $( this ).attr( 'href' ),
			tmp = [],
			provider = $( this ).data( 'scllgn-provider' );
			location.search
				.substr( 1 )
				.split( "&" )
				.forEach( function ( item ) {
					tmp = item.split( "=" );
					if ( tmp[0] === 'redirect_to' ) {
						redirect_url = decodeURIComponent( tmp[1] );
					}
				} );

			if ( ! redirect_url && ! scllgn_ajax.is_login_page  ) {
				redirect_url = window.location.href;
			}
			var remember_checked = $( '.forgetmenot input[name="rememberme"]' ).is( ':checked' );

			/* Get redirect URI */
			if ( click_url || redirect_url || remember_checked ) {
				ajax_data = {
					'action' : 'scllgn_remember',
					'scllgn_provider' : provider,
					'scllgn_nonce' : scllgn_ajax.scllgn_nonce
				};
				if ( redirect_url ) {
					ajax_data.scllgn_url = redirect_url;
				}
				if ( remember_checked ) {
					ajax_data.scllgn_remember = 'true';
				}

				$.ajax( {
					url 	: scllgn_ajax.ajaxurl,
					type 	: 'POST',
					data 	: ajax_data,
					success	: function( auth_url ) {
						if ( provider == 'google' && remember_checked ) {
							window.location.href = auth_url;
						} else {
							window.location.href = click_url;
						}
					}
				} );
			}
		} );
	} );
} )( jQuery );
