<?php
/**
 * Saved forms (Foxi › Forms): field lists, notifications, confirmation and
 * per-form settings, stored as a private post type. Everything that comes in
 * is cleaned here, so the renderer and the submission handler can trust it.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Forms;

use Foxi\Brand;

defined( 'ABSPATH' ) || exit;

class Store {

	const POST_TYPE = 'foxi_form';
	const META      = '_foxi_form';

	/** Field types and what they hold. */
	const INPUTS  = array( 'text', 'name', 'email', 'tel', 'number', 'url', 'textarea', 'select', 'radio', 'checkboxes', 'consent', 'date', 'time', 'file', 'hidden' );
	const LAYOUT  = array( 'heading', 'html', 'step' );
	const CHOICES = array( 'select', 'radio', 'checkboxes' );
	const WIDTHS  = array( 'full', 'half', 'third', 'two-thirds' );
	const OPS     = array( 'is', 'is_not', 'contains', 'not_contains', 'empty', 'not_empty', 'gt', 'lt' );

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array( 'name' => __( 'Forms', 'foxi-builder' ) ),
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'supports'     => array( 'title' ),
				'rewrite'      => false,
			)
		);
	}

	/**
	 * How many forms, fields and notifications a site may have. 0 = unlimited.
	 * Plans can narrow these later with the `foxi_forms_limits` filter.
	 */
	public static function limits() {
		return wp_parse_args(
			(array) apply_filters( 'foxi_forms_limits', array() ),
			array(
				'forms'         => 0,
				'fields'        => 0,
				'notifications' => 0,
				'uploads'       => true,
				'steps'         => true,
				'conditions'    => true,
			)
		);
	}

	/* ------------------------------- Reading ------------------------------- */

	public static function get( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}
		$raw  = get_post_meta( $post->ID, self::META, true );
		$form = self::clean( is_array( $raw ) ? $raw : array() );
		$form['id']    = $post->ID;
		$form['title'] = $post->post_title;
		return $form;
	}

	public static function all() {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
	}

	/* ------------------------------- Writing ------------------------------- */

	/**
	 * @return int|\WP_Error New form id.
	 */
	public static function create( $title, array $form = array() ) {
		$limits = self::limits();
		if ( $limits['forms'] && count( self::all() ) >= $limits['forms'] ) {
			/* translators: %d: number of forms */
			return new \WP_Error( 'foxi_limit', sprintf( __( 'Your plan includes %d forms. Delete one to add another.', 'foxi-builder' ), $limits['forms'] ), array( 'status' => 403 ) );
		}
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => wp_strip_all_tags( $title ? $title : __( 'Untitled form', 'foxi-builder' ) ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, self::META, wp_slash( self::clean( $form ) ) );
		return $id;
	}

	/**
	 * @return array|\WP_Error The saved form.
	 */
	public static function save( $id, array $data ) {
		$form = self::get( $id );
		if ( ! $form ) {
			return new \WP_Error( 'foxi_form', __( 'Form not found.', 'foxi-builder' ), array( 'status' => 404 ) );
		}
		$clean  = self::clean( $data );
		$limits = self::limits();
		$inputs = array_filter( $clean['fields'], static function ( $f ) { return in_array( $f['type'], self::INPUTS, true ); } );
		if ( $limits['fields'] && count( $inputs ) > $limits['fields'] ) {
			/* translators: %d: number of fields */
			return new \WP_Error( 'foxi_limit', sprintf( __( 'Your plan allows %d fields per form.', 'foxi-builder' ), $limits['fields'] ), array( 'status' => 403 ) );
		}
		if ( $limits['notifications'] && count( $clean['notifications'] ) > $limits['notifications'] ) {
			/* translators: %d: number of emails */
			return new \WP_Error( 'foxi_limit', sprintf( __( 'Your plan allows %d emails per form.', 'foxi-builder' ), $limits['notifications'] ), array( 'status' => 403 ) );
		}
		if ( isset( $data['title'] ) ) {
			wp_update_post( array( 'ID' => (int) $id, 'post_title' => wp_strip_all_tags( (string) $data['title'] ) ) );
		}
		update_post_meta( (int) $id, self::META, wp_slash( $clean ) );
		return self::get( $id );
	}

	/* ------------------------------- Cleaning ------------------------------ */

	public static function defaults() {
		return array(
			'fields'        => array(),
			'notifications' => array(),
			'settings'      => array(
				'submitText'   => __( 'Send', 'foxi-builder' ),
				'confirmation' => array(
					'type'     => 'message',
					'message'  => __( 'Thanks! We’ve received your message and will be in touch shortly.', 'foxi-builder' ),
					'pageId'   => 0,
					'url'      => '',
					'hideForm' => true,
				),
				'store'        => true,
				'captcha'      => true,
				'requiredMark' => true,
				'progress'     => 'bar',
				'limit'        => array( 'enabled' => false, 'max' => 100, 'message' => __( 'Sorry, this form is no longer taking submissions.', 'foxi-builder' ) ),
				'schedule'     => array( 'enabled' => false, 'start' => '', 'end' => '', 'message' => __( 'This form isn’t open right now.', 'foxi-builder' ) ),
				'loginOnly'    => false,
			),
		);
	}

	public static function clean( array $in ) {
		$def      = self::defaults();
		$fields   = array();
		$keys     = array();
		foreach ( isset( $in['fields'] ) && is_array( $in['fields'] ) ? $in['fields'] : array() as $f ) {
			$f = self::clean_field( is_array( $f ) ? $f : array(), $keys );
			if ( $f ) {
				$fields[] = $f;
				$keys[]   = $f['key'];
			}
		}
		$notes = array();
		foreach ( isset( $in['notifications'] ) && is_array( $in['notifications'] ) ? $in['notifications'] : array() as $n ) {
			$notes[] = self::clean_notification( is_array( $n ) ? $n : array() );
		}
		$s  = isset( $in['settings'] ) && is_array( $in['settings'] ) ? $in['settings'] : array();
		$ds = $def['settings'];
		$c  = isset( $s['confirmation'] ) && is_array( $s['confirmation'] ) ? $s['confirmation'] : array();
		$l  = isset( $s['limit'] ) && is_array( $s['limit'] ) ? $s['limit'] : array();
		$sc = isset( $s['schedule'] ) && is_array( $s['schedule'] ) ? $s['schedule'] : array();

		return array(
			'fields'        => $fields,
			'notifications' => $notes,
			'settings'      => array(
				'submitText'   => self::text( $s, 'submitText', $ds['submitText'] ),
				'confirmation' => array(
					'type'     => in_array( isset( $c['type'] ) ? $c['type'] : '', array( 'message', 'page', 'url' ), true ) ? $c['type'] : 'message',
					'message'  => isset( $c['message'] ) ? wp_kses_post( (string) $c['message'] ) : $ds['confirmation']['message'],
					'pageId'   => isset( $c['pageId'] ) ? absint( $c['pageId'] ) : 0,
					'url'      => isset( $c['url'] ) ? esc_url_raw( (string) $c['url'] ) : '',
					'hideForm' => isset( $c['hideForm'] ) ? (bool) $c['hideForm'] : true,
				),
				'store'        => isset( $s['store'] ) ? (bool) $s['store'] : true,
				'captcha'      => isset( $s['captcha'] ) ? (bool) $s['captcha'] : true,
				'requiredMark' => isset( $s['requiredMark'] ) ? (bool) $s['requiredMark'] : true,
				'progress'     => in_array( isset( $s['progress'] ) ? $s['progress'] : '', array( 'bar', 'steps', 'none' ), true ) ? $s['progress'] : 'bar',
				'limit'        => array(
					'enabled' => ! empty( $l['enabled'] ),
					'max'     => max( 1, isset( $l['max'] ) ? absint( $l['max'] ) : 100 ),
					'message' => self::text( $l, 'message', $ds['limit']['message'] ),
				),
				'schedule'     => array(
					'enabled' => ! empty( $sc['enabled'] ),
					'start'   => self::datetime( isset( $sc['start'] ) ? $sc['start'] : '' ),
					'end'     => self::datetime( isset( $sc['end'] ) ? $sc['end'] : '' ),
					'message' => self::text( $sc, 'message', $ds['schedule']['message'] ),
				),
				'loginOnly'    => ! empty( $s['loginOnly'] ),
			),
		);
	}

	private static function clean_field( array $f, array $taken ) {
		$type = isset( $f['type'] ) ? sanitize_key( $f['type'] ) : '';
		if ( ! in_array( $type, array_merge( self::INPUTS, self::LAYOUT ), true ) ) {
			return null;
		}
		$label = isset( $f['label'] ) ? wp_strip_all_tags( (string) $f['label'] ) : '';
		$key   = sanitize_key( isset( $f['key'] ) && '' !== $f['key'] ? $f['key'] : ( $label ? str_replace( '-', '_', sanitize_title( $label ) ) : $type ) );
		$key   = $key ? substr( $key, 0, 40 ) : 'field';
		$base  = $key;
		for ( $i = 2; in_array( $key, $taken, true ) || in_array( $key, array( 'form', 'page', 'site', 'all_fields' ), true ); $i++ ) {
			$key = $base . '_' . $i;
		}
		$out = array(
			'id'    => isset( $f['id'] ) && preg_match( '/^[a-z0-9_]{2,24}$/', (string) $f['id'] ) ? $f['id'] : 'f' . strtolower( wp_generate_password( 6, false ) ),
			'type'  => $type,
			'key'   => $key,
			'label' => $label,
			'width' => in_array( isset( $f['width'] ) ? $f['width'] : '', self::WIDTHS, true ) ? $f['width'] : 'full',
			'cond'  => self::clean_cond( isset( $f['cond'] ) ? $f['cond'] : array() ),
		);
		if ( 'step' === $type ) {
			$out['width'] = 'full';
			return $out;
		}
		if ( 'heading' === $type || 'html' === $type ) {
			$out['content'] = isset( $f['content'] ) ? wp_kses_post( (string) $f['content'] ) : '';
			if ( 'heading' === $type ) {
				$out['level'] = in_array( (int) ( isset( $f['level'] ) ? $f['level'] : 3 ), array( 2, 3, 4 ), true ) ? (int) $f['level'] : 3;
			}
			return $out;
		}
		$out += array(
			'placeholder' => self::text( $f, 'placeholder', '' ),
			'help'        => self::text( $f, 'help', '' ),
			'required'    => ! empty( $f['required'] ),
			'requiredMsg' => self::text( $f, 'requiredMsg', '' ),
			'default'     => self::text( $f, 'default', '' ),
			'hideLabel'   => ! empty( $f['hideLabel'] ),
			'className'   => isset( $f['className'] ) ? implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $f['className'] ) ) ) : '',
		);
		if ( in_array( $type, self::CHOICES, true ) ) {
			$opts = array();
			foreach ( isset( $f['options'] ) && is_array( $f['options'] ) ? $f['options'] : array() as $o ) {
				$l = is_array( $o ) ? self::text( $o, 'label', '' ) : wp_strip_all_tags( (string) $o );
				$v = is_array( $o ) && isset( $o['value'] ) && '' !== $o['value'] ? wp_strip_all_tags( (string) $o['value'] ) : $l;
				if ( '' !== $l ) {
					$opts[] = array( 'label' => $l, 'value' => $v );
				}
			}
			$out['options'] = $opts;
			$out['inline']  = ! empty( $f['inline'] );
		}
		if ( 'consent' === $type ) {
			$out['text'] = isset( $f['text'] ) ? wp_kses( (string) $f['text'], array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'strong' => array(), 'em' => array() ) ) : '';
		}
		if ( 'number' === $type ) {
			foreach ( array( 'min', 'max', 'step' ) as $k ) {
				$out[ $k ] = isset( $f[ $k ] ) && is_numeric( $f[ $k ] ) ? 0 + $f[ $k ] : '';
			}
		}
		if ( in_array( $type, array( 'text', 'name', 'textarea' ), true ) ) {
			$out['maxLength'] = isset( $f['maxLength'] ) ? absint( $f['maxLength'] ) : 0;
		}
		if ( 'textarea' === $type ) {
			$out['rows'] = max( 2, min( 20, isset( $f['rows'] ) ? absint( $f['rows'] ) : 5 ) );
		}
		if ( 'date' === $type ) {
			$out['future'] = ! empty( $f['future'] );
		}
		if ( 'file' === $type ) {
			$out['accept']   = in_array( isset( $f['accept'] ) ? $f['accept'] : '', array( 'images', 'documents', 'both' ), true ) ? $f['accept'] : 'both';
			$out['maxSize']  = max( 1, min( 50, isset( $f['maxSize'] ) ? absint( $f['maxSize'] ) : 5 ) );
			$out['maxFiles'] = max( 1, min( 10, isset( $f['maxFiles'] ) ? absint( $f['maxFiles'] ) : 1 ) );
		}
		return $out;
	}

	public static function clean_cond( $c ) {
		$c     = is_array( $c ) ? $c : array();
		$rules = array();
		foreach ( isset( $c['rules'] ) && is_array( $c['rules'] ) ? $c['rules'] : array() as $r ) {
			if ( ! is_array( $r ) || empty( $r['field'] ) ) {
				continue;
			}
			$rules[] = array(
				'field' => sanitize_key( $r['field'] ),
				'op'    => in_array( isset( $r['op'] ) ? $r['op'] : '', self::OPS, true ) ? $r['op'] : 'is',
				'value' => isset( $r['value'] ) ? wp_strip_all_tags( (string) $r['value'] ) : '',
			);
		}
		return array(
			'enabled' => ! empty( $c['enabled'] ) && $rules,
			'action'  => isset( $c['action'] ) && 'hide' === $c['action'] ? 'hide' : 'show',
			'match'   => isset( $c['match'] ) && 'any' === $c['match'] ? 'any' : 'all',
			'rules'   => $rules,
		);
	}

	private static function clean_notification( array $n ) {
		$lines = static function ( $v ) {
			return implode( ', ', array_filter( array_map( 'trim', preg_split( '/[,;\n]+/', wp_strip_all_tags( (string) $v ) ) ) ) );
		};
		return array(
			'id'       => isset( $n['id'] ) && preg_match( '/^[a-z0-9_]{2,24}$/', (string) $n['id'] ) ? $n['id'] : 'n' . strtolower( wp_generate_password( 6, false ) ),
			'name'     => self::text( $n, 'name', __( 'Email', 'foxi-builder' ) ),
			'enabled'  => isset( $n['enabled'] ) ? (bool) $n['enabled'] : true,
			'to'       => $lines( isset( $n['to'] ) ? $n['to'] : '' ),
			'cc'       => $lines( isset( $n['cc'] ) ? $n['cc'] : '' ),
			'bcc'      => $lines( isset( $n['bcc'] ) ? $n['bcc'] : '' ),
			'replyTo'  => self::text( $n, 'replyTo', '' ),
			'fromName' => self::text( $n, 'fromName', '' ),
			'subject'  => self::text( $n, 'subject', '' ),
			'message'  => isset( $n['message'] ) ? wp_kses_post( (string) $n['message'] ) : '{all_fields}',
			'attach'   => ! empty( $n['attach'] ),
			'cond'     => self::clean_cond( isset( $n['cond'] ) ? $n['cond'] : array() ),
		);
	}

	private static function text( $a, $k, $default ) {
		return isset( $a[ $k ] ) && is_scalar( $a[ $k ] ) ? sanitize_text_field( (string) $a[ $k ] ) : $default;
	}

	private static function datetime( $v ) {
		$v = trim( (string) $v );
		return preg_match( '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $v ) ? $v : '';
	}

	/* ------------------------------ Templates ------------------------------ */

	public static function templates() {
		return array(
			'contact' => array( 'title' => __( 'Contact form', 'foxi-builder' ), 'description' => __( 'Name, email, phone and a message.', 'foxi-builder' ) ),
			'stay'    => array( 'title' => __( 'Accommodation enquiry', 'foxi-builder' ), 'description' => __( 'Dates, guests and room preferences.', 'foxi-builder' ) ),
			'event'   => array( 'title' => __( 'Event & wedding enquiry', 'foxi-builder' ), 'description' => __( 'Event type, date, guest numbers and budget.', 'foxi-builder' ) ),
			'blank'   => array( 'title' => __( 'Blank form', 'foxi-builder' ), 'description' => __( 'Start from nothing.', 'foxi-builder' ) ),
		);
	}

	public static function from_template( $slug ) {
		$f = static function ( $type, $label, $extra = array() ) {
			return array_merge( array( 'type' => $type, 'label' => $label ), $extra );
		};
		$o = static function ( array $labels ) {
			return array_map( static function ( $l ) { return array( 'label' => $l, 'value' => $l ); }, $labels );
		};
		$people = array(
			$f( 'name', __( 'First name', 'foxi-builder' ), array( 'key' => 'first_name', 'required' => true, 'width' => 'half' ) ),
			$f( 'name', __( 'Last name', 'foxi-builder' ), array( 'key' => 'last_name', 'width' => 'half' ) ),
			$f( 'email', __( 'Email', 'foxi-builder' ), array( 'key' => 'email', 'required' => true, 'width' => 'half' ) ),
			$f( 'tel', __( 'Phone', 'foxi-builder' ), array( 'key' => 'phone', 'width' => 'half' ) ),
		);
		switch ( $slug ) {
			case 'contact':
				$fields  = array_merge( $people, array( $f( 'textarea', __( 'Message', 'foxi-builder' ), array( 'key' => 'message', 'required' => true ) ) ) );
				$subject = __( 'New enquiry from {field:first_name} {field:last_name}', 'foxi-builder' );
				break;
			case 'stay':
				$fields  = array_merge(
					$people,
					array(
						$f( 'date', __( 'Arrival', 'foxi-builder' ), array( 'key' => 'arrival', 'required' => true, 'width' => 'half', 'future' => true ) ),
						$f( 'date', __( 'Departure', 'foxi-builder' ), array( 'key' => 'departure', 'required' => true, 'width' => 'half', 'future' => true ) ),
						$f( 'number', __( 'Adults', 'foxi-builder' ), array( 'key' => 'adults', 'width' => 'half', 'default' => '2', 'min' => 1, 'max' => 20 ) ),
						$f( 'number', __( 'Children', 'foxi-builder' ), array( 'key' => 'children', 'width' => 'half', 'default' => '0', 'min' => 0, 'max' => 20 ) ),
						$f( 'select', __( 'Room preference', 'foxi-builder' ), array( 'key' => 'room', 'options' => $o( array( __( 'No preference', 'foxi-builder' ), __( 'King room', 'foxi-builder' ), __( 'Queen room', 'foxi-builder' ), __( 'Twin room', 'foxi-builder' ) ) ) ) ),
						$f( 'textarea', __( 'Anything else we should know?', 'foxi-builder' ), array( 'key' => 'message', 'rows' => 4 ) ),
					)
				);
				$subject = __( 'Accommodation enquiry: {field:arrival} to {field:departure}', 'foxi-builder' );
				break;
			case 'event':
				$fields  = array_merge(
					$people,
					array(
						$f( 'radio', __( 'What are you planning?', 'foxi-builder' ), array( 'key' => 'event_type', 'required' => true, 'inline' => true, 'options' => $o( array( __( 'Wedding', 'foxi-builder' ), __( 'Conference', 'foxi-builder' ), __( 'Retreat', 'foxi-builder' ), __( 'Celebration', 'foxi-builder' ), __( 'Other', 'foxi-builder' ) ) ) ) ),
						$f( 'text', __( 'Tell us what it is', 'foxi-builder' ), array( 'key' => 'event_other', 'cond' => array( 'enabled' => true, 'action' => 'show', 'match' => 'all', 'rules' => array( array( 'field' => 'event_type', 'op' => 'is', 'value' => __( 'Other', 'foxi-builder' ) ) ) ) ) ),
						$f( 'date', __( 'Preferred date', 'foxi-builder' ), array( 'key' => 'event_date', 'width' => 'half', 'future' => true ) ),
						$f( 'number', __( 'Number of guests', 'foxi-builder' ), array( 'key' => 'guests', 'width' => 'half', 'min' => 1 ) ),
						$f( 'checkboxes', __( 'We’re interested in', 'foxi-builder' ), array( 'key' => 'extras', 'inline' => true, 'options' => $o( array( __( 'Accommodation', 'foxi-builder' ), __( 'Catering', 'foxi-builder' ), __( 'Ceremony space', 'foxi-builder' ), __( 'AV equipment', 'foxi-builder' ) ) ) ) ),
						$f( 'textarea', __( 'Tell us about your event', 'foxi-builder' ), array( 'key' => 'message', 'rows' => 4 ) ),
					)
				);
				$subject = __( '{field:event_type} enquiry from {field:first_name} {field:last_name}', 'foxi-builder' );
				break;
			default:
				$fields  = array();
				$subject = __( 'New form entry: {form_name}', 'foxi-builder' );
		}
		$fields[] = $f( 'consent', __( 'Privacy', 'foxi-builder' ), array( 'key' => 'consent', 'required' => true, 'text' => __( 'I agree to my details being stored so you can reply to my enquiry.', 'foxi-builder' ) ) );
		if ( 'blank' === $slug ) {
			$fields = array();
		}

		$form                  = self::defaults();
		$form['fields']        = $fields;
		$form['notifications'] = self::default_notifications( $subject, (bool) $fields );
		if ( $fields ) {
			$form['settings']['confirmation']['message'] = __( 'Thanks, {field:first_name}! We’ve received your message and will be in touch shortly.', 'foxi-builder' );
		}
		return $form;
	}

	public static function default_notifications( $subject, $with_guest = true ) {
		$biz   = Brand::business();
		$to    = ! empty( $biz['email'] ) && is_email( $biz['email'] ) ? $biz['email'] : '{admin_email}';
		$notes = array(
			array(
				'name'    => __( 'Email to you', 'foxi-builder' ),
				'to'      => $to,
				'replyTo' => '{field:email}',
				'subject' => $subject,
				'message' => "{all_fields}\n\n" . __( 'Sent from {page_url}', 'foxi-builder' ),
				'attach'  => true,
			),
		);
		if ( $with_guest ) {
			$notes[] = array(
				'name'    => __( 'Thank-you email to the sender', 'foxi-builder' ),
				'enabled' => false,
				'to'      => '{field:email}',
				'subject' => __( 'Thanks for getting in touch with {site_name}', 'foxi-builder' ),
				'message' => __( "Hi {field:first_name},\n\nThanks for your enquiry. We’ve received it and will get back to you shortly.\n\nHere’s a copy of what you sent:\n\n{all_fields}\n\n{site_name}", 'foxi-builder' ),
			);
		}
		return $notes;
	}

	/**
	 * Turn an old Contact Form block's fields into a saved form.
	 *
	 * @return array Form data for create().
	 */
	public static function from_legacy_block( array $attrs ) {
		$map    = array( 'tel' => 'tel', 'email' => 'email', 'textarea' => 'textarea', 'select' => 'select', 'date' => 'date', 'number' => 'number', 'checkbox' => 'consent' );
		$fields = array();
		foreach ( isset( $attrs['fields'] ) && is_array( $attrs['fields'] ) ? $attrs['fields'] : array() as $f ) {
			$type = isset( $f['type'], $map[ $f['type'] ] ) ? $map[ $f['type'] ] : ( isset( $f['id'] ) && 'name' === $f['id'] ? 'name' : 'text' );
			$new  = array(
				'type'        => $type,
				'key'         => isset( $f['id'] ) ? $f['id'] : '',
				'label'       => isset( $f['label'] ) ? $f['label'] : '',
				'required'    => ! empty( $f['required'] ),
				'width'       => isset( $f['width'] ) && 'half' === $f['width'] ? 'half' : 'full',
				'placeholder' => isset( $f['placeholder'] ) ? $f['placeholder'] : '',
			);
			if ( 'select' === $type ) {
				$new['options'] = array_values( array_filter( array_map( 'trim', explode( "\n", isset( $f['options'] ) ? (string) $f['options'] : '' ) ) ) );
			}
			if ( 'consent' === $type ) {
				$new['text'] = $new['label'];
			}
			$fields[] = $new;
		}
		$form           = self::defaults();
		$form['fields'] = $fields;
		$name_key       = in_array( 'name', wp_list_pluck( $fields, 'key' ), true ) ? '{field:name}' : '';
		$to             = isset( $attrs['recipient'] ) && '' !== trim( $attrs['recipient'] ) ? $attrs['recipient'] : '';
		$form['notifications']    = self::default_notifications( isset( $attrs['subject'] ) && '' !== $attrs['subject'] ? $attrs['subject'] : __( 'New enquiry from your website', 'foxi-builder' ) );
		if ( $to ) {
			$form['notifications'][0]['to'] = $to;
		}
		$form['notifications'][1]['message'] = str_replace( '{field:first_name}', $name_key ? $name_key : '', $form['notifications'][1]['message'] );
		$form['settings']['submitText']              = isset( $attrs['submitText'] ) ? $attrs['submitText'] : __( 'Send message', 'foxi-builder' );
		$form['settings']['confirmation']['message'] = isset( $attrs['successMessage'] ) ? $attrs['successMessage'] : __( 'Thanks — we’ll be in touch shortly.', 'foxi-builder' );
		return $form;
	}
}
