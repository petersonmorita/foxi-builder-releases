<?php
/**
 * Brand Kit (colours, fonts, type scale, buttons, layout) and Business details.
 *
 * Everything in the Brand Kit is published as CSS custom properties, and blocks
 * reference those variables, so changing the Brand Kit re-themes the whole site.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Brand {

	const OPTION          = 'foxi_brand';
	const BUSINESS_OPTION = 'foxi_business';

	const COLOR_KEYS = array( 'primary', 'secondary', 'accent', 'dark', 'light', 'text', 'background' );

	/** @var array|null */
	private static $cache = null;

	public function init() {
		add_filter( 'block_editor_settings_all', array( $this, 'editor_palette' ), 20 );
	}

	public static function defaults() {
		return array(
			'name'    => 'Foxi Default',
			'colors'  => array(
				'primary'    => '#2F5D50',
				'secondary'  => '#7A9E7E',
				'accent'     => '#D9A441',
				'dark'       => '#17211C',
				'light'      => '#EEF1EC',
				'text'       => '#2A322D',
				'background' => '#FFFFFF',
			),
			'fonts'   => array(
				'heading' => array( 'family' => 'Marcellus', 'weight' => '400', 'category' => 'serif' ),
				'body'    => array( 'family' => 'Mulish', 'weight' => '400', 'category' => 'sans-serif' ),
				// Optional third font for menus and small labels; empty = body font.
				'accent'  => array( 'family' => '', 'weight' => '400', 'category' => 'sans-serif' ),
			),
			'type'    => array(
				'base'              => 17,
				'ratio'             => 1.22,
				'headingLineHeight' => 1.15,
				'bodyLineHeight'    => 1.7,
				'headingWeight'     => '400',
				'headingTransform'  => 'none',
				'headingSpacing'    => '0',
			),
			'buttons' => array(
				'radius'        => 4,
				'padY'          => 14,
				'padX'          => 28,
				'weight'        => '700',
				'transform'     => 'none',
				'letterSpacing' => '0.02',
			),
			'layout'  => array(
				'container'  => 1200,
				'sectionPad' => 112,
				'radius'     => 8,
				'gutter'     => 32,
			),
		);
	}

	public static function business_defaults() {
		return array(
			'name'        => get_bloginfo( 'name' ),
			'tagline'     => get_bloginfo( 'description' ),
			'phone'       => '',
			'email'       => get_bloginfo( 'admin_email' ),
			'address'     => '',
			'city'        => '',
			'region'      => '',
			'postcode'    => '',
			'country'     => '',
			'hours'       => '',
			'bookingUrl'  => '',
			'priceRange'  => '',
			'logoId'      => 0,
			'logoUrl'     => '',
			'socials'     => array(
				'facebook'    => '',
				'instagram'   => '',
				'tripadvisor' => '',
				'linkedin'    => '',
				'x'           => '',
				'youtube'     => '',
				'tiktok'      => '',
				'pinterest'   => '',
			),
		);
	}

	public static function get() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = self::merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$cache;
	}

	public static function business() {
		$saved = get_option( self::BUSINESS_OPTION, array() );
		return self::merge( self::business_defaults(), is_array( $saved ) ? $saved : array() );
	}

	private static function merge( array $defaults, array $saved ) {
		foreach ( $defaults as $k => $v ) {
			if ( ! array_key_exists( $k, $saved ) ) {
				$saved[ $k ] = $v;
			} elseif ( is_array( $v ) && is_array( $saved[ $k ] ) && self::is_assoc( $v ) ) {
				$saved[ $k ] = self::merge( $v, $saved[ $k ] );
			}
		}
		return $saved;
	}

	private static function is_assoc( array $a ) {
		return array_keys( $a ) !== range( 0, count( $a ) - 1 );
	}

	/**
	 * Sanitise and save the Brand Kit.
	 *
	 * @param array $input Raw.
	 * @return array
	 */
	public static function update( array $input ) {
		$d     = self::defaults();
		$clean = array( 'name' => sanitize_text_field( foxi_get( $input, 'name', $d['name'] ) ) );

		foreach ( self::COLOR_KEYS as $key ) {
			$c                         = sanitize_hex_color( (string) foxi_get( $input, 'colors.' . $key, '' ) );
			$clean['colors'][ $key ] = $c ? $c : $d['colors'][ $key ];
		}

		foreach ( array( 'heading', 'body', 'accent' ) as $role ) {
			$family = (string) foxi_get( $input, "fonts.$role.family", $d['fonts'][ $role ]['family'] );
			$family = preg_replace( '/[^A-Za-z0-9 \-]/', '', $family );
			$clean['fonts'][ $role ] = array(
				'family'   => $family || 'accent' === $role ? $family : $d['fonts'][ $role ]['family'],
				'weight'   => preg_replace( '/[^0-9]/', '', (string) foxi_get( $input, "fonts.$role.weight", '400' ) ),
				'category' => in_array( foxi_get( $input, "fonts.$role.category" ), array( 'serif', 'sans-serif', 'display', 'handwriting', 'monospace' ), true ) ? foxi_get( $input, "fonts.$role.category" ) : 'sans-serif',
			);
		}

		$clean['type'] = array(
			'base'              => max( 12, min( 24, (float) foxi_get( $input, 'type.base', 17 ) ) ),
			'ratio'             => max( 1.05, min( 1.6, (float) foxi_get( $input, 'type.ratio', 1.22 ) ) ),
			'headingLineHeight' => max( 0.9, min( 2, (float) foxi_get( $input, 'type.headingLineHeight', 1.15 ) ) ),
			'bodyLineHeight'    => max( 1, min( 2.4, (float) foxi_get( $input, 'type.bodyLineHeight', 1.7 ) ) ),
			'headingWeight'     => preg_replace( '/[^0-9]/', '', (string) foxi_get( $input, 'type.headingWeight', '400' ) ),
			'headingTransform'  => in_array( foxi_get( $input, 'type.headingTransform' ), array( 'none', 'uppercase', 'capitalize' ), true ) ? foxi_get( $input, 'type.headingTransform' ) : 'none',
			'headingSpacing'    => (string) (float) foxi_get( $input, 'type.headingSpacing', 0 ),
		);

		$clean['buttons'] = array(
			'radius'        => max( 0, min( 99, (int) foxi_get( $input, 'buttons.radius', 4 ) ) ),
			'padY'          => max( 4, min( 40, (int) foxi_get( $input, 'buttons.padY', 14 ) ) ),
			'padX'          => max( 6, min( 80, (int) foxi_get( $input, 'buttons.padX', 28 ) ) ),
			'weight'        => preg_replace( '/[^0-9]/', '', (string) foxi_get( $input, 'buttons.weight', '700' ) ),
			'transform'     => in_array( foxi_get( $input, 'buttons.transform' ), array( 'none', 'uppercase' ), true ) ? foxi_get( $input, 'buttons.transform' ) : 'none',
			'letterSpacing' => (string) (float) foxi_get( $input, 'buttons.letterSpacing', 0 ),
		);

		$clean['layout'] = array(
			'container'  => max( 720, min( 1920, (int) foxi_get( $input, 'layout.container', 1200 ) ) ),
			'sectionPad' => max( 0, min( 240, (int) foxi_get( $input, 'layout.sectionPad', 112 ) ) ),
			'radius'     => max( 0, min( 48, (int) foxi_get( $input, 'layout.radius', 8 ) ) ),
			'gutter'     => max( 0, min( 96, (int) foxi_get( $input, 'layout.gutter', 32 ) ) ),
		);

		update_option( self::OPTION, $clean );
		self::$cache = null;
		return self::get();
	}

	public static function update_business( array $input ) {
		$d     = self::business_defaults();
		$clean = array();
		foreach ( $d as $key => $default ) {
			if ( 'socials' === $key ) {
				foreach ( $default as $net => $_ ) {
					$clean['socials'][ $net ] = esc_url_raw( (string) foxi_get( $input, "socials.$net", '' ) );
				}
				continue;
			}
			$v = foxi_get( $input, $key, $default );
			if ( 'email' === $key ) {
				$clean[ $key ] = sanitize_email( (string) $v );
			} elseif ( in_array( $key, array( 'bookingUrl', 'logoUrl' ), true ) ) {
				$clean[ $key ] = esc_url_raw( (string) $v );
			} elseif ( 'logoId' === $key ) {
				$clean[ $key ] = absint( $v );
			} elseif ( 'hours' === $key || 'address' === $key ) {
				$clean[ $key ] = sanitize_textarea_field( (string) $v );
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $v );
			}
		}
		update_option( self::BUSINESS_OPTION, $clean );
		return self::business();
	}

	/* ------------------------------------------------------------------
	 * Colour maths (shared with the JS Brand Kit; keep in sync)
	 * ---------------------------------------------------------------- */

	public static function hex_to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return array( 0, 0, 0 );
		}
		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	public static function luminance( $hex ) {
		$out = array();
		foreach ( self::hex_to_rgb( $hex ) as $c ) {
			$c     = $c / 255;
			$out[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $out[0] + 0.7152 * $out[1] + 0.0722 * $out[2];
	}

	public static function contrast( $a, $b ) {
		$l1 = self::luminance( $a );
		$l2 = self::luminance( $b );
		return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
	}

	/** Best readable text colour on a background. */
	public static function on_color( $bg, $dark = '#111111', $light = '#FFFFFF' ) {
		return self::contrast( $bg, $dark ) >= self::contrast( $bg, $light ) ? $dark : $light;
	}

	public static function mix( $a, $b, $weight ) {
		$ra = self::hex_to_rgb( $a );
		$rb = self::hex_to_rgb( $b );
		$o  = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$o .= str_pad( dechex( (int) round( $ra[ $i ] * ( 1 - $weight ) + $rb[ $i ] * $weight ) ), 2, '0', STR_PAD_LEFT );
		}
		return $o;
	}

	/* ------------------------------------------------------------------
	 * CSS output
	 * ---------------------------------------------------------------- */

	private static function stack( array $font ) {
		$fallback = array(
			'serif'       => 'Georgia, "Times New Roman", serif',
			'sans-serif'  => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
			'display'     => 'system-ui, sans-serif',
			'handwriting' => 'cursive',
			'monospace'   => 'ui-monospace, Menlo, monospace',
		);
		$cat = isset( $fallback[ $font['category'] ] ) ? $font['category'] : 'sans-serif';
		return '"' . $font['family'] . '", ' . $fallback[ $cat ];
	}

	/**
	 * Fluid size: clamp(min, preferred, max) between 375px and 1280px viewports.
	 */
	private static function fluid( $min, $max ) {
		$min = round( $min, 2 );
		$max = round( $max, 2 );
		if ( abs( $max - $min ) < 0.5 ) {
			return $max . 'px';
		}
		$slope = ( $max - $min ) / ( 1280 - 375 );
		$inter = $min - $slope * 375;
		return sprintf( 'clamp(%spx, %spx + %svw, %spx)', $min, round( $inter, 2 ), round( $slope * 100, 3 ), $max );
	}

	/**
	 * CSS custom properties for the current Brand Kit.
	 *
	 * @param array|null $brand Optional brand (for previews).
	 * @return string
	 */
	public static function css_vars( $brand = null ) {
		$b = $brand ? $brand : self::get();
		$c = $b['colors'];
		$v = array();

		foreach ( self::COLOR_KEYS as $key ) {
			$v[ '--fx-c-' . $key ] = $c[ $key ];
		}
		foreach ( array( 'primary', 'secondary', 'accent', 'dark', 'light' ) as $key ) {
			$v[ '--fx-c-on-' . $key ] = self::on_color( $c[ $key ], $c['dark'], '#FFFFFF' );
		}
		$v['--fx-c-muted']        = self::mix( $c['text'], $c['background'], 0.22 );
		$v['--fx-c-border']       = self::mix( $c['text'], $c['background'], 0.86 );
		$v['--fx-c-primary-soft'] = self::mix( $c['primary'], $c['background'], 0.88 );
		$v['--fx-c-white']        = '#FFFFFF';
		$v['--fx-c-black']        = '#000000';

		$v['--fx-font-heading']   = self::stack( $b['fonts']['heading'] );
		$v['--fx-font-body']      = self::stack( $b['fonts']['body'] );
		$v['--fx-font-accent']    = ! empty( $b['fonts']['accent']['family'] ) ? self::stack( $b['fonts']['accent'] ) : 'var(--fx-font-body)';
		$v['--fx-weight-heading'] = $b['type']['headingWeight'] ? $b['type']['headingWeight'] : $b['fonts']['heading']['weight'];
		$v['--fx-weight-body']    = $b['fonts']['body']['weight'];
		$v['--fx-lh-heading']     = $b['type']['headingLineHeight'];
		$v['--fx-lh-body']        = $b['type']['bodyLineHeight'];
		$v['--fx-heading-transform'] = $b['type']['headingTransform'];
		$v['--fx-heading-spacing']   = $b['type']['headingSpacing'] . 'em';

		$base  = (float) $b['type']['base'];
		$ratio = (float) $b['type']['ratio'];
		$mbase = $base * 0.94;
		$mrat  = 1 + ( $ratio - 1 ) * 0.62;
		$steps = array(
			'small'   => -1,
			'body'    => 0,
			'lead'    => 1,
			'h6'      => 1,
			'h5'      => 2,
			'h4'      => 3,
			'h3'      => 4,
			'h2'      => 5,
			'h1'      => 6,
			'display' => 7.5,
		);
		foreach ( $steps as $name => $n ) {
			$v[ '--fx-fs-' . $name ] = self::fluid( $mbase * pow( $mrat, $n ), $base * pow( $ratio, $n ) );
		}

		$bt                         = $b['buttons'];
		$v['--fx-btn-radius']       = $bt['radius'] . 'px';
		$v['--fx-btn-pad-y']        = $bt['padY'] . 'px';
		$v['--fx-btn-pad-x']        = $bt['padX'] . 'px';
		$v['--fx-btn-weight']       = $bt['weight'];
		$v['--fx-btn-transform']    = $bt['transform'];
		$v['--fx-btn-spacing']      = $bt['letterSpacing'] . 'em';

		$l                     = $b['layout'];
		$v['--fx-container']   = $l['container'] . 'px';
		$v['--fx-section-pad'] = self::fluid( max( 24, $l['sectionPad'] * 0.5 ), $l['sectionPad'] );
		$v['--fx-radius']      = $l['radius'] . 'px';
		$v['--fx-gutter']      = self::fluid( max( 16, $l['gutter'] * 0.6 ), $l['gutter'] );

		$space = array( 'xs' => 8, 's' => 16, 'm' => 24, 'l' => 40, 'xl' => 64, 'xxl' => 96 );
		foreach ( $space as $k => $px ) {
			$v[ '--fx-space-' . $k ] = $px >= 40 ? self::fluid( $px * 0.6, $px ) : $px . 'px';
		}

		$out = ':root{';
		foreach ( $v as $prop => $val ) {
			$out .= $prop . ':' . $val . ';';
		}
		return $out . '}';
	}

	/**
	 * Google Fonts stylesheet URL for the brand fonts.
	 *
	 * @return string
	 */
	public static function google_fonts_url() {
		$settings = Settings::get();
		if ( empty( $settings['googleFonts'] ) ) {
			return '';
		}
		$b        = self::get();
		$families = array();
		foreach ( array( 'heading', 'body', 'accent' ) as $role ) {
			$f = isset( $b['fonts'][ $role ] ) ? $b['fonts'][ $role ] : array();
			if ( empty( $f['family'] ) || in_array( strtolower( $f['family'] ), array( 'system-ui', 'georgia', 'arial', 'helvetica' ), true ) ) {
				continue;
			}
			$name = $f['family'];
			$ws   = array( '400', '700' );
			if ( ! empty( $f['weight'] ) ) {
				$ws[] = $f['weight'];
			}
			if ( 'heading' === $role && ! empty( $b['type']['headingWeight'] ) ) {
				$ws[] = $b['type']['headingWeight'];
			}
			if ( 'body' === $role ) {
				$ws[] = '600';
			}
			$families[ $name ] = isset( $families[ $name ] ) ? array_merge( $families[ $name ], $ws ) : $ws;
		}
		if ( ! $families ) {
			return '';
		}
		$catalog = self::fonts();
		$parts   = array();
		foreach ( $families as $name => $ws ) {
			$ws = array_unique( $ws );
			sort( $ws );
			// Google Fonts rejects the whole request if a weight doesn't exist, so keep only real ones.
			if ( isset( $catalog[ $name ] ) ) {
				$f  = $catalog[ $name ];
				$ws = array_values(
					array_filter(
						$ws,
						static function ( $w ) use ( $f ) {
							$w = (int) $w;
							return isset( $f['r'] ) ? ( $w >= $f['r'][0] && $w <= $f['r'][1] ) : in_array( $w, $f['w'], true );
						}
					)
				);
				if ( ! $ws ) {
					$ws = array( isset( $f['r'] ) ? max( 400, $f['r'][0] ) : $f['w'][0] );
				}
			}
			$parts[] = 'family=' . str_replace( ' ', '+', $name ) . ( array( '400' ) === array_map( 'strval', $ws ) ? '' : ':wght@' . implode( ';', $ws ) );
		}
		return 'https://fonts.googleapis.com/css2?' . implode( '&', $parts ) . '&display=swap';
	}

	/**
	 * Curated Google Fonts catalogue (family => weights or range).
	 *
	 * @return array<string,array>
	 */
	public static function fonts() {
		static $fonts = null;
		if ( null === $fonts ) {
			$fonts = array();
			$list  = json_decode( (string) file_get_contents( FOXI_DIR . 'assets/fonts.json' ), true ); // phpcs:ignore
			foreach ( (array) $list as $f ) {
				$fonts[ $f['family'] ] = $f;
			}
		}
		return $fonts;
	}

	/**
	 * Expose the Brand Kit palette in Gutenberg's own colour pickers too.
	 *
	 * @param array $settings Editor settings.
	 * @return array
	 */
	public function editor_palette( $settings ) {
		$b       = self::get();
		$palette = array();
		foreach ( self::COLOR_KEYS as $key ) {
			$palette[] = array(
				'name'  => ucfirst( $key ),
				'slug'  => 'foxi-' . $key,
				'color' => $b['colors'][ $key ],
			);
		}
		$existing = isset( $settings['colors'] ) && is_array( $settings['colors'] ) ? $settings['colors'] : array();
		$settings['colors'] = array_merge( $palette, $existing );
		return $settings;
	}
}
