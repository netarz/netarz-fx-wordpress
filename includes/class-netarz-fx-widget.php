<?php
/**
 * Classic widget: a small table of rates. (In block themes and the block
 * widget editor, use the "NetArz rates table" block instead.)
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
		echo Netarz_FX_Render::board_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderer.
			$codes,
			$fields,
			array(
				'change'  => ! empty( $instance['change'] ),
				'updated' => ! empty( $instance['updated'] ),
			)
		);
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
		<p>
			<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'change' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'change' ) ); ?>" value="1" <?php checked( ! empty( $instance['change'] ) ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'change' ) ); ?>"><?php esc_html_e( 'Show the change since yesterday', 'netarz-fx' ); ?></label><br>
			<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'updated' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'updated' ) ); ?>" value="1" <?php checked( ! empty( $instance['updated'] ) ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( 'updated' ) ); ?>"><?php esc_html_e( 'Show when the rates were updated', 'netarz-fx' ); ?></label>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title'      => sanitize_text_field( isset( $new_instance['title'] ) ? $new_instance['title'] : '' ),
			'currencies' => implode( ',', Netarz_FX_Render::codes( isset( $new_instance['currencies'] ) ? $new_instance['currencies'] : '' ) ),
			'field'      => isset( $new_instance['field'] ) && in_array( $new_instance['field'], array( 'both', 'buy', 'sell', 'mid' ), true ) ? $new_instance['field'] : 'both',
			'change'     => empty( $new_instance['change'] ) ? 0 : 1,
			'updated'    => empty( $new_instance['updated'] ) ? 0 : 1,
		);
	}

	private function defaults() {
		return array(
			'title'      => __( 'Exchange rates', 'netarz-fx' ),
			'currencies' => 'USD,EUR,AED,TRY',
			'field'      => 'both',
			'change'     => 0,
			'updated'    => 0,
		);
	}
}
