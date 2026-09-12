<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shared admin list-table behaviour for the "bmg_location" and "bmg_area" CPTs:
 * Parent Map / Page View columns, Map / Visibility filters, and Hide/Unhide
 * bulk actions. Consuming classes stay pure static-method classes (hooked via
 * [ __CLASS__, 'method' ] callables) — a trait preserves that, where an
 * abstract base class would require restructuring every hook registration.
 */
trait BMG_CPT_Admin_List_Trait {

	// -------------------------------------------------------------------------
	// Required hooks — the consuming class must implement these.
	// -------------------------------------------------------------------------

	abstract protected static function post_type(): string;
	abstract protected static function map_meta_key(): string;
	abstract protected static function admin_script_handle(): string;
	abstract protected static function admin_script_file(): string;
	abstract protected static function hide_notice_message( int $count ): string;
	abstract protected static function unhide_notice_message( int $count ): string;

	// -------------------------------------------------------------------------
	// Optional hooks — no-op defaults; overridden by BMG_Location_CPT for its
	// Secret/Temporary columns, Secret filter, and "Copy to map" bulk action.
	// -------------------------------------------------------------------------

	protected static function extra_columns(): array {
		return [];
	}

	protected static function extra_column_renderer( string $column, int $post_id ): bool {
		return false;
	}

	protected static function extra_bulk_actions(): array {
		return [];
	}

	protected static function handle_extra_bulk_action( string $action, array $post_ids ): ?string {
		return null;
	}

	protected static function extra_list_filters(): void {}

	protected static function extra_meta_query_filters( array $meta_query ): array {
		return $meta_query;
	}

	// -------------------------------------------------------------------------
	// Columns
	// -------------------------------------------------------------------------

