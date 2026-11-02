<?php
/**
 * Social Feed block: recent Instagram / Facebook posts, or posts added by hand.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Render;

defined( 'ABSPATH' ) || exit;

class SocialFeed extends SwiperBase {
	const NAME = 'foxi/social-feed';

	const DESIGNS = array( 'grid', 'cards', 'carousel', 'masonry', 'collage', 'strip' );

	protected static function markup( $a, $content, $block ) {
		$design = in_array( foxi_get( $a, 'design' ), self::DESIGNS, true ) ? $a['design'] : 'grid';
		$source = in_array( foxi_get( $a, 'source' ), array( 'instagram', 'facebook', 'both', 'manual' ), true ) ? $a['source'] : 'instagram';
		$limit  = max( 1, min( 24, (int) foxi_get( $a, 'limit', 6 ) ) );
		$note   = '';

		if ( 'manual' === $source ) {
			$posts = self::manual( isset( $a['manual'] ) && is_array( $a['manual'] ) ? $a['manual'] : array() );
		} else {
			$posts = \Foxi\Social::posts( $source, 'collage' === $design ? $limit + 6 : $limit );
			if ( is_wp_error( $posts ) ) {
				if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
					$note  = '<div class="fx-social__setup">' . esc_html( $posts->get_error_message() ) . ' ' . esc_html__( 'Showing sample posts until then.', 'foxi-builder' ) . '</div>';
					$posts = self::sample();
				} else {
					return current_user_can( 'edit_pages' ) ? '<!-- Foxi Social Feed: ' . esc_html( $posts->get_error_message() ) . ' -->' : '';
				}
			}
		}
		if ( 'collage' === $design && 'manual' !== $source ) {
			// The big first post takes 4 spaces; pick a count that fills the last row.
			$cols  = max( 3, (int) foxi_get( isset( $a['columns'] ) && is_array( $a['columns'] ) ? $a['columns'] : array(), 'desktop', 3 ) );
			$avail = count( $posts );
			$fit   = static function ( $n ) use ( $cols ) {
				return 0 === ( $n + 3 ) % $cols;
			};
			for ( $n = $limit; $n <= $avail && ! $fit( $n ); $n++ ) {
				continue;
			}
			if ( $n > $avail || ! $fit( $n ) ) {
				for ( $n = min( $limit, $avail ); $n > 3 && ! $fit( $n ); $n-- ) {
					continue;
				}
			}
			$limit = $n;
		}
		$posts = array_slice( $posts, 0, $limit );
		if ( ! $posts ) {
			return current_user_can( 'edit_pages' ) ? '<!-- Foxi Social Feed: no posts to show yet. -->' : '';
		}

		$hover = in_array( foxi_get( $a, 'hover' ), array( 'caption', 'icon', 'zoom', 'none' ), true ) ? $a['hover'] : 'caption';
		$items = '';
		foreach ( $posts as $i => $p ) {
			$items .= 'cards' === $design ? self::card( $p, $a ) : self::tile( $p, $a, $hover, 'carousel' === $design, 'collage' === $design && 0 === $i );
		}

		$cols = isset( $a['columns'] ) && is_array( $a['columns'] ) ? $a['columns'] : array();
		$d    = max( 1, min( 8, (int) foxi_get( $cols, 'desktop', 3 ) ) );
		$t    = max( 1, min( 8, (int) foxi_get( $cols, 'tablet', 3 ) ) );
		$m    = max( 1, min( 4, (int) foxi_get( $cols, 'mobile', 2 ) ) );
		$gap  = 'strip' === $design ? 0 : max( 0, min( 60, (int) foxi_get( $a, 'gap', 8 ) ) );
		$ratio = array(
			'square'    => '1 / 1',
			'portrait'  => '4 / 5',
			'landscape' => '4 / 3',
		);
		$ratio = isset( $ratio[ foxi_get( $a, 'ratio' ) ] ) ? $ratio[ $a['ratio'] ] : '1 / 1';
		$c     = isset( $a['colors'] ) && is_array( $a['colors'] ) ? $a['colors'] : array();
		$vars  = sprintf( '--sf-cols:%d;--sf-cols-t:%d;--sf-cols-m:%d;--sf-gap:%dpx;--sf-ratio:%s;--sf-lines:%d', 'strip' === $design ? max( $d, 6 ) : $d, $t, $m, $gap, $ratio, max( 1, min( 10, (int) foxi_get( $a, 'captionLines', 4 ) ) ) );
		foreach ( array( 'overlay' => '--sf-overlay', 'text' => '--sf-text' ) as $k => $var ) {
			$v = isset( $c[ $k ] ) ? \Foxi\Scrollbar::color( $c[ $k ], '' ) : '';
			if ( $v ) {
				$vars .= ';' . $var . ':' . $v;
			}
		}

		if ( 'carousel' === $design ) {
			$opts = array(
				'perView'      => array( 'desktop' => $d, 'tablet' => $t, 'mobile' => $m ),
				'gap'          => array( 'desktop' => $gap, 'tablet' => $gap, 'mobile' => min( $gap, 8 ) ),
				'autoplay'     => ! empty( $a['autoplay'] ),
				'delay'        => 4000,
				'speed'        => 600,
				'loop'         => count( $posts ) > $d,
				'arrows'       => ! empty( $a['arrows'] ),
				'dots'         => false,
				'pauseOnHover' => true,
			);
			wp_enqueue_script( 'foxi-swiper' );
			wp_enqueue_style( 'foxi-swiper' );
			$body = sprintf(
				'<div class="fx-carousel fx-social__track%1$s" data-fx-swiper="%2$s"><div class="swiper"><div class="swiper-wrapper">%3$s</div></div>%4$s</div>',
				count( $posts ) <= $d ? ' fx-social__track--fits' : '',
				esc_attr( wp_json_encode( $opts ) ),
				$items,
				! empty( $a['arrows'] ) ? static::arrows() : ''
			);
		} else {
			$body = '<div class="fx-social__items">' . $items . '</div>';
		}

		return sprintf(
			'<div %1$s>%2$s%3$s%4$s</div>',
			self::wrapper( $a, array( 'fx-social', 'fx-social--' . $design, 'fx-social--hover-' . $hover ), array( 'style' => $vars ) ),
			$note,
			! empty( $a['header'] ) ? self::head( $a, $source, $posts ) : '',
			$body
		);
	}

	private static function label( $net ) {
		$names = array(
			'instagram' => 'Instagram',
			'facebook'  => 'Facebook',
			'linkedin'  => 'LinkedIn',
		);
		return isset( $names[ $net ] ) ? $names[ $net ] : __( 'Social media', 'foxi-builder' );
	}

	private static function icon( $net ) {
		return in_array( $net, array( 'instagram', 'facebook', 'linkedin' ), true ) ? foxi_icon( 'lucide:' . $net ) : foxi_icon( 'lucide:share-2' );
	}

	private static function short( $text, $words = 14 ) {
		return wp_trim_words( wp_strip_all_tags( (string) $text ), $words, '…' );
	}

	private static function post_link( $p ) {
		/* translators: 1: network, 2: start of the caption */
		$label = sprintf( __( '%1$s post: %2$s (opens in a new tab)', 'foxi-builder' ), self::label( $p['network'] ), $p['caption'] ? self::short( $p['caption'], 12 ) : __( 'photo', 'foxi-builder' ) );
		return $p['url'] ? ' href="' . esc_url( $p['url'] ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $label ) . '"' : '';
	}

	private static function badge( $p ) {
		if ( ! empty( $p['video'] ) ) {
			return '<span class="fx-social__badge" aria-hidden="true">' . foxi_icon( 'lucide:play' ) . '</span>';
		}
		if ( ! empty( $p['album'] ) ) {
			return '<span class="fx-social__badge" aria-hidden="true">' . foxi_icon( 'lucide:copy' ) . '</span>';
		}
		return '';
	}

	private static function post_img( $p ) {
		return sprintf( '<img class="fx-social__img" src="%1$s" alt="%2$s" loading="lazy" decoding="async">', esc_url( $p['image'] ), esc_attr( $p['alt'] ?? self::short( $p['caption'], 16 ) ) );
	}

	private static function tile( $p, $a, $hover, $slide, $big ) {
		$tag     = $p['url'] ? 'a' : 'div';
		$overlay = '';
		if ( 'caption' === $hover || 'icon' === $hover ) {
			$overlay = '<span class="fx-social__overlay">'
				. ( ! empty( $a['showIcon'] ) || 'icon' === $hover ? '<span class="fx-social__net">' . self::icon( $p['network'] ) . '</span>' : '' )
				. ( 'caption' === $hover && $p['caption'] ? '<span class="fx-social__caption">' . esc_html( $p['caption'] ) . '</span>' : '' )
				. '</span>';
		}
		return sprintf(
			'<%1$s class="fx-social__item%2$s"%3$s>%4$s%5$s%6$s</%1$s>',
			$tag,
			( $slide ? ' swiper-slide' : '' ) . ( $big ? ' is-big' : '' ),
			'a' === $tag ? self::post_link( $p ) : '',
			self::post_img( $p ),
			self::badge( $p ),
			$overlay
		);
	}

	private static function card( $p, $a ) {
		$date = ! empty( $a['showDate'] ) && $p['date'] ? '<time datetime="' . esc_attr( $p['date'] ) . '">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $p['date'] ) ) ) . '</time>' : '';
		return sprintf(
			'<article class="fx-social__card"><a class="fx-social__item"%1$s>%2$s%3$s</a><div class="fx-social__body">%4$s<p class="fx-social__caption">%5$s</p>%6$s</div></article>',
			self::post_link( $p ),
			self::post_img( $p ),
			self::badge( $p ),
			! empty( $a['showIcon'] ) || $date ? '<div class="fx-social__meta">' . ( ! empty( $a['showIcon'] ) ? '<span class="fx-social__net">' . self::icon( $p['network'] ) . '</span>' : '' ) . $date . '</div>' : '',
			esc_html( $p['caption'] ),
			$p['url'] ? '<a class="fx-social__more" href="' . esc_url( $p['url'] ) . '" target="_blank" rel="noopener">' . esc_html( sprintf( /* translators: %s: network */ __( 'View on %s', 'foxi-builder' ), self::label( $p['network'] ) ) ) . '</a>' : ''
		);
	}

	private static function head( $a, $source, $posts ) {
		$s    = \Foxi\Social::status();
		$user = 'facebook' === $source ? $s['facebook']['page'] : $s['instagram']['username'];
		$url  = ! empty( $a['followUrl'] ) ? $a['followUrl'] : ( 'facebook' !== $source && $user ? 'https://www.instagram.com/' . rawurlencode( $user ) . '/' : ( $posts[0]['url'] ?? '' ) );
		$net  = 'manual' === $source ? ( $posts[0]['network'] ?? 'instagram' ) : ( 'facebook' === $source ? 'facebook' : 'instagram' );
		$text = ! empty( $a['headerText'] ) ? $a['headerText'] : ( $user ? ( 'facebook' === $source ? $user : '@' . $user ) : '' );
		return sprintf(
			'<div class="fx-social__head"><span class="fx-social__handle">%1$s<span>%2$s</span></span>%3$s</div>',
			self::icon( $net ),
			esc_html( $text ),
			$url ? '<a class="fx-social__follow fx-button fx-button--primary fx-button--m" href="' . esc_url( $url ) . '" target="_blank" rel="noopener"><span class="fx-button__text">' . esc_html( ! empty( $a['followText'] ) ? $a['followText'] : __( 'Follow us', 'foxi-builder' ) ) . '</span></a>' : ''
		);
	}

	private static function manual( array $list ) {
		$out = array();
		foreach ( $list as $i => $m ) {
			$img = isset( $m['image'] ) && is_array( $m['image'] ) ? $m['image'] : array();
			$url = ! empty( $img['id'] ) ? wp_get_attachment_image_url( (int) $img['id'], 'large' ) : ( isset( $img['url'] ) ? $img['url'] : '' );
			if ( ! $url ) {
				continue;
			}
			$out[] = array(
				'id'      => 'm' . $i,
				'network' => isset( $m['network'] ) ? sanitize_key( $m['network'] ) : 'other',
				'image'   => $url,
				'alt'     => isset( $img['alt'] ) && '' !== $img['alt'] ? $img['alt'] : null,
				'video'   => false,
				'album'   => false,
				'caption' => isset( $m['caption'] ) ? (string) $m['caption'] : '',
				'url'     => isset( $m['url'] ) ? (string) $m['url'] : '',
				'date'    => isset( $m['date'] ) ? (string) $m['date'] : '',
			);
		}
		return $out;
	}

	/** Placeholder posts for the editor before an account is connected. */
	private static function sample() {
		$out   = array();
		$words = array( 'Sunrise over the gardens this morning 🌿', 'Our newly refreshed rooms are ready for you.', 'Weekend special: book direct and save.', 'The pool is calling.', 'Thank you to everyone who joined us this week!', 'Local tip: the markets are on every Saturday.' );
		foreach ( $words as $i => $w ) {
			$out[] = array(
				'id'      => 's' . $i,
				'network' => 'instagram',
				'image'   => FOXI_URL . 'assets/placeholders/placeholder-card-' . ( $i % 4 + 1 ) . '.jpg',
				'video'   => 2 === $i,
				'album'   => 4 === $i,
				'caption' => $w,
				'url'     => 'https://www.instagram.com/',
				'date'    => gmdate( 'c', time() - $i * 3 * DAY_IN_SECONDS ),
			);
		}
		return $out;
	}
}
