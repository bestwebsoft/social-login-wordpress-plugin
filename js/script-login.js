;( function( $ ) {
	$( document ).ready( function() {
		var form = $( '#loginform, #registerform' ),
			buttons = $( '.scllgn_login_button' ),
			position;
		form.append( '<div style="clear:both;"></div>' )

		buttons.each( function() {
			position = $( this ).data( 'position' );
			if ( 'top' == position ) {
				$( this ).prependTo( form );
			} else if ( 'bottom' == position ) {
				$( this ).appendTo( form );
			}
		} );
	} );
} )( jQuery );