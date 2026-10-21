<?php
/**
 * HTML for gold, coins, metals and charts: the market table and the
 * server-side SVG chart. Everything printed is escaped.
 *
 * A computed row (18k gold, mesghal, coins) always carries the
 * "Computed value" badge and the note that it is not the Tehran market
 * price. The data source line is always printed as plain text, because
 * naming the source is a condition of the data licence; it is not a link.
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Market_Render {

	/** Gives each tab group and chart on a page its own ids. */
	private static $count = 0;

	private static $needs_tabs = false;

	/** Source names when the API does not send any, by source key. */
	private static function source_names() {
		return array(
			'goldapi'  => 'gold-api.com',
			'computed' => __( 'NetArz computed value (ounce from gold-api.com)', 'netarz-fx' ),
			'netarz'   => __( 'NetArz', 'netarz-fx' ),
		);
	}

	public static function name( array $row ) {
		$fa = 0 === strpos( determine_locale(), 'fa' );
		if ( $fa && '' !== $row['name'] ) {
			return $row['name'];
		}
		return '' !== $row['name_en'] ? $row['name_en'] : $row['symbol'];
	}

	/**
	 * A formatted number with the row's decimals (0 for Toman, 2 for dollars unless the API says otherwise).
	 *
	 * @param float $value Number.
	 * @param array $row   Normalised row.
	 */
	public static function number( $value, array $row ) {
		$decimals = null !== $row['decimals'] ? (int) $row['decimals'] : ( 'IRT' === $row['quote'] ? 0 : 2 );
		$decimals = max( 0, min( 6, $decimals ) );
		return Netarz_FX_Render::digits( number_format( (float) $value, $decimals ) );
	}

	/** "102,900 Toman" or "2,412.30 USD". */
	public static function price_text( $value, array $row ) {
		$number = self::number( $value, $row );
		if ( 'USD' === $row['quote'] ) {
			/* translators: %s: a price in US dollars, already formatted. */
			return sprintf( __( '%s USD', 'netarz-fx' ), $number );
		}
		if ( 'IRT' === $row['quote'] ) {
			/* translators: %s: a price in Iranian Toman, already formatted. */
			return sprintf( __( '%s Toman', 'netarz-fx' ), $number );
		}
		return $number . ' ' . $row['quote'];
	}

	/** The badge printed beside every computed row. */
	public static function computed_badge() {
		return ' <span class="netarz-fx-computed-badge">' . esc_html__( 'Computed value', 'netarz-fx' ) . '</span>';
	}

	/** The note printed under anything that shows a computed row. Never optional. */
	public static function computed_note() {
		return '<p class="netarz-fx-computed-note"><strong>' . esc_html__( 'Computed value:', 'netarz-fx' ) . '</strong> '
			. esc_html__( 'worked out from the global gold ounce and the NetArz dollar rate. It is not the Tehran market price and leaves out the coin premium and making charges.', 'netarz-fx' )
			. '</p>';
	}

	/**
	 * "Source: gold-api.com, NetArz" as plain text.
	 *
	 * @param array $rows    Rows on display.
	 * @param array $sources key => array( name, url ) from the response.
	 */
	public static function sources_line( array $rows, array $sources ) {
		$fallback = self::source_names();
		$names    = array();
		foreach ( $rows as $row ) {
			$key = $row['source'];
			if ( '' === $key && 'metal' === $row['class'] ) {
				$key = 'goldapi';
			}
			if ( '' === $key && $row['computed'] ) {
				$key = 'computed';
			}
			if ( '' === $key && 'fiat' === $row['class'] ) {
				$key = 'netarz';
			}
			// Known sources use the plugin's own, translated names; others the name NetArz sends.
			if ( isset( $fallback[ $key ] ) ) {
				$names[ $key ] = $fallback[ $key ];
			} elseif ( '' !== $row['source_name'] ) {
				$names[ $key ] = $row['source_name'];
			} elseif ( isset( $sources[ $key ] ) ) {
				$names[ $key ] = $sources[ $key ]['name'];
			}
		}
		if ( ! $names ) {
			return '';
		}
		/* translators: %s: names of the data sources, comma-separated. */
		return '<p class="netarz-fx-sources">' . esc_html( sprintf( __( 'Source: %s', 'netarz-fx' ), implode( _x( ', ', 'list separator', 'netarz-fx' ), array_unique( $names ) ) ) ) . '</p>';
	}

	/**
	 * "Updated 14:20 (15-minute delay)", or "Last updated ..." when the
	 * numbers are an old copy because NetArz could not be reached.
	 *
	 * @param array $data  Normalised answer (meta, rows, stale_copy).
	 * @param bool  $asked The shortcode asked for the line; a stale copy prints it anyway.
	 */
	public static function updated( array $data, $asked ) {
		$meta  = $data['meta'];
		$stale = ! empty( $data['stale_copy'] ) || ! empty( $meta['stale'] );
		if ( ! $asked && ! $stale ) {
			return '';
		}

		$ts = isset( $meta['as_of'] ) ? strtotime( (string) $meta['as_of'] ) : false;
		if ( ! $ts ) {
			foreach ( $data['rows'] as $row ) {
				$row_ts = '' !== $row['as_of'] ? strtotime( $row['as_of'] ) : false;
				if ( $row_ts && ( ! $ts || $row_ts > $ts ) ) {
					$ts = $row_ts;
				}
			}
		}
		if ( ! $ts ) {
			return '';
		}

		$format = wp_date( 'Y-m-d', $ts ) === wp_date( 'Y-m-d' ) ? 'H:i' : 'Y-m-d H:i';
		$when   = Netarz_FX_Render::digits( wp_date( $format, $ts ) );
		$delay  = isset( $meta['delayed_minutes'] ) ? (int) $meta['delayed_minutes'] : 0;

		if ( $stale ) {
			/* translators: %s: time (and date, if not today) of the last numbers NetArz sent. */
			$text = sprintf( __( 'Last updated %s', 'netarz-fx' ), $when );
		} elseif ( $delay > 0 ) {
			/* translators: 1: time (and date, if not today) the rates were taken, 2: minutes of delay. */
			$text = sprintf( __( 'Updated %1$s (%2$s-minute delay)', 'netarz-fx' ), $when, Netarz_FX_Render::digits( (string) $delay ) );
		} else {
			/* translators: %s: time (and date, if not today) the rates were taken. */
			$text = sprintf( __( 'Updated %s', 'netarz-fx' ), $when );
		}

		return '<p class="netarz-fx-updated' . ( $stale ? ' netarz-fx-stale' : '' ) . '">' . esc_html( $text ) . '</p>';
	}

	/**
	 * Shown only to users who can manage options.
	 *
	 * @param string $text Plain text.
	 */
	public static function admin_note( $text ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		return ' <span class="netarz-fx-pro-note">' . esc_html( $text ) . ' ' . esc_html__( 'Only administrators see this note.', 'netarz-fx' ) . '</span>';
	}

	/** What a page shows before the connection is turned on: nothing for visitors, a pointer for administrators. */
	public static function not_connected() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}
		return '<span class="netarz-fx-pro-note">'
			. esc_html__( 'NetArz rates appear here once you turn on the connection in Settings > NetArz FX. Only administrators see this note.', 'netarz-fx' )
			. ' <a href="' . esc_url( admin_url( 'options-general.php?page=netarz-fx' ) ) . '">' . esc_html__( 'Open settings', 'netarz-fx' ) . '</a></span>';
	}

	/**
	 * A table of market rows: gold, coins, metals or any allowed symbols.
	 *
	 * @param string[] $symbols Normalised symbols.
	 * @param array    $opts    change (bool), updated (bool), digits ("persian" | "latin" | "").
	 */
	public static function table( array $symbols, array $opts = array() ) {
		if ( ! Netarz_FX_Client::connected() ) {
			return self::not_connected();
		}

		Netarz_FX_Render::use_digits( isset( $opts['digits'] ) ? $opts['digits'] : '' );
		$html = '<div class="netarz-fx netarz-fx-market">' . self::table_inner( $symbols, $opts ) . Netarz_FX_Render::attribution() . '</div>';
		Netarz_FX_Render::use_digits( null );

		return $html;
	}

	private static function table_inner( array $symbols, array $opts ) {
		$data = Netarz_FX_Markets::latest( $symbols );
		if ( is_wp_error( $data ) ) {
			return '<span class="netarz-fx-rate netarz-fx-unavailable">' . esc_html__( 'Rates unavailable', 'netarz-fx' ) . '</span>'
				. self::admin_note( $data->get_error_message() );
		}

		$change   = ! empty( $opts['change'] );
		$shown    = array();
		$missing  = array();
		$computed = false;

		$html = '<table class="netarz-fx-table"><thead><tr><th>' . esc_html__( 'Item', 'netarz-fx' ) . '</th><th>' . esc_html__( 'Price', 'netarz-fx' ) . '</th>';
		if ( $change ) {
			$html .= '<th>' . esc_html__( 'Since yesterday', 'netarz-fx' ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';

		foreach ( $symbols as $symbol ) {
			if ( ! isset( $data['rows'][ $symbol ] ) || null === $data['rows'][ $symbol ]['price'] ) {
				$missing[] = $symbol;
				continue;
			}
			$row     = $data['rows'][ $symbol ];
			$shown[] = $row;
			$badge   = '';
			if ( $row['computed'] ) {
				$computed = true;
				$badge    = self::computed_badge();
			}
			$html .= '<tr><td>' . esc_html( self::name( $row ) ) . $badge . '</td>';
			$html .= '<td>' . esc_html( self::price_text( $row['price'], $row ) ) . '</td>';
			if ( $change ) {
				$html .= '<td>' . Netarz_FX_Render::change( $row['change'] ) . '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody></table>';

		if ( ! $shown ) {
			$html = '<span class="netarz-fx-rate netarz-fx-unavailable">' . esc_html__( 'Rates unavailable', 'netarz-fx' ) . '</span>';
		}

		if ( $missing ) {
			$html .= self::admin_note(
				Netarz_FX_Markets::keyed()
					/* translators: %s: symbols, comma-separated, e.g. XAG, COIN_EMAMI. */
					? sprintf( __( 'NetArz did not return %s. Check the symbols; some are available on the Pro plan only.', 'netarz-fx' ), implode( ', ', $missing ) )
					/* translators: %s: symbols, comma-separated, e.g. XAG, COIN_EMAMI. */
					: sprintf( __( '%s is not on the keyless board. Add a free NetArz FX API key in the settings to show it.', 'netarz-fx' ), implode( ', ', $missing ) )
			);
		}

		if ( $computed ) {
			$html .= self::computed_note();
		}
		$html .= self::updated( $data, ! empty( $opts['updated'] ) );
		$html .= self::sources_line( $shown, $data['sources'] );

		return $html;
	}

	/**
	 * Tabs (currencies, gold, coins) for the classic widget. Without
	 * JavaScript every panel is visible, one under the other.
	 *
	 * @param array $tabs array( array( label, html ) ).
	 */
	public static function tabs( array $tabs ) {
		$tabs = array_values( array_filter( $tabs ) );
		if ( 1 === count( $tabs ) ) {
			return $tabs[0][1];
		}
		if ( ! $tabs ) {
			return '';
		}
		self::$needs_tabs = true;
		++self::$count;
		$id = 'netarz-fx-tabs-' . self::$count;

		$list   = '<div class="netarz-fx-tablist" role="tablist" hidden>';
		$panels = '';
		foreach ( $tabs as $i => $tab ) {
			$list   .= '<button type="button" role="tab" id="' . esc_attr( $id . '-t' . $i ) . '" aria-controls="' . esc_attr( $id . '-p' . $i ) . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '">' . esc_html( $tab[0] ) . '</button>';
			$panels .= '<div class="netarz-fx-tabpanel" role="tabpanel" id="' . esc_attr( $id . '-p' . $i ) . '" aria-labelledby="' . esc_attr( $id . '-t' . $i ) . '">'
				. '<p class="netarz-fx-tabtitle">' . esc_html( $tab[0] ) . '</p>' . $tab[1] . '</div>';
		}
		$list .= '</div>';

		return '<div class="netarz-fx-tabs" data-netarz-tabs="1">' . $list . $panels . '</div>';
	}

	/**
	 * A line chart drawn on the server as SVG, no chart library.
	 *
	 * @param string $symbol A normalised symbol.
	 * @param string $range  One of Netarz_FX_Markets::RANGES.
	 * @param array  $opts   height (px), digits.
	 */
	public static function chart( $symbol, $range, array $opts = array() ) {
		if ( ! Netarz_FX_Client::connected() ) {
			return self::not_connected();
		}

		Netarz_FX_Render::use_digits( isset( $opts['digits'] ) ? $opts['digits'] : '' );
		$html = '<div class="netarz-fx netarz-fx-chart">' . self::chart_inner( $symbol, $range, $opts ) . Netarz_FX_Render::attribution() . '</div>';
		Netarz_FX_Render::use_digits( null );

		return $html;
	}

	private static function chart_inner( $symbol, $range, array $opts ) {
		$range = in_array( $range, Netarz_FX_Markets::RANGES, true ) ? $range : '30d';
		$data  = Netarz_FX_Markets::history( $symbol, $range );

		if ( is_wp_error( $data ) ) {
			return '<span class="netarz-fx-rate netarz-fx-unavailable">' . esc_html__( 'Chart unavailable', 'netarz-fx' ) . '</span>'
				. self::admin_note( $data->get_error_message() );
		}

		$points = $data['points'];
		if ( count( $points ) < 2 ) {
			return '<span class="netarz-fx-rate netarz-fx-unavailable">' . esc_html__( 'Chart unavailable', 'netarz-fx' ) . '</span>'
				/* translators: %s: a symbol, e.g. XAU. */
				. self::admin_note( sprintf( __( 'NetArz has no history for %s in this range yet.', 'netarz-fx' ), $symbol ) );
		}

		// The instrument row names the chart and says whether it is computed;
		// the latest answer has it when the history answer does not.
		$row = $data['row'];
		if ( ! $row ) {
			$latest = Netarz_FX_Markets::latest( array( $symbol ) );
			$row    = ! is_wp_error( $latest ) && isset( $latest['rows'][ $symbol ] ) ? $latest['rows'][ $symbol ] : null;
		}
		if ( ! $row ) {
			$row = Netarz_FX_Markets::row( array( 'symbol' => $symbol ), $symbol, array() );
		}

		// At most 240 points: enough for a smooth line, small enough for the page.
		$count = count( $points );
		if ( $count > 240 ) {
			$step    = $count / 240;
			$reduced = array();
			for ( $i = 0; $i < 240; $i++ ) {
				$reduced[] = $points[ (int) floor( $i * $step ) ];
			}
			$reduced[] = $points[ $count - 1 ];
			$points    = $reduced;
			$count     = count( $points );
		}

		$values = array();
		foreach ( $points as $point ) {
			$values[] = $point[1];
		}
		$min   = min( $values );
		$max   = max( $values );
		$first = $values[0];
		$last  = $values[ $count - 1 ];

		$w    = 600;
		$h    = 200;
		$pad  = 8;
		$line = '';
		foreach ( $points as $i => $point ) {
			$x     = $i / ( $count - 1 ) * $w;
			$y     = $max > $min ? $h - $pad - ( $point[1] - $min ) / ( $max - $min ) * ( $h - 2 * $pad ) : $h / 2;
			$line .= ( 0 === $i ? 'M' : 'L' ) . number_format( $x, 1, '.', '' ) . ',' . number_format( $y, 1, '.', '' );
		}
		$area = $line . 'L' . $w . ',' . $h . 'L0,' . $h . 'Z';

		$height = isset( $opts['height'] ) ? max( 120, min( 480, (int) $opts['height'] ) ) : 220;
		$change = $first > 0 ? ( $last - $first ) / $first * 100 : null;
		$name   = self::name( $row );
		$labels = self::range_labels();

		/* translators: 1: item name, e.g. Gold ounce, 2: period, e.g. last 30 days. */
		$aria = sprintf( __( 'Price chart of %1$s, %2$s', 'netarz-fx' ), $name, $labels[ $range ] );

		$html  = '<div class="netarz-fx-chart-head"><span class="netarz-fx-chart-name">' . esc_html( $name ) . '</span>' . ( $row['computed'] ? self::computed_badge() : '' );
		$html .= ' <span class="netarz-fx-chart-last">' . esc_html( self::price_text( $last, $row ) ) . '</span> ' . Netarz_FX_Render::change( null === $change ? null : round( $change, 2 ) );
		$html .= ' <span class="netarz-fx-chart-range">' . esc_html( $labels[ $range ] ) . '</span></div>';
		$html .= '<svg class="netarz-fx-chart-svg" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="' . esc_attr( $aria ) . '" style="height:' . esc_attr( (string) $height ) . 'px">'
			. '<path class="netarz-fx-chart-area" d="' . esc_attr( $area ) . '"/>'
			. '<path class="netarz-fx-chart-line" d="' . esc_attr( $line ) . '" vector-effect="non-scaling-stroke"/>'
			. '</svg>';
		$html .= '<div class="netarz-fx-chart-foot"><span>' . esc_html( Netarz_FX_Render::digits( wp_date( 'Y-m-d', $points[0][0] ) ) ) . '</span>';
		$html .= '<span>' . esc_html(
			sprintf(
				/* translators: 1: lowest price in the period, 2: highest price in the period. */
				__( 'Low %1$s, high %2$s', 'netarz-fx' ),
				self::price_text( $min, $row ),
				self::price_text( $max, $row )
			)
		) . '</span>';
		$html .= '<span>' . esc_html( Netarz_FX_Render::digits( wp_date( 'Y-m-d', $points[ $count - 1 ][0] ) ) ) . '</span></div>';

		if ( $row['computed'] ) {
			$html .= self::computed_note();
		}
		if ( ! empty( $data['stale_copy'] ) ) {
			$html .= self::updated(
				array(
					'meta'       => array( 'as_of' => gmdate( 'c', $points[ $count - 1 ][0] ) ),
					'rows'       => array(),
					'stale_copy' => true,
				),
				true
			);
		}
		$html .= self::sources_line( array( $row ), $data['sources'] );

		return $html;
	}

	public static function range_labels() {
		return array(
			'7d'  => __( 'last 7 days', 'netarz-fx' ),
			'30d' => __( 'last 30 days', 'netarz-fx' ),
			'90d' => __( 'last 90 days', 'netarz-fx' ),
			'6m'  => __( 'last 6 months', 'netarz-fx' ),
			'1y'  => __( 'last year', 'netarz-fx' ),
			'5y'  => __( 'last 5 years', 'netarz-fx' ),
		);
	}

	/** Enqueue the tab script once, only on pages that use tabs. */
	public static function maybe_enqueue() {
		if ( self::$needs_tabs ) {
			wp_enqueue_script( 'netarz-fx-tabs', NETARZ_FX_URL . 'assets/netarz-fx-tabs.js', array(), NETARZ_FX_VERSION, true );
		}
	}
}
