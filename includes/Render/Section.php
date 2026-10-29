<?php
/**
 * Layout renderers: Section, Columns (row), Column, Spacer, Divider.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Render;

defined( 'ABSPATH' ) || exit;

class Section extends Base {
	const NAME = 'foxi/section';

	protected static function markup( $a, $content, $block ) {
		$tag = in_array( isset( $a['tagName'] ) ? $a['tagName'] : '', array( 'section', 'div', 'header', 'footer', 'aside', 'article', 'main' ), true ) ? $a['tagName'] : 'section';
		$cls = array(
			'alignfull',
			'fx-section--' . sanitize_key( isset( $a['width'] ) ? $a['width'] : 'boxed' ),
			'fx-section--h-' . sanitize_key( isset( $a['height'] ) ? $a['height'] : 'auto' ),
			'fx-valign-' . sanitize_key( isset( $a['vAlign'] ) ? $a['vAlign'] : 'center' ),
		);
		if ( ! empty( $a['fx']['bg']['parallax'] ) ) {
			$cls[] = 'fx-parallax';
		}
		return sprintf(
			'<%1$s %2$s>%3$s%4$s<div class="fx-section__inner">%5$s</div></%1$s>',
			$tag,
			self::wrapper( $a, $cls ),
			self::background( $a ),
			self::shapes( $a ),
			$content
		);
	}
}

class Row extends Base {
	const NAME = 'foxi/row';

	protected static function markup( $a, $content, $block ) {
		$cls = array( 'fx-valign-' . sanitize_key( isset( $a['vAlign'] ) ? $a['vAlign'] : 'stretch' ) );
		if ( ! empty( $a['reverseMobile'] ) ) {
			$cls[] = 'fx-row--reverse-m';
		}
		return '<div ' . self::wrapper( $a, $cls ) . '>' . $content . '</div>';
	}
}

class Column extends Base {
	const NAME = 'foxi/column';

	protected static function markup( $a, $content, $block ) {
		$link = '';
		if ( ! empty( $a['link']['url'] ) ) {
			$label = ! empty( $a['link']['label'] ) ? $a['link']['label'] : wp_strip_all_tags( $content );
			$link  = '<a class="fx-column__link"' . self::link_attrs( $a['link'] ) . ' aria-label="' . esc_attr( wp_trim_words( $label, 8, '' ) ) . '"></a>';
		}
		$cls = array( 'fx-valign-' . sanitize_key( isset( $a['vAlign'] ) ? $a['vAlign'] : 'top' ) );
		if ( $link ) {
			$cls[] = 'has-link';
		}
		return sprintf(
			'<div %1$s>%2$s<div class="fx-column__inner">%3$s</div>%4$s</div>',
			self::wrapper( $a, $cls ),
			self::background( $a ),
			$content,
			$link
		);
	}
}

class Spacer extends Base {
	const NAME = 'foxi/spacer';

	protected static function markup( $a, $content, $block ) {
		return '<div ' . self::wrapper( $a, array(), array( 'aria-hidden' => 'true' ) ) . '></div>';
	}
}

class Divider extends Base {
	const NAME = 'foxi/divider';

	protected static function markup( $a, $content, $block ) {
		$icon = ! empty( $a['icon'] ) ? foxi_icon( $a['icon'] ) : '';
		$html = '<span class="fx-divider__line"></span>';
		if ( $icon ) {
			$html .= '<span class="fx-divider__icon">' . $icon . '</span><span class="fx-divider__line"></span>';
		}
		return '<div ' . self::wrapper( $a, $icon ? array( 'has-icon' ) : array(), array( 'role' => 'separator' ) ) . '>' . $html . '</div>';
	}
}
