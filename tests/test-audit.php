<?php
/**
 * Does each Mailchimp connection or feed only subscribe people who ticked the
 * Consent box? Shapes follow WPForms 2.0.4 + WPForms Mailchimp 2.7.0 and the
 * Gravity Forms 3.1.1 feed add-on framework.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/components/consent/audit.php';

$consent = '12';

function pkwyc_t_rule( $field, $operator, $value = '' ) {
	return array( 'field' => $field, 'operator' => $operator, 'value' => $value );
}
function pkwyc_t_conn( $groups, $type = 'go', $action = 'subscribe', $logic = '1' ) {
	return array( 'connection_name' => 'Lead', 'action' => $action, 'conditional_logic' => $logic, 'conditional_type' => $type, 'conditionals' => $groups );
}

// --- WPForms, process this connection if ... ----------------------------
$area    = pkwyc_t_rule( '5', '==', '1' );
$consent_rule = pkwyc_t_rule( '12', '!e' );
pkwyc_assert( 'one group with Consent not empty: gated', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( $area, $consent_rule ) ) ), $consent ), 'gated' );
pkwyc_assert( 'one group without Consent: ungated', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( $area ) ) ), $consent ), 'ungated' );

$url1 = pkwyc_t_rule( '20', 'c', '/a/' );
$url2 = pkwyc_t_rule( '20', 'c', '/b/' );
$url3 = pkwyc_t_rule( '20', 'c', '/c/' );
pkwyc_assert( 'three groups, Consent in two: partial', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( $url1, $consent_rule ), array( $url2, $consent_rule ), array( $url3 ) ) ), $consent ), 'partial' );
pkwyc_assert( 'three groups, Consent in all: gated', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( $url1, $consent_rule ), array( $url2, $consent_rule ), array( $url3, $consent_rule ) ) ), $consent ), 'gated' );
pkwyc_assert( 'Consent is agreed counts as ticked', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( pkwyc_t_rule( '12', '==', 'agreed' ) ) ) ), $consent ), 'gated' );
pkwyc_assert( 'Consent is agreed, any case', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( pkwyc_t_rule( '12', '==', 'Agreed ' ) ) ) ), $consent ), 'gated' );
pkwyc_assert( 'Consent is empty in a go rule does not gate', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( pkwyc_t_rule( '12', 'e' ) ) ) ), $consent ), 'ungated' );

pkwyc_assert( 'logic off: ungated', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( $consent_rule ) ), 'go', 'subscribe', '' ), $consent ), 'ungated' );
pkwyc_assert( 'logic on but no groups: ungated', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array() ), $consent ), 'ungated' );
pkwyc_assert( 'record_event is not a subscribe connection', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array(), 'go', 'record_event' ), $consent ), 'not_subscribe' );
pkwyc_assert( 'unsubscribe is not a subscribe connection', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array(), 'go', 'unsubscribe' ), $consent ), 'not_subscribe' );

// Review Focus 1: "Don't process this connection if Consent is empty" is valid.
pkwyc_assert( 'stop if Consent empty: gated', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( pkwyc_t_rule( '12', 'e' ) ) ), 'stop' ), $consent ), 'gated' );
pkwyc_assert( 'stop if Consent empty OR another group: gated', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( pkwyc_t_rule( '30', '==', 'Low' ) ), array( pkwyc_t_rule( '12', 'e' ) ) ), 'stop' ), $consent ), 'gated' );
pkwyc_assert( 'stop if Consent empty AND something else: ungated', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( pkwyc_t_rule( '12', 'e' ), pkwyc_t_rule( '30', '==', 'Low' ) ) ), 'stop' ), $consent ), 'ungated' );
pkwyc_assert( 'stop if Consent not empty: ungated', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( $consent_rule ) ), 'stop' ), $consent ), 'ungated' );

// Review Focus 2: a rule left on an old Consent field id does nothing.
pkwyc_assert( 'rule on a deleted Consent field id: ungated', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( pkwyc_t_rule( '7', '!e' ) ) ) ), $consent ), 'ungated' );
pkwyc_assert( 'integer field ids compare as strings', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( pkwyc_t_rule( 12, '!e' ) ) ) ), 12 ), 'gated' );
pkwyc_assert( 'no Consent field on the form: ungated', pkwyc_audit_wpforms_connection( pkwyc_t_conn( array( array( $consent_rule ) ) ), '' ), 'ungated' );

// --- Gravity Forms feeds -------------------------------------------------
function pkwyc_t_feed( $rules, $logic_type = 'all', $on = '1' ) {
	return array(
		'feed_condition_conditional_logic'        => $on,
		'feed_condition_conditional_logic_object' => array( 'conditionalLogic' => array( 'actionType' => 'show', 'logicType' => $logic_type, 'rules' => $rules ) ),
	);
}
$gf_consent = array( 'fieldId' => '12', 'operator' => 'isnot', 'value' => '' );
$gf_other   = array( 'fieldId' => '3', 'operator' => 'is', 'value' => 'Yes' );
pkwyc_assert( 'gf: all + Consent is not empty: gated', pkwyc_audit_gf_feed( pkwyc_t_feed( array( $gf_other, $gf_consent ) ), $consent ), 'gated' );
pkwyc_assert( 'gf: Consent is agreed: gated', pkwyc_audit_gf_feed( pkwyc_t_feed( array( array( 'fieldId' => '12', 'operator' => 'is', 'value' => 'agreed' ) ) ), $consent ), 'gated' );
pkwyc_assert( 'gf: any with Consent and another rule: ungated', pkwyc_audit_gf_feed( pkwyc_t_feed( array( $gf_other, $gf_consent ), 'any' ), $consent ), 'ungated' );
pkwyc_assert( 'gf: any with only the Consent rule: gated', pkwyc_audit_gf_feed( pkwyc_t_feed( array( $gf_consent ), 'any' ), $consent ), 'gated' );
pkwyc_assert( 'gf: logic off: ungated', pkwyc_audit_gf_feed( pkwyc_t_feed( array( $gf_consent ), 'all', '0' ), $consent ), 'ungated' );
pkwyc_assert( 'gf: no condition at all: ungated', pkwyc_audit_gf_feed( array(), $consent ), 'ungated' );
pkwyc_assert( 'gf: Consent rule on another field id: ungated', pkwyc_audit_gf_feed( pkwyc_t_feed( array( array( 'fieldId' => '7', 'operator' => 'isnot', 'value' => '' ) ) ), $consent ), 'ungated' );

// --- labels --------------------------------------------------------------
foreach ( array( 'gated', 'partial', 'ungated', 'not_subscribe' ) as $status ) {
	pkwyc_assert( "label for {$status}", pkwyc_audit_status_label( $status ) !== '', true );
}

// --- one form's report ---------------------------------------------------
$form_data = array(
	'fields'    => array(
		'1'  => array( 'id' => '1', 'type' => 'name' ),
		'12' => array( 'id' => '12', 'type' => 'pkwyc_consent' ),
	),
	'providers' => array(
		'mailchimpv3' => array(
			'conn_a' => pkwyc_t_conn( array( array( $area, $consent_rule ) ) ),
			'conn_b' => array_merge( pkwyc_t_conn( array() ), array( 'connection_name' => 'Event', 'action' => 'record_event' ) ),
		),
		'constant-contact-v3' => array(),
	),
);
$report = pkwyc_audit_wpforms_form( $form_data );
pkwyc_assert( 'form report: consent field found', $report['consent_field_id'], '12' );
pkwyc_assert( 'form report: one row per Mailchimp connection', count( $report['connections'] ), 2 );
pkwyc_assert( 'form report: connection name and status', $report['connections'][0], array( 'id' => 'conn_a', 'name' => 'Lead', 'status' => 'gated' ) );
pkwyc_assert( 'form report: record event left for a person', $report['connections'][1]['status'], 'not_subscribe' );
pkwyc_assert( 'form without Consent: no report', pkwyc_audit_wpforms_form( array( 'fields' => array( array( 'id' => '1', 'type' => 'name' ) ) ) ), null );
pkwyc_assert( 'form with Consent and no Mailchimp: empty connections', pkwyc_audit_wpforms_form( array( 'fields' => array( array( 'id' => '4', 'type' => 'pkwyc_consent' ) ) ) )['connections'], array() );
pkwyc_assert( 'connection with no name falls back to its id', pkwyc_audit_wpforms_form( array( 'fields' => array( array( 'id' => '12', 'type' => 'pkwyc_consent' ) ), 'providers' => array( 'mailchimpv3' => array( 'c9' => array( 'action' => 'subscribe' ) ) ) ) )['connections'][0]['name'], 'c9' );

$gf_feeds = array(
	array( 'id' => 5, 'is_active' => '1', 'meta' => array_merge( pkwyc_t_feed( array( $gf_consent ) ), array( 'feedName' => 'Newsletter' ) ) ),
	array( 'id' => 6, 'is_active' => '0', 'meta' => array( 'feedName' => 'Old' ) ),
);
$gf_rows = pkwyc_audit_gf_connections( $gf_feeds, '12' );
pkwyc_assert( 'gf report: active feeds only', count( $gf_rows ), 1 );
pkwyc_assert( 'gf report: feed name and status', $gf_rows[0], array( 'id' => '5', 'name' => 'Newsletter', 'status' => 'gated' ) );

pkwyc_results();
