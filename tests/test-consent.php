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

// --- the message for a required box left unticked ----------------------
// Gravity Forms runs its own "required" check before the field's validate()
// and shows the field's Error Message setting, or "This field is required."
// The adapter feeds it this instead, so both platforms say the same thing.
pkwyc_assert( 'required message: default',          pkwyc_consent_required_message( $good, '' ), 'You must agree to continue.' );
pkwyc_assert( 'required message: form custom kept', pkwyc_consent_required_message( $good, 'Please tick the box.' ), 'Please tick the box.' );
pkwyc_assert( 'required message: blank custom is default', pkwyc_consent_required_message( $good, "  " ), 'You must agree to continue.' );
pkwyc_assert( 'required message: unconfigured beats custom',
	pkwyc_consent_required_message( array_merge( $good, array( 'privacy_url' => '' ) ), 'Please tick the box.' ), 'This form cannot be submitted at this time.' );

// --- why it is not usable, for the settings banner and the admin notice ---
// One reason, the first that applies, so the message can name it exactly.
pkwyc_assert( 'problem: none when configured',   pkwyc_consent_config_problem( $good ), '' );
pkwyc_assert( 'problem: none for privacy only',  pkwyc_consent_config_problem( $noterms ), '' );
pkwyc_assert( 'problem: privacy url missing',    pkwyc_consent_config_problem( array_merge( $good, array( 'privacy_url' => ' ' ) ) ), 'no_privacy_url' );
pkwyc_assert( 'problem: privacy url wins over everything else',
	pkwyc_consent_config_problem( array( 'privacy_url' => '', 'terms_url' => '', 'wording' => '' ) ), 'no_privacy_url' );
pkwyc_assert( 'problem: wording missing',        pkwyc_consent_config_problem( array_merge( $good, array( 'wording' => '' ) ) ), 'no_wording' );
// The case the old banner got wrong: privacy set, wording names {terms}, no terms url.
pkwyc_assert( 'problem: terms placeholder without terms url',
	pkwyc_consent_config_problem( array_merge( $good, array( 'terms_url' => '' ) ) ), 'terms_without_url' );
pkwyc_assert( 'problem agrees with is_configured', pkwyc_consent_config_problem( array() ) !== '', ! pkwyc_consent_is_configured( array() ) );
pkwyc_assert( 'problem message: privacy',  strpos( pkwyc_consent_config_problem_message( 'no_privacy_url' ), 'Privacy Policy URL' ) !== false, true );
pkwyc_assert( 'problem message: wording',  strpos( pkwyc_consent_config_problem_message( 'no_wording' ), 'wording' ) !== false, true );
pkwyc_assert( 'problem message: terms names the placeholder and the url',
	strpos( pkwyc_consent_config_problem_message( 'terms_without_url' ), '{terms}' ) !== false
	&& strpos( pkwyc_consent_config_problem_message( 'terms_without_url' ), 'Terms URL' ) !== false, true );
pkwyc_assert( 'problem message: terms does not blame the privacy url',
	strpos( pkwyc_consent_config_problem_message( 'terms_without_url' ), 'Privacy Policy URL' ), false );
pkwyc_assert( 'problem message: none is empty', pkwyc_consent_config_problem_message( '' ), '' );

// --- should the admin be warned, and how loudly -------------------------
// No Privacy URL: nothing on the site can work, custom wording or not.
// Site wording unusable: forms that use it refuse to submit, but a site whose
// forms all carry custom wording may be fine, so the warning is softer.
pkwyc_assert( 'notice: none when configured',          pkwyc_consent_notice_level( $good, false ), '' );
pkwyc_assert( 'notice: strong without privacy url',    pkwyc_consent_notice_level( array_merge( $good, array( 'privacy_url' => '' ) ), false ), 'strong' );
pkwyc_assert( 'notice: soft when wording is blank',    pkwyc_consent_notice_level( array_merge( $good, array( 'wording' => '' ) ), false ), 'soft' );
pkwyc_assert( 'notice: soft when terms url missing',   pkwyc_consent_notice_level( array_merge( $good, array( 'terms_url' => '' ) ), false ), 'soft' );
pkwyc_assert( 'notice: none when disabled',            pkwyc_consent_notice_level( array(), true ), '' );

