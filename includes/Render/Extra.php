<?php
/**
 * Renderers: Posts, Flip Box (+ sides), Breadcrumbs, Search, Page Title.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Render;

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Posts
 * ---------------------------------------------------------------------- */

class Posts extends Base {
	const NAME = 'foxi/posts';

	/**
	 * Query for this block. "inherit" uses the page's own query (blog index,
	 * category and search pages), everything else runs its own query.
	 *
	 * @return \WP_Query
	 */
	private static function query( $a ) {
		global $wp_query;

		if ( 'inherit' === foxi_get( $a, 'source', 'latest' ) && ( is_home() || is_archive() || is_search() ) && $wp_query instanceof \WP_Query ) {
			return $wp_query;
		}

		$args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( 24, (int) foxi_get( $a, 'count', 3 ) ) ),
			'offset'              => max( 0, (int) foxi_get( $a, 'offset', 0 ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		$order = foxi_get( $a, 'orderBy', 'date' );
		if ( in_array( $order, array( 'date', 'title', 'rand', 'comment_count', 'modified' ), true ) ) {
			$args['orderby'] = $order;
			$args['order']   = 'title' === $order ? 'ASC' : 'DESC';
		}

		if ( 'category' === foxi_get( $a, 'source', 'latest' ) && (int) foxi_get( $a, 'category', 0 ) ) {
			$args['cat'] = (int) $a['category'];
		}

		if ( foxi_get( $a, 'excludeCurrent', true ) && is_singular( 'post' ) ) {
			$args['post__not_in'] = array( get_queried_object_id() );
		}

		return new \WP_Query( $args );
	}

	protected static function markup( $a, $content, $block ) {
		$q       = self::query( $a );
		$show    = wp_parse_args( (array) foxi_get( $a, 'show', array() ), array( 'image' => true, 'date' => true, 'category' => true, 'author' => false, 'excerpt' => true, 'readMore' => true ) );
		$layout  = in_array( foxi_get( $a, 'layout', 'grid' ), array( 'grid', 'list', 'overlay' ), true ) ? $a['layout'] : 'grid';
		$level   = max( 2, min( 6, (int) foxi_get( $a, 'headingLevel', 3 ) ) );
		$words   = max( 0, min( 80, (int) foxi_get( $a, 'excerptLength', 22 ) ) );
		$ratio   = preg_match( '~^\d+(\.\d+)?/\d+(\.\d+)?$~', (string) foxi_get( $a, 'imageRatio', '3/2' ) ) ? $a['imageRatio'] : '3/2';
		$cols    = wp_parse_args( (array) foxi_get( $a, 'columns', array() ), array( 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ) );
		$more    = (string) foxi_get( $a, 'readMoreText', __( 'Read more', 'foxi-builder' ) );
		$style   = sprintf( '--fx-posts-cols:%d;--fx-posts-cols-t:%d;--fx-posts-cols-m:%d;--fx-posts-ratio:%s', max( 1, (int) $cols['desktop'] ), max( 1, (int) $cols['tablet'] ), max( 1, (int) $cols['mobile'] ), str_replace( '/', ' / ', $ratio ) );
		$wrapper = self::wrapper( $a, array( 'fx-posts--' . $layout ), array( 'style' => $style ) );

		if ( ! $q->have_posts() ) {
			$empty = (string) foxi_get( $a, 'emptyText', __( 'No posts yet.', 'foxi-builder' ) );
			return '<div ' . $wrapper . '><p class="fx-posts__empty">' . esc_html( $empty ) . '</p></div>';
		}

		$cards = '';
		while ( $q->have_posts() ) {
			$q->the_post();
			$id    = get_the_ID();
			$url   = get_permalink();
			$title = get_the_title();

			$media = '';
			if ( $show['image'] ) {
				$thumb = has_post_thumbnail() ? get_the_post_thumbnail( $id, 'large', array( 'class' => 'fx-posts__img', 'loading' => 'lazy', 'alt' => '' ) ) : '<span class="fx-posts__img fx-posts__img--empty" aria-hidden="true"></span>';
				$media = '<a class="fx-posts__media" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $thumb . '</a>';
			}

			$meta = array();
			if ( $show['category'] ) {
				$cats = get_the_category();
				if ( $cats ) {
					$meta[] = '<span class="fx-posts__cat">' . esc_html( $cats[0]->name ) . '</span>';
				}
			}
			if ( $show['date'] ) {
				$meta[] = '<time datetime="' . esc_attr( get_the_date( DATE_W3C ) ) . '">' . esc_html( get_the_date() ) . '</time>';
			}
			if ( $show['author'] ) {
				$meta[] = '<span class="fx-posts__author">' . esc_html( get_the_author() ) . '</span>';
			}

			$body  = $meta ? '<div class="fx-posts__meta">' . implode( '<span class="fx-posts__dot" aria-hidden="true">·</span>', $meta ) . '</div>' : '';
			$body .= sprintf( '<h%1$d class="fx-posts__title"><a href="%2$s">%3$s</a></h%1$d>', $level, esc_url( $url ), esc_html( $title ) );
			if ( $show['excerpt'] && $words ) {
				$body .= '<p class="fx-posts__excerpt">' . esc_html( wp_trim_words( get_the_excerpt(), $words ) ) . '</p>';
			}
			if ( $show['readMore'] && '' !== trim( $more ) ) {
				$body .= '<a class="fx-posts__more" href="' . esc_url( $url ) . '">' . esc_html( $more ) . ' ' . foxi_icon( 'lucide:arrow-right' ) . '<span class="screen-reader-text">: ' . esc_html( $title ) . '</span></a>';
			}

			$cards .= '<article class="fx-posts__card">' . $media . '<div class="fx-posts__body">' . $body . '</div></article>';
		}

		$pager = '';
		if ( $q === $GLOBALS['wp_query'] && foxi_get( $a, 'pagination', true ) ) {
			$links = paginate_links(
				array(
					'total'     => (int) $q->max_num_pages,
					'type'      => 'plain',
					'prev_text' => __( 'Previous', 'foxi-builder' ),
					'next_text' => __( 'Next', 'foxi-builder' ),
				)
			);
			if ( $links ) {
				$pager = '<nav class="fx-posts__pager" aria-label="' . esc_attr__( 'Posts pages', 'foxi-builder' ) . '">' . $links . '</nav>';
			}
		} else {
			wp_reset_postdata();
		}

		return '<div ' . $wrapper . '><div class="fx-posts__grid">' . $cards . '</div>' . $pager . '</div>';
	}
}

/* -------------------------------------------------------------------------
 * Flip box
 * ---------------------------------------------------------------------- */

class FlipBox extends Base {
	const NAME = 'foxi/flip-box';

