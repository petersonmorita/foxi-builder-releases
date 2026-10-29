<?php
/**
 * JSON-LD structured data.
 *
 * - Site schema (LocalBusiness, Hotel, Restaurant… from Business details) on the home page
 * - Per-page schema type preset and/or custom JSON-LD
 * - FAQPage, generated automatically from Accordion blocks with "FAQ schema" on
 *
 * @package FoxiBuilder
 */

namespace Foxi\Seo;

use Foxi\Brand;
use Foxi\Settings;

defined( 'ABSPATH' ) || exit;

class Schema {

	/** @var array<int,array{0:string,1:string}> */
	private static $faq = array();

	/**
	 * Schema types offered in the editor.
	 *
	 * @return array<string,string>
	 */
	public static function types() {
		return array(
			''                 => __( 'None', 'foxi-builder' ),
			'LocalBusiness'    => __( 'Local business', 'foxi-builder' ),
			'Organization'     => __( 'Organisation', 'foxi-builder' ),
			'Hotel'            => __( 'Hotel', 'foxi-builder' ),
			'LodgingBusiness'  => __( 'Accommodation / retreat', 'foxi-builder' ),
			'BedAndBreakfast'  => __( 'Bed & breakfast', 'foxi-builder' ),
			'Restaurant'       => __( 'Restaurant / café', 'foxi-builder' ),
			'EventVenue'       => __( 'Event venue', 'foxi-builder' ),
			'ProfessionalService' => __( 'Professional service', 'foxi-builder' ),
			'Service'          => __( 'Service page', 'foxi-builder' ),
			'Article'          => __( 'Article', 'foxi-builder' ),
			'AboutPage'        => __( 'About page', 'foxi-builder' ),
			'ContactPage'      => __( 'Contact page', 'foxi-builder' ),
		);
	}

	public static function add_faq( $question, $answer_html ) {
		$q = trim( preg_replace( '/\s+/', ' ', (string) $question ) );
		$a = trim( wp_kses( (string) $answer_html, array( 'a' => array( 'href' => true ), 'br' => array(), 'strong' => array(), 'em' => array(), 'p' => array(), 'ul' => array(), 'ol' => array(), 'li' => array() ) ) );
		$a = trim( preg_replace( '/\s+/', ' ', $a ) );
		if ( $q && $a ) {
			self::$faq[ md5( $q ) ] = array( $q, $a );
		}
	}

