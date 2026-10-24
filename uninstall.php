<?php
/**
 * Removes everything the plugin stored.
 *
 * @package NetArzFX
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$netarz_fx_index = get_option( 'netarz_fx_cache_index', array() );
foreach ( is_array( $netarz_fx_index ) ? $netarz_fx_index : array() as $netarz_fx_transient ) {
	delete_transient( (string) $netarz_fx_transient );
}

delete_option( 'netarz_fx_settings' );
delete_option( 'netarz_fx_version' );
delete_option( 'netarz_fx_last_board' );
delete_option( 'netarz_fx_last_markets' );
delete_option( 'netarz_fx_cache_index' );
delete_transient( 'netarz_fx_board' );
delete_transient( 'netarz_fx_catalogue' );
delete_option( 'netarz_fx_woo_last_run' );
wp_unschedule_hook( 'netarz_fx_woo_reprice' );
// Products keep the WooCommerce prices they have; only the plugin's own fields go.
foreach ( array( '_netarz_fx_currency', '_netarz_fx_regular', '_netarz_fx_sale', '_netarz_fx_fee', '_netarz_fx_rate', '_netarz_fx_updated', '_netarz_fx_note' ) as $netarz_fx_meta ) {
	delete_post_meta_by_key( $netarz_fx_meta );
}
