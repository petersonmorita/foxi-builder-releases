<?php
/**
 * Contact form submissions: validated against the form's own block settings
 * (never trusting the browser), emailed, and saved under Foxi > Messages.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Forms {

	const POST_TYPE = 'foxi_message';

	public function init() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'meta_box' ) );
	}

	public function register_post_types() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Messages', 'foxi-builder' ),
					'singular_name' => __( 'Message', 'foxi-builder' ),
					'edit_item'     => __( 'Message', 'foxi-builder' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title' ),
				'capabilities' => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap' => true,
			)
		);
	}

	public function columns( $cols ) {
		return array(
			'cb'           => $cols['cb'],
			'title'        => __( 'From', 'foxi-builder' ),
			'foxi_preview' => __( 'Message', 'foxi-builder' ),
			'foxi_page'    => __( 'Page', 'foxi-builder' ),
			'date'         => $cols['date'],
		);
	}

	public function column( $col, $post_id ) {
		$data = get_post_meta( $post_id, '_foxi_fields', true );
		if ( 'foxi_preview' === $col && is_array( $data ) ) {
			$last = end( $data );
			echo esc_html( wp_trim_words( is_array( $last ) ? $last['value'] : '', 18 ) );
		}
		if ( 'foxi_page' === $col ) {
			$page = (int) get_post_meta( $post_id, '_foxi_source', true );
			echo $page ? '<a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html( get_the_title( $page ) ) . '</a>' : '—';
		}
	}

	public function meta_box() {
		add_meta_box(
			'foxi-message',
			__( 'Message details', 'foxi-builder' ),
			static function ( $post ) {
				$data = get_post_meta( $post->ID, '_foxi_fields', true );
				echo '<table class="widefat striped"><tbody>';
				foreach ( (array) $data as $row ) {
					echo '<tr><th style="width:180px">' . esc_html( $row['label'] ) . '</th><td>' . nl2br( esc_html( $row['value'] ) ) . '</td></tr>';
				}
				echo '</tbody></table>';
			},
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Find a block by fx.id in a block tree.
	 */
	public static function find_block( array $blocks, $name, $id ) {
		foreach ( $blocks as $b ) {
			if ( $name === $b['blockName'] && Style::clean_id( foxi_get( $b['attrs'], 'fx.id', '' ) ) === $id ) {
				return $b;
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				$found = self::find_block( $b['innerBlocks'], $name, $id );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/**
	 * Handle a submission (called from the REST endpoint).
	 *
	 * @param array  $params Request params.
	 * @param string $ip     Client IP (for rate limiting).
	 * @return array|\WP_Error
	 */
	public static function submit( array $params, $ip ) {
		// Spam traps: hidden honeypot field and a minimum fill time.
		if ( ! empty( $params['fx_website'] ) ) {
			return array( 'ok' => true ); // Pretend success to bots.
		}
		$started = isset( $params['_fx_t'] ) ? (int) $params['_fx_t'] : 0;
		if ( $started && time() - $started < 3 ) {
			return new \WP_Error( 'foxi_too_fast', __( 'Please take a moment to complete the form.', 'foxi-builder' ), array( 'status' => 400 ) );
		}

		$key   = 'foxi_rl_' . md5( (string) $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 5 ) {
			return new \WP_Error( 'foxi_rate', __( 'Too many messages in a short time. Please try again in a few minutes.', 'foxi-builder' ), array( 'status' => 429 ) );
		}

		$ref = isset( $params['_fx_form'] ) ? (string) $params['_fx_form'] : '';
		if ( ! preg_match( '/^(\d+):([a-z0-9]+)$/', $ref, $m ) ) {
			return new \WP_Error( 'foxi_form', __( 'This form could not be found.', 'foxi-builder' ), array( 'status' => 400 ) );
		}
		$post = get_post( (int) $m[1] );
		if ( ! $post || ( 'publish' !== $post->post_status ) ) {
			return new \WP_Error( 'foxi_form', __( 'This form could not be found.', 'foxi-builder' ), array( 'status' => 400 ) );
		}
		$block = self::find_block( parse_blocks( $post->post_content ), 'foxi/form', $m[2] );
		if ( ! $block ) {
			return new \WP_Error( 'foxi_form', __( 'This form could not be found.', 'foxi-builder' ), array( 'status' => 400 ) );
		}

		$meta   = Blocks::meta( 'foxi/form' );
		$fields = isset( $block['attrs']['fields'] ) ? $block['attrs']['fields'] : $meta['attributes']['fields']['default'];
		$rows   = array();
		$errors = array();
		$reply  = '';
		$name   = '';

		foreach ( (array) $fields as $i => $f ) {
			$fid   = sanitize_key( isset( $f['id'] ) ? $f['id'] : 'field' . $i );
			$type  = isset( $f['type'] ) ? $f['type'] : 'text';
			$label = isset( $f['label'] ) ? $f['label'] : $fid;
			$raw   = isset( $params[ $fid ] ) ? wp_unslash( $params[ $fid ] ) : '';
			$value = 'textarea' === $type ? sanitize_textarea_field( (string) $raw ) : sanitize_text_field( (string) $raw );

			if ( ! empty( $f['required'] ) && '' === trim( $value ) ) {
				$errors[ $fid ] = sprintf( /* translators: %s field label */ __( '%s is required.', 'foxi-builder' ), $label );
				continue;
			}
			if ( 'email' === $type && '' !== $value ) {
				if ( ! is_email( $value ) ) {
					$errors[ $fid ] = __( 'Enter a valid email address.', 'foxi-builder' );
					continue;
				}
				$reply = $value;
			}
			if ( 'name' === $fid ) {
				$name = $value;
			}
			if ( 'checkbox' === $type ) {
				$value = $value ? __( 'Yes', 'foxi-builder' ) : __( 'No', 'foxi-builder' );
			}
			$rows[] = array( 'label' => $label, 'value' => $value );
		}

		if ( $errors ) {
			return new \WP_Error( 'foxi_invalid', __( 'Please check the highlighted fields.', 'foxi-builder' ), array( 'status' => 422, 'fields' => $errors ) );
		}

		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );

		$source = isset( $params['_fx_page'] ) ? absint( $params['_fx_page'] ) : $post->ID;
		$msg_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => $name ? $name : ( $reply ? $reply : __( 'Website enquiry', 'foxi-builder' ) ),
			)
		);
		if ( $msg_id && ! is_wp_error( $msg_id ) ) {
			update_post_meta( $msg_id, '_foxi_fields', $rows );
			update_post_meta( $msg_id, '_foxi_source', $source );
		}

		$to = ! empty( $block['attrs']['recipient'] ) ? Tokens::replace( $block['attrs']['recipient'] ) : '';
		$to = is_email( $to ) ? $to : Brand::business()['email'];
		$to = is_email( $to ) ? $to : get_option( 'admin_email' );

		$subject = ! empty( $block['attrs']['subject'] ) ? wp_strip_all_tags( Tokens::replace( $block['attrs']['subject'] ) ) : __( 'New enquiry from your website', 'foxi-builder' );
		$body    = '';
		foreach ( $rows as $row ) {
			$body .= $row['label'] . ":\n" . $row['value'] . "\n\n";
		}
		$body   .= '— ' . get_permalink( $source );
		$headers = $reply ? array( 'Reply-To: ' . ( $name ? $name . ' ' : '' ) . '<' . $reply . '>' ) : array();
		wp_mail( $to, $subject, $body, $headers );

		return array(
			'ok'      => true,
			'message' => ! empty( $block['attrs']['successMessage'] ) ? $block['attrs']['successMessage'] : __( 'Thanks — your message has been sent.', 'foxi-builder' ),
		);
	}
}
