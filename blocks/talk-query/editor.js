( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ServerSideRender = wp.serverSideRender;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var ToggleControl = wp.components.ToggleControl;
	var FormTokenField = wp.components.FormTokenField;
	var Placeholder = wp.components.Placeholder;
	var Spinner = wp.components.Spinner;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;

	registerBlockType( 'personal-profile-builder/talk-query', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			var topicTerms = useState( [] );
			var availableTopics = topicTerms[ 0 ];
			var setAvailableTopics = topicTerms[ 1 ];

			useEffect( function () {
				apiFetch( {
					path: '/wp/v2/talk-topics?per_page=100'
				} ).then( function ( terms ) {
					setAvailableTopics( terms.map( function ( t ) {
						return { id: t.id, name: t.name };
					} ) );
				} );
			}, [] );

			var selectedNames = availableTopics
				.filter( function ( t ) {
					return attributes.topic.indexOf( t.id ) !== -1;
				} )
				.map( function ( t ) {
					return t.name;
				} );

			function onTopicChange( tokens ) {
				var ids = tokens
					.map( function ( name ) {
						var match = availableTopics.find( function ( t ) {
							return t.name.toLowerCase() === name.toLowerCase();
						} );

						return match ? match.id : null;
					} )
					.filter( function ( id ) {
						return id !== null;
					} );

				setAttributes( { topic: ids } );
			}

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Filters', 'personal-profile-builder' ) },
					el( FormTokenField, {
						label: __( 'Topics', 'personal-profile-builder' ),
						value: selectedNames,
						suggestions: availableTopics.map( function ( t ) {
							return t.name;
						} ),
						onChange: onTopicChange
					} ),
					el( SelectControl, {
						label: __( 'Status', 'personal-profile-builder' ),
						value: attributes.status,
						options: [
							{ label: __( 'Any', 'personal-profile-builder' ), value: '' },
							{ label: __( 'Available', 'personal-profile-builder' ), value: 'available' },
							{ label: __( 'Retired', 'personal-profile-builder' ), value: 'retired' }
						],
						onChange: function ( value ) {
							setAttributes( { status: value } );
						}
					} )
				),
				el(
					PanelBody,
					{ title: __( 'Display', 'personal-profile-builder' ), initialOpen: false },
					el( RangeControl, {
						label: __( 'Max items', 'personal-profile-builder' ),
						value: attributes.maxItems,
						onChange: function ( value ) {
							setAttributes( { maxItems: value } );
						},
						min: 1,
						max: 24
					} ),
					el( SelectControl, {
						label: __( 'Order by', 'personal-profile-builder' ),
						value: attributes.orderBy,
						options: [
							{ label: __( 'Date published', 'personal-profile-builder' ), value: 'date' },
							{ label: __( 'Title', 'personal-profile-builder' ), value: 'title' },
							{ label: __( 'Next occurrence', 'personal-profile-builder' ), value: 'next_occurrence' }
						],
						onChange: function ( value ) {
							setAttributes( { orderBy: value } );
						}
					} ),
					el( SelectControl, {
						label: __( 'Layout', 'personal-profile-builder' ),
						value: attributes.layout,
						options: [
							{ label: __( 'Grid', 'personal-profile-builder' ), value: 'grid' },
							{ label: __( 'List', 'personal-profile-builder' ), value: 'list' }
						],
						onChange: function ( value ) {
							setAttributes( { layout: value } );
						}
					} ),
					el( ToggleControl, {
						label: __( 'Sort retired talks to bottom', 'personal-profile-builder' ),
						checked: attributes.retiredLast,
						onChange: function ( value ) {
							setAttributes( { retiredLast: value } );
						}
					} )
				)
			);

			var preview = el(
				'div',
				{ style: { pointerEvents: 'none' } },
				el( ServerSideRender, {
					block: 'personal-profile-builder/talk-query',
					attributes: attributes,
					EmptyResponsePlaceholder: function () {
						return el(
							Placeholder,
							{
								icon: 'microphone',
								label: __( 'Talk Query', 'personal-profile-builder' )
							},
							__( 'No talks found. Adjust the filters or add some talks.', 'personal-profile-builder' )
						);
					},
					LoadingResponsePlaceholder: function () {
						return el(
							Placeholder,
							{
								icon: 'microphone',
								label: __( 'Talk Query', 'personal-profile-builder' )
							},
							el( Spinner )
						);
					}
				} )
			);

			return el(
				'div',
				blockProps,
				inspector,
				preview
			);
		}
	} );
} )( window.wp );
