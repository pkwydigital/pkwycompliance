<?php
/**
 * Settings, under Settings, Compliance.
 *
 * A plain WordPress settings page rather than a tab inside WPForms, because
 * this plugin also runs on Gravity Forms sites where no WPForms menu exists.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function pkwyc_default_settings() {

	return array(
		'company'     => get_bloginfo( 'name' ),
		'privacy_url' => '',
		'terms_url'   => '',
		'wording'     => 'By submitting your email address, you agree to receive communications from {company}. You can unsubscribe at any time using the link in any email. You also confirm that you have read and agree to the {privacy} and {terms}.',
	);
}

function pkwyc_settings() {

	$saved = get_option( PKWYC_OPTION, array() );

	return wp_parse_args( is_array( $saved ) ? $saved : array(), pkwyc_default_settings() );
}

function pkwyc_settings_menu() {

	add_options_page(
		__( 'Compliance', 'pkwycompliance' ),
		__( 'Compliance', 'pkwycompliance' ),
		'manage_options',
		PKWYC_SLUG,
		'pkwyc_settings_page'
	);
}
add_action( 'admin_menu', 'pkwyc_settings_menu' );

function pkwyc_settings_register() {

	register_setting(
		'pkwyc_group',
		PKWYC_OPTION,
		array( 'sanitize_callback' => 'pkwyc_settings_sanitize' )
	);
}
add_action( 'admin_init', 'pkwyc_settings_register' );

function pkwyc_settings_sanitize( $input ) {

	$clean = pkwyc_default_settings();

	$clean['company']     = isset( $input['company'] ) ? sanitize_text_field( $input['company'] ) : '';
	$clean['privacy_url'] = isset( $input['privacy_url'] ) ? esc_url_raw( trim( (string) $input['privacy_url'] ) ) : '';
	$clean['terms_url']   = isset( $input['terms_url'] ) ? esc_url_raw( trim( (string) $input['terms_url'] ) ) : '';
	$clean['wording']     = isset( $input['wording'] ) ? wp_kses_post( trim( (string) $input['wording'] ) ) : '';

	return $clean;
}

function pkwyc_settings_page() {

	$s       = pkwyc_settings();
	$problem = pkwyc_consent_config_problem( $s );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Compliance', 'pkwycompliance' ); ?></h1>

		<?php if ( $problem !== '' ) : ?>
			<div class="notice notice-error inline">
				<p><strong><?php esc_html_e( 'The consent field is not usable yet.', 'pkwycompliance' ); ?></strong>
				<?php echo esc_html( pkwyc_consent_config_problem_message( $problem ) ); ?></p>
				<p><?php
				if ( $problem === 'no_privacy_url' ) {
					esc_html_e( 'Until it is set, every form carrying the consent field will refuse to submit, including forms with custom wording.', 'pkwycompliance' );
				} else {
					esc_html_e( 'Until it is fixed, forms using the site wording will refuse to submit. Forms with their own custom wording are not affected by this.', 'pkwycompliance' );
				}
				echo ' ';
				esc_html_e( 'That is deliberate: it is better than asking somebody to agree to documents they cannot open.', 'pkwycompliance' );
				?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'pkwyc_group' ); ?>

			<h2><?php esc_html_e( 'Form consent field', 'pkwycompliance' ); ?></h2>
			<p class="description" style="max-width:40em">
				<?php esc_html_e( 'This wording appears beside the consent checkbox on every form that uses the field, unless that form sets custom wording in the field options. The links always come from here. Forms with custom wording are listed below so they do not drift unnoticed.', 'pkwycompliance' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="pkwyc_company"><?php esc_html_e( 'Company name', 'pkwycompliance' ); ?></label></th>
					<td><input name="<?php echo esc_attr( PKWYC_OPTION ); ?>[company]" id="pkwyc_company" type="text" class="regular-text" value="<?php echo esc_attr( $s['company'] ); ?>">
					<p class="description"><?php esc_html_e( 'Substituted wherever {company} appears in the wording.', 'pkwycompliance' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="pkwyc_privacy"><?php esc_html_e( 'Privacy Policy URL', 'pkwycompliance' ); ?></label></th>
					<td><input name="<?php echo esc_attr( PKWYC_OPTION ); ?>[privacy_url]" id="pkwyc_privacy" type="url" class="regular-text code" value="<?php echo esc_attr( $s['privacy_url'] ); ?>" placeholder="https://">
					<?php pkwyc_settings_privacy_page_hint( $s['privacy_url'] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="pkwyc_terms"><?php esc_html_e( 'Terms URL', 'pkwycompliance' ); ?></label></th>
					<td><input name="<?php echo esc_attr( PKWYC_OPTION ); ?>[terms_url]" id="pkwyc_terms" type="url" class="regular-text code" value="<?php echo esc_attr( $s['terms_url'] ); ?>" placeholder="https://">
					<p class="description"><?php esc_html_e( 'Optional. Leave blank if the site has no terms page, and take {terms} out of the wording.', 'pkwycompliance' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="pkwyc_wording"><?php esc_html_e( 'Consent wording', 'pkwycompliance' ); ?></label></th>
					<td>
						<textarea name="<?php echo esc_attr( PKWYC_OPTION ); ?>[wording]" id="pkwyc_wording" rows="5" class="large-text"><?php echo esc_textarea( $s['wording'] ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Use {company}, {privacy} and {terms} as placeholders. If you leave the link placeholders out, the links are added on the end anyway, because consent to terms nobody can reach is not consent.', 'pkwycompliance' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Preview', 'pkwycompliance' ); ?></h2>
			<p style="max-width:40em;padding:12px 14px;border:1px solid #c3c4c7;background:#fff">
				<?php
				$preview = pkwyc_consent_wording_html( $s );
				echo $preview !== '' ? wp_kses_post( $preview ) : '<em>' . esc_html__( 'Nothing to preview until the URLs and wording are filled in.', 'pkwycompliance' ) . '</em>';
				?>
			</p>

			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Forms with the Consent field', 'pkwycompliance' ); ?></h2>
		<p class="description" style="max-width:40em"><?php esc_html_e( 'Ticking the box changes nothing in Mailchimp by itself. Every Mailchimp connection that subscribes people needs Consent is not empty added to each of its rule groups. This table checks that for you.', 'pkwycompliance' ); ?></p>
		<?php pkwyc_settings_forms_report(); ?>

		<h2><?php esc_html_e( 'Forms with custom wording', 'pkwycompliance' ); ?></h2>
		<?php
		$overrides = apply_filters( 'pkwyc_consent_overrides', array() );

		if ( empty( $overrides ) ) {
			echo '<p>' . esc_html__( 'None. Every consent field uses the wording above.', 'pkwycompliance' ) . '</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:60em"><thead><tr><th>' . esc_html__( 'Form', 'pkwycompliance' ) . '</th><th>' . esc_html__( 'Wording shown', 'pkwycompliance' ) . '</th></tr></thead><tbody>';
			foreach ( $overrides as $o ) {
				$shown = pkwyc_consent_wording_html( pkwyc_consent_effective_settings( $s, $o['wording'] ) );
				printf(
					'<tr><td><a href="%s">%s</a><br><span class="description">%s</span></td><td>%s</td></tr>',
					esc_url( $o['edit_url'] ),
					esc_html( $o['title'] ),
					esc_html( $o['platform'] ),
					$shown !== '' ? wp_kses_post( $shown ) : '<em>' . esc_html__( 'Not usable until the URLs are filled in.', 'pkwycompliance' ) . '</em>'
				);
			}
			echo '</tbody></table>';
		}
		?>
	</div>
	<?php
}

/**
 * The "Forms with the Consent field" table: each form using the field and
 * whether each of its Mailchimp connections only takes people who ticked.
 */
