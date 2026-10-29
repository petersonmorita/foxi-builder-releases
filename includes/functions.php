<?php
/**
 * Global helper functions.
 *
 * @package FoxiBuilder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read a nested array value with dot notation.
 *
 * @param array  $data    Source.
 * @param string $path    e.g. "motion.entrance".
 * @param mixed  $default Fallback.
 * @return mixed
 */
function foxi_get( $data, $path, $default = null ) {
	if ( ! is_array( $data ) ) {
		return $default;
	}
	foreach ( explode( '.', $path ) as $key ) {
		if ( is_array( $data ) && array_key_exists( $key, $data ) ) {
			$data = $data[ $key ];
		} else {
			return $default;
		}
	}
	return $data;
}

/** @return array */
function foxi_settings() {
	return Foxi\Settings::get();
}

/** @return array */
function foxi_brand() {
	return Foxi\Brand::get();
}

/** @return array */
function foxi_business() {
	return Foxi\Brand::business();
}

/**
 * Escape a string for safe output inside a <style> element.
 *
 * @param string $css CSS.
 * @return string
 */
function foxi_esc_css( $css ) {
	$css = (string) $css;
	$css = preg_replace( '#</?\s*style#i', '', $css );
	$css = str_ireplace( array( '<script', 'javascript:', 'expression(' ), '', $css );
	return $css;
}

/**
 * Render an icon as inline SVG.
 *
 * @param string $name  Icon id, e.g. "lucide:phone" or "brand:facebook".
 * @param string $class Extra class.
 * @return string
 */
function foxi_icon( $name, $class = '' ) {
	return Foxi\Icons::svg( $name, $class );
}

/**
 * Replace {{business tokens}} in a string.
 *
 * @param string $text Text.
 * @return string
 */
function foxi_tokens( $text ) {
	return Foxi\Tokens::replace( $text );
}

/**
 * Allowed inline HTML for rich text fields.
 *
 * @param string $html HTML.
 * @return string
 */
function foxi_kses_inline( $html ) {
	static $allowed = null;
	if ( null === $allowed ) {
		$allowed = array(
			'a'      => array( 'href' => true, 'target' => true, 'rel' => true, 'class' => true, 'title' => true ),
			'strong' => array( 'class' => true ),
			'b'      => array(),
			'em'     => array( 'class' => true ),
			'i'      => array(),
			'u'      => array(),
			's'      => array(),
			'mark'   => array( 'class' => true, 'style' => true ),
			'span'   => array( 'class' => true, 'style' => true, 'data-fx-token' => true ),
			'br'     => array(),
			'sub'    => array(),
			'sup'    => array(),
			'code'   => array(),
			'kbd'    => array(),
			'small'  => array(),
		);
	}
	return wp_kses( (string) $html, $allowed );
}

/**
 * Is a known SEO plugin active? Foxi steps aside to avoid duplicate tags.
 *
 * @return string Plugin name or empty string.
 */
function foxi_active_seo_plugin() {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return 'Yoast SEO';
	}
	if ( defined( 'RANK_MATH_VERSION' ) ) {
		return 'Rank Math';
	}
	if ( defined( 'AIOSEO_VERSION' ) ) {
		return 'All in One SEO';
	}
	if ( defined( 'SEOPRESS_VERSION' ) ) {
		return 'SEOPress';
	}
	if ( class_exists( 'The_SEO_Framework\\Load' ) ) {
		return 'The SEO Framework';
	}
	return '';
}
