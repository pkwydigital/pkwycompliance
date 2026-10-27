<?php
/**
 * Pure logic for the consent component.
 *
 * Nothing here touches WordPress state or either form plugin, so every rule is
 * exercised by tests/test-consent.php with no WordPress and no database.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The kill switch.
 *
 * This plugin updates itself silently across every site it is installed on, and
 * it deliberately blocks submission when it is misconfigured. Those two
 * together mean a bad release could stop forms working everywhere at once, so
 * there has to be one line that stops it without waiting for a fix:
 *
 *     define( 'PKWYC_DISABLE', true );   // in wp-config.php
 */
function pkwyc_is_disabled() {
	return defined( 'PKWYC_DISABLE' ) && PKWYC_DISABLE;
}

function pkwyc_consent_setting( $settings, $key ) {
	return isset( $settings[ $key ] ) ? trim( (string) $settings[ $key ] ) : '';
}

/**
 * Is there enough here to show a consent the visitor could actually read?
 *
 * Both links and the wording are required. Anything less and the component
 * fails closed rather than asking somebody to agree to documents they cannot
 * open.
 */
function pkwyc_consent_is_configured( $settings ) {

	foreach ( array( 'privacy_url', 'terms_url', 'wording' ) as $key ) {
		if ( pkwyc_consent_setting( $settings, $key ) === '' ) {
			return false;
		}
	}

	return true;
}

/**
 * The sentence shown beside the checkbox.
 *
 * Returns an empty string when unconfigured; callers must treat that as "do not
 * render a usable control".
 */
function pkwyc_consent_wording_html( $settings ) {

	if ( ! pkwyc_consent_is_configured( $settings ) ) {
		return '';
	}

	$privacy = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( pkwyc_consent_setting( $settings, 'privacy_url' ) ),
		esc_html__( 'Privacy Policy', 'pkwycompliance' )
	);

	$terms = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( pkwyc_consent_setting( $settings, 'terms_url' ) ),
		esc_html__( 'Terms', 'pkwycompliance' )
	);

	$html = pkwyc_consent_setting( $settings, 'wording' );
	$html = str_replace( '{company}', pkwyc_consent_setting( $settings, 'company' ), $html );

	$had_links = ( strpos( $html, '{privacy}' ) !== false ) || ( strpos( $html, '{terms}' ) !== false );

	$html = str_replace( array( '{privacy}', '{terms}' ), array( $privacy, $terms ), $html );

	/*
	 * The wording is editable, so somebody will eventually save a version with
	 * the link placeholders removed. Appending them is not tidiness: consent to
	 * terms nobody can reach is not consent, so the links are not optional.
	 */
	if ( ! $had_links ) {
		$html = rtrim( $html ) . ' ' . sprintf(
			/* translators: 1: privacy policy link, 2: terms link. */
			esc_html__( 'See our %1$s and %2$s.', 'pkwycompliance' ),
			$privacy,
			$terms
		);
	}

	return $html;
}

/**
 * Only ever 'agreed' or empty.
 *
 * A checkbox posts whatever value the markup gave it, and a hand crafted
 * request can post anything at all, so the stored value is pinned here rather
 * than trusted.
 */
function pkwyc_consent_normalise_value( $raw ) {

	if ( ! is_scalar( $raw ) ) {
		return '';
	}

	return trim( (string) $raw ) === 'agreed' ? 'agreed' : '';
}
