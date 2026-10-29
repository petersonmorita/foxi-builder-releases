<?php
/**
 * Inline SVG icons (no icon fonts: faster pages, crisp at any size).
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Icons {

	/** @var array|null */
	private static $lucide = null;
	/** @var array|null */
	private static $brands = null;

	private static function lucide() {
		if ( null === self::$lucide ) {
			self::$lucide = include FOXI_DIR . 'includes/data/icons-lucide.php';
		}
		return self::$lucide;
	}

	public static function brands() {
		if ( null === self::$brands ) {
			self::$brands = include FOXI_DIR . 'includes/data/icons-brands.php';
		}
		return self::$brands;
	}

	/**
	 * @param string $id     "lucide:name", "brand:name" or bare lucide name.
	 * @param string $class  Extra classes.
	 * @param bool   $filled Fill line icons (e.g. rating stars).
	 * @return string
	 */
	public static function svg( $id, $class = '', $filled = false ) {
		$id = (string) $id;
		if ( '' === $id ) {
			return '';
		}
		$set  = 'lucide';
		$name = $id;
		if ( false !== strpos( $id, ':' ) ) {
			list( $set, $name ) = explode( ':', $id, 2 );
		}
		$name  = sanitize_key( $name );
		$class = trim( 'fx-svg ' . $class );

		if ( 'brand' === $set ) {
			$brands = self::brands();
			if ( empty( $brands[ $name ] ) ) {
				return '';
			}
			return sprintf(
				'<svg class="%1$s" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="%2$s"/></svg>',
				esc_attr( $class ),
				esc_attr( $brands[ $name ]['d'] )
			);
		}

		$icons = self::lucide();
		if ( empty( $icons[ $name ] ) ) {
			return '';
		}
		return sprintf(
			'<svg class="%1$s" viewBox="0 0 24 24" fill="%2$s" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
			esc_attr( $class ),
			$filled ? 'currentColor' : 'none',
			$icons[ $name ] // Generated, trusted markup.
		);
	}
}