	protected static function markup( $a, $content, $block ) {
		$effect  = in_array( foxi_get( $a, 'effect', 'flip' ), array( 'flip', 'slide', 'fade', 'zoom' ), true ) ? $a['effect'] : 'flip';
		$dir     = in_array( foxi_get( $a, 'direction', 'left' ), array( 'left', 'right', 'up', 'down' ), true ) ? $a['direction'] : 'left';
		$trigger = 'click' === foxi_get( $a, 'trigger', 'hover' ) ? 'click' : 'hover';
		$height  = preg_match( '~^\d+(\.\d+)?(px|vh|rem|em)$~', (string) foxi_get( $a, 'height', '320px' ) ) ? $a['height'] : '320px';

		return sprintf(
			'<div %1$s><div class="fx-flip-box__card">%2$s</div></div>',
			self::wrapper(
				$a,
				array( 'fx-flip-box--' . $effect, 'fx-flip-box--' . $dir, 'fx-flip-box--' . $trigger ),
				array(
					'style'       => '--fx-flip-h:' . $height,
					'data-fx-flip' => $trigger,
					'tabindex'    => '0',
				)
			),
			$content
		);
	}
}

class FlipSide extends Base {
	const NAME = 'foxi/flip-side';

	protected static function markup( $a, $content, $block ) {
		$side = 'back' === foxi_get( $a, 'side', 'front' ) ? 'back' : 'front';
		return sprintf(
			'<div %1$s>%2$s<div class="fx-flip-side__inner">%3$s</div></div>',
			self::wrapper( $a, array( 'fx-flip-side--' . $side, 'fx-valign-' . sanitize_key( foxi_get( $a, 'vAlign', 'center' ) ) ) ),
			self::background( $a ),
			$content
		);
	}
}

/* -------------------------------------------------------------------------
 * Breadcrumbs
 * ---------------------------------------------------------------------- */

class Breadcrumbs extends Base {
	const NAME = 'foxi/breadcrumbs';

