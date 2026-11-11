<?php
/**
 * Inline colour tags for popup titles and text.
 *
 * Editors colour specific letters by typing tags in a location or area's title
 * or content, using a Named Colour from Settings or a hex value:
 *
 *   [color=Gold]B[/color]lack [color=#e74c3c]R[/color]iver
 *
 * Tags are rendered in the popup and hover tooltip only. Everywhere else the
 * title is shown without them. A tag must open and close on one line, and
 * tags cannot be nested.
 *
 * A location or area can also have a Styled Title (bold/italic/underline and
 * its own free-colour picker, set via a restricted rich-text editor — not
 * tied to the Named Colours list). When non-empty it replaces the plain
 * title's [color=] rendering entirely in the popup/tooltip; see display_title_html().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BMG_Text_Color {

	private const PATTERN = '/\[color=([^\]\r\n]+)\](.*?)\[\/color\]/i';

	private const POST_TYPES = [ 'bmg_location', 'bmg_area' ];

	/** Tags allowed in a Styled Title field, written via its restricted editor toolbar. */
	private const STYLED_TITLE_TAGS = [
		'strong' => [],
		'b'      => [],
		'em'     => [],
		'i'      => [],
		'u'      => [],
		'span'   => [ 'style' => true ],
	];

	public static function init(): void {
		// Priority 1: strip before wptexturize and friends alter the tag text.
		add_filter( 'the_title', [ __CLASS__, 'filter_the_title' ], 1, 2 );
		add_filter( 'wp_insert_post_data', [ __CLASS__, 'filter_post_data' ], 10, 2 );
	}

	/** Remove colour tags, keeping the text inside them. */
	public static function strip( string $text ): string {
		return preg_replace( self::PATTERN, '$2', $text ) ?? $text;
	}

	/**
	 * The title as literal text, with tags removed and entities decoded. Every place
	 * that outputs this value must escape it.
	 */
	public static function plain_title( string $title ): string {
		return html_entity_decode( self::strip( $title ), ENT_QUOTES, 'UTF-8' );
	}

	/** Resolve a Named Colour (case-insensitive) or hex value to a hex colour. */
	public static function resolve( string $value ): ?string {
		$value = trim( $value );

		foreach ( BMG_Settings::get()['named_colors'] as $color ) {
			if ( strcasecmp( $color['name'], $value ) === 0 ) {
				return sanitize_hex_color( $color['hex'] ) ?: null;
			}
		}

		return sanitize_hex_color( $value ) ?: null;
	}

	/** Build escaped HTML from a plain-text title, applying colour tags. */
	public static function title_html( string $title ): string {
		// With two capture groups the parts repeat as: text, colour, inner text.
		$parts = preg_split( self::PATTERN, $title, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( $parts === false ) {
			return esc_html( $title );
		}

		$html = '';
		foreach ( $parts as $i => $part ) {
			if ( $i % 3 === 0 ) {
				$html .= esc_html( $part );
			} elseif ( $i % 3 === 2 ) {
				$html .= self::wrap( esc_html( $part ), $parts[ $i - 1 ] );
			}
		}
		return $html;
	}

	/**
	 * Replace colour tags in post content with coloured spans. Inner text is not
	 * escaped here because content is HTML: run this before the wp_kses_post() pass.
	 */
	public static function body_html( string $content ): string {
		return preg_replace_callback(
			self::PATTERN,
			static function ( array $match ): string {
				return self::wrap( $match[2], $match[1] );
			},
			$content
		) ?? $content;
	}

	/**
	 * Sort posts by their title without tags. The database sorts on the raw title,
	 * which would put a title starting with a tag ahead of everything else. Lists
	 * with no tags keep the database order.
	 *
	 * @param WP_Post[] $posts
	 * @return WP_Post[]
	 */
	public static function sort_by_title( array $posts ): array {
		$has_tags = array_filter( $posts, static function ( $post ): bool {
			return self::strip( $post->post_title ) !== $post->post_title;
		} );
		if ( ! $has_tags ) {
			return $posts;
		}

		usort( $posts, static function ( $a, $b ): int {
			return strcasecmp(
				remove_accents( self::strip( $a->post_title ) ),
				remove_accents( self::strip( $b->post_title ) )
			);
		} );
		return $posts;
	}

	/** Keep tags out of every get_the_title() result for locations and areas. */
	public static function filter_the_title( $title, $post_id = 0 ) {
		if ( ! is_string( $title ) || stripos( $title, '[color=' ) === false ) {
			return $title;
		}
		if ( ! $post_id || ! in_array( get_post_type( $post_id ), self::POST_TYPES, true ) ) {
			return $title;
		}
		return self::strip( $title );
	}

	/**
	 * Build auto-generated slugs from the title without tags. Slugs that are already
	 * set stay as they are, because deep links and secret discovery keys rely on them.
	 */
	public static function filter_post_data( array $data, array $postarr ): array {
		if (
			! in_array( $data['post_type'] ?? '', self::POST_TYPES, true )
			|| ! empty( $postarr['post_name'] )
			|| empty( $data['post_name'] )
		) {
			return $data;
		}

		$title    = wp_unslash( $data['post_title'] ?? '' );
		$stripped = self::strip( $title );
		$slug     = sanitize_title( $stripped );
		if ( $stripped === $title || $slug === '' ) {
			return $data;
		}

		$data['post_name'] = wp_slash(
			wp_unique_post_slug(
				$slug,
				(int) ( $postarr['ID'] ?? 0 ),
				$data['post_status'],
				$data['post_type'],
				(int) ( $data['post_parent'] ?? 0 )
			)
		);
		return $data;
	}

	/** An unknown colour leaves the text as it is. Only resolved hex values reach the style. */
	private static function wrap( string $html, string $color ): string {
		$hex = self::resolve( $color );
		return $hex ? '<span style="color:' . esc_attr( $hex ) . '">' . $html . '</span>' : $html;
	}

	/** Sanitize a Styled Title field to its allowed formatting tags (including a free text colour), for saving and for display. */
	public static function sanitize_styled_title( string $html ): string {
		return trim( wp_kses( $html, self::STYLED_TITLE_TAGS ) );
	}

	/**
	 * The HTML to render for a popup/tooltip title. Uses the Styled Title when one is
	 * set (bold/italic/underline, no [color=] parsing); otherwise falls back to the
	 * plain title with [color=] tags applied.
	 */
	public static function display_title_html( string $plain_title, string $styled_title ): string {
		$styled_title = self::sanitize_styled_title( $styled_title );
		return $styled_title !== '' ? $styled_title : self::title_html( $plain_title );
	}
}
