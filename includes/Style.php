<?php
/**
 * Style engine (server side).
 *
 * Every Foxi block stores its design in one attribute, `fx.style`:
 *
 *   element => device => state => { css-property: value }
 *
 *   element: ""       = the block root, or a named part from block.json
 *                       ("foxi.elements": { "title": "& .fx-heading__text" })
 *   device:  desktop | tablet | mobile   (tablet/mobile inherit from larger sizes)
 *   state:   normal | hover | stuck | active | focus
 *
 * The editor's controls compile everything (gradients, shadows, typography
 * presets…) into plain CSS declarations, so this class only has to print them.
 * The JS twin lives in src/shared/style-engine.js — keep the two in sync.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Style {

	const STATES = array(
		'normal' => '&',
		'hover'  => '&:hover',
		'stuck'  => '&.is-stuck',
		'active' => '&.is-active',
		'focus'  => '&:focus-within',
	);

	/** @var array<string,bool> Ids whose CSS was already printed in <head>. */
	private static $printed = array();

	/** @var string CSS collected before rendering (printed in <head>). */
	private static $head_css = '';

	/** @var array<string,bool> Block names found while pre-collecting. */
	private static $used_blocks = array();

	public function init() {}

	/**
	 * Root selector for a block id. The class is doubled to win over the
	 * base block styles without resorting to !important.
	 */
	public static function root( $id ) {
		$id = self::clean_id( $id );
		return '.fx-' . $id . '.fx-' . $id;
	}

	public static function clean_id( $id ) {
		return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $id ) );
	}

	private static function sanitize_prop( $prop ) {
		$prop = strtolower( trim( (string) $prop ) );
		return preg_match( '/^(--[a-z0-9-]+|-?[a-z][a-z-]*)$/', $prop ) ? $prop : '';
	}

	public static function sanitize_value( $value ) {
		if ( is_bool( $value ) || is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		$value = (string) $value;
		$value = str_replace( array( '{', '}', '<', '>', ';', '/*', '*/', '\\' ), '', $value );
		$value = preg_replace( '/(expression|javascript)\s*[:(]/i', '', $value );
		return trim( $value );
	}

	/**
	 * Build the CSS for one block.
	 *
	 * @param string $id       Block id.
	 * @param array  $style    fx.style.
	 * @param array  $elements Element selectors (from block.json).
	 * @param string $custom   Custom CSS ("selector" = this block).
	 * @return string
	 */
	public static function block_css( $id, $style, array $elements = array(), $custom = '' ) {
		$id = self::clean_id( $id );
		if ( ! $id ) {
			return '';
		}
		$root    = self::root( $id );
		$devices = array( 'desktop' => array(), 'tablet' => array(), 'mobile' => array() );

		if ( is_array( $style ) ) {
			foreach ( $style as $element => $by_device ) {
				if ( ! is_array( $by_device ) ) {
					continue;
				}
				$el_sel = '';
				if ( '' !== $element && 'root' !== $element ) {
					if ( ! isset( $elements[ $element ] ) ) {
						continue;
					}
					$el_sel = $elements[ $element ];
				}
				foreach ( $by_device as $device => $by_state ) {
					if ( ! isset( $devices[ $device ] ) || ! is_array( $by_state ) ) {
						continue;
					}
					foreach ( $by_state as $state => $decls ) {
						if ( ! isset( self::STATES[ $state ] ) || ! is_array( $decls ) ) {
							continue;
						}
						$body = '';
						foreach ( $decls as $prop => $value ) {
							$prop  = self::sanitize_prop( $prop );
							$value = self::sanitize_value( $value );
							if ( $prop && '' !== $value ) {
								$body .= $prop . ':' . $value . ';';
							}
						}
						if ( '' === $body ) {
							continue;
						}
						$selector                     = self::selector( $root, self::STATES[ $state ], $el_sel );
						$devices[ $device ][]         = $selector . '{' . $body . '}';
					}
				}
			}
		}

		$settings = Settings::get();
		$css      = implode( '', $devices['desktop'] );
		if ( $devices['tablet'] ) {
			$css .= '@media (max-width:' . (int) $settings['breakpointTablet'] . 'px){' . implode( '', $devices['tablet'] ) . '}';
		}
		if ( $devices['mobile'] ) {
			$css .= '@media (max-width:' . (int) $settings['breakpointMobile'] . 'px){' . implode( '', $devices['mobile'] ) . '}';
		}

		if ( $custom ) {
			$css .= str_replace( 'selector', '.fx-' . $id, foxi_esc_css( $custom ) );
		}
		return $css;
	}

	/**
	 * Combine root + state + element selector. Element selectors may list
	 * several selectors separated by commas; each gets the root.
	 */
	public static function selector( $root, $state_tpl, $el_sel ) {
		$base = str_replace( '&', $root, $state_tpl );
		if ( '' === $el_sel ) {
			return $base;
		}
		$out = array();
		foreach ( explode( ',', $el_sel ) as $part ) {
			$part = trim( $part );
			if ( false === strpos( $part, '&' ) ) {
				$part = '& ' . $part;
			}
			$out[] = str_replace( '&', $base, $part );
		}
		return implode( ',', $out );
	}

	/**
	 * CSS for a block's attributes (convenience wrapper used by renderers).
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $block_name Block name.
	 * @return string
	 */
	public static function for_block( $attributes, $block_name ) {
		$fx = isset( $attributes['fx'] ) && is_array( $attributes['fx'] ) ? $attributes['fx'] : array();
		if ( empty( $fx['id'] ) ) {
			return '';
		}
		return self::block_css(
			$fx['id'],
			isset( $fx['style'] ) ? $fx['style'] : array(),
			Blocks::elements( $block_name ),
			(string) foxi_get( $fx, 'advanced.css', '' )
		);
	}

	/**
	 * Inline <style> for a block, unless it was already printed in <head>.
	 */
	public static function inline_tag( $attributes, $block_name ) {
		$id = self::clean_id( foxi_get( $attributes, 'fx.id', '' ) );
		if ( ! $id || isset( self::$printed[ $id ] ) ) {
			return '';
		}
		self::$printed[ $id ] = true;
		$css                  = self::for_block( $attributes, $block_name );
		return $css ? '<style id="fx-css-' . esc_attr( $id ) . '">' . $css . '</style>' : '';
	}

	/**
	 * Walk parsed blocks, collect CSS for <head> and remember used block types.
	 *
	 * @param array $blocks parse_blocks() output.
	 */
	public static function collect( array $blocks ) {
		foreach ( $blocks as $block ) {
			$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
			if ( $name ) {
				self::$used_blocks[ $name ] = true;
			}
			if ( 0 === strpos( $name, 'foxi/' ) ) {
				$id = self::clean_id( foxi_get( $block['attrs'], 'fx.id', '' ) );
				if ( $id && ! isset( self::$printed[ $id ] ) ) {
					self::$head_css      .= self::for_block( $block['attrs'], $name );
					self::$printed[ $id ] = true;
				}
			}
			if ( 'core/block' === $name && ! empty( $block['attrs']['ref'] ) ) {
				$ref = get_post( (int) $block['attrs']['ref'] );
				if ( $ref ) {
					self::collect( parse_blocks( $ref->post_content ) );
				}
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::collect( $block['innerBlocks'] );
			}
		}
	}

	public static function head_css() {
		return self::$head_css;
	}

	public static function uses( $block_name ) {
		return isset( self::$used_blocks[ $block_name ] );
	}

	public static function used_blocks() {
		return array_keys( self::$used_blocks );
	}
}
