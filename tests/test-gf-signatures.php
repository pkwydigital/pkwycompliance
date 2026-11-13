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

pkwyc_results();
