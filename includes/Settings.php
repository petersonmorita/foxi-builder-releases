<?php
/**
 * Plugin-wide settings.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Settings {

	const OPTION = 'foxi_settings';

	/** @var array|null */
	private static $cache = null;

	public function init() {
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
	}

	public static function defaults() {
		return array(
			// Pages use the Foxi full-page canvas (Foxi header + footer) by default.
			'canvasDefault'    => true,
			// Remove the active theme's stylesheets on Foxi canvas pages.
			'isolateTheme'     => true,
			// Post types that open in Foxi Studio.
			'studioPostTypes'  => array( 'page', 'foxi_part' ),
			'googleFonts'      => true,
			// SEO: 'auto' = on unless another SEO plugin is active.
			'seo'              => 'auto',
			'titleTemplate'    => '%title% %sep% %site%',
			'homeTitle'        => '%site% %sep% %tagline%',
			'separator'        => '|',
			'defaultOgImage'   => '',
			'siteSchema'       => 'LocalBusiness',
			'headerId'         => 0,
			'footerId'         => 0,
			// Blog, archive, search, single post and 404 pages use Foxi layouts with the Foxi header and footer.
			'siteTemplates'    => true,
			'archiveId'        => 0,
			'singleId'         => 0,
			'notFoundId'       => 0,
			'breakpointTablet' => 1024,
			'breakpointMobile' => 767,
		);
	}

	public static function get() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function update( array $values ) {
		$current = self::get();
		$clean   = array();
		$defs    = self::defaults();
		foreach ( $defs as $key => $default ) {
			if ( ! array_key_exists( $key, $values ) ) {
				$clean[ $key ] = $current[ $key ];
				continue;
			}
			$v = $values[ $key ];
			if ( is_bool( $default ) ) {
				$clean[ $key ] = (bool) $v;
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = (int) $v;
			} elseif ( is_array( $default ) ) {
				$clean[ $key ] = array_values( array_filter( array_map( 'sanitize_key', (array) $v ) ) );
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $v );
			}
		}
		update_option( self::OPTION, $clean );
		self::$cache = null;
		return self::get();
	}

	public static function flush() {
		self::$cache = null;
	}
}
