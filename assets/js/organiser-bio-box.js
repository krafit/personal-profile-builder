/**
 * Personal Profile Builder — organiser bio box copy button.
 *
 * Reveals and wires up the "Copy" button next to the avatar URL.
 * The button is rendered hidden so that it doesn't appear when JS
 * is unavailable; the URL field is `readonly` and selectable in
 * any case, so the page degrades gracefully.
 */
( function () {
	'use strict';

	function flash( button ) {
		const original = button.textContent;
		const copiedLabel = ( window.ppbBioBoxL10n && window.ppbBioBoxL10n.copied ) || 'Copied!';

		button.textContent = copiedLabel;
		window.setTimeout( function () {
			button.textContent = original;
		}, 1500 );
	}

	function copyText( text, button ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then(
				function () {
					flash( button );
				},
				function () {
					flash( button );
				}
			);

			return;
		}

		const helper = document.createElement( 'textarea' );

		helper.value = text;
		helper.setAttribute( 'readonly', '' );
		helper.style.position = 'absolute';
		helper.style.left = '-9999px';
		document.body.appendChild( helper );
		helper.select();

		try {
			document.execCommand( 'copy' );
			flash( button );
		} catch ( err ) {
			// Silent: the URL is still selectable in the visible input.
		}

		document.body.removeChild( helper );
	}

	function initButton( button ) {
		button.hidden = false;
		button.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			const wrapper = button.closest( '.ppb-organiser-bio-box__avatar-url' );
			const input = wrapper ? wrapper.querySelector( '[data-ppb-bio-box-url]' ) : null;

			if ( ! input || ! input.value ) {
				return;
			}

			input.select();
			copyText( input.value, button );
		} );
	}

	function init() {
		const buttons = document.querySelectorAll( '[data-ppb-bio-box-copy]' );

		buttons.forEach( initButton );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
