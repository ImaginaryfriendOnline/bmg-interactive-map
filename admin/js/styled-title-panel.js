/* BMG Interactive Map — Styled Title sidebar panel (no build step).
 *
 * Adds a "Styled Title" panel to the block editor's document sidebar for
 * locations and areas. It's a RichText field bound to post meta: selecting
 * text and applying Bold/Italic/Underline/Highlight writes real inline HTML
 * (<strong>, <em>, a style/class on <span>/<mark>) into that meta value,
 * which BMG_Text_Color::sanitize_styled_title() re-sanitizes server-side.
 */
( function ( plugins, editorPkg, editPost, blockEditor, data, element, i18n ) {
	'use strict';

	var el                         = element.createElement;
	var __                         = i18n.__;
	var registerPlugin             = plugins.registerPlugin;
	var PluginDocumentSettingPanel = ( editorPkg && editorPkg.PluginDocumentSettingPanel )
		|| ( editPost && editPost.PluginDocumentSettingPanel );
	var RichText                   = blockEditor.RichText;
	var useSelect                  = data.useSelect;
	var useDispatch                = data.useDispatch;

	var metaKeys = ( window.bmgStyledTitle && window.bmgStyledTitle.metaKeys ) || {};

	if ( ! PluginDocumentSettingPanel || ! RichText ) {
		return;
	}

	function StyledTitlePanel() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		var metaKey = metaKeys[ postType ];

		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );

		var editPostFn = useDispatch( 'core/editor' ).editPost;

		if ( ! metaKey ) {
			return null;
		}

		var value = meta[ metaKey ] || '';

		return el( PluginDocumentSettingPanel, {
			name     : 'bmg-styled-title',
			title    : __( 'Styled Title', 'bmg-interactive-map' ),
			className: 'bmg-styled-title-panel',
		},
			el( 'p', { className: 'description' },
				__( 'Select text and use Bold, Italic, Underline, or Highlight to style the popup and hover tooltip title. Leave blank to use the plain Title above (which supports [color=…] tags).', 'bmg-interactive-map' )
			),
			el( RichText, {
				tagName       : 'div',
				className     : 'bmg-styled-title-input',
				value         : value,
				onChange      : function ( newValue ) {
					var updated = {};
					updated[ metaKey ] = newValue;
					editPostFn( { meta: updated } );
				},
				allowedFormats: [ 'core/bold', 'core/italic', 'core/underline', 'core/text-color' ],
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
	window.wp.data,
	window.wp.element,
	window.wp.i18n
);
