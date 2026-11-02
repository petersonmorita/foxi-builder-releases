<?php
/**
 * Form entries: each submission saved as a private `foxi_message` post (the
 * same type older Contact Form blocks used, so their messages show up too).
 *
 * @package FoxiBuilder
 */

namespace Foxi\Forms;

defined( 'ABSPATH' ) || exit;

class Entries {

	const POST_TYPE = 'foxi_message';

	public static function create( array $form, array $rows, array $files, $page, $ip ) {
		$name  = '';
		$email = '';
		foreach ( $rows as $r ) {
			if ( ! $email && 'email' === $r['type'] ) {
				$email = $r['value'];
			}
			if ( 'name' === $r['type'] || 'name' === $r['key'] ) {
				$name = trim( $name . ' ' . $r['value'] );
			}
		}
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => $name ? $name : ( $email ? $email : $form['title'] ),
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		$clean_files = array();
		foreach ( $files as $k => $group ) {
			$clean_files[ $k ] = array_map( static function ( $f ) { return array( 'name' => $f['name'], 'url' => $f['url'], 'path' => $f['path'], 'size' => $f['size'] ); }, $group );
		}
		update_post_meta( $id, '_foxi_form_id', (int) $form['id'] );
		update_post_meta( $id, '_foxi_form_title', $form['title'] );
		update_post_meta( $id, '_foxi_fields', wp_slash( array_map( static function ( $r ) { return array( 'key' => $r['key'], 'label' => $r['label'], 'value' => $r['value'], 'type' => $r['type'] ); }, $rows ) ) );
		update_post_meta( $id, '_foxi_files', wp_slash( $clean_files ) );
		update_post_meta( $id, '_foxi_source', (int) $page );
		update_post_meta( $id, '_foxi_email', $email );
		if ( $ip ) {
			update_post_meta( $id, '_foxi_ip', $ip );
		}
		return (int) $id;
	}

	public static function count( $form_id, $unread = false ) {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_foxi_form_id', 'value' => (int) $form_id ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		);
		if ( $unread ) {
			$args['meta_query'][] = array( 'key' => '_foxi_read', 'compare' => 'NOT EXISTS' );
		}
		$q = new \WP_Query( $args );
		return (int) $q->found_posts;
	}

	public static function query( array $a ) {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'private',
			'posts_per_page' => max( 1, min( 100, isset( $a['per_page'] ) ? (int) $a['per_page'] : 25 ) ),
			'paged'          => max( 1, isset( $a['page'] ) ? (int) $a['page'] : 1 ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(), // phpcs:ignore WordPress.DB.SlowDBQuery
		);
		if ( isset( $a['form'] ) && '' !== (string) $a['form'] ) {
			$args['meta_query'][] = 0 === (int) $a['form']
				? array( 'key' => '_foxi_form_id', 'compare' => 'NOT EXISTS' )
				: array( 'key' => '_foxi_form_id', 'value' => (int) $a['form'] );
		}
		if ( ! empty( $a['status'] ) && 'unread' === $a['status'] ) {
			$args['meta_query'][] = array( 'key' => '_foxi_read', 'compare' => 'NOT EXISTS' );
		}
		if ( ! empty( $a['status'] ) && 'starred' === $a['status'] ) {
			$args['meta_query'][] = array( 'key' => '_foxi_star', 'value' => '1' );
		}
		if ( ! empty( $a['search'] ) ) {
			$term = '%' . $GLOBALS['wpdb']->esc_like( sanitize_text_field( $a['search'] ) ) . '%';
			$ids  = $GLOBALS['wpdb']->get_col( $GLOBALS['wpdb']->prepare( "SELECT post_id FROM {$GLOBALS['wpdb']->postmeta} WHERE meta_key = '_foxi_fields' AND meta_value LIKE %s", $term ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$args['post__in'] = $ids ? array_map( 'intval', $ids ) : array( 0 );
		}
		$q = new \WP_Query( $args );
		return array(
			'items' => array_map( array( __CLASS__, 'summary' ), $q->posts ),
			'total' => (int) $q->found_posts,
			'pages' => (int) $q->max_num_pages,
		);
	}

	public static function summary( $p ) {
		$fields = get_post_meta( $p->ID, '_foxi_fields', true );
		$fields = is_array( $fields ) ? $fields : array();
		$long   = '';
		foreach ( $fields as $f ) {
			if ( isset( $f['type'] ) && 'textarea' === $f['type'] && '' !== $f['value'] ) {
				$long = $f['value'];
			}
		}
		if ( ! $long && $fields ) {
			$last = end( $fields );
			$long = (string) $last['value'];
		}
		$form_id = (int) get_post_meta( $p->ID, '_foxi_form_id', true );
		$mail    = get_post_meta( $p->ID, '_foxi_mail', true );
		return array(
			'id'       => $p->ID,
			'title'    => $p->post_title,
			'email'    => (string) get_post_meta( $p->ID, '_foxi_email', true ),
			'preview'  => wp_trim_words( $long, 16 ),
			'form'     => $form_id,
			'formName' => $form_id ? (string) get_post_meta( $p->ID, '_foxi_form_title', true ) : __( 'Older contact form', 'foxi-builder' ),
			'date'     => get_post_time( 'c', true, $p ),
			'ago'      => human_time_diff( get_post_time( 'U', true, $p ), time() ),
			'read'     => (bool) get_post_meta( $p->ID, '_foxi_read', true ),
			'star'     => (bool) get_post_meta( $p->ID, '_foxi_star', true ),
			'mailFail' => is_array( $mail ) && in_array( false, wp_list_pluck( $mail, 'ok' ), true ),
		);
	}

	public static function detail( $id ) {
		$p = get_post( (int) $id );
		if ( ! $p || self::POST_TYPE !== $p->post_type ) {
			return null;
		}
		$out           = self::summary( $p );
		$fields        = get_post_meta( $p->ID, '_foxi_fields', true );
		$files         = get_post_meta( $p->ID, '_foxi_files', true );
		$page          = (int) get_post_meta( $p->ID, '_foxi_source', true );
		$mail          = get_post_meta( $p->ID, '_foxi_mail', true );
		$out['fields'] = array_map(
			static function ( $f ) use ( $files ) {
				$row = array( 'label' => isset( $f['label'] ) ? $f['label'] : '', 'value' => isset( $f['value'] ) ? $f['value'] : '' );
				if ( isset( $f['key'] ) && is_array( $files ) && ! empty( $files[ $f['key'] ] ) ) {
					$row['files'] = array_map( static function ( $x ) { return array( 'name' => $x['name'], 'url' => $x['url'] ); }, $files[ $f['key'] ] );
				}
				return $row;
			},
			is_array( $fields ) ? $fields : array()
		);
		$out['page']      = $page ? array( 'title' => get_the_title( $page ), 'url' => get_permalink( $page ) ) : null;
		$out['ip']        = (string) get_post_meta( $p->ID, '_foxi_ip', true );
		$out['mail']      = is_array( $mail ) ? $mail : array();
		$out['dateLabel'] = get_post_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), false, $p, true );
		return $out;
	}

