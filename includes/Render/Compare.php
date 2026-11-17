<?php
/**
 * Before & After block: two photos stacked, with a handle to drag between them.
 * A real range input underneath keeps it usable by keyboard and screen readers.
 *
 * @package FoxiBuilder
 */

namespace Foxi\Render;

defined( 'ABSPATH' ) || exit;

class Compare extends Base {
	const NAME = 'foxi/compare';

	const RATIOS = array( '', '16/9', '3/2', '4/3', '1/1', '4/5', '2/3', '9/16', '21/9' );

	protected static function markup( $a, $content, $block ) {
		$before = isset( $a['before'] ) && is_array( $a['before'] ) ? $a['before'] : array();
		$after  = isset( $a['after'] ) && is_array( $a['after'] ) ? $a['after'] : array();
		if ( empty( $before['url'] ) && empty( $before['id'] ) || empty( $after['url'] ) && empty( $after['id'] ) ) {
			return current_user_can( 'edit_pages' ) ? '<!-- Foxi Before & After: choose both photos. -->' : '';
		}
		$vertical = 'vertical' === foxi_get( $a, 'direction' );
		$start    = max( 0, min( 100, (float) foxi_get( $a, 'start', 50 ) ) );
		$ratio    = in_array( (string) foxi_get( $a, 'ratio', '16/9' ), self::RATIOS, true ) ? (string) foxi_get( $a, 'ratio', '16/9' ) : '16/9';
		$handle   = in_array( foxi_get( $a, 'handle' ), array( 'circle', 'pill', 'line' ), true ) ? $a['handle'] : 'circle';
		$on       = in_array( foxi_get( $a, 'labelsOn' ), array( 'always', 'hover' ), true ) ? $a['labelsOn'] : 'always';
		$b_label  = (string) foxi_get( $a, 'beforeLabel', __( 'Before', 'foxi-builder' ) );
		$a_label  = (string) foxi_get( $a, 'afterLabel', __( 'After', 'foxi-builder' ) );

		$vars = array( '--cmp-pos:' . $start . '%' );
		if ( '' !== $ratio ) {
			$vars[] = '--cmp-ratio:' . str_replace( '/', ' / ', $ratio );
		}
		$c = isset( $a['colors'] ) && is_array( $a['colors'] ) ? $a['colors'] : array();
		foreach ( array( 'line' => '--cmp-line', 'knob' => '--cmp-knob', 'icon' => '--cmp-icon' ) as $k => $var ) {
			$v = isset( $c[ $k ] ) ? \Foxi\Scrollbar::color( $c[ $k ], '' ) : '';
			if ( $v ) {
				$vars[] = $var . ':' . $v;
			}
		}

		$labels = '';
		if ( ! empty( $a['labels'] ) ) {
			$labels = ( '' !== trim( $b_label ) ? '<span class="fx-compare__label fx-compare__label--before" aria-hidden="true">' . esc_html( $b_label ) . '</span>' : '' )
				. ( '' !== trim( $a_label ) ? '<span class="fx-compare__label fx-compare__label--after" aria-hidden="true">' . esc_html( $a_label ) . '</span>' : '' );
		}
		$arrows = $vertical
			? '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 9 4-4 4 4M8 15l4 4 4-4"/></svg>'
			: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 8-4 4 4 4M15 8l4 4-4 4"/></svg>';

		return sprintf(
			'<div %1$s data-fx-compare>'
			. '<div class="fx-compare__frame">%2$s<div class="fx-compare__before">%3$s</div>%4$s'
			. '<div class="fx-compare__handle" aria-hidden="true"><span class="fx-compare__knob">%5$s</span></div>'
			. '<input class="fx-compare__range" type="range" min="0" max="100" step="1" value="%6$s" aria-label="%7$s"%8$s>'
			. '</div></div>',
			self::wrapper(
				$a,
				array(
					'fx-compare--' . ( $vertical ? 'vertical' : 'horizontal' ),
					'fx-compare--' . $handle,
					'fx-compare--labels-' . $on,
					'' === $ratio ? 'fx-compare--auto' : '',
					! empty( $a['follow'] ) ? 'fx-compare--follow' : '',
					! empty( $a['hint'] ) ? 'fx-compare--hint' : '',
				),
				array( 'style' => implode( ';', $vars ) )
			),
			self::img( $after, 'fx-compare__img fx-compare__img--after', 'large' ),
			self::img( $before, 'fx-compare__img', 'large', array( 'loading' => 'lazy' ) ),
			$labels,
			$arrows,
			esc_attr( $start ),
			/* translators: 1: before label, 2: after label */
			esc_attr( sprintf( __( 'Slide to compare %1$s and %2$s', 'foxi-builder' ), $b_label ? $b_label : __( 'before', 'foxi-builder' ), $a_label ? $a_label : __( 'after', 'foxi-builder' ) ) ),
			$vertical ? ' aria-orientation="vertical"' : ''
		);
	}
}
