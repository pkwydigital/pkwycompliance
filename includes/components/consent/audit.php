<?php
/**
 * Does a Mailchimp connection only take people who ticked the Consent box?
 *
 * Adding the Consent field to a form changes nothing in Mailchimp by itself:
 * every subscribe connection needs a rule on it. These functions read a
 * connection's rules and say whether that rule is there. Pure: no WordPress
 * calls, so they are tested from the terminal.
 *
 * Statuses: 'gated' (only people who ticked are subscribed), 'partial' (some
 * rule groups check Consent, others do not), 'ungated' (nothing checks it),
 * 'not_subscribe' (the connection does something other than subscribe, so a
 * person has to judge it).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is this WPForms rule "Consent is ticked"?
 */
function pkwyc_audit_rule_is_ticked( $rule, $consent_field_id ) {
	if ( ! is_array( $rule ) || ! isset( $rule['field'], $rule['operator'] ) || (string) $rule['field'] !== (string) $consent_field_id ) {
		return false;
	}
	if ( $rule['operator'] === '!e' ) {
		return true;
	}
	return $rule['operator'] === '==' && strtolower( trim( (string) ( isset( $rule['value'] ) ? $rule['value'] : '' ) ) ) === 'agreed';
}

/**
 * Is this WPForms rule "Consent is empty"?
 */
function pkwyc_audit_rule_is_unticked( $rule, $consent_field_id ) {
	return is_array( $rule ) && isset( $rule['field'], $rule['operator'] )
		&& (string) $rule['field'] === (string) $consent_field_id
		&& $rule['operator'] === 'e';
}

/**
 * One WPForms provider connection (form_data['providers']['mailchimpv3'][id]).
 *
 * WPForms ORs the groups and ANDs the rules inside a group; "stop" inverts the
 * whole result. So "process if" is gated only when every group requires
 * Consent ticked, and "don't process if" is gated when some group is nothing
 * but "Consent is empty".
 *
 * @param array  $connection       The connection array.
 * @param string $consent_field_id The form's Consent field id, '' if none.
 * @return string 'gated'|'partial'|'ungated'|'not_subscribe'
 */
function pkwyc_audit_wpforms_connection( $connection, $consent_field_id ) {

	if ( ( isset( $connection['action'] ) ? $connection['action'] : 'subscribe' ) !== 'subscribe' ) {
		return 'not_subscribe';
	}

	$groups = isset( $connection['conditionals'] ) && is_array( $connection['conditionals'] ) ? array_filter( $connection['conditionals'], 'is_array' ) : array();

	if ( (string) $consent_field_id === '' || empty( $connection['conditional_logic'] ) || empty( $groups ) ) {
		return 'ungated';
	}

	if ( ( isset( $connection['conditional_type'] ) ? $connection['conditional_type'] : 'go' ) === 'stop' ) {
		foreach ( $groups as $group ) {
			if ( empty( $group ) ) {
				continue;
			}
			$only_unticked = true;
			foreach ( $group as $rule ) {
				if ( ! pkwyc_audit_rule_is_unticked( $rule, $consent_field_id ) ) {
					$only_unticked = false;
					break;
				}
			}
			if ( $only_unticked ) {
				return 'gated';
			}
		}
		return 'ungated';
	}

	$with = 0;
	foreach ( $groups as $group ) {
		foreach ( $group as $rule ) {
			if ( pkwyc_audit_rule_is_ticked( $rule, $consent_field_id ) ) {
				$with++;
				break;
			}
		}
	}

	if ( $with === 0 ) {
		return 'ungated';
	}
	return $with === count( $groups ) ? 'gated' : 'partial';
}

/**
 * One Gravity Forms feed's meta (feed['meta']), from the feed add-on framework.
 *
 * Gravity Forms has a single rule set, matched on all or any of its rules.
 *
 * @param array  $feed_meta        The feed meta.
 * @param string $consent_field_id The form's Consent field id, '' if none.
 * @return string 'gated'|'ungated'
 */
