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
 * Is this download URL one of this plugin's own GitHub release files?
 *
 * Forced auto-updates and the download itself are limited to packages that
 * pass this, so an update offered from anywhere else (a wordpress.org plugin
 * that happens to share the slug, for one) is never installed silently.
 */
function pkwyc_update_package_is_ours( $url ) {
	$prefix = 'https://github.com/' . PKWYC_UPDATE_REPO . '/releases/download/';
	return strpos( (string) $url, $prefix ) === 0;
}

/**
 * Where to download a release from: the built pkwycompliance.zip attached to
 * the release, and nothing else.
 *
 * Not the first zip by name, which could be anything attached, and not the
 * zipball, which carries development files and unpacks to the wrong folder.
 * A release without the zip offers no update.
 */
function pkwyc_update_package_url( $release ) {

	if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
		foreach ( $release['assets'] as $asset ) {
			$name = isset( $asset['name'] ) ? (string) $asset['name'] : '';
			$url  = isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
			if ( $name === PKWYC_SLUG . '.zip' && pkwyc_update_package_is_ours( $url ) ) {
				return $url;
			}
		}
	}

	return '';
}

/**
 * Why an unpacked update must not be installed, or '' when it may.
 *
 * WordPress deletes the old plugin folder before moving the new one in. If the
 * zip had the wrong root, every site would lose the plugin, updater included,
 * with no way to recover by itself. So the unpacked folder must hold this
 * plugin's main file, named PKWY Compliance, at the version that was offered.
 *
 * @param string $source           Unpacked folder, as WordPress passes it.
 * @param string $expected_version The offered version, or '' when unknown.
 */
function pkwyc_update_source_problem( $source, $expected_version ) {

	$file = rtrim( (string) $source, '/\\' ) . '/' . PKWYC_SLUG . '.php';

	if ( ! is_readable( $file ) ) {
		return 'the package has no ' . PKWYC_SLUG . '.php at its top level';
	}

	$head = (string) file_get_contents( $file, false, null, 0, 8192 );

	if ( ! preg_match( '/^[ \t\/*#@]*Plugin Name:\s*PKWY Compliance\s*$/mi', $head ) ) {
		return 'the package is not PKWY Compliance';
	}

	if ( (string) $expected_version !== '' ) {
		if ( ! preg_match( '/^[ \t\/*#@]*Version:\s*(\S+)/mi', $head, $m ) || $m[1] !== (string) $expected_version ) {
			return 'the package is not version ' . $expected_version;
		}
	}

	return '';
}

/**
 * Should this request refetch the latest release? Yes when someone presses
 * Check again on Dashboard, Updates, so a fix can be taken at once instead of
 * waiting out the twelve-hour cache.
 */
function pkwyc_update_should_refetch( $query ) {
	return is_array( $query ) && ! empty( $query['force-check'] );
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
 * Refuse an unpacked update that is not this plugin at the offered version.
 * Runs after the folder name is corrected. See pkwyc_update_source_problem().
 */
function pkwyc_update_check_source( $source, $remote_source, $upgrader, $hook_extra = array() ) {

	if ( is_wp_error( $source ) || empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== PKWYC_BASENAME ) {
		return $source;
	}

	$updates  = get_site_transient( 'update_plugins' );
	$expected = ( is_object( $updates ) && isset( $updates->response[ PKWYC_BASENAME ]->new_version ) ) ? (string) $updates->response[ PKWYC_BASENAME ]->new_version : '';
	$problem  = pkwyc_update_source_problem( $source, $expected );

	if ( $problem !== '' ) {
		return new WP_Error( 'pkwyc_bad_package', 'PKWY Compliance update refused: ' . $problem . '.' );
	}

	return $source;
}
add_filter( 'upgrader_source_selection', 'pkwyc_update_check_source', 11, 4 );

/**
 * Check again on Dashboard, Updates also refetches the latest release.
 */
function pkwyc_update_refetch_on_check() {
	if ( pkwyc_update_should_refetch( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, core's own link.
		delete_site_transient( PKWYC_UPDATE_TRANSIENT );
	}
}
add_action( 'load-update-core.php', 'pkwyc_update_refetch_on_check' );

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

	// Only our own release. Anything else keeps the site's own setting.
	if ( ! isset( $item->package ) || ! pkwyc_update_package_is_ours( $item->package ) ) {
		return $update;
	}

	if ( defined( 'PKWYC_NO_AUTO_UPDATE' ) && PKWYC_NO_AUTO_UPDATE ) {
		return false;
	}

	return true;
}
add_filter( 'auto_update_plugin', 'pkwyc_update_force_auto', 10, 2 );
