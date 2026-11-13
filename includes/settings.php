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

	$s         = pkwyc_settings();
	$configured = pkwyc_consent_is_configured( $s );

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Compliance', 'pkwycompliance' ); ?></h1>

		<?php if ( ! $configured ) : ?>
			<div class="notice notice-error inline">
				<p><strong><?php esc_html_e( 'The consent field is not usable yet.', 'pkwycompliance' ); ?></strong>
				<?php esc_html_e( 'Until the Privacy Policy URL and the wording are filled in, any form carrying the consent field will refuse to submit. The Terms URL is optional, but wording that uses {terms} needs it. That is deliberate: it is better than asking somebody to agree to documents they cannot open.', 'pkwycompliance' ); ?></p>
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
					<td><input name="<?php echo esc_attr( PKWYC_OPTION ); ?>[privacy_url]" id="pkwyc_privacy" type="url" class="regular-text code" value="<?php echo esc_attr( $s['privacy_url'] ); ?>" placeholder="https://"></td>
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
