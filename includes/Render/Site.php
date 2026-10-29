<?php
/**
 * Site renderers: Header, Header Row, Logo, Menu, Social Icons, Contact Form, Booking Bar.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Render;

use Foxi\Brand;
use Foxi\Icons;

defined( 'ABSPATH' ) || exit;

class Header extends Base {
	const NAME = 'foxi/header';

	public static function overlay_active( $a ) {
		$mode = isset( $a['overlay'] ) ? $a['overlay'] : 'none';
		// Editor previews pass the page being edited.
		$post_id = ! empty( $GLOBALS['foxi_preview_post'] ) ? (int) $GLOBALS['foxi_preview_post'] : ( is_singular() ? get_queried_object_id() : 0 );
		$page    = $post_id ? get_post_meta( $post_id, '_foxi_page', true ) : array();
		$pref    = is_array( $page ) && ! empty( $page['headerOverlay'] ) ? $page['headerOverlay'] : 'default';
		if ( 'on' === $pref ) {
			return true;
		}
		if ( 'off' === $pref ) {
			return false;
		}
		$is_front = ! empty( $GLOBALS['foxi_preview_post'] ) ? (int) get_option( 'page_on_front' ) === $post_id : is_front_page();
		return 'all' === $mode || ( 'front' === $mode && $is_front );
	}

	protected static function markup( $a, $content, $block ) {
		$cls = array();
		if ( ! empty( $a['sticky'] ) ) {
			$cls[] = 'fx-header--sticky';
		}
		if ( ! empty( $a['shrink'] ) ) {
			$cls[] = 'fx-header--shrink';
		}
		if ( self::overlay_active( $a ) ) {
			$cls[] = 'fx-header--overlay';
		}
		return '<header ' . self::wrapper( $a, $cls, array( 'data-fx-header' => true ) ) . '>' . $content . '</header>';
	}
}

class HeaderRow extends Base {
	const NAME = 'foxi/header-row';

	protected static function markup( $a, $content, $block ) {
		$cls = array(
			'fx-header-row--' . sanitize_key( isset( $a['layout'] ) ? $a['layout'] : 'logo-left' ),
			'fx-header-row--' . sanitize_key( isset( $a['role'] ) ? $a['role'] : 'main' ),
		);
		return '<div ' . self::wrapper( $a, $cls ) . '><div class="fx-header-row__inner">' . $content . '</div></div>';
	}
}

class Logo extends Base {
	const NAME = 'foxi/logo';

	protected static function markup( $a, $content, $block ) {
		$source = isset( $a['source'] ) ? $a['source'] : 'business';
		$name   = foxi_tokens( '{{name}}' );
		$image  = array();
		if ( 'custom' === $source && ! empty( $a['image']['url'] ) ) {
			$image = $a['image'];
		} elseif ( 'business' === $source ) {
			$biz = Brand::business();
			if ( ! empty( $biz['logoUrl'] ) ) {
				$image = array( 'id' => $biz['logoId'], 'url' => $biz['logoUrl'] );
			} elseif ( has_custom_logo() ) {
				$id    = (int) get_theme_mod( 'custom_logo' );
				$image = array( 'id' => $id, 'url' => wp_get_attachment_image_url( $id, 'full' ) );
			}
		}

		$alt   = sprintf( /* translators: %s site name */ __( '%s home', 'foxi-builder' ), $name );
		$inner = '';
		if ( $image && 'text' !== $source ) {
			$image['alt'] = $name;
			$inner        = self::img( $image, 'fx-logo__img', 'full', array( 'loading' => 'eager' ) );
			if ( ! empty( $a['altImage']['url'] ) ) {
				$alt_img        = $a['altImage'];
				$alt_img['alt'] = '';
				$inner         .= self::img( $alt_img, 'fx-logo__img fx-logo__img--alt', 'full', array( 'loading' => 'eager', 'aria-hidden' => 'true' ) );
			}
		}
		if ( ! $inner ) {
			$text  = ! empty( $a['text'] ) ? self::rich( $a['text'] ) : esc_html( $name );
			$inner = '<span class="fx-logo__text">' . $text . '</span>';
		}
		$cls = ! empty( $a['altImage']['url'] ) ? array( 'has-alt' ) : array();
		return '<a ' . self::wrapper( $a, $cls, array( 'href' => home_url( '/' ), 'aria-label' => $alt, 'rel' => 'home' ) ) . '>' . $inner . '</a>';
	}
}

