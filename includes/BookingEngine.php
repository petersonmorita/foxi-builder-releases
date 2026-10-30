<?php
/**
 * Site-wide booking engine settings: the link parameter names each booking
 * system expects. Stored with the Business details (Brand & business), so
 * every Booking Widget on the site shares them. A widget can still override
 * any parameter for itself.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class BookingEngine {

	/** Parameter keys a widget can send. */
	const KEYS = array( 'arrival', 'departure', 'nights', 'guests', 'adults', 'children', 'rooms', 'promo' );

	/** Date formats the booking link can use (PHP date() letters, mirrored in the front-end script). */
	const DATE_FORMATS = array( 'Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Ymd' );

	/**
	 * Known booking engines. Only engines whose link format has been checked
	 * against a live site are listed; everything else uses "custom".
	 */
	public static function presets() {
		return array(
			'custom'     => array(
				'label'      => __( 'Other / custom', 'foxi-builder' ),
				'dateFormat' => 'Y-m-d',
				'params'     => array(
					'arrival'   => 'arrival',
					'departure' => 'departure',
					'nights'    => '',
					'guests'    => 'guests',
					'adults'    => 'adults',
					'children'  => 'children',
					'rooms'     => 'rooms',
					'promo'     => 'promo',
				),
			),
			'siteminder' => array(
				'label'      => 'SiteMinder',
				'dateFormat' => 'Y-m-d',
				'params'     => array(
					'arrival'   => 'checkInDate',
					'departure' => 'checkOutDate',
					'nights'    => '',
					'guests'    => 'items[0][adults]',
					'adults'    => 'items[0][adults]',
					'children'  => 'items[0][children]',
					'rooms'     => '',
					'promo'     => '',
				),
			),
		);
	}

	public static function defaults() {
		return array(
			'engine'     => 'custom',
			'dateFormat' => '',
			'params'     => array_fill_keys( self::KEYS, '' ),
		);
	}

	/** Clean a settings array from the Business details form. */
	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$presets = self::presets();
		$out     = self::defaults();
		if ( isset( $input['engine'] ) && isset( $presets[ $input['engine'] ] ) ) {
			$out['engine'] = $input['engine'];
		}
		if ( isset( $input['dateFormat'] ) && in_array( $input['dateFormat'], self::DATE_FORMATS, true ) ) {
			$out['dateFormat'] = $input['dateFormat'];
		}
		foreach ( self::KEYS as $k ) {
			$out['params'][ $k ] = self::clean_param( foxi_get( $input, "params.$k", '' ) );
		}
		return $out;
	}

	/** Parameter names may contain brackets, e.g. items[0][adults]. */
	public static function clean_param( $v ) {
		return substr( preg_replace( '/[^A-Za-z0-9_\-\[\]\.]/', '', (string) $v ), 0, 80 );
	}

	/**
	 * The settings a widget uses: its own overrides, then the site settings,
	 * then the chosen engine's defaults.
	 *
	 * @param array $a Block attributes.
	 * @return array{dateFormat:string,params:array<string,string>}
	 */
	public static function resolve( array $a ) {
		$business = Brand::business();
		$site     = self::sanitize( isset( $business['booking'] ) ? $business['booking'] : array() );
		$presets  = self::presets();
		$preset   = $presets[ $site['engine'] ];

		$params = array();
		foreach ( self::KEYS as $k ) {
			$own = self::clean_param( isset( $a[ $k . 'Param' ] ) ? $a[ $k . 'Param' ] : '' );
			if ( '' !== $own ) {
				$params[ $k ] = $own;
			} elseif ( '' !== $site['params'][ $k ] ) {
				$params[ $k ] = $site['params'][ $k ];
			} else {
				$params[ $k ] = $preset['params'][ $k ];
			}
		}
		return array(
			'dateFormat' => $site['dateFormat'] ? $site['dateFormat'] : $preset['dateFormat'],
			'params'     => $params,
		);
	}
}
