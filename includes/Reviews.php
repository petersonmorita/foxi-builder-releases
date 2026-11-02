<?php
/**
 * Google reviews from the Places API (New).
 *
 * Google returns up to 5 reviews per place (its "most relevant" selection),
 * plus the overall rating and review count. Google's terms don't allow
 * storing Places content, so responses are only kept briefly in a transient
 * (default 24 hours) to keep requests, and costs, low.
 *
 * Settings (Foxi › Settings › Google): API key and the site's Google listing.
 * A Reviews block can point at a different listing.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Reviews {

	const API = 'https://places.googleapis.com/v1/';

	public function init() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		// A new key or listing should show straight away.
		add_action( 'update_option_' . Settings::OPTION, array( __CLASS__, 'flush' ) );
	}

	public static function api_key() {
		$s = Settings::get();
		return defined( 'FOXI_GOOGLE_API_KEY' ) ? (string) FOXI_GOOGLE_API_KEY : (string) $s['googleApiKey'];
	}

	public static function site_place() {
		$s = Settings::get();
		return (string) $s['googlePlaceId'];
	}

	public static function clean_place_id( $id ) {
		return substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id ), 0, 200 );
	}

	/** The site language as Google expects it, e.g. "en-AU". */
	public static function language() {
		return str_replace( '_', '-', get_locale() );
	}

	/**
	 * Rating, count, links and up to 5 reviews for a place.
	 *
	 * @param string $place_id Google place ID ('' = the site's listing).
	 * @param bool   $fresh    Skip the cache.
	 * @return array|\WP_Error
	 */
	public static function get( $place_id = '', $fresh = false ) {
		$place_id = self::clean_place_id( $place_id ? $place_id : self::site_place() );
		$key      = self::api_key();
		if ( ! $key ) {
			return new \WP_Error( 'foxi_no_key', __( 'Add a Google API key in Foxi › Settings › Google.', 'foxi-builder' ) );
		}
		if ( ! $place_id ) {
			return new \WP_Error( 'foxi_no_place', __( 'Choose your Google listing in Foxi › Settings › Google.', 'foxi-builder' ) );
		}
		$lang  = self::language();
		$cache = 'foxi_gr_' . md5( $place_id . '|' . $lang );
		if ( ! $fresh ) {
			$hit = get_transient( $cache );
			if ( is_array( $hit ) ) {
				return $hit;
			}
		}

		$res = wp_remote_get(
			self::API . 'places/' . rawurlencode( $place_id ) . '?languageCode=' . rawurlencode( $lang ),
			array(
				'timeout' => 12,
				'headers' => array(
					'X-Goog-Api-Key'   => $key,
					'X-Goog-FieldMask' => 'id,displayName,rating,userRatingCount,googleMapsUri,googleMapsLinks,reviews',
				),
			)
		);
		$data = self::response( $res );
		if ( is_wp_error( $data ) ) {
			// Don't hammer Google while something is wrong; try again in 15 minutes.
			set_transient( $cache, array( 'error' => $data->get_error_message() ), 15 * MINUTE_IN_SECONDS );
			return $data;
		}

		$out = self::normalise( $data, $place_id );
		$s   = Settings::get();
		set_transient( $cache, $out, max( 1, min( 72, (int) $s['reviewsCacheHours'] ) ) * HOUR_IN_SECONDS );
		return $out;
	}

	/** Clear cached reviews (all places). */
	public static function flush() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_foxi\\_gr\\_%' OR option_name LIKE '\\_transient\\_timeout\\_foxi\\_gr\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/** Find places by name (for picking the listing). */
	public static function search( $query ) {
		$key = self::api_key();
		if ( ! $key ) {
			return new \WP_Error( 'foxi_no_key', __( 'Add a Google API key first.', 'foxi-builder' ) );
		}
		$res  = wp_remote_post(
			self::API . 'places:searchText',
			array(
				'timeout' => 12,
				'headers' => array(
					'Content-Type'     => 'application/json',
					'X-Goog-Api-Key'   => $key,
					'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.rating,places.userRatingCount',
				),
				'body'    => wp_json_encode( array( 'textQuery' => (string) $query, 'languageCode' => self::language() ) ),
			)
		);
		$data = self::response( $res );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$out = array();
		foreach ( isset( $data['places'] ) ? $data['places'] : array() as $p ) {
			$out[] = array(
				'id'      => (string) $p['id'],
				'name'    => (string) foxi_get( $p, 'displayName.text', '' ),
				'address' => isset( $p['formattedAddress'] ) ? (string) $p['formattedAddress'] : '',
				'rating'  => isset( $p['rating'] ) ? (float) $p['rating'] : 0,
				'count'   => isset( $p['userRatingCount'] ) ? (int) $p['userRatingCount'] : 0,
			);
		}
		return $out;
	}

	private static function response( $res ) {
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== (int) $code || ! is_array( $body ) ) {
			$msg = is_array( $body ) ? (string) foxi_get( $body, 'error.message', '' ) : '';
			/* translators: 1: HTTP status, 2: Google's message */
			return new \WP_Error( 'foxi_google', sprintf( __( 'Google said no (%1$d): %2$s', 'foxi-builder' ), (int) $code, $msg ? $msg : __( 'unknown error', 'foxi-builder' ) ) );
		}
		return $body;
	}

	private static function normalise( array $p, $place_id ) {
		$reviews = array();
		foreach ( isset( $p['reviews'] ) && is_array( $p['reviews'] ) ? $p['reviews'] : array() as $r ) {
			$text     = (string) foxi_get( $r, 'text.text', '' );
			$original = (string) foxi_get( $r, 'originalText.text', '' );
			$lang     = (string) foxi_get( $r, 'text.languageCode', '' );
			$olang    = (string) foxi_get( $r, 'originalText.languageCode', '' );
			$reviews[] = array(
				'id'         => (string) ( isset( $r['name'] ) ? $r['name'] : md5( wp_json_encode( $r ) ) ),
				'rating'     => (int) ( isset( $r['rating'] ) ? $r['rating'] : 0 ),
				'text'       => $text,
				'original'   => $original,
				'translated' => $olang && $lang && $olang !== $lang,
				'author'     => (string) foxi_get( $r, 'authorAttribution.displayName', '' ),
				'authorUrl'  => (string) foxi_get( $r, 'authorAttribution.uri', '' ),
				'photo'      => (string) foxi_get( $r, 'authorAttribution.photoUri', '' ),
				'time'       => (string) ( isset( $r['publishTime'] ) ? $r['publishTime'] : '' ),
				'relative'   => (string) ( isset( $r['relativePublishTimeDescription'] ) ? $r['relativePublishTimeDescription'] : '' ),
				'url'        => (string) ( isset( $r['googleMapsUri'] ) ? $r['googleMapsUri'] : '' ),
			);
		}
		$maps = isset( $p['googleMapsUri'] ) ? (string) $p['googleMapsUri'] : '';
		return array(
			'placeId'    => $place_id,
			'name'       => (string) foxi_get( $p, 'displayName.text', '' ),
			'rating'     => isset( $p['rating'] ) ? (float) $p['rating'] : 0,
			'count'      => isset( $p['userRatingCount'] ) ? (int) $p['userRatingCount'] : 0,
			'url'        => $maps,
			'reviewsUrl' => (string) foxi_get( $p, 'googleMapsLinks.reviewsUri', $maps ? $maps : 'https://search.google.com/local/reviews?placeid=' . rawurlencode( $place_id ) ),
			'writeUrl'   => (string) foxi_get( $p, 'googleMapsLinks.writeAReviewUri', 'https://search.google.com/local/writereview?placeid=' . rawurlencode( $place_id ) ),
			'reviews'    => $reviews,
			'fetched'    => time(),
		);
	}

	/** Sample data for the editor before Google is connected. */
	public static function sample() {
		$names = array( 'Sarah M.', 'James L.', 'Priya K.', 'Tom R.', 'Emma W.' );
		$texts = array(
			__( 'A beautiful, peaceful place. The rooms were spotless and the staff could not have been more helpful. We will be back!', 'foxi-builder' ),
			__( 'Stunning gardens and views. Breakfast was excellent and the location made it easy to explore the area.', 'foxi-builder' ),
			__( 'We held our team retreat here and everything ran perfectly, from the meeting room to the dinners.', 'foxi-builder' ),
			__( 'Great value and a lovely quiet spot. The bed was very comfortable.', 'foxi-builder' ),
			__( 'Our wedding weekend was magical. Thank you to the whole team for looking after us and our guests.', 'foxi-builder' ),
		);
		$reviews = array();
		foreach ( $names as $i => $n ) {
			$reviews[] = array(
				'id'         => 'sample-' . $i,
				'rating'     => 3 === $i ? 4 : 5,
				'text'       => $texts[ $i ],
				'original'   => '',
				'translated' => false,
				'author'     => $n,
				'authorUrl'  => '',
				'photo'      => '',
				'time'       => gmdate( 'c', time() - ( $i + 1 ) * 3 * WEEK_IN_SECONDS ),
				/* translators: %d: number of weeks */
				'relative'   => sprintf( _n( '%d week ago', '%d weeks ago', ( $i + 1 ) * 3, 'foxi-builder' ), ( $i + 1 ) * 3 ),
				'url'        => '',
			);
		}
		return array(
			'placeId'    => '',
			'name'       => get_bloginfo( 'name' ),
			'rating'     => 4.8,
			'count'      => 128,
			'url'        => '',
			'reviewsUrl' => '',
			'writeUrl'   => '',
			'reviews'    => $reviews,
			'fetched'    => time(),
			'sample'     => true,
		);
	}

	public function routes() {
		register_rest_route(
			Rest::NS,
			'/google/search',
			array(
				'methods'             => 'GET',
				'callback'            => static function ( $req ) {
					$r = self::search( (string) $req->get_param( 'q' ) );
					return is_wp_error( $r ) ? new \WP_REST_Response( array( 'message' => $r->get_error_message() ), 400 ) : $r;
				},
				'permission_callback' => array( Rest::class, 'can_manage' ),
			)
		);
		register_rest_route(
			Rest::NS,
			'/google/reviews',
			array(
				'methods'             => 'GET',
				'callback'            => static function ( $req ) {
					$fresh = (bool) $req->get_param( 'fresh' );
					if ( $fresh ) {
						self::flush();
					}
					$r = self::get( (string) $req->get_param( 'place' ), $fresh );
					return is_wp_error( $r ) ? new \WP_REST_Response( array( 'message' => $r->get_error_message(), 'code' => $r->get_error_code() ), 200 ) : $r;
				},
				'permission_callback' => array( Rest::class, 'can_edit' ),
			)
		);
	}
}
