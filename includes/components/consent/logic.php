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
 * The Privacy Policy link and the wording are required. Anything less and the
 * component fails closed rather than asking somebody to agree to a policy they
 * cannot open. Terms is optional, since many sites have no terms page, but
 * wording that names {terms} without a Terms URL would print a dead
 * placeholder, so that also fails closed.
 */
function pkwyc_consent_is_configured( $settings ) {

	foreach ( array( 'privacy_url', 'wording' ) as $key ) {
		if ( pkwyc_consent_setting( $settings, $key ) === '' ) {
			return false;
		}
	}

	if ( pkwyc_consent_setting( $settings, 'terms_url' ) === ''
		&& strpos( pkwyc_consent_setting( $settings, 'wording' ), '{terms}' ) !== false ) {
		return false;
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

	$terms = '';
	if ( pkwyc_consent_setting( $settings, 'terms_url' ) !== '' ) {
		$terms = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( pkwyc_consent_setting( $settings, 'terms_url' ) ),
			esc_html__( 'Terms', 'pkwycompliance' )
		);
	}

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
		if ( $terms !== '' ) {
			$html = rtrim( $html ) . ' ' . sprintf(
				/* translators: 1: privacy policy link, 2: terms link. */
				esc_html__( 'See our %1$s and %2$s.', 'pkwycompliance' ),
				$privacy,
				$terms
			);
		} else {
			$html = rtrim( $html ) . ' ' . sprintf(
				/* translators: %s: privacy policy link. */
				esc_html__( 'See our %s.', 'pkwycompliance' ),
				$privacy
			);
		}
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

/**
 * Is the consent required on this form?
 *
 * Takes the per-form "optional" switch rather than a "required" one, so a form
 * saved before the switch existed, or saved with anything unexpected in it,
 * stays required.
 */
function pkwyc_consent_is_required( $optional_flag ) {
	return ! ( $optional_flag === true || ( is_scalar( $optional_flag ) && (string) $optional_flag === '1' ) );
}

/**
 * Why a submission should be refused, or an empty string if it should not.
 *
 * Returns 'unconfigured' or 'not_agreed'. Optional does not relax the fail
 * closed rule: an unticked box beside wording that is missing would still
 * record a decision made without the terms in view.
 */
function pkwyc_consent_validation_error( $settings, $required, $raw ) {

	if ( ! pkwyc_consent_is_configured( $settings ) ) {
		return 'unconfigured';
	}

	if ( $required && pkwyc_consent_normalise_value( $raw ) !== 'agreed' ) {
		return 'not_agreed';
	}

	return '';
}

/**
 * The message shown for an error from pkwyc_consent_validation_error().
 */
function pkwyc_consent_error_message( $error ) {

	if ( $error === 'unconfigured' ) {
		return esc_html__( 'This form cannot be submitted at this time.', 'pkwycompliance' );
	}

	return esc_html__( 'You must agree to continue.', 'pkwycompliance' );
}

/**
 * The settings as one form sees them, with that form's custom wording applied.
 *
 * The override replaces the wording and nothing else. The links still come
 * from the site settings, so a form cannot carry its own privacy policy, and
 * every other rule (links appended, fail closed) applies to it unchanged. A
 * blank override means "use the site wording".
 */
function pkwyc_consent_effective_settings( $settings, $override ) {

	if ( ! is_scalar( $override ) || trim( (string) $override ) === '' ) {
		return $settings;
	}

	$settings['wording'] = trim( (string) $override );

	return $settings;
}

/**
 * Add the consent field to a list of field types, once.
 *
 * Used for WPForms' lists of types that may drive conditional logic, so a
 * Mailchimp connection, notification or another field can depend on whether
 * the visitor ticked the box.
 */
function pkwyc_consent_add_conditional_type( $types ) {

	$types = is_array( $types ) ? $types : array();

	if ( ! in_array( 'pkwyc_consent', $types, true ) ) {
		$types[] = 'pkwyc_consent';
	}

	return $types;
}
