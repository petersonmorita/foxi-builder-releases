<?php
/**
 * Media renderers: Image, Video, Gallery, Map.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Render;

defined( 'ABSPATH' ) || exit;

class Image extends Base {
	const NAME = 'foxi/image';

	protected static function markup( $a, $content, $block ) {
		$image = isset( $a['image'] ) && is_array( $a['image'] ) ? $a['image'] : array();
		if ( empty( $image['url'] ) && empty( $image['id'] ) ) {
			return '';
		}
		$size = in_array( isset( $a['size'] ) ? $a['size'] : 'large', array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), true ) ? $a['size'] : 'large';
		$img  = self::img( $image, 'fx-image__img', $size );

		$full = ! empty( $image['id'] ) ? wp_get_attachment_image_url( (int) $image['id'], 'full' ) : ( isset( $image['url'] ) ? $image['url'] : '' );
		if ( ! empty( $a['lightbox'] ) && $full ) {
			$img = '<a class="fx-image__link" href="' . esc_url( $full ) . '" data-fx-lightbox>' . $img . '</a>';
		} elseif ( ! empty( $a['link']['url'] ) ) {
			$img = '<a class="fx-image__link"' . self::link_attrs( $a['link'] ) . '>' . $img . '</a>';
		}

		$caption = ! empty( $a['caption'] ) ? '<figcaption class="fx-image__caption">' . self::rich( $a['caption'] ) . '</figcaption>' : '';
		$cls     = array();
		if ( ! empty( $a['hover'] ) && 'none' !== $a['hover'] ) {
			$cls[] = 'fx-image--hover-' . sanitize_key( $a['hover'] );
		}
		return '<figure ' . self::wrapper( $a, $cls ) . '><div class="fx-image__frame">' . $img . '</div>' . $caption . '</figure>';
	}
}

class Video extends Base {
	const NAME = 'foxi/video';

	protected static function markup( $a, $content, $block ) {
		$url    = isset( $a['url'] ) ? trim( (string) $a['url'] ) : '';
		$file   = ! empty( $a['file']['url'] ) ? $a['file']['url'] : '';
		$poster = ! empty( $a['poster']['url'] ) ? $a['poster']['url'] : '';
		$title  = ! empty( $a['title'] ) ? $a['title'] : __( 'Video', 'foxi-builder' );
		$mode   = isset( $a['mode'] ) && 'lightbox' === $a['mode'] ? 'lightbox' : 'inline';

		$embed = '';
		if ( $url ) {
			$yt = self::youtube_id( $url );
			$vm = self::vimeo_id( $url );
			if ( $yt ) {
				$embed  = 'https://www.youtube-nocookie.com/embed/' . $yt . '?autoplay=1&rel=0';
				$poster = $poster ? $poster : 'https://i.ytimg.com/vi/' . $yt . '/hqdefault.jpg';
			} elseif ( $vm ) {
				$embed = 'https://player.vimeo.com/video/' . $vm . '?autoplay=1';
			} elseif ( preg_match( '~(?:facebook\.com/|fb\.watch/)~i', $url ) ) {
				// Facebook videos and reels.
				$embed = 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode( $url ) . '&show_text=false&autoplay=1';
			} elseif ( preg_match( '~instagram\.com/(?:reel|p|tv)/([A-Za-z0-9_-]+)~i', $url, $ig ) ) {
				$embed = 'https://www.instagram.com/reel/' . $ig[1] . '/embed';
			}
		}

		// Self-hosted video.
		if ( ! $embed && $file ) {
			if ( 'inline' === $mode ) {
				$flags = '';
				foreach ( array( 'autoplay', 'muted', 'loop', 'controls' ) as $flag ) {
					if ( ! empty( $a[ $flag ] ) ) {
						$flags .= ' ' . $flag;
					}
				}
				if ( ! empty( $a['autoplay'] ) ) {
					$flags .= ' playsinline' . ( empty( $a['muted'] ) ? ' muted' : '' );
				}
				return sprintf(
					'<div %1$s><div class="fx-video__frame"><video src="%2$s"%3$s%4$s preload="metadata"></video></div></div>',
					self::wrapper( $a ),
					esc_url( $file ),
					$poster ? ' poster="' . esc_url( $poster ) . '"' : '',
					$flags
				);
			}
			$embed = $file;
		}

		if ( ! $embed ) {
			// No video yet: keep the space with the cover image so the layout holds.
			if ( $poster ) {
				return sprintf( '<div %1$s><div class="fx-video__frame"><img class="fx-video__poster" src="%2$s" alt="" loading="lazy"></div></div>', self::wrapper( $a ), esc_url( $poster ) );
			}
			return '';
		}

		$play = '<button type="button" class="fx-video__play" aria-label="' . esc_attr( sprintf( /* translators: %s video title */ __( 'Play video: %s', 'foxi-builder' ), $title ) ) . '">' . \Foxi\Icons::svg( 'lucide:play', '', true ) . '</button>';
		$img  = $poster ? '<img class="fx-video__poster" src="' . esc_url( $poster ) . '" alt="" loading="lazy">' : '';

		return sprintf(
			'<div %1$s><div class="fx-video__frame" data-fx-video-embed="%2$s" data-mode="%3$s" data-kind="%4$s" data-title="%5$s">%6$s%7$s</div></div>',
			self::wrapper( $a ),
			esc_url( $embed ),
			esc_attr( $mode ),
			$file && $embed === $file ? 'file' : 'iframe',
			esc_attr( $title ),
			$img,
			$play
		);
	}
}

