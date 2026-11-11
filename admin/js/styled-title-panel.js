/* BMG Interactive Map — Styled Title sidebar panel (no build step).
 *
 * A RichText field in the block editor's document sidebar, bound to post
 * meta. A RichText used outside a block's canvas gets no automatic floating
 * format popover (that only appears for RichText rendered inside the block
 * list), so this renders its own always-visible Bold/Italic/Underline/Colour
 * toolbar, driving the formatting via @wordpress/rich-text's functions
 * directly rather than relying on that popover.
 */
( function ( plugins, editorPkg, editPost, blockEditor, components, richText, data, element, i18n ) {
	'use strict';

	var el                         = element.createElement;
	var useState                   = element.useState;
	var useRef                     = element.useRef;
	var useEffect                  = element.useEffect;
	var __                         = i18n.__;
	var registerPlugin             = plugins.registerPlugin;
	var PluginDocumentSettingPanel = ( editorPkg && editorPkg.PluginDocumentSettingPanel )
		|| ( editPost && editPost.PluginDocumentSettingPanel );
	var RichText        = blockEditor.RichText;
	var useSelect        = data.useSelect;
	var useDispatch       = data.useDispatch;
	var ToolbarGroup      = components.ToolbarGroup;
	var ToolbarButton     = components.ToolbarButton;
	var Dropdown          = components.Dropdown;
	var ColorPalette      = components.ColorPalette;

	var create          = richText.create;
	var toHTMLString     = richText.toHTMLString;
	var toggleFormat      = richText.toggleFormat;
	var applyFormat       = richText.applyFormat;
	var removeFormat      = richText.removeFormat;
	var getActiveFormat   = richText.getActiveFormat;
	var registerFormatType = richText.registerFormatType;
	var getFormatType     = richText.getFormatType;

	var COLOR_FORMAT = 'bmg/text-color';

	// A dedicated format (rather than reusing core's Highlight) so its HTML shape
	// — <mark style="color:...">, nothing else — matches BMG_Text_Color's sanitizer exactly.
	if ( registerFormatType && getFormatType && ! getFormatType( COLOR_FORMAT ) ) {
		registerFormatType( COLOR_FORMAT, {
			title    : __( 'Text Colour', 'bmg-interactive-map' ),
			tagName  : 'mark',
			className: null,
			attributes: { style: 'style' },
			edit: function () {
				return null; // UI is this panel's own toolbar button, not the format library popover.
			},
		} );
	}

	var metaKeys = ( window.bmgStyledTitle && window.bmgStyledTitle.metaKeys ) || {};

	if ( ! PluginDocumentSettingPanel || ! RichText || ! create ) {
		return;
	}

	function StyledTitlePanel() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );
		var metaKey = metaKeys[ postType ];

		var savedMeta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );

		var paletteColors = useSelect( function ( select ) {
			var settings = select( 'core/block-editor' ).getSettings();
			return ( settings && settings.colors ) || [];
		}, [] );

		var editPostFn = useDispatch( 'core/editor' ).editPost;

		var savedHtml = metaKey ? ( savedMeta[ metaKey ] || '' ) : '';

		var state    = useState( function () { return create( { html: savedHtml } ); } );
		var value    = state[ 0 ];
		var setValue = state[ 1 ];

		// If the meta changes from outside this panel (new post load, undo), re-sync.
		var lastHtmlRef = useRef( savedHtml );
		useEffect( function () {
			if ( savedHtml !== lastHtmlRef.current ) {
				lastHtmlRef.current = savedHtml;
				setValue( create( { html: savedHtml } ) );
			}
		}, [ savedHtml ] );

		if ( ! metaKey ) {
			return null;
		}

		function commit( nextValue ) {
			setValue( nextValue );
			var html = toHTMLString( { value: nextValue } );
			lastHtmlRef.current = html;
			var updated = {};
			updated[ metaKey ] = html;
			editPostFn( { meta: updated } );
		}

		function toggle( type ) {
			commit( toggleFormat( value, { type: type } ) );
		}

		function applyColor( hex ) {
			if ( ! hex ) {
				commit( removeFormat( value, COLOR_FORMAT ) );
				return;
			}
			commit( applyFormat( value, { type: COLOR_FORMAT, attributes: { style: 'color:' + hex + ';' } } ) );
		}

		function isActive( type ) {
			return !! getActiveFormat( value, type );
		}

		return el( PluginDocumentSettingPanel, {
			name     : 'bmg-styled-title',
			title    : __( 'Styled Title', 'bmg-interactive-map' ),
			className: 'bmg-styled-title-panel',
		},
			el( 'p', { className: 'description' },
				__( 'Select text, then use the buttons below to style the popup and hover tooltip title. Leave blank to use the plain Title above (which supports [color=…] tags).', 'bmg-interactive-map' )
			),
			el( ToolbarGroup, { className: 'bmg-styled-title-toolbar' },
				el( ToolbarButton, {
					icon    : 'editor-bold',
					label   : __( 'Bold', 'bmg-interactive-map' ),
					isActive: isActive( 'core/bold' ),
					onClick : function () { toggle( 'core/bold' ); },
				} ),
				el( ToolbarButton, {
					icon    : 'editor-italic',
					label   : __( 'Italic', 'bmg-interactive-map' ),
					isActive: isActive( 'core/italic' ),
					onClick : function () { toggle( 'core/italic' ); },
				} ),
				el( ToolbarButton, {
					icon    : 'editor-underline',
					label   : __( 'Underline', 'bmg-interactive-map' ),
					isActive: isActive( 'core/underline' ),
					onClick : function () { toggle( 'core/underline' ); },
				} ),
				el( Dropdown, {
					className   : 'bmg-styled-title-color-dropdown',
					renderToggle: function ( props ) {
						return el( ToolbarButton, {
							icon          : 'admin-appearance',
							label         : __( 'Text Colour', 'bmg-interactive-map' ),
							isActive      : isActive( COLOR_FORMAT ),
							onClick       : props.onToggle,
							'aria-expanded': props.isOpen,
						} );
					},
					renderContent: function () {
						return el( ColorPalette, {
							colors    : paletteColors,
							value     : undefined,
							onChange  : applyColor,
							clearable : true,
						} );
					},
				} )
			),
			el( RichText, {
				tagName       : 'div',
				className     : 'bmg-styled-title-input',
				value         : value,
				onChange      : commit,
				allowedFormats: [ 'core/bold', 'core/italic', 'core/underline', COLOR_FORMAT ],
				placeholder   : __( 'Styled title…', 'bmg-interactive-map' ),
			} )
		);
	}

	registerPlugin( 'bmg-styled-title', {
		render: StyledTitlePanel,
	} );

} )(
	window.wp.plugins,
	window.wp.editor,
	window.wp.editPost,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.richText,
	window.wp.data,
	window.wp.element,
	window.wp.i18n
);
