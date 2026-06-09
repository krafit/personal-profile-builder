( function ( wp ) {
	'use strict';

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var TextControl = wp.components.TextControl;
	var FormTokenField = wp.components.FormTokenField;
	var RadioControl = wp.components.RadioControl;
	var Notice = wp.components.Notice;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	/**
	 * Get the [{label, value}] list of locale choices passed from PHP.
	 *
	 * Skips any empty-value entry — for the token field, "no languages"
	 * is represented by an empty tokens array, not by a sentinel option.
	 *
	 * @returns {Array<{label: string, value: string}>}
	 */
	function getLocaleChoices() {
		var config = window.PPBTalkSidebar || {};
		var raw = Array.isArray( config.localeChoices ) ? config.localeChoices : [];

		return raw.filter( function ( c ) {
			return c && typeof c.value === 'string' && c.value !== '';
		} );
	}

	/**
	 * Build label↔code lookup maps from the PHP-provided choices.
	 *
	 * @returns {{labelToCode: Object, codeToLabel: Object, labels: Array<string>}}
	 */
	function buildLocaleMaps() {
		var choices = getLocaleChoices();
		var labelToCode = {};
		var codeToLabel = {};
		var labels = [];

		choices.forEach( function ( c ) {
			labelToCode[ c.label ] = c.value;
			codeToLabel[ c.value ] = c.label;
			labels.push( c.label );
		} );

		return { labelToCode: labelToCode, codeToLabel: codeToLabel, labels: labels };
	}

	function TalkDetailsPanel() {
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var talkLanguages = useSelect( function ( select ) {
			var v = select( 'core/editor' ).getEditedPostAttribute( 'talk_languages' );

			return Array.isArray( v ) ? v : [];
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;

		function setMeta( key, value ) {
			var update = {};
			update[ key ] = value;
			editPost( { meta: update } );
		}

		function setTalkLanguages( codes ) {
			editPost( { talk_languages: codes } );
		}

		var maps = buildLocaleMaps();
		var tokens = talkLanguages
			.map( function ( code ) { return maps.codeToLabel[ code ]; } )
			.filter( function ( label ) { return typeof label === 'string'; } );

		function onTokensChange( newTokens ) {
			// FormTokenField returns tokens as either strings or objects
			// with a .value property when fed suggestions as objects.
			// We feed plain strings (labels), so each token is a label.
			// Look up the locale code, drop unknown tokens, dedupe.
			var seen = {};
			var nextCodes = [];

			newTokens.forEach( function ( token ) {
				var label = typeof token === 'string' ? token : ( token.value || '' );
				var code = maps.labelToCode[ label ];

				if ( typeof code !== 'string' || code === '' ) {
					return;
				}

				if ( seen[ code ] ) {
					return;
				}

				seen[ code ] = true;
				nextCodes.push( code );
			} );

			setTalkLanguages( nextCodes );
		}

		var legacyValue = meta._talk_language_legacy || '';
		var languageChildren = [
			el( FormTokenField, {
				key: 'language-tokens',
				label: __( 'Languages', 'personal-profile-builder' ),
				value: tokens,
				suggestions: maps.labels,
				onChange: onTokensChange,
				__experimentalExpandOnFocus: true,
				__experimentalShowHowTo: false,
				__experimentalAutoSelectFirstMatch: true,
				placeholder: __( 'Add a language…', 'personal-profile-builder' )
			} ),
			el(
				'p',
				{ key: 'language-help', className: 'description' },
				__( 'Languages this talk is given in. Pick one or more from the suggestions.', 'personal-profile-builder' )
			)
		];

		if ( legacyValue !== '' ) {
			languageChildren.unshift( el(
				Notice,
				{
					key: 'language-legacy-notice',
					status: 'warning',
					isDismissible: false
				},
				sprintf(
					/* translators: %s: previous free-form language value, e.g. "German" */
					__( 'Previous language value: %s — please pick the equivalent from the suggestions below. This field will be removed in version 1.8.0.', 'personal-profile-builder' ),
					legacyValue
				)
			) );
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'ppb-talk-details',
				title: __( 'Talk details', 'personal-profile-builder' ),
				icon: 'microphone'
			},
			el( TextControl, {
				label: __( 'Cover emoji', 'personal-profile-builder' ),
				value: meta._talk_cover_emoji || '',
				onChange: function ( v ) { setMeta( '_talk_cover_emoji', v ); }
			} ),
			el( TextControl, {
				label: __( 'Duration', 'personal-profile-builder' ),
				value: meta._talk_duration || '',
				onChange: function ( v ) { setMeta( '_talk_duration', v ); },
				placeholder: __( 'e.g. 30 min', 'personal-profile-builder' )
			} ),
			el( Fragment, { key: 'language-block' }, languageChildren ),
			el( TextControl, {
				label: __( 'Target audience', 'personal-profile-builder' ),
				value: meta._talk_target_audience || '',
				onChange: function ( v ) { setMeta( '_talk_target_audience', v ); },
				placeholder: __( 'e.g. Developers, Beginners', 'personal-profile-builder' )
			} ),
			el( TextControl, {
				label: __( 'Format', 'personal-profile-builder' ),
				value: meta._talk_format || '',
				onChange: function ( v ) { setMeta( '_talk_format', v ); },
				placeholder: __( 'e.g. Workshop, Lightning talk', 'personal-profile-builder' )
			} ),
			el( TextControl, {
				label: __( 'Default slides URL', 'personal-profile-builder' ),
				value: meta._talk_slides_url || '',
				onChange: function ( v ) { setMeta( '_talk_slides_url', v ); },
				type: 'url',
				placeholder: 'https://'
			} ),
			el( TextControl, {
				label: __( 'Default recording URL', 'personal-profile-builder' ),
				value: meta._talk_recording_url || '',
				onChange: function ( v ) { setMeta( '_talk_recording_url', v ); },
				type: 'url',
				placeholder: 'https://'
			} )
		);
	}

	function TalkStatusPanel() {
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'ppb-talk-status',
				title: __( 'Talk status', 'personal-profile-builder' ),
				icon: 'flag'
			},
			el( RadioControl, {
				selected: meta._talk_status || '',
				options: [
					{ label: __( 'Available', 'personal-profile-builder' ), value: 'available' },
					{ label: __( 'Retired', 'personal-profile-builder' ), value: 'retired' },
					{ label: __( 'Neither', 'personal-profile-builder' ), value: '' }
				],
				onChange: function ( v ) {
					editPost( { meta: { _talk_status: v } } );
				}
			} )
		);
	}

	function TalkSidebar() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== 'talk' ) {
			return null;
		}

		return el(
			Fragment,
			null,
			el( TalkDetailsPanel ),
			el( TalkStatusPanel )
		);
	}

	registerPlugin( 'ppb-talk-sidebar', {
		render: TalkSidebar,
		icon: 'microphone'
	} );
} )( window.wp );
