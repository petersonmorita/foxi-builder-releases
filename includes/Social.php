<?php
/**
 * Recent posts from Instagram and a Facebook Page, for the Social Feed block.
 *
 * Instagram: "Instagram API with Instagram Login". The owner pastes a token
 * generated in their Meta app; it lasts 60 days and Foxi renews it weekly.
 * Facebook: a Page token, made from a short user token plus the app's ID and
 * secret, which doesn't expire.
 * LinkedIn only shares company posts with apps it has approved, so LinkedIn
 * (or anything else) can be added to a block by hand.
 *
 * Posts are cached (default 3 hours). If Instagram or Facebook can't be
 * reached, the last posts fetched keep showing.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

use Foxi\Forms\Config;

defined( 'ABSPATH' ) || exit;

class Social {

	const OPTION = 'foxi_social';
	const IG     = 'https://graph.instagram.com/';
	const FB     = 'https://graph.facebook.com/';

	public function init() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_action( 'foxi_social_refresh', array( __CLASS__, 'refresh_token' ) );
		if ( ! wp_next_scheduled( 'foxi_social_refresh' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'foxi_social_refresh' );
		}
	}

	/* ------------------------------ Settings ------------------------------ */

	private static function raw() {
		$o = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $o ) ? $o : array(),
			array(
				'igToken'    => '',
				'igUser'     => '',
				'igRefreshed' => 0,
				'fbPageId'   => '',
				'fbPageName' => '',
				'fbToken'    => '',
				'igBizId'    => '',
				'igBizUser'  => '',
				'cacheHours' => 3,
			)
		);
	}

	private static function token( $k ) {
		return Config::decrypt( self::raw()[ $k ] );
	}

	/** What the dashboard may see: no tokens. */
	public static function status() {
		$o = self::raw();
		return array(
			'instagram'  => array(
				'connected' => '' !== $o['igToken'] || ( '' !== $o['igBizId'] && '' !== $o['fbToken'] ),
				'username'  => $o['igToken'] ? $o['igUser'] : $o['igBizUser'],
				'viaPage'   => '' === $o['igToken'] && '' !== $o['igBizId'],
				'renewed'   => $o['igToken'] && $o['igRefreshed'] ? date_i18n( get_option( 'date_format' ), $o['igRefreshed'] ) : '',
			),
			'facebook'   => array( 'connected' => '' !== $o['fbToken'], 'page' => $o['fbPageName'] ),
			'cacheHours' => (int) $o['cacheHours'],
		);
	}

	private static function save( array $patch ) {
		update_option( self::OPTION, array_merge( self::raw(), $patch ), false );
		self::flush();
	}

	public static function flush() {
		delete_transient( 'foxi_social_instagram' );
		delete_transient( 'foxi_social_facebook' );
	}

	/* ------------------------------ Connecting ----------------------------- */

	/** Tokens are often copied with spaces, quotes or "access_token=" in front. */
	public static function clean_token( $token ) {
		$token = trim( (string) $token, " \t\n\r\0\x0B\"'" );
		$token = preg_replace( '/^(access_token=|bearer\s+)/i', '', $token );
		return preg_replace( '/\s+/', '', $token );
	}

	public static function connect_instagram( $token ) {
		$token = self::clean_token( $token );
		if ( 0 === strpos( $token, 'EAA' ) ) {
			return new \WP_Error( 'foxi_ig', __( 'That’s a Facebook token (it starts with EAA), not an Instagram one. Either generate the token under Instagram › “API setup with Instagram login” (it starts with IG), or connect your Facebook Page below: if your Instagram is linked to that Page, Foxi picks it up too.', 'foxi-builder' ) );
		}
		$me    = self::request( self::IG . 'me?' . http_build_query( array( 'fields' => 'user_id,username', 'access_token' => $token ) ) );
		if ( is_wp_error( $me ) ) {
			return $me;
		}
		if ( empty( $me['username'] ) ) {
			return new \WP_Error( 'foxi_ig', __( 'Instagram didn’t accept that token. Generate a new one and paste it again.', 'foxi-builder' ) );
		}
		// Make sure it's a 60-day token (a fresh one from the dashboard already is).
		$long = self::request( self::IG . 'refresh_access_token?' . http_build_query( array( 'grant_type' => 'ig_refresh_token', 'access_token' => $token ) ) );
		if ( ! is_wp_error( $long ) && ! empty( $long['access_token'] ) ) {
			$token = $long['access_token'];
		}
		self::save( array( 'igToken' => Config::encrypt( $token ), 'igUser' => sanitize_text_field( $me['username'] ), 'igRefreshed' => time() ) );
		return self::status();
	}

	/** Weekly: renew the 60-day Instagram token so it never runs out. */
	public static function refresh_token() {
		$token = self::token( 'igToken' );
		if ( ! $token ) {
			return;
		}
		$res = self::request( self::IG . 'refresh_access_token?' . http_build_query( array( 'grant_type' => 'ig_refresh_token', 'access_token' => $token ) ) );
		if ( ! is_wp_error( $res ) && ! empty( $res['access_token'] ) ) {
			self::save( array( 'igToken' => Config::encrypt( $res['access_token'] ), 'igRefreshed' => time() ) );
		}
	}

	/**
	 * Swap a short user token for the Pages it manages (with lasting Page tokens).
	 *
	 * @return array|\WP_Error List of { id, name } and stores tokens in a transient for the pick step.
	 */
	/**
	 * Find the Facebook Pages (and their linked Instagram accounts) a token can read.
	 *
	 * Works with the tokens newer Meta apps ("Unified login") give out:
	 * - a System User token from Meta Business settings (recommended: it
	 *   never expires and needs no App ID or secret),
	 * - a Page token,
	 * - or a short user token plus the App ID and secret, which is swapped
	 *   for a lasting one.
	 *
	 * @return array|\WP_Error List of { id, name, instagram }; tokens wait in a transient for the pick step.
	 */
	public static function facebook_pages( $app_id, $secret, $user_token ) {
		$token = self::clean_token( $user_token );
		if ( 0 === strpos( $token, 'IG' ) ) {
			return new \WP_Error( 'foxi_fb', __( 'That’s an Instagram Login token (it starts with IG). Paste it into the Instagram box instead. This box needs a Facebook token, which starts with EAA.', 'foxi-builder' ) );
		}
		if ( '' === $token ) {
			return new \WP_Error( 'foxi_fb', __( 'Paste the access token first.', 'foxi-builder' ) );
		}
		// A short user token lasts an hour; with the app's ID and secret it can be swapped for a lasting one.
		// System User and Page tokens can't be swapped and don't need to be, so carry on with the original.
		if ( '' !== trim( (string) $app_id ) && '' !== trim( (string) $secret ) ) {
			$long = self::request(
				self::FB . 'oauth/access_token?' . http_build_query(
					array(
						'grant_type'        => 'fb_exchange_token',
						'client_id'         => trim( $app_id ),
						'client_secret'     => trim( $secret ),
						'fb_exchange_token' => $token,
					)
				)
			);
			if ( ! is_wp_error( $long ) && ! empty( $long['access_token'] ) ) {
				$token = $long['access_token'];
			}
		}

		$fields = 'id,name,access_token,instagram_business_account{id,username}';
		$pages  = self::request( self::FB . 'me/accounts?' . http_build_query( array( 'fields' => $fields, 'limit' => 50, 'access_token' => $token ) ) );
		$found  = array();
		if ( is_wp_error( $pages ) ) {
			// A Page token is the Page itself: it has no "accounts".
			$page = self::request( self::FB . 'me?' . http_build_query( array( 'fields' => 'id,name,instagram_business_account{id,username}', 'access_token' => $token ) ) );
			if ( is_wp_error( $page ) || empty( $page['id'] ) || ( ! array_key_exists( 'instagram_business_account', $page ) && false === stripos( $pages->get_error_message(), 'node type (Page)' ) ) ) {
				return $pages;
			}
			$page['access_token'] = $token;
			$found[]              = $page;
		} else {
			$found = isset( $pages['data'] ) ? $pages['data'] : array();
		}

		$list = array();
		$keep = array();
		foreach ( $found as $p ) {
			$ig                = isset( $p['instagram_business_account']['id'] ) ? $p['instagram_business_account'] : null;
			$list[]            = array( 'id' => (string) $p['id'], 'name' => (string) $p['name'], 'instagram' => $ig && isset( $ig['username'] ) ? (string) $ig['username'] : '' );
			$keep[ $p['id'] ] = array( 'name' => (string) $p['name'], 'token' => (string) ( isset( $p['access_token'] ) ? $p['access_token'] : $token ), 'igId' => $ig ? (string) $ig['id'] : '', 'igUser' => $ig && isset( $ig['username'] ) ? (string) $ig['username'] : '' );
		}
		if ( ! $list ) {
			return new \WP_Error( 'foxi_fb', __( 'The token works, but no Facebook Pages came with it. In Meta Business settings, give the System User the hotel’s Page (and Instagram account) under Assign assets, then generate the token again with pages_show_list, pages_read_engagement and instagram_basic.', 'foxi-builder' ) );
		}
		set_transient( 'foxi_fb_pick_' . get_current_user_id(), Config::encrypt( wp_json_encode( $keep ) ), 15 * MINUTE_IN_SECONDS );
		return $list;
	}

	public static function choose_page( $page_id ) {
		$raw  = get_transient( 'foxi_fb_pick_' . get_current_user_id() );
		$keep = $raw ? json_decode( Config::decrypt( $raw ), true ) : array();
		if ( empty( $keep[ $page_id ] ) ) {
			return new \WP_Error( 'foxi_fb', __( 'That took too long. Paste the token and connect again.', 'foxi-builder' ) );
		}
		delete_transient( 'foxi_fb_pick_' . get_current_user_id() );
		$k = $keep[ $page_id ];
		self::save(
			array(
				'fbPageId'   => sanitize_text_field( $page_id ),
				'fbPageName' => sanitize_text_field( $k['name'] ),
				'fbToken'    => Config::encrypt( $k['token'] ),
				// The Instagram business account linked to this Page, if any.
				'igBizId'    => isset( $k['igId'] ) ? preg_replace( '/\D/', '', $k['igId'] ) : '',
				'igBizUser'  => isset( $k['igUser'] ) ? sanitize_text_field( $k['igUser'] ) : '',
			)
		);
		return self::status();
	}

	public static function disconnect( $network ) {
		if ( 'instagram' === $network ) {
			self::save( array( 'igToken' => '', 'igUser' => '', 'igBizId' => '', 'igBizUser' => '' ) );
		} elseif ( 'facebook' === $network ) {
			self::save( array( 'fbToken' => '', 'fbPageId' => '', 'fbPageName' => '', 'igBizId' => '', 'igBizUser' => '' ) );
		}
		return self::status();
	}

	/* -------------------------------- Posts -------------------------------- */

	/**
	 * Recent posts, newest first.
	 *
	 * @param string $source instagram, facebook or both.
	 * @return array|\WP_Error
	 */
	public static function posts( $source = 'instagram', $limit = 12 ) {
		$nets  = 'both' === $source ? array( 'instagram', 'facebook' ) : array( $source );
		$posts = array();
		$error = null;
		foreach ( $nets as $net ) {
			$got = self::cached( $net );
			if ( is_wp_error( $got ) ) {
				$error = $got;
				continue;
			}
			$posts = array_merge( $posts, $got );
		}
		if ( ! $posts && $error ) {
			return $error;
		}
		usort( $posts, static function ( $a, $b ) { return strcmp( $b['date'], $a['date'] ); } );
		return array_slice( $posts, 0, max( 1, min( 30, (int) $limit ) ) );
	}

	private static function cached( $net ) {
		$key  = 'foxi_social_' . $net;
		$hit  = get_transient( $key );
		if ( is_array( $hit ) ) {
			return $hit;
		}
		$got = 'instagram' === $net ? self::fetch_instagram() : self::fetch_facebook();
		if ( is_wp_error( $got ) ) {
			// Keep showing the last posts we had rather than an empty space.
			$last = get_option( $key . '_last' );
			if ( is_array( $last ) && $last ) {
				set_transient( $key, $last, 30 * MINUTE_IN_SECONDS );
				return $last;
			}
			return $got;
		}
		set_transient( $key, $got, max( 1, (int) self::raw()['cacheHours'] ) * HOUR_IN_SECONDS );
		update_option( $key . '_last', $got, false );
		return $got;
	}

	private static function fetch_instagram() {
		$token = self::token( 'igToken' );
		$base  = self::IG . 'me/media?';
		if ( ! $token ) {
			// Instagram linked to the connected Facebook Page.
			$o     = self::raw();
			$token = $o['igBizId'] ? self::token( 'fbToken' ) : '';
			$base  = self::FB . rawurlencode( $o['igBizId'] ) . '/media?';
		}
		if ( ! $token ) {
			return new \WP_Error( 'foxi_ig_none', __( 'Connect Instagram in Foxi › Settings › Social media.', 'foxi-builder' ) );
		}
		$res = self::request(
			$base . http_build_query(
				array(
					'fields'       => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,username',
					'limit'        => 25,
					'access_token' => $token,
				)
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$out = array();
		foreach ( isset( $res['data'] ) ? $res['data'] : array() as $m ) {
			$video = 'VIDEO' === ( isset( $m['media_type'] ) ? $m['media_type'] : '' );
			$image = $video ? ( isset( $m['thumbnail_url'] ) ? $m['thumbnail_url'] : '' ) : ( isset( $m['media_url'] ) ? $m['media_url'] : '' );
			if ( ! $image ) {
				continue;
			}
			$out[] = array(
				'id'      => 'ig_' . $m['id'],
				'network' => 'instagram',
				'image'   => esc_url_raw( $image ),
				'video'   => $video,
				'album'   => 'CAROUSEL_ALBUM' === ( isset( $m['media_type'] ) ? $m['media_type'] : '' ),
				'caption' => isset( $m['caption'] ) ? sanitize_textarea_field( $m['caption'] ) : '',
				'url'     => esc_url_raw( isset( $m['permalink'] ) ? $m['permalink'] : '' ),
				'date'    => isset( $m['timestamp'] ) ? gmdate( 'c', strtotime( $m['timestamp'] ) ) : '',
				'author'  => isset( $m['username'] ) ? sanitize_text_field( $m['username'] ) : '',
			);
		}
		return $out;
	}

	private static function fetch_facebook() {
		$o     = self::raw();
		$token = self::token( 'fbToken' );
		if ( ! $token || ! $o['fbPageId'] ) {
			return new \WP_Error( 'foxi_fb_none', __( 'Connect a Facebook Page in Foxi › Settings › Social media.', 'foxi-builder' ) );
		}
		$res = self::request(
			self::FB . rawurlencode( $o['fbPageId'] ) . '/posts?' . http_build_query(
				array(
					'fields'       => 'id,message,created_time,permalink_url,full_picture,attachments{media_type}',
					'limit'        => 25,
					'access_token' => $token,
				)
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$out = array();
		foreach ( isset( $res['data'] ) ? $res['data'] : array() as $p ) {
			if ( empty( $p['full_picture'] ) ) {
				continue; // Text-only posts don't suit a picture feed.
			}
			$type  = isset( $p['attachments']['data'][0]['media_type'] ) ? $p['attachments']['data'][0]['media_type'] : '';
			$out[] = array(
				'id'      => 'fb_' . $p['id'],
				'network' => 'facebook',
				'image'   => esc_url_raw( $p['full_picture'] ),
				'video'   => 'video' === $type,
				'album'   => 'album' === $type,
				'caption' => isset( $p['message'] ) ? sanitize_textarea_field( $p['message'] ) : '',
				'url'     => esc_url_raw( isset( $p['permalink_url'] ) ? $p['permalink_url'] : '' ),
				'date'    => isset( $p['created_time'] ) ? gmdate( 'c', strtotime( $p['created_time'] ) ) : '',
				'author'  => $o['fbPageName'],
			);
		}
		return $out;
	}

	private static function request( $url ) {
		$res = wp_remote_get( $url, array( 'timeout' => 12 ) );
		if ( is_wp_error( $res ) ) {
			return new \WP_Error( 'foxi_social_http', __( 'Couldn’t reach the social network. Showing nothing until it’s back.', 'foxi-builder' ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $body['error'] ) ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'The social network refused the request.', 'foxi-builder' );
			$code = isset( $body['error']['code'] ) ? (int) $body['error']['code'] : 0;
			if ( 200 === $code && false !== stripos( $msg, 'blocked' ) ) {
				$msg = __( 'Meta has blocked your Meta app from reading posts (“API access blocked”). This is set on Meta’s side, not in Foxi: open your app at developers.facebook.com and follow any red warning or “Required actions” there, or switch the app back to Development mode.', 'foxi-builder' );
			} elseif ( false !== stripos( $msg, 'parse access token' ) ) {
				$msg = __( 'Meta couldn’t read that token. Copy it again with the Copy button (all of it, no spaces), and check it’s the right kind: Instagram tokens start with IG, Facebook tokens start with EAA.', 'foxi-builder' );
			} elseif ( false !== stripos( $msg, 'expired' ) || false !== stripos( $msg, 'Session has' ) ) {
				$msg = __( 'That token has expired. Generate a new one and paste it again.', 'foxi-builder' );
			}
			return new \WP_Error( 'foxi_social_api', sanitize_text_field( $msg ) );
		}
		return is_array( $body ) ? $body : new \WP_Error( 'foxi_social_api', __( 'Unexpected reply from the social network.', 'foxi-builder' ) );
	}

	/* --------------------------------- REST -------------------------------- */

	public function routes() {
		$manage = static function () {
			return current_user_can( 'manage_options' );
		};
		register_rest_route(
			'foxi/v1',
			'/social',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $manage,
					'callback'            => array( __CLASS__, 'status' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $manage,
					'callback'            => static function ( \WP_REST_Request $r ) {
						$do = (string) $r->get_param( 'action' );
						if ( 'instagram' === $do ) {
							return self::connect_instagram( (string) $r->get_param( 'token' ) );
						}
						if ( 'facebook' === $do ) {
							return self::facebook_pages( (string) $r->get_param( 'appId' ), (string) $r->get_param( 'secret' ), (string) $r->get_param( 'token' ) );
						}
						if ( 'page' === $do ) {
							return self::choose_page( (string) $r->get_param( 'page' ) );
						}
						if ( 'disconnect' === $do ) {
							return self::disconnect( (string) $r->get_param( 'network' ) );
						}
						if ( 'cache' === $do ) {
							self::save( array( 'cacheHours' => max( 1, min( 24, (int) $r->get_param( 'hours' ) ) ) ) );
							return self::status();
						}
						if ( 'refresh' === $do ) {
							self::flush();
							return self::status();
						}
						return new \WP_Error( 'foxi_social', 'Unknown action', array( 'status' => 400 ) );
					},
				),
			)
		);
	}
}
