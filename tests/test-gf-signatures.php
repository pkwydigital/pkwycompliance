<?php
/**
 * The Gravity Forms field class must be declarable against GF_Field.
 *
 * A child method whose signature is incompatible with the parent is a fatal
 * error at the moment the class is declared, which happens as soon as Gravity
 * Forms loads, on every request, front end and admin. Found 2026-11-13: v1.0.0
 * to v1.1.1 declared get_field_label( $force_frontend_label, ... ) without the
 * default that Gravity Forms 3.x gives it, so activating Gravity Forms beside
 * this plugin took the whole site down.
 *
 * The stub below copies the signatures from Gravity Forms 3.1.1's GF_Field for
 * every method the adapter overrides. If this file fatals, the adapter has
 * drifted from Gravity Forms. Add a method here whenever the adapter starts
 * overriding a new one.
 */

require_once __DIR__ . '/bootstrap.php';

class GF_Field {
	public $duplicatable = true;
	public $id;
	public $visibility;
	public $type;
	public $label;
	public $isRequired;
	public $labelPlacement;
	public $errorMessage;
	public $failed_validation;
	public $validation_message;
	public function get_form_editor_field_title() { return ''; }
	public function get_form_editor_button() { return array(); }
	public function is_conditional_logic_supported() { return false; }
	public function get_form_editor_field_icon() { return ''; }
	public function get_form_editor_field_settings() { return array(); }
	public function get_form_editor_inline_script_on_page_render() { return ''; }
	public function get_field_input( $form, $value = '', $entry = null ) { return ''; }
	public function get_field_css_class() { return ''; }
	public function get_field_label( $force_frontend_label = true, $value = '' ) { return 'label'; }
	public function get_value_submission( $field_values, $get_from_post_global_var = true ) { return ''; }
	public function validate( $value, $form ) {}
	public function get_value_entry_detail( $value, $entry = array(), $use_text = false, $format = 'html', $media = 'screen' ) { return $value; }
	public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) { return ''; }
}

class GF_Fields {
	public static $registered = array();
	public static function register( $field ) { self::$registered[] = $field; }
}

function did_action( $hook ) { return 1; }

require_once __DIR__ . '/../includes/components/consent/logic.php';
require_once __DIR__ . '/../includes/components/consent/adapters/gravityforms.php';

pkwyc_assert( 'consent field class declared against GF_Field', class_exists( 'PKWYC_GF_Consent_Field', false ), true );
pkwyc_assert( 'consent field registered', count( GF_Fields::$registered ), 1 );
pkwyc_assert( 'registered field is the consent type', GF_Fields::$registered[0]->type, 'pkwyc_consent' );

// One Consent field per form. Gravity Forms 3.x enforces this itself for any
// field that is not duplicatable: it refuses a second add with its own message
// and drops the Duplicate button. The old editor script looked for
// "#gform_fields li[data-type]", which Gravity Forms 3.x no longer renders, so
// it never blocked anything (found in a browser, 2026-11-13).
pkwyc_assert( 'consent field is not duplicatable', GF_Fields::$registered[0]->duplicatable, false );
pkwyc_assert( 'editor script does not rely on li[data-type]', strpos( GF_Fields::$registered[0]->get_form_editor_inline_script_on_page_render(), '#gform_fields li' ), false );

// Gravity Forms has its own built-in field called Consent in the same
// Advanced Fields group. Ours must be told apart from it in the editor.
$pkwyc_button = GF_Fields::$registered[0]->get_form_editor_button();
pkwyc_assert( 'editor button named apart from the built-in Consent field', $pkwyc_button['text'], 'PKWY Consent' );
pkwyc_assert( 'editor field title named apart from the built-in Consent field', GF_Fields::$registered[0]->get_form_editor_field_title(), 'PKWY Consent' );

// New-field defaults. Gravity Forms 3.x applies a custom field's defaults by
// calling window["SetDefaultValues_" + type](field) after its own switch; there
// is no JS filter named gform_editor_js_set_default_values (that name is a PHP
// action inside the switch). Until 1.3.2 the adapter used the filter, so it
// never ran: a new PKWY Consent field came out labelled "Untitled" and NOT
// Required (found while recording the help video, 2026-11-14). Run the real
// editor script in node, the way Gravity Forms would.
$pkwyc_script = GF_Fields::$registered[0]->get_form_editor_inline_script_on_page_render();
$pkwyc_js     = 'var window = globalThis; var document = {}; var gform = { addFilter: function(){} };'
	. 'function jQuery(){ return { on: function(){} }; }'
	. $pkwyc_script
	. ';var f = typeof window.SetDefaultValues_pkwyc_consent === "function"'
	. ' ? window.SetDefaultValues_pkwyc_consent({ type: "pkwyc_consent", label: "" }) : null;'
	. 'process.stdout.write(JSON.stringify(f));';
