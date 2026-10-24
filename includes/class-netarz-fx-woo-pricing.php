<?php
/**
 * WooCommerce: products priced in a foreign currency, sold in Toman or Rial.
 *
 * The store owner gives a product (or a variation) a currency, a regular
 * price and an optional sale price in that currency. The plugin multiplies
 * them by the NetArz rate, adds the store's fee (a percentage, a fixed Toman
 * amount, or both), rounds, and writes the result into the product's normal
 * WooCommerce prices. A scheduled event repeats this every few hours, so the
 * shop follows the rate without anyone editing a product.
 *
 * Off by default (Settings > NetArz FX). One cached board serves every
 * product, so a run costs one API request whatever the catalogue size.
 *
 * @package NetArzFX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Netarz_FX_Woo_Pricing {

	const HOOK = 'netarz_fx_woo_reprice';

	/** Summary of the last run: time, counters, skipped product ids, error. */
	const LAST_RUN = 'netarz_fx_woo_last_run';

	const META_CURRENCY = '_netarz_fx_currency';
	const META_REGULAR  = '_netarz_fx_regular';
	const META_SALE     = '_netarz_fx_sale';
	const META_FEE      = '_netarz_fx_fee';
	const META_RATE     = '_netarz_fx_rate';
	const META_UPDATED  = '_netarz_fx_updated';
	const META_NOTE     = '_netarz_fx_note';

	/** Hours between runs the settings offer. */
	const INTERVALS = array( 1, 3, 6, 12, 24 );

	/** Rounding steps in Toman. */
	const ROUNDINGS = array( 1, 10, 100, 1000, 10000, 100000 );

	/** Products per batch; the rest continue in a follow-up event a minute later. */
	const BATCH = 100;

	/** Units of each Iranian store currency WooCommerce knows in one Toman. */
	const STORE_UNITS = array(
		'IRT'  => 1,
		'IRR'  => 10,
		'IRHT' => 0.001,
		'IRHR' => 0.01,
	);

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- 1 to 24 hours.
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ), 20 );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( 'admin_post_netarz_fx_woo_reprice', array( __CLASS__, 'reprice_now' ) );
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_compatibility' ) );

		add_action( 'woocommerce_product_options_pricing', array( __CLASS__, 'product_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_product' ) );
		add_action( 'woocommerce_variation_options_pricing', array( __CLASS__, 'variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( __CLASS__, 'save_variation' ), 10, 2 );
	}

	/** Product data lives in posts; the plugin touches no orders, so it is compatible with HPOS. */
	public static function declare_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', NETARZ_FX_FILE, true );
		}
	}

	public static function schedules( $schedules ) {
		foreach ( self::INTERVALS as $hours ) {
			$schedules[ 'netarz_fx_woo_' . $hours . 'h' ] = array(
				'interval' => $hours * HOUR_IN_SECONDS,
				// Some plugins read the schedules before init, when translations may not load yet.
				/* translators: %d: number of hours. */
				'display'  => did_action( 'init' ) ? sprintf( _n( 'Every %d hour (NetArz FX)', 'Every %d hours (NetArz FX)', $hours, 'netarz-fx' ), $hours ) : 'NetArz FX ' . $hours . 'h',
			);
		}
		return $schedules;
	}

	public static function interval() {
		$hours = (int) Netarz_FX_Settings::get( 'woo_interval' );
		return in_array( $hours, self::INTERVALS, true ) ? $hours : 3;
	}

	/** Store-currency units in one Toman, or null when the store is not priced in Toman or Rial. */
	public static function store_unit() {
		if ( ! function_exists( 'get_woocommerce_currency' ) ) {
			return null;
		}
		$code = strtoupper( (string) get_woocommerce_currency() );
		return isset( self::STORE_UNITS[ $code ] ) ? (float) self::STORE_UNITS[ $code ] : null;
	}

	/**
	 * True when automatic prices are switched on and can run: WooCommerce in
	 * Toman or Rial, the connection on, and the server allowed to call NetArz.
	 */
	public static function available() {
		if ( ! Netarz_FX_Settings::get( 'woo_pricing' ) || ! Netarz_FX_Client::connected() || ! class_exists( 'WooCommerce' ) ) {
			return false;
		}
		// Browser mode with a key means the server may not call NetArz; without a key the server reads the keyless board.
		if ( 'browser' === Netarz_FX_Settings::get( 'mode' ) && Netarz_FX_Client::has_key() ) {
			return false;
		}
		return null !== self::store_unit();
	}

	/** Keeps exactly one recurring event at the chosen interval while the feature is available, and none otherwise. */
	public static function maybe_schedule() {
		$want = self::available() ? 'netarz_fx_woo_' . self::interval() . 'h' : false;
		$have = wp_get_schedule( self::HOOK );
		if ( $want === $have ) {
			return;
		}
		wp_clear_scheduled_hook( self::HOOK );
		if ( false !== $want ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, $want, self::HOOK );
		}
	}

	public static function deactivate() {
		wp_unschedule_hook( self::HOOK );
	}

	/**
	 * One amount in the product's currency, as a price in the store currency.
	 *
	 * @param float $amount      Price in the foreign currency.
	 * @param array $row         The currency's row from the board.
	 * @param float $fee_percent Fee in percent.
	 * @return array{price: float, rate: float}|null Null when the rate is missing or the result is not positive.
	 */
	public static function compute( $amount, array $row, $fee_percent ) {
		$unit  = self::store_unit();
		$field = Netarz_FX_Render::field( Netarz_FX_Settings::get( 'woo_price_field' ) );
		if ( null === $unit || empty( $row[ $field ] ) || $amount <= 0 ) {
			return null;
		}
		$per  = isset( $row['unit'] ) ? max( 1, (int) $row['unit'] ) : 1;
		$rate = (float) $row[ $field ] / $per;

		$toman = $amount * $rate * ( 1 + $fee_percent / 100 ) + (float) Netarz_FX_Settings::get( 'woo_fee_fixed' );
		$step  = max( 1, (int) Netarz_FX_Settings::get( 'woo_rounding' ) );
		$toman = 'nearest' === Netarz_FX_Settings::get( 'woo_round_mode' )
			? round( $toman / $step ) * $step
			: ceil( $toman / $step - 1e-9 ) * $step;

		$price = round( $toman * $unit, wc_get_price_decimals() );
		return $price > 0 ? array(
			'price' => $price,
			'rate'  => $rate,
		) : null;
	}

	/**
	 * Writes the converted prices into the product object (the caller saves it).
	 *
	 * @param \WC_Product $product A simple product or a variation.
	 * @param array       $board   Netarz_FX_Client::board().
	 * @param bool        $force   Ignore the jump guard (an administrator saving the product).
	 * @return string updated | unchanged | skipped | none
	 */
	public static function apply( $product, array $board, $force = false ) {
		$code = strtoupper( (string) $product->get_meta( self::META_CURRENCY ) );
		if ( '' === $code ) {
			return 'none';
		}
		$row = isset( $board['rates'][ $code ] ) && is_array( $board['rates'][ $code ] ) ? $board['rates'][ $code ] : null;
		if ( ! $row ) {
			return self::skip( $product, 'no_rate' );
		}

		$fee     = (string) $product->get_meta( self::META_FEE );
		$fee     = '' === $fee ? (float) Netarz_FX_Settings::get( 'woo_fee_percent' ) : (float) $fee;
		$regular = self::compute( (float) $product->get_meta( self::META_REGULAR ), $row, $fee );
		if ( ! $regular ) {
			return self::skip( $product, (float) $product->get_meta( self::META_REGULAR ) > 0 ? 'no_rate' : 'no_price' );
		}

		// A rate that moved more than the guard allows since the last update is held for a person to look at.
		$last = (float) $product->get_meta( self::META_RATE );
		$max  = (float) Netarz_FX_Settings::get( 'woo_max_jump' );
		if ( ! $force && $last > 0 && $max > 0 && abs( $regular['rate'] - $last ) / $last * 100 > $max ) {
			return self::skip( $product, 'jump' );
		}

		$sale     = self::compute( (float) $product->get_meta( self::META_SALE ), $row, $fee );
		$new_reg  = wc_format_decimal( $regular['price'] );
		$new_sale = $sale && $sale['price'] < $regular['price'] ? wc_format_decimal( $sale['price'] ) : '';

		$changed = (string) $product->get_regular_price( 'edit' ) !== (string) $new_reg
			|| (string) $product->get_sale_price( 'edit' ) !== (string) $new_sale;
		if ( $changed ) {
			$product->set_regular_price( $new_reg );
			$product->set_sale_price( $new_sale );
		}
		$product->update_meta_data( self::META_RATE, (string) $regular['rate'] );
		$product->update_meta_data( self::META_UPDATED, time() );
		$product->delete_meta_data( self::META_NOTE );

		return $changed ? 'updated' : 'unchanged';
	}

	private static function skip( $product, $reason ) {
		$product->update_meta_data( self::META_NOTE, $reason );
		return 'skipped';
	}

	/**
	 * Reprices one batch of products and schedules the next batch.
	 *
	 * @param int $offset Products already handled in this run.
	 */
	public static function run( $offset = 0 ) {
		$offset = max( 0, (int) $offset );
		if ( ! self::available() ) {
			return;
		}

		$stats = get_option( self::LAST_RUN );
		if ( 0 === $offset || ! is_array( $stats ) ) {
			$stats = array(
				'started'   => time(),
				'updated'   => 0,
				'unchanged' => 0,
				'skipped'   => 0,
				'skip_ids'  => array(),
				'error'     => '',
			);
		}

		$board = Netarz_FX_Client::board();
		if ( is_wp_error( $board ) ) {
			$stats['error']    = $board->get_error_message();
			$stats['finished'] = time();
			update_option( self::LAST_RUN, $stats, false );
			return;
		}

		$ids = get_posts(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'fields'         => 'ids',
				'posts_per_page' => self::BATCH,
				'offset'         => $offset,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- runs from cron, a batch at a time.
					array(
						'key'     => self::META_CURRENCY,
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		$parents = array();
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || $product->is_type( array( 'variable', 'grouped' ) ) ) {
				continue;
			}
			$status = self::apply( $product, $board );
			if ( 'updated' === $status ) {
				$product->save();
				if ( $product->get_parent_id() ) {
					$parents[ $product->get_parent_id() ] = true;
				}
			} else {
				$product->save_meta_data();
			}
			if ( isset( $stats[ $status ] ) ) {
				++$stats[ $status ];
			}
			if ( 'skipped' === $status && count( $stats['skip_ids'] ) < 20 ) {
				$stats['skip_ids'][] = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
			}
		}
		foreach ( array_keys( $parents ) as $parent_id ) {
			WC_Product_Variable::sync( $parent_id );
			wc_delete_product_transients( $parent_id );
		}

		$stats['skip_ids'] = array_values( array_unique( $stats['skip_ids'] ) );
		if ( count( $ids ) === self::BATCH ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK, array( $offset + self::BATCH ) );
		} else {
			$stats['finished'] = time();
		}
		update_option( self::LAST_RUN, $stats, false );
	}

	/** Settings > NetArz FX > "Update prices now": fresh rates, first batch right away. */
	public static function reprice_now() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'netarz-fx' ) );
		}
		check_admin_referer( 'netarz_fx_woo_reprice' );
		Netarz_FX_Client::flush();
		self::run( 0 );
		wp_safe_redirect( admin_url( 'options-general.php?page=netarz-fx#netarz-fx-woo-pricing' ) );
		exit;
	}

	/** The settings rows, printed inside the settings form. */
	public static function settings_rows( array $o, $n ) {
		$store = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : '';
		?>
		<tr id="netarz-fx-woo-pricing">
			<th scope="row"><?php esc_html_e( 'Automatic WooCommerce prices', 'netarz-fx' ); ?></th>
			<td>
				<label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[woo_pricing]" value="1" <?php checked( (int) $o['woo_pricing'], 1 ); ?>> <strong><?php esc_html_e( 'Update the Toman price of products priced in a foreign currency', 'netarz-fx' ); ?></strong></label>
				<p class="description"><?php esc_html_e( 'On the product page, under Price, give the product a currency and its regular (and, if you like, sale) price in that currency. The plugin turns them into the store currency at the NetArz rate plus your fee, and repeats it on the schedule below. Products without a currency are never touched.', 'netarz-fx' ); ?></p>
				<?php if ( '' !== $store && null === self::store_unit() ) : ?>
					<p class="description"><strong>
						<?php
						printf(
							/* translators: %s: the WooCommerce store currency code, such as USD. */
							esc_html__( 'Your store currency is %s. Automatic prices work when the store currency is Toman or Rial (WooCommerce > Settings > General).', 'netarz-fx' ),
							esc_html( $store )
						);
						?>
					</strong></p>
				<?php endif; ?>
				<p>
					<label><?php esc_html_e( 'Rate', 'netarz-fx' ); ?>
						<select name="<?php echo esc_attr( $n ); ?>[woo_price_field]">
							<option value="sell" <?php selected( $o['woo_price_field'], 'sell' ); ?>><?php esc_html_e( 'Sell', 'netarz-fx' ); ?></option>
							<option value="buy" <?php selected( $o['woo_price_field'], 'buy' ); ?>><?php esc_html_e( 'Buy', 'netarz-fx' ); ?></option>
							<option value="mid" <?php selected( $o['woo_price_field'], 'mid' ); ?>><?php esc_html_e( 'Average', 'netarz-fx' ); ?></option>
						</select>
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Fee (percent)', 'netarz-fx' ); ?>
						<input type="number" step="0.01" min="-90" max="1000" class="small-text" name="<?php echo esc_attr( $n ); ?>[woo_fee_percent]" value="<?php echo esc_attr( (string) $o['woo_fee_percent'] ); ?>">
					</label>
					&nbsp;
					<label><?php esc_html_e( 'Fixed fee (Toman per item)', 'netarz-fx' ); ?>
						<input type="number" step="1" min="0" class="regular-text" style="width:10em" name="<?php echo esc_attr( $n ); ?>[woo_fee_fixed]" value="<?php echo esc_attr( (string) $o['woo_fee_fixed'] ); ?>">
					</label>
				</p>
				<p class="description"><?php esc_html_e( 'Price = amount × rate × (1 + fee ÷ 100) + fixed fee, then rounded. A product can have its own fee percent; empty means this one.', 'netarz-fx' ); ?></p>
				<p>
					<label><?php esc_html_e( 'Round to', 'netarz-fx' ); ?>
						<select name="<?php echo esc_attr( $n ); ?>[woo_rounding]">
							<?php foreach ( self::ROUNDINGS as $step ) : ?>
								<option value="<?php echo esc_attr( (string) $step ); ?>" <?php selected( (int) $o['woo_rounding'], $step ); ?>>
									<?php
									/* translators: %s: a price in Iranian Toman, already formatted. */
									echo esc_html( sprintf( __( '%s Toman', 'netarz-fx' ), number_format_i18n( $step ) ) );
									?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<select name="<?php echo esc_attr( $n ); ?>[woo_round_mode]" aria-label="<?php esc_attr_e( 'Rounding direction', 'netarz-fx' ); ?>">
						<option value="up" <?php selected( $o['woo_round_mode'], 'up' ); ?>><?php esc_html_e( 'Upwards', 'netarz-fx' ); ?></option>
						<option value="nearest" <?php selected( $o['woo_round_mode'], 'nearest' ); ?>><?php esc_html_e( 'To the nearest', 'netarz-fx' ); ?></option>
					</select>
				</p>
				<p>
					<label><?php esc_html_e( 'Update every', 'netarz-fx' ); ?>
						<select name="<?php echo esc_attr( $n ); ?>[woo_interval]">
							<?php foreach ( self::INTERVALS as $hours ) : ?>
								<option value="<?php echo esc_attr( (string) $hours ); ?>" <?php selected( (int) $o['woo_interval'], $hours ); ?>>
									<?php
									/* translators: %d: number of hours. */
									echo esc_html( sprintf( _n( '%d hour', '%d hours', $hours, 'netarz-fx' ), $hours ) );
									?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					&nbsp;
					<label><?php esc_html_e( 'Hold a product if the rate moved more than (percent)', 'netarz-fx' ); ?>
						<input type="number" step="1" min="0" max="100" class="small-text" name="<?php echo esc_attr( $n ); ?>[woo_max_jump]" value="<?php echo esc_attr( (string) $o['woo_max_jump'] ); ?>">
					</label>
				</p>
				<p class="description"><?php esc_html_e( 'A safety catch against a wrong rate: when the rate moved more than this since the product\'s last update, its price is left as it is and the product is listed below until you save it. 0 turns the catch off.', 'netarz-fx' ); ?></p>
				<p class="description"><?php esc_html_e( 'WordPress runs scheduled tasks when the site has visitors. On a quiet site, point a server cron job at wp-cron.php so prices are updated on time.', 'netarz-fx' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/** Last run and the "Update prices now" button, printed after the settings form. */
	public static function status_box() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		echo '<h2>' . esc_html__( 'Automatic WooCommerce prices', 'netarz-fx' ) . '</h2>';

		$stats = get_option( self::LAST_RUN );
		if ( is_array( $stats ) ) {
			echo '<p>';
			printf(
				/* translators: 1: date and time, 2: products updated, 3: products unchanged, 4: products held. */
				esc_html__( 'Last run: %1$s. Updated: %2$s, unchanged: %3$s, held: %4$s.', 'netarz-fx' ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $stats['started'] ) ),
				esc_html( number_format_i18n( (int) $stats['updated'] ) ),
				esc_html( number_format_i18n( (int) $stats['unchanged'] ) ),
				esc_html( number_format_i18n( (int) $stats['skipped'] ) )
			);
			if ( empty( $stats['finished'] ) && '' === $stats['error'] ) {
				echo ' ' . esc_html__( 'Still running: the rest follow a minute apart.', 'netarz-fx' );
			}
			echo '</p>';
			if ( '' !== (string) $stats['error'] ) {
				echo '<p><strong>' . esc_html__( 'No price was changed: the rates could not be read.', 'netarz-fx' ) . '</strong> ' . esc_html( $stats['error'] ) . '</p>';
			}
			$links = array();
			if ( ! empty( $stats['skip_ids'] ) ) {
				foreach ( $stats['skip_ids'] as $id ) {
					$link = get_edit_post_link( (int) $id );
					if ( $link ) {
						$links[] = '<a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( (int) $id ) ) . '</a>';
					}
				}
			}
			if ( ! empty( $links ) ) {
				echo '<p>' . esc_html__( 'Held products (open one to see why):', 'netarz-fx' ) . ' ' . wp_kses( implode( wp_get_list_item_separator(), $links ), array( 'a' => array( 'href' => array() ) ) ) . '</p>';
			}
		}

		$next = wp_next_scheduled( self::HOOK );
		if ( $next ) {
			echo '<p>';
			printf(
				/* translators: %s: date and time. */
				esc_html__( 'Next run: %s.', 'netarz-fx' ),
				esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) )
			);
			echo '</p>';
		}

		if ( self::available() ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="netarz_fx_woo_reprice">
				<?php wp_nonce_field( 'netarz_fx_woo_reprice' ); ?>
				<?php submit_button( __( 'Update prices now', 'netarz-fx' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php
		}
	}

	/** Currency choices for the product screen: the board's codes, or a short list when it is not readable. */
	private static function currency_options( $current ) {
		$options = array( '' => __( 'None (price set by hand)', 'netarz-fx' ) );
		$board   = Netarz_FX_Client::connected() ? Netarz_FX_Client::board() : null;
		$codes   = is_array( $board ) && ! empty( $board['rates'] ) ? array_keys( $board['rates'] ) : array( 'USD', 'EUR', 'GBP', 'AED', 'TRY', 'CNY' );
		foreach ( $codes as $code ) {
			$row              = is_array( $board ) && isset( $board['rates'][ $code ] ) ? $board['rates'][ $code ] : array( 'code' => $code );
			$options[ $code ] = $code . ( empty( $row['name'] ) && empty( $row['name_en'] ) ? '' : ' (' . Netarz_FX_Render::currency_name( $row ) . ')' );
		}
		if ( '' !== $current && ! isset( $options[ $current ] ) ) {
			$options[ $current ] = $current;
		}
		return $options;
	}

	/**
	 * The four fields for a product (`$loop` null) or for one variation.
	 *
	 * @param \WC_Product $product The product or variation.
	 * @param int|null    $loop    Variation index, or null for the product itself.
	 */
	private static function fields( $product, $loop ) {
		$suffix = null === $loop ? '' : '[' . $loop . ']';
		$id     = null === $loop ? '' : (string) $loop;
		$wrap   = null === $loop ? '' : 'form-row ';
		$code   = $product ? strtoupper( (string) $product->get_meta( self::META_CURRENCY ) ) : '';

		woocommerce_wp_select(
			array(
				'id'            => 'netarz_fx_currency' . $id,
				'name'          => 'netarz_fx_currency' . $suffix,
				'label'         => __( 'Price currency (NetArz)', 'netarz-fx' ),
				'options'       => self::currency_options( $code ),
				'value'         => $code,
				'wrapper_class' => $wrap . ( null === $loop ? '' : 'form-row-full' ),
				'desc_tip'      => true,
				'description'   => __( 'Pick a currency to have this price follow the NetArz rate. The regular and sale prices above are then filled in by the plugin.', 'netarz-fx' ),
			)
		);
		$amounts = array(
			'regular' => array( self::META_REGULAR, __( 'Regular price in that currency', 'netarz-fx' ), 'form-row-first' ),
			'sale'    => array( self::META_SALE, __( 'Sale price in that currency', 'netarz-fx' ), 'form-row-last' ),
			'fee'     => array( self::META_FEE, __( 'Fee for this product (percent)', 'netarz-fx' ), 'form-row-first' ),
		);
		foreach ( $amounts as $key => $field ) {
			woocommerce_wp_text_input(
				array(
					'id'            => 'netarz_fx_' . $key . $id,
					'name'          => 'netarz_fx_' . $key . $suffix,
					'label'         => $field[1],
					'value'         => $product ? wc_format_localized_decimal( (string) $product->get_meta( $field[0] ) ) : '',
					'data_type'     => 'decimal',
					'wrapper_class' => null === $loop ? '' : $wrap . $field[2],
					'placeholder'   => 'fee' === $key ? wc_format_localized_decimal( (string) Netarz_FX_Settings::get( 'woo_fee_percent' ) ) : '',
				)
			);
		}

		if ( ! $product || '' === $code ) {
			return;
		}
		$note    = (string) $product->get_meta( self::META_NOTE );
		$updated = (int) $product->get_meta( self::META_UPDATED );
		$text    = '';
		if ( 'jump' === $note ) {
			$text = __( 'Held: the rate moved more than the safety catch allows since the last update. Check the rate and save the product to apply it.', 'netarz-fx' );
		} elseif ( 'no_rate' === $note ) {
			$text = __( 'Held: NetArz has no rate for this currency on your plan.', 'netarz-fx' );
		} elseif ( 'no_price' === $note ) {
			$text = __( 'Held: the regular price in that currency is empty.', 'netarz-fx' );
		} elseif ( $updated ) {
			$text = sprintf(
				/* translators: 1: date and time, 2: currency code, 3: rate in Toman. */
				__( 'Last updated %1$s at 1 %2$s = %3$s Toman.', 'netarz-fx' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $updated ),
				$code,
				Netarz_FX_Render::number( round( (float) $product->get_meta( self::META_RATE ), 2 ) )
			);
		}
		if ( '' !== $text ) {
			echo '<p class="form-field' . ( null === $loop ? '' : ' form-row form-row-full' ) . '"><em>' . esc_html( $text ) . '</em></p>';
		}
	}

	public static function product_fields() {
		global $product_object;
		echo '<div class="options_group netarz-fx-woo-pricing show_if_simple show_if_external">';
		self::fields( $product_object instanceof WC_Product ? $product_object : null, null );
		echo '</div>';
	}

	public static function variation_fields( $loop, $variation_data, $variation ) {
		echo '<div class="netarz-fx-woo-pricing">';
		self::fields( wc_get_product( $variation->ID ), (int) $loop );
		echo '</div>';
	}

	/**
	 * Saves the fields from the posted form and, when the feature can run, the converted prices.
	 * WooCommerce has already checked its nonce before these actions fire.
	 */
	public static function save_product( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce (woocommerce_meta_nonce).
		if ( ! isset( $_POST['netarz_fx_currency'] ) || is_array( $_POST['netarz_fx_currency'] ) || $product->is_type( array( 'variable', 'grouped' ) ) ) {
			return;
		}
		self::store_fields(
			$product,
			sanitize_text_field( wp_unslash( $_POST['netarz_fx_currency'] ) ),
			isset( $_POST['netarz_fx_regular'] ) ? sanitize_text_field( wp_unslash( $_POST['netarz_fx_regular'] ) ) : '',
			isset( $_POST['netarz_fx_sale'] ) ? sanitize_text_field( wp_unslash( $_POST['netarz_fx_sale'] ) ) : '',
			isset( $_POST['netarz_fx_fee'] ) ? sanitize_text_field( wp_unslash( $_POST['netarz_fx_fee'] ) ) : ''
		);
		// phpcs:enable
		self::apply_now( $product );
	}

	public static function save_variation( $variation_id, $i ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce (save-variations nonce).
		if ( ! isset( $_POST['netarz_fx_currency'][ $i ] ) ) {
			return;
		}
		$variation = wc_get_product( $variation_id );
		if ( ! $variation ) {
			return;
		}
		self::store_fields(
			$variation,
			sanitize_text_field( wp_unslash( $_POST['netarz_fx_currency'][ $i ] ) ),
			isset( $_POST['netarz_fx_regular'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['netarz_fx_regular'][ $i ] ) ) : '',
			isset( $_POST['netarz_fx_sale'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['netarz_fx_sale'][ $i ] ) ) : '',
			isset( $_POST['netarz_fx_fee'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['netarz_fx_fee'][ $i ] ) ) : ''
		);
		// phpcs:enable
		self::apply_now( $variation );
		$variation->save();
		if ( $variation->get_parent_id() ) {
			WC_Product_Variable::sync( $variation->get_parent_id() );
		}
	}

	private static function store_fields( $product, $currency, $regular, $sale, $fee ) {
		$code = substr( strtoupper( preg_replace( '/[^A-Za-z]/', '', $currency ) ), 0, 10 );
		$product->update_meta_data( self::META_CURRENCY, $code );
		$product->update_meta_data( self::META_REGULAR, self::decimal( $regular ) );
		$product->update_meta_data( self::META_SALE, self::decimal( $sale ) );
		$fee = self::decimal( $fee, true );
		$product->update_meta_data( self::META_FEE, '' === $fee ? '' : (string) max( -90, min( 1000, (float) $fee ) ) );
		if ( '' === $code ) {
			$product->delete_meta_data( self::META_NOTE );
		}
	}

	private static function decimal( $value, $signed = false ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$value = wc_format_decimal( $value );
		return is_numeric( $value ) && ( $signed || (float) $value > 0 ) ? $value : '';
	}

	/** An administrator saving the product sees the converted price at once; the jump guard does not apply. */
	private static function apply_now( $product ) {
		if ( ! self::available() || '' === (string) $product->get_meta( self::META_CURRENCY ) ) {
			return;
		}
		$board = Netarz_FX_Client::board();
		if ( is_array( $board ) ) {
			self::apply( $product, $board, true );
		}
	}
}
