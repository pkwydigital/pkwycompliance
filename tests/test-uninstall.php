<?php
/**
 * uninstall.php, run against stubbed WordPress functions.
 *
 * WordPress loads uninstall.php on its own, without the main plugin file, so
 * this includes it the same way. The guard test runs in a child process
 * because the guard exits.
 */

require_once __DIR__ . '/bootstrap.php';

// --- the guard ----------------------------------------------------------
$child = 'define("ABSPATH", "x"); $d = array();'
	. 'function delete_option( $n ) { $GLOBALS["d"][] = $n; }'
	. 'function delete_site_transient( $n ) { $GLOBALS["d"][] = $n; }'
	. 'function is_multisite() { return false; }'
	. 'register_shutdown_function( function () { echo json_encode( $GLOBALS["d"] ); } );'
	. 'include ' . var_export( __DIR__ . '/../uninstall.php', true ) . ';';
$out = shell_exec( escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $child ) );
pkwyc_assert( 'nothing deleted without WP_UNINSTALL_PLUGIN', json_decode( (string) $out, true ), array() );

// --- single site --------------------------------------------------------
$GLOBALS['deleted']   = array();
$GLOBALS['multisite'] = false;
$GLOBALS['blog']      = 1;

function delete_option( $n ) { $GLOBALS['deleted'][] = 'blog' . $GLOBALS['blog'] . ':' . $n; return true; }
function delete_site_transient( $n ) { $GLOBALS['deleted'][] = 'network:' . $n; return true; }
function is_multisite() { return $GLOBALS['multisite']; }
function get_sites( $args = array() ) { return array( 1, 2, 3 ); }
function switch_to_blog( $id ) { $GLOBALS['blog'] = $id; return true; }
function restore_current_blog() { $GLOBALS['blog'] = 1; return true; }

define( 'WP_UNINSTALL_PLUGIN', 'pkwycompliance/pkwycompliance.php' );

include __DIR__ . '/../uninstall.php';
pkwyc_assert( 'single site: option and update cache removed', $GLOBALS['deleted'],
	array( 'blog1:pkwyc_settings', 'network:pkwyc_latest_release' ) );

// --- multisite: every site's option, the network transient once --------
$GLOBALS['deleted']   = array();
$GLOBALS['multisite'] = true;

include __DIR__ . '/../uninstall.php';
sort( $GLOBALS['deleted'] );
pkwyc_assert( 'multisite: every site cleaned, transient once', $GLOBALS['deleted'],
	array( 'blog1:pkwyc_settings', 'blog2:pkwyc_settings', 'blog3:pkwyc_settings', 'network:pkwyc_latest_release' ) );
pkwyc_assert( 'multisite: back on the original site', $GLOBALS['blog'], 1 );

pkwyc_results();
