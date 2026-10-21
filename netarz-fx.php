<?php
/**
 * Plugin Name:       NetArz Currency & Gold Rates
 * Plugin URI:        https://github.com/netarz/netarz-fx-wordpress
 * Description:       Exchange rates in Iranian Toman, the global gold and silver price and a clearly labelled computed value of 18k gold and Iranian gold coins, from NetArz. Shortcodes, blocks, charts and widgets. Nothing is fetched until you turn the connection on.
 * Version:           2.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            NetArz
 * Author URI:        https://netarz.ir/fx-api
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       netarz-fx
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NETARZ_FX_VERSION', '2.0.1' );
define( 'NETARZ_FX_FILE', __FILE__ );
define( 'NETARZ_FX_DIR', plugin_dir_path( __FILE__ ) );
define( 'NETARZ_FX_URL', plugin_dir_url( __FILE__ ) );
// A staging or test site may point the plugin at other base URLs from wp-config.php.
if ( ! defined( 'NETARZ_FX_API' ) ) {
	define( 'NETARZ_FX_API', 'https://netarz.ir/api/fx/v1' );
}
if ( ! defined( 'NETARZ_FX_MARKETS_API' ) ) {
	define( 'NETARZ_FX_MARKETS_API', 'https://netarz.ir/api/markets/v1' );
}

require_once NETARZ_FX_DIR . 'includes/class-netarz-fx-client.php';
require_once NETARZ_FX_DIR . 'includes/class-netarz-fx-markets.php';
require_once NETARZ_FX_DIR . 'includes/class-netarz-fx-render.php';
require_once NETARZ_FX_DIR . 'includes/class-netarz-fx-market-render.php';
require_once NETARZ_FX_DIR . 'includes/class-netarz-fx-settings.php';
require_once NETARZ_FX_DIR . 'includes/class-netarz-fx-shortcode.php';
require_once NETARZ_FX_DIR . 'includes/class-netarz-fx-widget.php';
require_once NETARZ_FX_DIR . 'includes/class-netarz-fx-market-widget.php';
require_once NETARZ_FX_DIR . 'includes/class-netarz-fx-blocks.php';
require_once NETARZ_FX_DIR . 'includes/class-netarz-fx-woo.php';

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'netarz-fx', false, dirname( plugin_basename( NETARZ_FX_FILE ) ) . '/languages' );
	}
);

Netarz_FX_Settings::init();
Netarz_FX_Client::init();
Netarz_FX_Shortcode::init();
Netarz_FX_Blocks::init();
Netarz_FX_Woo::init();

add_action(
	'widgets_init',
	static function () {
		register_widget( 'Netarz_FX_Widget' );
		register_widget( 'Netarz_FX_Market_Widget' );
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( $links ) {
		$url = admin_url( 'options-general.php?page=netarz-fx' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'netarz-fx' ) . '</a>' );
		return $links;
	}
);
