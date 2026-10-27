<?php
/**
 * Self-update from the public GitHub repository.
 *
 * Sites running this plugin take new releases without anyone pressing a button,
 * which is the point: the wording it displays is a compliance statement and
 * every site should be showing the same one. That also means a bad release
 * reaches the whole fleet, so see the kill switch in the consent logic.
 *
 * No third-party updater library: the whole job is one cached API call and
 * three filters.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this release tag newer than what is installed?
 *
 * Anything that is not a plain dotted version is refused, which rules out
 * "latest", branch names, and pre-releases such as 1.2.0-beta1. A pre-release
 * must never reach client sites by accident.
 */
function pkwyc_update_is_newer( $current, $tag ) {

	$tag = ltrim( trim( (string) $tag ), 'vV' );

	if ( ! preg_match( '/^\d+(\.\d+)*$/', $tag ) ) {
		return false;
	}

	return version_compare( $tag, trim( (string) $current ), '>' );
}

/**
 * Correct the folder name a GitHub zipball unpacks to.
 *
 * GitHub names it <owner>-<repo>-<sha>. Left alone, WordPress would install the
 * plugin into a new directory on every update, deactivating the real one and
 * leaving a pile of orphans. Only a folder that matches that pattern for this
 * slug is touched; anything else is another plugin's business.
 */
function pkwyc_update_target_dirname( $source_dirname, $slug ) {

	$source_dirname = (string) $source_dirname;
	$slug           = (string) $slug;

	if ( $source_dirname === $slug ) {
		return $source_dirname;
	}

	if ( preg_match( '/^.+-' . preg_quote( $slug, '/' ) . '-[0-9a-f]{7,40}$/i', $source_dirname ) ) {
		return $slug;
	}

	return $source_dirname;
}

/**
 * Where to download a release from.
 *
 * A zip attached to the release is preferred, because it contains exactly what
 * was built. The zipball is the fallback and carries whatever is in the repo,
 * including development files.
 */
function pkwyc_update_package_url( $release ) {

	if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
		foreach ( $release['assets'] as $asset ) {
			$name = isset( $asset['name'] ) ? strtolower( (string) $asset['name'] ) : '';
			$url  = isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
			if ( $url !== '' && substr( $name, -4 ) === '.zip' ) {
				return $url;
			}
		}
	}

	if ( ! empty( $release['zipball_url'] ) ) {
		return (string) $release['zipball_url'];
	}

	return '';
}

/* -------------------------------------------------------------------------
 * WordPress wiring. Everything above is pure and tested; everything below
 * talks to WordPress and GitHub.
 * ---------------------------------------------------------------------- */

if ( ! function_exists( 'add_filter' ) ) {
	return;
}

/**
 * The latest release, cached for twelve hours.
 *
 * Failures are cached too, for one hour, so a GitHub outage does not mean an
 * API call on every admin page load.
 */
function pkwyc_update_latest_release( $force = false ) {

	$cached = get_site_transient( PKWYC_UPDATE_TRANSIENT );

	if ( ! $force && $cached !== false ) {
		return is_array( $cached ) ? $cached : array();
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . PKWYC_UPDATE_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'pkwycompliance/' . PKWYC_VERSION,
			),
		)
	);

	if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
		set_site_transient( PKWYC_UPDATE_TRANSIENT, array(), HOUR_IN_SECONDS );
		return array();
	}

	$release = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( ! is_array( $release ) ) {
		set_site_transient( PKWYC_UPDATE_TRANSIENT, array(), HOUR_IN_SECONDS );
		return array();
	}

	set_site_transient( PKWYC_UPDATE_TRANSIENT, $release, 12 * HOUR_IN_SECONDS );

	return $release;
}

/**
 * Offer the update to WordPress.
 */
function pkwyc_update_check( $transient ) {

	if ( ! is_object( $transient ) ) {
		return $transient;
	}

	$release = pkwyc_update_latest_release();
	$tag     = isset( $release['tag_name'] ) ? $release['tag_name'] : '';

	if ( ! pkwyc_update_is_newer( PKWYC_VERSION, $tag ) ) {
		return $transient;
	}

	$package = pkwyc_update_package_url( $release );

	if ( $package === '' ) {
		return $transient;
	}

	$version = ltrim( trim( (string) $tag ), 'vV' );

	$transient->response[ PKWYC_BASENAME ] = (object) array(
		'slug'        => PKWYC_SLUG,
		'plugin'      => PKWYC_BASENAME,
		'new_version' => $version,
		'package'     => $package,
		'url'         => 'https://github.com/' . PKWYC_UPDATE_REPO,
		'tested'      => get_bloginfo( 'version' ),
	);

	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'pkwyc_update_check' );

/**
 * Put the unpacked folder back to the plugin slug.
 */
function pkwyc_update_fix_dirname( $source, $remote_source, $upgrader, $hook_extra = array() ) {

	if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== PKWYC_BASENAME ) {
		return $source;
	}

	$current = basename( untrailingslashit( $source ) );
	$wanted  = pkwyc_update_target_dirname( $current, PKWYC_SLUG );

	if ( $wanted === $current ) {
		return $source;
	}

	$target = trailingslashit( dirname( untrailingslashit( $source ) ) ) . $wanted;

	global $wp_filesystem;

	if ( $wp_filesystem && $wp_filesystem->move( $source, $target, true ) ) {
		return trailingslashit( $target );
	}

	return $source;
}
add_filter( 'upgrader_source_selection', 'pkwyc_update_fix_dirname', 10, 4 );

/**
 * Take updates without being asked.
 *
 * Deliberate, and the reason the kill switch exists. A site can opt out with
 * define( 'PKWYC_NO_AUTO_UPDATE', true ) without disabling the plugin itself.
 */
function pkwyc_update_force_auto( $update, $item ) {

	if ( ! isset( $item->plugin ) || $item->plugin !== PKWYC_BASENAME ) {
		return $update;
	}

	if ( defined( 'PKWYC_NO_AUTO_UPDATE' ) && PKWYC_NO_AUTO_UPDATE ) {
		return false;
	}

	return true;
}
add_filter( 'auto_update_plugin', 'pkwyc_update_force_auto', 10, 2 );
