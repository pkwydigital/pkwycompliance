<?php
/**
 * Release smoke test against real WordPress, WPForms Pro and Gravity Forms.
 *
 * The unit suites run the plugin against stubs. This runs the built package
 * inside a real site, through the form plugins' own code, for the behaviour
 * that matters most: whether a Mailchimp rule on Consent subscribes only the
 * people who ticked, with the plugin working and with PKWYC_DISABLE on.
 *
 * Run by build/release.sh against a private test site that has WPForms Pro
 * and Gravity Forms active and the built zip installed:
 *
 *   wp eval-file tests/real/smoke.php active  <version>
 *   wp --exec="define('PKWYC_DISABLE', true);" eval-file tests/real/smoke.php disabled <version>
 *
 * Prints one line per check and exits non-zero on the first failure.
 */

$mode    = isset( $args[0] ) ? $args[0] : 'active';
$version = isset( $args[1] ) ? $args[1] : '';
$zip     = isset( $args[2] ) ? $args[2] : '';
$sig     = isset( $args[3] ) ? $args[3] : '';
$failed  = 0;

$check = function ( $label, $ok ) use ( &$failed ) {
	WP_CLI::line( ( $ok ? 'ok   ' : 'FAIL ' ) . $label );
	if ( ! $ok ) {
		$failed++;
	}
};

$check( "plugin version is {$version}", defined( 'PKWYC_VERSION' ) && PKWYC_VERSION === $version );
$check( 'WPForms Pro conditional logic is available', function_exists( 'wpforms_conditional_logic' ) );

// One form: Email (1), Consent (5), a second Consent (6), and a Mailchimp
// connection set the way the guide teaches: process if Consent is not empty.
$form_data = array(
	'id'     => 999001,
	'fields' => array(
		1 => array( 'id' => 1, 'type' => 'email', 'label' => 'Email' ),
		5 => array( 'id' => 5, 'type' => 'pkwyc_consent', 'label' => 'Consent' ),
		6 => array( 'id' => 6, 'type' => 'pkwyc_consent', 'label' => 'Consent' ),
	),
);
$rule = array( array( array( 'field' => '5', 'operator' => '!e' ) ) );
// Also "process if Consent is agreed", which the forms table counts as gated.
$rule_agreed = array( array( array( 'field' => '5', 'operator' => '==', 'value' => 'agreed' ) ) );

$process = wpforms()->obj( 'process' );

// Run WPForms' own format step for the Consent field, then its own rule check.
$subscribes = function ( $posted, $use = null ) use ( $form_data, $rule, $process ) {
	$use = $use ? $use : $rule;
	$process->fields = array( 1 => array( 'id' => 1, 'type' => 'email', 'name' => 'Email', 'value' => 'pat@example.org' ) );
	if ( has_action( 'wpforms_process_format_pkwyc_consent' ) ) {
		do_action( 'wpforms_process_format_pkwyc_consent', 5, $posted, $form_data );
	}
	return (bool) wpforms_conditional_logic()->process( $process->fields, $form_data, $use );
};