	public static function delete( $id ) {
		$p = get_post( (int) $id );
		if ( ! $p || self::POST_TYPE !== $p->post_type ) {
			return false;
		}
		$files = get_post_meta( $p->ID, '_foxi_files', true );
		foreach ( is_array( $files ) ? $files : array() as $group ) {
			foreach ( (array) $group as $f ) {
				if ( ! empty( $f['path'] ) && file_exists( $f['path'] ) && false !== strpos( $f['path'], '/foxi-forms/' ) ) {
					wp_delete_file( $f['path'] );
				}
			}
		}
		return (bool) wp_delete_post( $p->ID, true );
	}

	/** CSV of a form's entries (or older-form messages when $form_id is 0). */
	public static function csv( $form_id ) {
		$posts  = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'meta_query'     => array( $form_id ? array( 'key' => '_foxi_form_id', 'value' => (int) $form_id ) : array( 'key' => '_foxi_form_id', 'compare' => 'NOT EXISTS' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$cols   = array();
		$rows   = array();
		foreach ( $posts as $p ) {
			$row    = array( 'Date' => get_post_time( 'Y-m-d H:i', false, $p, true ) );
			$fields = get_post_meta( $p->ID, '_foxi_fields', true );
			foreach ( is_array( $fields ) ? $fields : array() as $f ) {
				$label         = isset( $f['label'] ) ? $f['label'] : '';
				$row[ $label ] = isset( $f['value'] ) ? $f['value'] : '';
			}
			$cols   = array_unique( array_merge( $cols, array_keys( $row ) ) );
			$rows[] = $row;
		}
		$h = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $h, $cols ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $cols as $c ) {
				$v      = isset( $row[ $c ] ) ? (string) $row[ $c ] : '';
				$line[] = preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v; // Stop spreadsheet formulas.
			}
			fputcsv( $h, $line ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		rewind( $h );
		$csv = stream_get_contents( $h );
		fclose( $h ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return "\xEF\xBB\xBF" . $csv; // Excel reads UTF-8 with a BOM.
	}

	/** Daily: delete entries older than the site's "keep entries for" setting. */
	public static function prune() {
		$days = (int) Config::get()['entries']['keepDays'];
		if ( ! $days ) {
			return;
		}
		$old = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'date_query'     => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ), 'column' => 'post_date_gmt' ) ),
			)
		);
		foreach ( $old as $id ) {
			self::delete( $id );
		}
	}
}
