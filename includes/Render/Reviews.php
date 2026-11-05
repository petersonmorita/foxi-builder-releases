<?php
/**
 * Google Reviews block.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Render;

defined( 'ABSPATH' ) || exit;

class Reviews extends SwiperBase {
	const NAME = 'foxi/reviews';

	const DESIGNS = array( 'carousel', 'grid', 'masonry', 'spotlight', 'list', 'badge' );

	/** Google's "G" mark, used as the required attribution. */
	public static function g_logo() {
		return '<svg class="fx-g" viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>';
	}

	public static function stars( $rating, $label = true ) {
		$rating = max( 0, min( 5, (float) $rating ) );
		$out    = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$fill = $rating >= $i ? 100 : ( $rating > $i - 1 ? (int) round( ( $rating - $i + 1 ) * 100 ) : 0 );
			$out .= '<span class="fx-star" style="--fill:' . $fill . '%"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.6l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.5l-5.9 3.1 1.2-6.5L2.5 9.5l6.6-.9z"/></svg></span>';
		}
		/* translators: %s: star rating, e.g. 4.5 */
		$aria = $label ? ' role="img" aria-label="' . esc_attr( sprintf( __( 'Rated %s out of 5', 'foxi-builder' ), rtrim( rtrim( number_format( $rating, 1 ), '0' ), '.' ) ) ) . '"' : ' aria-hidden="true"';
		return '<span class="fx-stars"' . $aria . '>' . $out . '</span>';
	}

	protected static function markup( $a, $content, $block ) {
		$preview = defined( 'REST_REQUEST' ) && REST_REQUEST;
		$data    = \Foxi\Reviews::get( isset( $a['placeId'] ) ? $a['placeId'] : '' );
		$note    = '';
		$extra   = self::extra( isset( $a['extra'] ) && is_array( $a['extra'] ) ? $a['extra'] : array() );
		if ( is_wp_error( $data ) && $extra ) {
			// Google isn't connected (or is down): the reviews added by hand still show.
			$data = array( 'name' => '', 'rating' => 0, 'count' => 0, 'url' => '', 'reviewsUrl' => '', 'writeUrl' => '', 'reviews' => array() );
			$a['summary'] = false;
		}
		if ( is_wp_error( $data ) ) {
			if ( ! $preview ) {
				return current_user_can( 'edit_pages' ) ? '<!-- Foxi Google Reviews: ' . esc_html( $data->get_error_message() ) . ' -->' : '';
			}
			$note = '<div class="fx-reviews__setup">' . esc_html( $data->get_error_message() ) . ' ' . esc_html__( 'Showing sample reviews until then.', 'foxi-builder' ) . '</div>';
			$data = \Foxi\Reviews::sample();
		}

		$design = isset( $a['design'] ) && in_array( $a['design'], self::DESIGNS, true ) ? $a['design'] : 'carousel';
		$list   = self::filter( array_merge( $data['reviews'], $extra ), $a );
		$uid    = \Foxi\Style::clean_id( foxi_get( $a, 'fx.id', 'r' ) );

		$vars   = array();
		$colors = isset( $a['colors'] ) && is_array( $a['colors'] ) ? $a['colors'] : array();
		foreach ( array( 'stars' => '--rv-star', 'card' => '--rv-card', 'text' => '--rv-text', 'accent' => '--rv-accent' ) as $k => $var ) {
			$c = isset( $colors[ $k ] ) ? trim( preg_replace( '/[^#a-zA-Z0-9(),.%\-\s]/', '', (string) $colors[ $k ] ) ) : '';
			if ( '' !== $c ) {
				$vars[] = $var . ':' . $c;
			}
		}
		$vars[] = '--rv-lines:' . max( 0, min( 20, (int) ( isset( $a['maxLines'] ) ? $a['maxLines'] : 5 ) ) );
		$attrs  = array( 'style' => implode( ';', $vars ) );
		$align  = isset( $a['align'] ) && in_array( $a['align'], array( 'left', 'center' ), true ) ? $a['align'] : 'left';

		if ( 'badge' === $design ) {
			return sprintf(
				'<div %1$s>%2$s<a class="fx-reviews__badge" href="%3$s" target="_blank" rel="noopener">%4$s<span class="fx-reviews__badge-score">%5$s</span>%6$s<span class="fx-reviews__badge-count">%7$s</span></a></div>',
				self::wrapper( $a, array( 'fx-reviews--badge', 'fx-reviews--align-' . $align ), $attrs ),
				$note,
				esc_url( $data['reviewsUrl'] ? $data['reviewsUrl'] : '#' ),
				self::g_logo(),
				esc_html( number_format_i18n( $data['rating'], 1 ) ),
				self::stars( $data['rating'] ),
				/* translators: %s: number of reviews */
				esc_html( sprintf( _n( '%s Google review', '%s Google reviews', $data['count'], 'foxi-builder' ), number_format_i18n( $data['count'] ) ) )
			);
		}

		$cards = '';
		foreach ( $list as $r ) {
			$cards .= self::card( $r, $a, $design, $uid );
		}
		if ( ! $cards ) {
			$cards = '<p class="fx-reviews__empty">' . esc_html__( 'No reviews match these filters yet.', 'foxi-builder' ) . '</p>';
		}

		if ( 'carousel' === $design || 'spotlight' === $design ) {
			$pv   = isset( $a['perView'] ) && is_array( $a['perView'] ) ? $a['perView'] : array();
			$d    = 'spotlight' === $design ? 1 : max( 1, (float) foxi_get( $pv, 'desktop', 3 ) );
			$t    = 'spotlight' === $design ? 1 : max( 1, (float) foxi_get( $pv, 'tablet', 2 ) );
			$m    = 'spotlight' === $design ? 1 : max( 1, (float) foxi_get( $pv, 'mobile', 1 ) );
			$gap  = max( 0, min( 80, (int) ( isset( $a['gap'] ) ? $a['gap'] : 24 ) ) );
			$opts = array(
				'type'     => 'spotlight' === $design ? 'slider' : 'carousel',
				'effect'   => 'spotlight' === $design ? 'fade' : 'slide',
				'perView'  => array( 'desktop' => $d, 'tablet' => $t, 'mobile' => $m ),
				'gap'      => array( 'desktop' => $gap, 'tablet' => $gap, 'mobile' => min( $gap, 16 ) ),
				'autoplay' => ! empty( $a['autoplay'] ),
				'delay'    => max( 2000, (int) ( isset( $a['delay'] ) ? $a['delay'] : 6000 ) ),
				'speed'    => 600,
				'loop'     => count( $list ) > $d,
				'arrows'   => ! empty( $a['arrows'] ),
				'dots'     => ! empty( $a['dots'] ),
				'pauseOnHover' => true,
			);
			wp_enqueue_script( 'foxi-swiper' );
			wp_enqueue_style( 'foxi-swiper' );
			$body = sprintf(
				'<div class="fx-carousel fx-reviews__track%1$s" data-fx-swiper="%2$s" style="--fx-pv:%3$s;--fx-pv-t:%4$s;--fx-pv-m:%5$s;--fx-cgap:%6$dpx;--fx-cgap-t:%6$dpx;--fx-cgap-m:%7$dpx"><div class="swiper"><div class="swiper-wrapper">%8$s</div></div>%9$s%10$s</div>',
				( 'spotlight' === $design ? ' fx-reviews__track--spotlight' : '' ) . ( count( $list ) <= $d ? ' fx-reviews__track--fits' : '' ),
				esc_attr( wp_json_encode( $opts ) ),
				$d,
				$t,
				$m,
				$gap,
				min( $gap, 16 ),
				$cards,
				! empty( $a['arrows'] ) ? static::arrows() : '',
				! empty( $a['dots'] ) ? '<div class="fx-slider__dots"></div>' : ''
			);
		} else {
			$pv   = isset( $a['perView'] ) && is_array( $a['perView'] ) ? $a['perView'] : array();
			$cols = sprintf( '--rv-cols:%d;--rv-cols-t:%d;--rv-cols-m:%d;--rv-gap:%dpx', max( 1, (int) foxi_get( $pv, 'desktop', 3 ) ), max( 1, (int) foxi_get( $pv, 'tablet', 2 ) ), max( 1, (int) foxi_get( $pv, 'mobile', 1 ) ), max( 0, min( 80, (int) ( isset( $a['gap'] ) ? $a['gap'] : 24 ) ) ) );
			$body = '<div class="fx-reviews__items" style="' . esc_attr( $cols ) . '">' . $cards . '</div>';
		}

		$summary = '';
		if ( ! empty( $a['summary'] ) ) {
			$write    = ! empty( $a['writeButton'] ) && $data['writeUrl'] ? sprintf( '<a class="fx-reviews__write fx-button fx-button--primary fx-button--m" href="%s" target="_blank" rel="noopener"><span class="fx-button__text">%s</span></a>', esc_url( $data['writeUrl'] ), esc_html( isset( $a['writeText'] ) && '' !== $a['writeText'] ? $a['writeText'] : __( 'Write a review', 'foxi-builder' ) ) ) : '';
			$summary  = sprintf(
				'<div class="fx-reviews__summary">%1$s<div class="fx-reviews__overall"><div class="fx-reviews__score"><strong>%2$s</strong>%3$s</div><a class="fx-reviews__count" href="%4$s" target="_blank" rel="noopener">%5$s</a></div>%6$s</div>',
				self::g_logo(),
				esc_html( number_format_i18n( $data['rating'], 1 ) ),
				self::stars( $data['rating'] ),
				esc_url( $data['reviewsUrl'] ? $data['reviewsUrl'] : '#' ),
				/* translators: %s: number of reviews */
				esc_html( sprintf( _n( 'Based on %s Google review', 'Based on %s Google reviews', $data['count'], 'foxi-builder' ), number_format_i18n( $data['count'] ) ) ),
				$write
			);
		}

		return sprintf(
			'<div %1$s>%2$s%3$s%4$s%5$s</div>',
			self::wrapper( $a, array( 'fx-reviews--' . $design, 'fx-reviews--align-' . $align ), array_merge( $attrs, array( 'data-fx-reviews' => true ) ) ),
			$note,
			$summary,
			$body,
			! empty( $a['note'] ) ? '<p class="fx-reviews__note">' . self::notice( $a, count( $list ) ) . '</p>' : ''
		);
	}

	/** Google asks sites to say how reviews are chosen and ordered. */
	private static function notice( $a, $shown ) {
		$parts = array( __( 'Reviews from Google', 'foxi-builder' ) );
		$sort  = isset( $a['sort'] ) ? $a['sort'] : 'relevance';
		$parts[] = 'newest' === $sort ? __( 'newest first', 'foxi-builder' ) : ( 'highest' === $sort ? __( 'highest rated first', 'foxi-builder' ) : __( 'most relevant first', 'foxi-builder' ) );
		$min = (int) ( isset( $a['minRating'] ) ? $a['minRating'] : 0 );
		if ( $min >= 5 ) {
			$parts[] = __( '5-star reviews only', 'foxi-builder' );
		} elseif ( $min > 1 ) {
			/* translators: %d: minimum stars */
			$parts[] = sprintf( __( '%d stars and up', 'foxi-builder' ), $min );
		}
		if ( ! empty( $a['withText'] ) ) {
			$parts[] = __( 'with written comments', 'foxi-builder' );
		}
		return esc_html( implode( ' · ', $parts ) );
	}

	/** Where a review added by hand came from. */
	const SOURCES = array(
		'google'      => 'Google',
		'tripadvisor' => 'Tripadvisor',
		'booking'     => 'Booking.com',
		'expedia'     => 'Expedia',
		'facebook'    => 'Facebook',
		'direct'      => '',
	);

	/** Reviews added by hand in the block, in the same shape as Google's. */
	private static function extra( array $items ) {
		$out = array();
		foreach ( $items as $i => $x ) {
			if ( ! is_array( $x ) || '' === trim( (string) ( $x['text'] ?? '' ) ) && '' === trim( (string) ( $x['author'] ?? '' ) ) ) {
				continue;
			}
			$time  = ! empty( $x['date'] ) && preg_match( '/^\d{4}-\d{2}(-\d{2})?$/', $x['date'] ) ? $x['date'] . ( 7 === strlen( $x['date'] ) ? '-01' : '' ) . 'T00:00:00Z' : '';
			$src   = isset( $x['source'], self::SOURCES[ $x['source'] ] ) ? $x['source'] : 'google';
			$out[] = array(
				'id'         => 'own-' . $i,
				'rating'     => max( 1, min( 5, (int) ( $x['rating'] ?? 5 ) ) ),
				'text'       => (string) ( $x['text'] ?? '' ),
				'original'   => '',
				'translated' => false,
				'author'     => (string) ( $x['author'] ?? '' ),
				'authorUrl'  => '',
				'photo'      => '',
				'time'       => $time,
				/* translators: %s: time, e.g. "3 months" */
				'relative'   => $time ? sprintf( __( '%s ago', 'foxi-builder' ), human_time_diff( strtotime( $time ), time() ) ) : '',
				'url'        => isset( $x['url'] ) ? esc_url_raw( (string) $x['url'] ) : '',
				'source'     => $src,
			);
		}
		return $out;
	}

	private static function filter( array $reviews, array $a ) {
		$min    = (int) ( isset( $a['minRating'] ) ? $a['minRating'] : 0 );
		$text   = ! empty( $a['withText'] );
		$len    = (int) ( isset( $a['minLength'] ) ? $a['minLength'] : 0 );
		$hidden = isset( $a['hidden'] ) && is_array( $a['hidden'] ) ? $a['hidden'] : array();
		$out    = array_values(
			array_filter(
				$reviews,
				static function ( $r ) use ( $min, $text, $len, $hidden ) {
					if ( $r['rating'] < $min || in_array( $r['id'], $hidden, true ) ) {
						return false;
					}
					$t = trim( $r['text'] );
					if ( $text && '' === $t ) {
						return false;
					}
					return ! $len || ( function_exists( 'mb_strlen' ) ? mb_strlen( $t ) : strlen( $t ) ) >= $len;
				}
			)
		);
		$sort = isset( $a['sort'] ) ? $a['sort'] : 'relevance';
		if ( 'newest' === $sort ) {
			usort( $out, static function ( $x, $y ) {
				return strcmp( $y['time'], $x['time'] );
			} );
		} elseif ( 'highest' === $sort ) {
			usort( $out, static function ( $x, $y ) {
				return $y['rating'] === $x['rating'] ? strcmp( $y['time'], $x['time'] ) : $y['rating'] - $x['rating'];
			} );
		}
		$limit = max( 1, min( 30, (int) ( isset( $a['limit'] ) ? $a['limit'] : 5 ) ) );
		return array_slice( $out, 0, $limit );
	}

	private static function card( array $r, array $a, $design, $uid ) {
		$slide  = in_array( $design, array( 'carousel', 'spotlight' ), true );
		$name   = $r['authorUrl'] ? sprintf( '<a class="fx-review__name" href="%s" target="_blank" rel="noopener nofollow">%s</a>', esc_url( $r['authorUrl'] ), esc_html( $r['author'] ) ) : '<span class="fx-review__name">' . esc_html( $r['author'] ) . '</span>';
		$avatar = '';
		if ( ! empty( $a['showAvatar'] ) ) {
			$avatar = $r['photo']
				? '<img class="fx-review__avatar" src="' . esc_url( $r['photo'] ) . '" alt="" width="44" height="44" loading="lazy" referrerpolicy="no-referrer">'
				: '<span class="fx-review__avatar fx-review__avatar--initial" aria-hidden="true">' . esc_html( function_exists( 'mb_substr' ) ? mb_substr( $r['author'], 0, 1 ) : substr( $r['author'], 0, 1 ) ) . '</span>';
		}
		$date  = '';
		$style = isset( $a['dateStyle'] ) ? $a['dateStyle'] : 'relative';
		if ( 'none' !== $style && ( $r['relative'] || $r['time'] ) ) {
			$ts    = $r['time'] ? strtotime( $r['time'] ) : 0;
			$label = 'date' === $style && $ts ? wp_date( get_option( 'date_format' ), $ts ) : $r['relative'];
			$date  = sprintf( '<time class="fx-review__date"%s>%s</time>', $ts ? ' datetime="' . esc_attr( gmdate( 'c', $ts ) ) . '"' : '', esc_html( $label ) );
		}
		$src  = isset( $r['source'] ) ? $r['source'] : 'google';
		$site = self::SOURCES[ $src ] ?? '';
		$mark = '';
		if ( ! empty( $a['googleMark'] ) ) {
			$mark = 'google' === $src ? '<span class="fx-review__g" title="Google">' . self::g_logo() . '</span>' : ( $site ? '<span class="fx-review__src">' . esc_html( $site ) . '</span>' : '' );
		}
		/* translators: %s: review site, e.g. Google */
		$link = $r['url'] ? sprintf( '<a class="fx-review__link" href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $r['url'] ), esc_html( sprintf( __( 'View on %s', 'foxi-builder' ), $site ? $site : __( 'the review site', 'foxi-builder' ) ) ) ) : '';
		$tr   = $r['translated'] ? '<span class="fx-review__translated">' . esc_html__( 'Translated by Google', 'foxi-builder' ) . '</span>' : '';
		$text = '' !== trim( $r['text'] ) ? '<div class="fx-review__text"><p>' . nl2br( esc_html( $r['text'] ) ) . '</p></div><button type="button" class="fx-review__more" aria-expanded="false" data-less="' . esc_attr__( 'Read less', 'foxi-builder' ) . '" hidden>' . esc_html__( 'Read more', 'foxi-builder' ) . '</button>' : '';

		if ( 'spotlight' === $design ) {
			$inner = sprintf(
				'<span class="fx-review__quote" aria-hidden="true">“</span>%1$s%2$s<footer class="fx-review__foot">%3$s<span class="fx-review__who">%4$s%5$s</span>%6$s</footer><div class="fx-review__meta">%7$s%8$s</div>',
				self::stars( $r['rating'] ),
				$text,
				$avatar,
				$name,
				$date,
				$mark,
				$tr,
				$link
			);
		} else {
			$inner = sprintf(
				'<header class="fx-review__head">%1$s<span class="fx-review__who">%2$s%3$s</span>%4$s</header>%5$s%6$s<div class="fx-review__meta">%7$s%8$s</div>',
				$avatar,
				$name,
				$date,
				$mark,
				self::stars( $r['rating'] ),
				$text,
				$tr,
				$link
			);
		}
		return '<article class="fx-review' . ( $slide ? ' swiper-slide fx-carousel-item' : '' ) . '">' . $inner . '</article>';
	}
}
