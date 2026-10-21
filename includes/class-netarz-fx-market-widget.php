<?php
/**
 * Classic widget with tabs: currencies, gold and coins. (In block themes
 * and the block widget editor, use the blocks instead.)
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Market_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'netarz_fx_market_widget',
			__( 'NetArz currency and gold rates', 'netarz-fx' ),
			array(
				'description'                 => __( 'Currencies, gold and coins from NetArz, in tabs.', 'netarz-fx' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	public function widget( $args, $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults() );
		$tabs     = array();
		$opts     = array(
			'change'  => ! empty( $instance['change'] ),
			'updated' => ! empty( $instance['updated'] ),
		);

		if ( ! empty( $instance['show_fx'] ) ) {
			$codes = Netarz_FX_Render::codes( $instance['currencies'] );
			if ( $codes ) {
				$tabs[] = array( __( 'Currencies', 'netarz-fx' ), Netarz_FX_Render::board_html( $codes, array( 'buy', 'sell' ), $opts ) );
			}
		}
		if ( ! empty( $instance['show_gold'] ) ) {
			$tabs[] = array( __( 'Gold', 'netarz-fx' ), Netarz_FX_Market_Render::table( Netarz_FX_Markets::GOLD, $opts ) );
		}
		if ( ! empty( $instance['show_coins'] ) ) {
			$tabs[] = array( __( 'Coins', 'netarz-fx' ), Netarz_FX_Market_Render::table( Netarz_FX_Markets::COINS, $opts ) );
		}

		// Before the connection is on, visitors get nothing at all, not an empty box.
		$tabs = array_values(
			array_filter(
				$tabs,
				static function ( $tab ) {
					return '' !== $tab[1];
				}
			)
		);
		if ( ! $tabs ) {
			return;
		}

		wp_enqueue_style( 'netarz-fx' );
		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme markup.
		if ( '' !== (string) $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme markup.
		}
		echo Netarz_FX_Market_Render::tabs( $tabs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the renderers.
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme markup.
	}

	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, $this->defaults() );
		$checks   = array(
			'show_fx'    => __( 'Currencies tab', 'netarz-fx' ),
			'show_gold'  => __( 'Gold tab (ounce, 18k gram, mesghal)', 'netarz-fx' ),
			'show_coins' => __( 'Coins tab', 'netarz-fx' ),
			'change'     => __( 'Show the change since yesterday', 'netarz-fx' ),
			'updated'    => __( 'Show when the rates were updated', 'netarz-fx' ),
		);
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
			<?php foreach ( $checks as $key => $label ) : ?>
				<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>" value="1" <?php checked( ! empty( $instance[ $key ] ) ); ?>>
				<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $label ); ?></label><br>
			<?php endforeach; ?>
		</p>
		<p class="description"><?php esc_html_e( '18k gold, the mesghal and the coins are a computed value, not the Tehran market price, and are labelled so.', 'netarz-fx' ); ?></p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$out = array(
			'title'      => sanitize_text_field( isset( $new_instance['title'] ) ? $new_instance['title'] : '' ),
			'currencies' => implode( ',', Netarz_FX_Render::codes( isset( $new_instance['currencies'] ) ? $new_instance['currencies'] : '' ) ),
		);
		foreach ( array( 'show_fx', 'show_gold', 'show_coins', 'change', 'updated' ) as $key ) {
			$out[ $key ] = empty( $new_instance[ $key ] ) ? 0 : 1;
		}
		return $out;
	}

	private function defaults() {
		return array(
			'title'      => __( 'Currency and gold rates', 'netarz-fx' ),
			'currencies' => 'USD,EUR,AED,TRY',
			'show_fx'    => 1,
			'show_gold'  => 1,
			'show_coins' => 1,
			'change'     => 1,
			'updated'    => 1,
		);
	}
}