class Nav extends Base {
	const NAME = 'foxi/nav';

	public static function sub_toggle( $output, $item, $depth, $args ) {
		if ( isset( $args->menu_class ) && 'fx-nav__menu' === $args->menu_class && in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
			$output .= '<button type="button" class="fx-nav__sub-toggle" aria-expanded="false" aria-label="' . esc_attr( sprintf( /* translators: %s menu item */ __( 'Show submenu for %s', 'foxi-builder' ), wp_strip_all_tags( $item->title ) ) ) . '">' . foxi_icon( 'lucide:chevron-down' ) . '</button>';
		}
		return $output;
	}

	private static function fallback_menu() {
		$pages = get_pages(
			array(
				'parent'      => 0,
				'sort_column' => 'menu_order,post_title',
				'number'      => 7,
			)
		);
		$items = '';
		foreach ( $pages as $p ) {
			$current = is_page( $p->ID ) ? ' current-menu-item' : '';
			$items  .= '<li class="menu-item' . $current . '"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>';
		}
		return '<ul class="fx-nav__menu">' . $items . '</ul>';
	}

	protected static function markup( $a, $content, $block ) {
		$menu_id = absint( isset( $a['menuId'] ) ? $a['menuId'] : 0 );
		$uid     = \Foxi\Style::clean_id( foxi_get( $a, 'fx.id', wp_rand() ) );

		if ( ! $menu_id ) {
			$locations = get_nav_menu_locations();
			$menu_id   = ! empty( $locations['foxi-primary'] ) ? (int) $locations['foxi-primary'] : 0;
		}

		$menu = '';
		if ( $menu_id && wp_get_nav_menu_object( $menu_id ) ) {
			add_filter( 'walker_nav_menu_start_el', array( __CLASS__, 'sub_toggle' ), 10, 4 );
			$menu = wp_nav_menu(
				array(
					'menu'        => $menu_id,
					'container'   => false,
					'menu_class'  => 'fx-nav__menu',
					'menu_id'     => '',
					'depth'       => 3,
					'echo'        => false,
					'fallback_cb' => false,
				)
			);
			remove_filter( 'walker_nav_menu_start_el', array( __CLASS__, 'sub_toggle' ), 10 );
		}
		if ( ! $menu ) {
			$menu = self::fallback_menu();
		}

		$collapse = in_array( isset( $a['collapse'] ) ? $a['collapse'] : 'tablet', array( 'tablet', 'mobile', 'never' ), true ) ? $a['collapse'] : 'tablet';
		$style    = isset( $a['mobileStyle'] ) && 'fullscreen' === $a['mobileStyle'] ? 'fullscreen' : 'drawer';
		$label    = ! empty( $a['label'] ) ? $a['label'] : __( 'Main', 'foxi-builder' );

		return sprintf(
			'<nav %1$s><button type="button" class="fx-nav__toggle" aria-expanded="false" aria-controls="fx-nav-%2$s"><span class="fx-nav__bars" aria-hidden="true"><span></span></span><span class="screen-reader-text">%3$s</span></button><div class="fx-nav__panel" id="fx-nav-%2$s"><button type="button" class="fx-nav__close" aria-label="%4$s">%5$s</button>%6$s</div></nav>',
			self::wrapper( $a, array( 'fx-nav--collapse-' . $collapse, 'fx-nav--' . $style ), array( 'aria-label' => $label, 'data-fx-nav' => true ) ),
			esc_attr( $uid ),
			esc_html__( 'Menu', 'foxi-builder' ),
			esc_attr__( 'Close menu', 'foxi-builder' ),
			foxi_icon( 'lucide:x' ),
			$menu
		);
	}
}