// --- the kill switch ----------------------------------------------------
pkwyc_assert( 'enforcing by default', pkwyc_is_disabled(), false );
define( 'PKWYC_DISABLE', true );
pkwyc_assert( 'constant disables it', pkwyc_is_disabled(), true );

// --- the WordPress privacy page (Settings, Privacy) ---------------------
pkwyc_assert( 'urls match ignoring scheme, host case and trailing slash', pkwyc_urls_match( 'https://a.com/privacy/', 'http://A.com/privacy' ), true );
pkwyc_assert( 'urls with different paths do not match', pkwyc_urls_match( 'https://a.com/privacy/', 'https://a.com/privacy-policy/' ), false );
pkwyc_assert( 'two empty urls do not match', pkwyc_urls_match( '', '' ), false );
pkwyc_assert( 'urls ignore the fragment', pkwyc_urls_match( 'https://a.com/privacy/#cookies', 'https://a.com/privacy/' ), true );
pkwyc_assert( 'urls keep the query', pkwyc_urls_match( 'https://a.com/?page_id=3', 'https://a.com/?page_id=4' ), false );

$wp_page = array( 'url' => 'https://a.com/legal-notices/', 'title' => 'Legal Notices', 'status' => 'publish' );
$advice  = pkwyc_privacy_page_advice( '', $wp_page );
pkwyc_assert( 'empty url and a published page: suggest it', $advice['state'], 'suggest' );
pkwyc_assert( 'suggestion carries the url', $advice['url'], 'https://a.com/legal-notices/' );
pkwyc_assert( 'suggestion carries the title, so a wrong page is obvious', $advice['title'], 'Legal Notices' );
pkwyc_assert( 'draft page never suggested', pkwyc_privacy_page_advice( '', array_merge( $wp_page, array( 'status' => 'draft' ) ) )['state'], 'none' );
pkwyc_assert( 'trashed page never suggested', pkwyc_privacy_page_advice( '', array_merge( $wp_page, array( 'status' => 'trash' ) ) )['state'], 'none' );
pkwyc_assert( 'no page set: nothing to say', pkwyc_privacy_page_advice( '', null )['state'], 'none' );
pkwyc_assert( 'url set, different published page: differs', pkwyc_privacy_page_advice( 'https://a.com/privacy-policy/', $wp_page )['state'], 'differs' );
pkwyc_assert( 'url set, same page written differently: same', pkwyc_privacy_page_advice( 'http://A.com/legal-notices', $wp_page )['state'], 'same' );
pkwyc_assert( 'url set, page is a draft: nothing to compare', pkwyc_privacy_page_advice( 'https://a.com/privacy-policy/', array_merge( $wp_page, array( 'status' => 'draft' ) ) )['state'], 'none' );

// --- builder hint about Mailchimp ----------------------------------------

// --- the record kept with each entry --------------------------------------
$rec = pkwyc_consent_record( $good, '', '2026-11-13T15:00:00+00:00', '1.3.0' );
pkwyc_assert( 'record: wording is exactly what was shown', $rec['wording'], pkwyc_consent_wording_html( pkwyc_consent_effective_settings( $good, '' ) ) );
pkwyc_assert( 'record: hash is sha256 of the wording', $rec['hash'], hash( 'sha256', $rec['wording'] ) );
pkwyc_assert( 'record: time passed through', $rec['recorded_at'], '2026-11-13T15:00:00+00:00' );
pkwyc_assert( 'record: plugin version passed through', $rec['plugin_version'], '1.3.0' );
$rec_custom = pkwyc_consent_record( $good, 'Custom {company} text.', '2026-11-13T15:00:00+00:00', '1.3.0' );
pkwyc_assert( 'record: custom wording is what is recorded', strpos( $rec_custom['wording'], 'Custom Example Law Group text.' ) === 0, true );
pkwyc_assert( 'record: custom wording changes the hash', $rec_custom['hash'] !== $rec['hash'], true );
pkwyc_assert( 'record: unusable settings record no wording', pkwyc_consent_record( array_merge( $good, array( 'privacy_url' => '' ) ), '', 'x', '1.3.0' )['wording'], '' );
pkwyc_assert( 'record: exactly four keys', array_keys( $rec ), array( 'wording', 'hash', 'recorded_at', 'plugin_version' ) );