	/**
	 * Trail for the current request: list of [ label, url|null ].
	 *
	 * @return array<int,array{0:string,1:?string}>
	 */
	public static function trail( $a = array() ) {
		$items = array();
		if ( foxi_get( $a, 'showHome', true ) ) {
			$items[] = array( (string) foxi_get( $a, 'homeLabel', __( 'Home', 'foxi-builder' ) ), home_url( '/' ) );
		}

		if ( is_front_page() ) {
			return $items;
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( 'post' === $post->post_type ) {
				$blog = (int) get_option( 'page_for_posts' );
				if ( $blog ) {
					$items[] = array( get_the_title( $blog ), get_permalink( $blog ) );
				}
				$cats = get_the_category( $post->ID );
				if ( $cats ) {
					$items[] = array( $cats[0]->name, get_category_link( $cats[0] ) );
				}
			} else {
				foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor ) {
					$items[] = array( get_the_title( $ancestor ), get_permalink( $ancestor ) );
				}
			}
			$items[] = array( get_the_title( $post ), null );
		} elseif ( is_home() ) {
			$items[] = array( get_the_title( (int) get_option( 'page_for_posts' ) ) ?: __( 'Blog', 'foxi-builder' ), null );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$items[] = array( single_term_title( '', false ), null );
		} elseif ( is_search() ) {
			/* translators: %s search terms */
			$items[] = array( sprintf( __( 'Search results for “%s”', 'foxi-builder' ), get_search_query() ), null );
		} elseif ( is_author() ) {
			$items[] = array( get_the_author_meta( 'display_name', (int) get_query_var( 'author' ) ), null );
		} elseif ( is_archive() ) {
			$items[] = array( wp_strip_all_tags( get_the_archive_title() ), null );
		} elseif ( is_404() ) {
			$items[] = array( __( 'Page not found', 'foxi-builder' ), null );
		}

		return $items;
	}

	protected static function markup( $a, $content, $block ) {
		$items = self::trail( $a );
		if ( ! foxi_get( $a, 'showCurrent', true ) && $items && null === end( $items )[1] ) {
			array_pop( $items );
		}
		if ( count( $items ) < 2 ) {
			// In the editor there is no "current page"; show an example trail.
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				$items = array( array( (string) foxi_get( $a, 'homeLabel', __( 'Home', 'foxi-builder' ) ), '#' ), array( __( 'Parent page', 'foxi-builder' ), '#' ), array( __( 'This page', 'foxi-builder' ), null ) );
			} elseif ( ! $items ) {
				return '';
			}
		}

		$sep = foxi_get( $a, 'separator', 'chevron' );
		$sep = 'chevron' === $sep ? foxi_icon( 'lucide:chevron-right' ) : esc_html( in_array( $sep, array( '/', '›', '»', '·', '|', '→' ), true ) ? $sep : '/' );

