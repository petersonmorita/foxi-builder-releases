<?php
/**
 * Content renderers: Heading, Text, Button(s), Icon, Icon List, Rating, Copyright.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Render;

defined( 'ABSPATH' ) || exit;

class Heading extends Base {
	const NAME = 'foxi/heading';

	protected static function markup( $a, $content, $block ) {
		$level = max( 1, min( 6, (int) ( isset( $a['level'] ) ? $a['level'] : 2 ) ) );
		$tag   = 'h' . $level;
		if ( ! empty( $a['tagName'] ) && in_array( $a['tagName'], array( 'p', 'div', 'span' ), true ) ) {
			$tag = $a['tagName'];
		}
		$text = self::rich( isset( $a['content'] ) ? $a['content'] : '' );
		if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
			return '';
		}

		$typed = '';
		$tw    = isset( $a['typewriter'] ) && is_array( $a['typewriter'] ) ? $a['typewriter'] : array();
		if ( ! empty( $tw['enabled'] ) && ! empty( $tw['words'] ) ) {
			$words = array_values( array_filter( array_map( 'trim', explode( '|', (string) $tw['words'] ) ) ) );
			if ( $words ) {
				$typed = sprintf(
					' <span class="fx-typed" data-fx-typed="%1$s" data-speed="%2$d" data-pause="%3$d">%4$s</span>',
					esc_attr( wp_json_encode( $words ) ),
					absint( isset( $tw['speed'] ) ? $tw['speed'] : 70 ),
					absint( isset( $tw['pause'] ) ? $tw['pause'] : 1800 ),
					esc_html( $words[0] )
				);
			}
		}

		return sprintf( '<%1$s %2$s>%3$s%4$s</%1$s>', $tag, self::wrapper( $a, array( 'fx-heading--' . $tag ) ), $text, $typed );
	}
}

class Text extends Base {
	const NAME = 'foxi/text';

	protected static function markup( $a, $content, $block ) {
		$text = self::rich( isset( $a['content'] ) ? $a['content'] : '' );
		if ( '' === trim( $text ) ) {
			return '';
		}
		return '<p ' . self::wrapper( $a ) . '>' . $text . '</p>';
	}
}

class Copyright extends Base {
	const NAME = 'foxi/copyright';

	protected static function markup( $a, $content, $block ) {
		return '<p ' . self::wrapper( $a ) . '>' . self::rich( isset( $a['content'] ) ? $a['content'] : '' ) . '</p>';
	}
}

class Button extends Base {
	const NAME = 'foxi/button';

	protected static function markup( $a, $content, $block ) {
		$text = self::rich( isset( $a['text'] ) ? $a['text'] : '' );
		$icon = ! empty( $a['icon'] ) ? foxi_icon( $a['icon'] ) : '';
		$pos  = isset( $a['iconPosition'] ) && 'before' === $a['iconPosition'] ? 'before' : 'after';
		$cls  = array(
			'fx-button--' . sanitize_key( isset( $a['variant'] ) ? $a['variant'] : 'primary' ),
			'fx-button--' . sanitize_key( isset( $a['size'] ) ? $a['size'] : 'm' ),
		);
		if ( ! empty( $a['fullMobile'] ) ) {
			$cls[] = 'fx-button--full-m';
		}
		if ( $icon && '' === trim( wp_strip_all_tags( $text ) ) ) {
			$cls[] = 'fx-button--icon-only';
		}
		$inner = ( 'before' === $pos ? $icon : '' ) . '<span class="fx-button__text">' . $text . '</span>' . ( 'after' === $pos ? $icon : '' );
		$attrs = self::wrapper( $a, $cls );
		$link  = self::link_attrs( isset( $a['link'] ) ? $a['link'] : array() );
		if ( ! $link ) {
			return '<a ' . $attrs . ' role="link" aria-disabled="true">' . $inner . '</a>';
		}
		return '<a ' . $attrs . $link . '>' . $inner . '</a>';
	}
}

class Buttons extends Base {
	const NAME = 'foxi/buttons';

	protected static function markup( $a, $content, $block ) {
		return '<div ' . self::wrapper( $a, ! empty( $a['stackMobile'] ) ? array( 'fx-buttons--stack-m' ) : array() ) . '>' . $content . '</div>';
	}
}

class Icon extends Base {
	const NAME = 'foxi/icon';

	protected static function markup( $a, $content, $block ) {
		$svg = foxi_icon( isset( $a['icon'] ) ? $a['icon'] : '' );
		if ( ! $svg ) {
			return '';
		}
		$shape = sanitize_key( isset( $a['shape'] ) ? $a['shape'] : 'none' );
		$label = isset( $a['label'] ) ? trim( (string) $a['label'] ) : '';
		$inner = '<span class="fx-icon__shape">' . $svg . '</span>';
		$link  = self::link_attrs( isset( $a['link'] ) ? $a['link'] : array() );
		if ( $link ) {
			$inner = '<a class="fx-icon__link"' . $link . ( $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' ) . '>' . $inner . '</a>';
		} elseif ( $label ) {
			$inner = '<span role="img" aria-label="' . esc_attr( $label ) . '">' . $inner . '</span>';
		}
		return '<div ' . self::wrapper( $a, array( 'fx-icon--' . $shape ) ) . '>' . $inner . '</div>';
	}
}

class IconList extends Base {
	const NAME = 'foxi/icon-list';

	protected static function markup( $a, $content, $block ) {
		$cls = array( 'fx-icon-list--' . sanitize_key( isset( $a['layout'] ) ? $a['layout'] : 'vertical' ) );
		if ( ! empty( $a['divider'] ) ) {
			$cls[] = 'has-divider';
		}
		return '<ul ' . self::wrapper( $a, $cls ) . '>' . $content . '</ul>';
	}
}

class IconListItem extends Base {
	const NAME = 'foxi/icon-list-item';

	protected static function markup( $a, $content, $block ) {
		$icon = ! empty( $a['icon'] ) ? $a['icon'] : ( $block && isset( $block->context['foxi/listIcon'] ) ? $block->context['foxi/listIcon'] : 'lucide:check' );
		$text = self::rich( isset( $a['text'] ) ? $a['text'] : '' );
		$svg  = foxi_icon( $icon );
		$body = ( $svg ? '<span class="fx-icon-list__icon">' . $svg . '</span>' : '' ) . '<span class="fx-icon-list__text">' . $text . '</span>';
		$link = self::link_attrs( isset( $a['link'] ) ? $a['link'] : array() );
		if ( $link ) {
			$body = '<a class="fx-icon-list__link"' . $link . '>' . $body . '</a>';
		}
		return '<li ' . self::wrapper( $a ) . '>' . $body . '</li>';
	}
}

class Rating extends Base {
	const NAME = 'foxi/rating';

	protected static function markup( $a, $content, $block ) {
		$max   = max( 1, min( 10, (int) ( isset( $a['max'] ) ? $a['max'] : 5 ) ) );
		$value = max( 0, min( $max, (float) ( isset( $a['value'] ) ? $a['value'] : 5 ) ) );
		$star  = '<svg class="fx-svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>';
		$stars = str_repeat( $star, $max );
		/* translators: 1: rating, 2: maximum */
		$label = sprintf( __( 'Rated %1$s out of %2$s', 'foxi-builder' ), $value, $max );
		$html  = sprintf(
			'<span class="fx-rating__stars" style="--fx-rating:%1$s"><span class="fx-rating__base">%2$s</span><span class="fx-rating__fill">%2$s</span></span>',
			esc_attr( round( $value / $max, 4 ) ),
			$stars
		);
		if ( ! empty( $a['showValue'] ) ) {
			$html .= '<span class="fx-rating__value">' . esc_html( number_format_i18n( $value, 1 ) ) . '</span>';
		}
		if ( ! empty( $a['text'] ) ) {
			$html .= '<span class="fx-rating__text">' . self::rich( $a['text'] ) . '</span>';
		}
		return '<div ' . self::wrapper( $a, array(), array( 'role' => 'img', 'aria-label' => $label ) ) . '>' . $html . '</div>';
	}
}