$shown = pkwyc_consent_record_html( json_encode( $rec ) );
pkwyc_assert( 'record html: shows the wording', strpos( $shown, $rec['wording'] ) !== false, true );
pkwyc_assert( 'record html: shows when and which version', strpos( $shown, '2026-11-13T15:00:00+00:00' ) !== false && strpos( $shown, '1.3.0' ) !== false, true );
pkwyc_assert( 'record html: no record names both reasons', strpos( pkwyc_consent_record_html( '' ), 'made before 1.3.0, or while the plugin was switched off' ) !== false, true );
pkwyc_assert( 'record html: broken data treated as no record', strpos( pkwyc_consent_record_html( '{oops' ), 'No wording was recorded' ) !== false, true );
pkwyc_assert( 'record html: says in effect when received, not what was seen', strpos( $shown, 'in effect when this entry was received' ) !== false, true );

// --- the emergency switch on Gravity Forms -------------------------------
// With PKWYC_DISABLE set the field class is not loaded, so Gravity Forms
// treats a stored Consent field as an unknown field that is still required
// and has no box to tick: the form could never be sent (found 2026-11-13).
$gf_form = array( 'fields' => array(
	(object) array( 'type' => 'text', 'isRequired' => true ),
	(object) array( 'type' => 'pkwyc_consent', 'isRequired' => true ),
) );
$relaxed = pkwyc_kill_switch_relax_gf_form( $gf_form );
pkwyc_assert( 'switch: consent field no longer required', $relaxed['fields'][1]->isRequired, false );
pkwyc_assert( 'switch: other required fields untouched', $relaxed['fields'][0]->isRequired, true );
pkwyc_assert( 'switch: form without fields passes through', pkwyc_kill_switch_relax_gf_form( array( 'title' => 'x' ) ), array( 'title' => 'x' ) );
pkwyc_assert( 'switch: non-array passes through', pkwyc_kill_switch_relax_gf_form( false ), false );

// Review fix: the kill switch must never touch the form editor, or saving a
// form while it is on would store the Consent field as optional for good.
pkwyc_assert( 'switch hooks: front end and validation only', pkwyc_kill_switch_gf_hooks(), array( 'gform_pre_render', 'gform_pre_validation', 'gform_pre_submission_filter' ) );

// The builder note does not depend on whether the form has a Mailchimp
// connection: that state changes in the builder without a reload, and a note
// that read it went stale. One sentence per platform, true either way.
$wpf = pkwyc_consent_mailchimp_hint( 'wpforms' );
$gfh = pkwyc_consent_mailchimp_hint( 'gravityforms' );
pkwyc_assert( 'hint says the box does nothing alone', strpos( $wpf, 'does nothing to Mailchimp on its own' ) !== false, true );
pkwyc_assert( 'hint names the wpforms rule', strpos( $wpf, 'Consent is not empty' ) !== false, true );
pkwyc_assert( 'hint points at the forms table', strpos( $wpf, 'Settings, Compliance' ) !== false, true );
pkwyc_assert( 'hint makes no claim about this form', strpos( $wpf, 'This form sends' ), false );
pkwyc_assert( 'gf hint names the gravity forms rule', strpos( $gfh, 'Consent is agreed' ) !== false, true );
pkwyc_assert( 'gf hint does not name the wpforms rule', strpos( $gfh, 'not empty' ), false );
pkwyc_assert( 'gf hint says the box does nothing alone', strpos( $gfh, 'does nothing to Mailchimp on its own' ) !== false, true );
pkwyc_assert( 'wpforms is the default platform', pkwyc_consent_mailchimp_hint(), $wpf );

// The WPForms entry screen zeroes a box's inner padding, so the record brings its own.
$padded = pkwyc_consent_record_padded_html( '' );
pkwyc_assert( 'padded record: wrapped in a padded block', strpos( $padded, '<div class="pkwyc-consent-record-body" style="padding:' ) === 0, true );
pkwyc_assert( 'padded record: carries the record itself', strpos( $padded, pkwyc_consent_record_html( '' ) ) !== false, true );

pkwyc_results();
