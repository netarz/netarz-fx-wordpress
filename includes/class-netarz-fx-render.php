<?php
/**
 * Shared HTML for the shortcodes and the widget. Everything printed goes
 * through esc_html / esc_attr / esc_url.
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Render {

	const FIELDS = array( 'buy', 'sell', 'mid' );

	/** Attribution is printed at most once per page. */
	private static $attributed = false;

	private static $needs_script = false;

	/** Codes a browser-mode page asks for, so the script makes one request. */
	private static $codes = array();

	public static function field( $field ) {
		$field = strtolower( (string) $field );
		return in_array( $field, self::FIELDS, true ) ? $field : 'sell';
	}

	/** Normalises "usd, EUR ,aed" into array( 'USD', 'EUR', 'AED' ). */
	public static function codes( $list ) {
		$codes = array();
		foreach ( explode( ',', (string) $list ) as $code ) {
			$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', $code ) );
			if ( 3 === strlen( $code ) ) {
				$codes[] = $code;
			}
		}
		return array_slice( array_values( array_unique( $codes ) ), 0, 20 );
	}

	public static function number( $value ) {
		$out = number_format( (float) $value, floor( (float) $value ) == $value ? 0 : 2 ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
		if ( self::persian_digits() ) {
			$out = strtr( $out, array( ',' => '٬', '.' => '٫', '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ) );
		}
		return $out;
	}

	public static function persian_digits() {
		$digits = Netarz_FX_Settings::get( 'digits' );
		if ( 'auto' === $digits ) {
			return 0 === strpos( determine_locale(), 'fa' );
		}
		return 'persian' === $digits;
	}

	public static function currency_name( array $row ) {
		$fa = 0 === strpos( determine_locale(), 'fa' );
		if ( $fa && ! empty( $row['name'] ) ) {
			return (string) $row['name'];
		}
		return ! empty( $row['name_en'] ) ? (string) $row['name_en'] : (string) $row['code'];
	}

	/**
	 * One price as inline HTML.
	 */
	public static function inline( $code, $field, $show_name ) {
		$field = self::field( $field );

		if ( 'browser' === Netarz_FX_Settings::get( 'mode' ) ) {
			self::$needs_script = true;
			self::$codes[]      = $code;
			return sprintf(
				'<span class="netarz-fx-rate" data-netarz-code="%1$s" data-netarz-field="%2$s" data-netarz-name="%3$s">&hellip;</span>',
				esc_attr( $code ),
				esc_attr( $field ),
				$show_name ? '1' : '0'
			);
		}

		$row = Netarz_FX_Client::rate( $code );
		if ( ! $row || ! isset( $row[ $field ] ) ) {
			return '<span class="netarz-fx-rate netarz-fx-unavailable">' . esc_html__( 'Rate unavailable', 'netarz-fx' ) . '</span>';
		}

		$text = self::price_text( $row, $field );
		if ( $show_name ) {
			$text = self::currency_name( $row ) . ': ' . $text;
		}

		return '<span class="netarz-fx-rate" data-netarz-code="' . esc_attr( $code ) . '">' . esc_html( $text ) . '</span>';
	}

	public static function price_text( array $row, $field ) {
		/* translators: %s: a price in Iranian Toman, already formatted. */
		$text = sprintf( __( '%s Toman', 'netarz-fx' ), self::number( $row[ $field ] ) );
		$unit = isset( $row['unit'] ) ? (int) $row['unit'] : 1;
		if ( $unit > 1 ) {
			/* translators: %s: number of currency units the price is for, e.g. 100. */
			$text .= ' ' . sprintf( __( '(per %s units)', 'netarz-fx' ), self::number( $unit ) );
		}
		return $text;
	}

	/**
	 * A small table of several currencies (used by [netarz_rates] and the widget).
	 */
	public static function table( array $codes, $fields ) {
		$fields = array_values( array_intersect( (array) $fields, self::FIELDS ) );
		if ( ! $fields ) {
			$fields = array( 'buy', 'sell' );
		}
		$labels = array(
			'buy'  => __( 'Buy', 'netarz-fx' ),
			'sell' => __( 'Sell', 'netarz-fx' ),
			'mid'  => __( 'Average', 'netarz-fx' ),
		);

		$html  = '<table class="netarz-fx-table"><thead><tr><th>' . esc_html__( 'Currency', 'netarz-fx' ) . '</th>';
		foreach ( $fields as $f ) {
			$html .= '<th>' . esc_html( $labels[ $f ] ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		foreach ( $codes as $code ) {
			if ( 'browser' === Netarz_FX_Settings::get( 'mode' ) ) {
				self::$needs_script = true;
				self::$codes[]      = $code;
				$html .= '<tr><td><span class="netarz-fx-name" data-netarz-code="' . esc_attr( $code ) . '">' . esc_html( $code ) . '</span></td>';
				foreach ( $fields as $f ) {
					$html .= '<td><span class="netarz-fx-rate" data-netarz-code="' . esc_attr( $code ) . '" data-netarz-field="' . esc_attr( $f ) . '" data-netarz-name="0">&hellip;</span></td>';
				}
				$html .= '</tr>';
				continue;
			}

			$row = Netarz_FX_Client::rate( $code );
			if ( ! $row ) {
				continue;
			}
			$html .= '<tr><td>' . esc_html( self::currency_name( $row ) ) . '</td>';
			foreach ( $fields as $f ) {
				$html .= '<td>' . esc_html( isset( $row[ $f ] ) ? self::price_text( $row, $f ) : '-' ) . '</td>';
			}
			$html .= '</tr>';
		}

		return $html . '</tbody></table>';
	}

	/**
	 * «نرخ از نِت اَرز»: a visible credit link, shown once per page only when the
	 * site owner turns it on in Settings > NetArz FX (or with the filter).
	 */
	public static function attribution() {
		if ( self::$attributed ) {
			return '';
		}
		$show = (bool) apply_filters( 'netarz_fx_show_attribution', (bool) Netarz_FX_Settings::get( 'attribution' ) );
		if ( ! $show ) {
			return '';
		}
		self::$attributed = true;

		return ' <span class="netarz-fx-credit"><a href="' . esc_url( 'https://netarz.ir/rates' ) . '">' . esc_html__( 'Rates by NetArz', 'netarz-fx' ) . '</a></span>';
	}

	/** Enqueue the browser-mode script once, only on pages that need it. */
	public static function maybe_enqueue() {
		if ( ! self::$needs_script ) {
			return;
		}
		wp_enqueue_script( 'netarz-fx', NETARZ_FX_URL . 'assets/netarz-fx.js', array(), NETARZ_FX_VERSION, true );
		wp_localize_script(
			'netarz-fx',
			'netarzFx',
			array(
				'api'        => NETARZ_FX_API,
				'key'        => (string) Netarz_FX_Settings::get( 'api_key' ),
				'codes'      => array_values( array_unique( self::$codes ) ),
				'cacheMs'    => Netarz_FX_Client::cache_seconds() * 1000,
				'persian'    => self::persian_digits() ? 1 : 0,
				'nameFa'     => 0 === strpos( determine_locale(), 'fa' ) ? 1 : 0,
				/* translators: %s: a price in Iranian Toman, already formatted. */
				'toman'      => __( '%s Toman', 'netarz-fx' ),
				/* translators: %s: number of currency units the price is for, e.g. 100. */
				'perUnits'   => __( '(per %s units)', 'netarz-fx' ),
				'unavailable' => __( 'Rate unavailable', 'netarz-fx' ),
			)
		);
	}
}
