<?php
/**
 * The WPForms field's front-end markup.
 *
 * The wording sits in a label wrapping the checkbox. Without a class of
 * WPForms' own it fell back to the theme's body text: 21.8px against 16px
 * field labels on Twenty Twenty-Five (found 2026-11-14). It now carries
 * wpforms-field-label-inline, the class WPForms gives its own checkbox
 * choices and GDPR agreement text.
 */

require_once __DIR__ . '/bootstrap.php';

class WPForms_Field {
	public $name;
	public $type;
	public $icon;
	public $order;
	public $group;
	public function __construct() { $this->init(); }
}

function did_action( $hook ) { return 1; }
function current_user_can( $cap ) { return false; }
function esc_js( $t ) { return addslashes( (string) $t ); }
// Renders attributes the way WPForms does: id, class, then every attr in order.
function wpforms_html_attributes( $id = '', $class = array(), $datas = array(), $atts = array() ) {
	$out = 'id="' . esc_attr( $id ) . '" class="' . esc_attr( implode( ' ', (array) $class ) ) . '"';
	foreach ( (array) $atts as $k => $v ) {
		$out .= ' ' . $k . '="' . esc_attr( $v ) . '"';
	}
	return $out;
}
class PKWYC_Test_Process { public $errors = array(); public $fields = array(); }
$GLOBALS['pkwyc_process'] = new PKWYC_Test_Process();
class PKWYC_Test_WPForms { public function obj( $n ) { return $n === 'process' ? $GLOBALS['pkwyc_process'] : null; } }
function wpforms() { return new PKWYC_Test_WPForms(); }
function absint( $n ) { return abs( (int) $n ); }
function pkwyc_settings() {
	return array( 'company' => 'Example Law Firm', 'privacy_url' => 'https://example.org/privacy/', 'terms_url' => '', 'wording' => 'Yes, {company} can email me. {privacy}' );
}

require_once __DIR__ . '/../includes/components/consent/logic.php';
require_once __DIR__ . '/../includes/components/consent/adapters/wpforms.php';

$pkwyc_field = new PKWYC_WPForms_Consent_Field();
$pkwyc_input = array(
	'properties' => array(
		'inputs' => array(
			'primary' => array( 'id' => 'wpforms-1-field_4', 'class' => array( 'wpforms-field-medium' ), 'data' => array(), 'attr' => array( 'name' => 'wpforms[fields][4]' ) ),
		),
	),
);
ob_start();
$pkwyc_field->field_display( $pkwyc_input, null, array() );
$pkwyc_html = ob_get_clean();

pkwyc_assert( 'front end renders the checkbox', strpos( $pkwyc_html, 'type="checkbox"' ) !== false, true );
pkwyc_assert( 'wording label uses the WPForms inline label class', strpos( $pkwyc_html, '<label class="wpforms-field-label-inline">' ) !== false, true );
pkwyc_assert( 'wording is inside the label', strpos( $pkwyc_html, 'Yes, Example Law Firm can email me.' ) !== false, true );

// A value WPForms puts in attr (dynamic population, a stored default) would be
// printed before value="agreed", and the browser keeps the first one, so a
// ticked box could submit something other than "agreed" (audit 2026-11-14).
$pkwyc_input['properties']['inputs']['primary']['attr']['value'] = 'prefilled';
ob_start();
$pkwyc_field->field_display( $pkwyc_input, null, array() );
$pkwyc_html2 = ob_get_clean();
pkwyc_assert( 'no populated value attribute reaches the box', strpos( $pkwyc_html2, 'prefilled' ), false );
pkwyc_assert( 'the box submits agreed', substr_count( $pkwyc_html2, 'value="agreed"' ), 1 );
pkwyc_assert( 'the field name is kept', strpos( $pkwyc_html2, 'name="wpforms[fields][4]"' ) !== false, true );

// Two Consent fields (the builder's Duplicate button, an import). The second is
// dropped at render, so it must not be validated either: a required, unseen
// duplicate refused every submission (audit 2026-11-14, high).
$two = array( 'id' => 9, 'fields' => array(
	3 => array( 'id' => 3, 'type' => 'pkwyc_consent' ),
	4 => array( 'id' => 4, 'type' => 'pkwyc_consent' ),
) );
$GLOBALS['pkwyc_process']->errors = array();
$pkwyc_field->validate( 4, '', $two );
pkwyc_assert( 'second consent field is not validated', $GLOBALS['pkwyc_process']->errors, array() );
$pkwyc_field->validate( 3, '', $two );
pkwyc_assert( 'first consent field is still enforced', isset( $GLOBALS['pkwyc_process']->errors[9][3] ), true );
pkwyc_assert( 'first consent instance helper', pkwyc_consent_wpforms_first_id( $two ), '3' );
pkwyc_assert( 'no consent field gives empty id', pkwyc_consent_wpforms_first_id( array( 'fields' => array() ) ), '' );

