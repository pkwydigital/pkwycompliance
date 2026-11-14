<?php
/**
 * Gravity Forms adapter for the consent field.
 *
 * Carries over the defences from an earlier single-site version of this field
 * that has been in production for some time: one instance per form, never
 * pre-ticked, and a stored value that is only ever "agreed" or empty. What
 * changed is that the wording is read from this plugin's settings rather than
 * hardcoded, so it matches what WPForms shows on the same site, and that
 * Required is a per-form choice rather than forced on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Same timing trap as the WPForms adapter: gform_loaded may already have fired.
if ( did_action( 'gform_loaded' ) ) {
	pkwyc_consent_gf_register();
} else {
	add_action( 'gform_loaded', 'pkwyc_consent_gf_register', 5 );
}

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

		/**
		 * One Consent field per form. Gravity Forms refuses a second add with
		 * its own message and leaves out the Duplicate button.
		 */
		public $duplicatable = false;

		/**
		 * "PKWY Consent", not "Consent": Gravity Forms has a built-in Consent
		 * field in the same group, which carries none of the site wording.
		 */
		public function get_form_editor_field_title() {
			return esc_html__( 'PKWY Consent', 'pkwycompliance' );
		}

		public function get_form_editor_button() {
			return array(
				'group' => 'advanced_fields',
				'text'  => esc_html__( 'PKWY Consent', 'pkwycompliance' ),
			);
		}

		/**
		 * Let other fields, notifications and feeds depend on the answer.
		 * The value is "agreed" when ticked and empty otherwise, so a rule
		 * reads "Consent is agreed".
		 */
		public function is_conditional_logic_supported() {
			return true;
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
				'pkwyc_wording_setting',
				'css_class_setting',
				'visibility_setting',
				'rules_setting',
			);
		}

		/**
		 * Pin the defaults on add. A second instance is blocked by $duplicatable.
		 */
		public function get_form_editor_inline_script_on_page_render() {

			$script  = 'window.fieldSettings = window.fieldSettings || {};';
			$script .= 'window.fieldSettings["pkwyc_consent"] = ".conditional_logic_field_setting,.error_message_setting,.label_setting,.label_placement_setting,.description_setting,.pkwyc_wording_setting,.css_class_setting,.visibility_setting,.rules_setting";';

			$script .= 'gform.addFilter("gform_editor_js_set_default_values", function(field){'
				. 'if(field.type==="pkwyc_consent"){field.label="Consent";field.isRequired=true;field.labelPlacement="hidden_label";}'
				. 'return field;});';

			$script .= 'jQuery(document).on("gform_load_field_settings", function(event, field){'
				. 'jQuery("#pkwyc_wording").val(field.pkwycWording || "");});';

			return $script;
		}

		/**
		 * The site settings with this form's custom wording applied.
		 */
		public function pkwyc_settings() {
			return pkwyc_consent_effective_settings( pkwyc_settings(), isset( $this->pkwycWording ) ? $this->pkwycWording : '' );
		}

		public function get_field_input( $form, $value = '', $entry = null ) {

			$is_editor = GFCommon::is_form_editor();
			$settings  = $this->pkwyc_settings();
			$wording   = pkwyc_consent_wording_html( $settings );

			// Fail closed, exactly as the WPForms side does.
			if ( $wording === '' ) {
				$message = esc_html__( 'This form cannot be submitted at this time.', 'pkwycompliance' );
				if ( $is_editor || current_user_can( 'manage_options' ) ) {
					$message .= ' ' . esc_html__( 'Admin:', 'pkwycompliance' ) . ' ' . esc_html( pkwyc_consent_config_problem_message( pkwyc_consent_config_problem( $settings ) ) ) . ' ' . esc_html__( 'See Settings, Compliance.', 'pkwycompliance' );
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

		/*
		 * Signatures below must stay compatible with GF_Field in every Gravity
		 * Forms version a site might run. A child method that drops a default
		 * the parent has is a fatal error the moment Gravity Forms loads, and
		 * it takes the whole site down, not just the form. Gravity Forms 3.x
		 * declares get_field_label( $force_frontend_label = true, $value = '' ).
		 */
		public function get_field_label( $force_frontend_label = true, $value = '' ) {
			return GFCommon::is_form_editor() ? parent::get_field_label( $force_frontend_label, $value ) : '';
		}

		public function get_value_submission( $field_values, $get_from_post_global = false ) {
			return pkwyc_consent_normalise_value( rgpost( 'input_' . $this->id ) );
		}

		public function validate( $value, $form ) {

			$error = pkwyc_consent_validation_error( $this->pkwyc_settings(), (bool) $this->isRequired, $value );

			if ( $error !== '' ) {
				$this->failed_validation  = true;
				$this->validation_message = pkwyc_consent_required_message( $this->pkwyc_settings(), $this->errorMessage );
			}
		}

		public function get_value_entry_detail( $value, $entry = array(), $use_text = false, $format = 'html', $media = 'screen' ) {
			return $value;
		}

		public function get_value_export( $entry, $input_id = '', $use_text = false, $is_csv = false ) {
			return rgar( $entry, (string) $this->id );
		}
	}

	GF_Fields::register( new PKWYC_GF_Consent_Field() );
}

/**
 * The Custom wording box in the field editor. Blank means the site wording.
 */
function pkwyc_consent_gf_wording_setting( $position, $form_id ) {

	if ( $position !== 50 ) {
		return;
	}

	?>
	<li class="pkwyc_wording_setting field_setting">
		<label for="pkwyc_wording" class="section_label"><?php esc_html_e( 'Custom wording', 'pkwycompliance' ); ?></label>
		<textarea id="pkwyc_wording" class="fieldwidth-3 fieldheight-2" oninput="SetFieldProperty('pkwycWording', this.value);"></textarea>
		<p class="description"><?php esc_html_e( 'Leave blank to use the site wording from Settings, Compliance. {company}, {privacy} and {terms} work here too, and the links are added if left out.', 'pkwycompliance' ); ?></p>
		<p class="description pkwyc-mailchimp-hint" style="color:#996800"><?php
			echo esc_html( pkwyc_consent_mailchimp_hint( 'gravityforms' ) );
		?></p>
	</li>
	<?php
}
add_action( 'gform_field_standard_settings', 'pkwyc_consent_gf_wording_setting', 10, 2 );

/**
 * Every Gravity Forms form, loaded once per request: the settings screen needs
 * them for both the custom-wording list and the forms table.
 */
function pkwyc_consent_gf_all_forms( $forget = false ) {
	static $forms = null;
	if ( $forget ) {
		$forms = null;
		return array();
	}
	if ( $forms === null ) {
		$forms = (array) GFAPI::get_forms();
	}
	return $forms;
}

/**
 * A form saved later in the same request is read again, not from the copy above.
 */
function pkwyc_consent_gf_forget_forms() {
	pkwyc_consent_gf_all_forms( true );
}
add_action( 'gform_after_save_form', 'pkwyc_consent_gf_forget_forms' );

/**
 * Gravity Forms forms whose consent field carries custom wording, for the
 * settings screen.
 */
function pkwyc_consent_gf_overrides( $found ) {

	if ( ! class_exists( 'GFAPI' ) ) {
		return $found;
	}

	foreach ( pkwyc_consent_gf_all_forms() as $form ) {
		if ( empty( $form['fields'] ) ) {
			continue;
		}
		foreach ( $form['fields'] as $field ) {
			if ( isset( $field->type ) && $field->type === 'pkwyc_consent'
				&& isset( $field->pkwycWording ) && trim( (string) $field->pkwycWording ) !== '' ) {
				$found[] = array(
					'platform' => 'Gravity Forms',
					'title'    => rgar( $form, 'title' ),
					'edit_url' => admin_url( 'admin.php?page=gf_edit_forms&id=' . absint( rgar( $form, 'id' ) ) ),
					'wording'  => $field->pkwycWording,
				);
				break;
			}
		}
	}

	return $found;
}
add_filter( 'pkwyc_consent_overrides', 'pkwyc_consent_gf_overrides' );

/**
 * Gravity Forms forms carrying the consent field, with each active Mailchimp
 * feed's status, for the settings screen. Feeds are read only when the
 * Gravity Forms Mailchimp add-on is active.
 */
function pkwyc_consent_gf_report( $rows ) {

	if ( ! class_exists( 'GFAPI' ) ) {
		return $rows;
	}

	// Every Mailchimp feed on the site in one query, not one per form.
	$feeds_by_form = class_exists( 'GFMailChimp' ) ? pkwyc_audit_group_feeds_by_form( GFAPI::get_feeds( null, null, 'gravityformsmailchimp' ) ) : array();

	foreach ( pkwyc_consent_gf_all_forms() as $form ) {
		$consent_id = '';
		foreach ( (array) rgar( $form, 'fields' ) as $field ) {
			if ( isset( $field->type ) && $field->type === 'pkwyc_consent' ) {
				$consent_id = (string) $field->id;
				break;
			}
		}
		if ( $consent_id === '' ) {
			continue;
		}

		$form_id     = absint( rgar( $form, 'id' ) );
		$connections = array();
		if ( isset( $feeds_by_form[ $form_id ] ) ) {
			foreach ( pkwyc_audit_gf_connections( $feeds_by_form[ $form_id ], $consent_id ) as $c ) {
				$c['edit_url']  = admin_url( 'admin.php?page=gf_edit_forms&view=settings&subview=gravityformsmailchimp&id=' . $form_id . '&fid=' . absint( $c['id'] ) );
				$connections[] = $c;
			}
		}

		$rows[] = array(
			'platform'    => 'Gravity Forms',
			'title'       => rgar( $form, 'title' ),
			'edit_url'    => admin_url( 'admin.php?page=gf_edit_forms&id=' . $form_id ),
			'connections' => $connections,
		);
	}

	return $rows;
}
add_filter( 'pkwyc_consent_forms_report', 'pkwyc_consent_gf_report' );

/**
 * Re-pin the label placement at render, in case a form was saved with it changed.
 *
 * Required is deliberately not re-pinned. It starts on for a new field (see the
 * editor defaults above) and is a per-form choice from then on, made with
 * Gravity Forms' own Required checkbox.
 */
function pkwyc_consent_gf_enforce( $form ) {

	if ( empty( $form['fields'] ) ) {
		return $form;
	}

	foreach ( $form['fields'] as $field ) {
		if ( isset( $field->type ) && $field->type === 'pkwyc_consent' ) {
			$field->labelPlacement = 'hidden_label';
		}
	}

	return $form;
}
add_filter( 'gform_pre_render', 'pkwyc_consent_gf_enforce' );
add_filter( 'gform_pre_validation', 'pkwyc_consent_gf_enforce' );

/**
 * Give Gravity Forms' own "required" check the plugin's message.
 *
 * Gravity Forms decides a required field is empty before it ever calls the
 * field's validate(), and then shows the field's Error Message setting or its
 * generic "This field is required." So an unticked box never reached the
 * plugin's wording, and with the settings cleared it told the visitor to tick
 * a box that would not have helped. Setting the error message for this
 * submission only (nothing is saved) puts the plugin's message in its place,
 * while a form's own Error Message is still respected.
 */
function pkwyc_consent_gf_required_message( $form ) {

	if ( empty( $form['fields'] ) ) {
		return $form;
	}

	foreach ( $form['fields'] as $field ) {
		if ( isset( $field->type ) && $field->type === 'pkwyc_consent' && method_exists( $field, 'pkwyc_settings' ) ) {
			$field->errorMessage = pkwyc_consent_required_message( $field->pkwyc_settings(), $field->errorMessage );
		}
	}

	return $form;
}
add_filter( 'gform_pre_validation', 'pkwyc_consent_gf_required_message' );

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

/**
 * Keep the exact wording shown with every entry from a form carrying the field.
 */
function pkwyc_consent_gf_record( $entry, $form ) {

	foreach ( (array) rgar( $form, 'fields' ) as $field ) {
		if ( ! isset( $field->type ) || $field->type !== 'pkwyc_consent' ) {
			continue;
		}
		$record = pkwyc_consent_record( pkwyc_settings(), isset( $field->pkwycWording ) ? $field->pkwycWording : '', gmdate( 'c' ), PKWYC_VERSION );
		if ( $record['wording'] !== '' ) {
			gform_update_meta( absint( rgar( $entry, 'id' ) ), 'pkwyc_consent_record', wp_json_encode( $record ), absint( rgar( $form, 'id' ) ) );
		}
		return;
	}
}
add_action( 'gform_entry_created', 'pkwyc_consent_gf_record', 10, 2 );

/**
 * Show the recorded wording on the entry detail screen.
 */
function pkwyc_consent_gf_entry_box( $meta_boxes, $entry, $form ) {

	foreach ( (array) rgar( $form, 'fields' ) as $field ) {
		if ( isset( $field->type ) && $field->type === 'pkwyc_consent' ) {
			$meta_boxes['pkwyc_consent_record'] = array(
				'title'    => esc_html__( 'Consent wording', 'pkwycompliance' ),
				'callback' => 'pkwyc_consent_gf_entry_box_render',
				'context'  => 'normal',
			);
			break;
		}
	}
	return $meta_boxes;
}
add_filter( 'gform_entry_detail_meta_boxes', 'pkwyc_consent_gf_entry_box', 10, 3 );

function pkwyc_consent_gf_entry_box_render( $args ) {
	$json = gform_get_meta( absint( rgar( $args['entry'], 'id' ) ), 'pkwyc_consent_record' );
	echo pkwyc_consent_record_html( is_string( $json ) ? $json : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
}
