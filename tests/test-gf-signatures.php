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

pkwyc_results();
