<?php
/**
 * Gravity Forms adapter for the consent field.
 *
 * Carries over the defences from an earlier single-site version of this field
 * that has been in production for some time: one instance per form, enforced
 * defaults, never pre-ticked, and a stored value that is only ever "agreed" or
 * empty. What changed is that the wording is read from this plugin's settings
 * rather than hardcoded, so it matches what WPForms shows on the same site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'gform_loaded', 'pkwyc_consent_gf_register', 5 );

function pkwyc_consent_gf_register() {

	if ( ! class_exists( 'GF_Field' ) ) {
		return;
	}

	if ( class_exists( 'PKWYC_GF_Consent_Field' ) ) {
		return;
	}

	class PKWYC_GF_Consent_Field extends GF_Field {

		public $type           = 'pkwyc_consent';
		public $label          = 'Consent';
		public $isRequired     = true;
		public $labelPlacement = 'hidden_label';

		public function get_form_editor_field_title() {
			return esc_html__( 'Consent', 'pkwycompliance' );
		}

		public function get_form_editor_button() {
			return array(
				'group' => 'advanced_fields',
				'text'  => esc_html__( 'Consent', 'pkwycompliance' ),
			);
		}

		public function get_form_editor_field_icon() {
			return 'gform-icon--check-box';
		}

		public function get_form_editor_field_settings() {
			return array(
				'conditional_logic_field_setting',
				'error_message_setting',
				'label_setting',
				'label_placement_setting',
				'description_setting',
				'css_class_setting',
				'visibility_setting',
				'rules_setting',
			);
		}

		/**
		 * Block a second instance, and pin the defaults on add.
		 */
		public function get_form_editor_inline_script_on_page_render() {

			$script  = 'window.fieldSettings = window.fieldSettings || {};';
			$script .= 'window.fieldSettings["pkwyc_consent"] = ".conditional_logic_field_setting,.error_message_setting,.label_setting,.label_placement_setting,.description_setting,.css_class_setting,.visibility_setting,.rules_setting";';

			$script .= 'gform.addFilter("gform_form_editor_can_field_be_added", function(canAdd, type){'
				. 'if(type==="pkwyc_consent"){var has=false;jQuery("#gform_fields li").each(function(){if(jQuery(this).attr("data-type")==="pkwyc_consent"){has=true;}});return !has;}return canAdd;});';

			$script .= 'gform.addFilter("gform_editor_js_set_default_values", function(field){'
				. 'if(field.type==="pkwyc_consent"){field.label="Consent";field.isRequired=true;field.labelPlacement="hidden_label";}'
				. 'return field;});';

			return $script;
		}

		public function get_field_input( $form, $value = '', $entry = null ) {

			$is_editor = GFCommon::is_form_editor();
			$settings  = pkwyc_settings();
			$wording   = pkwyc_consent_wording_html( $settings );

			// Fail closed, exactly as the WPForms side does.
			if ( $wording === '' ) {
				$message = esc_html__( 'This form cannot be submitted at this time.', 'pkwycompliance' );
				if ( $is_editor || current_user_can( 'manage_options' ) ) {
					$message .= ' ' . esc_html__( 'Admin: set the Privacy Policy URL, Terms URL and wording under Settings, Compliance.', 'pkwycompliance' );
				}
				return '<div class="ginput_container ginput_container_consent"><em>' . $message . '</em></div>';
			}

			$form_id  = absint( rgar( $form, 'id' ) );
			$id       = intval( $this->id );
			$input_id = 'input_' . $form_id . '_' . $id;

			return sprintf(
				'<div class="ginput_container ginput_container_consent"><input id="%1$s" name="%2$s" type="checkbox" value="agreed"%3$s%4$s%5$s /> <label for="%1$s">%6$s</label></div>',
				esc_attr( $input_id ),
				esc_attr( 'input_' . $id ),
				( $value === 'agreed' ) ? ' checked="checked"' : '',
				$is_editor ? ' disabled="disabled"' : '',
				$this->isRequired ? ' aria-required="true" ' . GFCommon::get_tabindex() : GFCommon::get_tabindex(),
				wp_kses_post( $wording )
			);
		}

		public function get_field_label( $force_frontend_label, $value = '', $form = null ) {
			return GFCommon::is_form_editor() ? parent::get_field_label( $force_frontend_label, $value, $form ) : '';
		}

		public function get_value_submission( $field_values, $get_from_post_global = false ) {
			return pkwyc_consent_normalise_value( rgpost( 'input_' . $this->id ) );
		}

		public function validate( $value, $form ) {

			$settings = pkwyc_settings();

			if ( ! pkwyc_consent_is_configured( $settings ) ) {
				$this->failed_validation  = true;
				$this->validation_message = esc_html__( 'This form cannot be submitted at this time.', 'pkwycompliance' );
				return;
			}

			if ( $this->isRequired && pkwyc_consent_normalise_value( $value ) !== 'agreed' ) {
				$this->failed_validation  = true;
				$this->validation_message = esc_html__( 'You must agree to continue.', 'pkwycompliance' );
			}
		}

		public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
			return $value;
		}

		public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
			return rgar( $entry, (string) $this->id );
		}
	}

	GF_Fields::register( new PKWYC_GF_Consent_Field() );
}

/**
 * Re-pin the defaults at render, in case a form was saved with them changed.
 */
function pkwyc_consent_gf_enforce( $form ) {

	if ( empty( $form['fields'] ) ) {
		return $form;
	}

	foreach ( $form['fields'] as $field ) {
		if ( isset( $field->type ) && $field->type === 'pkwyc_consent' ) {
			$field->isRequired     = true;
			$field->labelPlacement = 'hidden_label';
		}
	}

	return $form;
}
add_filter( 'gform_pre_render', 'pkwyc_consent_gf_enforce' );
add_filter( 'gform_pre_validation', 'pkwyc_consent_gf_enforce' );

/**
 * Only ever store "agreed" or empty.
 */
function pkwyc_consent_gf_save_value( $value, $lead, $field, $form, $input_id ) {

	if ( isset( $field->type ) && $field->type === 'pkwyc_consent' ) {
		return pkwyc_consent_normalise_value( $value );
	}

	return $value;
}
add_filter( 'gform_save_field_value', 'pkwyc_consent_gf_save_value', 10, 5 );
