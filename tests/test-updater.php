<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/updater.php';

// --- deciding whether a release is newer -------------------------------
pkwyc_assert( 'newer release wins',        pkwyc_update_is_newer( '1.0.0', '1.0.1' ), true );
pkwyc_assert( 'same version does not',     pkwyc_update_is_newer( '1.0.1', '1.0.1' ), false );
pkwyc_assert( 'older release does not',    pkwyc_update_is_newer( '1.1.0', '1.0.9' ), false );
pkwyc_assert( 'v prefix tolerated',        pkwyc_update_is_newer( '1.0.0', 'v1.0.1' ), true );
pkwyc_assert( 'whitespace tolerated',      pkwyc_update_is_newer( '1.0.0', ' 1.0.1 ' ), true );
pkwyc_assert( 'empty tag is not newer',    pkwyc_update_is_newer( '1.0.0', '' ), false );
pkwyc_assert( 'rubbish tag is not newer',  pkwyc_update_is_newer( '1.0.0', 'latest' ), false );
pkwyc_assert( 'prerelease is not newer',   pkwyc_update_is_newer( '1.0.0', '1.0.1-beta1' ), false );

// --- the GitHub zip unpacks to the wrong folder name -------------------
// GitHub names the zipball <owner>-<repo>-<sha>, which would rename the plugin
// directory on every update and silently deactivate it.
pkwyc_assert( 'github folder is corrected',
	pkwyc_update_target_dirname( 'pkwydigital-pkwycompliance-8f3a9c1', 'pkwycompliance' ),
	'pkwycompliance' );
pkwyc_assert( 'already correct is untouched',
	pkwyc_update_target_dirname( 'pkwycompliance', 'pkwycompliance' ),
	'pkwycompliance' );
pkwyc_assert( 'an unrelated folder is left alone',
	pkwyc_update_target_dirname( 'some-other-plugin', 'pkwycompliance' ),
	'some-other-plugin' );

// --- which release asset to download -----------------------------------
$release = array(
	'tag_name' => 'v1.2.0',
	'zipball_url' => 'https://api.github.com/repos/pkwydigital/pkwycompliance/zipball/v1.2.0',
	'assets' => array(
		array( 'name' => 'notes.txt', 'browser_download_url' => 'https://example.com/notes.txt' ),
		array( 'name' => 'pkwycompliance.zip', 'browser_download_url' => 'https://example.com/pkwycompliance.zip' ),
	),
);
pkwyc_assert( 'a built zip asset is preferred',
	pkwyc_update_package_url( $release ),
	'https://example.com/pkwycompliance.zip' );
pkwyc_assert( 'falls back to the zipball',
	pkwyc_update_package_url( array( 'tag_name' => 'v1.2.0', 'zipball_url' => 'https://example.com/zipball' ) ),
	'https://example.com/zipball' );
pkwyc_assert( 'nothing usable returns empty',
	pkwyc_update_package_url( array( 'tag_name' => 'v1.2.0' ) ),
	'' );

pkwyc_results();
