<?php
/**
 * Classic widget: a small table of rates. (In block themes, use the
 * Shortcode block with [netarz_rates] instead.)
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'netarz_fx_widget',
			__( 'NetArz exchange rates', 'netarz-fx' ),
			array(
				'description'                 => __( 'Rates in Toman from the NetArz FX API.', 'netarz-fx' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults() );
		$codes    = Netarz_FX_Render::codes( $instance['currencies'] );
		if ( ! $codes ) {
			return;
		}

		wp_enqueue_style( 'netarz-fx' );
		$fields = 'both' === $instance['field'] ? array( 'buy', 'sell' ) : array( Netarz_FX_Render::field( $instance['field'] ) );
		$title  = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme markup.
		if ( '' !== (string) $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme markup.
		}
		echo '<div class="netarz-fx">' . Netarz_FX_Render::table( $codes, $fields ) . Netarz_FX_Render::attribution() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme markup.
	}

	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults() );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title', 'netarz-fx' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'currencies' ) ); ?>"><?php esc_html_e( 'Currencies (comma-separated codes)', 'netarz-fx' ); ?></label>
			<input class="widefat code" id="<?php echo esc_attr( $this->get_field_id( 'currencies' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'currencies' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['currencies'] ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'field' ) ); ?>"><?php esc_html_e( 'Show', 'netarz-fx' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'field' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'field' ) ); ?>">
				<option value="both" <?php selected( $instance['field'], 'both' ); ?>><?php esc_html_e( 'Buy and sell', 'netarz-fx' ); ?></option>
				<option value="sell" <?php selected( $instance['field'], 'sell' ); ?>><?php esc_html_e( 'Sell', 'netarz-fx' ); ?></option>
				<option value="buy" <?php selected( $instance['field'], 'buy' ); ?>><?php esc_html_e( 'Buy', 'netarz-fx' ); ?></option>
				<option value="mid" <?php selected( $instance['field'], 'mid' ); ?>><?php esc_html_e( 'Average', 'netarz-fx' ); ?></option>
			</select>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title'      => sanitize_text_field( isset( $new_instance['title'] ) ? $new_instance['title'] : '' ),
			'currencies' => implode( ',', Netarz_FX_Render::codes( isset( $new_instance['currencies'] ) ? $new_instance['currencies'] : '' ) ),
			'field'      => isset( $new_instance['field'] ) && in_array( $new_instance['field'], array( 'both', 'buy', 'sell', 'mid' ), true ) ? $new_instance['field'] : 'both',
		);
	}

	private function defaults() {
		return array(
			'title'      => __( 'Exchange rates', 'netarz-fx' ),
			'currencies' => 'USD,EUR,AED,TRY',
			'field'      => 'both',
		);
	}
}
