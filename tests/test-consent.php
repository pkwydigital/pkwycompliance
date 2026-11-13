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
// Terms is optional: plenty of sites have a privacy policy and no terms page.
// But wording that names {terms} with no Terms URL would print a dead
// placeholder, so that combination fails closed.
pkwyc_assert( 'not configured when wording names terms but no terms url', pkwyc_consent_is_configured( array_merge( $good, array( 'terms_url' => '' ) ) ), false );
$noterms = array_merge( $good, array( 'terms_url' => '', 'wording' => 'Yes, {company} can send me marketing emails. I can unsubscribe at any time.' ) );
pkwyc_assert( 'configured without terms url when wording does not name terms', pkwyc_consent_is_configured( $noterms ), true );
pkwyc_assert( 'privacy only: one link',            substr_count( pkwyc_consent_wording_html( $noterms ), '<a ' ), 1 );
pkwyc_assert( 'privacy only: privacy link present', strpos( pkwyc_consent_wording_html( $noterms ), 'href="https://example.com/privacy/"' ) !== false, true );
pkwyc_assert( 'privacy only: appended sentence',   strpos( pkwyc_consent_wording_html( $noterms ), 'See our <a ' ) !== false && strpos( pkwyc_consent_wording_html( $noterms ), ' and ' ) === false, true );
$privonly = array_merge( $noterms, array( 'wording' => 'Read the {privacy}.' ) );
pkwyc_assert( 'privacy placeholder alone: nothing appended', substr_count( pkwyc_consent_wording_html( $privonly ), '<a ' ), 1 );
pkwyc_assert( 'not configured without wording',     pkwyc_consent_is_configured( array_merge( $good, array( 'wording' => '' ) ) ), false );
pkwyc_assert( 'whitespace only url does not count', pkwyc_consent_is_configured( array_merge( $good, array( 'privacy_url' => '   ' ) ) ), false );

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

// --- required or optional, per form ------------------------------------
pkwyc_assert( 'required and ticked passes',     pkwyc_consent_validation_error( $good, true, 'agreed' ), '' );
pkwyc_assert( 'required and unticked fails',    pkwyc_consent_validation_error( $good, true, '' ), 'not_agreed' );
pkwyc_assert( 'required and forged value fails', pkwyc_consent_validation_error( $good, true, 'yes' ), 'not_agreed' );
pkwyc_assert( 'optional and ticked passes',     pkwyc_consent_validation_error( $good, false, 'agreed' ), '' );
pkwyc_assert( 'optional and unticked passes',   pkwyc_consent_validation_error( $good, false, '' ), '' );

// Fails closed either way: optional does not mean the wording may be missing.
$unconfigured = array_merge( $good, array( 'privacy_url' => '' ) );
pkwyc_assert( 'required and unconfigured fails', pkwyc_consent_validation_error( $unconfigured, true, 'agreed' ), 'unconfigured' );
pkwyc_assert( 'optional and unconfigured fails', pkwyc_consent_validation_error( $unconfigured, false, '' ), 'unconfigured' );

// The per-form switch is "optional", so a form saved before it existed, or
// with anything unexpected in it, stays required.
pkwyc_assert( 'missing flag means required', pkwyc_consent_is_required( null ), true );
pkwyc_assert( 'empty flag means required',   pkwyc_consent_is_required( '' ), true );
pkwyc_assert( 'flag 1 means optional',       pkwyc_consent_is_required( '1' ), false );
pkwyc_assert( 'flag true means optional',    pkwyc_consent_is_required( true ), false );
pkwyc_assert( 'flag 0 means required',       pkwyc_consent_is_required( '0' ), true );

// --- custom wording on one form -----------------------------------------
$custom = 'Yes, {company} can send me marketing emails. I can unsubscribe at any time.';

pkwyc_assert( 'no override keeps site wording',         pkwyc_consent_effective_settings( $good, '' ), $good );
pkwyc_assert( 'whitespace override keeps site wording', pkwyc_consent_effective_settings( $good, "  \n " ), $good );
pkwyc_assert( 'missing override keeps site wording',    pkwyc_consent_effective_settings( $good, null ), $good );
pkwyc_assert( 'array override is ignored',              pkwyc_consent_effective_settings( $good, array( 'x' ) ), $good );

$over = pkwyc_consent_wording_html( pkwyc_consent_effective_settings( $good, $custom ) );
pkwyc_assert( 'override replaces site wording', strpos( $over, 'can send me marketing emails' ) !== false && strpos( $over, 'By submitting' ) === false, true );
pkwyc_assert( 'override gets company substituted', strpos( $over, 'Yes, Example Law Group can' ) !== false, true );
pkwyc_assert( 'override without links still gets both links', substr_count( $over, '<a ' ), 2 );

// An override is wording only. It never stands in for the links.
pkwyc_assert( 'override does not excuse a missing url',
	pkwyc_consent_validation_error( pkwyc_consent_effective_settings( array_merge( $good, array( 'privacy_url' => '' ) ), $custom ), false, '' ), 'unconfigured' );

// A site that only ever uses custom wording does not also need the default.
pkwyc_assert( 'override is enough when site wording is blank',
	pkwyc_consent_is_configured( pkwyc_consent_effective_settings( array_merge( $good, array( 'wording' => '' ) ), $custom ) ), true );

// --- conditional logic --------------------------------------------------
// WPForms only offers field types on its own lists; the consent field joins
// them as a text-like field, so rules read "is not empty" or "is agreed".
pkwyc_assert( 'consent type added to list', pkwyc_consent_add_conditional_type( array( 'text', 'email' ) ), array( 'text', 'email', 'pkwyc_consent' ) );
pkwyc_assert( 'consent type not added twice', pkwyc_consent_add_conditional_type( array( 'text', 'pkwyc_consent' ) ), array( 'text', 'pkwyc_consent' ) );
pkwyc_assert( 'non array list becomes a list', pkwyc_consent_add_conditional_type( null ), array( 'pkwyc_consent' ) );

// --- the kill switch ----------------------------------------------------
pkwyc_assert( 'enforcing by default', pkwyc_is_disabled(), false );
define( 'PKWYC_DISABLE', true );
pkwyc_assert( 'constant disables it', pkwyc_is_disabled(), true );

pkwyc_results();
