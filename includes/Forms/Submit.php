<?php
/**
 * Handles a submission of a saved form: restrictions, spam checks, the
 * form's own show/hide rules, validation, file uploads, saving the entry and
 * sending its emails. The browser is never trusted.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Forms;

use Foxi\Tokens;

defined( 'ABSPATH' ) || exit;

class Submit {

	const IMAGES = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic' );
	const DOCS   = array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt' );

	/**
	 * @return array|\WP_Error
	 */
	public static function handle( $form_id, \WP_REST_Request $r ) {
		$form = Store::get( $form_id );
		if ( ! $form ) {
			return self::err( 'foxi_form', __( 'This form could not be found.', 'foxi-builder' ), 404 );
		}
		$params = $r->get_body_params() ? $r->get_body_params() : (array) $r->get_json_params();
		$s      = $form['settings'];
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$closed = self::closed( $form );
		if ( $closed ) {
			return self::err( 'foxi_closed', $closed, 403 );
		}

		// Spam traps: a hidden field people never see, and a minimum fill time.
		if ( ! empty( $params['fx_website'] ) ) {
			return array( 'ok' => true, 'type' => 'message', 'message' => wp_kses_post( $s['confirmation']['message'] ) );
		}
		$started = isset( $params['_fx_t'] ) ? (int) $params['_fx_t'] : 0;
		if ( ! $started || time() - $started < 3 ) {
			return self::err( 'foxi_too_fast', __( 'Please take a moment to complete the form, then try again.', 'foxi-builder' ), 400 );
		}

		$conf = Config::get();
		$rate = (int) $conf['spam']['rate'];
		$rkey = 'foxi_rl_' . md5( $ip . '|' . $form['id'] );
		$sent = (int) get_transient( $rkey );
		if ( $rate && $sent >= $rate ) {
			return self::err( 'foxi_rate', __( 'Too many submissions in a short time. Please try again in a few minutes.', 'foxi-builder' ), 429 );
		}

		if ( ! empty( $s['captcha'] ) && Config::captcha() ) {
			$ok = self::verify_captcha( $conf['spam'], $params, $ip );
			if ( true !== $ok ) {
				return self::err( 'foxi_captcha', $ok, 400 );
			}
		}

		// Values for every input field, then work out which ones are showing.
		$fields = array_values( array_filter( $form['fields'], static function ( $f ) { return in_array( $f['type'], Store::INPUTS, true ); } ) );
		$values = array();
		foreach ( $fields as $f ) {
			$values[ $f['key'] ] = self::read( $f, $params );
		}
		$shown  = self::visible( $form['fields'], $values );
		$files  = $r->get_file_params();
		$errors = array();
		$rows   = array();
		$up     = array();

		foreach ( $fields as $f ) {
			$k = $f['key'];
			if ( empty( $shown[ $k ] ) ) {
				$values[ $k ] = '';
				continue;
			}
			if ( 'file' === $f['type'] ) {
				$res = self::files( $f, isset( $files[ $k ] ) ? $files[ $k ] : null );
				if ( is_wp_error( $res ) ) {
					$errors[ $k ] = $res->get_error_message();
					continue;
				}
				$up[ $k ]     = $res;
				$values[ $k ] = implode( ', ', wp_list_pluck( $res, 'name' ) );
				$rows[]       = array( 'key' => $k, 'label' => self::label( $f ), 'value' => $values[ $k ], 'type' => 'file' );
				continue;
			}
			$err = self::validate( $f, $values[ $k ] );
			if ( $err ) {
				$errors[ $k ] = $err;
				continue;
			}
			if ( is_array( $values[ $k ] ) ? ! $values[ $k ] : '' === $values[ $k ] ) {
				continue; // Left blank: nothing to show in the entry or email.
			}
			$rows[] = array( 'key' => $k, 'label' => self::label( $f ), 'value' => self::display( $f, $values[ $k ] ), 'type' => $f['type'] );
		}

		if ( $errors ) {
			self::cleanup( $up );
			return new \WP_Error( 'foxi_invalid', __( 'Please check the highlighted fields.', 'foxi-builder' ), array( 'status' => 422, 'fields' => $errors ) );
		}

		$spam = self::spammy( $rows, $conf['spam'] );
		if ( $spam ) {
			self::cleanup( $up );
			return self::err( 'foxi_spam', $spam, 400 );
		}

		if ( $rate ) {
			set_transient( $rkey, $sent + 1, 10 * MINUTE_IN_SECONDS );
		}

		$page = isset( $params['_fx_page'] ) ? absint( $params['_fx_page'] ) : 0;
		$ctx  = array(
			'form'   => $form,
			'values' => $values,
			'rows'   => $rows,
			'files'  => $up,
			'page'   => $page,
			'ip'     => $ip,
			'entry'  => 0,
		);

		if ( ! empty( $s['store'] ) ) {
			$ctx['entry'] = Entries::create( $form, $rows, $up, $page, ! empty( $conf['entries']['storeIp'] ) ? $ip : '' );
		}

		$log = self::notify( $ctx );
		if ( $ctx['entry'] ) {
			update_post_meta( $ctx['entry'], '_foxi_mail', $log );
		}
		if ( ! $ctx['entry'] ) {
			self::cleanup( $up ); // Not keeping entries: uploads were only needed for the email.
		}

		/**
		 * After a Foxi form is submitted and its emails sent.
		 *
		 * @param array $ctx Form, values, rows, files, page and entry id.
		 */
		do_action( 'foxi_form_submitted', $ctx );

		$c = $s['confirmation'];
		if ( 'page' === $c['type'] && $c['pageId'] && get_permalink( $c['pageId'] ) ) {
			return array( 'ok' => true, 'type' => 'redirect', 'url' => get_permalink( $c['pageId'] ) );
		}
		if ( 'url' === $c['type'] && $c['url'] ) {
			return array( 'ok' => true, 'type' => 'redirect', 'url' => esc_url_raw( self::merge( $c['url'], $ctx, 'url' ) ) );
		}
		return array(
			'ok'       => true,
			'type'     => 'message',
			'message'  => wp_kses_post( wpautop( self::merge( $c['message'], $ctx, 'text' ) ) ),
			'hideForm' => ! empty( $c['hideForm'] ),
		);
	}

	private static function err( $code, $msg, $status ) {
		return new \WP_Error( $code, $msg, array( 'status' => $status ) );
	}

	/** Why the form is closed right now, or ''. */
	public static function closed( array $form ) {
		$s = $form['settings'];
		if ( ! empty( $s['loginOnly'] ) && ! is_user_logged_in() ) {
			return __( 'Please log in to use this form.', 'foxi-builder' );
		}
		if ( ! empty( $s['schedule']['enabled'] ) ) {
			$now = current_time( 'Y-m-d\TH:i' );
			if ( ( $s['schedule']['start'] && $now < $s['schedule']['start'] ) || ( $s['schedule']['end'] && $now > $s['schedule']['end'] . ( strlen( $s['schedule']['end'] ) === 10 ? 'T23:59' : '' ) ) ) {
				return $s['schedule']['message'];
			}
		}
		if ( ! empty( $s['limit']['enabled'] ) && Entries::count( $form['id'] ) >= $s['limit']['max'] ) {
			return $s['limit']['message'];
		}
		return '';
	}

	/* ------------------------------- Values -------------------------------- */

	private static function read( array $f, array $p ) {
		$raw = isset( $p[ $f['key'] ] ) ? $p[ $f['key'] ] : '';
		if ( 'checkboxes' === $f['type'] ) {
			$raw = is_array( $raw ) ? $raw : ( '' === $raw ? array() : array( $raw ) );
			return array_values( array_map( 'sanitize_text_field', array_map( 'strval', $raw ) ) );
		}
		if ( is_array( $raw ) ) {
			$raw = implode( ', ', array_map( 'strval', $raw ) );
		}
		if ( 'textarea' === $f['type'] ) {
			return sanitize_textarea_field( (string) $raw );
		}
		if ( 'consent' === $f['type'] ) {
			return '' !== (string) $raw && '0' !== (string) $raw ? '1' : '';
		}
		return trim( sanitize_text_field( (string) $raw ) );
	}

	/** Which input fields are showing, given the values (rules may chain). */
	public static function visible( array $all, array $values ) {
		$shown = array();
		foreach ( $all as $f ) {
			$shown[ $f['key'] ] = true;
		}
		for ( $pass = 0; $pass < 5; $pass++ ) {
			$changed = false;
			foreach ( $all as $f ) {
				if ( empty( $f['cond']['enabled'] ) ) {
					continue;
				}
				$vals = array();
				foreach ( $values as $k => $v ) {
					$vals[ $k ] = ! empty( $shown[ $k ] ) ? $v : '';
				}
				$on = self::rules_pass( $f['cond'], $vals );
				$on = 'hide' === $f['cond']['action'] ? ! $on : $on;
				if ( $shown[ $f['key'] ] !== $on ) {
					$shown[ $f['key'] ] = $on;
					$changed            = true;
				}
			}
			if ( ! $changed ) {
				break;
			}
		}
		// A field inside a hidden step is hidden too.
		$step_on = true;
		foreach ( $all as $f ) {
			if ( 'step' === $f['type'] ) {
				$step_on = ! empty( $shown[ $f['key'] ] );
				continue;
			}
			if ( ! $step_on ) {
				$shown[ $f['key'] ] = false;
			}
		}
		return $shown;
	}

	public static function rules_pass( array $cond, array $vals ) {
		if ( empty( $cond['enabled'] ) ) {
			return true;
		}
		$results = array();
		foreach ( $cond['rules'] as $rule ) {
			$results[] = self::rule( $rule, isset( $vals[ $rule['field'] ] ) ? $vals[ $rule['field'] ] : '' );
		}
		return 'any' === $cond['match'] ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}

	private static function rule( array $rule, $v ) {
		$list = is_array( $v ) ? $v : ( '' === $v ? array() : array( (string) $v ) );
		$want = strtolower( trim( $rule['value'] ) );
		$low  = array_map( static function ( $x ) { return strtolower( trim( (string) $x ) ); }, $list );
		switch ( $rule['op'] ) {
			case 'is':
				return in_array( $want, $low, true );
			case 'is_not':
				return ! in_array( $want, $low, true );
			case 'contains':
				return (bool) array_filter( $low, static function ( $x ) use ( $want ) { return '' !== $want && false !== strpos( $x, $want ); } );
			case 'not_contains':
				return ! array_filter( $low, static function ( $x ) use ( $want ) { return '' !== $want && false !== strpos( $x, $want ); } );
			case 'empty':
				return ! $list;
			case 'not_empty':
				return (bool) $list;
			case 'gt':
				return $list && is_numeric( $list[0] ) && (float) $list[0] > (float) $rule['value'];
			case 'lt':
				return $list && is_numeric( $list[0] ) && (float) $list[0] < (float) $rule['value'];
		}
		return false;
	}

	private static function validate( array $f, $v ) {
		$empty = is_array( $v ) ? ! $v : '' === $v;
		if ( $empty ) {
			if ( ! empty( $f['required'] ) ) {
				return $f['requiredMsg'] ? $f['requiredMsg'] : ( 'consent' === $f['type'] ? __( 'Please tick this box to continue.', 'foxi-builder' ) : __( 'This field is required.', 'foxi-builder' ) );
			}
			return '';
		}
		switch ( $f['type'] ) {
			case 'email':
				return is_email( $v ) ? '' : __( 'Enter a valid email address.', 'foxi-builder' );
			case 'url':
				return wp_http_validate_url( preg_match( '#^https?://#i', $v ) ? $v : 'https://' . $v ) ? '' : __( 'Enter a valid web address.', 'foxi-builder' );
			case 'tel':
				return preg_match( '/^[0-9 +().\-]{6,24}$/', $v ) ? '' : __( 'Enter a valid phone number.', 'foxi-builder' );
			case 'number':
				if ( ! is_numeric( $v ) ) {
					return __( 'Enter a number.', 'foxi-builder' );
				}
				if ( '' !== $f['min'] && $v < $f['min'] ) {
					/* translators: %s: minimum */
					return sprintf( __( 'Enter %s or more.', 'foxi-builder' ), $f['min'] );
				}
				if ( '' !== $f['max'] && $v > $f['max'] ) {
					/* translators: %s: maximum */
					return sprintf( __( 'Enter %s or less.', 'foxi-builder' ), $f['max'] );
				}
				return '';
			case 'date':
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
					return __( 'Enter a valid date.', 'foxi-builder' );
				}
				return ! empty( $f['future'] ) && $v < current_time( 'Y-m-d' ) ? __( 'Choose a date from today onwards.', 'foxi-builder' ) : '';
			case 'time':
				return preg_match( '/^\d{2}:\d{2}$/', $v ) ? '' : __( 'Enter a valid time.', 'foxi-builder' );
			case 'select':
			case 'radio':
				return in_array( $v, wp_list_pluck( $f['options'], 'value' ), true ) ? '' : __( 'Choose one of the options.', 'foxi-builder' );
			case 'checkboxes':
				return array_diff( $v, wp_list_pluck( $f['options'], 'value' ) ) ? __( 'Choose from the options given.', 'foxi-builder' ) : '';
		}
		if ( ! empty( $f['maxLength'] ) && mb_strlen( $v ) > $f['maxLength'] ) {
			/* translators: %d: characters */
			return sprintf( __( 'Keep this under %d characters.', 'foxi-builder' ), $f['maxLength'] );
		}
		return '';
	}

	/** Human-readable value for emails and entries. */
	private static function display( array $f, $v ) {
		if ( 'consent' === $f['type'] ) {
			return $v ? __( 'Yes', 'foxi-builder' ) : __( 'No', 'foxi-builder' );
		}
		$labels = in_array( $f['type'], Store::CHOICES, true ) ? wp_list_pluck( $f['options'], 'label', 'value' ) : array();
		if ( is_array( $v ) ) {
			return implode( ', ', array_map( static function ( $x ) use ( $labels ) { return isset( $labels[ $x ] ) ? $labels[ $x ] : $x; }, $v ) );
		}
		if ( isset( $labels[ $v ] ) ) {
			return $labels[ $v ];
		}
		if ( 'date' === $f['type'] && $v ) {
			return date_i18n( get_option( 'date_format' ), strtotime( $v ) );
		}
		return (string) $v;
	}

	private static function label( array $f ) {
		if ( 'consent' === $f['type'] && $f['text'] ) {
			return $f['label'] ? $f['label'] : wp_strip_all_tags( $f['text'] );
		}
		return $f['label'] ? $f['label'] : $f['key'];
	}

	/* ------------------------------- Uploads ------------------------------- */

	/**
	 * @return array|\WP_Error List of { name, path, url, size }.
	 */
	private static function files( array $f, $file ) {
		$list = array();
		if ( is_array( $file ) && isset( $file['name'] ) ) {
			if ( is_array( $file['name'] ) ) {
				foreach ( array_keys( $file['name'] ) as $i ) {
					if ( UPLOAD_ERR_NO_FILE !== $file['error'][ $i ] ) {
						$list[] = array( 'name' => $file['name'][ $i ], 'tmp_name' => $file['tmp_name'][ $i ], 'size' => $file['size'][ $i ], 'error' => $file['error'][ $i ] );
					}
				}
			} elseif ( UPLOAD_ERR_NO_FILE !== $file['error'] ) {
				$list[] = $file;
			}
		}
		if ( ! $list ) {
			return ! empty( $f['required'] ) ? new \WP_Error( 'foxi_file', $f['requiredMsg'] ? $f['requiredMsg'] : __( 'Please add a file.', 'foxi-builder' ) ) : array();
		}
		if ( count( $list ) > $f['maxFiles'] ) {
			/* translators: %d: number of files */
			return new \WP_Error( 'foxi_file', sprintf( _n( 'You can add %d file.', 'You can add up to %d files.', $f['maxFiles'], 'foxi-builder' ), $f['maxFiles'] ) );
		}
		$allowed = 'images' === $f['accept'] ? self::IMAGES : ( 'documents' === $f['accept'] ? self::DOCS : array_merge( self::IMAGES, self::DOCS ) );
		$dir     = self::upload_dir();
		$out     = array();
		foreach ( $list as $one ) {
			if ( UPLOAD_ERR_OK !== (int) $one['error'] || ! is_uploaded_file( $one['tmp_name'] ) ) {
				self::cleanup( array( $out ) );
				return new \WP_Error( 'foxi_file', __( 'The file didn’t upload. Please try again.', 'foxi-builder' ) );
			}
			if ( $one['size'] > $f['maxSize'] * MB_IN_BYTES ) {
				self::cleanup( array( $out ) );
				/* translators: %d: megabytes */
				return new \WP_Error( 'foxi_file', sprintf( __( 'Files must be smaller than %d MB.', 'foxi-builder' ), $f['maxSize'] ) );
			}
			$check = wp_check_filetype_and_ext( $one['tmp_name'], $one['name'] );
			$ext   = $check['ext'] ? strtolower( $check['ext'] ) : '';
			if ( ! $ext || ! in_array( $ext, $allowed, true ) ) {
				self::cleanup( array( $out ) );
				/* translators: %s: file types */
				return new \WP_Error( 'foxi_file', sprintf( __( 'That type of file isn’t accepted. Use %s.', 'foxi-builder' ), strtoupper( implode( ', ', $allowed ) ) ) );
			}
			$name = sanitize_file_name( pathinfo( $one['name'], PATHINFO_FILENAME ) );
			$file = wp_unique_filename( $dir['path'], strtolower( wp_generate_password( 12, false ) ) . '-' . ( $name ? $name : 'file' ) . '.' . $ext );
			$dest = trailingslashit( $dir['path'] ) . $file;
			if ( ! @move_uploaded_file( $one['tmp_name'], $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				self::cleanup( array( $out ) );
				return new \WP_Error( 'foxi_file', __( 'The file couldn’t be saved on the server.', 'foxi-builder' ) );
			}
			$out[] = array( 'name' => sanitize_file_name( $one['name'] ), 'path' => $dest, 'url' => trailingslashit( $dir['url'] ) . $file, 'size' => (int) $one['size'] );
		}
		return $out;
	}

	/** wp-content/uploads/foxi-forms/YYYY/MM, with directory listing switched off. */
	private static function upload_dir() {
		$up   = wp_upload_dir();
		$sub  = '/foxi-forms/' . gmdate( 'Y/m' );
		$path = $up['basedir'] . $sub;
		if ( ! is_dir( $path ) ) {
			wp_mkdir_p( $path );
		}
		$root = $up['basedir'] . '/foxi-forms';
		if ( ! file_exists( $root . '/index.php' ) ) {
			@file_put_contents( $root . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
			@file_put_contents( $root . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|html?|svg|js)$\">\nRequire all denied\n</FilesMatch>\n" ); // phpcs:ignore
		}
		if ( ! file_exists( $path . '/index.php' ) ) {
			@file_put_contents( $path . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
		}
		return array( 'path' => $path, 'url' => $up['baseurl'] . $sub );
	}

	private static function cleanup( array $groups ) {
		foreach ( $groups as $g ) {
			foreach ( (array) $g as $file ) {
				if ( is_array( $file ) && ! empty( $file['path'] ) && file_exists( $file['path'] ) ) {
					wp_delete_file( $file['path'] );
				}
			}
		}
	}

	/* ------------------------------- Spam --------------------------------- */

	/** @return true|string True when passed, otherwise a message. */
	private static function verify_captcha( array $spam, array $p, $ip ) {
		$token = '';
		foreach ( array( 'g-recaptcha-response', 'h-captcha-response', 'cf-turnstile-response' ) as $k ) {
			if ( ! empty( $p[ $k ] ) ) {
				$token = (string) $p[ $k ];
			}
		}
		$fail = __( 'Please confirm you’re not a robot, then send again.', 'foxi-builder' );
		if ( '' === $token ) {
			return $fail;
		}
		$urls = array(
			'recaptcha2' => 'https://www.google.com/recaptcha/api/siteverify',
			'recaptcha3' => 'https://www.google.com/recaptcha/api/siteverify',
			'hcaptcha'   => 'https://api.hcaptcha.com/siteverify',
			'turnstile'  => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
		);
		$res = wp_remote_post(
			$urls[ $spam['provider'] ],
			array(
				'timeout' => 10,
				'body'    => array( 'secret' => $spam['secret'], 'response' => $token, 'remoteip' => $ip ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return __( 'The spam check couldn’t be reached. Please try again.', 'foxi-builder' );
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['success'] ) ) {
			return $fail;
		}
		if ( 'recaptcha3' === $spam['provider'] && isset( $body['score'] ) && (float) $body['score'] < (float) $spam['threshold'] ) {
			return __( 'Your message looked like spam, so it wasn’t sent. Please try again, or contact us another way.', 'foxi-builder' );
		}
		return true;
	}

	private static function spammy( array $rows, array $spam ) {
		$text  = strtolower( implode( "\n", wp_list_pluck( $rows, 'value' ) ) );
		$links = preg_match_all( '#(https?://|www\.)#i', $text );
		if ( $spam['maxLinks'] && $links > $spam['maxLinks'] ) {
			return __( 'Your message has too many links. Please remove some and send again.', 'foxi-builder' );
		}
		foreach ( array_filter( array_map( 'trim', preg_split( '/[\n,]+/', strtolower( $spam['words'] ) ) ) ) as $w ) {
			if ( false !== strpos( $text, $w ) ) {
				return __( 'Your message couldn’t be sent. Please contact us another way.', 'foxi-builder' );
			}
		}
		return '';
	}

	/* --------------------------- Emails & merge tags --------------------------- */

	private static function notify( array $ctx ) {
		$log = array();
		foreach ( $ctx['form']['notifications'] as $n ) {
			if ( empty( $n['enabled'] ) || ! self::rules_pass( $n['cond'], $ctx['values'] ) ) {
				continue;
			}
			$to = self::emails( self::merge( $n['to'], $ctx, 'text' ) );
			if ( ! $to ) {
				$log[] = array( 'name' => $n['name'], 'to' => '', 'ok' => false, 'error' => __( 'No valid “Send to” address.', 'foxi-builder' ), 'time' => time() );
				continue;
			}
			$headers = array();
			foreach ( array( 'cc' => 'Cc', 'bcc' => 'Bcc' ) as $k => $h ) {
				foreach ( self::emails( self::merge( $n[ $k ], $ctx, 'text' ) ) as $e ) {
					$headers[] = $h . ': ' . $e;
				}
			}
			$reply = self::emails( self::merge( $n['replyTo'], $ctx, 'text' ) );
			if ( $reply ) {
				$headers[] = 'Reply-To: ' . $reply[0];
			}
			if ( $n['fromName'] ) {
				$from_name = str_replace( array( "\r", "\n", '"' ), '', self::merge( $n['fromName'], $ctx, 'text' ) );
				$headers[] = 'From: "' . $from_name . '" <' . apply_filters( 'wp_mail_from', 'wordpress@' . wp_parse_url( home_url(), PHP_URL_HOST ) ) . '>';
			}
			$subject = trim( preg_replace( '/\s+/', ' ', wp_specialchars_decode( self::merge( $n['subject'] ? $n['subject'] : '{form_name}', $ctx, 'text' ), ENT_QUOTES ) ) );
			$body    = wpautop( self::merge( $n['message'], $ctx, 'html' ) );
			$attach  = array();
			if ( $n['attach'] ) {
				foreach ( $ctx['files'] as $group ) {
					foreach ( $group as $file ) {
						if ( $file['size'] < 10 * MB_IN_BYTES ) {
							$attach[] = $file['path'];
						}
					}
				}
			}
			$res   = Mail::send( $to, $subject, Mail::layout( $subject, $body ), $headers, $attach );
			$log[] = array( 'name' => $n['name'], 'to' => implode( ', ', $to ), 'ok' => $res['ok'], 'error' => $res['error'], 'time' => time() );
		}
		return $log;
	}

	private static function emails( $list ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/[,;]+/', (string) $list ) ), 'is_email' ) );
	}

	/**
	 * Replace {field:key}, {all_fields} and friends. $mode: text, html or url.
	 */
	public static function merge( $text, array $ctx, $mode = 'text' ) {
		$text  = (string) $text;
		$esc   = static function ( $v ) use ( $mode ) {
			return 'html' === $mode ? nl2br( esc_html( $v ) ) : ( 'url' === $mode ? rawurlencode( $v ) : $v );
		};
		$rows  = array();
		foreach ( $ctx['rows'] as $r ) {
			$rows[ $r['key'] ] = $r;
		}
		$page  = $ctx['page'] ? get_post( $ctx['page'] ) : null;
		$tags  = array(
			'form_name'   => $ctx['form']['title'],
			'site_name'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'    => home_url( '/' ),
			'admin_email' => get_option( 'admin_email' ),
			'page_title'  => $page ? get_the_title( $page ) : '',
			'page_url'    => $page ? get_permalink( $page ) : home_url( '/' ),
			'date'        => date_i18n( get_option( 'date_format' ) ),
			'time'        => date_i18n( get_option( 'time_format' ) ),
			'ip'          => $ctx['ip'],
			'entry_id'    => $ctx['entry'] ? (string) $ctx['entry'] : '',
			'entry_url'   => $ctx['entry'] ? admin_url( 'admin.php?page=foxi-forms&tab=entries&entry=' . $ctx['entry'] ) : '',
		);
		$text = preg_replace_callback(
			'/\{(field:([a-z0-9_]+)|all_fields|[a-z_]+)\}/',
			static function ( $m ) use ( $rows, $tags, $ctx, $esc, $mode ) {
				if ( ! empty( $m[2] ) ) {
					if ( isset( $rows[ $m[2] ] ) ) {
						$r = $rows[ $m[2] ];
						if ( 'html' === $mode && 'file' === $r['type'] && ! empty( $ctx['files'][ $m[2] ] ) ) {
							return self::file_links( $ctx['files'][ $m[2] ] );
						}
						return $esc( $r['value'] );
					}
					return '';
				}
				if ( 'all_fields' === $m[1] ) {
					if ( 'html' !== $mode ) {
						return implode( "\n", array_map( static function ( $r ) { return $r['label'] . ': ' . $r['value']; }, $ctx['rows'] ) );
					}
					$rows_html = array();
					foreach ( $ctx['rows'] as $r ) {
						if ( 'file' === $r['type'] && ! empty( $ctx['files'][ $r['key'] ] ) ) {
							$r['html'] = self::file_links( $ctx['files'][ $r['key'] ] );
						}
						$rows_html[] = $r;
					}
					return Mail::table( $rows_html );
				}
				return isset( $tags[ $m[1] ] ) ? $esc( $tags[ $m[1] ] ) : $m[0];
			},
			$text
		);
		// Brand Kit tokens like {{name}} and {{phone}} work too.
		return class_exists( '\Foxi\Tokens' ) ? Tokens::replace( $text ) : $text;
	}

	private static function file_links( array $files ) {
		return implode( '<br>', array_map( static function ( $f ) { return '<a href="' . esc_url( $f['url'] ) . '">' . esc_html( $f['name'] ) . '</a>'; }, $files ) );
	}
}
