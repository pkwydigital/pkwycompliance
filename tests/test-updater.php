<?php
require_once __DIR__ . '/bootstrap.php';
define( 'PKWYC_BASENAME', 'pkwycompliance/pkwycompliance.php' );
define( 'PKWYC_SLUG', 'pkwycompliance' );
define( 'PKWYC_UPDATE_REPO', 'pkwydigital/pkwycompliance' );
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
// Only the exact built zip under this repository's releases (audit 2026-11-14):
// the first ".zip" by name could be anything attached, and the zipball carries
// tests/ and .github/ and unpacks to the wrong folder.
$ours = 'https://github.com/pkwydigital/pkwycompliance/releases/download/v1.2.0/pkwycompliance.zip';
$release = array(
	'tag_name' => 'v1.2.0',
	'zipball_url' => 'https://api.github.com/repos/pkwydigital/pkwycompliance/zipball/v1.2.0',
	'assets' => array(
		array( 'name' => 'aaa-other.zip', 'browser_download_url' => 'https://github.com/pkwydigital/pkwycompliance/releases/download/v1.2.0/aaa-other.zip' ),
		array( 'name' => 'notes.txt', 'browser_download_url' => 'https://example.com/notes.txt' ),
		array( 'name' => 'pkwycompliance.zip', 'browser_download_url' => $ours ),
	),
);
pkwyc_assert( 'the exact built zip is chosen over another zip', pkwyc_update_package_url( $release ), $ours );
pkwyc_assert( 'no zipball fallback',
	pkwyc_update_package_url( array( 'tag_name' => 'v1.2.0', 'zipball_url' => 'https://api.github.com/repos/pkwydigital/pkwycompliance/zipball/v1.2.0' ) ),
	'' );
pkwyc_assert( 'a pkwycompliance.zip hosted elsewhere is refused',
	pkwyc_update_package_url( array( 'assets' => array( array( 'name' => 'pkwycompliance.zip', 'browser_download_url' => 'https://example.com/pkwycompliance.zip' ) ) ) ),
	'' );
pkwyc_assert( 'nothing usable returns empty', pkwyc_update_package_url( array( 'tag_name' => 'v1.2.0' ) ), '' );

// --- is a package ours ----------------------------------------------------
pkwyc_assert( 'ours: release download', pkwyc_update_package_is_ours( $ours ), true );
pkwyc_assert( 'ours: other repo refused', pkwyc_update_package_is_ours( 'https://github.com/someone/pkwycompliance/releases/download/v9/pkwycompliance.zip' ), false );
pkwyc_assert( 'ours: wordpress.org refused', pkwyc_update_package_is_ours( 'https://downloads.wordpress.org/plugin/pkwycompliance.9.0.zip' ), false );
pkwyc_assert( 'ours: lookalike host refused', pkwyc_update_package_is_ours( 'https://github.com/pkwydigital/pkwycompliance.evil.example/x.zip' ), false );
pkwyc_assert( 'ours: http refused', pkwyc_update_package_is_ours( 'http://github.com/pkwydigital/pkwycompliance/releases/download/v1/pkwycompliance.zip' ), false );

// --- forced auto-update only for our own package --------------------------
// A wordpress.org plugin with the same slug would otherwise be auto-installed
// on every site (audit 2026-11-14).
$item_ours  = (object) array( 'plugin' => PKWYC_BASENAME, 'package' => $ours );
$item_other = (object) array( 'plugin' => PKWYC_BASENAME, 'package' => 'https://downloads.wordpress.org/plugin/pkwycompliance.9.0.zip' );
pkwyc_assert( 'auto: our package is forced on', pkwyc_update_force_auto( null, $item_ours ), true );
pkwyc_assert( 'auto: a foreign package keeps the site default', pkwyc_update_force_auto( null, $item_other ), null );
pkwyc_assert( 'auto: another plugin untouched', pkwyc_update_force_auto( false, (object) array( 'plugin' => 'x/x.php', 'package' => $ours ) ), false );

// --- the unpacked package must be this plugin at the offered version -------
// A zip with the wrong root would make WordPress delete pkwycompliance/ on every
// site, updater included, with no way back (audit 2026-11-14).
$tmp = sys_get_temp_dir() . '/pkwyc-src-' . getmypid();
@mkdir( $tmp . '/pkwycompliance', 0777, true );
file_put_contents( $tmp . '/pkwycompliance/pkwycompliance.php', "<?php\n/**\n * Plugin Name: PKWY Compliance\n * Version:     1.4.0\n */\n" );
pkwyc_assert( 'source: right plugin and version passes', pkwyc_update_source_problem( $tmp . '/pkwycompliance/', '1.4.0' ), '' );
pkwyc_assert( 'source: version mismatch refused', pkwyc_update_source_problem( $tmp . '/pkwycompliance/', '1.5.0' ) !== '', true );
pkwyc_assert( 'source: wrong root refused', pkwyc_update_source_problem( $tmp . '/', '1.4.0' ) !== '', true );
@mkdir( $tmp . '/other', 0777, true );
file_put_contents( $tmp . '/other/pkwycompliance.php', "<?php\n/**\n * Plugin Name: Something Else\n * Version:     1.4.0\n */\n" );
pkwyc_assert( 'source: another plugin refused', pkwyc_update_source_problem( $tmp . '/other/', '1.4.0' ) !== '', true );
pkwyc_assert( 'source: unknown expected version checks identity only', pkwyc_update_source_problem( $tmp . '/pkwycompliance/', '' ), '' );

