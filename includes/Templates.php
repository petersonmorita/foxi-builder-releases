<?php
/**
 * Site templates: blog index, archives, search results, single blog posts
 * and the 404 page render with the Foxi header and footer, so the whole site
 * matches, not just the pages built in Foxi.
 *
 * Each layout is a site part (Foxi › Headers & footers) you can edit in
 * Foxi Studio. Until you create one, a built-in layout styled by the Brand
 * Kit is used.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Templates {

	/** Part types that are page layouts (not header/footer). */
	const KINDS = array( 'archive', 'single', '404' );

	public function init() {}

	/**
	 * Which layout applies to this request, or '' when Foxi stays out of it.
	 */
	public static function current_kind() {
		if ( empty( Settings::get()['siteTemplates'] ) || ! Parts::active( 'header' ) ) {
			return '';
		}
		if ( is_404() ) {
			return '404';
		}
		if ( is_search() || is_home() || is_category() || is_tag() || is_author() || is_date() ) {
			return 'archive';
		}
		if ( is_singular( 'post' ) && ! Canvas::uses_canvas() ) {
			return 'single';
		}
		return '';
	}

	/**
	 * Block markup for a layout: the chosen site part, or the built-in default.
	 */
	public static function markup( $kind ) {
		$id = Parts::active( $kind );
		if ( $id ) {
			return (string) get_post_field( 'post_content', $id );
		}
		return self::default_markup( $kind );
	}

	public static function default_markup( $kind ) {
		$blocks = Starters::resolve( self::default_blocks( $kind ), array( 'images' => array(), 'pages' => array(), 'menus' => array() ) );
		return Starters::serialize( $blocks );
	}

	public static function label( $kind ) {
		$labels = array(
			'header'  => __( 'Header', 'foxi-builder' ),
			'footer'  => __( 'Footer', 'foxi-builder' ),
			'archive' => __( 'Blog & archive layout', 'foxi-builder' ),
			'single'  => __( 'Blog post layout', 'foxi-builder' ),
			'404'     => __( '404 page', 'foxi-builder' ),
		);
		return isset( $labels[ $kind ] ) ? $labels[ $kind ] : ucfirst( (string) $kind );
	}

	/* ------------------------------------------------------------------
	 * Built-in layouts
	 * ---------------------------------------------------------------- */

	private static function st( array $desktop, array $mobile = array() ) {
		$s = array( 'desktop' => array( 'normal' => $desktop ) );
		if ( $mobile ) {
			$s['mobile'] = array( 'normal' => $mobile );
		}
		return $s;
	}

	/** Page header band used by all three layouts. */
	private static function title_band( array $inner ) {
		return array(
			'name'  => 'foxi/section',
			'attrs' => array(
				'fx' => array(
					'bg'    => array( 'type' => 'color', 'color' => 'var(--fx-c-dark)' ),
					'style' => array(
						// Dark band so a see-through header stays readable.
						'' => self::st(
							array(
								'background-color'  => 'var(--fx-c-dark)',
								'padding-top'       => 'var(--fx-space-xl)',
								'padding-bottom'    => 'var(--fx-space-l)',
								'--fx-auto-text'    => 'rgba(255,255,255,.85)',
								'--fx-auto-heading' => '#FFFFFF',
								'--fx-auto-link'    => '#FFFFFF',
								'color'             => 'rgba(255,255,255,.85)',
							),
							array( 'padding-top' => 'var(--fx-space-l)' )
						),
					),
				),
			),
			'inner' => $inner,
		);
	}

	private static function default_blocks( $kind ) {
		$crumbs = array( 'name' => 'foxi/breadcrumbs' );

		if ( 'single' === $kind ) {
			return array(
				self::title_band(
					array(
						$crumbs,
						array( 'name' => 'foxi/page-title', 'attrs' => array( 'level' => 1 ) ),
					)
				),
				array(
					'name'  => 'foxi/section',
					'attrs' => array( 'width' => 'narrow', 'fx' => array( 'style' => array( '' => self::st( array( 'padding-top' => 'var(--fx-space-l)' ) ) ) ) ),
					'inner' => array( array( 'name' => 'foxi/post-content', 'attrs' => array( 'featuredImage' => true ) ) ),
				),
				array(
					'name'  => 'foxi/section',
					'attrs' => array( 'fx' => array( 'bg' => array( 'type' => 'color', 'color' => 'var(--fx-c-light)' ), 'style' => array( '' => self::st( array( 'background-color' => 'var(--fx-c-light)' ) ) ) ) ),
					'inner' => array(
						array( 'name' => 'foxi/heading', 'attrs' => array( 'level' => 2, 'content' => __( 'Keep reading', 'foxi-builder' ) ) ),
						array( 'name' => 'foxi/posts', 'attrs' => array( 'count' => 3, 'show' => array( 'image' => true, 'date' => true, 'category' => true, 'author' => false, 'excerpt' => false, 'readMore' => true ) ) ),
					),
				),
			);
		}

		if ( '404' === $kind ) {
			return array(
				array(
					'name'  => 'foxi/section',
					'attrs' => array(
						'height' => 'tall',
						'width'  => 'narrow',
						'fx'     => array(
							'bg'    => array( 'type' => 'color', 'color' => 'var(--fx-c-dark)' ),
							'style' => array(
								'' => self::st(
									array(
										'text-align'        => 'center',
										'padding-top'       => 'var(--fx-space-xl)',
										'background-color'  => 'var(--fx-c-dark)',
										'color'             => 'rgba(255,255,255,.85)',
										'--fx-auto-text'    => 'rgba(255,255,255,.85)',
										'--fx-auto-heading' => '#FFFFFF',
									)
								),
							),
						),
					),
					'inner' => array(
						array( 'name' => 'foxi/text', 'attrs' => array( 'content' => '404', 'fx' => array( 'style' => array( '' => self::st( array( 'font-size' => 'clamp(4rem, 12vw, 8rem)', 'line-height' => '1', 'font-family' => 'var(--fx-font-heading)', 'color' => 'var(--fx-c-accent)' ) ) ) ) ) ),
						array( 'name' => 'foxi/page-title', 'attrs' => array( 'level' => 1 ) ),
						array( 'name' => 'foxi/text', 'attrs' => array( 'content' => __( 'The page you were looking for has moved or no longer exists. Try a search, or head back to the home page.', 'foxi-builder' ) ) ),
						array( 'name' => 'foxi/search', 'attrs' => array( 'fx' => array( 'style' => array( '' => self::st( array( 'margin-left' => 'auto', 'margin-right' => 'auto' ) ) ) ) ) ),
						array(
							'name'  => 'foxi/buttons',
							'attrs' => array( 'fx' => array( 'style' => array( '' => self::st( array( 'justify-content' => 'center' ) ) ) ) ),
							'inner' => array( array( 'name' => 'foxi/button', 'attrs' => array( 'text' => __( 'Back to the home page', 'foxi-builder' ), 'link' => array( 'url' => home_url( '/' ) ) ) ) ),
						),
					),
				),
			);
		}

		// Archive: blog index, categories, tags, authors, dates and search results.
		return array(
			self::title_band(
				array(
					$crumbs,
					array( 'name' => 'foxi/page-title', 'attrs' => array( 'level' => 1 ) ),
				)
			),
			array(
				'name'  => 'foxi/section',
				'attrs' => array( 'fx' => array( 'style' => array( '' => self::st( array( 'padding-top' => 'var(--fx-space-l)' ) ) ) ) ),
				'inner' => array(
					array(
						'name'  => 'foxi/posts',
						'attrs' => array(
							'source'    => 'inherit',
							'emptyText' => __( 'Nothing found. Try another search.', 'foxi-builder' ),
						),
					),
				),
			),
		);
	}
}
