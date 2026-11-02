<?php
/**
 * Email delivery: optional SMTP (Gmail, Microsoft 365 or any server), a tidy
 * HTML layout for form emails, and a record of what failed and why.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Forms;

defined( 'ABSPATH' ) || exit;

class Mail {

	/** Last wp_mail() failure message during this request. */
	private static $error = '';

	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'smtp' ), 999 );
		add_action( 'wp_mail_failed', array( __CLASS__, 'failed' ) );
		add_filter( 'wp_mail_from', array( __CLASS__, 'from_email' ), 999 );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'from_name' ), 999 );
	}

	private static function conf() {
		return Config::get()['mail'];
	}

	public static function smtp( $phpmailer ) {
		$m = self::conf();
		if ( empty( $m['smtp'] ) || '' === $m['host'] ) {
			return;
		}
		$phpmailer->isSMTP();
		$phpmailer->Host       = $m['host']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Port       = (int) $m['port']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPSecure = 'none' === $m['secure'] ? '' : $m['secure']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPAutoTLS = 'none' !== $m['secure']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->SMTPAuth   = ! empty( $m['auth'] ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( ! empty( $m['auth'] ) ) {
			$phpmailer->Username = $m['user']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$phpmailer->Password = $m['pass']; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
		$phpmailer->Timeout = 15; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/** Gmail and Microsoft only send from the signed-in address (or its aliases). */
	public static function from_email( $email ) {
		$m    = self::conf();
		$from = $m['fromEmail'] ? $m['fromEmail'] : ( ! empty( $m['smtp'] ) && is_email( $m['user'] ) ? $m['user'] : '' );
		if ( ! $from ) {
			return $email;
		}
		return ( ! empty( $m['forceFrom'] ) || 0 === strpos( $email, 'wordpress@' ) ) ? $from : $email;
	}

	public static function from_name( $name ) {
		if ( 'WordPress' !== $name ) {
			return $name; // A name chosen for this email (e.g. in a form notification).
		}
		$m = self::conf();
		return $m['fromName'] ? $m['fromName'] : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	}

	public static function failed( $error ) {
		self::$error = is_wp_error( $error ) ? $error->get_error_message() : __( 'Unknown error', 'foxi-builder' );
		update_option( 'foxi_mail_last_error', gmdate( 'Y-m-d H:i' ) . ' UTC: ' . self::$error, false );
	}

	/**
	 * Send one email and say what happened.
	 *
	 * @return array{ok:bool,error:string}
	 */
	public static function send( $to, $subject, $html, array $headers = array(), array $attachments = array() ) {
		self::$error = '';
		$headers[]   = 'Content-Type: text/html; charset=UTF-8';
		$ok          = wp_mail( $to, $subject, $html, $headers, $attachments );
		if ( $ok ) {
			delete_option( 'foxi_mail_last_error' );
		}
		return array( 'ok' => (bool) $ok, 'error' => $ok ? '' : ( self::$error ? self::$error : __( 'The server refused to send the email.', 'foxi-builder' ) ) );
	}

	public static function test( $to ) {
		if ( ! is_email( $to ) ) {
			return array( 'ok' => false, 'error' => __( 'Enter a valid email address.', 'foxi-builder' ) );
		}
		$m    = self::conf();
		$how  = ! empty( $m['smtp'] ) ? sprintf( /* translators: %s: mail server */ __( 'It was sent through %s.', 'foxi-builder' ), $m['host'] ) : __( 'It was sent by your web host (no SMTP set up).', 'foxi-builder' );
		$body = self::layout(
			__( 'Test email', 'foxi-builder' ),
			'<p>' . esc_html__( 'If you’re reading this, your website can send email.', 'foxi-builder' ) . '</p><p>' . esc_html( $how ) . '</p>'
		);
		return self::send( $to, sprintf( /* translators: %s: site name */ __( 'Test email from %s', 'foxi-builder' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ), $body );
	}

	/** A plain, readable email layout that works in every mail app. */
	public static function layout( $title, $inner ) {
		$site = esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
			. '<body style="margin:0;padding:24px 12px;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#1f2430;">'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">'
			. '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:10px;">'
			. '<tr><td style="padding:28px 32px 8px;font-size:13px;color:#6b7280;">' . $site . '</td></tr>'
			. '<tr><td style="padding:0 32px 8px;font-size:20px;font-weight:600;line-height:1.3;">' . esc_html( $title ) . '</td></tr>'
			. '<tr><td style="padding:8px 32px 32px;font-size:15px;line-height:1.6;">' . $inner . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	/** Field rows for {all_fields}. */
	public static function table( array $rows ) {
		$out = '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:8px 0;">';
		foreach ( $rows as $r ) {
			$out .= '<tr><td style="padding:10px 0 2px;font-size:12px;font-weight:600;color:#6b7280;">' . esc_html( $r['label'] ) . '</td></tr>'
				. '<tr><td style="padding:0 0 10px;border-bottom:1px solid #eeeff2;font-size:15px;">' . ( isset( $r['html'] ) ? $r['html'] : nl2br( esc_html( $r['value'] ) ) ) . '</td></tr>';
		}
		return $out . '</table>';
	}
}
