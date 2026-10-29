<?php
/**
 * Interactive renderers: Counter, Slider/Slide, Carousel/Item, Accordion/Item, Tabs/Tab.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Render;

use Foxi\Seo\Schema;

defined( 'ABSPATH' ) || exit;

class Counter extends Base {
	const NAME = 'foxi/counter';

	private static function fmt( $n, $decimals, $sep ) {
		$dec = ( ',' === $sep ) ? '.' : ( '.' === $sep ? ',' : '.' );
		return number_format( (float) $n, max( 0, min( 4, (int) $decimals ) ), $dec, $sep );
	}

	protected static function markup( $a, $content, $block ) {
		$type   = in_array( isset( $a['type'] ) ? $a['type'] : 'number', array( 'number', 'circle', 'bar' ), true ) ? $a['type'] : 'number';
		$end    = (float) ( isset( $a['end'] ) ? $a['end'] : 100 );
		$start  = (float) ( isset( $a['start'] ) ? $a['start'] : 0 );
		$dec    = (int) ( isset( $a['decimals'] ) ? $a['decimals'] : 0 );
		$sep    = isset( $a['separator'] ) ? (string) $a['separator'] : ',';
		$sep    = in_array( $sep, array( ',', '.', ' ', '' ), true ) ? $sep : ',';
		$max    = max( 0.0001, (float) ( isset( $a['max'] ) ? $a['max'] : 100 ) );
		$pct    = max( 0, min( 1, $end / $max ) );
		$label  = ! empty( $a['label'] ) ? '<div class="fx-counter__label">' . self::rich( $a['label'] ) . '</div>' : '';
		$above  = isset( $a['labelPosition'] ) && 'above' === $a['labelPosition'];

		$value = sprintf(
			'<div class="fx-counter__value"><span class="fx-counter__prefix">%1$s</span><span class="fx-counter__number">%2$s</span><span class="fx-counter__suffix">%3$s</span></div>',
			esc_html( isset( $a['prefix'] ) ? $a['prefix'] : '' ),
			esc_html( self::fmt( $end, $dec, $sep ) ),
			esc_html( isset( $a['suffix'] ) ? $a['suffix'] : '' )
		);

		if ( 'circle' === $type ) {
			$body = '<div class="fx-counter__circle"><svg viewBox="0 0 120 120" aria-hidden="true"><circle class="fx-counter__track" cx="60" cy="60" r="54"/><circle class="fx-counter__fill" cx="60" cy="60" r="54" pathLength="100"/></svg>' . $value . '</div>';
		} elseif ( 'bar' === $type ) {
			$body = '<div class="fx-counter__head">' . $label . $value . '</div><div class="fx-counter__track"><div class="fx-counter__fill"></div></div>';
			$label = '';
		} else {
			$body = $value;
		}

		$html = $above ? $label . $body : $body . $label;

		return sprintf(
			'<div %1$s>%2$s</div>',
			self::wrapper(
				$a,
				array( 'fx-counter--' . $type, $above ? 'fx-counter--label-above' : '' ),
				array(
					'data-fx-counter' => true,
					'data-start'      => $start,
					'data-end'        => $end,
					'data-decimals'   => $dec,
					'data-sep'        => $sep,
					'data-duration'   => absint( isset( $a['duration'] ) ? $a['duration'] : 2000 ),
					'style'           => '--fx-progress:' . round( $pct, 4 ),
				)
			),
			$html
		);
	}
}

/**
 * Shared Swiper markup for Slider and Carousel.
 */
abstract class SwiperBase extends Base {

	protected static function arrows() {
		return '<button type="button" class="fx-slider__arrow fx-slider__arrow--prev" aria-label="' . esc_attr__( 'Previous slide', 'foxi-builder' ) . '">' . foxi_icon( 'lucide:chevron-left' ) . '</button>'
			. '<button type="button" class="fx-slider__arrow fx-slider__arrow--next" aria-label="' . esc_attr__( 'Next slide', 'foxi-builder' ) . '">' . foxi_icon( 'lucide:chevron-right' ) . '</button>';
	}

