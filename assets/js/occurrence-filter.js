/**
 * Personal Profile Builder — front-end occurrence language filter.
 *
 * Toggles visibility of occurrence list items based on the selected
 * language pill. Filter state lives in the URL hash so a filtered
 * view is shareable; the hash is read on load and updated on each
 * pill click. Vanilla JS — no framework dependency.
 *
 * The PHP renderer ships every pill with `data-ppb-filter-value` and
 * every list item with `data-language`; the special pill value `*`
 * means "show all".
 */
( function () {
	'use strict';

	var HASH_PARAM = 'lang';

	/**
	 * Parse a `#lang=de_DE` (or similar) hash into the locale value.
	 *
	 * @returns {string|null} Locale value or null when not present.
	 */
	function readHashLang() {
		var hash = window.location.hash || '';

		if ( hash.charAt( 0 ) === '#' ) {
			hash = hash.slice( 1 );
		}

		if ( ! hash ) {
			return null;
		}

		var parts = hash.split( '&' );

		for ( var i = 0; i < parts.length; i++ ) {
			var pair = parts[ i ].split( '=' );

			if ( pair[ 0 ] === HASH_PARAM ) {
				return decodeURIComponent( pair[ 1 ] || '' );
			}
		}

		return null;
	}

	/**
	 * Write a value to the `lang` hash key, preserving other params.
	 * Passing `null` removes the param entirely.
	 *
	 * @param {string|null} value
	 */
	function writeHashLang( value ) {
		var hash = window.location.hash || '';

		if ( hash.charAt( 0 ) === '#' ) {
			hash = hash.slice( 1 );
		}

		var parts = hash ? hash.split( '&' ) : [];
		var out = [];

		for ( var i = 0; i < parts.length; i++ ) {
			var pair = parts[ i ].split( '=' );

			if ( pair[ 0 ] !== HASH_PARAM && pair[ 0 ] !== '' ) {
				out.push( parts[ i ] );
			}
		}

		if ( value !== null ) {
			out.push( HASH_PARAM + '=' + encodeURIComponent( value ) );
		}

		var nextHash = out.length > 0 ? '#' + out.join( '&' ) : ' ';

		if ( window.history && typeof window.history.replaceState === 'function' ) {
			window.history.replaceState( null, '', nextHash === ' ' ? window.location.pathname + window.location.search : nextHash );
		}
		else {
			window.location.hash = nextHash;
		}
	}

	/**
	 * Apply the active value to a single filter container.
	 *
	 * @param {HTMLElement} container Wrapper with `[data-ppb-occurrence-filter]`
	 * @param {string} value Locale code, '' for "Unspecified", or '*' for "All"
	 */
	function applyFilter( container, value ) {
		var list = container.querySelector( '[data-ppb-occurrence-list]' );
		var pills = container.querySelectorAll( '[data-ppb-filter-value]' );

		pills.forEach( function ( pill ) {
			var pillValue = pill.getAttribute( 'data-ppb-filter-value' );

			if ( pillValue === value ) {
				pill.setAttribute( 'data-active', 'true' );
			}
			else {
				pill.removeAttribute( 'data-active' );
			}
		} );

		if ( ! list ) {
			return;
		}

		var items = list.querySelectorAll( '[data-language]' );

		items.forEach( function ( item ) {
			var itemValue = item.getAttribute( 'data-language' ) || '';

			if ( value === '*' || itemValue === value ) {
				item.hidden = false;
			}
			else {
				item.hidden = true;
			}
		} );
	}

	/**
	 * Pick a sensible initial value: the hash if it matches a pill,
	 * otherwise '*' (show all).
	 *
	 * @param {HTMLElement} container
	 * @returns {string}
	 */
	function pickInitialValue( container ) {
		var fromHash = readHashLang();

		if ( fromHash === null ) {
			return '*';
		}

		var match = container.querySelector(
			'[data-ppb-filter-value="' + fromHash.replace( /"/g, '' ) + '"]'
		);

		return match ? fromHash : '*';
	}

	/**
	 * Initialise a single filter container.
	 *
	 * @param {HTMLElement} container
	 */
	function init( container ) {
		var controls = container.querySelector( '[data-ppb-occurrence-filter-controls]' );

		if ( ! controls ) {
			return;
		}

		applyFilter( container, pickInitialValue( container ) );

		controls.addEventListener( 'click', function ( event ) {
			var target = event.target;

			while ( target && target !== controls ) {
				if (
					target.nodeType === 1
					&& target.hasAttribute( 'data-ppb-filter-value' )
				) {
					event.preventDefault();
					var value = target.getAttribute( 'data-ppb-filter-value' );
					applyFilter( container, value );
					writeHashLang( value === '*' ? null : value );

					return;
				}

				target = target.parentNode;
			}
		} );

		window.addEventListener( 'hashchange', function () {
			applyFilter( container, pickInitialValue( container ) );
		} );
	}

	function bootstrap() {
		var containers = document.querySelectorAll( '[data-ppb-occurrence-filter]' );

		containers.forEach( function ( c ) {
			init( c );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', bootstrap );
	}
	else {
		bootstrap();
	}
} )();
