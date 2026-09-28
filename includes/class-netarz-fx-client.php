<?php
/**
 * Talks to the NetArz FX API and caches the answer.
 *
 * One request fetches the whole rate board; every shortcode and widget on
 * the site reads from that one cached copy, so the daily quota is spent at
 * most once per cache period, whatever the traffic.
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Client {

	const TRANSIENT = 'netarz_fx_board';
	const LAST_GOOD = 'netarz_fx_last_board';
	const CATALOGUE = 'netarz_fx_catalogue';

	public static function init() {
		// Keep the source address on IPv4 (optional, on by default) so it matches
		// the IPv4 address allow-listed on the app. netarz.ir also has IPv6, and a
		// dual-stack host would otherwise often connect over IPv6.
		add_action( 'http_api_curl', array( __CLASS__, 'force_ipv4' ), 10, 3 );
	}

	/**
	 * @param resource|\CurlHandle $handle
	 * @param array                $args
	 * @param string               $url
	 */
	public static function force_ipv4( $handle, $args, $url ) {
		if ( 0 !== strpos( (string) $url, NETARZ_FX_API ) ) {
			return;
		}
		if ( Netarz_FX_Settings::get( 'force_ipv4' ) && defined( 'CURLOPT_IPRESOLVE' ) ) {
			curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
		}
	}

	/**
	 * The full board keyed by currency code, or a WP_Error when nothing is available.
	 *
	 * @return array{rates: array<string, array>, meta: array}|WP_Error
	 */
	public static function board() {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = self::request( '/rates' );

		if ( is_wp_error( $response ) ) {
			// Serve the last good board rather than an empty box, and retry in a minute.
			$last = get_option( self::LAST_GOOD );
			if ( is_array( $last ) ) {
				set_transient( self::TRANSIENT, $last, MINUTE_IN_SECONDS );
				return $last;
			}
			return $response;
		}

		$board = array(
			'rates' => array(),
			'meta'  => isset( $response['meta'] ) && is_array( $response['meta'] ) ? $response['meta'] : array(),
		);
		foreach ( (array) $response['data'] as $row ) {
			if ( isset( $row['code'] ) ) {
				$board['rates'][ strtoupper( $row['code'] ) ] = $row;
			}
		}

		set_transient( self::TRANSIENT, $board, self::cache_seconds() );
		update_option( self::LAST_GOOD, $board, false );

		return $board;
	}

	/**
	 * One currency row, or null.
	 */
	public static function rate( $code ) {
		$board = self::board();
		if ( is_wp_error( $board ) ) {
			return null;
		}
		$code = strtoupper( (string) $code );
		return isset( $board['rates'][ $code ] ) ? $board['rates'][ $code ] : null;
	}

	/**
	 * The board's meta block (as_of, delayed_minutes, plan, ...), or an empty array.
	 *
	 * @return array
	 */
	public static function meta() {
		$board = self::board();
		return is_wp_error( $board ) || ! isset( $board['meta'] ) ? array() : (array) $board['meta'];
	}

	/**
	 * True when NetArz serves this currency to Pro apps only (GET /currencies
	 * flags it `pro_only`). The free plan's board simply leaves such a code
	 * out, so this is how the plugin tells "Pro only" from "not a currency".
	 *
	 * The catalogue has no prices and rarely changes: it is fetched at most
	 * once a day, and only when a page asks for a code the board lacks.
	 */
	public static function is_pro_only( $code ) {
		$catalogue = get_transient( self::CATALOGUE );
		if ( ! is_array( $catalogue ) ) {
			$catalogue = array();
			$response  = self::request( '/currencies' );
			if ( ! is_wp_error( $response ) && isset( $response['data'] ) && is_array( $response['data'] ) ) {
				foreach ( $response['data'] as $row ) {
					if ( isset( $row['code'] ) ) {
						$catalogue[ strtoupper( (string) $row['code'] ) ] = ! empty( $row['pro_only'] );
					}
				}
				set_transient( self::CATALOGUE, $catalogue, DAY_IN_SECONDS );
			} else {
				// Remember the failure for an hour instead of asking again on every view.
				set_transient( self::CATALOGUE, $catalogue, HOUR_IN_SECONDS );
			}
		}
		$code = strtoupper( (string) $code );
		return ! empty( $catalogue[ $code ] );
	}

	/**
	 * GET a path on the API. Returns the decoded body or a WP_Error whose data
	 * carries the API's error object (code, message, and `ip` for IP refusals).
	 *
	 * The request deliberately sends no Origin or Referer header: server calls
	 * are matched by IP, as the API expects.
	 *
	 * @return array|WP_Error
	 */
	public static function request( $path ) {
		$key = (string) Netarz_FX_Settings::get( 'api_key' );
		if ( '' === $key ) {
			return new WP_Error( 'netarz_fx_no_key', __( 'No API key is set.', 'netarz-fx' ) );
		}

		$response = wp_remote_get(
			NETARZ_FX_API . $path,
			array(
				'timeout'    => 10,
				'user-agent' => 'NetArz-FX-WordPress/' . NETARZ_FX_VERSION,
				'headers'    => array(
					'Authorization' => 'Bearer ' . $key,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status || ! is_array( $body ) ) {
			$error = is_array( $body ) && isset( $body['error'] ) && is_array( $body['error'] ) ? $body['error'] : array();
			$code  = isset( $error['code'] ) ? (string) $error['code'] : 'http_' . $status;
			return new WP_Error( 'netarz_fx_' . $code, isset( $error['message'] ) ? (string) $error['message'] : $code, $error );
		}

		return $body;
	}

	public static function cache_seconds() {
		$minutes = (int) Netarz_FX_Settings::get( 'cache_minutes' );
		return max( 1, min( 60, $minutes ) ) * MINUTE_IN_SECONDS;
	}

	public static function flush() {
		delete_transient( self::TRANSIENT );
		delete_transient( self::CATALOGUE );
	}
}
