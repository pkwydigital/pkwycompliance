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

/*
 * Components load on plugins_loaded at priority 20, and WPForms fires
 * wpforms_loaded earlier in that same plugins_loaded run, so by the time this
 * file is read the hook has usually been and gone. Register now if it has.
 */
if ( did_action( 'wpforms_loaded' ) ) {
	pkwyc_consent_wpforms_register();
} else {
	add_action( 'wpforms_loaded', 'pkwyc_consent_wpforms_register' );
}

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
		 * Deliberately few. The wording comes from Settings, Compliance unless
		 * this form sets its own; a form that does is listed on that screen so
		 * overrides do not drift unnoticed. WPForms' own Required option is
		 * not offered, because new fields start unrequired there; this field
		 * starts required and the per-form "Optional" switch below is the only
		 * way out of that.
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
						esc_html__( 'The links, and the wording unless set below, come from %s.', 'pkwycompliance' ),
						'<a href="' . esc_url( admin_url( 'options-general.php?page=' . PKWYC_SLUG ) ) . '" target="_blank">' . esc_html__( 'Settings, Compliance', 'pkwycompliance' ) . '</a>'
					) . '</p>',
				)
			);

			$this->field_element(
				'row',
				$field,
				array(
					'slug'    => 'pkwyc_mailchimp_hint',
					'content' => '<p class="note" style="margin:0;color:#996800">' . esc_html( pkwyc_consent_mailchimp_hint( pkwyc_consent_wpforms_form_has_mailchimp( $this->form_id ) ) ) . '</p>',
				)
			);

			$this->field_option( 'label', $field );
			$this->field_option( 'description', $field );

			$optional = $this->field_element(
				'checkbox',
				$field,
				array(
					'slug'  => 'pkwyc_optional',
					'value' => pkwyc_consent_is_required( isset( $field['pkwyc_optional'] ) ? $field['pkwyc_optional'] : null ) ? '0' : '1',
					'desc'  => esc_html__( 'Optional', 'pkwycompliance' ),
				),
				false
			);
			$this->field_element(
				'row',
				$field,
				array(
					'slug'    => 'pkwyc_optional',
					'content' => $optional . '<p class="note">' . esc_html__( 'Off: the form cannot be submitted unless the box is ticked. On: visitors may leave it unticked, and the entry records an empty value.', 'pkwycompliance' ) . '</p>',
				)
			);

			$wording_label = $this->field_element(
				'label',
				$field,
				array(
					'slug'    => 'pkwyc_wording',
					'value'   => esc_html__( 'Custom wording', 'pkwycompliance' ),
					'tooltip' => esc_html__( 'Leave blank to use the site wording. {company}, {privacy} and {terms} work here too, and the links are added if left out.', 'pkwycompliance' ),
				),
				false
			);
			$wording_input = $this->field_element(
				'textarea',
				$field,
				array(
					'slug'  => 'pkwyc_wording',
					'value' => isset( $field['pkwyc_wording'] ) ? $field['pkwyc_wording'] : '',
					'rows'  => 5,
				),
				false
			);
			$this->field_element(
				'row',
				$field,
				array(
					'slug'    => 'pkwyc_wording',
					'content' => $wording_label . $wording_input,
				)
			);

			$this->field_option( 'basic-options', $field, array( 'markup' => 'close' ) );

			$this->field_option( 'advanced-options', $field, array( 'markup' => 'open' ) );
			$this->field_option( 'css', $field );
			$this->field_option( 'advanced-options', $field, array( 'markup' => 'close' ) );
		}

		public function field_preview( $field ) {

			$settings = pkwyc_consent_wpforms_settings( $field );
			$wording  = pkwyc_consent_wording_html( $settings );

			$this->field_preview_option( 'label', $field );

			if ( $wording === '' ) {
				echo '<p><em>' . esc_html__( 'Not configured. Set the Privacy Policy URL and the wording under Settings, Compliance (and a Terms URL if the wording uses {terms}). Until then this form cannot be submitted.', 'pkwycompliance' ) . '</em></p>';
				return;
			}

			echo '<label class="wpforms-field-label-inline"><input type="checkbox" disabled> ' . wp_kses_post( $wording ) . '</label>';
		}

		public function field_display( $field, $deprecated, $form_data ) {

			$settings = pkwyc_consent_wpforms_settings( $field );
			$wording  = pkwyc_consent_wording_html( $settings );

			/*
			 * Fail closed. Rendering a checkbox with no readable terms beside
			 * it would collect a consent that means nothing, so no usable
			 * control is produced and validate() refuses the submission.
			 */
			if ( $wording === '' ) {
				echo '<em>' . esc_html__( 'This form cannot be submitted at this time.', 'pkwycompliance' ) . '</em>';
				if ( current_user_can( 'manage_options' ) ) {
					echo ' <em>' . esc_html__( 'Admin:', 'pkwycompliance' ) . ' ' . esc_html( pkwyc_consent_config_problem_message( pkwyc_consent_config_problem( $settings ) ) ) . ' ' . esc_html__( 'See Settings, Compliance.', 'pkwycompliance' ) . '</em>';
				}
				return;
			}

			$primary   = $field['properties']['inputs']['primary'];
			$mirror_id = $primary['id'];
			$box_id    = $mirror_id . '-box';

			/*
			 * Conditional logic in the browser reads a text-like field's value
			 * from the element with WPForms' own id. A checkbox's value is
			 * "agreed" whether or not it is ticked, so that id goes on an
			 * unnamed hidden mirror holding "agreed" or "", kept current by the
			 * box's own change handler, which runs before WPForms' delegated
			 * one. The box keeps its name and value, so submission does not
			 * depend on the mirror or on JavaScript at all. Never pre-ticked,
			 * so both start empty.
			 */
			printf(
				'<label><input type="checkbox" %1$s value="agreed" onchange="%2$s"> %3$s</label><input type="hidden" id="%4$s" value="">',
				wpforms_html_attributes( $box_id, $primary['class'], $primary['data'], $primary['attr'] ),
				esc_attr( "var m=document.getElementById('" . esc_js( $mirror_id ) . "');if(m){m.value=this.checked?'agreed':'';}" ),
				wp_kses_post( $wording ),
				esc_attr( $mirror_id )
			);
		}

		public function validate( $field_id, $field_submit, $form_data ) {

			$field    = isset( $form_data['fields'][ $field_id ] ) ? $form_data['fields'][ $field_id ] : array();
			$required = pkwyc_consent_is_required( isset( $field['pkwyc_optional'] ) ? $field['pkwyc_optional'] : null );
			$error    = pkwyc_consent_validation_error( pkwyc_consent_wpforms_settings( $field ), $required, $field_submit );

			if ( $error !== '' ) {
				wpforms()->obj( 'process' )->errors[ $form_data['id'] ][ $field_id ] = pkwyc_consent_error_message( $error );
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
 * Does this WPForms form have any Mailchimp connection?
 */
function pkwyc_consent_wpforms_form_has_mailchimp( $form_id ) {
	if ( ! $form_id || ! function_exists( 'wpforms' ) ) {
		return false;
	}
	$data = wpforms()->obj( 'form' )->get( absint( $form_id ), array( 'content_only' => true ) );
	return ! empty( $data['providers']['mailchimpv3'] );
}

/**
 * The settings as this field sees them, with its custom wording applied.
 */
function pkwyc_consent_wpforms_settings( $field ) {
	return pkwyc_consent_effective_settings( pkwyc_settings(), isset( $field['pkwyc_wording'] ) ? $field['pkwyc_wording'] : '' );
}

/**
 * WPForms forms whose consent field carries custom wording, for the settings
 * screen.
 */
function pkwyc_consent_wpforms_overrides( $found ) {

	if ( ! function_exists( 'wpforms' ) || ! function_exists( 'wpforms_decode' ) ) {
		return $found;
	}

	$forms = wpforms()->obj( 'form' )->get( '', array( 'posts_per_page' => -1 ) );

	foreach ( (array) $forms as $form ) {
		$data = wpforms_decode( $form->post_content );
		if ( empty( $data['fields'] ) ) {
			continue;
		}
		foreach ( $data['fields'] as $field ) {
			if ( isset( $field['type'] ) && $field['type'] === 'pkwyc_consent'
				&& isset( $field['pkwyc_wording'] ) && trim( (string) $field['pkwyc_wording'] ) !== '' ) {
				$found[] = array(
					'platform' => 'WPForms',
					'title'    => $form->post_title,
					'edit_url' => admin_url( 'admin.php?page=wpforms-builder&view=fields&form_id=' . absint( $form->ID ) ),
					'wording'  => $field['pkwyc_wording'],
				);
				break;
			}
		}
	}

	return $found;
}
add_filter( 'pkwyc_consent_overrides', 'pkwyc_consent_wpforms_overrides' );

/**
 * WPForms forms carrying the consent field, with each Mailchimp connection's
 * status, for the "Forms with the Consent field" table on the settings screen.
 */
function pkwyc_consent_wpforms_report( $rows ) {

	if ( ! function_exists( 'wpforms' ) || ! function_exists( 'wpforms_decode' ) ) {
		return $rows;
	}

	foreach ( (array) wpforms()->obj( 'form' )->get( '', array( 'posts_per_page' => -1 ) ) as $form ) {
		$report = pkwyc_audit_wpforms_form( wpforms_decode( $form->post_content ) );
		if ( $report === null ) {
			continue;
		}
		$panel = admin_url( 'admin.php?page=wpforms-builder&view=providers&form_id=' . absint( $form->ID ) );
		foreach ( $report['connections'] as $i => $c ) {
			$report['connections'][ $i ]['edit_url'] = $panel;
		}
		$rows[] = array(
			'platform'    => 'WPForms',
			'title'       => $form->post_title,
			'edit_url'    => admin_url( 'admin.php?page=wpforms-builder&view=fields&form_id=' . absint( $form->ID ) ),
			'connections' => $report['connections'],
		);
	}

	return $rows;
}
add_filter( 'pkwyc_consent_forms_report', 'pkwyc_consent_wpforms_report' );

/**
 * Let the consent field drive conditional logic: Mailchimp connections,
 * notifications, confirmations, and showing or hiding other fields. It joins as
 * a text-like field, so a rule reads "Consent is not empty" (ticked) or
 * "Consent is empty" (not ticked). Server side the value is the one format()
 * stored; in the browser it is the hidden mirror written in field_display().
 */
add_filter( 'wpforms_get_conditional_logic_form_fields_supported', 'pkwyc_consent_add_conditional_type' );
add_filter( 'wpforms_conditional_logic_core_get_text_based_fields', 'pkwyc_consent_add_conditional_type' );

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
