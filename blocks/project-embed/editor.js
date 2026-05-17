( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ServerSideRender = wp.serverSideRender;
	var PanelBody = wp.components.PanelBody;
	var ComboboxControl = wp.components.ComboboxControl;
	var Placeholder = wp.components.Placeholder;
	var Spinner = wp.components.Spinner;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;

	registerBlockType( 'personal-profile-builder/project-embed', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();
			var projectsState = useState( [] );
			var projects = projectsState[ 0 ];
			var setProjects = projectsState[ 1 ];
			var loadingState = useState( true );
			var isLoading = loadingState[ 0 ];
			var setIsLoading = loadingState[ 1 ];

			useEffect( function () {
				apiFetch( {
					path: '/wp/v2/projects?per_page=100&status=publish&_fields=id,title'
				} ).then( function ( results ) {
					setProjects( results.map( function ( post ) {
						return {
							value: post.id,
							label: post.title.rendered
						};
					} ) );
					setIsLoading( false );
				} );
			}, [] );

			var selectedValue = useMemo( function () {
				if ( attributes.postId === 0 ) {
					return null;
				}

				var match = projects.find( function ( t ) {
					return t.value === attributes.postId;
				} );

				return match ? match.value : null;
			}, [ attributes.postId, projects ] );

			function onSelect( value ) {
				setAttributes( {
					postId: value ? parseInt( value, 10 ) : 0
				} );
			}

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Project', 'personal-profile-builder' ) },
					isLoading
						? el( Spinner )
						: el( ComboboxControl, {
							label: __( 'Select a project', 'personal-profile-builder' ),
							value: selectedValue,
							options: projects,
							onChange: onSelect,
							__experimentalRenderItem: undefined
						} )
				)
			);

			var content;

			if ( attributes.postId === 0 ) {
				content = el(
					Placeholder,
					{
						icon: 'portfolio',
						label: __( 'Project Embed', 'personal-profile-builder' )
					},
					isLoading
						? el( Spinner )
						: el( ComboboxControl, {
							label: __( 'Search for a project…', 'personal-profile-builder' ),
							value: null,
							options: projects,
							onChange: onSelect
						} )
				);
			}
			else {
				content = el(
					'div',
					{ style: { pointerEvents: 'none' } },
					el( ServerSideRender, {
						block: 'personal-profile-builder/project-embed',
						attributes: attributes,
						EmptyResponsePlaceholder: function () {
							return el(
								Placeholder,
								{
									icon: 'portfolio',
									label: __( 'Project Embed', 'personal-profile-builder' )
								},
								__( 'Project not found. It may have been deleted or unpublished.', 'personal-profile-builder' )
							);
						},
						LoadingResponsePlaceholder: function () {
							return el(
								Placeholder,
								{
									icon: 'portfolio',
									label: __( 'Project Embed', 'personal-profile-builder' )
								},
								el( Spinner )
							);
						}
					} )
				);
			}

			return el(
				'div',
				blockProps,
				inspector,
				content
			);
		}
	} );
} )( window.wp );
