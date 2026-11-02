<?php
/**
 * Site-wide form settings: email delivery (SMTP), spam protection and how
 * long entries are kept. Secrets are stored encrypted and never sent back to
 * the browser.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Forms;

defined( 'ABSPATH' ) || exit;

class Config {

	const OPTION  = 'foxi_forms_settings';
	const SECRETS = array( 'mail.pass', 'spam.secret' );

	/** Known email services, so people only need a username and password. */
	const PROVIDERS = array(
		'gmail'   => array( 'host' => 'smtp.gmail.com', 'port' => 587, 'secure' => 'tls' ),
		'outlook' => array( 'host' => 'smtp.office365.com', 'port' => 587, 'secure' => 'tls' ),
		'other'   => array(),
	);

	public static function defaults() {
		return array(
			'mail'    => array(
				'smtp'      => false,
				'provider'  => 'gmail',
				'host'      => '',
				'port'      => 587,
				'secure'    => 'tls',
				'auth'      => true,
				'user'      => '',
				'pass'      => '',
				'fromEmail' => '',
				'fromName'  => '',
				'forceFrom' => true,
			),
			'spam'    => array(
				'provider'  => 'none',
				'siteKey'   => '',
				'secret'    => '',
				'threshold' => 0.5,
				'rate'      => 5,
				'maxLinks'  => 3,
				'words'     => '',
			),
			'entries' => array(
				'keepDays' => 0,
				'storeIp'  => true,
			),
		);
	}

	/** Settings with secrets decrypted (server use only). */
	public static function get() {
		$raw = get_option( self::OPTION, array() );
		$out = self::defaults();
		foreach ( $out as $group => $vals ) {
			if ( isset( $raw[ $group ] ) && is_array( $raw[ $group ] ) ) {
				$out[ $group ] = array_merge( $vals, array_intersect_key( $raw[ $group ], $vals ) );
			}
		}
		$out['mail']['pass']   = self::decrypt( $out['mail']['pass'] );
		$out['spam']['secret'] = self::decrypt( $out['spam']['secret'] );
		if ( defined( 'FOXI_SMTP_PASSWORD' ) ) {
			$out['mail']['pass'] = (string) FOXI_SMTP_PASSWORD;
		}
		if ( 'other' !== $out['mail']['provider'] && isset( self::PROVIDERS[ $out['mail']['provider'] ] ) ) {
			$out['mail'] = array_merge( $out['mail'], self::PROVIDERS[ $out['mail']['provider'] ] );
		}
		return $out;
	}

	/** Settings for the dashboard: secrets replaced by "is it set?". */
	public static function for_admin() {
		$s                       = self::get();
		$s['mail']['hasPass']    = '' !== $s['mail']['pass'];
		$s['mail']['pass']       = '';
		$s['mail']['passLocked'] = defined( 'FOXI_SMTP_PASSWORD' );
		$s['spam']['hasSecret']  = '' !== $s['spam']['secret'];
		$s['spam']['secret']     = '';
		$s['mail']['otherPlugin'] = self::other_mail_plugin();
		$s['mail']['lastError']  = (string) get_option( 'foxi_mail_last_error', '' );
		$s['mail']['siteDefault'] = get_option( 'admin_email' );
		return $s;
	}

	public static function save( array $in ) {
		$cur = self::get();
		$m   = isset( $in['mail'] ) && is_array( $in['mail'] ) ? $in['mail'] : array();
		$sp  = isset( $in['spam'] ) && is_array( $in['spam'] ) ? $in['spam'] : array();
		$en  = isset( $in['entries'] ) && is_array( $in['entries'] ) ? $in['entries'] : array();
		$str = static function ( $a, $k, $d ) {
			return isset( $a[ $k ] ) && is_scalar( $a[ $k ] ) ? sanitize_text_field( (string) $a[ $k ] ) : $d;
		};
		$provider = isset( $m['provider'], self::PROVIDERS[ $m['provider'] ] ) ? $m['provider'] : $cur['mail']['provider'];
		$pass     = isset( $m['pass'] ) && '' !== $m['pass'] ? preg_replace( '/\s+/', '', (string) $m['pass'] ) : $cur['mail']['pass']; // App passwords are often pasted with spaces.
		$secret   = isset( $sp['secret'] ) && '' !== $sp['secret'] ? trim( (string) $sp['secret'] ) : $cur['spam']['secret'];
		$sprov    = in_array( isset( $sp['provider'] ) ? $sp['provider'] : '', array( 'none', 'recaptcha2', 'recaptcha3', 'hcaptcha', 'turnstile' ), true ) ? $sp['provider'] : $cur['spam']['provider'];
		if ( $sprov !== $cur['spam']['provider'] && ! ( isset( $sp['secret'] ) && '' !== $sp['secret'] ) ) {
			$secret = ''; // Keys belong to one service; a new service needs new keys.
		}
		$data = array(
			'mail'    => array(
				'smtp'      => isset( $m['smtp'] ) ? (bool) $m['smtp'] : $cur['mail']['smtp'],
				'provider'  => $provider,
				'host'      => $str( $m, 'host', $cur['mail']['host'] ),
				'port'      => isset( $m['port'] ) ? max( 1, min( 65535, absint( $m['port'] ) ) ) : $cur['mail']['port'],
				'secure'    => in_array( isset( $m['secure'] ) ? $m['secure'] : '', array( 'tls', 'ssl', 'none' ), true ) ? $m['secure'] : $cur['mail']['secure'],
				'auth'      => isset( $m['auth'] ) ? (bool) $m['auth'] : true,
				'user'      => $str( $m, 'user', $cur['mail']['user'] ),
				'pass'      => self::encrypt( $pass ),
				'fromEmail' => isset( $m['fromEmail'] ) ? sanitize_email( $m['fromEmail'] ) : $cur['mail']['fromEmail'],
				'fromName'  => $str( $m, 'fromName', $cur['mail']['fromName'] ),
				'forceFrom' => isset( $m['forceFrom'] ) ? (bool) $m['forceFrom'] : true,
			),
			'spam'    => array(
				'provider'  => $sprov,
				'siteKey'   => $str( $sp, 'siteKey', $cur['spam']['siteKey'] ),
				'secret'    => self::encrypt( $secret ),
				'threshold' => isset( $sp['threshold'] ) ? max( 0.1, min( 0.9, (float) $sp['threshold'] ) ) : $cur['spam']['threshold'],
				'rate'      => isset( $sp['rate'] ) ? max( 0, min( 100, absint( $sp['rate'] ) ) ) : $cur['spam']['rate'],
				'maxLinks'  => isset( $sp['maxLinks'] ) ? max( 0, min( 50, absint( $sp['maxLinks'] ) ) ) : $cur['spam']['maxLinks'],
				'words'     => isset( $sp['words'] ) ? sanitize_textarea_field( (string) $sp['words'] ) : $cur['spam']['words'],
			),
			'entries' => array(
				'keepDays' => isset( $en['keepDays'] ) ? absint( $en['keepDays'] ) : $cur['entries']['keepDays'],
				'storeIp'  => isset( $en['storeIp'] ) ? (bool) $en['storeIp'] : $cur['entries']['storeIp'],
			),
		);
		if ( ( isset( $m['pass'] ) && '-' === $m['pass'] ) || defined( 'FOXI_SMTP_PASSWORD' ) ) {
			$data['mail']['pass'] = ''; // "Remove password", or it lives in wp-config.php.
		}
		update_option( self::OPTION, $data, false );
		return self::for_admin();
	}

	/** Public settings the form needs in the browser. */
	public static function captcha() {
		$s = self::get()['spam'];
		if ( 'none' === $s['provider'] || '' === $s['siteKey'] || '' === $s['secret'] ) {
			return null;
		}
		return array( 'provider' => $s['provider'], 'siteKey' => $s['siteKey'] );
	}

	public static function other_mail_plugin() {
		if ( defined( 'FLUENTMAIL' ) || function_exists( 'fluentSmtpInit' ) ) {
			return 'FluentSMTP';
		}
		if ( defined( 'WPMS_PLUGIN_VER' ) ) {
			return 'WP Mail SMTP';
		}
		if ( class_exists( 'Post_SMTP' ) || defined( 'POST_SMTP_VER' ) ) {
			return 'Post SMTP';
		}
		if ( defined( 'EASY_WP_SMTP_PLUGIN_VERSION' ) ) {
			return 'Easy WP SMTP';
		}
		return '';
	}

	/* ------------------------------- Secrets ------------------------------- */

	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ) . 'foxi-forms', true );
	}

	private static function encrypt( $plain ) {
		if ( '' === (string) $plain ) {
			return '';
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv = random_bytes( 16 );
			$ct = openssl_encrypt( (string) $plain, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
			if ( false !== $ct ) {
				return 'fx1:' . base64_encode( $iv . $ct ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}
		}
		return 'fx0:' . base64_encode( (string) $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	private static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( 0 === strpos( $stored, 'fx1:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw   = base64_decode( substr( $stored, 4 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			$plain = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
			return false === $plain ? '' : $plain;
		}
		if ( 0 === strpos( $stored, 'fx0:' ) ) {
			return (string) base64_decode( substr( $stored, 4 ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}
		return $stored;
	}
}
