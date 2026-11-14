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
	return pkwyc_consent_config_problem( $settings ) === '';
}

/**
 * Why the settings are not usable, or an empty string if they are.
 *
 * Returns the first reason that applies, in this order: 'no_privacy_url',
 * 'no_wording', 'terms_without_url' (the wording names {terms} and there is no
 * Terms URL). One reason, so a message can say exactly what to fix rather than
 * listing every field.
 */
function pkwyc_consent_config_problem( $settings ) {

	if ( pkwyc_consent_setting( $settings, 'privacy_url' ) === '' ) {
		return 'no_privacy_url';
	}

	if ( pkwyc_consent_setting( $settings, 'wording' ) === '' ) {
		return 'no_wording';
	}

	if ( pkwyc_consent_setting( $settings, 'terms_url' ) === ''
		&& strpos( pkwyc_consent_setting( $settings, 'wording' ), '{terms}' ) !== false ) {
		return 'terms_without_url';
	}

	return '';
}

/**
 * The sentence that tells an admin what to fix, for a reason from
 * pkwyc_consent_config_problem().
 */
function pkwyc_consent_config_problem_message( $problem ) {

	switch ( $problem ) {
		case 'no_privacy_url':
			return esc_html__( 'The Privacy Policy URL is not set.', 'pkwycompliance' );
		case 'no_wording':
			return esc_html__( 'The consent wording is blank.', 'pkwycompliance' );
		case 'terms_without_url':
			return esc_html__( 'The wording uses {terms} but there is no Terms URL. Either add a Terms URL or take {terms} out of the wording.', 'pkwycompliance' );
	}

	return '';
}

/**
 * Should admins be warned that the consent field is not usable, and how loudly?
 *
 * Returns 'strong', 'soft' or an empty string for no warning.
 *
 * Strong: no Privacy URL. Every consent field on the site refuses submission,
 * custom wording or not, because the link always comes from the site settings.
 *
 * Soft: the Privacy URL is set but the site wording is not usable (blank, or
 * naming {terms} with no Terms URL). Forms using the site wording refuse
 * submission, but a site whose forms all carry their own custom wording may be
 * working perfectly, so this is worded as a check rather than an alarm.
 *
 * Nothing when the kill switch is on: that has its own notice, and nothing is
 * being enforced.
 */
