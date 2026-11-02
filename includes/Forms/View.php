<?php
/**
 * Front-end HTML for a saved form, used by the Contact Form block.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Forms;

defined( 'ABSPATH' ) || exit;

class View {

	/**
	 * @param array  $form    Saved form (Store::get()).
	 * @param array  $opts    labels: above|hidden, uid: unique id for DOM ids.
	 * @param string $wrapper Attributes for the <form> tag (class, id, data-…).
	 */
	public static function render( array $form, array $opts, $wrapper ) {
		$closed = Submit::closed( $form );
		if ( $closed ) {
			return '<div ' . $wrapper . '><p class="fx-form__closed">' . esc_html( $closed ) . '</p></div>';
		}
		$s       = $form['settings'];
		$uid     = $opts['uid'];
		$hidden  = 'hidden' === $opts['labels'];
		$mark    = ! empty( $s['requiredMark'] );
		$steps   = array( array() );
		$step_fx = array( null );
		foreach ( $form['fields'] as $f ) {
			if ( 'step' === $f['type'] ) {
				$steps[]   = array();
				$step_fx[] = $f;
				continue;
			}
			$steps[ count( $steps ) - 1 ][] = $f;
		}
		$steps = array_values( array_filter( $steps, static function ( $list ) { return (bool) $list; } ) ) ? $steps : array( array() );

		$multi = count( $steps ) > 1;
		$html  = '';
		foreach ( $steps as $i => $list ) {
			$fields = '';
			foreach ( $list as $f ) {
				$fields .= self::field( $f, $uid, $hidden, $mark );
			}
			if ( ! $multi ) {
				$html .= '<div class="fx-form__fields">' . $fields . '</div>';
				continue;
			}
			$cond  = $step_fx[ $i ] && ! empty( $step_fx[ $i ]['cond']['enabled'] ) ? ' data-cond="' . esc_attr( wp_json_encode( $step_fx[ $i ]['cond'] ) ) . '" data-key="' . esc_attr( $step_fx[ $i ]['key'] ) . '"' : '';
			$last  = count( $steps ) - 1 === $i;
			$nav   = '<div class="fx-form__nav">'
				. ( $i > 0 ? '<button type="button" class="fx-form__back fx-button fx-button--outline fx-button--m" data-fx-step="back"><span class="fx-button__text">' . esc_html__( 'Back', 'foxi-builder' ) . '</span></button>' : '' )
				. ( $last ? '%SUBMIT%' : '<button type="button" class="fx-form__next fx-button fx-button--primary fx-button--m" data-fx-step="next"><span class="fx-button__text">' . esc_html__( 'Next', 'foxi-builder' ) . '</span></button>' )
				. '</div>';
			$html .= sprintf(
				'<fieldset class="fx-form__step%1$s" data-step="%2$d"%3$s%4$s><legend class="screen-reader-text">%5$s</legend><div class="fx-form__fields">%6$s</div>%7$s</fieldset>',
				0 === $i ? ' is-active' : '',
				$i,
				0 === $i ? '' : ' hidden',
				$cond,
				/* translators: 1: step number, 2: total steps */
				esc_html( sprintf( __( 'Step %1$d of %2$d', 'foxi-builder' ), $i + 1, count( $steps ) ) ),
				$fields,
				$nav
			);
		}

		$progress = '';
		if ( $multi && 'none' !== $s['progress'] ) {
			$progress = 'steps' === $s['progress']
				? '<p class="fx-form__progress fx-form__progress--text" aria-hidden="true"></p>'
				: '<div class="fx-form__progress fx-form__progress--bar" aria-hidden="true"><span></span></div>';
		}

		$captcha = ! empty( $s['captcha'] ) ? Config::captcha() : null;
		$page    = ! empty( $GLOBALS['foxi_rendering_part'] ) ? (int) $GLOBALS['foxi_rendering_part'] : (int) get_the_ID();
		$extra   = $captcha ? ' data-fx-captcha="' . esc_attr( wp_json_encode( $captcha ) ) . '"' : '';
		$extra  .= ! empty( $s['loginOnly'] ) ? ' data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '"' : '';

		$button = '<button type="submit" class="fx-form__submit fx-button fx-button--primary fx-button--m"><span class="fx-button__text">' . esc_html( $s['submitText'] ? $s['submitText'] : __( 'Send', 'foxi-builder' ) ) . '</span></button>';
		$box    = $captcha && 'recaptcha3' !== $captcha['provider'] ? '<div class="fx-form__captcha"></div>' : '';
		if ( $multi ) {
			$html = str_replace( '%SUBMIT%', $button, $html );
		}

		return sprintf(
			'<form %1$s data-form="%2$d" method="post" novalidate enctype="multipart/form-data" aria-label="%3$s"%4$s>%5$s%6$s'
			. '<div class="fx-hp" aria-hidden="true"><label>Website<input type="text" name="fx_website" tabindex="-1" autocomplete="off"></label></div>'
			. '<input type="hidden" name="_fx_t" value="%7$d"><input type="hidden" name="_fx_page" value="%8$d">%9$s'
			. '<div class="fx-form__status" role="status" aria-live="polite"></div></form>',
			$wrapper,
			(int) $form['id'],
			esc_attr( $form['title'] ),
			$extra,
			$progress,
			$html,
			time(),
			$page,
			$multi ? $box : '<div class="fx-form__end">' . $box . $button . '</div>'
		);
	}

	private static function field( array $f, $uid, $hide_labels, $mark ) {
		$type = $f['type'];
		$cls  = array( 'fx-form__field', 'fx-form__field--' . $f['width'], 'fx-form__field--' . $type );
		if ( ! empty( $f['className'] ) ) {
			$cls[] = $f['className'];
		}
		$attrs = ' data-key="' . esc_attr( $f['key'] ) . '"';
		if ( ! empty( $f['cond']['enabled'] ) ) {
			$attrs .= ' data-cond="' . esc_attr( wp_json_encode( $f['cond'] ) ) . '"';
		}

		if ( 'heading' === $type ) {
			return sprintf( '<div class="%1$s"%2$s><h%3$d class="fx-form__heading">%4$s</h%3$d></div>', esc_attr( implode( ' ', $cls ) ), $attrs, (int) $f['level'], wp_kses_post( $f['content'] ) );
		}
		if ( 'html' === $type ) {
			return sprintf( '<div class="%1$s"%2$s><div class="fx-form__text">%3$s</div></div>', esc_attr( implode( ' ', $cls ) ), $attrs, wp_kses_post( wpautop( $f['content'] ) ) );
		}

		$id      = 'fx-' . $uid . '-' . $f['key'];
		$req     = ! empty( $f['required'] );
		$get     = preg_match( '/^\{get:([a-zA-Z0-9_\-]+)\}$/', $f['default'], $m ) ? $m[1] : '';
		$default = $get ? '' : $f['default'];

		if ( 'hidden' === $type ) {
			return sprintf( '<input type="hidden" name="%1$s" value="%2$s"%3$s>', esc_attr( $f['key'] ), esc_attr( $default ), $get ? ' data-fx-get="' . esc_attr( $get ) . '"' : '' );
		}

		$star   = $req && $mark ? ' <span class="fx-form__req" aria-hidden="true">*</span>' : '';
		$lab_cl = 'fx-form__label' . ( $hide_labels || ! empty( $f['hideLabel'] ) ? ' screen-reader-text' : '' );
		$help   = $f['help'] ? '<p class="fx-form__help" id="' . esc_attr( $id ) . '-help">' . esc_html( $f['help'] ) . '</p>' : '';
		$desc   = $help ? ' aria-describedby="' . esc_attr( $id ) . '-help"' : '';
		$ph     = $f['placeholder'] ? $f['placeholder'] : ( $hide_labels && ! in_array( $type, array( 'select', 'date', 'time' ), true ) ? $f['label'] : '' );
		$common = sprintf(
			' id="%1$s" name="%2$s" class="fx-form__input"%3$s%4$s%5$s%6$s%7$s',
			esc_attr( $id ),
			esc_attr( $f['key'] ),
			$req ? ' required' : '',
			$ph && 'select' !== $type ? ' placeholder="' . esc_attr( $ph ) . '"' : '',
			$desc,
			$f['requiredMsg'] ? ' data-msg="' . esc_attr( $f['requiredMsg'] ) . '"' : '',
			$get ? ' data-fx-get="' . esc_attr( $get ) . '"' : ''
		);

		if ( in_array( $type, array( 'radio', 'checkboxes' ), true ) ) {
			$items = '';
			foreach ( $f['options'] as $i => $o ) {
				$items .= sprintf(
					'<label class="fx-form__choice"><input type="%1$s" name="%2$s" value="%3$s"%4$s%5$s><span>%6$s</span></label>',
					'radio' === $type ? 'radio' : 'checkbox',
					esc_attr( $f['key'] . ( 'checkboxes' === $type ? '[]' : '' ) ),
					esc_attr( $o['value'] ),
					( 'radio' === $type && $req ) ? ' required' : '',
					'' !== $default && in_array( $o['value'], array_map( 'trim', explode( ',', $default ) ), true ) ? ' checked' : '',
					esc_html( $o['label'] )
				);
			}
			return sprintf(
				'<fieldset class="%1$s"%2$s%3$s%4$s><legend class="%5$s">%6$s%7$s</legend><div class="fx-form__choices%8$s">%9$s</div>%10$s</fieldset>',
				esc_attr( implode( ' ', $cls ) ),
				$attrs,
				$desc,
				( 'checkboxes' === $type && $req ? ' data-required="1"' : '' ) . ( $f['requiredMsg'] ? ' data-msg="' . esc_attr( $f['requiredMsg'] ) . '"' : '' ),
				esc_attr( $lab_cl ),
				esc_html( $f['label'] ),
				$star,
				! empty( $f['inline'] ) ? ' fx-form__choices--inline' : '',
				$items,
				$help
			);
		}

		if ( 'consent' === $type ) {
			$text = $f['text'] ? $f['text'] : esc_html( $f['label'] );
			return sprintf(
				'<div class="%1$s"%2$s><label class="fx-form__check" for="%3$s"><input type="checkbox" id="%3$s" name="%4$s" value="1"%5$s%6$s%7$s><span>%8$s%9$s</span></label>%10$s</div>',
				esc_attr( implode( ' ', $cls ) ),
				$attrs,
				esc_attr( $id ),
				esc_attr( $f['key'] ),
				$req ? ' required' : '',
				$desc,
				$f['requiredMsg'] ? ' data-msg="' . esc_attr( $f['requiredMsg'] ) . '"' : '',
				wp_kses( $text, array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'strong' => array(), 'em' => array() ) ),
				$star,
				$help
			);
		}

		switch ( $type ) {
			case 'textarea':
				$control = '<textarea' . $common . ' rows="' . (int) $f['rows'] . '"' . ( $f['maxLength'] ? ' maxlength="' . (int) $f['maxLength'] . '"' : '' ) . '>' . esc_textarea( $default ) . '</textarea>';
				break;
			case 'select':
				$control = '<select' . $common . '><option value="">' . esc_html( $ph ? $ph : __( 'Choose…', 'foxi-builder' ) ) . '</option>';
				foreach ( $f['options'] as $o ) {
					$control .= '<option value="' . esc_attr( $o['value'] ) . '"' . selected( $default, $o['value'], false ) . '>' . esc_html( $o['label'] ) . '</option>';
				}
				$control .= '</select>';
				break;
			case 'file':
				$accept = array(
					'images'    => 'image/*,.heic',
					'documents' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.odt',
					'both'      => 'image/*,.heic,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.odt',
				);
				$control = sprintf(
					'<input type="file" id="%1$s" name="%2$s" class="fx-form__input fx-form__file" accept="%3$s"%4$s%5$s%6$s data-max-mb="%7$d" data-max-files="%8$d">',
					esc_attr( $id ),
					esc_attr( $f['key'] . ( $f['maxFiles'] > 1 ? '[]' : '' ) ),
					esc_attr( $accept[ $f['accept'] ] ),
					$f['maxFiles'] > 1 ? ' multiple' : '',
					$req ? ' required' : '',
					$desc,
					(int) $f['maxSize'],
					(int) $f['maxFiles']
				);
				break;
			default:
				$input = array(
					'name'   => 'text',
					'email'  => 'email',
					'tel'    => 'tel',
					'number' => 'number',
					'url'    => 'url',
					'date'   => 'date',
					'time'   => 'time',
				);
				$auto  = array(
					'email' => 'email',
					'tel'   => 'tel',
					'url'   => 'url',
				);
				$ac    = isset( $auto[ $type ] ) ? $auto[ $type ] : '';
				if ( 'name' === $type ) {
					$ac = false !== strpos( $f['key'], 'last' ) || false !== strpos( $f['key'], 'surname' ) ? 'family-name' : ( false !== strpos( $f['key'], 'first' ) ? 'given-name' : 'name' );
				}
				$more = '';
				if ( 'number' === $type ) {
					foreach ( array( 'min', 'max', 'step' ) as $k ) {
						$more .= '' !== $f[ $k ] ? ' ' . $k . '="' . esc_attr( $f[ $k ] ) . '"' : '';
					}
					$more .= ' inputmode="decimal"';
				}
				if ( 'date' === $type && ! empty( $f['future'] ) ) {
					$more .= ' min="' . esc_attr( current_time( 'Y-m-d' ) ) . '" data-future="1"';
				}
				if ( in_array( $type, array( 'text', 'name' ), true ) && $f['maxLength'] ) {
					$more .= ' maxlength="' . (int) $f['maxLength'] . '"';
				}
				$control = sprintf(
					'<input type="%1$s"%2$s value="%3$s"%4$s%5$s>',
					isset( $input[ $type ] ) ? $input[ $type ] : 'text',
					$common,
					esc_attr( $default ),
					$ac ? ' autocomplete="' . esc_attr( $ac ) . '"' : '',
					$more
				);
		}

		return sprintf(
			'<div class="%1$s"%2$s><label class="%3$s" for="%4$s">%5$s%6$s</label>%7$s%8$s</div>',
			esc_attr( implode( ' ', $cls ) ),
			$attrs,
			esc_attr( $lab_cl ),
			esc_attr( $id ),
			esc_html( $f['label'] ),
			$star,
			$control,
			$help
		);
	}
}
