<?php
/**
 * Starter sites, the section/template library and site kit import/export.
 *
 * A "kit" is one JSON format used for everything: bundled starter sites,
 * exported sites, and (later) shared kits. Pages and parts are block trees:
 *
 *   { "name": "foxi/heading", "attrs": {…}, "inner": [ …children ] }
 *
 * Placeholders resolved at import:
 *   { "$img": "landscape" }  -> { id, url, alt } (placeholder copied into the Media Library)
 *   { "$menu": "main" }      -> menu id
 *   "{{page:rooms}}"         -> permalink of the imported "rooms" page
 * Business tokens such as {{phone}} are kept and resolved when the page renders.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Starters {

	public function init() {}

	/* ------------------------------------------------------------------
	 * Catalogue
	 * ---------------------------------------------------------------- */

	public static function dirs() {
		$dirs = glob( FOXI_DIR . 'starters/*', GLOB_ONLYDIR );
		return apply_filters( 'foxi_starter_dirs', $dirs ? $dirs : array() );
	}

	public static function load( $slug ) {
		foreach ( self::dirs() as $dir ) {
			if ( basename( $dir ) === $slug && is_readable( $dir . '/site.json' ) ) {
				$kit = json_decode( (string) file_get_contents( $dir . '/site.json' ), true ); // phpcs:ignore
				if ( is_array( $kit ) ) {
					$kit['_dir'] = $dir;
					return $kit;
				}
			}
		}
		return null;
	}

	public static function catalog() {
		$out = array();
		foreach ( self::dirs() as $dir ) {
			$kit = self::load( basename( $dir ) );
			if ( ! $kit ) {
				continue;
			}
			$out[] = array(
				'slug'        => basename( $dir ),
				'name'        => isset( $kit['name'] ) ? $kit['name'] : basename( $dir ),
				'description' => isset( $kit['description'] ) ? $kit['description'] : '',
				'industry'    => isset( $kit['industry'] ) ? $kit['industry'] : '',
				'brand'       => isset( $kit['brand'] ) ? $kit['brand'] : array(),
				'pages'       => array_map(
					static function ( $p ) {
						return array( 'key' => $p['key'], 'title' => $p['title'] );
					},
					isset( $kit['pages'] ) ? $kit['pages'] : array()
				),
				'preview'     => is_readable( $dir . '/preview.jpg' ) ? self::dir_url( $dir ) . '/preview.jpg' : '',
			);
		}
		return $out;
	}

	private static function dir_url( $dir ) {
		return FOXI_URL . ltrim( str_replace( wp_normalize_path( FOXI_DIR ), '', wp_normalize_path( $dir ) ), '/' );
	}

	/**
	 * Sections and page templates for the editor library. Images point at the
	 * bundled placeholder files so nothing is copied until you use it.
	 */
	public static function library() {
		$sections  = array();
		$templates = array();
		foreach ( self::dirs() as $dir ) {
			$kit = self::load( basename( $dir ) );
			if ( ! $kit ) {
				continue;
			}
			$ctx = array(
				'images' => self::placeholder_urls( $kit ),
				'pages'  => self::existing_pages( $kit ),
				'menus'  => array(),
			);
			foreach ( isset( $kit['sections'] ) ? $kit['sections'] : array() as $s ) {
				$sections[] = array(
					'key'      => basename( $dir ) . '/' . $s['key'],
					'title'    => $s['title'],
					'category' => isset( $s['category'] ) ? $s['category'] : 'General',
					'kit'      => isset( $kit['name'] ) ? $kit['name'] : '',
					'blocks'   => self::resolve( $s['blocks'], $ctx ),
				);
			}
			foreach ( isset( $kit['pages'] ) ? $kit['pages'] : array() as $p ) {
				if ( empty( $p['blocks'] ) ) {
					continue;
				}
				$templates[] = array(
					'key'    => basename( $dir ) . '/' . $p['key'],
					'title'  => $p['title'],
					'kit'    => isset( $kit['name'] ) ? $kit['name'] : '',
					'blocks' => self::resolve( $p['blocks'], $ctx ),
				);
			}
		}
		return array( 'sections' => $sections, 'templates' => $templates );
	}

	private static function placeholder_urls( $kit ) {
		$out = array();
		foreach ( isset( $kit['images'] ) ? $kit['images'] : array() as $key => $file ) {
			$out[ $key ] = array(
				'id'  => 0,
				'url' => self::image_source_url( $kit, $file ),
				'alt' => '',
			);
		}
		return $out;
	}

	private static function image_source_path( $kit, $file ) {
		$local = isset( $kit['_dir'] ) ? $kit['_dir'] . '/images/' . $file : '';
		return ( $local && is_readable( $local ) ) ? $local : FOXI_DIR . 'assets/placeholders/' . $file;
	}

	private static function image_source_url( $kit, $file ) {
		$local = isset( $kit['_dir'] ) ? $kit['_dir'] . '/images/' . $file : '';
		return ( $local && is_readable( $local ) ) ? self::dir_url( $kit['_dir'] ) . '/images/' . $file : FOXI_URL . 'assets/placeholders/' . $file;
	}

	private static function existing_pages( $kit ) {
		$out = array();
		foreach ( isset( $kit['pages'] ) ? $kit['pages'] : array() as $p ) {
			$page             = get_page_by_path( isset( $p['slug'] ) ? $p['slug'] : $p['key'] );
			$out[ $p['key'] ] = $page ? get_permalink( $page ) : '#';
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Tree helpers
	 * ---------------------------------------------------------------- */

	public static function new_id() {
		return strtolower( wp_generate_password( 6, false, false ) );
	}

	/**
	 * Resolve placeholders in a value (recursive).
	 */
	private static function resolve_value( $v, $ctx ) {
		if ( is_array( $v ) ) {
			if ( isset( $v['$img'] ) && is_string( $v['$img'] ) ) {
				$img  = isset( $ctx['images'][ $v['$img'] ] ) ? $ctx['images'][ $v['$img'] ] : array();
				$rest = $v;
				unset( $rest['$img'] );
				// Extra keys (focal point, size…) sit alongside the resolved image.
				return $img ? array_merge( $img, self::resolve_value( $rest, $ctx ) ) : $rest;
			}
			if ( isset( $v['$menu'] ) && 1 === count( $v ) ) {
				return isset( $ctx['menus'][ $v['$menu'] ] ) ? (int) $ctx['menus'][ $v['$menu'] ] : 0;
			}
			foreach ( $v as $k => $item ) {
				$v[ $k ] = self::resolve_value( $item, $ctx );
			}
			return $v;
		}
		if ( is_string( $v ) && false !== strpos( $v, '{{page:' ) ) {
			return preg_replace_callback(
				'/\{\{page:([a-z0-9-]+)\}\}/',
				static function ( $m ) use ( $ctx ) {
					return isset( $ctx['pages'][ $m[1] ] ) ? $ctx['pages'][ $m[1] ] : '#';
				},
				$v
			);
		}
		if ( is_string( $v ) && false !== strpos( $v, '{{img:' ) ) {
			return preg_replace_callback(
				'/\{\{img:([a-z0-9-]+)\}\}/',
				static function ( $m ) use ( $ctx ) {
					return isset( $ctx['images'][ $m[1] ]['url'] ) ? $ctx['images'][ $m[1] ]['url'] : '';
				},
				$v
			);
		}
		return $v;
	}

	/**
	 * Resolve placeholders and assign fresh fx ids to a tree.
	 *
	 * @param array $nodes Tree.
	 * @param array $ctx   Context.
	 * @return array
	 */
	public static function resolve( array $nodes, array $ctx ) {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( empty( $node['name'] ) ) {
				continue;
			}
			$attrs = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? self::resolve_value( $node['attrs'], $ctx ) : array();
			if ( 0 === strpos( $node['name'], 'foxi/' ) ) {
				if ( ! isset( $attrs['fx'] ) || ! is_array( $attrs['fx'] ) ) {
					$attrs['fx'] = array();
				}
				$attrs['fx']['id'] = self::new_id();
			}
			$out[] = array(
				'name'  => $node['name'],
				'attrs' => $attrs,
				'inner' => ! empty( $node['inner'] ) ? self::resolve( $node['inner'], $ctx ) : array(),
			);
		}
		return $out;
	}

	/**
	 * Block tree -> serialized block markup.
	 */
	public static function serialize( array $nodes ) {
		$html = '';
		foreach ( $nodes as $node ) {
			$html .= serialize_block( self::to_parsed( $node ) ) . "\n\n";
		}
		return trim( $html );
	}

	private static function to_parsed( array $node ) {
		$inner = array();
		foreach ( isset( $node['inner'] ) ? $node['inner'] : array() as $child ) {
			$inner[] = self::to_parsed( $child );
		}
		$content = array();
		foreach ( $inner as $i => $_ ) {
			$content[] = "\n";
			$content[] = null;
		}
		if ( $inner ) {
			$content[] = "\n";
		}
		return array(
			'blockName'    => $node['name'],
			'attrs'        => isset( $node['attrs'] ) ? $node['attrs'] : array(),
			'innerBlocks'  => $inner,
			'innerHTML'    => $inner ? str_repeat( "\n", count( $inner ) + 1 ) : '',
			'innerContent' => $content,
		);
	}

	/* ------------------------------------------------------------------
	 * Import
	 * ---------------------------------------------------------------- */

	/**
	 * Copy a placeholder image into the Media Library once and reuse it.
	 */
	private static function import_image( $kit, $key, $file ) {
		$tag      = ( isset( $kit['slug'] ) ? $kit['slug'] : 'kit' ) . ':' . $key;
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'meta_key'       => '_foxi_placeholder', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $tag, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'         => 'ids',
			)
		);
		if ( $existing ) {
			$id = (int) $existing[0];
			return array( 'id' => $id, 'url' => wp_get_attachment_image_url( $id, 'full' ), 'alt' => '' );
		}

		// Remote file in an exported kit: keep the URL rather than downloading.
		if ( 0 === strpos( (string) $file, 'http' ) ) {
			return array( 'id' => 0, 'url' => esc_url_raw( $file ), 'alt' => '' );
		}

		$src = self::image_source_path( $kit, $file );
		if ( ! is_readable( $src ) ) {
			return array( 'id' => 0, 'url' => self::image_source_url( $kit, $file ), 'alt' => '' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( basename( $file ) );
		copy( $src, $tmp );
		$id = media_handle_sideload(
			array(
				'name'     => 'foxi-' . basename( $file ),
				'tmp_name' => $tmp,
			),
			0,
			sprintf( /* translators: %s image key */ __( 'Placeholder: %s', 'foxi-builder' ), $key )
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return array( 'id' => 0, 'url' => self::image_source_url( $kit, $file ), 'alt' => '' );
		}
		update_post_meta( $id, '_foxi_placeholder', $tag );
		return array( 'id' => (int) $id, 'url' => wp_get_attachment_image_url( $id, 'full' ), 'alt' => '' );
	}

	/**
	 * Import a kit.
	 *
	 * @param array $kit     Kit.
	 * @param array $options pages, parts, menus, brand, business, front (bools); only (page keys).
	 * @return array Report.
	 */
	public static function import( array $kit, array $options = array() ) {
		$o = wp_parse_args(
			$options,
			array(
				'pages'    => true,
				'parts'    => true,
				'menus'    => true,
				'brand'    => true,
				'business' => true,
				'front'    => true,
				'only'     => array(),
			)
		);
		foreach ( array( 'pages', 'parts', 'menus', 'brand', 'business', 'front' ) as $k ) {
			$o[ $k ] = rest_sanitize_boolean( $o[ $k ] );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}

		$report = array( 'pages' => array(), 'parts' => array(), 'menus' => array(), 'images' => 0 );
		$ctx    = array( 'images' => array(), 'pages' => array(), 'menus' => array() );

		if ( $o['brand'] && ! empty( $kit['brand'] ) ) {
			Brand::update( $kit['brand'] );
		}
		if ( $o['business'] && ! empty( $kit['business'] ) ) {
			// Only fill fields that are still empty: never overwrite your real details.
			$current  = Brand::business();
			$defaults = Brand::business_defaults();
			$merged   = $current;
			foreach ( $kit['business'] as $k => $v ) {
				if ( 'socials' === $k && is_array( $v ) ) {
					foreach ( $v as $net => $url ) {
						if ( empty( $current['socials'][ $net ] ) ) {
							$merged['socials'][ $net ] = $url;
						}
					}
				} elseif ( empty( $current[ $k ] ) || ( isset( $defaults[ $k ] ) && $current[ $k ] === $defaults[ $k ] ) ) {
					// Empty, or still WordPress's default (site title, admin email): use the kit's.
					$merged[ $k ] = $v;
				}
			}
			Brand::update_business( $merged );
			Tokens::flush();
		}

		// Images.
		foreach ( isset( $kit['images'] ) ? $kit['images'] : array() as $key => $file ) {
			$ctx['images'][ $key ] = self::import_image( $kit, $key, $file );
			++$report['images'];
		}

		$pages = isset( $kit['pages'] ) ? $kit['pages'] : array();
		if ( ! empty( $o['only'] ) ) {
			$only  = array_map( 'sanitize_key', (array) $o['only'] );
			$pages = array_values(
				array_filter(
					$pages,
					static function ( $p ) use ( $only ) {
						return in_array( $p['key'], $only, true );
					}
				)
			);
		}

		// Pass 1: create pages so links between them can resolve.
		$created = array();
		if ( $o['pages'] ) {
			// Plain links (?page_id=12) can't show nested pages like /rooms/the-cottage/.
			if ( '' === (string) get_option( 'permalink_structure' ) ) {
				global $wp_rewrite;
				$wp_rewrite->set_permalink_structure( '/%postname%/' );
				flush_rewrite_rules( false );
			}
			// WordPress's unpublished sample Privacy Policy would take the "privacy-policy" address.
			$sample_privacy = (int) get_option( 'wp_page_for_privacy_policy' );
			if ( $sample_privacy && 'draft' === get_post_status( $sample_privacy ) && 'privacy-policy' === get_post_field( 'post_name', $sample_privacy ) ) {
				wp_update_post( array( 'ID' => $sample_privacy, 'post_name' => 'privacy-policy-sample' ) );
			}
			foreach ( $pages as $p ) {
				$id = wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => $p['title'],
						'post_name'    => isset( $p['slug'] ) ? $p['slug'] : $p['key'],
						'post_content' => '',
						'menu_order'   => isset( $p['order'] ) ? (int) $p['order'] : 0,
					),
					true
				);
				if ( is_wp_error( $id ) ) {
					continue;
				}
				$created[ $p['key'] ]      = $id;
				$ctx['pages'][ $p['key'] ] = get_permalink( $id );
				if ( 'privacy-policy' === ( isset( $p['slug'] ) ? $p['slug'] : $p['key'] ) ) {
					update_option( 'wp_page_for_privacy_policy', $id );
				}
			}
			// Parent pages (after all exist).
			foreach ( $pages as $p ) {
				if ( ! empty( $p['parent'] ) && isset( $created[ $p['key'] ], $created[ $p['parent'] ] ) ) {
					wp_update_post( array( 'ID' => $created[ $p['key'] ], 'post_parent' => $created[ $p['parent'] ] ) );
					$ctx['pages'][ $p['key'] ] = get_permalink( $created[ $p['key'] ] );
				}
			}
		}
		// Links to pages from the kit that already exist on this site.
		foreach ( isset( $kit['pages'] ) ? $kit['pages'] : array() as $p ) {
			if ( ! isset( $ctx['pages'][ $p['key'] ] ) ) {
				$existing                  = get_page_by_path( isset( $p['slug'] ) ? $p['slug'] : $p['key'] );
				$ctx['pages'][ $p['key'] ] = $existing ? get_permalink( $existing ) : '#';
			}
		}

		// Menus.
		if ( $o['menus'] && ! empty( $kit['menus'] ) ) {
			foreach ( $kit['menus'] as $menu ) {
				$menu_id = self::import_menu( $menu, $ctx );
				if ( $menu_id ) {
					$ctx['menus'][ $menu['slug'] ] = $menu_id;
					$report['menus'][]             = array( 'id' => $menu_id, 'name' => $menu['name'] );
				}
			}
		}

		// Kits exported from another site: copy its images here and repoint links.
		$migrate = Migrate::applies( $kit ) ? new Migrate( $kit, $ctx ) : null;

		// Pass 2: page content.
		foreach ( $pages as $p ) {
			if ( empty( $created[ $p['key'] ] ) ) {
				continue;
			}
			$id      = $created[ $p['key'] ];
			$content = ! empty( $p['blocks'] ) ? self::serialize( self::resolve( $p['blocks'], $ctx ) ) : ( isset( $p['content'] ) ? (string) $p['content'] : '' );
			if ( $migrate && empty( $p['blocks'] ) ) {
				$content = $migrate->content( $content );
			}
			wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( $content ) ) );
			$page_meta = isset( $p['page'] ) && is_array( $p['page'] ) ? $p['page'] : array();
			update_post_meta( $id, Canvas::META, wp_parse_args( $page_meta, array( 'layout' => 'canvas' ) ) );
			if ( ! empty( $p['seo'] ) ) {
				$seo = self::resolve_value( $p['seo'], $ctx );
				update_post_meta( $id, Seo\Seo::META, Seo\Seo::sanitize( $migrate ? $migrate->value( $seo ) : $seo ) );
			}
			$report['pages'][] = array( 'id' => $id, 'title' => $p['title'], 'url' => get_permalink( $id ), 'edit' => admin_url( 'post.php?post=' . $id . '&action=edit' ) );

			if ( $o['front'] && ! empty( $p['front'] ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $id );
			}
			// The page that lists blog posts (uses the Foxi blog layout).
			if ( $o['front'] && ! empty( $p['posts'] ) ) {
				update_option( 'page_for_posts', $id );
			}
		}

		// Header & footer.
		if ( $o['parts'] && ! empty( $kit['parts'] ) ) {
			$settings = array();
			foreach ( $kit['parts'] as $part ) {
				$type    = in_array( $part['type'], Parts::types(), true ) ? $part['type'] : 'header';
				$content = ! empty( $part['blocks'] ) ? self::serialize( self::resolve( $part['blocks'], $ctx ) ) : ( isset( $part['content'] ) ? (string) $part['content'] : '' );
				if ( $migrate && empty( $part['blocks'] ) ) {
					$content = $migrate->content( $content );
				}
				$id      = wp_insert_post(
					array(
						'post_type'    => Parts::POST_TYPE,
						'post_status'  => 'publish',
						'post_title'   => $part['title'],
						'post_content' => wp_slash( $content ),
					)
				);
				if ( $id && ! is_wp_error( $id ) ) {
					update_post_meta( $id, '_foxi_part_type', $type );
					// The first part of each type becomes the site default; "alternative" parts are extra choices.
					$key = Parts::setting_key( $type );
					if ( empty( $part['alternative'] ) && ! isset( $settings[ $key ] ) ) {
						$settings[ $key ] = $id;
					}
					$report['parts'][]       = array( 'id' => $id, 'title' => $part['title'], 'type' => $type, 'edit' => admin_url( 'post.php?post=' . $id . '&action=edit' ) );
				}
			}
			if ( $settings ) {
				Settings::update( $settings );
			}
		}

		if ( $migrate ) {
			// The logo from Business details.
			$biz = Brand::business();
			if ( ! empty( $biz['logoUrl'] ) ) {
				$moved = $migrate->value( array( 'id' => isset( $biz['logoId'] ) ? $biz['logoId'] : 0, 'url' => $biz['logoUrl'] ) );
				if ( $moved['url'] !== $biz['logoUrl'] ) {
					$biz['logoUrl'] = $moved['url'];
					if ( isset( $biz['logoId'] ) ) {
						$biz['logoId'] = $moved['id'];
					}
					Brand::update_business( $biz );
				}
			}
			$report['mediaCopied'] = $migrate->copied;
			$report['mediaFailed'] = $migrate->failed;
		}

		do_action( 'foxi_kit_imported', $kit, $report );
		return $report;
	}

	private static function import_menu( array $menu, array $ctx ) {
		$name = $menu['name'];
		$obj  = wp_get_nav_menu_object( $name );
		if ( $obj ) {
			$name = $name . ' ' . gmdate( 'Y-m-d H:i' );
		}
		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			return 0;
		}
		self::menu_items( $menu_id, isset( $menu['items'] ) ? $menu['items'] : array(), 0, $ctx );

		if ( ! empty( $menu['location'] ) ) {
			$locations                      = get_theme_mod( 'nav_menu_locations', array() );
			$locations[ $menu['location'] ] = $menu_id;
			set_theme_mod( 'nav_menu_locations', $locations );
		}
		return (int) $menu_id;
	}

	private static function menu_items( $menu_id, array $items, $parent, array $ctx ) {
		foreach ( $items as $i => $item ) {
			$args = array(
				'menu-item-title'     => $item['title'],
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $parent,
				'menu-item-position'  => $i + 1,
			);
			$page = ! empty( $item['page'] ) ? get_page_by_path( self::slug_for( $item['page'], $ctx ) ) : null;
			if ( $page ) {
				$args['menu-item-type']      = 'post_type';
				$args['menu-item-object']    = 'page';
				$args['menu-item-object-id'] = $page->ID;
			} else {
				$args['menu-item-type'] = 'custom';
				$args['menu-item-url']  = ! empty( $item['url'] ) ? self::resolve_value( $item['url'], $ctx ) : ( ! empty( $item['page'] ) && isset( $ctx['pages'][ $item['page'] ] ) ? $ctx['pages'][ $item['page'] ] : '#' );
			}
			$id = wp_update_nav_menu_item( $menu_id, 0, $args );
			if ( ! is_wp_error( $id ) && ! empty( $item['children'] ) ) {
				self::menu_items( $menu_id, $item['children'], $id, $ctx );
			}
		}
	}

	private static function slug_for( $key, $ctx ) {
		$url = isset( $ctx['pages'][ $key ] ) ? $ctx['pages'][ $key ] : '';
		if ( $url && '#' !== $url ) {
			$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
			$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
			if ( $home && 0 === strpos( $path, $home ) ) {
				$path = trim( substr( $path, strlen( $home ) ), '/' );
			}
			if ( '' !== $path ) {
				return $path;
			}
			// Front page permalink is the home URL; look the page up by id instead.
			$front = (int) get_option( 'page_on_front' );
			return $front ? get_page_uri( $front ) : $key;
		}
		return $key;
	}

	/**
	 * Create a new header/footer from a starter template ("panorama/header") or blank.
	 */
	public static function create_part( $type, $template = '' ) {
		$blocks = array();
		$title  = 'header' === $type ? __( 'New header', 'foxi-builder' ) : __( 'New footer', 'foxi-builder' );
		if ( in_array( $type, Templates::KINDS, true ) ) {
			$id = wp_insert_post(
				array(
					'post_type'    => Parts::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => Templates::label( $type ),
					'post_content' => wp_slash( Templates::default_markup( $type ) ),
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_foxi_part_type', $type );
				return (int) $id;
			}
			return 0;
		}
		if ( $template && false !== strpos( $template, '/' ) ) {
			list( $slug, $key ) = explode( '/', $template, 2 );
			$kit                = self::load( sanitize_key( $slug ) );
			foreach ( $kit && isset( $kit['parts'] ) ? $kit['parts'] : array() as $part ) {
				if ( $part['key'] === $key ) {
					$blocks = $part['blocks'];
					$title  = $part['title'];
					$ctx    = array( 'images' => self::placeholder_urls( $kit ), 'pages' => self::existing_pages( $kit ), 'menus' => array() );
					$blocks = self::resolve( $blocks, $ctx );
				}
			}
		}
		if ( ! $blocks ) {
			$blocks = 'header' === $type ? self::blank_header() : self::blank_footer();
			$blocks = self::resolve( $blocks, array( 'images' => array(), 'pages' => array(), 'menus' => array() ) );
		}
		$id = wp_insert_post(
			array(
				'post_type'    => Parts::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => wp_slash( self::serialize( $blocks ) ),
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_foxi_part_type', $type );
			return (int) $id;
		}
		return 0;
	}

	private static function blank_header() {
		return array(
			array(
				'name'  => 'foxi/header',
				'attrs' => array( 'sticky' => true ),
				'inner' => array(
					array(
						'name'  => 'foxi/header-row',
						'attrs' => array( 'layout' => 'logo-left' ),
						'inner' => array(
							array( 'name' => 'foxi/logo' ),
							array( 'name' => 'foxi/nav' ),
							array(
								'name'  => 'foxi/buttons',
								'inner' => array( array( 'name' => 'foxi/button', 'attrs' => array( 'text' => 'Contact us', 'size' => 's', 'link' => array( 'url' => '#' ) ) ) ),
							),
						),
					),
				),
			),
		);
	}

	private static function blank_footer() {
		return array(
			array(
				'name'  => 'foxi/section',
				'attrs' => array(
					'tagName' => 'div',
					'fx'      => array( 'style' => array( '' => array( 'desktop' => array( 'normal' => array( 'background-color' => 'var(--fx-c-dark)', 'color' => 'var(--fx-c-on-dark)', 'padding-top' => 'var(--fx-space-xl)', 'padding-bottom' => 'var(--fx-space-l)' ) ) ) ) ),
				),
				'inner' => array(
					array( 'name' => 'foxi/copyright', 'attrs' => array( 'fx' => array( 'style' => array( '' => array( 'desktop' => array( 'normal' => array( 'text-align' => 'center' ) ) ) ) ) ) ),
				),
			),
		);
	}

	/* ------------------------------------------------------------------
	 * Export
	 * ---------------------------------------------------------------- */

	public static function export() {
		$settings = Settings::get();
		$kit      = array(
			'foxiKit'     => 1,
			'slug'        => sanitize_title( get_bloginfo( 'name' ) ),
			'name'        => get_bloginfo( 'name' ),
			'description' => sprintf( /* translators: %s date */ __( 'Exported from %1$s on %2$s', 'foxi-builder' ), home_url(), gmdate( 'Y-m-d' ) ),
			'brand'       => Brand::get(),
			'business'    => Brand::business(),
			'images'      => array(),
			'menus'       => array(),
			'menuIds'     => array(),
			'parts'       => array(),
			'pages'       => array(),
			// Lets the importing site copy images and fix links.
			'source'      => array(
				'home'    => home_url( '/' ),
				'uploads' => wp_upload_dir()['baseurl'],
			),
		);

		// Every site part; the ones in use come first and become the defaults.
		foreach ( Parts::types() as $type ) {
			$active = (int) $settings[ Parts::setting_key( $type ) ];
			$parts  = Parts::list_parts( $type );
			usort(
				$parts,
				static function ( $x, $y ) use ( $active ) {
					return ( (int) $y['id'] === $active ) - ( (int) $x['id'] === $active );
				}
			);
			foreach ( $parts as $part ) {
				if ( 'publish' !== $part['status'] ) {
					continue;
				}
				$kit['parts'][] = array(
					'key'         => $type . '-' . $part['id'],
					'type'        => $type,
					'title'       => $part['title'],
					'content'     => get_post_field( 'post_content', $part['id'] ),
					'alternative' => (int) $part['id'] !== $active,
				);
			}
		}

		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
		$front = (int) get_option( 'page_on_front' );
		foreach ( $pages as $p ) {
			$kit['pages'][] = array(
				'key'     => $p->post_name,
				'slug'    => $p->post_name,
				'title'   => $p->post_title,
				'order'   => $p->menu_order,
				'front'   => $p->ID === $front,
				'posts'   => $p->ID === (int) get_option( 'page_for_posts' ),
				'content' => $p->post_content,
				'page'    => Canvas::page_settings( $p->ID ),
				'seo'     => Seo\Seo::get( $p->ID ),
			);
		}

		$locations = get_nav_menu_locations();
		if ( ! empty( $locations['foxi-primary'] ) ) {
			$menu = wp_get_nav_menu_object( $locations['foxi-primary'] );
			if ( $menu ) {
				$kit['menuIds'][ $menu->term_id ] = 'main';
				$kit['menus'][]                   = array(
					'slug'     => 'main',
					'name'     => $menu->name,
					'location' => 'foxi-primary',
					'items'    => self::export_menu_items( wp_get_nav_menu_items( $menu->term_id ) ),
				);
			}
		}
		return $kit;
	}

	private static function export_menu_items( $items, $parent = 0 ) {
		$out = array();
		foreach ( (array) $items as $item ) {
			if ( (int) $item->menu_item_parent !== (int) $parent ) {
				continue;
			}
			$row = array( 'title' => $item->title );
			if ( 'page' === $item->object && 'post_type' === $item->type ) {
				$row['page'] = get_post_field( 'post_name', (int) $item->object_id );
			} else {
				$row['url'] = $item->url;
			}
			$children = self::export_menu_items( $items, $item->ID );
			if ( $children ) {
				$row['children'] = $children;
			}
			$out[] = $row;
		}
		return $out;
	}
}
