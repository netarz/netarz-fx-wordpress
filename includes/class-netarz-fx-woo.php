<?php
/**
 * WooCommerce: for a store priced in a foreign currency (for example USD),
 * print the Toman equivalent after each product price: "about 10,290,000 Toman".
 *
 * Off by default (Settings > NetArz FX). Reads the cached board only, so it
 * adds no request per product; server mode only, because in browser mode the
 * server is not allowed to call the API.
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Woo {

	/** Store currencies that already are Toman or Rial. */
	const IRANIAN = array( 'IRT', 'IRR', 'IRHT', 'IRHR' );

	public static function init() {
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'price_html' ), 20, 2 );
	}

	/**
	 * @param string      $html    Price HTML from WooCommerce.
	 * @param \WC_Product $product The product.
	 */
	public static function price_html( $html, $product ) {
		if ( '' === (string) $html || ! Netarz_FX_Settings::get( 'woo_toman' ) || 'browser' === Netarz_FX_Settings::get( 'mode' ) ) {
			return $html;
		}
		if ( ! is_object( $product ) || ! function_exists( 'get_woocommerce_currency' ) || ! function_exists( 'wc_get_price_to_display' ) ) {
			return $html;
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $html;
		}
		// A range ("$10 - $40") would need two conversions; each variation still shows its own.
		if ( $product->is_type( array( 'variable', 'grouped' ) ) ) {
			return $html;
		}

		$code = strtoupper( (string) get_woocommerce_currency() );
		if ( in_array( $code, self::IRANIAN, true ) ) {
			return $html;
		}

		$field = Netarz_FX_Render::field( Netarz_FX_Settings::get( 'woo_field' ) );
		$row   = Netarz_FX_Client::rate( $code );
		if ( ! $row || empty( $row[ $field ] ) ) {
			return $html;
		}

		$price = (float) wc_get_price_to_display( $product );
		if ( $price <= 0 ) {
			return $html;
		}

		$unit  = isset( $row['unit'] ) ? max( 1, (int) $row['unit'] ) : 1;
		$toman = round( $price * (float) $row[ $field ] / $unit );

		wp_enqueue_style( 'netarz-fx' );

		return $html . ' <span class="netarz-fx-woo">' . esc_html(
			sprintf(
				/* translators: %s: a price in Iranian Toman, already formatted. */
				__( 'about %s Toman', 'netarz-fx' ),
				Netarz_FX_Render::number( $toman )
			)
		) . '</span>';
	}
}