		$html = '';
		$last = count( $items ) - 1;
		foreach ( $items as $i => $item ) {
			$label = esc_html( $item[0] );
			$html .= '<li class="fx-breadcrumbs__item">';
			$html .= $item[1] && $i !== $last ? '<a href="' . esc_url( $item[1] ) . '">' . $label . '</a>' : '<span' . ( null === $item[1] ? ' aria-current="page"' : '' ) . '>' . $label . '</span>';
			if ( $i !== $last ) {
				$html .= '<span class="fx-breadcrumbs__sep" aria-hidden="true">' . $sep . '</span>';
			}
			$html .= '</li>';
		}

		// Search engines read the trail from the BreadcrumbList schema Foxi prints in the page head.
		return '<nav ' . self::wrapper( $a, array(), array( 'aria-label' => __( 'Breadcrumb', 'foxi-builder' ) ) ) . '><ol class="fx-breadcrumbs__list">' . $html . '</ol></nav>';
	}
}

/* -------------------------------------------------------------------------
 * Search
 * ---------------------------------------------------------------------- */

class Search extends Base {
	const NAME = 'foxi/search';

	protected static function markup( $a, $content, $block ) {
		static $n = 0;
		++$n;
		$id     = 'fx-search-' . $n;
		$style  = foxi_get( $a, 'buttonStyle', 'icon' );
		$text   = (string) foxi_get( $a, 'buttonText', __( 'Search', 'foxi-builder' ) );
		$button = 'icon' === $style ? foxi_icon( 'lucide:search' ) . '<span class="screen-reader-text">' . esc_html( $text ) . '</span>' : esc_html( $text );
		$type   = foxi_get( $a, 'postType', 'any' );
		$hidden = in_array( $type, array( 'post', 'page' ), true ) ? '<input type="hidden" name="post_type" value="' . esc_attr( $type ) . '">' : '';

		return sprintf(
			'<form %1$s role="search" method="get" action="%2$s"><label class="screen-reader-text" for="%3$s">%4$s</label><input class="fx-search__input" id="%3$s" type="search" name="s" value="%5$s" placeholder="%6$s">%7$s<button class="fx-search__button fx-search__button--%8$s" type="submit">%9$s</button></form>',
			self::wrapper( $a ),
			esc_url( home_url( '/' ) ),
			esc_attr( $id ),
			esc_html__( 'Search for:', 'foxi-builder' ),
			esc_attr( get_search_query() ),
			esc_attr( (string) foxi_get( $a, 'placeholder', '' ) ),
			$hidden,
			'icon' === $style ? 'icon' : 'text',
			$button
		);
	}
}

/* -------------------------------------------------------------------------
 * Page title
 * ---------------------------------------------------------------------- */

class PageTitle extends Base {
	const NAME = 'foxi/page-title';

	protected static function markup( $a, $content, $block ) {
		$level = max( 1, min( 3, (int) foxi_get( $a, 'level', 1 ) ) );
		$title = '';
		$meta  = '';
		$desc  = '';

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! is_singular() && ! is_archive() ) {
			// Editor preview.
			$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
			$title   = $post_id ? get_the_title( $post_id ) : __( 'Page title', 'foxi-builder' );
		} elseif ( is_singular() ) {
			$title = single_post_title( '', false );
			if ( foxi_get( $a, 'showMeta', true ) && is_singular( 'post' ) ) {
				$meta = '<time datetime="' . esc_attr( get_the_date( DATE_W3C ) ) . '">' . esc_html( get_the_date() ) . '</time>'
					. '<span aria-hidden="true">·</span><span>' . esc_html( get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', get_queried_object_id() ) ) ) . '</span>';
			}
		} elseif ( is_home() ) {
			$title = get_the_title( (int) get_option( 'page_for_posts' ) ) ?: __( 'Blog', 'foxi-builder' );
		} elseif ( is_search() ) {
			/* translators: %s search terms */
			$title = sprintf( __( 'Results for “%s”', 'foxi-builder' ), get_search_query() );
		} elseif ( is_404() ) {
			$title = __( 'Page not found', 'foxi-builder' );
		} elseif ( is_archive() ) {
			$title = foxi_get( $a, 'prefix', false ) ? wp_strip_all_tags( get_the_archive_title() ) : wp_strip_all_tags( single_term_title( '', false ) ?: post_type_archive_title( '', false ) ?: get_the_archive_title() );
			if ( foxi_get( $a, 'showDescription', true ) ) {
				$desc = wp_strip_all_tags( get_the_archive_description() );
			}
		}

		if ( '' === $title ) {
			return '';
		}

		return sprintf(
			'<div %1$s><h%2$d class="fx-page-title__title">%3$s</h%2$d>%4$s%5$s</div>',
			self::wrapper( $a ),
			$level,
			esc_html( $title ),
			$meta ? '<div class="fx-page-title__meta">' . $meta . '</div>' : '',
			$desc ? '<p class="fx-page-title__desc">' . esc_html( $desc ) . '</p>' : ''
		);
	}
}

