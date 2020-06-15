;( function( $ ) {
	$( document ).ready( function() {
		var form = $( '#loginform, #registerform, #setupform' ),
			buttons = $( '.scllgn_login_button' ),
			position;

		form.append( '<div style="clear:both;"></div>' )

		buttons.each( function() {
			position = $( this ).data( 'scllgn-position' );
			if ( 'top' == position ) {
				$( '.scllgn_buttons_block' ).prependTo( form );
				$( '.scllgn_buttons_block' ).last().css( "margin-bottom", "30px" )
			} else if ( 'bottom' == position ) {
				$( '.scllgn_buttons_block' ).appendTo( form );
				$( '.scllgn_buttons_block' ).first().css( "margin-top", "30px" );
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
					if ( 'redirect_to' === tmp[0] ) {
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
						window.location.href = click_url;
					},
					error: function() {
						window.location.reload();
					}
				} );
			}
		} );

	} );
} )( jQuery );
