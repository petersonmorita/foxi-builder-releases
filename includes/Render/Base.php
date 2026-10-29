<?php
/**
 * Shared helpers for block renderers.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Render;

use Foxi\Style;

defined( 'ABSPATH' ) || exit;

abstract class Base {

	/** Block name, set by each renderer. */
	const NAME = '';

	/** @var array|null */
	private static $shapes = null;

	/**
	 * Render entry point (registered as render_callback).
	 *
	 * @param array          $attributes Attributes.
	 * @param string         $content    Inner blocks HTML.
	 * @param \WP_Block|null $block      Block instance.
	 * @return string
	 */
	public static function render( $attributes, $content = '', $block = null ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		$html       = static::markup( $attributes, (string) $content, $block );
		if ( '' === $html ) {
			return '';
		}
		return Style::inline_tag( $attributes, static::NAME ) . $html;
	}

	/**
	 * @param array          $a       Attributes.
	 * @param string         $content Inner HTML.
	 * @param \WP_Block|null $block   Block.
	 * @return string
	 */
	abstract protected static function markup( $a, $content, $block );

	protected static function fx( $a ) {
		return isset( $a['fx'] ) && is_array( $a['fx'] ) ? $a['fx'] : array();
	}

	protected static function slug() {
		return 'fx-' . str_replace( 'foxi/', '', static::NAME );
	}

	/**
	 * Wrapper attribute string: classes, anchor, motion and visibility.
	 *
	 * @param array $a       Attributes.
	 * @param array $classes Extra classes.
	 * @param array $attrs   Extra HTML attributes.
	 * @return string
	 */
	protected static function wrapper( $a, array $classes = array(), array $attrs = array() ) {
		$fx  = self::fx( $a );
		$cls = array( static::slug() );

		$id = Style::clean_id( isset( $fx['id'] ) ? $fx['id'] : '' );
		if ( $id ) {
			$cls[] = 'fx-' . $id;
		}

		foreach ( array( 'desktop' => 'd', 'tablet' => 't', 'mobile' => 'm' ) as $device => $short ) {
			if ( ! empty( $fx['hide'][ $device ] ) ) {
				$cls[] = 'fx-hide-' . $short;
			}
		}

		$motion = isset( $fx['motion'] ) && is_array( $fx['motion'] ) ? $fx['motion'] : array();
		$vars   = array();
		if ( ! empty( $motion['entrance'] ) && 'none' !== $motion['entrance'] ) {
			$attrs['data-fx-anim'] = sanitize_key( $motion['entrance'] );
			if ( ! empty( $motion['duration'] ) ) {
				$vars[] = '--fx-anim-dur:' . absint( $motion['duration'] ) . 'ms';
			}
			if ( ! empty( $motion['delay'] ) ) {
				$vars[] = '--fx-anim-delay:' . absint( $motion['delay'] ) . 'ms';
			}
			if ( ! empty( $motion['stagger'] ) ) {
				$attrs['data-fx-stagger'] = absint( $motion['stagger'] );
			}
		}
		if ( ! empty( $motion['loop'] ) && 'none' !== $motion['loop'] ) {
			$cls[] = 'fx-loop-' . sanitize_key( $motion['loop'] );
		}
		if ( ! empty( $motion['hover'] ) && 'none' !== $motion['hover'] ) {
			$cls[] = 'fx-hover-' . sanitize_key( $motion['hover'] );
		}
		if ( $vars ) {
			$attrs['style'] = ( isset( $attrs['style'] ) ? rtrim( $attrs['style'], ';' ) . ';' : '' ) . implode( ';', $vars );
		}

		if ( ! empty( $a['className'] ) ) {
			$cls[] = $a['className'];
		}
		if ( ! empty( $a['anchor'] ) && empty( $attrs['id'] ) ) {
			$attrs['id'] = $a['anchor'];
		}

		$cls = array_merge( $cls, $classes );
		$out = 'class="' . esc_attr( implode( ' ', array_filter( array_unique( $cls ) ) ) ) . '"';
		foreach ( $attrs as $k => $v ) {
			if ( null === $v || false === $v ) {
				continue;
			}
			$out .= ' ' . esc_attr( $k ) . ( true === $v ? '' : '="' . esc_attr( (string) $v ) . '"' );
		}
		return $out;
	}

	/**
	 * Background layers (video, slideshow, overlay). Colours, gradients and
	 * images are plain CSS compiled into fx.style by the editor.
	 */
	protected static function background( $a ) {
		$bg = foxi_get( self::fx( $a ), 'bg', array() );
		if ( ! is_array( $bg ) ) {
			return '';
		}
		$type    = isset( $bg['type'] ) ? $bg['type'] : 'none';
		$overlay = ! empty( $bg['overlay'] );
		$layers  = '';

		if ( 'video' === $type && ! empty( $bg['video']['url'] ) ) {
			$url    = esc_url( $bg['video']['url'] );
			$poster = ! empty( $bg['video']['poster'] ) ? esc_url( $bg['video']['poster'] ) : '';
			$yt     = self::youtube_id( $bg['video']['url'] );
			$layers .= sprintf(
				'<div class="fx-bg__video" data-fx-video="%1$s" data-fx-video-type="%2$s"%3$s%4$s></div>',
				$yt ? esc_attr( $yt ) : $url,
				$yt ? 'youtube' : 'file',
				$poster ? ' style="background-image:url(' . $poster . ')"' : '',
				! empty( $bg['video']['mobilePoster'] ) ? ' data-fx-mobile-poster="1"' : ''
			);
		} elseif ( 'slideshow' === $type && ! empty( $bg['slideshow']['images'] ) && is_array( $bg['slideshow']['images'] ) ) {
			$ss     = $bg['slideshow'];
			$slides = '';
			foreach ( $ss['images'] as $i => $img ) {
				if ( empty( $img['url'] ) ) {
					continue;
				}
				$slides .= sprintf(
					'<div class="fx-bg__slide%1$s" style="background-image:url(%2$s)"></div>',
					0 === $i ? ' is-active' : '',
					esc_url( $img['url'] )
				);
			}
			$layers .= sprintf(
				'<div class="fx-bg__slideshow%1$s" data-fx-duration="%2$d" style="--fx-ss-transition:%3$dms;--fx-ss-duration:%2$dms">%4$s</div>',
				! empty( $ss['kenburns'] ) ? ' has-kenburns' : '',
				max( 1500, absint( isset( $ss['duration'] ) ? $ss['duration'] : 5000 ) ),
				absint( isset( $ss['transition'] ) ? $ss['transition'] : 1200 ),
				$slides
			);
		}

		if ( $overlay ) {
			$pattern = ! empty( $bg['pattern'] ) && 'none' !== $bg['pattern'] ? ' fx-pattern-' . sanitize_key( $bg['pattern'] ) : '';
			$gradient = ! empty( $bg['overlayColor'] ) && false !== strpos( (string) $bg['overlayColor'], 'gradient(' ) ? ' fx-has-gradient' : '';
			$layers  .= '<div class="fx-bg__overlay' . $pattern . $gradient . '"></div>';
		}

		return $layers ? '<div class="fx-bg" aria-hidden="true">' . $layers . '</div>' : '';
	}

	protected static function youtube_id( $url ) {
		if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', (string) $url, $m ) ) {
			return $m[1];
		}
		return '';
	}

	protected static function vimeo_id( $url ) {
		if ( preg_match( '~vimeo\.com/(?:video/)?([0-9]+)~', (string) $url, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Shape dividers (top / bottom).
	 */
	protected static function shapes( $a ) {
		$shapes = foxi_get( self::fx( $a ), 'shapes', array() );
		if ( ! is_array( $shapes ) || ! $shapes ) {
			return '';
		}
		if ( null === self::$shapes ) {
			self::$shapes = json_decode( (string) file_get_contents( FOXI_DIR . 'assets/shapes.json' ), true ); // phpcs:ignore
		}
		$out = '';
		foreach ( array( 'top', 'bottom' ) as $pos ) {
			$s = isset( $shapes[ $pos ] ) ? $shapes[ $pos ] : array();
			if ( empty( $s['type'] ) || empty( self::$shapes[ $s['type'] ] ) ) {
				continue;
			}
			$paths = '';
			foreach ( self::$shapes[ $s['type'] ]['paths'] as $p ) {
				$paths .= '<path d="' . esc_attr( $p['d'] ) . '"' . ( isset( $p['opacity'] ) ? ' opacity="' . esc_attr( $p['opacity'] ) . '"' : '' ) . '/>';
			}
			$cls  = 'fx-shape fx-shape--' . $pos;
			$cls .= ! empty( $s['flip'] ) ? ' is-flipped' : '';
			$cls .= ! empty( $s['invert'] ) ? ' is-inverted' : '';
			$cls .= ! empty( $s['front'] ) ? ' is-front' : '';
			$out .= '<div class="' . esc_attr( $cls ) . '" aria-hidden="true"><svg viewBox="0 0 1000 100" preserveAspectRatio="none" fill="currentColor">' . $paths . '</svg></div>';
		}
		return $out;
	}

	/**
	 * Rich text attribute -> safe HTML with business tokens resolved.
	 */
	protected static function rich( $html ) {
		return foxi_tokens( foxi_kses_inline( (string) $html ) );
	}

	protected static function plain( $text ) {
		return foxi_tokens( esc_html( wp_strip_all_tags( (string) $text ) ) );
	}

	/**
	 * Link attributes for a {url, newTab, nofollow} object.
	 */
	protected static function link_attrs( $link ) {
		if ( ! is_array( $link ) || empty( $link['url'] ) ) {
			return '';
		}
		$url = \Foxi\Tokens::url( $link['url'] );
		$out = ' href="' . esc_url( $url ) . '"';
		$rel = array();
		if ( ! empty( $link['newTab'] ) ) {
			$out  .= ' target="_blank"';
			$rel[] = 'noopener';
		}
		if ( ! empty( $link['nofollow'] ) ) {
			$rel[] = 'nofollow';
		}
		if ( ! empty( $link['sponsored'] ) ) {
			$rel[] = 'sponsored';
		}
		if ( $rel ) {
			$out .= ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
		}
		return $out;
	}

	/**
	 * Image tag with srcset when we have an attachment id.
	 */
	protected static function img( $image, $class = '', $size = 'large', $extra = array() ) {
		if ( ! is_array( $image ) ) {
			return '';
		}
		$alt = isset( $image['alt'] ) ? (string) $image['alt'] : '';
		if ( ! empty( $image['id'] ) && wp_attachment_is_image( (int) $image['id'] ) ) {
			$attrs = array_merge(
				array(
					'class'   => $class,
					'alt'     => $alt,
					'loading' => isset( $extra['loading'] ) ? $extra['loading'] : 'lazy',
				),
				$extra
			);
			$html = wp_get_attachment_image( (int) $image['id'], $size, false, $attrs );
			if ( $html ) {
				return $html;
			}
		}
		if ( empty( $image['url'] ) ) {
			return '';
		}
		$out = '<img src="' . esc_url( $image['url'] ) . '" alt="' . esc_attr( $alt ) . '"';
		$out .= $class ? ' class="' . esc_attr( $class ) . '"' : '';
		$out .= ' loading="' . esc_attr( isset( $extra['loading'] ) ? $extra['loading'] : 'lazy' ) . '" decoding="async"';
		foreach ( $extra as $k => $v ) {
			if ( 'loading' !== $k ) {
				$out .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
			}
		}
		return $out . '>';
	}
}