	protected static function frame( $a, $content, $options, array $cls = array(), $style = '' ) {
		wp_enqueue_script( 'foxi-swiper' );
		wp_enqueue_style( 'foxi-swiper' );
		$extra = array( 'data-fx-swiper' => wp_json_encode( $options ) );
		if ( $style ) {
			$extra['style'] = $style;
		}
		return sprintf(
			'<div %1$s><div class="swiper"><div class="swiper-wrapper">%2$s</div></div>%3$s%4$s</div>',
			self::wrapper( $a, $cls, $extra ),
			$content,
			! empty( $options['arrows'] ) ? static::arrows() : '',
			! empty( $options['dots'] ) ? '<div class="fx-slider__dots"></div>' : ''
		);
	}
}

class Slider extends SwiperBase {
	const NAME = 'foxi/slider';

	protected static function markup( $a, $content, $block ) {
		$opts = array(
			'type'         => 'slider',
			'autoplay'     => ! empty( $a['autoplay'] ),
			'delay'        => absint( isset( $a['delay'] ) ? $a['delay'] : 6000 ),
			'speed'        => absint( isset( $a['speed'] ) ? $a['speed'] : 800 ),
			'loop'         => ! empty( $a['loop'] ),
			'effect'       => in_array( isset( $a['effect'] ) ? $a['effect'] : 'fade', array( 'slide', 'fade', 'creative' ), true ) ? $a['effect'] : 'fade',
			'arrows'       => ! empty( $a['arrows'] ),
			'dots'         => ! empty( $a['dots'] ),
			'pauseOnHover' => ! empty( $a['pauseOnHover'] ),
		);
		return self::frame( $a, $content, $opts, array( 'alignfull' ) );
	}
}

class Slide extends Base {
	const NAME = 'foxi/slide';

	protected static function markup( $a, $content, $block ) {
		$cls = array( 'swiper-slide', 'fx-valign-' . sanitize_key( isset( $a['vAlign'] ) ? $a['vAlign'] : 'center' ) );
		return '<div ' . self::wrapper( $a, $cls ) . '>' . self::background( $a ) . '<div class="fx-slide__inner">' . $content . '</div></div>';
	}
}

class Carousel extends SwiperBase {
	const NAME = 'foxi/carousel';

	protected static function markup( $a, $content, $block ) {
		$pv   = isset( $a['perView'] ) && is_array( $a['perView'] ) ? $a['perView'] : array();
		$gap  = isset( $a['gap'] ) && is_array( $a['gap'] ) ? $a['gap'] : array();
		$d    = max( 1, (float) foxi_get( $pv, 'desktop', 3 ) );
		$t    = max( 1, (float) foxi_get( $pv, 'tablet', 2 ) );
		$m    = max( 1, (float) foxi_get( $pv, 'mobile', 1 ) );
		$gd   = absint( foxi_get( $gap, 'desktop', 24 ) );
		$gt   = absint( foxi_get( $gap, 'tablet', 20 ) );
		$gm   = absint( foxi_get( $gap, 'mobile', 16 ) );
		$opts = array(
			'type'     => 'carousel',
			'perView'  => array( 'desktop' => $d, 'tablet' => $t, 'mobile' => $m ),
			'gap'      => array( 'desktop' => $gd, 'tablet' => $gt, 'mobile' => $gm ),
			'autoplay' => ! empty( $a['autoplay'] ),
			'delay'    => absint( isset( $a['delay'] ) ? $a['delay'] : 5000 ),
			'speed'    => absint( isset( $a['speed'] ) ? $a['speed'] : 600 ),
			'loop'     => ! empty( $a['loop'] ),
			'arrows'   => ! empty( $a['arrows'] ),
			'dots'     => ! empty( $a['dots'] ),
			'centered' => ! empty( $a['centered'] ),
		);
		$style = sprintf( '--fx-pv:%s;--fx-pv-t:%s;--fx-pv-m:%s;--fx-cgap:%dpx;--fx-cgap-t:%dpx;--fx-cgap-m:%dpx', $d, $t, $m, $gd, $gt, $gm );
		return self::frame( $a, $content, $opts, array(), $style );
	}
}

class CarouselItem extends Base {
	const NAME = 'foxi/carousel-item';