if ( $mode === 'disabled' ) {

	$check( 'PKWYC_DISABLE is on', defined( 'PKWYC_DISABLE' ) && PKWYC_DISABLE );
	$check( 'the field class is not loaded', ! class_exists( 'PKWYC_WPForms_Consent_Field', false ) );
	$check( 'Mailchimp rule subscribes nobody who did not tick', $subscribes( '' ) === false );
	$check( 'Mailchimp rule subscribes nobody even when agreed is posted', $subscribes( 'agreed' ) === false );
	$check( 'Mailchimp "is agreed" rule subscribes nobody', $subscribes( '', $rule_agreed ) === false );
	$check( 'Mailchimp "is agreed" rule subscribes nobody even when agreed is posted', $subscribes( 'agreed', $rule_agreed ) === false );

} else {

	// The built zip verifies inside real WordPress (its sodium or sodium_compat),
	// and a tampered copy does not.
	if ( $zip !== '' ) {
		$check( 'signed zip verifies', pkwyc_update_signature_problem( $zip, (string) file_get_contents( $sig ), pkwyc_update_signing_keys() ) === '' );
		$bad = tempnam( sys_get_temp_dir(), 'pkwyc-tampered' );
		file_put_contents( $bad, file_get_contents( $zip ) . 'x' );
		$check( 'tampered zip is refused', pkwyc_update_signature_problem( $bad, (string) file_get_contents( $sig ), pkwyc_update_signing_keys() ) !== '' );
		unlink( $bad );
		$check( 'updates are only taken through the signature check', has_filter( 'upgrader_pre_download', 'pkwyc_update_verified_download' ) !== false );
	}

	// The signature guard must not mistake the real form plugins for broken
	// ones: a false alarm turns the field off on every site.
	$check( 'signature guard: WPForms fits', pkwyc_signature_problems( 'WPForms_Field', pkwyc_consent_wpforms_spec() ) === array() );
	$check( 'signature guard: Gravity Forms fits', pkwyc_signature_problems( 'GF_Field', pkwyc_consent_gf_spec() ) === array() );
	$check( 'signature guard: nothing turned off', empty( $GLOBALS['pkwyc_signature_failures'] ) );
	$check( 'WPForms field class loaded', class_exists( 'PKWYC_WPForms_Consent_Field', false ) );
	$check( 'Gravity Forms field class loaded', class_exists( 'PKWYC_GF_Consent_Field', false ) );
	$check( 'Mailchimp rule: unticked is not subscribed', $subscribes( '' ) === false );
	$check( 'Mailchimp rule: ticked is subscribed', $subscribes( 'agreed' ) === true );
	$check( 'Mailchimp rule: a forged value is not subscribed', $subscribes( 'yes please' ) === false );
	$check( 'Mailchimp "is agreed" rule: unticked is not subscribed', $subscribes( '', $rule_agreed ) === false );
	$check( 'Mailchimp "is agreed" rule: ticked is subscribed', $subscribes( 'agreed', $rule_agreed ) === true );

	// The second Consent field is never shown, so it must never refuse a submission.
	$process->errors = array();
	update_option( 'pkwyc_smoke_backup', get_option( 'pkwyc_settings' ) );
	update_option( 'pkwyc_settings', array( 'company' => 'Smoke', 'privacy_url' => 'https://example.org/privacy/', 'terms_url' => '', 'wording' => 'Yes, {company} may email me.' ) );
	do_action( 'wpforms_process_validate_pkwyc_consent', 6, '', $form_data );
	$check( 'a duplicate Consent field is not validated', empty( $process->errors[ $form_data['id'] ] ) );
	do_action( 'wpforms_process_validate_pkwyc_consent', 5, '', $form_data );
	$check( 'the first Consent field is enforced', ! empty( $process->errors[ $form_data['id'] ][5] ) );

	// Gravity Forms renders the box beside its text, at its own label size, never ticked.
	$gf = class_exists( 'GF_Fields' ) ? GF_Fields::get( 'pkwyc_consent' ) : null;
	if ( $gf ) {
		$gf->id = 4;
	}
	$gf_input = $gf ? $gf->get_field_input( array( 'id' => 1 ) ) : '';
	$check( 'Gravity Forms box is not pre-ticked', $gf_input !== '' && strpos( $gf_input, 'checked' ) === false );
	$check( 'Gravity Forms label uses its inline label class', strpos( $gf_input, 'gform-field-label--type-inline' ) !== false );
	$check( 'Gravity Forms lays it out as a choice field', $gf && $gf->get_field_css_class() === 'gfield--type-choice' );

	$backup = get_option( 'pkwyc_smoke_backup' );
	if ( $backup === false ) {
		delete_option( 'pkwyc_settings' );
	} else {
		update_option( 'pkwyc_settings', $backup );
	}
	delete_option( 'pkwyc_smoke_backup' );
}

if ( $failed ) {
	WP_CLI::error( "{$failed} smoke check(s) failed ({$mode})." );
}
WP_CLI::success( "smoke checks passed ({$mode})." );
