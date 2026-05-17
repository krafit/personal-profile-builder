( function ( wp ) {
	'use strict';

	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ServerSideRender = wp.serverSideRender;
	var PanelBody = wp.components.PanelBody;
	var ComboboxControl = wp.components.ComboboxControl;
	var SelectControl = wp.components.SelectControl;
	var Placeholder = wp.components.Placeholder;
	var Spinner = wp.components.Spinner;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var __ = wp.i18n.__;
	var apiFetch = wp.apiFetch;

	registerBlockType( 'personal-profile-builder/talk-embed', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			var talksState = useState( [] );
			var talks = talksState[ 0 ];
			var setTalks = talksState[ 1 ];

			var loadingState = useState( true );
			var isLoading = loadingState[ 0 ];
			var setIsLoading = loadingState[ 1 ];

			var occurrencesState = useState( [] );
			var occurrences = occurrencesState[ 0 ];
			var setOccurrences = occurrencesState[ 1 ];

			useEffect( function () {
				apiFetch( {
					path: '/wp/v2/talks?per_page=100&status=publish&_fields=id,title'
				} ).then( function ( results ) {
					setTalks( results.map( function ( post ) {
						return {
							value: post.id,
							label: post.title.rendered
						};
					} ) );
					setIsLoading( false );
				} );
			}, [] );

			useEffect( function () {
				if ( attributes.postId === 0 ) {
					setOccurrences( [] );

					return;
				}

				apiFetch( {
					path: '/wp/v2/talks/' + attributes.postId + '?_fields=occurrences'
				} ).then( function ( result ) {
					var items = result.occurrences || [];

					setOccurrences( items );
				} ).catch( function () {
					setOccurrences( [] );
				} );
			}, [ attributes.postId ] );

			var selectedValue = useMemo( function () {
				if ( attributes.postId === 0 ) {
					return null;
				}

				var match = talks.find( function ( t ) {
					return t.value === attributes.postId;
				} );

				return match ? match.value : null;
			}, [ attributes.postId, talks ] );

			function onSelect( value ) {
				setAttributes( {
					postId: value ? parseInt( value, 10 ) : 0,
					occurrenceDate: ''
				} );
			}

			var occurrenceOptions = [ {
				label: __( 'None (link to talk page)', 'personal-profile-builder' ),
				value: ''
			} ];

			occurrences.forEach( function ( occ ) {
				var label = occ.date;

				if ( occ.event_name ) {
					label += ' — ' + occ.event_name;
				}

				if ( occ.location ) {
					label += ' (' + occ.location + ')';
				}

				occurrenceOptions.push( {
					label: label,
					value: occ.date
				} );
			} );

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Talk', 'personal-profile-builder' ) },
					isLoading
						? el( Spinner )
						: el( ComboboxControl, {
							label: __( 'Select a talk', 'personal-profile-builder' ),
							value: selectedValue,
							options: talks,
							onChange: onSelect,
							__experimentalRenderItem: undefined
						} ),
					attributes.postId !== 0 && occurrences.length > 0
						? el( SelectControl, {
							label: __( 'Occurrence', 'personal-profile-builder' ),
							value: attributes.occurrenceDate || '',
							options: occurrenceOptions,
							onChange: function ( value ) {
								setAttributes( { occurrenceDate: value } );
							},
							help: __( 'Optionally link to a specific occurrence and show its slides, recording, and event URL.', 'personal-profile-builder' )
						} )
						: null
				)
			);

			var content;

			if ( attributes.postId === 0 ) {
				content = el(
					Placeholder,
					{
						icon: 'microphone',
						label: __( 'Talk Embed', 'personal-profile-builder' )
					},
					isLoading
						? el( Spinner )
						: el( ComboboxControl, {
							label: __( 'Search for a talk…', 'personal-profile-builder' ),
							value: null,
							options: talks,
							onChange: onSelect
						} )
				);
			}
			else {
				content = el(
					'div',
					{ style: { pointerEvents: 'none' } },
					el( ServerSideRender, {
						block: 'personal-profile-builder/talk-embed',
						attributes: attributes,
						EmptyResponsePlaceholder: function () {
							return el(
								Placeholder,
								{
									icon: 'microphone',
									label: __( 'Talk Embed', 'personal-profile-builder' )
								},
								__( 'Talk not found. It may have been deleted or unpublished.', 'personal-profile-builder' )
							);
						},
						LoadingResponsePlaceholder: function () {
							return el(
								Placeholder,
								{
									icon: 'microphone',
									label: __( 'Talk Embed', 'personal-profile-builder' )
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