// --- "Check again" on Dashboard, Updates refetches the release -------------
pkwyc_assert( 'refetch on force-check', pkwyc_update_should_refetch( array( 'force-check' => '1' ) ), true );
pkwyc_assert( 'no refetch otherwise', pkwyc_update_should_refetch( array() ), false );

// --- the header names where updates come from ------------------------------
$head = file_get_contents( __DIR__ . '/../pkwycompliance.php' );
pkwyc_assert( 'header: Update URI points at the repo', (bool) preg_match( '/^ \* Update URI:\s+https:\/\/github\.com\/pkwydigital\/pkwycompliance\s*$/m', $head ), true );

// --- signed releases (2026-11-15) -------------------------------------------
// Every update must carry pkwycompliance.zip.sig, an Ed25519 signature made
// with a key that lives only in Parkway's Keychain. Taking over the GitHub
// account is then not enough to push code to every site.
pkwyc_assert( 'two trusted keys, primary and backup', pkwyc_update_signing_keys(), array(
	'cRLtLQOPULfScB7wGlXT+MsBaa5sSHp7n1g8p9d8T1s=',
	'ktRVUJUNsFB6mPpZgEBEl5v5lodBsCn/V97u4ODQKR4=',
) );
pkwyc_assert( 'signature sits beside our package', pkwyc_update_signature_url( $ours ), $ours . '.sig' );
pkwyc_assert( 'no signature url for a foreign package', pkwyc_update_signature_url( 'https://example.com/pkwycompliance.zip' ), '' );

$k1   = sodium_crypto_sign_keypair();
$k2   = sodium_crypto_sign_keypair();
$k3   = sodium_crypto_sign_keypair();
$keys = array( base64_encode( sodium_crypto_sign_publickey( $k1 ) ), base64_encode( sodium_crypto_sign_publickey( $k2 ) ) );
$zip  = $tmp . '/signed.zip';
file_put_contents( $zip, 'PK pretend zip bytes ' . str_repeat( 'x', 500 ) );
$sig1 = base64_encode( sodium_crypto_sign_detached( file_get_contents( $zip ), sodium_crypto_sign_secretkey( $k1 ) ) );
$sig2 = base64_encode( sodium_crypto_sign_detached( file_get_contents( $zip ), sodium_crypto_sign_secretkey( $k2 ) ) );
$sig3 = base64_encode( sodium_crypto_sign_detached( file_get_contents( $zip ), sodium_crypto_sign_secretkey( $k3 ) ) );
pkwyc_assert( 'signed with the primary key: accepted', pkwyc_update_signature_problem( $zip, $sig1 . "\n", $keys ), '' );
pkwyc_assert( 'signed with the backup key: accepted', pkwyc_update_signature_problem( $zip, $sig2, $keys ), '' );
pkwyc_assert( 'signed with another key: refused', pkwyc_update_signature_problem( $zip, $sig3, $keys ) !== '', true );
pkwyc_assert( 'no signature: refused', pkwyc_update_signature_problem( $zip, '', $keys ) !== '', true );
pkwyc_assert( 'malformed signature: refused', pkwyc_update_signature_problem( $zip, 'not base64 !!', $keys ) !== '', true );
pkwyc_assert( 'short signature: refused', pkwyc_update_signature_problem( $zip, base64_encode( 'short' ), $keys ) !== '', true );
pkwyc_assert( 'no trusted keys: refused', pkwyc_update_signature_problem( $zip, $sig1, array() ) !== '', true );
pkwyc_assert( 'malformed trusted key is skipped, good one still works', pkwyc_update_signature_problem( $zip, $sig2, array( 'junk', $keys[1] ) ), '' );
pkwyc_assert( 'missing zip: refused', pkwyc_update_signature_problem( $tmp . '/nope.zip', $sig1, $keys ) !== '', true );
file_put_contents( $zip, 'tampered', FILE_APPEND );
pkwyc_assert( 'tampered zip: refused', pkwyc_update_signature_problem( $zip, $sig1, $keys ) !== '', true );

pkwyc_results();