/* -------------------------------------------------------------------------
 * Post content (blog post layout)
 * ---------------------------------------------------------------------- */

class PostContent extends Base {
	const NAME = 'foxi/post-content';

	/** @var bool Guards against a post rendering itself. */
	private static $busy = false;

	protected static function markup( $a, $content, $block ) {
		if ( self::$busy ) {
			return '';
		}
		$post = is_singular() ? get_queried_object() : null;
		if ( ! $post instanceof \WP_Post || \Foxi\Parts::POST_TYPE === $post->post_type ) {
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return '<div ' . self::wrapper( $a ) . '><div class="fx-editor-empty">' . esc_html__( 'The post’s featured image and text appear here.', 'foxi-builder' ) . '</div></div>';
			}
			return '';
		}

		self::$busy = true;
		$body       = apply_filters( 'the_content', get_the_content( null, false, $post ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		self::$busy = false;

		$image = '';
		if ( foxi_get( $a, 'featuredImage', true ) && has_post_thumbnail( $post ) ) {
			$ratio = preg_match( '~^\d+/\d+$~', (string) foxi_get( $a, 'imageRatio', '16/9' ) ) ? str_replace( '/', ' / ', $a['imageRatio'] ) : '16 / 9';
			$image = '<figure class="fx-post-content__image" style="aspect-ratio:' . esc_attr( $ratio ) . '">' . get_the_post_thumbnail( $post, 'full', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ) . '</figure>';
		}

		return '<div ' . self::wrapper( $a ) . '>' . $image . '<div class="fx-post-content__body">' . $body . '</div></div>';
	}
}

/* -------------------------------------------------------------------------
 * Popup
 * ---------------------------------------------------------------------- */

class Popup extends Base {
	const NAME = 'foxi/popup';

	protected static function markup( $a, $content, $block ) {
		$id    = sanitize_html_class( (string) foxi_get( $a, 'anchor', 'enquire' ) );
		$id    = $id ? $id : 'popup';
		$width = preg_match( '~^\d+(\.\d+)?(px|%|vw|rem)$~', (string) foxi_get( $a, 'width', '640px' ) ) ? $a['width'] : '640px';
		$label = (string) foxi_get( $a, 'label', '' );
		return sprintf(
			'<div %1$s hidden><div class="fx-popup__backdrop" data-fx-close></div><div class="fx-popup__dialog" role="dialog" aria-modal="true"%2$s tabindex="-1" style="--fx-popup-w:%3$s"><button type="button" class="fx-popup__close" data-fx-close aria-label="%4$s">%5$s</button>%6$s</div></div>',
			self::wrapper( $a, array(), array( 'id' => $id, 'data-fx-popup' => true ) ),
			$label ? ' aria-label="' . esc_attr( $label ) . '"' : '',
			esc_attr( $width ),
			esc_attr__( 'Close', 'foxi-builder' ),
			foxi_icon( 'lucide:x' ),
			$content
		);
	}
}
