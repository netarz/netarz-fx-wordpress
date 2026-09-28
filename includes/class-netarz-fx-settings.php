<?php
/**
 * Settings > NetArz FX: API key, mode, cache, digits, attribution, and a
 * "Test connection" button that reports the IP NetArz saw when it refuses one.
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Settings {

	const OPTION = 'netarz_fx_settings';

	const DEFAULTS = array(
		'api_key'       => '',
		'mode'          => 'server',  // server | browser
		'cache_minutes' => 5,
		'digits'        => 'auto',    // auto | persian | latin
		'attribution'   => 0,         // «نرخ از نِت اَرز» link: off until the owner turns it on (WordPress.org guideline 10)
		'force_ipv4'    => 1,
		'woo_toman'     => 0,         // WooCommerce: "about X Toman" after foreign-currency prices
		'woo_field'     => 'sell',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_netarz_fx_test', array( __CLASS__, 'test_connection' ) );
		add_action( 'update_option_' . self::OPTION, array( 'Netarz_FX_Client', 'flush' ) );
	}

	public static function get( $key ) {
		$options = get_option( self::OPTION, array() );
		$options = is_array( $options ) ? array_merge( self::DEFAULTS, $options ) : self::DEFAULTS;
		return isset( $options[ $key ] ) ? $options[ $key ] : null;
	}

	public static function menu() {
		add_options_page(
			__( 'NetArz FX Rates', 'netarz-fx' ),
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

		$out['mode']          = isset( $input['mode'] ) && 'browser' === $input['mode'] ? 'browser' : 'server';
		$out['cache_minutes'] = isset( $input['cache_minutes'] ) ? max( 1, min( 60, absint( $input['cache_minutes'] ) ) ) : 5;
		$out['digits']        = isset( $input['digits'] ) && in_array( $input['digits'], array( 'auto', 'persian', 'latin' ), true ) ? $input['digits'] : 'auto';
		$out['attribution']   = empty( $input['attribution'] ) ? 0 : 1;
		$out['force_ipv4']    = empty( $input['force_ipv4'] ) ? 0 : 1;
		$out['woo_toman']     = empty( $input['woo_toman'] ) ? 0 : 1;
		$out['woo_field']     = isset( $input['woo_field'] ) && in_array( $input['woo_field'], Netarz_FX_Render::FIELDS, true ) ? $input['woo_field'] : 'sell';

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
			<h1><?php esc_html_e( 'NetArz FX Rates', 'netarz-fx' ); ?></h1>
			<?php self::print_test_result(); ?>
			<?php settings_errors( self::OPTION ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'netarz_fx' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="netarz-fx-key"><?php esc_html_e( 'API key', 'netarz-fx' ); ?></label></th>
						<td>
							<input type="password" id="netarz-fx-key" class="regular-text code" autocomplete="off" name="<?php echo esc_attr( $n ); ?>[api_key]" value="<?php echo esc_attr( $o['api_key'] ); ?>">
							<p class="description">
								<?php
								printf(
									/* translators: %s: link to the NetArz FX panel. */
									esc_html__( 'Create an app for this site\'s domain and copy its key from %s. Verify the domain in the same panel.', 'netarz-fx' ),
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
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Test connection', 'netarz-fx' ); ?></h2>
			<p><?php esc_html_e( 'Calls the API from this server with the saved key. If NetArz refuses the server\'s IP, the IP it saw is shown here so you can add exactly that one.', 'netarz-fx' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="netarz_fx_test">
				<?php wp_nonce_field( 'netarz_fx_test' ); ?>
				<?php submit_button( __( 'Test connection', 'netarz-fx' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Usage', 'netarz-fx' ); ?></h2>
			<p><code>[netarz_rate currency="usd"]</code> <code>[netarz_rate currency="eur" field="buy" show_name="1"]</code> <code>[netarz_rates currencies="usd,eur,aed,try" change="1" updated="1"]</code> <code>[netarz_convert currencies="usd,eur"]</code></p>
			<p><?php esc_html_e( 'In the block editor, search for "NetArz" to add the exchange rate, rates table or converter block.', 'netarz-fx' ); ?></p>
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

		$result = Netarz_FX_Client::request( '/me' );

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
