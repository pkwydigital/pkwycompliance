<?php
/**
 * The consent component.
 *
 * Loads whichever adapter the site needs. A site can run both form plugins, so
 * these are not exclusive.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WPForms_Field' ) || function_exists( 'wpforms' ) ) {
	require_once __DIR__ . '/adapters/wpforms.php';
}

if ( class_exists( 'GFForms' ) || class_exists( 'GF_Field' ) ) {
	require_once __DIR__ . '/adapters/gravityforms.php';
}
