<?php
/**
 * Shared HTML for the shortcodes, the blocks and the widget. Everything
 * printed goes through esc_html / esc_attr / esc_url.
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Render {

	const FIELDS = array( 'buy', 'sell', 'mid' );

	const DIGITS = array( 'auto', 'persian', 'latin' );

	/** Attribution is printed at most once per page. */
	private static $attributed = false;

	private static $needs_script = false;

	/** Codes a browser-mode page asks for, so the script makes one request. */
	private static $codes = array();

	/** Per-shortcode digits ("persian" / "latin"), or null to follow the settings. */
	private static $digits = null;

	/**
	 * A netarz.ir link tagged so NetArz can tell visits that came from the plugin.
	 *
	 * @param string $path    Path on netarz.ir, e.g. "/rates".
	 * @param string $content Where the link sits (utm_content), e.g. "credit".
	 */
	public static function url( $path, $content ) {
		return add_query_arg(
			array(
				'utm_source'   => 'wp-plugin',
				'utm_medium'   => 'referral',
				'utm_campaign' => 'netarz-fx',
				'utm_content'  => sanitize_key( $content ),
			),
			'https://netarz.ir' . $path
		);
	}

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

	/**
	 * Use these digits for the output that follows ("persian", "latin"; anything
	 * else, e.g. "" or "auto", follows the settings). Call with null to reset.
	 */
	public static function use_digits( $digits ) {
		$digits       = strtolower( (string) $digits );
		self::$digits = in_array( $digits, array( 'persian', 'latin' ), true ) ? $digits : null;
	}

	public static function persian_digits() {
		$digits = null !== self::$digits ? self::$digits : Netarz_FX_Settings::get( 'digits' );
		if ( 'auto' === $digits ) {
			return 0 === strpos( determine_locale(), 'fa' );
		}
		return 'persian' === $digits;
	}

	/** Swaps Latin digits and separators for Persian ones when Persian digits are on. */
	public static function digits( $text ) {
		if ( ! self::persian_digits() ) {
			return (string) $text;
		}
		return strtr(
			(string) $text,
			array(
				','  => '٬',
				'.'  => '٫',
				'%'  => '٪',
				'0'  => '۰',
				'1'  => '۱',
				'2'  => '۲',
				'3'  => '۳',
				'4'  => '۴',
				'5'  => '۵',
				'6'  => '۶',
				'7'  => '۷',
				'8'  => '۸',
				'9'  => '۹',
			)
		);
	}

	public static function number( $value ) {
		return self::digits( number_format( (float) $value, floor( (float) $value ) == $value ? 0 : 2 ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
	}

	public static function currency_name( array $row ) {
		$fa = 0 === strpos( determine_locale(), 'fa' );
		if ( $fa && ! empty( $row['name'] ) ) {
			return (string) $row['name'];
		}
		return ! empty( $row['name_en'] ) ? (string) $row['name_en'] : (string) $row['code'];
	}

	private static function browser_mode() {
		return 'browser' === Netarz_FX_Settings::get( 'mode' );
	}

	/** The data attribute that carries a per-shortcode digits choice to the browser script. */
	private static function digits_attr() {
		return null === self::$digits ? '' : ' data-netarz-digits="' . esc_attr( self::$digits ) . '"';
	}

	/**
	 * One price as inline HTML.
	 */
	public static function inline( $code, $field, $show_name ) {
		$field = self::field( $field );

		if ( self::browser_mode() ) {
			self::$needs_script = true;
			self::$codes[]      = $code;
			return sprintf(
				'<span class="netarz-fx-rate" data-netarz-code="%1$s" data-netarz-field="%2$s" data-netarz-name="%3$s"%4$s>&hellip;</span>',
				esc_attr( $code ),
				esc_attr( $field ),
				$show_name ? '1' : '0',
				self::digits_attr()
			);
		}

		$row = Netarz_FX_Client::rate( $code );
		if ( ! $row || ! isset( $row[ $field ] ) ) {
			return self::unavailable( $code );
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
	 * The change since yesterday's close, e.g. "+0.39%" in green or "−0.12%" in red.
	 *
	 * @param float|null $percent change_24h_percent from the API; null when there is no data.
	 */
	public static function change( $percent ) {
		if ( null === $percent || '' === $percent || ! is_numeric( $percent ) ) {
			return '<span class="netarz-fx-change netarz-fx-flat">-</span>';
		}
		$percent = round( (float) $percent, 2 );
		if ( $percent > 0 ) {
			$class = 'netarz-fx-up';
			$sign  = '+';
		} elseif ( $percent < 0 ) {
			$class = 'netarz-fx-down';
			$sign  = "\u{2212}";
		} else {
			$class = 'netarz-fx-flat';
			$sign  = '';
		}
		$text = $sign . self::digits( number_format( abs( $percent ), 2 ) . '%' );
		return '<span class="netarz-fx-change ' . esc_attr( $class ) . '" dir="ltr">' . esc_html( $text ) . '</span>';
	}

	/**
	 * "Rate unavailable", plus a note for administrators when the code is a
	 * currency NetArz serves to Pro apps only.
	 */
	public static function unavailable( $code ) {
		return '<span class="netarz-fx-rate netarz-fx-unavailable">' . esc_html__( 'Rate unavailable', 'netarz-fx' ) . '</span>'
			. self::pro_notice( $code );
	}

	/**
	 * Shown only to users who can manage options, never to visitors: the free
	 * plan leaves Pro-only currencies (for example UZS, TJS) out of the board.
	 */
	public static function pro_notice( $code ) {
		if ( ! current_user_can( 'manage_options' ) || self::browser_mode() ) {
			return '';
		}
		if ( is_wp_error( Netarz_FX_Client::board() ) || ! Netarz_FX_Client::is_pro_only( $code ) ) {
			return '';
		}
		return ' <span class="netarz-fx-pro-note">'
			. esc_html(
				sprintf(
					/* translators: %s: a currency code, e.g. UZS. */
					__( '%s is available on the Pro plan of the NetArz FX API; the free plan does not include it. Only administrators see this note.', 'netarz-fx' ),
					$code
				)
			)
			. ' <a href="' . esc_url( self::url( '/docs/fx/plans', 'pro-notice' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Compare plans', 'netarz-fx' ) . '</a></span>';
	}

	/**
	 * A small table of several currencies.
	 *
	 * @param string[] $codes  Currency codes.
	 * @param string[] $fields Any of buy, sell, mid.
	 * @param array    $opts   change (bool): add a "change since yesterday" column.
	 */
	public static function table( array $codes, $fields, array $opts = array() ) {
		$fields = array_values( array_intersect( (array) $fields, self::FIELDS ) );
		if ( ! $fields ) {
			$fields = array( 'buy', 'sell' );
		}
		$change = ! empty( $opts['change'] );
		$labels = array(
			'buy'  => __( 'Buy', 'netarz-fx' ),
			'sell' => __( 'Sell', 'netarz-fx' ),
			'mid'  => __( 'Average', 'netarz-fx' ),
		);

		$html = '<table class="netarz-fx-table"><thead><tr><th>' . esc_html__( 'Currency', 'netarz-fx' ) . '</th>';
		foreach ( $fields as $f ) {
			$html .= '<th>' . esc_html( $labels[ $f ] ) . '</th>';
		}
		if ( $change ) {
			$html .= '<th>' . esc_html__( 'Since yesterday', 'netarz-fx' ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		foreach ( $codes as $code ) {
			if ( self::browser_mode() ) {
				self::$needs_script = true;
				self::$codes[]      = $code;
				$html              .= '<tr><td><span class="netarz-fx-name" data-netarz-code="' . esc_attr( $code ) . '">' . esc_html( $code ) . '</span></td>';
				foreach ( $fields as $f ) {
					$html .= '<td><span class="netarz-fx-rate" data-netarz-code="' . esc_attr( $code ) . '" data-netarz-field="' . esc_attr( $f ) . '" data-netarz-name="0">&hellip;</span></td>';
				}
				if ( $change ) {
					$html .= '<td><span class="netarz-fx-rate netarz-fx-change" data-netarz-code="' . esc_attr( $code ) . '" data-netarz-field="change" dir="ltr">&hellip;</span></td>';
				}
				$html .= '</tr>';
				continue;
			}

			$row = Netarz_FX_Client::rate( $code );
			if ( ! $row ) {
				$note = self::pro_notice( $code );
				if ( '' !== $note ) {
					$html .= '<tr><td>' . esc_html( $code ) . '</td><td colspan="' . esc_attr( (string) ( count( $fields ) + ( $change ? 1 : 0 ) ) ) . '">' . $note . '</td></tr>';
				}
				continue;
			}
			$html .= '<tr><td>' . esc_html( self::currency_name( $row ) ) . '</td>';
			foreach ( $fields as $f ) {
				$html .= '<td>' . esc_html( isset( $row[ $f ] ) ? self::price_text( $row, $f ) : '-' ) . '</td>';
			}
			if ( $change ) {
				$html .= '<td>' . self::change( isset( $row['change_24h_percent'] ) ? $row['change_24h_percent'] : null ) . '</td>';
			}
			$html .= '</tr>';
		}

		return $html . '</tbody></table>';
	}

	/**
	 * The table with its wrapper, the optional "Updated" line and the optional credit.
	 *
	 * @param string[] $codes  Currency codes.
	 * @param string[] $fields Any of buy, sell, mid.
	 * @param array    $opts   change (bool), updated (bool), digits ("persian" | "latin" | "").
	 */
	public static function board_html( array $codes, $fields, array $opts = array() ) {
		self::use_digits( isset( $opts['digits'] ) ? $opts['digits'] : '' );

		$html = '<div class="netarz-fx"' . self::digits_attr() . '>'
			. self::table( $codes, $fields, $opts )
			. ( ! empty( $opts['updated'] ) ? self::updated() : '' )
			. self::attribution()
			. '</div>';

		self::use_digits( null );
		return $html;
	}

	/**
	 * "Updated 14:20 (15-minute delay)": when the rates were taken, from meta.as_of.
	 * The free plan's rates run a few minutes behind; the line says so.
	 */
	public static function updated() {
		if ( self::browser_mode() ) {
			self::$needs_script = true;
			return '<p class="netarz-fx-updated" data-netarz-updated="1"></p>';
		}

		$meta = Netarz_FX_Client::meta();
		$ts   = isset( $meta['as_of'] ) ? strtotime( (string) $meta['as_of'] ) : false;
		if ( ! $ts ) {
			return '';
		}

		$format = wp_date( 'Y-m-d', $ts ) === wp_date( 'Y-m-d' ) ? 'H:i' : 'Y-m-d H:i';
		$when   = self::digits( wp_date( $format, $ts ) );
		$delay  = isset( $meta['delayed_minutes'] ) ? (int) $meta['delayed_minutes'] : 0;

		if ( $delay > 0 ) {
			/* translators: 1: time (and date, if not today) the rates were taken, 2: minutes of delay. */
			$text = sprintf( __( 'Updated %1$s (%2$s-minute delay)', 'netarz-fx' ), $when, self::digits( (string) $delay ) );
		} else {
			/* translators: %s: time (and date, if not today) the rates were taken. */
			$text = sprintf( __( 'Updated %s', 'netarz-fx' ), $when );
		}

		return '<p class="netarz-fx-updated">' . esc_html( $text ) . '</p>';
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

		return ' <span class="netarz-fx-credit"><a href="' . esc_url( self::url( '/rates', 'credit' ) ) . '">' . esc_html__( 'Rates by NetArz', 'netarz-fx' ) . '</a></span>';
	}

	/** Enqueue the browser-mode script once, only on pages that need it. */
	public static function maybe_enqueue() {
		if ( ! self::$needs_script ) {
			return;
		}
		wp_enqueue_script( 'netarz-fx', NETARZ_FX_URL . 'assets/netarz-fx.js', array(), NETARZ_FX_VERSION, true );
		// wp_localize_script() would turn 0 into "0", which is truthy in JavaScript;
		// wp_json_encode() keeps numbers as numbers.
		$config = array(
			'api'            => NETARZ_FX_API,
			'key'            => (string) Netarz_FX_Settings::get( 'api_key' ),
			'codes'          => array_values( array_unique( self::$codes ) ),
			'cacheMs'        => Netarz_FX_Client::cache_seconds() * 1000,
			'persian'        => self::persian_digits() ? 1 : 0,
			'nameFa'         => 0 === strpos( determine_locale(), 'fa' ) ? 1 : 0,
			'tz'             => wp_timezone_string(),
			/* translators: %s: a price in Iranian Toman, already formatted. */
			'toman'          => __( '%s Toman', 'netarz-fx' ),
			/* translators: %s: number of currency units the price is for, e.g. 100. */
			'perUnits'       => __( '(per %s units)', 'netarz-fx' ),
			'unavailable'    => __( 'Rate unavailable', 'netarz-fx' ),
			/* translators: %s: time (and date, if not today) the rates were taken. */
			'updated'        => __( 'Updated %s', 'netarz-fx' ),
			/* translators: 1: time (and date, if not today) the rates were taken, 2: minutes of delay. */
			'updatedDelayed' => __( 'Updated %1$s (%2$s-minute delay)', 'netarz-fx' ),
		);
		wp_add_inline_script( 'netarz-fx', 'var netarzFx = ' . wp_json_encode( $config ) . ';', 'before' );
	}
}