	public static function add_map_column( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'title' ) {
				$new['bmg_parent_map'] = __( 'Parent Map', 'bmg-interactive-map' );
				$new['bmg_page_view']  = __( 'Page View',  'bmg-interactive-map' );
				foreach ( static::extra_columns() as $col_key => $col_label ) {
					$new[ $col_key ] = $col_label;
				}
			}
		}
		return $new;
	}

	public static function render_map_column( string $column, int $post_id ): void {
		if ( $column === 'bmg_page_view' ) {
			if ( get_post_meta( $post_id, '_bmg_hidden', true ) === '1' ) {
				echo '<span class="dashicons dashicons-hidden" style="color:#999;" title="' . esc_attr__( 'Hidden from page view', 'bmg-interactive-map' ) . '"></span>';
			} else {
				echo '<span class="dashicons dashicons-visibility" style="color:#46b450;" title="' . esc_attr__( 'Shown in page view', 'bmg-interactive-map' ) . '"></span>';
			}
			return;
		}

		if ( static::extra_column_renderer( $column, $post_id ) ) {
			return;
		}

		if ( $column !== 'bmg_parent_map' ) {
			return;
		}
		$map_id = (int) get_post_meta( $post_id, static::map_meta_key(), true );
		if ( ! $map_id ) {
			echo '—';
			return;
		}
		$map = get_post( $map_id );
		if ( ! $map ) {
			echo '—';
			return;
		}
		printf(
			'<a href="%s">%s</a>',
			esc_url( get_edit_post_link( $map_id ) ),
			esc_html( $map->post_title )
		);
	}

	// -------------------------------------------------------------------------
	// Admin list filters — Parent Map & Page View
	// -------------------------------------------------------------------------

	public static function render_list_filters( string $post_type ): void {
		if ( $post_type !== static::post_type() ) {
			return;
		}

		$maps = BMG_Map_CPT::get_all_for_dropdown();

		$selected_map = absint( $_GET['bmg_filter_map'] ?? 0 );
		echo '<select name="bmg_filter_map">';
		echo '<option value="">' . esc_html__( 'All Maps', 'bmg-interactive-map' ) . '</option>';
		foreach ( $maps as $map ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $map->ID ),
				selected( $selected_map, $map->ID, false ),
				esc_html( $map->post_title )
			);
		}
		echo '</select>';

		$selected_vis = isset( $_GET['bmg_filter_visible'] ) ? $_GET['bmg_filter_visible'] : '';
		echo '<select name="bmg_filter_visible">';
		echo '<option value="">' . esc_html__( 'All visibility', 'bmg-interactive-map' ) . '</option>';
		echo '<option value="1"' . selected( $selected_vis, '1', false ) . '>' . esc_html__( 'Shown in page view', 'bmg-interactive-map' ) . '</option>';
		echo '<option value="0"' . selected( $selected_vis, '0', false ) . '>' . esc_html__( 'Hidden from page view', 'bmg-interactive-map' ) . '</option>';
		echo '</select>';

		static::extra_list_filters();
	}

	public static function apply_list_filters( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== static::post_type() ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' ) ?: [];

		if ( ! empty( $_GET['bmg_filter_map'] ) ) {
			$meta_query[] = [
				'key'   => static::map_meta_key(),
				'value' => absint( $_GET['bmg_filter_map'] ),
				'type'  => 'NUMERIC',
			];
		}

		if ( isset( $_GET['bmg_filter_visible'] ) && $_GET['bmg_filter_visible'] !== '' ) {
			if ( $_GET['bmg_filter_visible'] === '0' ) {
				// Hidden: _bmg_hidden = '1'
				$meta_query[] = [ 'key' => '_bmg_hidden', 'value' => '1' ];
			} else {
				// Shown: _bmg_hidden absent or not '1'
				$meta_query[] = [
					'relation' => 'OR',
					[ 'key' => '_bmg_hidden', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_bmg_hidden', 'value' => '1', 'compare' => '!=' ],
				];
			}
		}

		$meta_query = static::extra_meta_query_filters( $meta_query );

		if ( $meta_query ) {
			$query->set( 'meta_query', $meta_query );
		}
	}

	// -------------------------------------------------------------------------
	// Bulk actions — Hide / Unhide
	// -------------------------------------------------------------------------

	public static function register_bulk_actions( array $actions ): array {
		$actions['bmg_hide']   = __( 'Hide from page view', 'bmg-interactive-map' );
		$actions['bmg_unhide'] = __( 'Show in page view',   'bmg-interactive-map' );
		return $actions + static::extra_bulk_actions();
	}

	public static function handle_bulk_actions( string $redirect_to, string $action, array $post_ids ): string {
		if ( $action !== 'bmg_hide' && $action !== 'bmg_unhide' ) {
			$extra_redirect = static::handle_extra_bulk_action( $action, $post_ids );
			return $extra_redirect ?? $redirect_to;
		}

		foreach ( $post_ids as $post_id ) {
			if ( ! current_user_can( 'edit_post', (int) $post_id ) ) {
				continue;
			}
			if ( $action === 'bmg_hide' ) {
				update_post_meta( (int) $post_id, '_bmg_hidden', '1' );
			} else {
				delete_post_meta( (int) $post_id, '_bmg_hidden' );
			}
		}

		return add_query_arg( [
			'bmg_bulk_action' => $action,
			'bmg_bulk_count'  => count( $post_ids ),
		], $redirect_to );
	}

	/** The generic Hide/Unhide half of the bulk-action admin notice. */
	protected static function render_hide_unhide_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->id !== 'edit-' . static::post_type() ) {
			return;
		}

		$action = sanitize_key( $_GET['bmg_bulk_action'] ?? '' );
		$count  = (int) ( $_GET['bmg_bulk_count'] ?? 0 );

		if ( ! $action || ! $count ) {
			return;
		}

		if ( $action === 'bmg_hide' ) {
			$msg = static::hide_notice_message( $count );
		} elseif ( $action === 'bmg_unhide' ) {
			$msg = static::unhide_notice_message( $count );
		} else {
			return;
		}

		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $msg ) );
	}

	public static function bulk_action_notice(): void {
		static::render_hide_unhide_notice();
	}

	// -------------------------------------------------------------------------
	// Admin assets
	// -------------------------------------------------------------------------

	public static function enqueue_admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== static::post_type() ) {
			return;
		}

		wp_enqueue_style(
			'leaflet',
			bmg_map_leaflet_url( 'leaflet.css' ),
			[],
			'1.9.4'
		);

		wp_enqueue_script(
			'leaflet',
			bmg_map_leaflet_url( 'leaflet.js' ),
			[],
			'1.9.4',
			true
		);

		wp_enqueue_style(
			'bmg-admin',
			BMG_MAP_PLUGIN_URL . 'admin/css/admin.css',
			[ 'leaflet' ],
			BMG_MAP_VERSION
		);

		wp_enqueue_script(
			'bmg-admin-map-editor-shared',
			BMG_MAP_PLUGIN_URL . 'admin/js/admin-map-editor-shared.js',
			[ 'leaflet' ],
			BMG_MAP_VERSION,
			true
		);

		wp_enqueue_script(
			static::admin_script_handle(),
			BMG_MAP_PLUGIN_URL . static::admin_script_file(),
			[ 'leaflet', 'bmg-admin-map-editor-shared' ],
			BMG_MAP_VERSION,
			true
		);

		$s = BMG_Settings::get();
		wp_localize_script( static::admin_script_handle(), 'bmgAdminSettings', [
			'defaultColor' => $s['default_color'],
			'minZoom'      => $s['min_zoom'],
			'maxZoom'      => $s['max_zoom'],
		] );
	}
}
