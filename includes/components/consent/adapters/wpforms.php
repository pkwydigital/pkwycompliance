<?php
/**
 * WPForms adapter for the consent field.
 *
 * Everything that decides anything lives in ../logic.php. This file only
 * translates that into WPForms' field API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wpforms_loaded', 'pkwyc_consent_wpforms_register' );

function pkwyc_consent_wpforms_register() {

	if ( ! class_exists( 'WPForms_Field' ) ) {
		return;
	}

	if ( class_exists( 'PKWYC_WPForms_Consent_Field' ) ) {
		return;
	}

	class PKWYC_WPForms_Consent_Field extends WPForms_Field {

		public function init() {
			$this->name  = esc_html__( 'Consent', 'pkwycompliance' );
			$this->type  = 'pkwyc_consent';
			$this->icon  = 'fa-check-square-o';
			$this->order = 200;
			$this->group = 'fancy';
		}

		/**
		 * Options in the builder.
		 *
		 * Deliberately few. The wording is not editable here: it is set once
		 * under Settings, Compliance, so it cannot differ between forms on the
		 * same site. Required is not editable either, since a consent nobody
		 * has to give is not a consent.
		 */
		public function field_options( $field ) {

			$this->field_option( 'basic-options', $field, array( 'markup' => 'open' ) );

			$this->field_element(
				'row',
				$field,
				array(
					'slug'    => 'pkwyc_notice',
					'content' => '<p style="margin:0">' . sprintf(
						/* translators: %s: link to the settings screen. */
						esc_html__( 'The wording and links come from %s and are the same on every form.', 'pkwycompliance' ),
						'<a href="' . esc_url( admin_url( 'options-general.php?page=' . PKWYC_SLUG ) ) . '" target="_blank">' . esc_html__( 'Settings, Compliance', 'pkwycompliance' ) . '</a>'
					) . '</p>',
				)
			);

			$this->field_option( 'label', $field );
			$this->field_option( 'description', $field );
			$this->field_option( 'basic-options', $field, array( 'markup' => 'close' ) );

			$this->field_option( 'advanced-options', $field, array( 'markup' => 'open' ) );
			$this->field_option( 'css', $field );
			$this->field_option( 'advanced-options', $field, array( 'markup' => 'close' ) );
		}

		public function field_preview( $field ) {

			$settings = pkwyc_settings();
			$wording  = pkwyc_consent_wording_html( $settings );

			$this->field_preview_option( 'label', $field );

			if ( $wording === '' ) {
				echo '<p><em>' . esc_html__( 'Not configured. Set the Privacy Policy URL, the Terms URL and the wording under Settings, Compliance. Until then this form cannot be submitted.', 'pkwycompliance' ) . '</em></p>';
				return;
			}

			echo '<label class="wpforms-field-label-inline"><input type="checkbox" disabled> ' . wp_kses_post( $wording ) . '</label>';
		}

		public function field_display( $field, $deprecated, $form_data ) {

			$settings = pkwyc_settings();
			$wording  = pkwyc_consent_wording_html( $settings );

			/*
			 * Fail closed. Rendering a checkbox with no readable terms beside
			 * it would collect a consent that means nothing, so no usable
			 * control is produced and validate() refuses the submission.
			 */
			if ( $wording === '' ) {
				echo '<em>' . esc_html__( 'This form cannot be submitted at this time.', 'pkwycompliance' ) . '</em>';
				if ( current_user_can( 'manage_options' ) ) {
					echo ' <em>' . esc_html__( 'Admin: set the Privacy Policy URL, Terms URL and wording under Settings, Compliance.', 'pkwycompliance' ) . '</em>';
				}
				return;
			}

			$primary = $field['properties']['inputs']['primary'];

			printf(
				'<label><input type="checkbox" %s value="agreed"> %s</label>',
				wpforms_html_attributes( $primary['id'], $primary['class'], $primary['data'], $primary['attr'] ),
				wp_kses_post( $wording )
			);
		}

		public function validate( $field_id, $field_submit, $form_data ) {

			$settings = pkwyc_settings();

			if ( ! pkwyc_consent_is_configured( $settings ) ) {
				wpforms()->obj( 'process' )->errors[ $form_data['id'] ][ $field_id ] =
					esc_html__( 'This form cannot be submitted at this time.', 'pkwycompliance' );
				return;
			}

			if ( pkwyc_consent_normalise_value( $field_submit ) !== 'agreed' ) {
				wpforms()->obj( 'process' )->errors[ $form_data['id'] ][ $field_id ] =
					esc_html__( 'You must agree to continue.', 'pkwycompliance' );
			}
		}

		public function format( $field_id, $field_submit, $form_data ) {

			$name = ! empty( $form_data['fields'][ $field_id ]['label'] )
				? $form_data['fields'][ $field_id ]['label']
				: esc_html__( 'Consent', 'pkwycompliance' );

			wpforms()->obj( 'process' )->fields[ $field_id ] = array(
				'name'  => sanitize_text_field( $name ),
				'value' => pkwyc_consent_normalise_value( $field_submit ),
				'id'    => absint( $field_id ),
				'type'  => $this->type,
			);
		}
	}

	new PKWYC_WPForms_Consent_Field();
}

/**
 * Keep one instance per form.
 *
 * Two consent fields on one form means asking the same question twice and
 * storing two answers. The second is dropped at render rather than refused, so
 * an editor who adds one by accident sees the form behave normally.
 */
function pkwyc_consent_wpforms_single( $form_data ) {

	if ( empty( $form_data['fields'] ) ) {
		return $form_data;
	}

	$seen = false;

	foreach ( $form_data['fields'] as $id => $field ) {
		if ( ! isset( $field['type'] ) || $field['type'] !== 'pkwyc_consent' ) {
			continue;
		}
		if ( $seen ) {
			unset( $form_data['fields'][ $id ] );
			continue;
		}
		$seen = true;
	}

	return $form_data;
}
add_filter( 'wpforms_frontend_form_data', 'pkwyc_consent_wpforms_single' );
