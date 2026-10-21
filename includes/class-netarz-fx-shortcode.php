<?php
/**
 * [netarz_rate currency="usd" field="sell" show_name="0" digits=""]
 * [netarz_rates currencies="usd,eur,aed" fields="buy,sell" change="0" updated="0" digits=""]
 *
 * [netarz_convert currencies="usd,eur,aed,try" field="sell" amount="1" updated="0" digits=""]
 *
 * [netarz_market symbols="XAU,GOLD18" change="0" updated="0" digits=""]
 * [netarz_gold] [netarz_coins] [netarz_metals]  (same options; preset symbols)
 * [netarz_chart symbol="XAU" range="30d" height="220" digits=""]
 *
 * digits: "persian" or "latin" overrides Settings > NetArz FX for one shortcode.
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
		add_shortcode( 'netarz_convert', array( __CLASS__, 'convert' ) );
		add_shortcode( 'netarz_market', array( __CLASS__, 'market' ) );
		add_shortcode( 'netarz_gold', array( __CLASS__, 'gold' ) );
		add_shortcode( 'netarz_coins', array( __CLASS__, 'coins' ) );
		add_shortcode( 'netarz_metals', array( __CLASS__, 'metals' ) );
		add_shortcode( 'netarz_chart', array( __CLASS__, 'chart' ) );
		// Registered on init, not wp_enqueue_scripts: the blocks name this style in
		// block.json and the block editor needs it too.
		add_action( 'init', array( __CLASS__, 'styles' ) );
		// Priority 1: after all content and widgets have rendered, before footer scripts print.
		add_action( 'wp_footer', array( 'Netarz_FX_Render', 'maybe_enqueue' ), 1 );
		add_action( 'wp_footer', array( 'Netarz_FX_Market_Render', 'maybe_enqueue' ), 1 );
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
				'digits'    => '',
			),
			$atts,
			'netarz_rate'
		);

		$codes = Netarz_FX_Render::codes( $atts['currency'] );
		if ( ! $codes ) {
			return '';
		}

		wp_enqueue_style( 'netarz-fx' );

		Netarz_FX_Render::use_digits( $atts['digits'] );
		$html = Netarz_FX_Render::inline( $codes[0], $atts['field'], self::yes( $atts['show_name'] ) )
			. Netarz_FX_Render::attribution();
		Netarz_FX_Render::use_digits( null );

		return $html;
	}

	public static function rates( $atts ) {
		$atts = shortcode_atts(
			array(
				'currencies' => 'usd,eur,aed,try,gbp',
				'fields'     => 'buy,sell',
				'change'     => '0',
				'updated'    => '0',
				'digits'     => '',
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

		return Netarz_FX_Render::board_html(
			$codes,
			$fields,
			array(
				'change'  => self::yes( $atts['change'] ),
				'updated' => self::yes( $atts['updated'] ),
				'digits'  => $atts['digits'],
			)
		);
	}

	public static function convert( $atts ) {
		$atts = shortcode_atts(
			array(
				'currencies' => 'usd,eur,aed,try',
				'field'      => 'sell',
				'amount'     => '1',
				'updated'    => '0',
				'digits'     => '',
			),
			$atts,
			'netarz_convert'
		);

		$codes = Netarz_FX_Render::codes( $atts['currencies'] );
		if ( ! $codes ) {
			return '';
		}

		wp_enqueue_style( 'netarz-fx' );

		return Netarz_FX_Render::converter(
			$codes,
			$atts['field'],
			is_numeric( $atts['amount'] ) ? (float) $atts['amount'] : 1,
			array(
				'updated' => self::yes( $atts['updated'] ),
				'digits'  => $atts['digits'],
			)
		);
	}

	public static function market( $atts, $content = '', $tag = 'netarz_market' ) {
		$presets = array(
			'netarz_gold'   => Netarz_FX_Markets::GOLD,
			'netarz_coins'  => Netarz_FX_Markets::COINS,
			'netarz_metals' => Netarz_FX_Markets::METALS,
		);
		$default = isset( $presets[ $tag ] ) ? $presets[ $tag ] : Netarz_FX_Markets::GOLD;

		$atts = shortcode_atts(
			array(
				'symbols' => implode( ',', $default ),
				'change'  => '1',
				'updated' => '1',
				'digits'  => '',
			),
			$atts,
			$tag
		);

		$symbols = Netarz_FX_Markets::symbols( $atts['symbols'] );
		if ( ! $symbols ) {
			return '';
		}

		wp_enqueue_style( 'netarz-fx' );

		return Netarz_FX_Market_Render::table(
			$symbols,
			array(
				'change'  => self::yes( $atts['change'] ),
				'updated' => self::yes( $atts['updated'] ),
				'digits'  => $atts['digits'],
			)
		);
	}

	public static function gold( $atts ) {
		return self::market( $atts, '', 'netarz_gold' );
	}

	public static function coins( $atts ) {
		return self::market( $atts, '', 'netarz_coins' );
	}

	public static function metals( $atts ) {
		return self::market( $atts, '', 'netarz_metals' );
	}

	public static function chart( $atts ) {
		$atts = shortcode_atts(
			array(
				'symbol' => 'XAU',
				'range'  => '30d',
				'height' => '220',
				'digits' => '',
			),
			$atts,
			'netarz_chart'
		);

		$symbols = Netarz_FX_Markets::symbols( $atts['symbol'] );
		if ( ! $symbols ) {
			return '';
		}

		wp_enqueue_style( 'netarz-fx' );

		return Netarz_FX_Market_Render::chart(
			$symbols[0],
			strtolower( (string) $atts['range'] ),
			array(
				'height' => absint( $atts['height'] ),
				'digits' => $atts['digits'],
			)
		);
	}

	/** "1", "yes", "true" and "on" all switch a shortcode option on. */
	public static function yes( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'on' ), true );
	}
}
