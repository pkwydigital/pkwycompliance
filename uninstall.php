<?php
/**
 * Runs when the plugin is deleted from the Plugins screen (not on deactivate).
 *
 * Removes the settings and the cached GitHub release. WordPress loads this file
 * without the main plugin file, so the names are spelled out here rather than
 * read from the PKWYC_ constants. Entries already stored by the form plugins
 * (the "agreed" values) belong to those plugins and are left alone.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $pkwyc_site_id ) {
		switch_to_blog( $pkwyc_site_id );
		delete_option( 'pkwyc_settings' );
		restore_current_blog();
	}
} else {
	delete_option( 'pkwyc_settings' );
}

// The updater caches the latest release as a network-wide site transient.
delete_site_transient( 'pkwyc_latest_release' );
