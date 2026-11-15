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
function wpforms_html_attributes( $id = '', $class = array(), $datas = array(), $atts = array() ) {
	return 'id="' . esc_attr( $id ) . '" class="' . esc_attr( implode( ' ', (array) $class ) ) . '"';
}
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

pkwyc_results();
