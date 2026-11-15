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

// Links to the guide PDF and the video, shared by the Help sidebar and the line at the top of the settings screen.
pkwyc_assert( 'guide pdf is the asset on the latest release', pkwyc_guide_pdf_url(), 'https://github.com/pkwydigital/pkwycompliance/releases/latest/download/pkwycompliance-guide.pdf' );
$video = pkwyc_video_url();
pkwyc_assert( 'video link is the published walkthrough', $video, 'https://www.youtube.com/watch?v=yFTJkiFRv90' );
pkwyc_assert( 'video url is empty or a YouTube watch link', $video === '' || preg_match( '#^https://(www\.youtube\.com/watch\?v=|youtu\.be/)[A-Za-z0-9_-]{11}$#', $video ) === 1, true );
$links = pkwyc_help_links();
pkwyc_assert( 'every help link has a label and a url', count( array_filter( $links, function ( $l ) { return $l['label'] !== '' && strpos( $l['url'], 'https://' ) === 0; } ) ), count( $links ) );
pkwyc_assert( 'help links include the pdf guide', in_array( pkwyc_guide_pdf_url(), array_column( $links, 'url' ), true ), true );
pkwyc_assert( 'help links include the readme section', in_array( pkwyc_guide_url(), array_column( $links, 'url' ), true ), true );
pkwyc_assert( 'help links include the video exactly when it is set', in_array( $video, array_column( $links, 'url' ), true ), $video !== '' );
pkwyc_assert( 'video comes first when set', $video === '' || $links[0]['url'] === $video, true );
$html = pkwyc_help_links_html();
foreach ( $links as $l ) {
	pkwyc_assert( "links html carries {$l['url']}", strpos( $html, 'href="' . esc_url( $l['url'] ) . '"' ) !== false, true );
}
pkwyc_assert( 'links open in a new tab safely', substr_count( $html, 'rel="noopener noreferrer"' ), count( $links ) );

pkwyc_results();
