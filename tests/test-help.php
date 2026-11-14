<?php
/**
 * The Help tab on Settings, Compliance: five tabs, each carrying what a person
 * setting the plugin up needs, and a link to the full setup guide.
 */
require_once __DIR__ . '/bootstrap.php';
define( 'PKWYC_SLUG', 'pkwycompliance' );
define( 'PKWYC_OPTION', 'pkwyc_settings' );
require_once __DIR__ . '/../includes/components/consent/logic.php';
require_once __DIR__ . '/../includes/settings.php';

$tabs = pkwyc_help_tabs();
pkwyc_assert( 'five help tabs, in order', array_column( $tabs, 'id' ), array( 'pkwyc-what', 'pkwyc-add', 'pkwyc-mailchimp', 'pkwyc-refuses', 'pkwyc-switches' ) );
foreach ( $tabs as $tab ) {
	pkwyc_assert( "tab {$tab['id']} has a title", $tab['title'] !== '', true );
	pkwyc_assert( "tab {$tab['id']} has content", strlen( strip_tags( $tab['content'] ) ) > 80, true );
}
$by = array_column( $tabs, 'content', 'id' );
foreach ( array( 'no_privacy_url', 'no_wording', 'terms_without_url' ) as $problem ) {
	$msg = pkwyc_consent_config_problem_message( $problem );
	pkwyc_assert( "refuses tab explains {$problem}", $msg !== '' && strpos( $by['pkwyc-refuses'], esc_html( $msg ) ) !== false, true );
}
pkwyc_assert( 'switches tab: no auto update line', strpos( $by['pkwyc-switches'], "define( 'PKWYC_NO_AUTO_UPDATE', true );" ) !== false, true );
pkwyc_assert( 'switches tab: disable line', strpos( $by['pkwyc-switches'], "define( 'PKWYC_DISABLE', true );" ) !== false, true );
pkwyc_assert( 'mailchimp tab warns against Add New Group', strpos( $by['pkwyc-mailchimp'], 'Add New Group' ) !== false, true );
pkwyc_assert( 'guide url points at the README section', pkwyc_guide_url(), 'https://github.com/pkwydigital/pkwycompliance#setting-it-up' );

pkwyc_results();
