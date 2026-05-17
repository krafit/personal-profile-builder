( function ( wp ) {
	'use strict';

	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var TextControl = wp.components.TextControl;
	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	function ProjectDetailsPanel() {
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
				name: 'ppb-project-details',
				title: __( 'Project details', 'personal-profile-builder' ),
				icon: 'portfolio'
			},
			el( TextControl, {
				label: __( 'Project URL', 'personal-profile-builder' ),
				value: meta._project_url || '',
				onChange: function ( v ) { setMeta( '_project_url', v ); },
				type: 'url',
				placeholder: 'https://',
				help: __( 'External URL the project lives at.', 'personal-profile-builder' )
			} ),
			el( TextControl, {
				label: __( 'Icon', 'personal-profile-builder' ),
				value: meta._project_icon || '',
				onChange: function ( v ) { setMeta( '_project_icon', v ); },
				help: __( 'Emoji or icon identifier.', 'personal-profile-builder' )
			} ),
			el( TextControl, {
				label: __( 'Badge', 'personal-profile-builder' ),
				value: meta._project_badge || '',
				onChange: function ( v ) { setMeta( '_project_badge', v ); },
				help: __( 'Short label rendered as a badge (e.g. "Beta", "Open source").', 'personal-profile-builder' )
			} ),
			el( TextControl, {
				label: __( 'Start date', 'personal-profile-builder' ),
				value: meta._project_start_date || '',
				onChange: function ( v ) { setMeta( '_project_start_date', v ); },
				type: 'date',
				help: __( 'When the project started.', 'personal-profile-builder' )
			} ),
			el( TextControl, {
				label: __( 'End date', 'personal-profile-builder' ),
				value: meta._project_end_date || '',
				onChange: function ( v ) { setMeta( '_project_end_date', v ); },
				type: 'date',
				help: __( 'When the project ended. Leave empty for ongoing projects.', 'personal-profile-builder' )
			} )
		);
	}

	function ProjectSidebar() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( postType !== 'project' ) {
			return null;
		}

		return el( ProjectDetailsPanel );
	}

	registerPlugin( 'ppb-project-sidebar', {
		render: ProjectSidebar,
		icon: 'portfolio'
	} );
} )( window.wp );