class Gallery extends Base {
	const NAME = 'foxi/gallery';

	protected static function markup( $a, $content, $block ) {
		$images = isset( $a['images'] ) && is_array( $a['images'] ) ? $a['images'] : array();
		if ( ! $images ) {
			return '';
		}
		$items = '';
		foreach ( $images as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}
			$img = self::img( $image, 'fx-gallery__img', 'large' );
			if ( ! $img ) {
				continue;
			}
			if ( ! empty( $a['lightbox'] ) ) {
				$full = ! empty( $image['id'] ) ? wp_get_attachment_image_url( (int) $image['id'], 'full' ) : $image['url'];
				$img  = '<a href="' . esc_url( $full ) . '" data-fx-lightbox="' . esc_attr( foxi_get( $a, 'fx.id', 'g' ) ) . '"' . ( ! empty( $image['caption'] ) ? ' data-caption="' . esc_attr( wp_strip_all_tags( $image['caption'] ) ) . '"' : '' ) . '>' . $img . '</a>';
			}
			$cap    = ! empty( $a['captions'] ) && ! empty( $image['caption'] ) ? '<figcaption class="fx-gallery__caption">' . esc_html( wp_strip_all_tags( $image['caption'] ) ) . '</figcaption>' : '';
			$items .= '<figure class="fx-gallery__item">' . $img . $cap . '</figure>';
		}
		$cls = array(
			'fx-gallery--' . sanitize_key( isset( $a['layout'] ) ? $a['layout'] : 'grid' ),
			'fx-gallery--hover-' . sanitize_key( isset( $a['hover'] ) ? $a['hover'] : 'zoom' ),
		);
		return '<div ' . self::wrapper( $a, $cls ) . '>' . $items . '</div>';
	}
}

class Map extends Base {
	const NAME = 'foxi/map';

	protected static function markup( $a, $content, $block ) {
		$address = trim( wp_strip_all_tags( \Foxi\Tokens::replace( isset( $a['address'] ) ? $a['address'] : '' ) ) );
		$address = html_entity_decode( $address, ENT_QUOTES );
		if ( '' === $address || false !== strpos( $address, '{{' ) ) {
			return '';
		}
		$src = add_query_arg(
			array(
				'q'      => rawurlencode( $address ),
				'z'      => max( 1, min( 20, (int) ( isset( $a['zoom'] ) ? $a['zoom'] : 14 ) ) ),
				'output' => 'embed',
			),
			'https://maps.google.com/maps'
		);
		return sprintf(
			'<div %1$s><iframe class="fx-map__frame" src="%2$s" title="%3$s" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>',
			self::wrapper( $a ),
			esc_url( $src ),
			esc_attr( ! empty( $a['title'] ) ? $a['title'] : __( 'Map', 'foxi-builder' ) )
		);
	}
}
