<?php
/**
 * Foxi site template: blog index, archives, search results, single blog
 * posts and the 404 page, with the Foxi header and footer.
 *
 * @package FoxiBuilder
 */

defined( 'ABSPATH' ) || exit;

$foxi_kind   = Foxi\Templates::current_kind();
$foxi_header = Foxi\Parts::active( 'header' );
$foxi_footer = Foxi\Parts::active( 'footer' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php if ( ! current_theme_supports( 'title-tag' ) && ! wp_is_block_theme() ) : ?>
		<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<?php endif; ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'foxi-template foxi-template--' . sanitize_html_class( $foxi_kind ) ); ?>>
<?php wp_body_open(); ?>
<a class="fx-skip-link" href="#fx-main"><?php esc_html_e( 'Skip to content', 'foxi-builder' ); ?></a>
<?php
if ( $foxi_header ) {
	echo Foxi\Parts::render( $foxi_header ); // phpcs:ignore WordPress.Security.EscapeOutput -- block output.
}
?>
<main id="fx-main" class="fx-main">
	<?php
	if ( is_singular() ) {
		the_post();
	}
	echo do_blocks( Foxi\Templates::markup( $foxi_kind ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- block output.
	?>
</main>
<?php
if ( $foxi_footer ) {
	echo '<footer class="fx-site-footer">' . Foxi\Parts::render( $foxi_footer ) . '</footer>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
wp_footer();
?>
</body>
</html>
