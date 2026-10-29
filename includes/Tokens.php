<?php
/**
 * Business tokens: write {{phone}} once, it renders the Business details value
 * everywhere (header, footer, contact sections, schema). Re-skinning a starter
 * site for a new client = update Business details once.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Tokens {

	/** @var array|null */
	private static $map = null;

	/**
	 * Token list (also used by the editor's "Insert business detail" menu).
	 *
	 * @return array<string,string> token => label
	 */
	public static function labels() {
		return array(
			'name'       => __( 'Business name', 'foxi-builder' ),
			'tagline'    => __( 'Tagline', 'foxi-builder' ),
			'phone'      => __( 'Phone', 'foxi-builder' ),
			'phone_link' => __( 'Phone (tel: link)', 'foxi-builder' ),
			'email'      => __( 'Email', 'foxi-builder' ),
			'address'    => __( 'Full address', 'foxi-builder' ),
			'city'       => __( 'City / suburb', 'foxi-builder' ),
			'hours'      => __( 'Opening hours', 'foxi-builder' ),
			'year'       => __( 'Current year', 'foxi-builder' ),
			'site_url'   => __( 'Site URL', 'foxi-builder' ),
			'booking_url' => __( 'Booking URL', 'foxi-builder' ),
		);
	}

	public static function values() {
		if ( null !== self::$map ) {
			return self::$map;
		}
		$b = Brand::business();

		$address_parts = array_filter( array( $b['address'], trim( $b['city'] . ' ' . $b['region'] . ' ' . $b['postcode'] ), $b['country'] ) );
		self::$map     = array(
			'name'        => $b['name'] ? $b['name'] : get_bloginfo( 'name' ),
			'tagline'     => $b['tagline'],
			'phone'       => $b['phone'],
			'phone_link'  => 'tel:' . preg_replace( '/[^0-9+]/', '', $b['phone'] ),
			'email'       => $b['email'],
			'address'     => implode( ', ', $address_parts ),
			'city'        => $b['city'],
			'hours'       => $b['hours'],
			'year'        => gmdate( 'Y' ),
			'site_url'    => home_url( '/' ),
			'booking_url' => $b['bookingUrl'],
		);
		return self::$map;
	}

	public static function flush() {
		self::$map = null;
	}

	/**
	 * Replace {{token}} placeholders. Values are escaped for HTML.
	 *
	 * @param string $text Text (may contain HTML).
	 * @return string
	 */
	public static function replace( $text ) {
		$text = (string) $text;
		if ( false === strpos( $text, '{{' ) ) {
			return $text;
		}
		$values = self::values();
		return preg_replace_callback(
			'/\{\{\s*([a-z_]+)\s*\}\}/',
			static function ( $m ) use ( $values ) {
				return isset( $values[ $m[1] ] ) ? esc_html( $values[ $m[1] ] ) : $m[0];
			},
			$text
		);
	}

	/**
	 * Replace tokens inside a URL attribute (no HTML escaping, URL-safe).
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function url( $url ) {
		$url = (string) $url;
		if ( false === strpos( $url, '{{' ) ) {
			return $url;
		}
		$values = self::values();
		$out    = trim(
			preg_replace_callback(
				'/\{\{\s*([a-z_]+)\s*\}\}/',
				static function ( $m ) use ( $values ) {
					return isset( $values[ $m[1] ] ) ? $values[ $m[1] ] : '';
				},
				$url
			)
		);
		// A bare email or phone number becomes a mailto:/tel: link.
		if ( is_email( $out ) ) {
			return 'mailto:' . $out;
		}
		if ( preg_match( '/^\+?[0-9 ()\-]{6,}$/', $out ) ) {
			return 'tel:' . preg_replace( '/[^0-9+]/', '', $out );
		}
		return $out;
	}
}
