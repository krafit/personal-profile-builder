/**
 * Personal Profile Builder — sync meta box driver.
 *
 * The meta box can't use a nested HTML <form> (the post edit screen
 * already has an outer form, and HTML forbids nesting), so the
 * buttons are plain <button type="button"> elements. This script
 * wires up their click handlers to call the REST sync-action
 * endpoint, then reloads the page to show fresh sibling state.
 *
 * Uses `wp.apiFetch` so the REST nonce is attached automatically.
 */
( function () {
	'use strict';

	var apiFetch = window.wp && window.wp.apiFetch;
	var i18n = window.wp && window.wp.i18n;
	var __ = i18n ? i18n.__ : function ( s ) { return s; };

	if ( ! apiFetch ) {
		return;
	}

	function findContainer() {
		return document.querySelector( '[data-ppb-sync-meta-box]' );
	}

	function setFeedback( container, message, isError ) {
		var el = container.querySelector( '[data-ppb-sync-feedback]' );

		if ( ! el ) {
			return;
		}

		el.textContent = message;
		el.hidden = false;
		el.classList.toggle( 'ppb-sync-status__feedback--error', !! isError );
	}

	function setBusy( container, busy ) {
		var buttons = container.querySelectorAll( '[data-ppb-sync-action]' );

		buttons.forEach( function ( b ) {
			b.disabled = !! busy;
		} );

		container.classList.toggle( 'ppb-sync-status--busy', !! busy );
	}

	function buildBody( action, container ) {
		var body = {
			sync_action: action
		};

		if ( action === 'pull' ) {
			var src = container.querySelector( '[data-ppb-sync-source]' );

			if ( src && src.value ) {
				body.source_locale = src.value;
			}
		}

		return body;
	}

	function performAction( action ) {
		var container = findContainer();

		if ( ! container ) {
			return;
		}

		var postId = parseInt( container.getAttribute( 'data-post-id' ), 10 );

		if ( ! postId ) {
			setFeedback( container, __( 'Missing post ID.', 'personal-profile-builder' ), true );

			return;
		}

		var config = window.PPBSyncMetaBox || {};
		var ns = config.restNamespace || 'personal-profile-builder/v1';
		var path = '/' + ns + '/talks/' + postId + '/sync-action';
		var body = buildBody( action, container );

		setBusy( container, true );
		setFeedback( container, __( 'Working…', 'personal-profile-builder' ), false );

		apiFetch( {
			path: path,
			method: 'POST',
			data: body
		} ).then( function ( response ) {
			if ( response && response.ok ) {
				setFeedback(
					container,
					__( 'Done. Reloading…', 'personal-profile-builder' ),
					false
				);
				// Reload so the meta box shows fresh sibling state.
				window.location.reload();
			}
			else {
				setBusy( container, false );
				setFeedback(
					container,
					__( 'The action could not be completed.', 'personal-profile-builder' ),
					true
				);
			}
		} ).catch( function ( error ) {
			setBusy( container, false );
			var msg = error && error.message
				? error.message
				: __( 'The action could not be completed.', 'personal-profile-builder' );
			setFeedback( container, msg, true );
		} );
	}

	function init() {
		// Use document-level event delegation rather than attaching to
		// the container at init time. Reason: in the block editor, the
		// classic meta box DOM is created asynchronously after the
		// editor mounts, so at DOMContentLoaded the container may not
		// exist yet. By listening on document.body we catch clicks
		// whenever the buttons end up in the DOM.
		document.body.addEventListener( 'click', function ( event ) {
			var target = event.target;

			while ( target && target !== document.body ) {
				if (
					target.nodeType === 1
					&& target.hasAttribute( 'data-ppb-sync-action' )
				) {
					// Confirm the button lives inside our meta box —
					// avoid hijacking unrelated buttons elsewhere on
					// the page that happen to share the attribute name.
					if ( ! target.closest( '[data-ppb-sync-meta-box]' ) ) {
						return;
					}

					event.preventDefault();
					performAction( target.getAttribute( 'data-ppb-sync-action' ) );

					return;
				}

				target = target.parentNode;
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	}
	else {
		init();
	}
} )();
