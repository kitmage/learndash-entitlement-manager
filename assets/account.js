( function () {
	'use strict';

	function init() {
		document.querySelectorAll( '.kitmage-lde-copy' ).forEach( function ( button ) {
			button.hidden = false;
			button.addEventListener( 'click', function () {
				var container = button.closest( '.kitmage-lde-enrollment-link' );
				var input = container.querySelector( 'input' );
				var status = container.querySelector( '.kitmage-lde-copy-status' );
				status.textContent = '';

				function complete( copied ) {
					status.textContent = copied ? button.dataset.copied : button.dataset.failed;
					if ( copied ) { button.focus(); }
				}

				function fallback() {
					input.focus();
					input.select();
					var copied = false;
					try { copied = document.execCommand( 'copy' ); } catch ( error ) { /* Keep the link selected for manual copying. */ }
					complete( copied );
				}

				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( input.value ).then( function () { complete( true ); }, fallback );
				} else {
					fallback();
				}
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