class SocialIcons extends Base {
	const NAME = 'foxi/social-icons';

	public static function labels() {
		return array(
			'facebook'    => 'Facebook',
			'instagram'   => 'Instagram',
			'tripadvisor' => 'Tripadvisor',
			'linkedin'    => 'LinkedIn',
			'x'           => 'X',
			'youtube'     => 'YouTube',
			'tiktok'      => 'TikTok',
			'pinterest'   => 'Pinterest',
			'whatsapp'    => 'WhatsApp',
			'google'      => 'Google',
			'airbnb'      => 'Airbnb',
			'threads'     => 'Threads',
			'bluesky'     => 'Bluesky',
			'spotify'     => 'Spotify',
			'vimeo'       => 'Vimeo',
			'yelp'        => 'Yelp',
			'email'       => 'Email',
			'phone'       => 'Phone',
			'website'     => 'Website',
		);
	}

	protected static function markup( $a, $content, $block ) {
		$items = array();
		if ( isset( $a['source'] ) && 'custom' === $a['source'] ) {
			$items = isset( $a['items'] ) && is_array( $a['items'] ) ? $a['items'] : array();
		} else {
			foreach ( Brand::business()['socials'] as $net => $url ) {
				if ( $url ) {
					$items[] = array( 'network' => $net, 'url' => $url );
				}
			}
		}
		if ( ! $items ) {
			return '';
		}
		$labels = self::labels();
		$brands = Icons::brands();
		$html   = '';
		foreach ( $items as $item ) {
			$net = sanitize_key( isset( $item['network'] ) ? $item['network'] : '' );
			$url = isset( $item['url'] ) ? \Foxi\Tokens::url( $item['url'] ) : '';
			if ( ! $net || ! $url ) {
				continue;
			}
			$icon = isset( $brands[ $net ] ) ? Icons::svg( 'brand:' . $net ) : Icons::svg( 'lucide:' . ( 'email' === $net ? 'mail' : ( 'website' === $net ? 'globe' : ( 'phone' === $net ? 'phone' : 'link' ) ) ) );
			$html .= sprintf(
				'<li><a class="fx-social__link fx-social--%1$s" href="%2$s" aria-label="%3$s"%4$s>%5$s</a></li>',
				esc_attr( $net ),
				esc_url( $url ),
				esc_attr( isset( $labels[ $net ] ) ? $labels[ $net ] : ucfirst( $net ) ),
				( 0 === strpos( $url, 'http' ) ) ? ' target="_blank" rel="noopener"' : '',
				$icon
			);
		}
		$cls = array( 'fx-social--' . sanitize_key( isset( $a['variant'] ) ? $a['variant'] : 'circle' ) );
		if ( ! empty( $a['brandColors'] ) ) {
			$cls[] = 'has-brand-colors';
		}
		return '<ul ' . self::wrapper( $a, $cls ) . '>' . $html . '</ul>';
	}
}

class Form extends Base {
	const NAME = 'foxi/form';

