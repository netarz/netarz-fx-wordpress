<?php
/**
 * Settings > NetArz FX: the consent to connect, API key, mode, cache, digits,
 * attribution, and a "Test connection" button that reports the IP NetArz saw
 * when it refuses one.
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Settings {

	const OPTION = 'netarz_fx_settings';

	/** Stores the plugin version whose upgrade steps have run. */
	const VERSION_OPTION = 'netarz_fx_version';

	const DEFAULTS = array(
		'connect'         => 0,         // No request to NetArz until an administrator turns this on (WordPress.org guideline 7).
		'api_key'         => '',
		'mode'            => 'server',  // server | browser.
		'cache_minutes'   => 5,
		'digits'          => 'auto',    // auto | persian | latin.
		'attribution'     => 0,         // «نرخ از نِت اَرز» link: off until the owner turns it on (WordPress.org guideline 10).
		'force_ipv4'      => 1,
		'woo_toman'       => 0,         // WooCommerce: "about X Toman" after foreign-currency prices.
		'woo_field'       => 'sell',
		'woo_pricing'     => 0,         // WooCommerce: reprice products priced in a foreign currency.
		'woo_price_field' => 'sell',
		'woo_fee_percent' => 0,
		'woo_fee_fixed'   => 0,         // Toman per item.
		'woo_rounding'    => 1000,      // Toman.
		'woo_round_mode'  => 'up',      // up | nearest.
		'woo_interval'    => 3,         // Hours.
		'woo_max_jump'    => 20,        // Percent; 0 = no guard.
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_netarz_fx_test', array( __CLASS__, 'test_connection' ) );
		add_action( 'update_option_' . self::OPTION, array( 'Netarz_FX_Client', 'flush' ) );
		add_action( 'init', array( __CLASS__, 'upgrade' ), 5 );
		add_action( 'admin_notices', array( __CLASS__, 'connect_notice' ) );
	}

	/**
	 * 1.x had no consent switch: a site that had saved an API key had already
	 * chosen to use NetArz, so it keeps working after the update. A site
	 * without a key stays disconnected until an administrator says yes.
	 */
	public static function upgrade() {
		if ( NETARZ_FX_VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}
		$options = get_option( self::OPTION, null );
		if ( is_array( $options ) && ! isset( $options['connect'] ) ) {
			$options['connect'] = ! empty( $options['api_key'] ) ? 1 : 0;
			update_option( self::OPTION, $options );
		}
		update_option( self::VERSION_OPTION, NETARZ_FX_VERSION );
	}

	/** On the Plugins screen only: one line saying the plugin waits for the administrator's consent. */
	public static function connect_notice() {
		if ( ! current_user_can( 'manage_options' ) || self::get( 'connect' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}
		echo '<div class="notice notice-info"><p>';
		printf(
			/* translators: %s: link to the plugin settings. */
			esc_html__( 'NetArz Currency & Gold Rates does not contact NetArz until you allow it. %s', 'netarz-fx' ),
			'<a href="' . esc_url( admin_url( 'options-general.php?page=netarz-fx' ) ) . '">' . esc_html__( 'Open settings', 'netarz-fx' ) . '</a>'
		);
		echo '</p></div>';
	}

	public static function get( $key ) {
		$options = get_option( self::OPTION, array() );
		$options = is_array( $options ) ? array_merge( self::DEFAULTS, $options ) : self::DEFAULTS;
		return isset( $options[ $key ] ) ? $options[ $key ] : null;
	}

	public static function menu() {
		add_options_page(
			__( 'NetArz Currency & Gold Rates', 'netarz-fx' ),
			__( 'NetArz FX', 'netarz-fx' ),
			'manage_options',
			'netarz-fx',
			array( __CLASS__, 'page' )
		);
	}

	public static function register() {
		register_setting(
			'netarz_fx',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::DEFAULTS,
			)
		);
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = self::DEFAULTS;

		$key = isset( $input['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $input['api_key'] ) ) ) : '';
		if ( '' !== $key && 0 !== strpos( $key, 'fx-ntz-v1-' ) ) {
			add_settings_error( self::OPTION, 'netarz_fx_key', __( 'The key should start with fx-ntz-v1-. Copy it again from the NetArz panel.', 'netarz-fx' ) );
		}
		$out['api_key'] = $key;

		$out['connect']       = empty( $input['connect'] ) ? 0 : 1;
		$out['mode']          = isset( $input['mode'] ) && 'browser' === $input['mode'] ? 'browser' : 'server';
		$out['cache_minutes'] = isset( $input['cache_minutes'] ) ? max( 1, min( 60, absint( $input['cache_minutes'] ) ) ) : 5;
		$out['digits']        = isset( $input['digits'] ) && in_array( $input['digits'], array( 'auto', 'persian', 'latin' ), true ) ? $input['digits'] : 'auto';
		$out['attribution']   = empty( $input['attribution'] ) ? 0 : 1;
		$out['force_ipv4']    = empty( $input['force_ipv4'] ) ? 0 : 1;
		$out['woo_toman']     = empty( $input['woo_toman'] ) ? 0 : 1;
		$out['woo_field']     = isset( $input['woo_field'] ) && in_array( $input['woo_field'], Netarz_FX_Render::FIELDS, true ) ? $input['woo_field'] : 'sell';

		$out['woo_pricing']     = empty( $input['woo_pricing'] ) ? 0 : 1;
		$out['woo_price_field'] = isset( $input['woo_price_field'] ) && in_array( $input['woo_price_field'], Netarz_FX_Render::FIELDS, true ) ? $input['woo_price_field'] : 'sell';
		$out['woo_fee_percent'] = isset( $input['woo_fee_percent'] ) && is_numeric( $input['woo_fee_percent'] ) ? max( -90, min( 1000, round( (float) $input['woo_fee_percent'], 2 ) ) ) : 0;
		$out['woo_fee_fixed']   = isset( $input['woo_fee_fixed'] ) ? absint( $input['woo_fee_fixed'] ) : 0;
		$out['woo_rounding']    = isset( $input['woo_rounding'] ) && in_array( (int) $input['woo_rounding'], Netarz_FX_Woo_Pricing::ROUNDINGS, true ) ? (int) $input['woo_rounding'] : 1000;
		$out['woo_round_mode']  = isset( $input['woo_round_mode'] ) && 'nearest' === $input['woo_round_mode'] ? 'nearest' : 'up';
		$out['woo_interval']    = isset( $input['woo_interval'] ) && in_array( (int) $input['woo_interval'], Netarz_FX_Woo_Pricing::INTERVALS, true ) ? (int) $input['woo_interval'] : 3;
		$out['woo_max_jump']    = isset( $input['woo_max_jump'] ) ? min( 100, absint( $input['woo_max_jump'] ) ) : 20;

		return $out;
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$o = array_merge( self::DEFAULTS, (array) get_option( self::OPTION, array() ) );
		$n = self::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NetArz Currency & Gold Rates', 'netarz-fx' ); ?></h1>
			<?php self::print_test_result(); ?>
			<?php settings_errors( self::OPTION ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'netarz_fx' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Connection to NetArz', 'netarz-fx' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[connect]" value="1" <?php checked( (int) $o['connect'], 1 ); ?>> <strong><?php esc_html_e( 'Allow this site to fetch rates from netarz.ir', 'netarz-fx' ); ?></strong></label>
							<p class="description">
								<?php esc_html_e( 'Off by default. While it is off the plugin sends nothing anywhere and the shortcodes, blocks and widgets show nothing to visitors.', 'netarz-fx' ); ?>
								<?php esc_html_e( 'When it is on, this site requests rates from netarz.ir (and, in browser mode, each visitor\'s browser does). NetArz sees the requesting IP address and, with a key, your key.', 'netarz-fx' ); ?>
								<?php
								printf(
									/* translators: %s: link to the NetArz terms and privacy page. */
									esc_html__( 'Terms of use and privacy: %s', 'netarz-fx' ),
									'<a href="' . esc_url( 'https://netarz.ir/terms' ) . '" target="_blank" rel="noopener">netarz.ir/terms</a>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="netarz-fx-key"><?php esc_html_e( 'API key', 'netarz-fx' ); ?></label></th>
						<td>
							<input type="password" id="netarz-fx-key" class="regular-text code" autocomplete="off" name="<?php echo esc_attr( $n ); ?>[api_key]" value="<?php echo esc_attr( $o['api_key'] ); ?>">
							<p class="description">
								<?php
								printf(
									/* translators: %s: link to the NetArz FX panel. */
									esc_html__( 'Optional. Without a key the plugin reads the keyless public board: a fixed set of currencies, gold and coins, a few minutes behind. With a free key you choose any currency or symbol of your plan and can draw charts. Create an app for this site\'s domain and copy its key from %s, then verify the domain in the same panel.', 'netarz-fx' ),
									'<a href="' . esc_url( Netarz_FX_Render::url( '/fx', 'settings-key' ) ) . '" target="_blank" rel="noopener">netarz.ir/fx</a>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Where rates are fetched', 'netarz-fx' ); ?></th>
						<td>
							<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[mode]" value="server" <?php checked( $o['mode'], 'server' ); ?>> <?php esc_html_e( 'From this server (recommended). Add the server\'s outgoing IP to the app\'s allowed IPs.', 'netarz-fx' ); ?></label><br>
							<label><input type="radio" name="<?php echo esc_attr( $n ); ?>[mode]" value="browser" <?php checked( $o['mode'], 'browser' ); ?>> <?php esc_html_e( 'From the visitor\'s browser. For hosts without a fixed IP. The key appears in the page source but only works on your verified domain; each visitor uses some of the app\'s daily quota.', 'netarz-fx' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="netarz-fx-cache"><?php esc_html_e( 'Cache (minutes)', 'netarz-fx' ); ?></label></th>
						<td>
							<input type="number" min="1" max="60" id="netarz-fx-cache" class="small-text" name="<?php echo esc_attr( $n ); ?>[cache_minutes]" value="<?php echo esc_attr( (string) $o['cache_minutes'] ); ?>">
							<p class="description"><?php esc_html_e( 'Rates change every few minutes. One request refreshes every rate on the site.', 'netarz-fx' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="netarz-fx-digits"><?php esc_html_e( 'Digits', 'netarz-fx' ); ?></label></th>
						<td>
							<select id="netarz-fx-digits" name="<?php echo esc_attr( $n ); ?>[digits]">
								<option value="auto" <?php selected( $o['digits'], 'auto' ); ?>><?php esc_html_e( 'Follow the site language', 'netarz-fx' ); ?></option>
								<option value="persian" <?php selected( $o['digits'], 'persian' ); ?>><?php esc_html_e( 'Persian digits', 'netarz-fx' ); ?></option>
								<option value="latin" <?php selected( $o['digits'], 'latin' ); ?>><?php esc_html_e( 'Latin digits', 'netarz-fx' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Credit link', 'netarz-fx' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[attribution]" value="1" <?php checked( (int) $o['attribution'], 1 ); ?>> <?php esc_html_e( 'Show a small "Rates by NetArz" link under the rates (once per page).', 'netarz-fx' ); ?></label>
							<p class="description"><?php esc_html_e( 'Optional, off by default. Turn it on if you want readers to see where the numbers come from.', 'netarz-fx' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Network', 'netarz-fx' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[force_ipv4]" value="1" <?php checked( (int) $o['force_ipv4'], 1 ); ?>> <?php esc_html_e( 'Connect over IPv4, so the IP NetArz sees is the IPv4 address you allow-listed.', 'netarz-fx' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WooCommerce', 'netarz-fx' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[woo_toman]" value="1" <?php checked( (int) $o['woo_toman'], 1 ); ?>> <?php esc_html_e( 'Show the Toman equivalent after product prices', 'netarz-fx' ); ?></label>
							<select name="<?php echo esc_attr( $n ); ?>[woo_field]" aria-label="<?php esc_attr_e( 'Rate', 'netarz-fx' ); ?>">
								<option value="sell" <?php selected( $o['woo_field'], 'sell' ); ?>><?php esc_html_e( 'Sell', 'netarz-fx' ); ?></option>
								<option value="buy" <?php selected( $o['woo_field'], 'buy' ); ?>><?php esc_html_e( 'Buy', 'netarz-fx' ); ?></option>
								<option value="mid" <?php selected( $o['woo_field'], 'mid' ); ?>><?php esc_html_e( 'Average', 'netarz-fx' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'For a store priced in a foreign currency such as USD: each price gets "about ... Toman" after it. Needs WooCommerce and works in server mode only; it uses the cached rates, so it adds no requests.', 'netarz-fx' ); ?></p>
						</td>
					</tr>
					<?php Netarz_FX_Woo_Pricing::settings_rows( $o, $n ); ?>
				</table>
				<?php submit_button(); ?>
			</form>

			<?php Netarz_FX_Woo_Pricing::status_box(); ?>

			<h2><?php esc_html_e( 'Test connection', 'netarz-fx' ); ?></h2>
			<p><?php esc_html_e( 'Calls NetArz from this server with the saved settings (the connection must be on). With a key, if NetArz refuses the server\'s IP, the IP it saw is shown here so you can add exactly that one.', 'netarz-fx' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="netarz_fx_test">
				<?php wp_nonce_field( 'netarz_fx_test' ); ?>
				<?php submit_button( __( 'Test connection', 'netarz-fx' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Usage', 'netarz-fx' ); ?></h2>
			<p><code>[netarz_rate currency="usd"]</code> <code>[netarz_rate currency="eur" field="buy" show_name="1"]</code> <code>[netarz_rates currencies="usd,eur,aed,try" change="1" updated="1"]</code> <code>[netarz_convert currencies="usd,eur"]</code></p>
			<p><code>[netarz_gold]</code> <code>[netarz_coins]</code> <code>[netarz_metals]</code> <code>[netarz_market symbols="XAU,GOLD18,COIN_EMAMI"]</code> <code>[netarz_chart symbol="XAU" range="30d"]</code></p>
			<p><?php esc_html_e( 'Symbols: XAU (gold ounce), XAG (silver ounce), GOLD18 (18k gold per gram), MESGHAL (18k gold per mesghal), COIN_EMAMI, COIN_BAHAR, COIN_HALF, COIN_QUARTER, COIN_GERAMI, and currency codes such as USD. Chart ranges: 7d, 30d, 90d, 6m, 1y, 5y (the free plan covers 30 days).', 'netarz-fx' ); ?></p>
			<p><?php esc_html_e( '18k gold, the mesghal and the coins are a computed value from the global gold ounce and the NetArz dollar rate, not the Tehran market price. The plugin always labels them so.', 'netarz-fx' ); ?></p>
			<p><?php esc_html_e( 'In the block editor, search for "NetArz" to add the exchange rate, rates table, converter, gold and coins, or chart block.', 'netarz-fx' ); ?></p>
			<p>
				<?php
				printf(
					/* translators: %s: link to the NetArz FX documentation. */
					esc_html__( 'Fields: buy, sell, mid. Documentation: %s', 'netarz-fx' ),
					'<a href="' . esc_url( Netarz_FX_Render::url( '/docs/fx', 'settings-usage' ) ) . '" target="_blank" rel="noopener">netarz.ir/docs/fx</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	public static function test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'netarz-fx' ) );
		}
		check_admin_referer( 'netarz_fx_test' );

		if ( Netarz_FX_Client::connected() && ! Netarz_FX_Client::has_key() ) {
			Netarz_FX_Client::flush();
			$board = Netarz_FX_Markets::board();
			if ( is_wp_error( $board ) || ! empty( $board['stale_copy'] ) ) {
				$store = array(
					'ok'      => false,
					'code'    => is_wp_error( $board ) ? $board->get_error_code() : 'unreachable',
					'message' => is_wp_error( $board ) ? $board->get_error_message() : __( 'NetArz could not be reached; the last saved rates are shown.', 'netarz-fx' ),
					'ip'      => '',
				);
			} else {
				$store = array(
					'ok'      => true,
					'keyless' => true,
					'count'   => count( $board['rows'] ),
				);
			}
			set_transient( 'netarz_fx_test_' . get_current_user_id(), $store, 5 * MINUTE_IN_SECONDS );
			wp_safe_redirect( admin_url( 'options-general.php?page=netarz-fx' ) );
			exit;
		}

		// Not connected: nothing is sent, and the error says why.
		$result = Netarz_FX_Client::connected() ? Netarz_FX_Client::request( '/me' ) : Netarz_FX_Client::not_connected();

		if ( is_wp_error( $result ) ) {
			$data  = $result->get_error_data();
			$store = array(
				'ok'      => false,
				'code'    => is_array( $data ) && isset( $data['code'] ) ? (string) $data['code'] : $result->get_error_code(),
				'message' => $result->get_error_message(),
				'ip'      => is_array( $data ) && isset( $data['ip'] ) ? (string) $data['ip'] : '',
			);
		} else {
			$me    = isset( $result['data'] ) ? $result['data'] : array();
			$store = array(
				'ok'        => true,
				'app'       => isset( $me['app']['name'] ) ? (string) $me['app']['name'] : '',
				'plan'      => isset( $me['plan']['name'] ) ? (string) $me['plan']['name'] : '',
				'remaining' => isset( $me['limits']['remaining_today'] ) ? (int) $me['limits']['remaining_today'] : 0,
			);
			Netarz_FX_Client::flush();
		}

		set_transient( 'netarz_fx_test_' . get_current_user_id(), $store, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'options-general.php?page=netarz-fx' ) );
		exit;
	}

	private static function print_test_result() {
		$key    = 'netarz_fx_test_' . get_current_user_id();
		$result = get_transient( $key );
		if ( ! is_array( $result ) ) {
			return;
		}
		delete_transient( $key );

		if ( ! empty( $result['ok'] ) && ! empty( $result['keyless'] ) ) {
			echo '<div class="notice notice-success"><p>';
			printf(
				/* translators: %s: number of rows on the keyless public board. */
				esc_html__( 'Connected without a key. The public board has %s items.', 'netarz-fx' ),
				esc_html( number_format_i18n( (int) $result['count'] ) )
			);
			echo '</p></div>';
			return;
		}

		if ( ! empty( $result['ok'] ) ) {
			echo '<div class="notice notice-success"><p>';
			printf(
				/* translators: 1: app name, 2: plan name (free or pro), 3: requests left today. */
				esc_html__( 'Connected. App: %1$s, plan: %2$s, requests left today: %3$s.', 'netarz-fx' ),
				esc_html( $result['app'] ),
				esc_html( $result['plan'] ),
				esc_html( number_format_i18n( $result['remaining'] ) )
			);
			echo '</p></div>';
			return;
		}

		echo '<div class="notice notice-error"><p><strong>' . esc_html( $result['code'] ) . '</strong>: ' . esc_html( $result['message'] ) . '</p>';
		if ( '' !== $result['ip'] ) {
			echo '<p>';
			printf(
				/* translators: %s: an IP address. */
				esc_html__( 'NetArz saw this server as %s. Add exactly this address to the app\'s allowed IPs at netarz.ir/fx, or switch to browser mode.', 'netarz-fx' ),
				'<code>' . esc_html( $result['ip'] ) . '</code>'
			);
			echo '</p>';
		}
		echo '</div>';
	}
}
