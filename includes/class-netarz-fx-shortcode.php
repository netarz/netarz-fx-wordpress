<?php
/**
 * [netarz_rate currency="usd" field="sell" show_name="0"]
 * [netarz_rates currencies="usd,eur,aed" fields="buy,sell"]
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Shortcode {

	public static function init() {
		add_shortcode( 'netarz_rate', array( __CLASS__, 'rate' ) );
		add_shortcode( 'netarz_rates', array( __CLASS__, 'rates' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
		// Priority 1: after all content and widgets have rendered, before footer scripts print.
		add_action( 'wp_footer', array( 'Netarz_FX_Render', 'maybe_enqueue' ), 1 );
	}

	public static function styles() {
		wp_register_style( 'netarz-fx', NETARZ_FX_URL . 'assets/netarz-fx.css', array(), NETARZ_FX_VERSION );
	}

	public static function rate( $atts ) {
		$atts = shortcode_atts(
			array(
				'currency'  => 'usd',
				'field'     => 'sell',
				'show_name' => '0',
			),
			$atts,
			'netarz_rate'
		);

		$codes = Netarz_FX_Render::codes( $atts['currency'] );
		if ( ! $codes ) {
			return '';
		}

		wp_enqueue_style( 'netarz-fx' );

		return Netarz_FX_Render::inline( $codes[0], $atts['field'], '1' === (string) $atts['show_name'] )
			. Netarz_FX_Render::attribution();
	}

	public static function rates( $atts ) {
		$atts = shortcode_atts(
			array(
				'currencies' => 'usd,eur,aed,try,gbp',
				'fields'     => 'buy,sell',
			),
			$atts,
			'netarz_rates'
		);

		$codes = Netarz_FX_Render::codes( $atts['currencies'] );
		if ( ! $codes ) {
			return '';
		}

		wp_enqueue_style( 'netarz-fx' );
		$fields = array_map( 'trim', explode( ',', strtolower( (string) $atts['fields'] ) ) );

		return '<div class="netarz-fx">' . Netarz_FX_Render::table( $codes, $fields ) . Netarz_FX_Render::attribution() . '</div>';
	}
}
