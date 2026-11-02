<?php
/**
 * Custom page scrollbar (Foxi › Settings › Scrollbar).
 *
 * Chrome, Edge and Safari use ::-webkit-scrollbar, which allows rounded, floating and
 * gradient bars. Firefox only supports a colour and thin/normal width, so it gets
 * scrollbar-color. Chrome ignores ::-webkit-scrollbar once scrollbar-color is set,
 * so scrollbar-color is only given to browsers without ::-webkit-scrollbar.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Scrollbar {

	const STYLES = array( 'rounded', 'floating', 'square', 'gradient', 'minimal' );
	const WIDTHS = array(
		'thin'    => 6,
		'regular' => 10,
		'wide'    => 14,
	);

	public function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_css' ), 20 );
	}

	public static function print_css() {
		$css = self::css( Settings::get() );
		if ( $css ) {
			echo '<style id="foxi-scrollbar">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from validated colours and numbers.
		}
	}

	/** A brand colour token (var(--fx-c-primary)) or a plain colour, as a plain colour. */
	public static function color( $value, $fallback ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^var\(--fx-c-([a-z]+)\)$/', $value, $m ) ) {
			$colors = Brand::get()['colors'];
			return isset( $colors[ $m[1] ] ) ? $colors[ $m[1] ] : $fallback;
		}
		if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $value ) || preg_match( '/^(rgb|rgba|hsl|hsla)\([0-9.,%\s\/]+\)$/', $value ) || 'transparent' === $value ) {
			return $value;
		}
		return $fallback;
	}

	/**
	 * @param array  $s     Settings.
	 * @param string $scope Selector for the scrolling element ('html' for the page).
	 */
	public static function css( array $s, $scope = 'html' ) {
		$style = isset( $s['scrollbar'] ) ? $s['scrollbar'] : 'default';
		if ( ! in_array( $style, self::STYLES, true ) ) {
			return '';
		}
		$c      = Brand::get()['colors'];
		$thumb  = self::color( isset( $s['scrollbarThumb'] ) ? $s['scrollbarThumb'] : '', $c['primary'] );
		$track  = self::color( isset( $s['scrollbarTrack'] ) ? $s['scrollbarTrack'] : '', $c['background'] );
		$thumb2 = self::color( isset( $s['scrollbarThumb2'] ) ? $s['scrollbarThumb2'] : '', $c['secondary'] );
		$w      = isset( $s['scrollbarWidth'], self::WIDTHS[ $s['scrollbarWidth'] ] ) ? self::WIDTHS[ $s['scrollbarWidth'] ] : 10;

		$radius = 'square' === $style ? '0' : '999px';
		$bar    = $thumb;
		$hover  = 'color-mix(in srgb,' . $thumb . ' 82%,#000)';
		$border = '';
		if ( 'floating' === $style ) {
			$w     += 6; // Room for the gap around the bar.
			$border = 'border:3px solid transparent;background-clip:padding-box;';
		}
		if ( 'gradient' === $style ) {
			$bar   = 'linear-gradient(180deg,' . $thumb . ',' . $thumb2 . ')';
			$hover = 'linear-gradient(180deg,color-mix(in srgb,' . $thumb . ' 85%,#000),color-mix(in srgb,' . $thumb2 . ' 85%,#000))';
		}
		if ( 'minimal' === $style ) {
			$track = 'transparent';
			$bar   = 'color-mix(in srgb,' . $thumb . ' 45%,transparent)';
			$hover = $thumb;
		}

		$sel = $scope;
		$out = sprintf(
			'%1$s::-webkit-scrollbar{width:%2$dpx;height:%2$dpx}'
			. '%1$s::-webkit-scrollbar-track{background:%3$s}'
			. '%1$s::-webkit-scrollbar-thumb{background:%4$s;border-radius:%5$s;%6$s}'
			. '%1$s::-webkit-scrollbar-thumb:hover{background:%7$s;%6$s}'
			. '%1$s::-webkit-scrollbar-corner{background:%3$s}'
			. '@supports not selector(::-webkit-scrollbar){%1$s{scrollbar-color:%8$s %9$s;scrollbar-width:%10$s}}',
			$sel,
			$w,
			$track,
			$bar,
			$radius,
			$border,
			$hover,
			$thumb,
			'transparent' === $track ? 'transparent' : $track,
			$w <= 6 ? 'thin' : 'auto'
		);
		return $out;
	}
}
