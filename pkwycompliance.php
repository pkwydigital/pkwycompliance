<?php
/**
 * Plugin Name: PKWY Compliance
 * Plugin URI:  https://github.com/pkwydigital/pkwycompliance
 * Description: Compliance building blocks for Parkway Digital client sites. The first component adds a marketing consent field to WPForms and Gravity Forms, with the legal wording held in one place rather than retyped per form.
 * Version:     1.0.0
 * Author:      Parkway Digital
 * Author URI:  https://www.pkwydigital.com
 * License:     GPL-2.0-or-later
 *
 * This plugin updates itself from its public GitHub repository without anyone
 * pressing a button, so that every site shows the same compliance wording.
 * Two escape hatches exist for when that is the wrong behaviour:
 *
 *   define( 'PKWYC_DISABLE', true );         // stop enforcing anything
 *   define( 'PKWYC_NO_AUTO_UPDATE', true );  // keep the plugin, stop updating
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PKWYC_VERSION', '1.0.0' );
define( 'PKWYC_SLUG', 'pkwycompliance' );
define( 'PKWYC_FILE', __FILE__ );
define( 'PKWYC_BASENAME', plugin_basename( __FILE__ ) );
define( 'PKWYC_PATH', plugin_dir_path( __FILE__ ) );
define( 'PKWYC_OPTION', 'pkwyc_settings' );
define( 'PKWYC_UPDATE_REPO', 'pkwydigital/pkwycompliance' );
define( 'PKWYC_UPDATE_TRANSIENT', 'pkwyc_latest_release' );

require_once PKWYC_PATH . 'includes/updater.php';
require_once PKWYC_PATH . 'includes/settings.php';
require_once PKWYC_PATH . 'includes/components/consent/logic.php';

/**
 * The components this plugin provides.
 *
 * There is one today. The registry exists so a second does not require
 * rearranging the plugin, and so each component can be listed and described in
 * one place rather than being implied by which files happen to be included.
 */
function pkwyc_components() {

	$components = array(
		'consent' => array(
			'label'       => __( 'Form consent field', 'pkwycompliance' ),
			'description' => __( 'Adds a consent checkbox to WPForms and Gravity Forms, with the wording held centrally.', 'pkwycompliance' ),
			'path'        => 'includes/components/consent/consent.php',
		),
	);

	return apply_filters( 'pkwyc_components', $components );
}

/**
 * Load the components.
 *
 * Wrapped because this plugin auto-updates across every site it is installed
 * on and can block form submission by design. A fatal in a component must not
 * take a client's forms down with it, so a component that throws is skipped
 * and logged rather than allowed to bring down the request.
 */
function pkwyc_load_components() {

	if ( pkwyc_is_disabled() ) {
		return;
	}

	foreach ( pkwyc_components() as $slug => $component ) {

		$file = PKWYC_PATH . $component['path'];

		if ( ! file_exists( $file ) ) {
			continue;
		}

		try {
			require_once $file;
		} catch ( Throwable $e ) {
			error_log( sprintf( 'PKWY Compliance: component "%s" failed to load: %s', $slug, $e->getMessage() ) );
		}
	}
}
add_action( 'plugins_loaded', 'pkwyc_load_components', 20 );

/**
 * Tell anyone looking at the Plugins screen that enforcement is off.
 *
 * Without this, a site running with the kill switch looks identical to a site
 * where the plugin is working, which is how a disabled consent field survives
 * for months unnoticed.
 */
function pkwyc_disabled_notice() {

	if ( ! pkwyc_is_disabled() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-warning"><p><strong>PKWY Compliance is disabled.</strong> '
		. esc_html__( 'PKWYC_DISABLE is set in wp-config.php, so no consent field is being enforced on this site.', 'pkwycompliance' )
		. '</p></div>';
}
add_action( 'admin_notices', 'pkwyc_disabled_notice' );
