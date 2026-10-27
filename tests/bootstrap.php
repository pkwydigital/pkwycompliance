<?php
/**
 * Minimal harness. Stubs the WordPress functions the pure logic touches so it
 * can be exercised from the terminal with no WordPress and no database.
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['pkwyc_tests']   = array( 'pass' => 0, 'fail' => 0 );
$GLOBALS['pkwyc_options'] = array();

function pkwyc_assert( $label, $actual, $expected ) {
	$ok = var_export( $actual, true ) === var_export( $expected, true );
	if ( $ok ) {
		$GLOBALS['pkwyc_tests']['pass']++;
		return;
	}
	$GLOBALS['pkwyc_tests']['fail']++;
	echo "FAIL: {$label}\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
}

function pkwyc_results() {
	$r = $GLOBALS['pkwyc_tests'];
	echo "{$r['pass']} passed, {$r['fail']} failed\n";
	exit( $r['fail'] > 0 ? 1 : 0 );
}

function esc_url( $url )       { return filter_var( (string) $url, FILTER_SANITIZE_URL ); }
function esc_html( $text )     { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text )     { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $t, $d = '' ) { return $t; }
function __( $t, $d = '' )     { return $t; }
function wp_kses_post( $html ) { return $html; }
function sanitize_text_field( $t ) { return trim( strip_tags( (string) $t ) ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, (array) $args ); }
function get_option( $n, $d = false ) { return isset( $GLOBALS['pkwyc_options'][ $n ] ) ? $GLOBALS['pkwyc_options'][ $n ] : $d; }
function update_option( $n, $v, $a = true ) { $GLOBALS['pkwyc_options'][ $n ] = $v; return true; }
function add_action( $h, $c, $p = 10, $a = 1 ) {}
function add_filter( $h, $c, $p = 10, $a = 1 ) {}
