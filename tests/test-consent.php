<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/components/consent/logic.php';

$good = array(
	'privacy_url' => 'https://example.com/privacy/',
	'terms_url'   => 'https://example.com/terms/',
	'wording'     => 'By submitting your email address, you agree to receive communications from {company}. You can unsubscribe at any time. You also confirm that you have read and agree to the {privacy} and {terms}.',
	'company'     => 'Example Law Group',
);

// --- is the component usable at all ------------------------------------
pkwyc_assert( 'configured when both urls present', pkwyc_consent_is_configured( $good ), true );
pkwyc_assert( 'not configured without privacy url', pkwyc_consent_is_configured( array_merge( $good, array( 'privacy_url' => '' ) ) ), false );
pkwyc_assert( 'not configured without terms url',   pkwyc_consent_is_configured( array_merge( $good, array( 'terms_url' => '' ) ) ), false );
pkwyc_assert( 'not configured without wording',     pkwyc_consent_is_configured( array_merge( $good, array( 'wording' => '' ) ) ), false );
pkwyc_assert( 'whitespace only url does not count', pkwyc_consent_is_configured( array_merge( $good, array( 'terms_url' => '   ' ) ) ), false );

// --- the sentence shown to the visitor ---------------------------------
$html = pkwyc_consent_wording_html( $good );
pkwyc_assert( 'privacy link rendered',  strpos( $html, 'href="https://example.com/privacy/"' ) !== false, true );
pkwyc_assert( 'terms link rendered',    strpos( $html, 'href="https://example.com/terms/"' ) !== false, true );
pkwyc_assert( 'links open in new tab',  substr_count( $html, 'rel="noopener noreferrer"' ), 2 );
pkwyc_assert( 'company substituted',    strpos( $html, 'Example Law Group' ) !== false, true );
pkwyc_assert( 'no placeholders left',   preg_match( '/\{(privacy|terms|company)\}/', $html ), 0 );

// Fails closed rather than rendering a sentence with dead links.
pkwyc_assert( 'unconfigured renders nothing', pkwyc_consent_wording_html( array_merge( $good, array( 'privacy_url' => '' ) ) ), '' );

// A wording that forgets the links must still produce them, or the consent
// would be to something the visitor could not read.
$nolinks = array_merge( $good, array( 'wording' => 'You agree to marketing.' ) );
pkwyc_assert( 'links appended when wording omits them', substr_count( pkwyc_consent_wording_html( $nolinks ), '<a ' ), 2 );

// --- the stored value ---------------------------------------------------
pkwyc_assert( 'agreed stored as agreed', pkwyc_consent_normalise_value( 'agreed' ), 'agreed' );
pkwyc_assert( 'anything else is empty',  pkwyc_consent_normalise_value( 'yes' ), '' );
pkwyc_assert( 'on is empty',             pkwyc_consent_normalise_value( 'on' ), '' );
pkwyc_assert( 'empty is empty',          pkwyc_consent_normalise_value( '' ), '' );
pkwyc_assert( 'array is empty',          pkwyc_consent_normalise_value( array( 'agreed' ) ), '' );

// --- the kill switch ----------------------------------------------------
pkwyc_assert( 'enforcing by default', pkwyc_is_disabled(), false );
define( 'PKWYC_DISABLE', true );
pkwyc_assert( 'constant disables it', pkwyc_is_disabled(), true );

pkwyc_results();