	protected static function markup( $a, $content, $block ) {
		$fields  = isset( $a['fields'] ) && is_array( $a['fields'] ) ? $a['fields'] : array();
		$form_id = \Foxi\Style::clean_id( foxi_get( $a, 'fx.id', '' ) );
		$hidden  = isset( $a['labels'] ) && 'hidden' === $a['labels'];
		$html    = '';

		foreach ( $fields as $i => $f ) {
			$fid   = sanitize_key( isset( $f['id'] ) ? $f['id'] : 'field' . $i );
			$type  = in_array( isset( $f['type'] ) ? $f['type'] : 'text', array( 'text', 'email', 'tel', 'textarea', 'select', 'date', 'number', 'checkbox' ), true ) ? $f['type'] : 'text';
			$label = isset( $f['label'] ) ? (string) $f['label'] : '';
			$req   = ! empty( $f['required'] );
			$ph    = isset( $f['placeholder'] ) ? (string) $f['placeholder'] : ( $hidden ? $label : '' );
			$dom   = 'fx-' . $form_id . '-' . $fid;
			$attrs = sprintf( 'id="%1$s" name="%2$s" class="fx-form__input"%3$s%4$s', esc_attr( $dom ), esc_attr( $fid ), $req ? ' required' : '', $ph ? ' placeholder="' . esc_attr( $ph ) . '"' : '' );
			$lab   = sprintf( '<label class="fx-form__label%1$s" for="%2$s">%3$s%4$s</label>', $hidden ? ' screen-reader-text' : '', esc_attr( $dom ), esc_html( $label ), $req ? ' <span class="fx-form__req" aria-hidden="true">*</span>' : '' );

			if ( 'textarea' === $type ) {
				$control = '<textarea ' . $attrs . ' rows="5"></textarea>';
			} elseif ( 'select' === $type ) {
				$opts    = array_filter( array_map( 'trim', explode( "\n", isset( $f['options'] ) ? (string) $f['options'] : '' ) ) );
				$control = '<select ' . $attrs . '><option value="">' . esc_html__( 'Choose…', 'foxi-builder' ) . '</option>';
				foreach ( $opts as $o ) {
					$control .= '<option>' . esc_html( $o ) . '</option>';
				}
				$control .= '</select>';
			} elseif ( 'checkbox' === $type ) {
				$html .= sprintf(
					'<div class="fx-form__field fx-form__field--full fx-form__field--checkbox"><label class="fx-form__check"><input type="checkbox" name="%1$s" value="1"%2$s> <span>%3$s</span></label></div>',
					esc_attr( $fid ),
					$req ? ' required' : '',
					esc_html( $label )
				);
				continue;
			} else {
				$control = '<input type="' . esc_attr( $type ) . '" ' . $attrs . ( 'email' === $type ? ' autocomplete="email"' : ( 'tel' === $type ? ' autocomplete="tel"' : ( 'name' === $fid ? ' autocomplete="name"' : '' ) ) ) . '>';
			}
			$width = isset( $f['width'] ) && 'half' === $f['width'] ? 'half' : 'full';
			$html .= '<div class="fx-form__field fx-form__field--' . $width . '">' . $lab . $control . '</div>';
		}

		$post_id = get_the_ID();
		if ( ! empty( $GLOBALS['foxi_rendering_part'] ) ) {
			$post_id = (int) $GLOBALS['foxi_rendering_part'];
		}

		return sprintf(
			'<form %1$s method="post" novalidate><div class="fx-form__fields">%2$s</div><div class="fx-hp" aria-hidden="true"><label>Website<input type="text" name="fx_website" tabindex="-1" autocomplete="off"></label></div><input type="hidden" name="_fx_form" value="%3$s"><input type="hidden" name="_fx_t" value="%4$s"><button type="submit" class="fx-form__submit fx-button fx-button--primary fx-button--m"><span class="fx-button__text">%5$s</span></button><div class="fx-form__status" role="status" aria-live="polite" data-success="%6$s"></div></form>',
			self::wrapper( $a, array(), array( 'data-fx-form' => true ) ),
			$html,
			esc_attr( absint( $post_id ) . ':' . $form_id ),
			esc_attr( time() ),
			esc_html( isset( $a['submitText'] ) ? $a['submitText'] : __( 'Send message', 'foxi-builder' ) ),
			esc_attr( isset( $a['successMessage'] ) ? $a['successMessage'] : '' )
		);
	}
}

class Booking extends Base {
	const NAME = 'foxi/booking';