function pkwyc_consent_notice_level( $settings, $disabled ) {

	if ( $disabled ) {
		return '';
	}

	$problem = pkwyc_consent_config_problem( $settings );

	if ( $problem === '' ) {
		return '';
	}

	return $problem === 'no_privacy_url' ? 'strong' : 'soft';
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
 * The message for a consent box that is required and was not ticked.
 *
 * Uses the form's own error message when it has one, otherwise the plugin's.
 * Unconfigured settings win over both: the visitor cannot fix that by ticking
 * the box, so telling them to tick it would be wrong.
 */
function pkwyc_consent_required_message( $settings, $custom ) {

	if ( ! pkwyc_consent_is_configured( $settings ) ) {
		return pkwyc_consent_error_message( 'unconfigured' );
	}

	if ( is_scalar( $custom ) && trim( (string) $custom ) !== '' ) {
		return trim( (string) $custom );
	}

	return pkwyc_consent_error_message( 'not_agreed' );
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

/**
 * Do two URLs point at the same page?
 *
 * Scheme, host case, a trailing slash and the fragment do not matter; the
 * path and the query do. Two empty URLs are not a match.
 */
function pkwyc_urls_match( $a, $b ) {

	$norm = function ( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}
		$p = parse_url( $url );
		if ( ! is_array( $p ) || empty( $p['host'] ) ) {
			return '';
		}
		$path  = isset( $p['path'] ) ? rtrim( $p['path'], '/' ) : '';
		$query = isset( $p['query'] ) ? '?' . $p['query'] : '';
		return strtolower( $p['host'] ) . $path . $query;
	};

	$na = $norm( $a );
	return $na !== '' && $na === $norm( $b );
}

/**
 * What to say about WordPress's own privacy page (Settings, Privacy).
 *
 * Never used as a silent fallback: a site can point that setting at the wrong
 * page. It is offered as a suggestion when the plugin's URL is empty, and
 * flagged when the two disagree. Only a published page counts.
 *
 * @param string     $plugin_url The Privacy Policy URL saved in this plugin.
 * @param array|null $wp_page    array( 'url', 'title', 'status' ) of the WordPress privacy page, or null.
 * @return array array( 'state' => 'suggest'|'differs'|'same'|'none', 'url' => string, 'title' => string )
 */
function pkwyc_privacy_page_advice( $plugin_url, $wp_page ) {

	$none = array( 'state' => 'none', 'url' => '', 'title' => '' );

	if ( ! is_array( $wp_page ) || ( isset( $wp_page['status'] ) ? $wp_page['status'] : '' ) !== 'publish' || empty( $wp_page['url'] ) ) {
		return $none;
	}

	$out = array(
		'url'   => (string) $wp_page['url'],
		'title' => isset( $wp_page['title'] ) ? (string) $wp_page['title'] : '',
	);

	if ( trim( (string) $plugin_url ) === '' ) {
		return array( 'state' => 'suggest' ) + $out;
	}

	return array( 'state' => pkwyc_urls_match( $plugin_url, $wp_page['url'] ) ? 'same' : 'differs' ) + $out;
}

/**
 * The note shown beside the Consent field in the form builders.
 *
 * Adding the box changes nothing in Mailchimp by itself, and nothing warns
 * about that anywhere else, so the builder says so where the field is added.
 *
 * @param bool $has_mailchimp Does this form already send people to Mailchimp?
 */
function pkwyc_consent_mailchimp_hint( $has_mailchimp ) {
	if ( $has_mailchimp ) {
		return __( 'This form sends people to Mailchimp. Ticking this box does nothing to Mailchimp on its own: open the form\'s Mailchimp connections and, on every one that subscribes people, add Consent is not empty to each rule group. Settings, Compliance shows whether that is done.', 'pkwycompliance' );
	}
	return __( 'If this form is later connected to Mailchimp, add Consent is not empty to every connection that subscribes people, or the box will make no difference.', 'pkwycompliance' );
}

/**
 * What to keep with an entry so it can later show what the visitor was shown.
 *
 * The site wording can be edited at any time, and each form can override it,
 * so the field's stored value ("agreed" or empty) alone cannot say what was
 * agreed to. This keeps the exact sentence, with its links, beside the entry.
 *
 * @param array  $settings     Site settings.
 * @param string $override     The form's custom wording, '' for the site wording.
 * @param string $now_iso8601  When it was recorded.
 * @param string $version      Plugin version.
 * @return array array( 'wording', 'hash', 'recorded_at', 'plugin_version' )
 */
function pkwyc_consent_record( $settings, $override, $now_iso8601, $version ) {
	$wording = pkwyc_consent_wording_html( pkwyc_consent_effective_settings( $settings, (string) $override ) );
	return array(
		'wording'        => $wording,
		'hash'           => hash( 'sha256', $wording ),
		'recorded_at'    => (string) $now_iso8601,
		'plugin_version' => (string) $version,
	);
}

/**
 * The record kept with an entry, as shown on the entry screen.
 *
 * @param string $json The stored record, '' when the entry has none.
 */
function pkwyc_consent_record_html( $json ) {
	$rec = json_decode( (string) $json, true );
	if ( ! is_array( $rec ) || ! isset( $rec['wording'] ) ) {
		return '<p>' . esc_html__( 'Not recorded. The entry was made before PKWY Compliance 1.3.0, which started keeping the wording with each entry.', 'pkwycompliance' ) . '</p>';
	}
	return '<p>' . esc_html__( 'Wording shown beside the Consent box when this entry was sent:', 'pkwycompliance' ) . '</p>'
		. '<blockquote style="margin:0 0 8px;padding:8px 12px;border-left:3px solid #c3c4c7;background:#f6f7f7">' . wp_kses_post( $rec['wording'] ) . '</blockquote>'
		. '<p class="description">' . sprintf(
			/* translators: 1: date and time, 2: plugin version */
			esc_html__( 'Recorded %1$s by PKWY Compliance %2$s.', 'pkwycompliance' ),
			esc_html( isset( $rec['recorded_at'] ) ? $rec['recorded_at'] : '' ),
			esc_html( isset( $rec['plugin_version'] ) ? $rec['plugin_version'] : '' )
		) . '</p>';
}