function pkwyc_audit_gf_feed( $feed_meta, $consent_field_id ) {

	if ( (string) $consent_field_id === '' || empty( $feed_meta['feed_condition_conditional_logic'] ) ) {
		return 'ungated';
	}

	$logic = isset( $feed_meta['feed_condition_conditional_logic_object']['conditionalLogic'] ) ? $feed_meta['feed_condition_conditional_logic_object']['conditionalLogic'] : array();
	$rules = isset( $logic['rules'] ) && is_array( $logic['rules'] ) ? $logic['rules'] : array();

	$ticked = 0;
	foreach ( $rules as $rule ) {
		if ( ! is_array( $rule ) || ! isset( $rule['fieldId'], $rule['operator'] ) || (string) $rule['fieldId'] !== (string) $consent_field_id ) {
			continue;
		}
		$value = strtolower( trim( (string) ( isset( $rule['value'] ) ? $rule['value'] : '' ) ) );
		if ( ( $rule['operator'] === 'isnot' && $value === '' ) || ( $rule['operator'] === 'is' && $value === 'agreed' ) ) {
			$ticked++;
		}
	}

	if ( $ticked === 0 ) {
		return 'ungated';
	}

	if ( ( isset( $logic['logicType'] ) ? $logic['logicType'] : 'all' ) === 'any' ) {
		return $ticked === count( $rules ) ? 'gated' : 'ungated';
	}

	return 'gated';
}

/**
 * One WPForms form: its Consent field and the status of each Mailchimp connection.
 *
 * @param array $form_data Decoded form post_content.
 * @return array|null array( 'consent_field_id' => string, 'connections' => array( array( 'id', 'name', 'status' ) ) ), or null when the form has no Consent field.
 */
function pkwyc_audit_wpforms_form( $form_data ) {

	$consent_id = '';
	foreach ( ( isset( $form_data['fields'] ) && is_array( $form_data['fields'] ) ? $form_data['fields'] : array() ) as $field ) {
		if ( isset( $field['type'], $field['id'] ) && $field['type'] === 'pkwyc_consent' ) {
			$consent_id = (string) $field['id'];
			break;
		}
	}
	if ( $consent_id === '' ) {
		return null;
	}

	$rows        = array();
	$connections = isset( $form_data['providers']['mailchimpv3'] ) && is_array( $form_data['providers']['mailchimpv3'] ) ? $form_data['providers']['mailchimpv3'] : array();
	foreach ( $connections as $id => $connection ) {
		if ( ! is_array( $connection ) ) {
			continue;
		}
		$name   = isset( $connection['connection_name'] ) ? trim( (string) $connection['connection_name'] ) : '';
		$rows[] = array(
			'id'     => (string) $id,
			'name'   => $name !== '' ? $name : (string) $id,
			'status' => pkwyc_audit_wpforms_connection( $connection, $consent_id ),
		);
	}

	return array( 'consent_field_id' => $consent_id, 'connections' => $rows );
}

/**
 * A Gravity Forms form's active Mailchimp feeds and their status.
 *
 * @param array  $feeds            Feeds as GFAPI::get_feeds() returns them.
 * @param string $consent_field_id The form's Consent field id.
 * @return array array( array( 'id', 'name', 'status' ) )
 */
function pkwyc_audit_gf_connections( $feeds, $consent_field_id ) {

	$rows = array();
	foreach ( is_array( $feeds ) ? $feeds : array() as $feed ) {
		if ( ! is_array( $feed ) || empty( $feed['is_active'] ) ) {
			continue;
		}
		$meta   = isset( $feed['meta'] ) && is_array( $feed['meta'] ) ? $feed['meta'] : array();
		$name   = isset( $meta['feedName'] ) ? trim( (string) $meta['feedName'] ) : '';
		$rows[] = array(
			'id'     => (string) ( isset( $feed['id'] ) ? $feed['id'] : '' ),
			'name'   => $name !== '' ? $name : (string) ( isset( $feed['id'] ) ? $feed['id'] : '' ),
			'status' => pkwyc_audit_gf_feed( $meta, $consent_field_id ),
		);
	}
	return $rows;
}

/**
 * The words shown for a status on the settings screen.
 */
function pkwyc_audit_status_label( $status ) {
	switch ( $status ) {
		case 'gated':
			return __( 'Checks Consent. Only people who tick the box are subscribed.', 'pkwycompliance' );
		case 'partial':
			return __( 'Checks Consent in some rule groups but not all. Mailchimp will subscribe some people who did not tick the box.', 'pkwycompliance' );
		case 'not_subscribe':
			return __( 'Does not subscribe anyone. Check by hand whether it should depend on Consent.', 'pkwycompliance' );
		default:
			return __( 'Does not check Consent. Mailchimp will subscribe people who did not tick the box.', 'pkwycompliance' );
	}
}
