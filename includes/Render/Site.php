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

	const LOOKS = array( 'bar', 'card', 'glass', 'boxed', 'outline', 'solid', 'minimal' );

	protected static function markup( $a, $content, $block ) {
		$labels = wp_parse_args(
			isset( $a['labels'] ) && is_array( $a['labels'] ) ? array_filter( $a['labels'], 'strlen' ) : array(),
			array(
				'arrival'   => __( 'Arrival', 'foxi-builder' ),
				'departure' => __( 'Departure', 'foxi-builder' ),
				'dates'     => __( 'Check-in – Check-out', 'foxi-builder' ),
				'guests'    => __( 'Guests', 'foxi-builder' ),
				'adults'    => __( 'Adults', 'foxi-builder' ),
				'children'  => __( 'Children', 'foxi-builder' ),
				'rooms'     => __( 'Rooms', 'foxi-builder' ),
				'promo'     => __( 'Promo code', 'foxi-builder' ),
			)
		);
		$ph     = wp_parse_args(
			isset( $a['placeholders'] ) && is_array( $a['placeholders'] ) ? array_filter( $a['placeholders'], 'strlen' ) : array(),
			array(
				'date'  => __( 'Select date', 'foxi-builder' ),
				'dates' => __( 'Add dates', 'foxi-builder' ),
				'promo' => __( 'Enter code', 'foxi-builder' ),
			)
		);
		$url    = \Foxi\Tokens::url( isset( $a['url'] ) ? $a['url'] : '' );
		if ( ! $url ) {
			$contact = get_page_by_path( 'contact' );
			$url     = $contact ? get_permalink( $contact ) : home_url( '/' );
		}
		$engine = \Foxi\BookingEngine::resolve( $a );
		$p      = $engine['params'];

		$opt = static function ( $key, $allowed, $default ) use ( $a ) {
			return isset( $a[ $key ] ) && in_array( $a[ $key ], $allowed, true ) ? $a[ $key ] : $default;
		};
		$look        = $opt( 'look', self::LOOKS, 'bar' );
		$layout      = $opt( 'layout', array( 'bar', 'stacked', 'compact' ), 'bar' );
		$mobile      = $opt( 'mobileLayout', array( 'stack', 'compact' ), 'stack' );
		$align       = $opt( 'align', array( 'stretch', 'left', 'center', 'right' ), 'stretch' );
		$fixed       = $opt( 'fixed', array( 'none', 'bottom', 'mobile' ), 'none' );
		$dates       = $opt( 'dates', array( 'two', 'range', 'none' ), 'two' );
		$guest_mode  = isset( $a['showGuests'] ) && ! $a['showGuests'] ? 'none' : $opt( 'guestMode', array( 'total', 'split', 'none' ), 'total' );
		$guest_ui    = $opt( 'guestUI', array( 'select', 'popover', 'stepper' ), 'select' );
		$label_style = $opt( 'labelStyle', array( 'above', 'hidden' ), 'above' );
		$icon_style  = $opt( 'iconStyle', array( 'none', 'beside', 'inside' ), ! empty( $a['icons'] ) ? 'beside' : 'none' );
		$display     = $opt( 'displayFormat', array( 'short', 'long', 'dmy', 'mdy', 'iso' ), 'short' );

		$uid    = \Foxi\Style::clean_id( foxi_get( $a, 'fx.id', 'b' ) );
		$today  = wp_date( 'Y-m-d' );
		$hidden = 'hidden' === $label_style;
		$icons  = array(
			'arrival'   => 'lucide:calendar-days',
			'departure' => 'lucide:calendar-days',
			'dates'     => 'lucide:calendar-days',
			'guests'    => 'lucide:users',
			'adults'    => 'lucide:users',
			'children'  => 'lucide:baby',
			'rooms'     => 'lucide:bed-double',
			'promo'     => 'lucide:tag',
		);

		/* One field: label + control, with the chosen icon placement. */
		$field = static function ( $key, $control, $for = '' ) use ( $uid, $labels, $icons, $icon_style, $hidden ) {
			$icon   = '<span class="fx-booking__icon" aria-hidden="true">' . foxi_icon( $icons[ $key ] ) . '</span>';
			$for    = $for ? $for : "fx-bk-$uid-$key";
			$label  = sprintf( '<label class="fx-booking__label%s" for="%s">%s</label>', $hidden ? ' fx-sr-only' : '', esc_attr( $for ), esc_html( $labels[ $key ] ) );
			$inside = 'inside' === $icon_style ? $icon : '';
			return sprintf(
				'<div class="fx-booking__field fx-booking__field--%1$s%2$s">%3$s<div class="fx-booking__fieldbody">%4$s<div class="fx-booking__control">%5$s%6$s</div></div></div>',
				esc_attr( $key ),
				'beside' === $icon_style ? ' has-icon' : '',
				'beside' === $icon_style ? $icon : '',
				$label,
				$control,
				$inside
			);
		};
		// Editor preview (no front-end script): draw the fields as visitors see them.
		$preview    = defined( 'REST_REQUEST' ) && REST_REQUEST;
		$fake       = static function ( $text ) {
			return '<span class="fx-booking__input fx-booking__trigger"><span class="fx-booking__value is-empty">' . esc_html( $text ) . '</span></span>';
		};
		$date_input = static function ( $role, $param, $placeholder, $label ) use ( $uid, $today, $preview, $fake ) {
			if ( $preview ) {
				return $fake( $placeholder );
			}
			return sprintf(
				'<input class="fx-booking__input fx-booking__date" type="date" id="fx-bk-%1$s-%2$s" name="%3$s" min="%4$s" data-role="%2$s" data-placeholder="%5$s" aria-label="%6$s" required>',
				esc_attr( $uid ),
				esc_attr( $role ),
				esc_attr( $param ),
				esc_attr( $today ),
				esc_attr( $placeholder ),
				esc_attr( $label )
			);
		};
		/* A number of guests/rooms: dropdown or plus/minus. */
		$count = static function ( $key, $param, $min, $max, $value ) use ( $uid, $guest_ui, $a ) {
			$id = "fx-bk-$uid-$key";
			if ( 'stepper' === $guest_ui ) {
				return sprintf(
					'<div class="fx-booking__stepper"><button type="button" class="fx-booking__step" data-step="-1" aria-label="%6$s">%8$s</button><input class="fx-booking__input fx-booking__num" type="number" id="%1$s" name="%2$s" min="%3$d" max="%4$d" value="%5$d" inputmode="numeric" data-role="%9$s"><button type="button" class="fx-booking__step" data-step="1" aria-label="%7$s">%10$s</button></div>',
					esc_attr( $id ),
					esc_attr( $param ),
					$min,
					$max,
					$value,
					esc_attr__( 'Fewer', 'foxi-builder' ),
					esc_attr__( 'More', 'foxi-builder' ),
					foxi_icon( 'lucide:minus' ),
					esc_attr( $key ),
					foxi_icon( 'lucide:plus' )
				);
			}
			$opts = '';
			for ( $n = $min; $n <= $max; $n++ ) {
				$text = (string) $n;
				if ( ! empty( $a['guestsWord'] ) ) {
					$words = array(
						/* translators: %d: number of guests */
						'guests'   => _n( '%d Guest', '%d Guests', $n, 'foxi-builder' ),
						/* translators: %d: number of adults */
						'adults'   => _n( '%d Adult', '%d Adults', $n, 'foxi-builder' ),
						/* translators: %d: number of children */
						'children' => _n( '%d Child', '%d Children', $n, 'foxi-builder' ),
						/* translators: %d: number of rooms */
						'rooms'    => _n( '%d Room', '%d Rooms', $n, 'foxi-builder' ),
					);
					$text  = sprintf( $words[ $key ], $n );
				}
				$opts .= sprintf( '<option value="%1$d"%2$s>%3$s</option>', $n, selected( $n, $value, false ), esc_html( $text ) );
			}
			return sprintf( '<select class="fx-booking__input fx-booking__select" id="%1$s" name="%2$s" data-role="%3$s">%4$s</select>', esc_attr( $id ), esc_attr( $param ), esc_attr( $key ), $opts );
		};

		$max_guests   = max( 1, min( 30, (int) ( isset( $a['maxGuests'] ) ? $a['maxGuests'] : 6 ) ) );
		$max_children = max( 0, min( 20, (int) ( isset( $a['maxChildren'] ) ? $a['maxChildren'] : 4 ) ) );
		$max_rooms    = max( 1, min( 20, (int) ( isset( $a['maxRooms'] ) ? $a['maxRooms'] : 5 ) ) );
		$default_g    = min( 2, $max_guests );

		$html = '';
		if ( 'two' === $dates ) {
			$html .= $field( 'arrival', $date_input( 'arrival', $p['arrival'], $hidden ? $labels['arrival'] : $ph['date'], $labels['arrival'] ) );
			$html .= $field( 'departure', $date_input( 'departure', $p['departure'], $hidden ? $labels['departure'] : $ph['date'], $labels['departure'] ) );
		} elseif ( 'range' === $dates ) {
			$placeholder = $hidden ? $labels['dates'] : $ph['dates'];
			$html       .= $field(
				'dates',
				$preview ? $fake( $placeholder ) : '<div class="fx-booking__range">' . $date_input( 'arrival', $p['arrival'], $placeholder, $labels['arrival'] ) . '<span class="fx-booking__range-sep" aria-hidden="true">–</span>' . $date_input( 'departure', $p['departure'], $placeholder, $labels['departure'] ) . '</div>',
				"fx-bk-$uid-arrival"
			);
		}

		$counts = array();
		if ( 'total' === $guest_mode ) {
			$counts['guests'] = array( $p['guests'], 1, $max_guests, $default_g );
		} elseif ( 'split' === $guest_mode ) {
			$counts['adults']   = array( $p['adults'], 1, $max_guests, $default_g );
			$counts['children'] = array( $p['children'], 0, $max_children, 0 );
		}
		if ( ! empty( $a['rooms'] ) && $p['rooms'] ) {
			$counts['rooms'] = array( $p['rooms'], 1, $max_rooms, 1 );
		}
		if ( $counts ) {
			if ( 'popover' === $guest_ui ) {
				// One "Guests" field; the script turns the dropdowns into a pop-up counter.
				$inner = '';
				foreach ( $counts as $key => $c ) {
					$inner .= $count( $key, $c[0], $c[1], $c[2], $c[3] );
				}
				if ( $preview ) {
					$parts = array();
					$words = array(
						/* translators: %d: number */
						'guests'   => _n( '%d Guest', '%d Guests', 2, 'foxi-builder' ),
						/* translators: %d: number */
						'adults'   => _n( '%d Adult', '%d Adults', 2, 'foxi-builder' ),
						/* translators: %d: number */
						'rooms'    => _n( '%d Room', '%d Rooms', 1, 'foxi-builder' ),
					);
					foreach ( $counts as $key => $c ) {
						if ( isset( $words[ $key ] ) ) {
							$parts[] = sprintf( $words[ $key ], $c[3] );
						}
					}
					$inner = str_replace( 'is-empty', '', $fake( implode( ', ', $parts ) ) ) . '<div class="fx-booking__party" hidden>' . $inner . '</div>';
				}
				$first  = array_keys( $counts )[0];
				$html  .= str_replace( 'fx-booking__field--guests', 'fx-booking__field--guests fx-booking__field--party', $field( 'guests', '<div class="fx-booking__party">' . $inner . '</div>', "fx-bk-$uid-$first" ) );
			} else {
				foreach ( $counts as $key => $c ) {
					$html .= $field( $key, $count( $key, $c[0], $c[1], $c[2], $c[3] ) );
				}
			}
		}
		if ( ! empty( $a['promo'] ) && $p['promo'] ) {
			$html .= $field( 'promo', sprintf( '<input class="fx-booking__input fx-booking__text" type="text" id="fx-bk-%1$s-promo" name="%2$s" placeholder="%3$s" autocomplete="off">', esc_attr( $uid ), esc_attr( $p['promo'] ), esc_attr( $hidden ? $labels['promo'] : $ph['promo'] ) ) );
		}
		if ( $p['nights'] && 'none' !== $dates ) {
			$html .= sprintf( '<input type="hidden" name="%s" data-role="nights" value="">', esc_attr( $p['nights'] ) );
		}

		$button_text = isset( $a['buttonText'] ) && '' !== $a['buttonText'] ? $a['buttonText'] : __( 'Check availability', 'foxi-builder' );
		$btn_icon    = ! empty( $a['buttonIcon'] ) ? '<span class="fx-button__icon" aria-hidden="true">' . foxi_icon( $a['buttonIcon'] ) . '</span>' : '';
		$html       .= '<div class="fx-booking__actions"><button type="submit" class="fx-booking__submit fx-button fx-button--primary fx-button--m"><span class="fx-button__text">' . esc_html( $button_text ) . '</span>' . $btn_icon . '</button></div>';

		$config = array(
			'dateFormat' => $engine['dateFormat'],
			'display'    => $display,
			'minNights'  => max( 1, min( 60, (int) ( isset( $a['minNights'] ) ? $a['minNights'] : 1 ) ) ),
			'prefill'    => ! empty( $a['prefillDates'] ),
			'fixed'      => $fixed,
			'guestUI'    => $guest_ui,
			'words'      => ! empty( $a['guestsWord'] ),
			'labels'     => $labels,
			'i18n'       => array(
				'clear'    => __( 'Clear', 'foxi-builder' ),
				'done'     => __( 'Done', 'foxi-builder' ),
				'close'    => __( 'Close', 'foxi-builder' ),
				'prev'     => __( 'Previous month', 'foxi-builder' ),
				'next'     => __( 'Next month', 'foxi-builder' ),
				'arrival'  => __( 'Select your arrival date', 'foxi-builder' ),
				'depart'   => __( 'Select your departure date', 'foxi-builder' ),
				/* translators: %d: number of nights */
				'night'    => _n( '%d night', '%d nights', 1, 'foxi-builder' ),
				/* translators: %d: number of nights */
				'nights'   => _n( '%d night', '%d nights', 2, 'foxi-builder' ),
				/* translators: %d: minimum number of nights */
				'minStay'  => __( 'Minimum stay: %d nights', 'foxi-builder' ),
				'guests'   => array( _n( '%d Guest', '%d Guests', 1, 'foxi-builder' ), _n( '%d Guest', '%d Guests', 2, 'foxi-builder' ) ),
				'adults'   => array( _n( '%d Adult', '%d Adults', 1, 'foxi-builder' ), _n( '%d Adult', '%d Adults', 2, 'foxi-builder' ) ),
				'children' => array( _n( '%d Child', '%d Children', 1, 'foxi-builder' ), _n( '%d Child', '%d Children', 2, 'foxi-builder' ) ),
				'rooms'    => array( _n( '%d Room', '%d Rooms', 1, 'foxi-builder' ), _n( '%d Room', '%d Rooms', 2, 'foxi-builder' ) ),
				'adultsHint'   => __( 'Ages 13 and over', 'foxi-builder' ),
				'childrenHint' => __( 'Ages 0 to 12', 'foxi-builder' ),
			),
		);

		/* Quick colours, corners and glass blur become CSS variables. */
		$vars   = array();
		$colors = isset( $a['colors'] ) && is_array( $a['colors'] ) ? $a['colors'] : array();
		$map    = array(
			'panel'      => '--bk-panel',
			'text'       => '--bk-text',
			'label'      => '--bk-label',
			'field'      => '--bk-field',
			'fieldText'  => '--bk-field-text',
			'border'     => '--bk-border',
			'accent'     => '--bk-accent',
			'accentText' => '--bk-accent-text',
		);
		foreach ( $map as $key => $var ) {
			$c = isset( $colors[ $key ] ) ? preg_replace( '/[^#a-zA-Z0-9(),.%\-\s]/', '', (string) $colors[ $key ] ) : '';
			if ( '' !== trim( $c ) ) {
				$vars[] = $var . ':' . trim( $c );
			}
		}
		if ( isset( $a['radius'] ) && '' !== (string) $a['radius'] ) {
			$vars[] = '--bk-radius:' . max( 0, min( 999, (int) $a['radius'] ) ) . 'px';
		}
		if ( 'glass' === $look && isset( $a['blur'] ) ) {
			$vars[] = '--bk-blur:' . max( 0, min( 60, (int) $a['blur'] ) ) . 'px';
		}
		$overlap = isset( $a['overlap'] ) ? max( 0, min( 400, (int) $a['overlap'] ) ) : 0;
		if ( $overlap ) {
			$vars[] = '--bk-overlap:' . $overlap . 'px';
		}

		$classes = array(
			'fx-booking--look-' . $look,
			'fx-booking--layout-' . $layout,
			'fx-booking--m-' . $mobile,
			'fx-booking--align-' . $align,
			'fx-booking--icons-' . $icon_style,
			$hidden ? 'fx-booking--labels-hidden' : '',
			! empty( $a['dividers'] ) ? 'fx-booking--dividers' : '',
			$overlap ? 'fx-booking--overlap' : '',
			'none' !== $fixed ? 'fx-booking--fixable' : '',
		);
		$attrs   = array( 'data-fx-booking' => wp_json_encode( $config ) );
		if ( $vars ) {
			$attrs['style'] = implode( ';', $vars );
		}

		$open = sprintf(
			'<button type="button" class="fx-booking__open fx-button fx-button--primary fx-button--m" aria-haspopup="dialog"><span class="fx-button__text">%1$s</span>%2$s</button>',
			esc_html( $button_text ),
			$btn_icon
		);

		return sprintf(
			'<div %1$s>%2$s<form class="fx-booking__form" action="%3$s" method="get"%4$s>%5$s</form></div>',
			self::wrapper( $a, array_filter( $classes ), $attrs ),
			$open,
			esc_url( $url ),
			! empty( $a['newTab'] ) ? ' target="_blank"' : '',
			$html
		);
	}
}
