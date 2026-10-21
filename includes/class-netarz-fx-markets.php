<?php
/**
 * The NetArz markets API (/api/markets/v1): the global gold and silver
 * ounce, the computed value of 18k gold and Iranian gold coins, and the
 * currencies, with their sources.
 *
 * Without a key the plugin reads only the keyless public board (a fixed
 * set, delayed). With a key, in server mode, it reads `latest` for any
 * symbol the app's plan allows and `history` for the charts.
 *
 * Every answer is cached in a transient, and the last good answer is kept
 * so a page shows the last numbers, with their time, instead of an empty
 * box when NetArz cannot be reached.
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Markets {

	/** Transient names this class created, so flush() and uninstall can remove them. */
	const INDEX = 'netarz_fx_cache_index';

	/** Last good answers, keyed like the transients. */
	const LAST_GOOD = 'netarz_fx_last_markets';

	const GOLD   = array( 'XAU', 'GOLD18', 'MESGHAL' );
	const COINS  = array( 'COIN_EMAMI', 'COIN_BAHAR', 'COIN_HALF', 'COIN_QUARTER', 'COIN_GERAMI' );
	const METALS = array( 'XAU', 'XAG' );

	/** Asset classes whose numbers are computed by NetArz in this version, never Tehran market prices. */
	const COMPUTED_CLASSES = array( 'gold_ir', 'coin_ir' );

	const RANGES = array( '7d', '30d', '90d', '6m', '1y', '5y' );

	/** Normalises "xau, Gold18" into array( 'XAU', 'GOLD18' ). */
	public static function symbols( $value ) {
		$out = array();
		foreach ( explode( ',', (string) $value ) as $symbol ) {
			$symbol = strtoupper( preg_replace( '/[^A-Za-z0-9_]/', '', $symbol ) );
			if ( strlen( $symbol ) >= 2 && strlen( $symbol ) <= 24 ) {
				$out[] = $symbol;
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, 20 );
	}

	/** Keyed requests are made from the server only; browser mode and keyless sites read the public board. */
	public static function keyed() {
		return Netarz_FX_Client::has_key() && 'browser' !== Netarz_FX_Settings::get( 'mode' );
	}

	/**
	 * The keyless public board: array( rows => symbol => row, meta, sources, stale_copy ).
	 *
	 * @return array|WP_Error
	 */
	public static function board() {
		return self::cached(
			'board',
			Netarz_FX_Client::cache_seconds(),
			static function () {
				$body = Netarz_FX_Client::get( NETARZ_FX_MARKETS_API . '/public/board?set=default', false );
				return is_wp_error( $body ) ? $body : self::normalise( $body );
			}
		);
	}

	/**
	 * The latest rows for these symbols. Symbols NetArz did not return are
	 * simply missing from `rows` (the API leaves out what a plan or a
	 * licence does not allow, it does not refuse the request).
	 *
	 * @param string[] $symbols Normalised symbols.
	 * @return array|WP_Error
	 */
	public static function latest( array $symbols ) {
		if ( ! $symbols ) {
			return new WP_Error( 'netarz_fx_no_symbols', __( 'No symbols were given.', 'netarz-fx' ) );
		}

		if ( ! self::keyed() ) {
			$board = self::board();
			if ( is_wp_error( $board ) ) {
				return $board;
			}
			$board['rows'] = array_intersect_key( $board['rows'], array_flip( $symbols ) );
			return $board;
		}

		sort( $symbols );
		$list = implode( ',', $symbols );

		return self::cached(
			'latest|' . $list,
			Netarz_FX_Client::cache_seconds(),
			static function () use ( $list ) {
				$body = Netarz_FX_Client::get( NETARZ_FX_MARKETS_API . '/latest?symbols=' . rawurlencode( $list ), true );
				return is_wp_error( $body ) ? $body : self::normalise( $body );
			}
		);
	}

	/**
	 * Daily history for a chart: array( points => array( array( timestamp, value ) ), row, meta, sources ).
	 * Needs a key (the free plan covers the last 30 days).
	 *
	 * @param string $symbol A normalised symbol.
	 * @param string $range  One of self::RANGES.
	 * @return array|WP_Error
	 */
	public static function history( $symbol, $range ) {
		if ( ! Netarz_FX_Client::connected() ) {
			return Netarz_FX_Client::not_connected();
		}
		if ( ! self::keyed() ) {
			return new WP_Error( 'netarz_fx_chart_needs_key', __( 'Charts need a NetArz FX API key and server mode.', 'netarz-fx' ) );
		}

		$range = in_array( $range, self::RANGES, true ) ? $range : '30d';
		$days  = array(
			'7d'  => 7,
			'30d' => 30,
			'90d' => 90,
			'6m'  => 183,
			'1y'  => 365,
			'5y'  => 1826,
		);
		$to    = gmdate( 'Y-m-d' );
		$from  = gmdate( 'Y-m-d', time() - $days[ $range ] * DAY_IN_SECONDS );
		$query = array(
			'from'     => $from,
			'to'       => $to,
			'interval' => $days[ $range ] > 400 ? '1w' : '1d',
		);

		return self::cached(
			'history|' . $symbol . '|' . $range,
			12 * HOUR_IN_SECONDS,
			static function () use ( $symbol, $query ) {
				$url  = add_query_arg( $query, NETARZ_FX_MARKETS_API . '/history/' . rawurlencode( $symbol ) );
				$body = Netarz_FX_Client::get( $url, true );
				if ( is_wp_error( $body ) ) {
					return $body;
				}
				$normal           = self::normalise( isset( $body['meta'] ) ? array( 'meta' => $body['meta'] ) : array() );
				$normal['points'] = self::points( isset( $body['data'] ) ? $body['data'] : array() );
				$normal['row']    = isset( $body['data'] ) && is_array( $body['data'] ) && isset( $body['data']['instrument'] ) && is_array( $body['data']['instrument'] )
					? self::row( $body['data']['instrument'], $symbol, $normal['sources'] )
					: null;
				return $normal;
			}
		);
	}

	/**
	 * The currencies of the keyless board, shaped like the FX API's /rates so
	 * the 1.x shortcodes, the converter and WooCommerce work without a key.
	 *
	 * @return array|WP_Error
	 */
	public static function fiat_board() {
		$board = self::board();
		if ( is_wp_error( $board ) ) {
			return $board;
		}
		$rates = array();
		foreach ( $board['rows'] as $symbol => $row ) {
			if ( 'fiat' !== $row['class'] ) {
				continue;
			}
			$price            = null !== $row['price'] ? $row['price'] : null;
			$rates[ $symbol ] = array(
				'code'               => $symbol,
				'name'               => $row['name'],
				'name_en'            => $row['name_en'],
				'unit'               => $row['per'],
				'buy'                => null !== $row['buy'] ? $row['buy'] : $price,
				'sell'               => null !== $row['sell'] ? $row['sell'] : $price,
				'mid'                => null !== $row['mid'] ? $row['mid'] : $price,
				'change_24h_percent' => $row['change'],
			);
		}
		return array(
			'rates' => $rates,
			'meta'  => $board['meta'],
		);
	}

	/**
	 * Turns an API body into array( rows, meta, sources ). Accepts `data` as a
	 * list of rows, an object keyed by symbol, or groups of rows (fx, gold, ...).
	 *
	 * @param array $body Decoded JSON.
	 * @return array
	 */
	public static function normalise( array $body ) {
		if ( ! isset( $body['data'] ) && isset( $body['tabs'] ) && is_array( $body['tabs'] ) ) {
			$body = self::from_tabs( $body['tabs'] );
		}
		$meta    = isset( $body['meta'] ) && is_array( $body['meta'] ) ? $body['meta'] : array();
		$sources = array();
		if ( isset( $meta['sources'] ) && is_array( $meta['sources'] ) ) {
			foreach ( $meta['sources'] as $source ) {
				if ( is_array( $source ) && isset( $source['key'] ) ) {
					$sources[ (string) $source['key'] ] = array(
						'name' => isset( $source['name'] ) ? (string) $source['name'] : (string) $source['key'],
						'url'  => isset( $source['url'] ) ? (string) $source['url'] : '',
					);
				}
			}
		}

		$rows = array();
		foreach ( self::flatten( isset( $body['data'] ) ? $body['data'] : array() ) as $key => $raw ) {
			$row = self::row( $raw, is_string( $key ) ? $key : '', $sources );
			if ( $row ) {
				$rows[ $row['symbol'] ] = $row;
			}
		}

		return array(
			'rows'       => $rows,
			'meta'       => $meta,
			'sources'    => $sources,
			'stale_copy' => false,
		);
	}

	/**
	 * A payload made of tabs (fx, gold, coin), each with rows of symbol, name,
	 * price, change and decimals, turned into the data/meta shape. The class,
	 * quote currency and source follow from the symbol.
	 *
	 * @param array $tabs Tabs from the payload.
	 * @return array
	 */
	private static function from_tabs( array $tabs ) {
		$rows  = array();
		$meta  = array(
			'delayed_minutes' => 0,
			'stale'           => false,
		);
		$times = array();
		foreach ( $tabs as $tab ) {
			if ( ! is_array( $tab ) || ! isset( $tab['rows'] ) || ! is_array( $tab['rows'] ) ) {
				continue;
			}
			if ( ! empty( $tab['as_of'] ) ) {
				$times[] = (string) $tab['as_of'];
			}
			if ( ! empty( $tab['stale'] ) ) {
				$meta['stale'] = true;
			}
			if ( ! empty( $tab['delayed_minutes'] ) ) {
				$meta['delayed_minutes'] = max( $meta['delayed_minutes'], (int) $tab['delayed_minutes'] );
			}
			foreach ( $tab['rows'] as $row ) {
				if ( ! is_array( $row ) || empty( $row['symbol'] ) ) {
					continue;
				}
				$symbol = strtoupper( (string) $row['symbol'] );
				if ( in_array( $symbol, array( 'XAU', 'XAG' ), true ) ) {
					$class  = 'metal';
					$quote  = 'USD';
					$source = 'goldapi';
				} elseif ( 0 === strpos( $symbol, 'COIN_' ) ) {
					$class  = 'coin_ir';
					$quote  = 'IRT';
					$source = 'computed';
				} elseif ( in_array( $symbol, array( 'GOLD18', 'MESGHAL' ), true ) || 0 === strpos( $symbol, 'GOLD' ) ) {
					$class  = 'gold_ir';
					$quote  = 'IRT';
					$source = 'computed';
				} else {
					$class  = 'fiat';
					$quote  = 'IRT';
					$source = 'netarz';
				}
				$rows[] = array_merge(
					$row,
					array(
						'asset_class'    => $class,
						'quote_currency' => $quote,
						'source'         => $source,
						'name_fa'        => isset( $row['name'] ) ? $row['name'] : $symbol,
						'change_pct_24h' => isset( $row['change'] ) ? $row['change'] : null,
					)
				);
			}
		}
		if ( $times ) {
			sort( $times );
			$meta['as_of'] = $times[0];
		}
		return array(
			'data' => $rows,
			'meta' => $meta,
		);
	}

	/**
	 * @param mixed $data `data` from a response.
	 * @return array<int|string, array>
	 */
	private static function flatten( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}
		foreach ( array( 'rows', 'instruments', 'quotes', 'items' ) as $wrapper ) {
			if ( isset( $data[ $wrapper ] ) && is_array( $data[ $wrapper ] ) ) {
				return self::flatten( $data[ $wrapper ] );
			}
		}
		$out = array();
		foreach ( $data as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}
			if ( self::looks_like_row( $value ) || ( is_string( $key ) && preg_match( '/^[A-Z0-9_]{2,24}$/', $key ) ) ) {
				$out[ $key ] = $value;
				continue;
			}
			// A group such as "gold" => array( rows ).
			foreach ( $value as $inner_key => $inner ) {
				if ( is_array( $inner ) ) {
					$out[ is_string( $inner_key ) ? $inner_key : count( $out ) ] = $inner;
				}
			}
		}
		return $out;
	}

	private static function looks_like_row( array $value ) {
		return isset( $value['symbol'] ) || isset( $value['code'] );
	}

	/**
	 * One row in the plugin's own shape.
	 *
	 * @param array  $raw      A row from the API.
	 * @param string $fallback The symbol when the row is keyed by it.
	 * @param array  $sources  key => array( name, url ).
	 * @return array|null
	 */
	public static function row( array $raw, $fallback, array $sources ) {
		$symbol = self::pick( $raw, array( 'symbol', 'code' ), $fallback );
		$symbol = strtoupper( preg_replace( '/[^A-Za-z0-9_]/', '', (string) $symbol ) );
		if ( '' === $symbol ) {
			return null;
		}

		$meta  = isset( $raw['meta'] ) && is_array( $raw['meta'] ) ? $raw['meta'] : array();
		$class = (string) self::pick( $raw, array( 'asset_class', 'class' ), '' );
		if ( '' === $class && preg_match( '/^[A-Z]{3}$/', $symbol ) && 'IRT' === strtoupper( (string) self::pick( $raw, array( 'quote_currency', 'quote' ), '' ) ) && ! in_array( $symbol, array( 'XAU', 'XAG' ), true ) ) {
			$class = 'fiat';
		}

		$source = self::pick( $raw, array( 'source', 'source_key' ), '' );
		if ( is_array( $source ) ) {
			$key = isset( $source['key'] ) ? (string) $source['key'] : '';
			if ( '' !== $key && ! isset( $sources[ $key ] ) ) {
				$sources[ $key ] = array(
					'name' => isset( $source['name'] ) ? (string) $source['name'] : $key,
					'url'  => isset( $source['url'] ) ? (string) $source['url'] : '',
				);
			}
			$source = $key;
		}
		$source = (string) $source;

		$flag = self::pick( $raw, array( 'computed', 'is_computed' ), self::pick( $meta, array( 'computed' ), null ) );
		if ( null === $flag ) {
			$computed = 'computed' === $source || in_array( $class, self::COMPUTED_CLASSES, true );
		} else {
			$computed = (bool) $flag || 'computed' === $source;
		}

		$fa = 0 === strpos( determine_locale(), 'fa' );

		return array(
			'symbol'      => $symbol,
			'class'       => $class,
			'name'        => (string) self::pick( $raw, array( 'name_fa', 'name' ), $symbol ),
			'name_en'     => (string) self::pick( $raw, array( 'name_en' ), self::pick( $raw, array( 'name_fa', 'name' ), $symbol ) ),
			'unit'        => is_numeric( self::pick( $raw, array( 'unit' ), '' ) ) ? '' : (string) self::pick( $raw, array( 'unit' ), '' ),
			'per'         => max( 1, (int) self::pick( $raw, array( 'unit_amount' ), is_numeric( self::pick( $raw, array( 'unit' ), '' ) ) ? $raw['unit'] : 1 ) ),
			'quote'       => strtoupper( (string) self::pick( $raw, array( 'quote_currency', 'quote' ), 'IRT' ) ),
			'decimals'    => self::number_or_null( self::pick( $raw, array( 'decimals' ), null ) ),
			'price'       => self::number_or_null( self::pick( $raw, array( 'price', 'value', 'mid', 'sell', 'close' ), null ) ),
			'buy'         => self::number_or_null( self::pick( $raw, array( 'buy', 'bid' ), null ) ),
			'sell'        => self::number_or_null( self::pick( $raw, array( 'sell', 'ask' ), null ) ),
			'mid'         => self::number_or_null( self::pick( $raw, array( 'mid' ), null ) ),
			'change'      => self::number_or_null( self::pick( $raw, array( 'change_pct_24h', 'change_24h_percent', 'change_percent' ), null ) ),
			'as_of'       => (string) self::pick( $raw, array( 'as_of' ), '' ),
			'stale'       => (bool) self::pick( $raw, array( 'stale' ), false ),
			'pro_only'    => (bool) self::pick( $raw, array( 'pro_only' ), false ),
			'computed'    => $computed,
			'label'       => (string) self::pick( $raw, array( 'label' ), self::pick( $meta, array( $fa ? 'label_fa' : 'label_en' ), '' ) ),
			'disclaimer'  => (string) self::pick( $raw, array( 'disclaimer' ), self::pick( $meta, array( $fa ? 'disclaimer_fa' : 'disclaimer_en' ), '' ) ),
			'source'      => $source,
			'source_name' => isset( $sources[ $source ] ) ? $sources[ $source ]['name'] : (string) self::pick( $raw, array( 'attribution' ), '' ),
		);
	}

	/**
	 * History points as array( array( timestamp, value ) ), oldest first.
	 *
	 * @param mixed $data `data` of a history response.
	 * @return array
	 */
	public static function points( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}
		foreach ( array( 'points', 'history', 'rows', 'items' ) as $wrapper ) {
			if ( isset( $data[ $wrapper ] ) && is_array( $data[ $wrapper ] ) ) {
				$data = $data[ $wrapper ];
				break;
			}
		}
		$points = array();
		foreach ( $data as $key => $point ) {
			if ( is_array( $point ) ) {
				$when  = self::pick( $point, array( 'date', 'd', 'day', 't', 'time', 'as_of' ), is_string( $key ) ? $key : '' );
				$value = self::number_or_null( self::pick( $point, array( 'close', 'c', 'price', 'value', 'mid', 'sell' ), null ) );
			} else {
				$when  = $key;
				$value = self::number_or_null( $point );
			}
			$ts = is_numeric( $when ) ? (int) $when : strtotime( (string) $when );
			if ( $ts && null !== $value ) {
				$points[ $ts ] = array( $ts, $value );
			}
		}
		ksort( $points );
		return array_values( $points );
	}

	/**
	 * Cache an answer, keep the last good one, and serve it (flagged
	 * stale_copy) when NetArz cannot be reached; retry a minute later.
	 *
	 * @param string   $name  Readable cache name.
	 * @param int      $ttl   Seconds.
	 * @param callable $fetch Returns a normalised array or a WP_Error.
	 * @return array|WP_Error
	 */
	private static function cached( $name, $ttl, $fetch ) {
		if ( ! Netarz_FX_Client::connected() ) {
			return Netarz_FX_Client::not_connected();
		}

		// The key is part of the name so another key never sees this one's answer.
		$id        = 'netarz_fx_m_' . md5( $name . '|' . (string) Netarz_FX_Settings::get( 'api_key' ) . '|' . (string) Netarz_FX_Settings::get( 'mode' ) );
		$transient = get_transient( $id );
		if ( is_array( $transient ) ) {
			return $transient;
		}

		$fresh = call_user_func( $fetch );
		$last  = get_option( self::LAST_GOOD, array() );
		$last  = is_array( $last ) ? $last : array();

		if ( is_wp_error( $fresh ) ) {
			if ( isset( $last[ $id ] ) && is_array( $last[ $id ] ) ) {
				$copy               = $last[ $id ];
				$copy['stale_copy'] = true;
				self::remember( $id, $copy, MINUTE_IN_SECONDS );
				return $copy;
			}
			return $fresh;
		}

		self::remember( $id, $fresh, $ttl );
		unset( $last[ $id ] );
		$last[ $id ] = $fresh;
		// Keep the last good copy of the 30 most recent questions only.
		update_option( self::LAST_GOOD, array_slice( $last, -30, null, true ), false );

		return $fresh;
	}

	private static function remember( $id, $value, $ttl ) {
		set_transient( $id, $value, $ttl );
		$index = get_option( self::INDEX, array() );
		$index = is_array( $index ) ? $index : array();
		if ( ! in_array( $id, $index, true ) ) {
			$index[] = $id;
			update_option( self::INDEX, array_slice( $index, -100 ), false );
		}
	}

	/** Removes every cached answer (not the last good copies, which keep the site showing numbers). */
	public static function flush() {
		$index = get_option( self::INDEX, array() );
		foreach ( is_array( $index ) ? $index : array() as $id ) {
			delete_transient( (string) $id );
		}
		update_option( self::INDEX, array(), false );
	}

	/**
	 * The first key of $keys present in $data, or $fallback.
	 *
	 * @param array $data     Source array.
	 * @param array $keys     Candidate keys, in order.
	 * @param mixed $fallback Returned when none is present.
	 * @return mixed
	 */
	private static function pick( array $data, array $keys, $fallback ) {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $data ) && null !== $data[ $key ] && '' !== $data[ $key ] ) {
				return $data[ $key ];
			}
		}
		return $fallback;
	}

	private static function number_or_null( $value ) {
		return is_numeric( $value ) ? (float) $value : null;
	}
}
