( function ( wp ) {
	'use strict';

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var TextControl = wp.components.TextControl;
	var RadioControl = wp.components.RadioControl;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var __ = wp.i18n.__;

	function TalkDetailsPanel() {
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;

		function setMeta( key, value ) {
			var update = {};
			update[ key ] = value;
			editPost( { meta: update } );
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
			el( TextControl, {
				label: __( 'Language', 'personal-profile-builder' ),
				value: meta._talk_language || '',
				onChange: function ( v ) { setMeta( '_talk_language', v ); },
				placeholder: __( 'e.g. English', 'personal-profile-builder' )
			} ),
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
