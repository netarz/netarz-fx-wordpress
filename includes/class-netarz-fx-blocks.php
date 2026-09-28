<?php
/**
 * Three dynamic blocks: netarz-fx/rate, netarz-fx/rates and netarz-fx/convert.
 *
 * Built without a build step: block.json describes each block, the editor
 * script (assets/blocks.js) uses the wp.* globals and ServerSideRender, and
 * the front end is drawn by the same renderer as the shortcodes.
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Blocks {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'netarz-fx-blocks',
			NETARZ_FX_URL . 'assets/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
			NETARZ_FX_VERSION,
			true
		);
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );

		register_block_type( NETARZ_FX_DIR . 'blocks/rate', array( 'render_callback' => array( __CLASS__, 'render_rate' ) ) );
		register_block_type( NETARZ_FX_DIR . 'blocks/rates', array( 'render_callback' => array( __CLASS__, 'render_rates' ) ) );
		register_block_type( NETARZ_FX_DIR . 'blocks/convert', array( 'render_callback' => array( __CLASS__, 'render_convert' ) ) );
	}

	/**
	 * Labels for the editor (translated here, so the one .po file covers them)
	 * and, in server mode, the currencies on the cached board for the picker.
	 */
	public static function editor_data() {
		$browser    = 'browser' === Netarz_FX_Settings::get( 'mode' );
		$currencies = array();
		if ( ! $browser && '' !== (string) Netarz_FX_Settings::get( 'api_key' ) ) {
			$board = Netarz_FX_Client::board();
			if ( ! is_wp_error( $board ) ) {
				foreach ( $board['rates'] as $code => $row ) {
					$currencies[] = array(
						'value' => (string) $code,
						'label' => Netarz_FX_Render::currency_name( $row ) . ' (' . $code . ')',
					);
				}
			}
		}

		$data = array(
			'browserMode' => $browser ? 1 : 0,
			'hasKey'      => '' !== (string) Netarz_FX_Settings::get( 'api_key' ) ? 1 : 0,
			'settingsUrl' => admin_url( 'options-general.php?page=netarz-fx' ),
			'currencies'  => $currencies,
			'l'           => array(
				'settings'     => __( 'Settings', 'netarz-fx' ),
				'currency'     => __( 'Currency', 'netarz-fx' ),
				'currencies'   => __( 'Currencies (comma-separated codes)', 'netarz-fx' ),
				'codesHelp'    => __( 'Three-letter codes, for example USD,EUR,AED,TRY.', 'netarz-fx' ),
				'rate'         => __( 'Rate', 'netarz-fx' ),
				'buy'          => __( 'Buy', 'netarz-fx' ),
				'sell'         => __( 'Sell', 'netarz-fx' ),
				'mid'          => __( 'Average', 'netarz-fx' ),
				'showName'     => __( 'Show the currency name', 'netarz-fx' ),
				'showChange'   => __( 'Show the change since yesterday', 'netarz-fx' ),
				'showUpdated'  => __( 'Show when the rates were updated', 'netarz-fx' ),
				'amount'       => __( 'Starting amount', 'netarz-fx' ),
				'digits'       => __( 'Digits', 'netarz-fx' ),
				'digitsAuto'   => __( 'As in the plugin settings', 'netarz-fx' ),
				'persian'      => __( 'Persian digits', 'netarz-fx' ),
				'latin'        => __( 'Latin digits', 'netarz-fx' ),
				'browserNote'  => __( 'Rates load in the visitor\'s browser (browser mode), so this preview shows placeholders. The published page shows the numbers.', 'netarz-fx' ),
				'noKey'        => __( 'Add your NetArz FX API key in Settings > NetArz FX to see rates.', 'netarz-fx' ),
				'openSettings' => __( 'Open settings', 'netarz-fx' ),
			),
		);

		wp_add_inline_script( 'netarz-fx-blocks', 'var netarzFxBlocks = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	private static function wrap( $html ) {
		if ( '' === $html ) {
			return '';
		}
		return '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
	}

	private static function digits( array $attributes ) {
		return isset( $attributes['digits'] ) ? (string) $attributes['digits'] : '';
	}

	public static function render_rate( $attributes ) {
		$codes = Netarz_FX_Render::codes( isset( $attributes['currency'] ) ? $attributes['currency'] : 'USD' );
		if ( ! $codes ) {
			return '';
		}
		wp_enqueue_style( 'netarz-fx' );

		Netarz_FX_Render::use_digits( self::digits( $attributes ) );
		$html = Netarz_FX_Render::inline( $codes[0], isset( $attributes['field'] ) ? $attributes['field'] : 'sell', ! empty( $attributes['showName'] ) )
			. Netarz_FX_Render::attribution();
		Netarz_FX_Render::use_digits( null );

		return self::wrap( $html );
	}

	public static function render_rates( $attributes ) {
		$codes = Netarz_FX_Render::codes( isset( $attributes['currencies'] ) ? $attributes['currencies'] : '' );
		if ( ! $codes ) {
			return '';
		}
		wp_enqueue_style( 'netarz-fx' );

		$fields = array();
		foreach ( array(
			'buy'  => 'showBuy',
			'sell' => 'showSell',
			'mid'  => 'showMid',
		) as $field => $attribute ) {
			if ( ! empty( $attributes[ $attribute ] ) ) {
				$fields[] = $field;
			}
		}

		return self::wrap(
			Netarz_FX_Render::board_html(
				$codes,
				$fields,
				array(
					'change'  => ! empty( $attributes['showChange'] ),
					'updated' => ! empty( $attributes['showUpdated'] ),
					'digits'  => self::digits( $attributes ),
				)
			)
		);
	}

	public static function render_convert( $attributes ) {
		$codes = Netarz_FX_Render::codes( isset( $attributes['currencies'] ) ? $attributes['currencies'] : '' );
		if ( ! $codes ) {
			return '';
		}
		wp_enqueue_style( 'netarz-fx' );

		return self::wrap(
			Netarz_FX_Render::converter(
				$codes,
				isset( $attributes['field'] ) ? $attributes['field'] : 'sell',
				isset( $attributes['amount'] ) ? (float) $attributes['amount'] : 1,
				array(
					'updated' => ! empty( $attributes['showUpdated'] ),
					'digits'  => self::digits( $attributes ),
				)
			)
		);
	}
}