$pkwyc_tmp = tempnam( sys_get_temp_dir(), 'pkwyc' ) . '.js';
file_put_contents( $pkwyc_tmp, $pkwyc_js );
$pkwyc_out = json_decode( (string) shell_exec( 'node ' . escapeshellarg( $pkwyc_tmp ) . ' 2>&1' ), true );
unlink( $pkwyc_tmp );
pkwyc_assert( 'editor script defines SetDefaultValues_pkwyc_consent', is_array( $pkwyc_out ), true );
pkwyc_assert( 'new field is labelled Consent', $pkwyc_out['label'] ?? null, 'Consent' );
pkwyc_assert( 'new field is Required', $pkwyc_out['isRequired'] ?? null, true );
pkwyc_assert( 'new field hides its label', $pkwyc_out['labelPlacement'] ?? null, 'hidden_label' );

// The wording label carries Gravity Forms' own inline checkbox-label classes,
// the ones its built-in Consent field uses. A bare <label> fell back to the
// theme's body text: 21.8px against 14px field labels, line height 1, on
// Twenty Twenty-Five (found 2026-11-14).
if ( ! class_exists( 'GFCommon', false ) ) {
	class GFCommon {
		public static function is_form_editor() { return false; }
		public static function get_tabindex() { return ''; }
	}
}
if ( ! function_exists( 'absint' ) ) { function absint( $n ) { return abs( (int) $n ); } }
if ( ! function_exists( 'rgar' ) ) { function rgar( $a, $k ) { return isset( $a[ $k ] ) ? $a[ $k ] : ''; } }
if ( ! function_exists( 'pkwyc_settings' ) ) {
	function pkwyc_settings() {
		return array( 'company' => 'Example Law Firm', 'privacy_url' => 'https://example.org/privacy/', 'terms_url' => '', 'wording' => 'Yes, {company} can email me. {privacy}' );
	}
}
$pkwyc_field     = GF_Fields::$registered[0];
$pkwyc_field->id = 4;
$pkwyc_html      = $pkwyc_field->get_field_input( array( 'id' => 1 ) );
pkwyc_assert( 'front end renders the checkbox', strpos( $pkwyc_html, 'type="checkbox"' ) !== false, true );
pkwyc_assert( 'wording label uses Gravity Forms inline label classes', strpos( $pkwyc_html, '<label for="input_1_4" class="gform-field-label gform-field-label--type-inline gfield_consent_label">' ) !== false, true );

// Gravity Forms lays a checkbox and its text side by side only inside a field
// marked gfield--type-choice, which it adds by type name (checkbox, radio,
// consent). Ours names itself so the text does not drop below the box.
pkwyc_assert( 'field marks itself a choice field for Gravity Forms layout', $pkwyc_field->get_field_css_class(), 'gfield--type-choice' );

// Visibility (audit 2026-11-14): an administrative or hidden Consent field is
// never shown, so it can never be ticked. The setting is not offered, and a
// stored value is put back to visible before render and validation.
$settings_list = ( new PKWYC_GF_Consent_Field() )->get_form_editor_field_settings();
pkwyc_assert( 'no visibility setting offered', in_array( 'visibility_setting', $settings_list, true ), false );
$stored = new PKWYC_GF_Consent_Field(); $stored->type = 'pkwyc_consent'; $stored->visibility = 'administrative';
$other  = new PKWYC_GF_Consent_Field(); $other->type = 'text'; $other->visibility = 'hidden';
$out = pkwyc_consent_gf_force_visible( array( 'fields' => array( $stored, $other ) ) );
pkwyc_assert( 'stored administrative consent field shown', $out['fields'][0]->visibility, 'visible' );
pkwyc_assert( 'other fields untouched', $out['fields'][1]->visibility, 'hidden' );
pkwyc_assert( 'non-form passes through', pkwyc_consent_gf_force_visible( false ), false );

pkwyc_results();
