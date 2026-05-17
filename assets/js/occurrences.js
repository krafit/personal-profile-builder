/**
 * Personal Profile Builder — occurrences UI.
 *
 * Manages the repeatable-rows interface for talk occurrences. The DOM
 * rows are the source of truth: any change to a row's fields walks the
 * row container and serialises everything to a JSON string in the
 * hidden field. Adding or removing rows just touches the DOM and then
 * re-serialises. This avoids index-shifting and stale-state bugs.
 */
( function () {
	'use strict';

	const CONTAINER_SELECTOR = '[data-ppb-occurrences]';
	const FIELDS = [ 'date', 'event_name', 'location', 'event_url', 'slides_url', 'recording_url' ];

	/**
	 * Validate a YYYY-MM-DD string as a real Gregorian date.
	 *
	 * @param {string} value HTML5 date input value
	 * @returns {boolean}
	 */
	function isValidISODate( value ) {
		if ( typeof value !== 'string' || ! /^\d{4}-\d{2}-\d{2}$/.test( value ) ) {
			return false;
		}

		const [ year, month, day ] = value.split( '-' ).map( Number );
		const d = new Date( Date.UTC( year, month - 1, day ) );

		return (
			d.getUTCFullYear() === year &&
			d.getUTCMonth() === month - 1 &&
			d.getUTCDate() === day
		);
	}

	/**
	 * Convert an ISO date (YYYY-MM-DD) to compact form (YYYYMMDD).
	 * Returns an empty string if the date is invalid.
	 *
	 * @param {string} value
	 * @returns {string}
	 */
	function toCompactDate( value ) {
		return isValidISODate( value ) ? value.replace( /-/g, '' ) : '';
	}

	/**
	 * Read all rows in DOM order and return their values as an array.
	 *
	 * @param {HTMLElement} rowsContainer
	 * @returns {Array<Object>}
	 */
	function readRows( rowsContainer ) {
		const rows = rowsContainer.querySelectorAll( '[data-ppb-occurrence]' );
		const out = [];

		rows.forEach( ( row ) => {
			const data = {};

			FIELDS.forEach( ( field ) => {
				const input = row.querySelector( '[data-ppb-field="' + field + '"]' );
				data[ field ] = input ? input.value : '';
			} );

			out.push( data );
		} );

		return out;
	}

	/**
	 * Serialise the rows into the hidden JSON field.
	 *
	 * Rows with empty dates are kept while editing — sanitisation on the
	 * server drops them — so the user doesn't lose work mid-keystroke.
	 *
	 * @param {HTMLElement} container
	 */
	function serialise( container ) {
		const rowsContainer = container.querySelector( '[data-ppb-occurrences-rows]' );
		const field = container.querySelector( '[data-ppb-occurrences-field]' );

		if ( ! rowsContainer || ! field ) {
			return;
		}

		field.value = JSON.stringify( readRows( rowsContainer ) );
	}

	/**
	 * Update the shareable-URL display for a single row.
	 *
	 * @param {HTMLElement} row
	 * @param {string} baseUrl
	 */
	function updateRowUrl( row, baseUrl ) {
		const dateInput = row.querySelector( '[data-ppb-field="date"]' );
		const display = row.querySelector( '[data-ppb-url-display]' );
		const copyBtn = row.querySelector( '[data-ppb-copy]' );

		if ( ! dateInput || ! display ) {
			return;
		}

		const compact = toCompactDate( dateInput.value );

		if ( compact === '' || baseUrl === '' ) {
			display.textContent = ( window.ppbOccurrencesL10n && window.ppbOccurrencesL10n.urlPlaceholder ) || '';
			display.classList.remove( 'is-active' );

			if ( copyBtn ) {
				copyBtn.hidden = true;
			}

			return;
		}

		const fullUrl = baseUrl + compact;

		display.textContent = fullUrl;
		display.classList.add( 'is-active' );

		if ( copyBtn ) {
			copyBtn.hidden = false;
			copyBtn.dataset.ppbCopyTarget = fullUrl;
		}
	}

	/**
	 * Populate a freshly cloned row with values from a data object.
	 *
	 * @param {HTMLElement} row
	 * @param {Object} data
	 */
	function populateRow( row, data ) {
		FIELDS.forEach( ( field ) => {
			const input = row.querySelector( '[data-ppb-field="' + field + '"]' );

			if ( input && typeof data[ field ] === 'string' ) {
				input.value = data[ field ];
			}
		} );
	}

	/**
	 * Append a new row, optionally pre-populated.
	 *
	 * @param {HTMLElement} container
	 * @param {Object} data
	 */
	function addRow( container, data ) {
		const template = container.querySelector( '[data-ppb-occurrences-template]' );
		const rowsContainer = container.querySelector( '[data-ppb-occurrences-rows]' );

		if ( ! template || ! rowsContainer ) {
			return;
		}

		const fragment = template.content.cloneNode( true );
		const row = fragment.querySelector( '[data-ppb-occurrence]' );

		if ( ! row ) {
			return;
		}

		if ( data ) {
			populateRow( row, data );
		}

		rowsContainer.appendChild( row );

		const baseUrl = container.dataset.ppbBaseUrl || '';
		updateRowUrl( row, baseUrl );
	}

	/**
	 * Copy a string to the clipboard, falling back to a synchronous
	 * selection-and-execCommand path on browsers without async support.
	 *
	 * @param {string} text
	 * @param {HTMLElement} button
	 */
	function copyToClipboard( text, button ) {
		const original = button.textContent;
		const copiedLabel = ( window.ppbOccurrencesL10n && window.ppbOccurrencesL10n.copied ) || 'Copied!';

		const flash = function () {
			button.textContent = copiedLabel;
			window.setTimeout( function () {
				button.textContent = original;
			}, 1500 );
		};

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then( flash, function () {
				flash();
			} );

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
			flash();
		} catch ( err ) {
			// Silent failure — user can still copy from the visible URL display.
		}

		document.body.removeChild( helper );
	}

	/**
	 * Open the WP Media Library modal to select a slides file.
	 *
	 * On selection, the attachment URL is written into the row's
	 * slides_url field and the hidden JSON is re-serialised.
	 *
	 * @param {HTMLElement} button The upload button that was clicked
	 * @param {HTMLElement} container The occurrences container
	 */
	function openSlidesUploader( button, container ) {
		const row = button.closest( '[data-ppb-occurrence]' );

		if ( ! row ) {
			return;
		}

		const input = row.querySelector( '[data-ppb-field="slides_url"]' );

		if ( ! input ) {
			return;
		}

		const l10n = window.ppbOccurrencesL10n || {};
		const frame = wp.media( {
			title: l10n.selectSlides || 'Select slides file',
			button: { text: l10n.useFile || 'Use this file' },
			multiple: false
		} );

		frame.on( 'select', function () {
			const attachment = frame.state().get( 'selection' ).first().toJSON();

			input.value = attachment.url || '';
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			serialise( container );
		} );

		frame.open();
	}

	/**
	 * Wire up event delegation on a container.
	 *
	 * @param {HTMLElement} container
	 */
	function bind( container ) {
		const baseUrl = container.dataset.ppbBaseUrl || '';
		const addButton = container.querySelector( '[data-ppb-occurrences-add]' );

		if ( addButton ) {
			addButton.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				addRow( container );
				serialise( container );
			} );
		}

		container.addEventListener( 'input', function ( event ) {
			const target = event.target;

			if ( ! ( target instanceof HTMLElement ) ) {
				return;
			}

			if ( ! target.matches( '[data-ppb-field]' ) ) {
				return;
			}

			const row = target.closest( '[data-ppb-occurrence]' );

			if ( row && target.matches( '[data-ppb-field="date"]' ) ) {
				updateRowUrl( row, baseUrl );
			}

			serialise( container );
		} );

		container.addEventListener( 'click', function ( event ) {
			const target = event.target;

			if ( ! ( target instanceof HTMLElement ) ) {
				return;
			}

			if ( target.matches( '[data-ppb-remove]' ) ) {
				event.preventDefault();
				const row = target.closest( '[data-ppb-occurrence]' );

				if ( row ) {
					row.remove();
					serialise( container );
				}

				return;
			}

			if ( target.matches( '[data-ppb-copy]' ) ) {
				event.preventDefault();
				const url = target.dataset.ppbCopyTarget || '';

				if ( url !== '' ) {
					copyToClipboard( url, target );
				}

				return;
			}

			if ( target.matches( '[data-ppb-upload-slides]' ) ) {
				event.preventDefault();
				openSlidesUploader( target, container );
			}
		} );
	}

	/**
	 * Initialise a single occurrences container: render existing rows
	 * from the JSON field, then bind handlers.
	 *
	 * @param {HTMLElement} container
	 */
	function initContainer( container ) {
		const field = container.querySelector( '[data-ppb-occurrences-field]' );
		let initial = [];

		if ( field && field.value !== '' ) {
			try {
				const parsed = JSON.parse( field.value );

				if ( Array.isArray( parsed ) ) {
					initial = parsed;
				}
			} catch ( err ) {
				initial = [];
			}
		}

		initial.forEach( function ( row ) {
			if ( row && typeof row === 'object' ) {
				addRow( container, row );
			}
		} );

		bind( container );
		serialise( container );
	}

	function init() {
		const containers = document.querySelectorAll( CONTAINER_SELECTOR );

		containers.forEach( initContainer );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
