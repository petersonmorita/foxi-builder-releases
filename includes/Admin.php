<?php
/**
 * Admin menu and dashboard app.
 *
 * @package FoxiBuilder
 */

namespace Foxi;

defined( 'ABSPATH' ) || exit;

class Admin {

	const VIEWS = array(
		'foxi'          => 'home',
		'foxi-starters' => 'starters',
		'foxi-brand'    => 'brand',
		'foxi-seo'      => 'seo',
		'foxi-settings' => 'settings',
	);

	public function init() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_init', array( $this, 'redirect' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FOXI_FILE ), array( $this, 'action_links' ) );
	}

	public static function icon() {
		// Fox head mark.
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M2 2.5 7 6.2h6L18 2.5l-.6 7.3c-.3 3.6-3.6 7.7-7.4 7.7S2.9 13.4 2.6 9.8L2 2.5Zm4.1 7.6c.5 1.6 2 2.9 3.9 3.2 1.9-.3 3.4-1.6 3.9-3.2-1.4.2-2.8.9-3.9 2-1.1-1.1-2.5-1.8-3.9-2Z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	public function menu() {
		$render = array( $this, 'page' );
		add_menu_page( __( 'Foxi Builder', 'foxi-builder' ), __( 'Foxi', 'foxi-builder' ), 'edit_pages', 'foxi', $render, self::icon(), 58 );
		add_submenu_page( 'foxi', __( 'Foxi Builder', 'foxi-builder' ), __( 'Dashboard', 'foxi-builder' ), 'edit_pages', 'foxi', $render );
		add_submenu_page( 'foxi', __( 'Starter sites', 'foxi-builder' ), __( 'Starter sites', 'foxi-builder' ), 'manage_options', 'foxi-starters', $render );
		add_submenu_page( 'foxi', __( 'Brand Kit', 'foxi-builder' ), __( 'Brand & business', 'foxi-builder' ), 'manage_options', 'foxi-brand', $render );
		add_submenu_page( 'foxi', __( 'Headers & Footers', 'foxi-builder' ), __( 'Headers & footers', 'foxi-builder' ), 'edit_pages', 'edit.php?post_type=' . Parts::POST_TYPE );
		add_submenu_page( 'foxi', __( 'Messages', 'foxi-builder' ), __( 'Messages', 'foxi-builder' ), 'edit_pages', 'edit.php?post_type=' . Forms::POST_TYPE );
		add_submenu_page( 'foxi', __( 'SEO', 'foxi-builder' ), __( 'SEO', 'foxi-builder' ), 'manage_options', 'foxi-seo', $render );
		add_submenu_page( 'foxi', __( 'Settings', 'foxi-builder' ), __( 'Settings', 'foxi-builder' ), 'manage_options', 'foxi-settings', $render );
	}

	public function page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'foxi'; // phpcs:ignore WordPress.Security.NonceVerification
		$view = isset( self::VIEWS[ $page ] ) ? self::VIEWS[ $page ] : 'home';
		echo '<div class="wrap foxi-admin-wrap"><div id="foxi-admin" data-view="' . esc_attr( $view ) . '"><p class="foxi-admin-loading">' . esc_html__( 'Loading Foxi…', 'foxi-builder' ) . '</p></div></div>';
	}

	public function assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( self::VIEWS[ $page ] ) ) {
			return;
		}
		$asset = Assets::asset( 'admin' );
		wp_enqueue_media();
		wp_enqueue_script( 'foxi-admin', FOXI_URL . 'build/admin.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'foxi-admin', FOXI_URL . 'build/admin.css', array( 'wp-components' ), $asset['version'] );
		$fonts = Brand::google_fonts_url();
		if ( $fonts ) {
			wp_enqueue_style( 'foxi-fonts', $fonts, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}

		$data                 = Studio::data();
		$data['starters']     = Starters::catalog();
		$data['parts']        = array(
			'header'  => Parts::list_parts( 'header' ),
			'footer'  => Parts::list_parts( 'footer' ),
			'archive' => Parts::list_parts( 'archive' ),
			'single'  => Parts::list_parts( 'single' ),
			'404'     => Parts::list_parts( '404' ),
		);
		$data['partsUrl']     = admin_url( 'edit.php?post_type=' . Parts::POST_TYPE );
		$data['messagesUrl']  = admin_url( 'edit.php?post_type=' . Forms::POST_TYPE );
		$data['newPageUrl']   = admin_url( 'post-new.php?post_type=page' );
		$data['pagesUrl']     = admin_url( 'edit.php?post_type=page' );
		$data['allSettings']  = Settings::get();
		$data['messageCount'] = (int) wp_count_posts( Forms::POST_TYPE )->private;
		$data['recentPages']  = array_map(
			static function ( $p ) {
				return array(
					'id'       => $p->ID,
					'title'    => $p->post_title ? $p->post_title : __( '(no title)', 'foxi-builder' ),
					'edit'     => Studio::edit_url( $p->ID ),
					'view'     => get_permalink( $p ),
					'modified' => human_time_diff( strtotime( $p->post_modified_gmt ), time() ),
				);
			},
			get_posts( array( 'post_type' => 'page', 'posts_per_page' => 6, 'orderby' => 'modified', 'post_status' => array( 'publish', 'draft' ) ) )
		);
		wp_localize_script( 'foxi-admin', 'foxiEditor', $data );
	}

	public function redirect() {
		if ( get_option( 'foxi_activation_redirect' ) ) {
			delete_option( 'foxi_activation_redirect' );
			if ( ! isset( $_GET['activate-multi'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				wp_safe_redirect( admin_url( 'admin.php?page=foxi' ) );
				exit;
			}
		}
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=foxi' ) ) . '">' . esc_html__( 'Dashboard', 'foxi-builder' ) . '</a>' );
		return $links;
	}
}
