<?php
/**
 * Removes everything the plugin stored.
 *
 * @package NetArzFX
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'netarz_fx_settings' );
delete_option( 'netarz_fx_last_board' );
delete_transient( 'netarz_fx_board' );
