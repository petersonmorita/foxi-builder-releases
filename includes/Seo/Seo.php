<?php
/**
 * On-page SEO: meta title, description, canonical, robots, Open Graph,
 * Twitter cards and JSON-LD schema.
 *
 * Foxi automatically steps aside when Yoast, Rank Math, AIOSEO, SEOPress or
 * The SEO Framework is active (unless forced on in settings), so you never get
 * duplicate tags.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Seo;

use Foxi\Settings;
use Foxi\Tokens;

defined( 'ABSPATH' ) || exit;

class Seo {

	const META = '_foxi_seo';

	public function init() {
		add_action( 'init', array( $this, 'register_meta' ) );
		// A plain flag mirrors "noindex" so the sitemap can filter on it quickly.
		add_action( 'added_post_meta', array( $this, 'sync_noindex' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'sync_noindex' ), 10, 4 );
		if ( ! self::enabled() ) {
			return;
		}
		add_filter( 'wp_sitemaps_posts_query_args', array( $this, 'sitemap_query' ), 10, 2 );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'sitemap_providers' ), 10, 2 );
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ), 20 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'get_canonical_url', array( $this, 'canonical' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'head' ), 1 );
		add_action( 'wp_footer', array( Schema::class, 'print_faq' ), 99 );
	}

	public function sync_noindex( $meta_id, $post_id, $key, $value ) {
		if ( self::META !== $key ) {
			return;
		}
		$value = maybe_unserialize( $value );
		if ( is_array( $value ) && ! empty( $value['noindex'] ) ) {
			update_post_meta( $post_id, '_foxi_noindex', 1 );
		} else {
			delete_post_meta( $post_id, '_foxi_noindex' );
		}
	}

	/** Pages hidden from Google stay out of the sitemap (wp-sitemap.xml). */
	public function sitemap_query( $args, $post_type ) {
		$args['meta_query']   = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
		$args['meta_query'][] = array(
			'key'     => '_foxi_noindex',
			'compare' => 'NOT EXISTS',
		);
		return $args;
	}

	/** The author sitemap lists login names; leave it out. */
	public function sitemap_providers( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Is Foxi's SEO output on?
	 */
	public static function enabled() {
		$mode = Settings::get()['seo'];
		if ( 'off' === $mode ) {
			return false;
		}
		if ( 'on' === $mode ) {
			return true;
		}
		return '' === foxi_active_seo_plugin();
	}

	public static function schema() {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'title'       => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'keyword'     => array( 'type' => 'string' ),
				'canonical'   => array( 'type' => 'string' ),
				'noindex'     => array( 'type' => 'boolean' ),
				'nofollow'    => array( 'type' => 'boolean' ),
				'ogTitle'     => array( 'type' => 'string' ),
				'ogDesc'      => array( 'type' => 'string' ),
				'ogImage'     => array( 'type' => 'string' ),
				'ogImageId'   => array( 'type' => 'integer' ),
				'schemaType'  => array( 'type' => 'string' ),
				'schemaJson'  => array( 'type' => 'string' ),
			),
		);
	}

	public function register_meta() {
		$types = get_post_types( array( 'public' => true ) );
		foreach ( $types as $type ) {
			if ( 'attachment' === $type ) {
				continue;
			}
			register_post_meta(
				$type,
				self::META,
				array(
					'type'              => 'object',
					'single'            => true,
					'default'           => array(),
					'show_in_rest'      => array( 'schema' => self::schema() ),
					'sanitize_callback' => array( __CLASS__, 'sanitize' ),
					'auth_callback'     => static function ( $allowed, $key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	public static function sanitize( $value ) {
		$value = is_array( $value ) ? $value : array();
		$out   = array();
		foreach ( array( 'title', 'keyword', 'ogTitle', 'schemaType' ) as $k ) {
			if ( isset( $value[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( $value[ $k ] );
			}
		}
		foreach ( array( 'description', 'ogDesc' ) as $k ) {
			if ( isset( $value[ $k ] ) ) {
				$out[ $k ] = sanitize_textarea_field( $value[ $k ] );
			}
		}
		foreach ( array( 'canonical', 'ogImage' ) as $k ) {
			if ( isset( $value[ $k ] ) ) {
				$out[ $k ] = esc_url_raw( $value[ $k ] );
			}
		}
		foreach ( array( 'noindex', 'nofollow' ) as $k ) {
			if ( isset( $value[ $k ] ) ) {
				$out[ $k ] = (bool) $value[ $k ];
			}
		}
		if ( isset( $value['ogImageId'] ) ) {
			$out['ogImageId'] = absint( $value['ogImageId'] );
		}
		if ( isset( $value['schemaJson'] ) ) {
			$json = trim( (string) $value['schemaJson'] );
			// Keep only valid JSON; invalid input is discarded rather than printed.
			$out['schemaJson'] = ( '' === $json || null !== json_decode( $json ) ) ? $json : '';
		}
		return $out;
	}

	/**
	 * @param int|null $post_id Post.
	 * @return array
	 */
	public static function get( $post_id = null ) {
		$post_id = $post_id ? $post_id : ( is_singular() ? get_queried_object_id() : 0 );
		if ( ! $post_id ) {
			return array();
		}
		$meta = get_post_meta( $post_id, self::META, true );
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Expand %title%, %site%, %tagline%, %sep% and {{business}} tokens.
	 */
	public static function expand( $template, $title = '' ) {
		$s = Settings::get();
		$out = strtr(
			(string) $template,
			array(
				'%title%'   => $title,
				'%site%'    => get_bloginfo( 'name' ),
				'%tagline%' => get_bloginfo( 'description' ),
				'%sep%'     => $s['separator'],
			)
		);
		$out = wp_strip_all_tags( html_entity_decode( Tokens::replace( $out ), ENT_QUOTES ) );
		// Tidy dangling separators when parts are empty.
		$sep = preg_quote( $s['separator'], '/' );
		$out = preg_replace( '/^\s*' . $sep . '\s*|\s*' . $sep . '\s*$/', '', trim( preg_replace( '/\s+/', ' ', $out ) ) );
		return trim( $out );
	}

	public function document_title( $title ) {
		$s = Settings::get();
		if ( is_singular() ) {
			$meta = self::get();
			if ( ! empty( $meta['title'] ) ) {
				return self::expand( $meta['title'], single_post_title( '', false ) );
			}
			if ( is_front_page() ) {
				return self::expand( $s['homeTitle'], single_post_title( '', false ) );
			}
			return self::expand( $s['titleTemplate'], single_post_title( '', false ) );
		}
		if ( is_front_page() || is_home() ) {
			return self::expand( $s['homeTitle'] );
		}
		return $title;
	}

	public static function description( $post_id = null ) {
		$meta = self::get( $post_id );
		if ( ! empty( $meta['description'] ) ) {
			return Tokens::replace( $meta['description'] );
		}
		if ( is_singular() ) {
			$post = get_post( $post_id ? $post_id : get_queried_object_id() );
			if ( $post && has_excerpt( $post ) ) {
				return wp_strip_all_tags( get_the_excerpt( $post ) );
			}
		}
		if ( is_front_page() ) {
			return get_bloginfo( 'description' );
		}
		return '';
	}

	public function robots( $robots ) {
		$meta = self::get();
		if ( ! empty( $meta['noindex'] ) ) {
			$robots['noindex'] = true;
			unset( $robots['max-image-preview'] );
		}
		if ( ! empty( $meta['nofollow'] ) ) {
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	public function canonical( $url, $post ) {
		$meta = self::get( $post ? $post->ID : 0 );
		return ! empty( $meta['canonical'] ) ? $meta['canonical'] : $url;
	}

	private static function og_image() {
		$meta = self::get();
		if ( ! empty( $meta['ogImageId'] ) ) {
			$src = wp_get_attachment_image_src( (int) $meta['ogImageId'], 'large' );
			if ( $src ) {
				return $src;
			}
		}
		if ( ! empty( $meta['ogImage'] ) ) {
			return array( $meta['ogImage'], 0, 0 );
		}
		if ( is_singular() && has_post_thumbnail() ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
			if ( $src ) {
				return $src;
			}
		}
		$default = Settings::get()['defaultOgImage'];
		return $default ? array( $default, 0, 0 ) : array();
	}

	public function head() {
		$meta  = self::get();
		$desc  = self::description();
		$title = wp_get_document_title();
		$url   = is_singular() ? wp_get_canonical_url() : home_url( add_query_arg( array() ) );

		echo "\n<!-- Foxi Builder SEO -->\n";
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( wp_trim_words( $desc, 60, '…' ) ) . '">' . "\n";
		}

		$og = array(
			'og:locale'      => get_locale(),
			'og:type'        => is_front_page() ? 'website' : ( is_singular( 'post' ) ? 'article' : 'website' ),
			'og:title'       => ! empty( $meta['ogTitle'] ) ? Tokens::replace( $meta['ogTitle'] ) : $title,
			'og:description' => ! empty( $meta['ogDesc'] ) ? Tokens::replace( $meta['ogDesc'] ) : $desc,
			'og:url'         => $url,
			'og:site_name'   => get_bloginfo( 'name' ),
		);
		$img = self::og_image();
		if ( $img ) {
			$og['og:image'] = $img[0];
			if ( ! empty( $img[1] ) ) {
				$og['og:image:width']  = $img[1];
				$og['og:image:height'] = $img[2];
			}
		}
		foreach ( $og as $prop => $content ) {
			if ( '' !== (string) $content ) {
				echo '<meta property="' . esc_attr( $prop ) . '" content="' . esc_attr( wp_strip_all_tags( (string) $content ) ) . '">' . "\n";
			}
		}
		echo '<meta name="twitter:card" content="' . ( $img ? 'summary_large_image' : 'summary' ) . '">' . "\n";

		Schema::print_head( $meta );
		echo "<!-- / Foxi Builder SEO -->\n";
	}
}