function pkwyc_settings_forms_report() {

	$rows = apply_filters( 'pkwyc_consent_forms_report', array() );

	if ( empty( $rows ) ) {
		echo '<p>' . esc_html__( 'No form uses the Consent field yet.', 'pkwycompliance' ) . '</p>';
		return;
	}

	echo '<table class="widefat striped pkwyc-forms-report" style="max-width:60em"><thead><tr><th>' . esc_html__( 'Form', 'pkwycompliance' ) . '</th><th>' . esc_html__( 'Mailchimp', 'pkwycompliance' ) . '</th></tr></thead><tbody>';

	foreach ( $rows as $row ) {
		$cell = '';
		if ( empty( $row['connections'] ) ) {
			$cell = esc_html__( 'No Mailchimp connection.', 'pkwycompliance' );
		}
		foreach ( $row['connections'] as $c ) {
			$bad   = in_array( $c['status'], array( 'ungated', 'partial' ), true );
			$cell .= sprintf(
				'<p class="pkwyc-status-%s" style="margin:0 0 6px;%s"><a href="%s"><strong>%s</strong></a>: %s</p>',
				esc_attr( $c['status'] ),
				$bad ? 'color:#b32d2e' : ( $c['status'] === 'gated' ? 'color:#007017' : '' ),
				esc_url( $c['edit_url'] ),
				esc_html( $c['name'] ),
				esc_html( pkwyc_audit_status_label( $c['status'] ) )
			);
		}
		printf(
			'<tr><td><a href="%s">%s</a><br><span class="description">%s</span></td><td>%s</td></tr>',
			esc_url( $row['edit_url'] ),
			esc_html( $row['title'] ),
			esc_html( $row['platform'] ),
			$cell
		);
	}

	echo '</tbody></table>';
}