	protected static function markup( $a, $content, $block ) {
		return '<div ' . self::wrapper( $a, array( 'swiper-slide' ) ) . '><div class="fx-carousel-item__inner">' . $content . '</div></div>';
	}
}

class Accordion extends Base {
	const NAME = 'foxi/accordion';

	protected static function markup( $a, $content, $block ) {
		$cls = array(
			'fx-accordion--icon-' . sanitize_key( isset( $a['icon'] ) ? $a['icon'] : 'plus' ),
			'fx-accordion--icon-' . ( isset( $a['iconPosition'] ) && 'left' === $a['iconPosition'] ? 'left' : 'right' ),
		);
		return '<div ' . self::wrapper( $a, $cls, array( 'data-fx-accordion' => ! empty( $a['multiple'] ) ? 'multiple' : 'single' ) ) . '>' . $content . '</div>';
	}
}

class AccordionItem extends Base {
	const NAME = 'foxi/accordion-item';

	protected static function markup( $a, $content, $block ) {
		$title = self::rich( isset( $a['title'] ) ? $a['title'] : '' );
		if ( $block && ! empty( $block->context['foxi/faq'] ) && class_exists( Schema::class ) ) {
			Schema::add_faq( wp_strip_all_tags( $title ), $content );
		}
		return sprintf(
			'<details %1$s%2$s><summary class="fx-accordion-item__title"><span class="fx-accordion-item__label">%3$s</span><span class="fx-accordion-item__icon" aria-hidden="true"></span></summary><div class="fx-accordion-item__content">%4$s</div></details>',
			self::wrapper( $a ),
			! empty( $a['open'] ) ? ' open' : '',
			$title,
			$content
		);
	}
}

class Tabs extends Base {
	const NAME = 'foxi/tabs';

	protected static function markup( $a, $content, $block ) {
		$nav   = '';
		$index = 0;
		$inner = ( $block && isset( $block->parsed_block['innerBlocks'] ) ) ? $block->parsed_block['innerBlocks'] : array();
		foreach ( $inner as $tab ) {
			if ( 'foxi/tab' !== $tab['blockName'] ) {
				continue;
			}
			$anchor = (string) foxi_get( $tab['attrs'], 'anchor', '' );
			$id     = $anchor ? sanitize_html_class( $anchor ) : 'fx-tab-' . \Foxi\Style::clean_id( foxi_get( $tab['attrs'], 'fx.id', 't' . $index ) );
			$title = foxi_get( $tab['attrs'], 'title', __( 'Tab', 'foxi-builder' ) );
			$icon  = foxi_get( $tab['attrs'], 'icon', '' );
			$nav  .= sprintf(
				'<button type="button" role="tab" class="fx-tabs__tab%1$s" id="%2$s-tab" aria-controls="%2$s" aria-selected="%3$s"%4$s>%5$s<span>%6$s</span></button>',
				0 === $index ? ' is-active' : '',
				esc_attr( $id ),
				0 === $index ? 'true' : 'false',
				0 === $index ? '' : ' tabindex="-1"',
				$icon ? foxi_icon( $icon ) : '',
				esc_html( foxi_tokens( $title ) )
			);
			++$index;
		}
		$cls = array(
			'fx-tabs--' . ( isset( $a['layout'] ) && 'left' === $a['layout'] ? 'left' : 'top' ),
			'fx-tabs--align-' . sanitize_key( isset( $a['align'] ) ? $a['align'] : 'start' ),
		);
		return '<div ' . self::wrapper( $a, $cls, array( 'data-fx-tabs' => true ) ) . '><div class="fx-tabs__nav" role="tablist">' . $nav . '</div><div class="fx-tabs__panels">' . $content . '</div></div>';
	}
}

class Tab extends Base {
	const NAME = 'foxi/tab';

	protected static function markup( $a, $content, $block ) {
		$id = ! empty( $a['anchor'] ) ? sanitize_html_class( $a['anchor'] ) : 'fx-tab-' . \Foxi\Style::clean_id( foxi_get( $a, 'fx.id', '' ) );
		return '<div ' . self::wrapper( $a, array(), array( 'role' => 'tabpanel', 'id' => $id, 'aria-labelledby' => $id . '-tab', 'tabindex' => '0' ) ) . '>' . $content . '</div>';
	}
}
