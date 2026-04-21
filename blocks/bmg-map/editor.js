/* BMG Interactive Map — Gutenberg block editor (no build step) */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el               = element.createElement;
	var __               = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps    = blockEditor.useBlockProps;
	var PanelBody        = components.PanelBody;
	var SelectControl    = components.SelectControl;
	var TextControl      = components.TextControl;
	var ToggleControl    = components.ToggleControl;
	var Placeholder      = components.Placeholder;

	var maps = ( window.bmgMapBlock && window.bmgMapBlock.maps ) ? window.bmgMapBlock.maps : [];

	var selectOptions = [ { value: 0, label: __( '— Select a map —', 'bmg-interactive-map' ) } ]
		.concat( maps.map( function ( m ) {
			return { value: m.value, label: m.label };
		} ) );

	blocks.registerBlockType( 'bmg/interactive-map', {
		title      : __( 'Interactive Map', 'bmg-interactive-map' ),
		description: __( 'Display an interactive image-based map with clickable location markers.', 'bmg-interactive-map' ),
		icon       : 'location-alt',
		category   : 'embed',
		attributes : {
			mapId            : { type: 'number',  default: 0      },
			width            : { type: 'string',  default: ''     },
			height           : { type: 'string',  default: ''     },
			listPosition     : { type: 'string',  default: 'none' },
			zoomPosition     : { type: 'string',  default: ''     },
			showTooltips     : { type: 'boolean', default: false  },
			areaListPosition : { type: 'string',  default: 'none' },
			areaListTitle    : { type: 'string',  default: ''     },
			toolbarPosition  : { type: 'string',  default: ''     },
		},

		edit: function ( props ) {
			var mapId            = props.attributes.mapId;
			var width            = props.attributes.width;
			var height           = props.attributes.height;
			var listPosition     = props.attributes.listPosition;
			var zoomPosition     = props.attributes.zoomPosition;
			var showTooltips     = props.attributes.showTooltips;
			var areaListPosition = props.attributes.areaListPosition;
			var areaListTitle    = props.attributes.areaListTitle;
			var toolbarPosition  = props.attributes.toolbarPosition;
			var setAttr          = props.setAttributes;

			var selectedLabel = '';
			if ( mapId ) {
				var found = maps.find( function ( m ) { return m.value === mapId; } );
				selectedLabel = found ? found.label : __( 'Map #', 'bmg-interactive-map' ) + mapId;
			}

			var onChangeMap = function ( val ) {
				setAttr( { mapId: parseInt( val, 10 ) || 0 } );
			};

			return [
				el( InspectorControls, { key: 'controls' },
					el( PanelBody, { title: __( 'Map Settings', 'bmg-interactive-map' ), initialOpen: true },
						el( SelectControl, {
							label   : __( 'Select Map', 'bmg-interactive-map' ),
							value   : mapId,
							options : selectOptions,
							onChange: onChangeMap,
						} ),
						el( TextControl, {
							label: __( 'Width', 'bmg-interactive-map' ),
							help : __( 'px or % — e.g. 800px or 100%. Leave blank to fill the column.', 'bmg-interactive-map' ),
							value: width,
							onChange: function ( val ) {
								setAttr( { width: val } );
							},
						} ),
						el( TextControl, {
							label: __( 'Height', 'bmg-interactive-map' ),
							help : __( 'px or % — e.g. 600px or 50%. Leave blank to use the image aspect ratio.', 'bmg-interactive-map' ),
							value: height,
							onChange: function ( val ) {
								setAttr( { height: val } );
							},
						} ),
						el( SelectControl, {
							label  : __( 'Zoom Control Position', 'bmg-interactive-map' ),
							help   : __( 'Leave on "Default" to use the site-wide setting.', 'bmg-interactive-map' ),
							value  : zoomPosition,
							options: [
								{ label: __( 'Default (site setting)', 'bmg-interactive-map' ), value: ''            },
								{ label: __( 'Top Left',               'bmg-interactive-map' ), value: 'topleft'     },
								{ label: __( 'Top Right',              'bmg-interactive-map' ), value: 'topright'    },
								{ label: __( 'Bottom Left',            'bmg-interactive-map' ), value: 'bottomleft'  },
								{ label: __( 'Bottom Right',           'bmg-interactive-map' ), value: 'bottomright' },
							],
							onChange: function ( val ) {
								setAttr( { zoomPosition: val } );
							},
						} ),
						el( ToggleControl, {
							label   : __( 'Show name on hover', 'bmg-interactive-map' ),
							help    : __( 'Display a tooltip with the location name when hovering over a marker.', 'bmg-interactive-map' ),
							checked : showTooltips,
							onChange: function ( val ) {
								setAttr( { showTooltips: val } );
							},
						} ),
						el( SelectControl, {
							label  : __( 'Location List Position', 'bmg-interactive-map' ),
							help   : __( 'Display a scrollable list of locations alongside the map.', 'bmg-interactive-map' ),
							value  : listPosition,
							options: [
								{ label: __( 'None',             'bmg-interactive-map' ), value: 'none'     },
								{ label: __( 'Right',            'bmg-interactive-map' ), value: 'right'    },
								{ label: __( 'Left',             'bmg-interactive-map' ), value: 'left'     },
								{ label: __( 'Float: Top Left',  'bmg-interactive-map' ), value: 'float-tl' },
								{ label: __( 'Float: Top Right', 'bmg-interactive-map' ), value: 'float-tr' },
								{ label: __( 'Float: Bot Left',  'bmg-interactive-map' ), value: 'float-bl' },
								{ label: __( 'Float: Bot Right', 'bmg-interactive-map' ), value: 'float-br' },
							],
							onChange: function ( val ) {
								setAttr( { listPosition: val } );
							},
						} ),
						el( SelectControl, {
							label  : __( 'Area List Position', 'bmg-interactive-map' ),
							value  : areaListPosition,
							options: [
								{ label: __( 'None',             'bmg-interactive-map' ), value: 'none'     },
								{ label: __( 'Right',            'bmg-interactive-map' ), value: 'right'    },
								{ label: __( 'Left',             'bmg-interactive-map' ), value: 'left'     },
								{ label: __( 'Float: Top Left',  'bmg-interactive-map' ), value: 'float-tl' },
								{ label: __( 'Float: Top Right', 'bmg-interactive-map' ), value: 'float-tr' },
								{ label: __( 'Float: Bot Left',  'bmg-interactive-map' ), value: 'float-bl' },
								{ label: __( 'Float: Bot Right', 'bmg-interactive-map' ), value: 'float-br' },
							],
							onChange: function ( val ) {
								setAttr( { areaListPosition: val } );
							},
						} ),
						areaListPosition !== 'none' && el( TextControl, {
							label      : __( 'Area List Title', 'bmg-interactive-map' ),
							value      : areaListTitle,
							placeholder: __( 'Areas', 'bmg-interactive-map' ),
							onChange   : function ( val ) {
								setAttr( { areaListTitle: val } );
							},
						} ),
						el( SelectControl, {
							label  : __( 'Toolbar Position', 'bmg-interactive-map' ),
							help   : __( 'Leave on "Auto" to avoid overlapping floating list panels.', 'bmg-interactive-map' ),
							value  : toolbarPosition,
							options: [
								{ label: __( 'Auto',          'bmg-interactive-map' ), value: ''            },
								{ label: __( 'Top Center',    'bmg-interactive-map' ), value: 'top'         },
								{ label: __( 'Top Left',      'bmg-interactive-map' ), value: 'top-left'    },
								{ label: __( 'Top Right',     'bmg-interactive-map' ), value: 'top-right'   },
								{ label: __( 'Bottom Center', 'bmg-interactive-map' ), value: 'bottom'      },
								{ label: __( 'Bottom Left',   'bmg-interactive-map' ), value: 'bottom-left' },
								{ label: __( 'Bottom Right',  'bmg-interactive-map' ), value: 'bottom-right'},
							],
							onChange: function ( val ) {
								setAttr( { toolbarPosition: val } );
							},
						} )
					)
				),
				el( 'div', Object.assign( { key: 'preview' }, useBlockProps() ),
					el( Placeholder, {
						icon        : 'location-alt',
						label       : __( 'Interactive Map', 'bmg-interactive-map' ),
						instructions: __( 'Choose a map to display.', 'bmg-interactive-map' ),
					},
						el( SelectControl, {
							value   : mapId,
							options : selectOptions,
							onChange: onChangeMap,
						} )
					)
				),
			];
		},

		save: function () {
			return null; // server-side rendered
		},
	} );

} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
);
