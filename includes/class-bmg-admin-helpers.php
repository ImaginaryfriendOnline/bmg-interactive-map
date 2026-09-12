<?php
defined( 'ABSPATH' ) || exit;

/**
 * Small stateless helpers shared by the plugin's CPT admin classes
 * (BMG_Location_CPT, BMG_Area_CPT, BMG_Map_CPT, BMG_Settings).
 */
class BMG_Admin_Helpers {

	/** Standard nonce + autosave + capability guard for a save_post handler. */
	public static function verify_save_request( int $post_id, string $nonce_field, string $nonce_action ): bool {
		if (
			! isset( $_POST[ $nonce_field ] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action )
		) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	public static function clamp_int( $value, int $min, int $max ): int {
		return max( $min, min( $max, (int) $value ) );
	}

	public static function clamp_float( $value, float $min, float $max ): float {
		return max( $min, min( $max, (float) $value ) );
	}

	/** Sanitize a hex colour, falling back to $fallback when invalid/empty. Callers unslash beforehand. */
	public static function sanitize_hex_with_fallback( string $value, string $fallback ): string {
		return sanitize_hex_color( $value ) ?: $fallback;
	}

	/**
	 * Clamp a "reach" value (secret reveal distance), only allowing fractional
	 * values when the unit is a percentage — a pixel distance is rounded to
	 * a whole number since fractional pixels aren't meaningful.
	 *
	 * @return int|float
	 */
	public static function sanitize_reach_value( $value, string $unit, float $max = 500 ) {
		return $unit === '%'
			? self::clamp_float( $value, 0.1, $max )
			: self::clamp_int( $value, 1, (int) $max );
	}
}
