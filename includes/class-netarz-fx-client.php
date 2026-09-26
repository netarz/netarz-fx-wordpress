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
	}
}