	protected static function markup( $a, $content, $block ) {
		$labels = wp_parse_args( isset( $a['labels'] ) && is_array( $a['labels'] ) ? $a['labels'] : array(), array( 'arrival' => 'Arrival', 'departure' => 'Departure', 'guests' => 'Guests' ) );
		$url    = \Foxi\Tokens::url( isset( $a['url'] ) ? $a['url'] : '' );
		if ( ! $url ) {
			$contact = get_page_by_path( 'contact' );
			$url     = $contact ? get_permalink( $contact ) : home_url( '/' );
		}
		$uid   = \Foxi\Style::clean_id( foxi_get( $a, 'fx.id', 'b' ) );
		$today = gmdate( 'Y-m-d' );
		$icons = ! empty( $a['icons'] );
		$field = static function ( $key, $label, $control ) use ( $uid, $icons ) {
			$icon = '';
			if ( $icons ) {
				$icon = '<span class="fx-booking__icon" aria-hidden="true">' . foxi_icon( 'g' === $key ? 'lucide:users' : 'lucide:calendar-days' ) . '</span>';
			}
			return sprintf( '<div class="fx-booking__field%5$s">%6$s<div class="fx-booking__fieldbody"><label class="fx-booking__label" for="fx-bk-%1$s-%2$s">%3$s</label>%4$s</div></div>', esc_attr( $uid ), esc_attr( $key ), esc_html( $label ), $control, $icon ? ' has-icon' : '', $icon );
		};
		$html  = $field( 'a', $labels['arrival'], sprintf( '<input class="fx-booking__input" type="date" id="fx-bk-%1$s-a" name="%2$s" min="%3$s" required>', esc_attr( $uid ), esc_attr( isset( $a['arrivalParam'] ) ? $a['arrivalParam'] : 'arrival' ), esc_attr( $today ) ) );
		$html .= $field( 'd', $labels['departure'], sprintf( '<input class="fx-booking__input" type="date" id="fx-bk-%1$s-d" name="%2$s" min="%3$s" required>', esc_attr( $uid ), esc_attr( isset( $a['departureParam'] ) ? $a['departureParam'] : 'departure' ), esc_attr( $today ) ) );
		if ( ! empty( $a['showGuests'] ) ) {
			$max  = max( 1, min( 30, (int) ( isset( $a['maxGuests'] ) ? $a['maxGuests'] : 6 ) ) );
			$opts = '';
			for ( $g = 1; $g <= $max; $g++ ) {
				$text  = ! empty( $a['guestsWord'] ) ? sprintf( /* translators: %d number of guests */ _n( '%d Guest', '%d Guests', $g, 'foxi-builder' ), $g ) : (string) $g;
				$opts .= '<option value="' . $g . '"' . ( 2 === $g ? ' selected' : '' ) . '>' . esc_html( $text ) . '</option>';
			}
			$html .= $field( 'g', $labels['guests'], sprintf( '<select class="fx-booking__input" id="fx-bk-%1$s-g" name="%2$s">%3$s</select>', esc_attr( $uid ), esc_attr( isset( $a['guestsParam'] ) ? $a['guestsParam'] : 'guests' ), $opts ) );
		}
		$btn_icon = ! empty( $a['buttonIcon'] ) ? '<span class="fx-button__icon" aria-hidden="true">' . foxi_icon( $a['buttonIcon'] ) . '</span>' : '';
		$html    .= '<button type="submit" class="fx-booking__submit fx-button fx-button--primary fx-button--m"><span class="fx-button__text">' . esc_html( isset( $a['buttonText'] ) ? $a['buttonText'] : __( 'Check availability', 'foxi-builder' ) ) . '</span>' . $btn_icon . '</button>';

		return sprintf(
			'<form %1$s action="%2$s" method="get"%3$s>%4$s</form>',
			self::wrapper( $a, array_filter( array( $icons ? 'fx-booking--icons' : '', ! empty( $a['dividers'] ) ? 'fx-booking--dividers' : '' ) ), array_filter( array( 'data-fx-booking' => true, 'data-fx-prefill' => ! empty( $a['prefillDates'] ) ) ) ),
			esc_url( $url ),
			! empty( $a['newTab'] ) ? ' target="_blank"' : '',
			$html
		);
	}
}
