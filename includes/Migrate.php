<?php
/**
 * Moves an exported site kit onto this site: copies its images into this
 * Media Library, and points menus and internal links at this site.
 *
 * Used when a kit carries a "source" (exported with Foxi › Settings › Export
 * site kit). The old site must still be online while you import, because
 * images are downloaded from it.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Migrate {

	/** @var string */
	private $home = '';

	/** @var string */
	private $uploads = '';

	/** @var array<string,array{id:int,url:string}> Old URL => new attachment. */
	private $media = array();

	/** @var array<int,int> Old menu id => new menu id. */
	private $menus = array();

	/** @var int */
	public $copied = 0;

	/** @var int */
	public $failed = 0;

	/**
	 * @param array $kit Kit.
	 * @param array $ctx Import context (menus by slug).
	 */
	public function __construct( array $kit, array $ctx ) {
		$this->home    = isset( $kit['source']['home'] ) ? untrailingslashit( (string) $kit['source']['home'] ) : '';
		$this->uploads = isset( $kit['source']['uploads'] ) ? untrailingslashit( (string) $kit['source']['uploads'] ) : '';
		if ( ! empty( $kit['menuIds'] ) && is_array( $kit['menuIds'] ) ) {
			foreach ( $kit['menuIds'] as $old => $slug ) {
				if ( isset( $ctx['menus'][ $slug ] ) ) {
					$this->menus[ (int) $old ] = (int) $ctx['menus'][ $slug ];
				}
			}
		}
	}

	public static function applies( array $kit ) {
		return ! empty( $kit['source']['home'] ) && untrailingslashit( $kit['source']['home'] ) !== untrailingslashit( home_url( '/' ) );
	}

	/**
	 * Block markup with images copied and links repointed.
	 */
	public function content( $content ) {
		$blocks = parse_blocks( (string) $content );
		foreach ( $blocks as &$block ) {
			$this->block( $block );
		}
		unset( $block );
		return serialize_blocks( $blocks );
	}

	/** Any value (array or string), e.g. SEO settings or business details. */
	public function value( $v, $key = '' ) {
		if ( is_array( $v ) ) {
			// An image object: { id, url, ... }.
			if ( isset( $v['url'] ) && is_string( $v['url'] ) && $this->is_media( $v['url'] ) ) {
				$new = $this->copy( $v['url'] );
				if ( $new ) {
					$v['url'] = $new['url'];
					if ( array_key_exists( 'id', $v ) ) {
						$v['id'] = $new['id'];
					}
				}
			}
			foreach ( $v as $k => $item ) {
				$v[ $k ] = $this->value( $item, (string) $k );
			}
			return $v;
		}
		if ( 'menuId' === $key && is_numeric( $v ) && isset( $this->menus[ (int) $v ] ) ) {
			return $this->menus[ (int) $v ];
		}
		if ( is_string( $v ) ) {
			return $this->text( $v );
		}
		return $v;
	}

	private function block( array &$block ) {
		if ( ! empty( $block['attrs'] ) ) {
			$block['attrs'] = $this->value( $block['attrs'] );
		}
		if ( isset( $block['innerHTML'] ) ) {
			$block['innerHTML'] = $this->text( $block['innerHTML'] );
		}
		if ( ! empty( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as $i => $chunk ) {
				if ( is_string( $chunk ) ) {
					$block['innerContent'][ $i ] = $this->text( $chunk );
				}
			}
		}
		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as &$inner ) {
				$this->block( $inner );
			}
			unset( $inner );
		}
	}

	/** Copy every old-site image URL in a string, then repoint old-site links. */
	private function text( $s ) {
		if ( '' === $s ) {
			return $s;
		}
		if ( $this->uploads && false !== strpos( $s, $this->uploads ) ) {
			if ( preg_match_all( '~' . preg_quote( $this->uploads, '~' ) . '/[^\s"\'\)<>\\\\]+~', $s, $m ) ) {
				foreach ( array_unique( $m[0] ) as $url ) {
					$new = $this->copy( $url );
					if ( $new ) {
						$s = str_replace( $url, $new['url'], $s );
					}
				}
			}
		}
		if ( $this->home ) {
			$s = str_replace( $this->home . '/', trailingslashit( home_url( '/' ) ), $s );
		}
		return $s;
	}

	private function is_media( $url ) {
		return $this->uploads && 0 === strpos( $url, $this->uploads . '/' );
	}

	/**
	 * Download one image into this Media Library (once per URL).
	 *
	 * @return array{id:int,url:string}|null
	 */
	private function copy( $url ) {
		if ( array_key_exists( $url, $this->media ) ) {
			return $this->media[ $url ];
		}
		$this->media[ $url ] = null;

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Prefer the full-size original over a resized copy (photo-1024x768.jpg).
		$original = preg_replace( '~-\d+x\d+(\.[a-z0-9]+)$~i', '$1', $url );
		$attempts = array_unique( array( $original, $url ) );

		foreach ( $attempts as $try ) {
			$tmp = download_url( $try, 60 );
			if ( is_wp_error( $tmp ) ) {
				continue;
			}
			$file = array(
				'name'     => wp_basename( wp_parse_url( $try, PHP_URL_PATH ) ),
				'tmp_name' => $tmp,
			);
			$id   = media_handle_sideload( $file, 0 );
			if ( is_wp_error( $id ) ) {
				wp_delete_file( $tmp );
				continue;
			}
			// Keep the same size the page used.
			$new_url = wp_get_attachment_url( $id );
			if ( $try !== $url && preg_match( '~-(\d+)x(\d+)\.[a-z0-9]+$~i', $url, $size ) ) {
				$sized = image_downsize( $id, array( (int) $size[1], (int) $size[2] ) );
				if ( $sized && ! empty( $sized[0] ) ) {
					$new_url = $sized[0];
				}
			}
			$this->media[ $url ]      = array( 'id' => (int) $id, 'url' => $new_url );
			$this->media[ $original ] = array( 'id' => (int) $id, 'url' => wp_get_attachment_url( $id ) );
			++$this->copied;
			return $this->media[ $url ];
		}

		++$this->failed;
		return null;
	}
}