/**
 * Under the Privacy Policy URL: name the page it links to, and compare it with
 * WordPress's own privacy page setting (Settings, Privacy). Suggests that page
 * when the URL is empty, but never fills it in by itself: a site can point the
 * WordPress setting at the wrong page, and a person has to look first.
 */
function pkwyc_settings_privacy_page_hint( $plugin_url ) {

	$wp_page = null;
	$page_id = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( $page_id > 0 && get_post( $page_id ) ) {
		$wp_page = array(
			'url'    => (string) get_permalink( $page_id ),
			'title'  => get_the_title( $page_id ),
			'status' => (string) get_post_status( $page_id ),
		);
	}

	$advice = pkwyc_privacy_page_advice( $plugin_url, $wp_page );
	$link   = function ( $url, $title ) {
		return sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( $url ), esc_html( $title !== '' ? $title : $url ) );
	};

	if ( trim( (string) $plugin_url ) !== '' ) {
		$own_id = url_to_postid( $plugin_url );
		if ( $own_id > 0 ) {
			/* translators: %s: page title, linked */
			echo '<p class="description pkwyc-privacy-names">' . sprintf( esc_html__( 'This links to the page: %s', 'pkwycompliance' ), $link( $plugin_url, get_the_title( $own_id ) ) ) . '</p>';
		} else {
			echo '<p class="description pkwyc-privacy-names">' . esc_html__( 'This is not a page on this site. Open it to check it is the privacy policy.', 'pkwycompliance' ) . '</p>';
		}
	}

	if ( $advice['state'] === 'suggest' ) {
		printf(
			'<p class="description pkwyc-privacy-suggest">%s <button type="button" class="button button-small" onclick="document.getElementById(\'pkwyc_privacy\').value=%s;this.parentNode.lastChild.textContent=\' %s\';">%s</button><span></span></p>',
			/* translators: %s: page title, linked */
			sprintf( esc_html__( 'WordPress has a privacy page set under Settings, Privacy: %s.', 'pkwycompliance' ), $link( $advice['url'], $advice['title'] ) ),
			esc_attr( wp_json_encode( $advice['url'] ) ),
			esc_attr__( 'Filled in. Check it is right, then Save Changes.', 'pkwycompliance' ),
			esc_html__( 'Use this page', 'pkwycompliance' )
		);
	} elseif ( $advice['state'] === 'differs' ) {
		echo '<p class="description pkwyc-privacy-differs" style="color:#996800">' . sprintf(
			/* translators: %s: page title, linked */
			esc_html__( 'This is not the page set under Settings, Privacy (%s). One of the two may be out of date.', 'pkwycompliance' ),
			$link( $advice['url'], $advice['title'] )
		) . '</p>';
	}
}

/**
 * Warn admins on other screens when the consent field is not usable.
 *
 * The settings screen already says so, and the field fails closed, but a site
 * with the plugin active and nothing configured would otherwise show no
 * warning anywhere an admin is likely to look. Dismissing it hides it for that
 * page load only; it comes back until the settings are fixed.
 */
function pkwyc_unconfigured_notice() {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && $screen->id === 'settings_page_' . PKWYC_SLUG ) {
		return;
	}

	$s     = pkwyc_settings();
	$level = pkwyc_consent_notice_level( $s, pkwyc_is_disabled() );

	if ( $level === '' ) {
		return;
	}

	$link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=' . PKWYC_SLUG ) ),
		esc_html__( 'Settings, Compliance', 'pkwycompliance' )
	);

	if ( $level === 'strong' ) {
		$lead = esc_html__( 'PKWY Compliance is not set up.', 'pkwycompliance' );
		$tail = esc_html__( 'Any form carrying the Consent field will refuse to submit until it is. Fix it under %s.', 'pkwycompliance' );
	} else {
		$lead = esc_html__( 'PKWY Compliance: the site consent wording is not usable.', 'pkwycompliance' );
		$tail = esc_html__( 'Forms using the site wording will refuse to submit; forms with their own custom wording are not affected. Check it under %s.', 'pkwycompliance' );
	}

	printf(
		'<div class="notice notice-warning is-dismissible pkwyc-unconfigured"><p><strong>%s</strong> %s %s</p></div>',
		$lead,
		esc_html( pkwyc_consent_config_problem_message( pkwyc_consent_config_problem( $s ) ) ),
		sprintf( $tail, $link )
	);
}
add_action( 'admin_notices', 'pkwyc_unconfigured_notice' );