	/**
	 * Business entity built from Business details.
	 */
	public static function business_entity( $type = '' ) {
		$b    = Brand::business();
		$type = $type ? $type : Settings::get()['siteSchema'];
		$type = $type ? $type : 'LocalBusiness';

		$e = array(
			'@type' => $type,
			'@id'   => home_url( '/#business' ),
			'name'  => $b['name'] ? $b['name'] : get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);
		if ( $b['tagline'] ) {
			$e['description'] = $b['tagline'];
		}
		if ( $b['phone'] ) {
			$e['telephone'] = $b['phone'];
		}
		if ( $b['email'] ) {
			$e['email'] = $b['email'];
		}
		if ( $b['logoUrl'] ) {
			$e['logo']  = $b['logoUrl'];
			$e['image'] = $b['logoUrl'];
		}
		if ( $b['priceRange'] && 'Organization' !== $type ) {
			$e['priceRange'] = $b['priceRange'];
		}
		if ( $b['address'] || $b['city'] ) {
			$e['address'] = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $b['address'],
					'addressLocality' => $b['city'],
					'addressRegion'   => $b['region'],
					'postalCode'      => $b['postcode'],
					'addressCountry'  => $b['country'],
				)
			);
		}
		$same = array_values( array_filter( $b['socials'] ) );
		if ( $same ) {
			$e['sameAs'] = $same;
		}
		return $e;
	}

	public static function print_head( array $meta ) {
		$graph = array();

		if ( is_front_page() ) {
			$graph[] = array(
				'@type' => 'WebSite',
				'@id'   => home_url( '/#website' ),
				'url'   => home_url( '/' ),
				'name'  => get_bloginfo( 'name' ),
			);
			if ( Settings::get()['siteSchema'] ) {
				$graph[] = self::business_entity();
			}
		}

		if ( is_singular() ) {
			$type = isset( $meta['schemaType'] ) ? $meta['schemaType'] : '';
			// Blog posts are articles unless the page says otherwise.
			if ( ! $type && is_singular( 'post' ) ) {
				$type = 'Article';
			}
			if ( $type ) {
				$graph[] = self::page_entity( $type );
			}
		}

		$crumbs = self::breadcrumb_list();
		if ( $crumbs ) {
			$graph[] = $crumbs;
		}

		if ( $graph ) {
			self::print_json( array( '@context' => 'https://schema.org', '@graph' => $graph ) );
		}

		if ( is_singular() && ! empty( $meta['schemaJson'] ) ) {
			$custom = json_decode( \Foxi\Tokens::replace( $meta['schemaJson'] ), true );
			if ( is_array( $custom ) ) {
				self::print_json( $custom );
			}
		}
	}

	/**
	 * BreadcrumbList for every page except the home page. Search engines show
	 * this trail instead of the raw URL in results.
	 */
	private static function breadcrumb_list() {
		if ( is_front_page() || is_404() || is_search() || ! class_exists( '\\Foxi\\Render\\Breadcrumbs' ) ) {
			return null;
		}
		$trail = \Foxi\Render\Breadcrumbs::trail( array( 'showHome' => true, 'homeLabel' => __( 'Home', 'foxi-builder' ) ) );
		if ( count( $trail ) < 2 ) {
			return null;
		}
		$items = array();
		foreach ( array_values( $trail ) as $i => $item ) {
			$url     = $item[1] ? $item[1] : ( is_singular() ? get_permalink() : '' );
			$items[] = array_filter(
				array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => wp_strip_all_tags( $item[0] ),
					'item'     => $url ? $url : null,
				)
			);
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	private static function page_entity( $type ) {
		$post = get_queried_object();
		$url  = get_permalink( $post );
		$desc = Seo::description();

		if ( in_array( $type, array( 'Article' ), true ) ) {
			return array_filter(
				array(
					'@type'         => 'Article',
					'headline'      => get_the_title( $post ),
					'description'   => $desc,
					'datePublished' => get_the_date( 'c', $post ),
					'dateModified'  => get_the_modified_date( 'c', $post ),
					'author'        => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $post->post_author ) ),
					'image'         => has_post_thumbnail( $post ) ? get_the_post_thumbnail_url( $post, 'large' ) : null,
					'mainEntityOfPage' => $url,
				)
			);
		}
		if ( in_array( $type, array( 'AboutPage', 'ContactPage' ), true ) ) {
			return array(
				'@type'       => $type,
				'url'         => $url,
				'name'        => get_the_title( $post ),
				'description' => $desc,
				'about'       => array( '@id' => home_url( '/#business' ) ),
			);
		}
		if ( 'Service' === $type ) {
			return array_filter(
				array(
					'@type'       => 'Service',
					'name'        => get_the_title( $post ),
					'description' => $desc,
					'url'         => $url,
					'provider'    => array( '@id' => home_url( '/#business' ) ),
				)
			);
		}
		// Business-type entity for this page (e.g. a venue page).
		$e        = self::business_entity( $type );
		$e['@id'] = $url . '#entity';
		$e['url'] = $url;
		if ( $desc ) {
			$e['description'] = $desc;
		}
		if ( has_post_thumbnail( $post ) ) {
			$e['image'] = get_the_post_thumbnail_url( $post, 'large' );
		}
		return $e;
	}

	public static function print_faq() {
		if ( ! self::$faq ) {
			return;
		}
		$items = array();
		foreach ( self::$faq as $qa ) {
			$items[] = array(
				'@type'          => 'Question',
				'name'           => $qa[0],
				'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ),
			);
		}
		self::print_json(
			array(
				'@context'   => 'https://schema.org',
				'@type'      => 'FAQPage',
				'mainEntity' => $items,
			)
		);
	}

	private static function print_json( array $data ) {
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
		if ( $json ) {
			echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON_HEX_TAG prevents </script> breakout.
		}
	}
}