// The builder offers no Duplicate button on the Consent field.
pkwyc_assert( 'no duplicate button on a new consent field', pkwyc_consent_wpforms_no_duplicate( true, array( 'type' => 'pkwyc_consent' ) ), false );
pkwyc_assert( 'no duplicate button on an existing consent field', pkwyc_consent_wpforms_no_duplicate( true, array( 'type' => 'pkwyc_consent' ), array() ), false );
pkwyc_assert( 'other fields keep theirs', pkwyc_consent_wpforms_no_duplicate( true, array( 'type' => 'text' ) ), true );

// The adapter's hand-kept spec must describe the class it declares, or the
// guard checks the wrong thing (signature guard, 2026-11-15).
pkwyc_assert( 'WPForms spec matches the declared class', pkwyc_signature_spec_of( 'PKWYC_WPForms_Consent_Field' ), pkwyc_consent_wpforms_spec() );
pkwyc_assert( 'WPForms spec fits the parent stub', pkwyc_signature_problems( 'WPForms_Field', pkwyc_consent_wpforms_spec() ), array() );

// A form-plugin update that breaks the parent must not take the site down:
// the class is not declared, the failure is recorded, and the fail-closed hooks go on.
$pkwyc_broken = "<?php\n"
	. "define( 'ABSPATH', __DIR__ );\n"
	. "abstract class WPForms_Field { abstract public function init(); abstract public function field_display( \$field, \$field_atts, \$form_data, \$extra ); }\n"
	. "function did_action( \$h ) { return 1; }\n"
	. "\$GLOBALS['pkwyc_hooks'] = array();\n"
	. "function add_filter( \$h, \$c, \$p = 10, \$a = 1 ) { \$GLOBALS['pkwyc_hooks'][] = \$h; return true; }\n"
	. "function add_action( \$h, \$c, \$p = 10, \$a = 1 ) { \$GLOBALS['pkwyc_hooks'][] = \$h; return true; }\n"
	. "function has_action( \$h, \$c = false ) { return in_array( \$h, \$GLOBALS['pkwyc_hooks'], true ); }\n"
	. "require " . var_export( realpath( __DIR__ . '/../includes/components/consent/logic.php' ), true ) . ";\n"
	. "require " . var_export( realpath( __DIR__ . '/../includes/components/consent/adapters/wpforms.php' ), true ) . ";\n"
	. "echo json_encode( array( 'declared' => class_exists( 'PKWYC_WPForms_Consent_Field', false ), 'failed' => isset( \$GLOBALS['pkwyc_signature_failures']['WPForms'] ), 'hooks' => \$GLOBALS['pkwyc_hooks'] ) );\n";
$pkwyc_bf = sys_get_temp_dir() . '/pkwyc-broken-' . getmypid() . '.php';
file_put_contents( $pkwyc_bf, $pkwyc_broken );
$pkwyc_raw = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' -d error_log=/dev/null ' . escapeshellarg( $pkwyc_bf ) . ' 2>&1' );
unlink( $pkwyc_bf );
$pkwyc_res = json_decode( substr( $pkwyc_raw, (int) strpos( $pkwyc_raw, '{' ) ), true );
pkwyc_assert( 'WPForms broken parent: site did not fatal', is_array( $pkwyc_res ), true );
pkwyc_assert( 'WPForms broken parent: class not declared', $pkwyc_res['declared'] ?? null, false );
pkwyc_assert( 'WPForms broken parent: failure recorded', $pkwyc_res['failed'] ?? null, true );
pkwyc_assert( 'WPForms broken parent: fail-closed hooks on', count( array_intersect( array( 'wpforms_process_format_pkwyc_consent', 'wpforms_conditional_logic_core_get_text_based_fields' ), $pkwyc_res['hooks'] ?? array() ) ), 2 );
pkwyc_assert( 'WPForms broken parent: admin notice on', in_array( 'admin_notices', $pkwyc_res['hooks'] ?? array(), true ), true );

pkwyc_results();
