<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * The Mollie field is a payment methods field used specifically by the Mollie Add-On.
 *
 * @since 1.0
 *
 * Class GF_Field_Mollie
 */
class GF_Field_Mollie extends GF_Field_CreditCard {

	/**
	 * Field type.
	 *
	 * @since 1.0
	 *
	 * @var string
	 */
	public $type = 'mollie';

	/**
	 * The payment methods.
	 *
	 * @since 1.0
	 *
	 * @var array
	 */
	private static $_choices = array();

	/**
	 * Get field button title.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Mollie', 'gravityformsmollie' );
	}

	/**
	 * Returns the field's form editor icon.
	 *
	 * This could be an icon url or a dashicons class.
	 *
	 * @since 1.1
	 *
	 * @return string
	 */
	public function get_form_editor_field_icon() {
		return gf_mollie()->is_gravityforms_supported( '2.5-beta-4' ) ? 'gform-icon--mollie' : gf_mollie()->get_base_url() . '/images/menu-icon.svg';
	}

	/**
	 * Returns the field's form editor description.
	 *
	 * @since 1.1
	 *
	 * @return string
	 */
	public function get_form_editor_field_description() {
		return esc_attr__( 'Allows accepting credit card information to make payments via Mollie payment gateway.', 'gravityformsmollie' );
	}

	/**
	 * Get form editor button.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'pricing_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	/**
	 * Get field settings in the form editor.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'mollie_default_payment_method',
			'conditional_logic_field_setting',
			'force_ssl_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'rules_setting',
			'description_setting',
			'css_class_setting',
			'sub_labels_setting',
			'sub_label_placement_setting',
			'credit_card_setting',
		);
	}

	/**
	 * Returns the warning message to be displayed in the form editor sidebar.
	 *
	 * @since 1.5
	 *
	 * @return array[]|array|string
	 */
	public function get_field_sidebar_messages() {
		if ( ! gf_mollie()->initialize_api() ) {
			return str_replace( 'href', 'target="_blank" href', gf_mollie()->configure_addon_message() );
		}

		$this->choices = self::get_choices( rgobj( $this, 'defaultPaymentMethod' ) );

		// If no payment method choices are available, display an error message.
		if ( empty( $this->choices ) ) {

			// Get current currency, settings page URL.
			$currency            = RGCurrency::get_currency( GFCommon::get_currency() );
			$plugin_settings_url = add_query_arg( array(
				'page'    => 'gf_settings',
				'subview' => 'settings',
			), admin_url( 'admin.php' ) );


			/* translators: 1. The currency name 2. Open strong tag 3. Close strong tag */
			$message = sprintf( esc_html__( 'Your Mollie profile doesn\'t have any payment method that supports %2$s%1$s%3$s.', 'gravityformsmollie' ), rgar( $currency, 'name' ), '<strong>', '</strong>' );

			// Display message based on Profile ID setting.
			if ( gf_mollie()->get_plugin_setting( 'profile_id' ) ) {

				/* translators: 1. Open link tag 2. Close link tag */
				$message .= ' ' . sprintf( esc_html__( 'You need to add a payment method that supports the currency in your Mollie Profile or change your %1$sCurrency Setting%2$s.', 'gravityformsmollie' ), "<a href='{$plugin_settings_url}'>", '</a>' );

			} else {

				// Get Mollie plugin settings URL.
				$mollie_settings_url = add_query_arg( array(
					'page'    => 'gf_settings',
					'subview' => gf_mollie()->get_slug(),
				), admin_url( 'admin.php' ) );

				/* translators: 1. Open link tag 2. Close lin tag 3. Open link tag */
				$message .= ' ' . sprintf( esc_html__( 'You need to select a %1$sMollie Profile%2$s.', 'gravityformsmollie' ), "<a href='{$mollie_settings_url}' target='_blank'>", '</a>' );

			}

			return $message;
		}

		if ( ! gf_mollie()->has_feed( $this->formId ) ) {
			return esc_html__( 'Please check if you have activated a Mollie feed for your form.', 'gravityformsmollie' );
		}

		if ( ( count( $this->choices ) > 1 ) ) {
			return array(
				'type'    => 'notice',
				'content' => esc_html__( 'Credit Card fields will only be displayed in your form when Credit Card is the selected payment method.', 'gravityformsmollie' ),
			);
		} elseif ( rgars( $this->choices, '0/value' ) !== 'creditcard' ) {
			return array(
				'type'    => 'notice',
				'content' => esc_html__( 'This field wouldn\'t be displayed in your form because only one supported payment method in your Mollie profile. Your customers will be redirected to the payment gateway after submitting the form.', 'gravityformsmollie' ),
			);
		}

		return '';
	}

	/**
	 * Returns the scripts to be included for this field type in the form editor.
	 *
	 * @since  1.0
	 *
	 * @return string
	 */
	public function get_form_editor_inline_script_on_page_render() {
		$default_payment_method = self::get_choices();
		$default_payment_method = ( count( $default_payment_method ) > 1 ) ? $default_payment_method[0]['value'] : '';

		return sprintf( "function SetDefaultValues_%s(field) {field.label = '%s';
						field.inputs = [new Input(field.id + '.1', %s), new Input(field.id + '.2', %s), new Input(field.id + '.3', %s), new Input(field.id + '.4', %s), new Input(field.id + '.5', %s), new Input(field.id + '.6', %s)];
						field.defaultPaymentMethod = '%s';
						field.creditCards = ['visa', 'mastercard', 'amex'];
			}",
				$this->type,
				esc_html__( 'Payment Method', 'gravityformsmollie' ),
				json_encode( gf_apply_filters( array( 'gform_card_number', rgget( 'id' ) ), esc_html__( 'Card Number', 'gravityformsmollie' ), rgget( 'id' ) ) ),
				json_encode( gf_apply_filters( array( 'gform_card_expiration', rgget( 'id' ) ), esc_html__( 'Expiration Date', 'gravityformsmollie' ), rgget( 'id' ) ) ),
				json_encode( gf_apply_filters( array( 'gform_card_security_code', rgget( 'id' ) ), esc_html__( 'Security Code', 'gravityformsmollie' ), rgget( 'id' ) ) ),
				json_encode( gf_apply_filters( array( 'gform_card_type', rgget( 'id' ) ), esc_html__( 'Card Type', 'gravityformsmollie' ), rgget( 'id' ) ) ),
				json_encode( gf_apply_filters( array( 'gform_card_name', rgget( 'id' ) ), esc_html__( 'Cardholder Name', 'gravityformsmollie' ), rgget( 'id' ) ) ),
				/**
				 * Modify the Payment Method label when creating a Mollie field.
				 *
				 * @since 1.0
				 *
				 * @param string $label   The label to be filtered.
				 * @param int    $form_id The current form ID.
				 */
				json_encode( gf_apply_filters( array( 'gform_mollie_payment_method_label', rgget( 'id' ) ), esc_html__( 'Payment Method', 'gravityformsmollie' ), rgget( 'id' ) ) ),
                $default_payment_method ) . PHP_EOL;
	}

	/**
	 * Registers the script returned by get_form_inline_script_on_page_render() for display on the front-end.
	 *
	 * @since 1.0
	 *
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_form_inline_script_on_page_render( $form ) {

		if ( ! gf_mollie()->initialize_api() ) {
			return '';
		}

		if ( $this->forceSSL && ! GFCommon::is_ssl() && ! GFCommon::is_preview() ) {
			$script = "document.location.href='" . esc_js( RGFormsModel::get_current_page_url( true ) ) . "';";
		} else {
			$card_rules = $this->get_credit_card_rules();
			$script     = "if(!window['gf_cc_rules']){window['gf_cc_rules'] = new Array(); } window['gf_cc_rules'] = " . GFCommon::json_encode( $card_rules ) . ";";
		}

		return $script;
	}

	public function get_credit_card_rules() {

		$cards = GFCommon::get_card_types();
		$rules = array();

		foreach ( $cards as $card ) {
			if ( ! $this->is_card_supported( $card['slug'] ) ) {
				continue;
			}
			$prefixes = explode( ',', $card['prefixes'] );
			foreach ( $prefixes as $prefix ) {
				$rules[ $card['slug'] ][] = $prefix;
			}
		}

		return $rules;
	}

	/**
	 * Used to determine the required validation result.
	 *
	 * @since 1.0
	 *
	 * @param int $form_id The ID of the form currently being processed.
	 *
	 * @return bool
	 */
	public function is_value_submission_empty( $form_id ) {
		$payment_method = rgpost( 'input_' . $this->id . '_6' );

		if ( empty( $payment_method ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Override the parent validate method.
	 *
	 * @since 1.0
	 *
	 * @param array|string $value The field value.
	 * @param array        $form  The form object.
	 */
	public function validate( $value, $form ) {
		// do nothing here.
	}

	/**
	 * Get submission value.
	 *
	 * @since 1.0
	 *
	 * @param array $field_values Field values.
	 * @param bool  $get_from_post_global_var True if get from global $_POST.
	 *
	 * @return array|string
	 */
	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		if ( $get_from_post_global_var ) {
			$value[ $this->id . '.6' ] = $this->get_input_value_submission( 'input_' . $this->id . '_6', rgar( $this->inputs[5], 'name' ), $field_values, true );
		} else {
			$value = $this->get_input_value_submission( 'input_' . $this->id, $this->inputName, $field_values, $get_from_post_global_var );
		}

		return $value;
	}

	/**
	 * Get field input.
	 *
	 * @since 1.0
	 *
	 * @param array      $form  The Form Object currently being processed.
	 * @param array      $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param null|array $entry Null or the Entry Object currently being edited.
	 *
	 * @return string
	 */
	public function get_field_input( $form, $value = array(), $entry = null ) {
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin        = $is_entry_detail || $is_form_editor;

		// Get the payment method choices.
		$method_value  = rgar( $value, $this->id . '.6' ) ? $value[ $this->id . '.6' ] : rgar( $this, 'defaultPaymentMethod' );
		$this->choices = self::get_choices( $method_value );

		$messages = $this->get_field_sidebar_messages();
		if ( ! empty( $messages ) && is_string( $messages ) ) {
			if ( $is_admin ) {
				return '<div class="gfield--mollie-message">' . $messages . '</div>';
			} else {
				return '<div class="gfield_description validation_message gfield_validation_message">' . $messages . '</div>';
			}
		}

		$form_id  = $form['id'];
		$id       = intval( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$form_id  = ( $is_entry_detail || $is_form_editor ) && empty( $form_id ) ? rgget( 'id' ) : $form_id;

		$disabled_text = $is_form_editor ? "disabled='disabled'" : '';
		$class_suffix  = $is_entry_detail ? '_admin' : '';

		$form_sub_label_placement  = rgar( $form, 'subLabelPlacement' );
		$field_sub_label_placement  = $this->subLabelPlacement;
		$is_sub_label_above        = $field_sub_label_placement == 'above' || ( empty( $field_sub_label_placement ) && $form_sub_label_placement == 'above' );
		$sub_label_class_attribute = $field_sub_label_placement == 'hidden_label' ? "class='hidden_sub_label screen-reader-text'" : " class='gform-field-label gform-field-label--type-sub'";

		$card_icons         = '';
		$cards              = GFCommon::get_card_types();
		$enabled_card_names = array();
		$card_style         = $this->creditCardStyle ? $this->creditCardStyle : 'style1';

		foreach ( $cards as $card ) {
			$style = '';
			if ( $this->is_card_supported( $card['slug'] ) ) {
				$print_card           = true;
				$enabled_card_names[] = rgar( $card, 'name' );
			} elseif ( $is_form_editor || $is_entry_detail ) {
				$print_card = true;
				$style      = "style='display:none;'";
			} else {
				$print_card = false;
			}

			if ( $print_card ) {
				$card_icons .= "<div class='gform_card_icon gform_card_icon_{$card['slug']}' {$style}>{$card['name']}</div>";
			}
		}

		$card_describer = sprintf(
			"<span class='screen-reader-text' id='field_%d_%d_supported_creditcards'>%s %s</span>",
			$form_id,
			$this->id,
			esc_html__( 'Supported Credit Cards:', 'gravityformsmollie' ),
			implode( ', ', $enabled_card_names )
		);
		$card_icons     = "<div class='gform_card_icon_container gform_card_icon_{$card_style}'>{$card_icons}{$card_describer}</div>";

		$size      = 'medium';
		$class     = $size . $class_suffix;
		$css_class = trim( esc_attr( $class ) . ' gfield_select' );
		$style     = ( count( $this->choices ) > 1 ) ? '' : 'style="display:none;"';

		// Display the Payment Method dropdown in the form editor even if only one payment method supported.
		if ( ( count( $this->choices ) === 1 ) && rgars( $this->choices, '0/value' ) !== 'creditcard' ) {
			$style = '';
		}

		$payment_method_field = sprintf( "<div class='ginput_container ginput_container_select ginput_mollie_payment_method' $style><select name='input_%d.6' id='%s' class='%s' %s>%s</select></div>", $id, $field_id, $css_class, $disabled_text, GFCommon::get_select_choices( $this, $method_value ) );

		// The card number field.
		$card_number_field_input = GFFormsModel::get_input( $this, $this->id . '.1' );
		$card_number_label       = rgar( $card_number_field_input, 'customLabel' ) != '' ? $card_number_field_input['customLabel'] : esc_html__( 'Card Number', 'gravityforms' );
		$card_number_label       = gf_apply_filters( array(
			'gform_card_number',
			$form_id
		), $card_number_label, $form_id );

		if ( $is_sub_label_above ) {
			$card_field = "<span class='ginput_full{$class_suffix} gform-grid-col' id='{$field_id}_1_container'>
                                    {$card_icons}
                                    <label for='{$field_id}_1' id='{$field_id}_1_label' {$sub_label_class_attribute}>{$card_number_label}</label>
                                    <span id='{$field_id}_1' class='ginput_card_field ginput_card_number gform-theme-field-control'></span>
                                 </span>";
		} else {
			$card_field = "<span class='ginput_full{$class_suffix} gform-grid-col' id='{$field_id}_1_container '>
                                    {$card_icons}
                                    <span id='{$field_id}_1' class='ginput_card_field ginput_card_number gform-theme-field-control'></span>
                                    <label for='{$field_id}_1' id='{$field_id}_1_label' {$sub_label_class_attribute}>{$card_number_label}</label>
                                 </span>";
		}

		// The expiration date field.
		$expiration_date_input = GFFormsModel::get_input( $this, $this->id . '.2' );
		$expiration_label      = rgar( $expiration_date_input, 'customLabel' ) != '' ? $expiration_date_input['customLabel'] : esc_html__( 'Expiration Date', 'gravityformsmollie' );
		$expiration_label      = gf_apply_filters( array(
			'gform_card_expiration',
			$form_id
		), $expiration_label, $form_id );

		if ( $is_sub_label_above ) {
			$expiration_field = "<span class='ginput_full{$class_suffix} ginput_cardextras gform-grid-col gform-grid-row' id='{$field_id}_2_container'>
                                            <span class='gform-grid--col-spacing-4 ginput_cardinfo_left{$class_suffix}' id='{$field_id}_2_cardinfo_left'>
                                                <label for='{$field_id}_2' {$sub_label_class_attribute}>{$expiration_label}</label>
                                                <span id='{$field_id}_2' class='ginput_card_field ginput_card_expiration gform-theme-field-control'></span>
                                            </span>";

		} else {
			$expiration_field = "<span class='ginput_full{$class_suffix} ginput_cardextras gform-grid-col gform-grid-row' id='{$field_id}_2_container'>
                                            <span class='gform-grid-col ginput_cardinfo_left{$class_suffix}' id='{$field_id}_2_cardinfo_left'>
                                                <span id='{$field_id}_2' class='ginput_card_field ginput_card_expiration gform-theme-field-control'></span>
                                                <label for='{$field_id}_2' {$sub_label_class_attribute}>{$expiration_label}</label>
                                            </span>";
		}

		// The security code field.
		$security_code_field_input = GFFormsModel::get_input( $this, $this->id . '.3' );
		$security_code_label       = rgar( $security_code_field_input, 'customLabel' ) != '' ? $security_code_field_input['customLabel'] : esc_html__( 'Security Code', 'gravityforms' );
		$security_code_label       = gf_apply_filters( array(
			'gform_card_security_code',
			$form_id
		), $security_code_label, $form_id );

		if ( $is_sub_label_above ) {
			$security_field = "<span class='ginput_cardinfo_right{$class_suffix} gform-grid-col' id='{$field_id}_2_cardinfo_right gform-theme-field-control'>
                                                <label for='{$field_id}_3' {$sub_label_class_attribute}>$security_code_label</label>
                                                <span id='{$field_id}_3' class='ginput_card_field ginput_card_security_code gform-theme-field-control'></span>
                                                <span class='ginput_card_security_code_icon'>&nbsp;</span>
                                             </span>
                                        </span>";
		} else {
			$security_field = "<span class='ginput_cardinfo_right{$class_suffix} gform-grid-col' id='{$field_id}_2_cardinfo_right'>
                                                <span id='{$field_id}_3' class='ginput_card_field ginput_card_security_code gform-theme-field-control'></span>
                                                <span class='ginput_card_security_code_icon'>&nbsp;</span>
                                                <label for='{$field_id}_3' {$sub_label_class_attribute}>$security_code_label</label>
                                             </span>
                                        </span>";
		}

		// The card holder name field.
		$card_name_field_input = GFFormsModel::get_input( $this, $this->id . '.5' );
		$card_name_label       = rgar( $card_name_field_input, 'customLabel' ) != '' ? $card_name_field_input['customLabel'] : esc_html__( 'Cardholder Name', 'gravityforms' );
		$card_name_label       = gf_apply_filters( array( 'gform_card_name', $form_id ), $card_name_label, $form_id );

		if ( $is_sub_label_above ) {
			$card_name_field = "<span class='ginput_full{$class_suffix} gform-grid-col' id='{$field_id}_5_container'>
                                            <label for='{$field_id}_5' id='{$field_id}_5_label' {$sub_label_class_attribute}>{$card_name_label}</label>
                                            <span id='{$field_id}_5' class='ginput_card_field ginput_card_name gform-theme-field-control'></span>
                                        </span>";
		} else {
			$card_name_field = "<span class='ginput_full{$class_suffix} gform-grid-col' id='{$field_id}_5_container'>
                                            <span id='{$field_id}_5' class='ginput_card_field ginput_card_name gform-theme-field-control'></span>
                                            <label for='{$field_id}_5' id='{$field_id}_5_label' {$sub_label_class_attribute}>{$card_name_label}</label>
                                        </span>";
		}

		// Display Mollie Components if Credit Card is supported, or hide them.
		if ( ( count( $this->choices ) === 1 ) ) {
			if ( rgars( $this->choices, '0/value' ) === 'creditcard' ) {
				$style = '';
			} else {
				$style = 'style="display: none;"';
			}
		}

		// Display note in the form editor depends on payment methods supported.
		$field_note = '';
		if ( $is_form_editor && ! empty( $messages['content'] ) ) {
			$field_note = '<p class="gfield--mollie-message"><em>' . $messages['content'] . '</em></p>';
		}

		$field_input = $payment_method_field . $field_note . "<div class='ginput_complex{$class_suffix} ginput_container ginput_container_mollie_components gform-grid-row' id='{$field_id}' $style>" . $card_field . $expiration_field . $security_field . $card_name_field . ' </div>';

		return $field_input;
	}

	/**
	 * Returns the field markup; including field label, description, validation, and the form editor admin buttons.
	 *
	 * The {FIELD} placeholder will be replaced in GFFormDisplay::get_field_content with the markup returned by GF_Field::get_field_input().
	 *
	 * @since 1.0
	 *
	 * @param string|array $value                The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param bool         $force_frontend_label Should the frontend label be displayed in the admin even if an admin label is configured.
	 * @param array        $form                 The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_field_content( $value, $force_frontend_label, $form ) {
		// Get the default HTML markup.
		$form_id = (int) rgar( $form, 'id' );

		$field_label = $this->get_field_label( $force_frontend_label, $value );

		$validation_message_id = 'validation_message_' . $form_id . '_' . $this->id;
		$validation_message    = ( $this->failed_validation && ! empty( $this->validation_message ) ) ? sprintf( "<div id='%s' class='gfield_description gfield_validation_message validation_message' aria-live='polite'>%s</div>", $validation_message_id, $this->validation_message ) : '';

		$is_form_editor  = $this->is_form_editor();
		$is_entry_detail = $this->is_entry_detail();
		$is_admin        = $is_form_editor || $is_entry_detail;

		$required_div = $is_admin || $this->isRequired ? sprintf( "<span class='gfield_required'>%s</span>", $this->isRequired ? '*' : '' ) : '';

		$admin_buttons = $this->get_admin_buttons();

		$target_input_id = $this->get_first_input_id( $form );

		$label_tag = method_exists( $this, 'get_field_label_tag' ) ? $this->get_field_label_tag( $form ) : 'label';

		if ( $is_form_editor && 'legend' === $label_tag ) {
			// Label wrapper is required for correct positioning of the legend in compact view in Safari.
			$legend_wrapper       = '<span>';
			$legend_wrapper_close = '</span>';
		} else {
			$legend_wrapper       = '';
			$legend_wrapper_close = '';
		}

		$for_attribute = ( empty( $target_input_id ) || $label_tag !== 'label' ) ? '' : "for='{$target_input_id}'";

		$description = $this->get_description( $this->description, 'gfield_description' );

		if ( $this->is_description_above( $form ) ) {
			$clear         = $is_admin ? "<div class='gf_clear'></div>" : '';
			$field_content = sprintf( "%s<%s class='%s' %s >$legend_wrapper%s%s$legend_wrapper_close</%s>%s{FIELD}%s%s", $admin_buttons, $label_tag, esc_attr( $this->get_field_label_class() ), $for_attribute, esc_html( $field_label ), $required_div, $label_tag, $description, $validation_message, $clear );
		} else {
			$field_content = sprintf( "%s<%s class='%s' %s >$legend_wrapper%s%s$legend_wrapper_close</%s>{FIELD}%s%s", $admin_buttons, $label_tag, esc_attr( $this->get_field_label_class() ), $for_attribute, esc_html( $field_label ), $required_div, $label_tag, $description, $validation_message );
		}

		// Add the non-ssl warning.
		if ( ! GFCommon::is_ssl() && ! $is_admin ) {
			$field_content = "<div class='gfield_description gfield_validation_message gfield_creditcard_warning_message'><span>" . esc_html__( 'This page is unsecured. Do not enter a real credit card number! Use this field only for testing purposes. ', 'gravityformsmollie' ) . '</span></div>' . $field_content;
		}

		return $field_content;
	}

	/**
	 * Retrieve the payment methods enabled in connected Mollie account.
	 *
	 * @since 1.0
	 *
	 * @param string $value value of field.
	 *
	 * @return array Choices for the Payment Method input.
	 */
	public static function get_choices( $value = '' ) {
		if ( empty( self::$_choices ) ) {
			$methods = gf_mollie()->get_methods();

			if ( count( $methods ) > 0 ) {
				foreach ( $methods as $method ) {
					array_push(
						self::$_choices,
						array(
							'text'       => rgar( $method, 'description' ),
							'value'      => rgar( $method, 'id' ),
							'isSelected' => $value === rgar( $method, 'id' ),
						)
					);
				}
			}
		}

		return self::$_choices;
	}

	/**
	 * Get field label class.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_field_label_class() {
		return 'gfield_label gfield_label_before_complex gfield_label_mollie gform-field-label';
	}

	/**
	 * Get entry inputs.
	 *
	 * @since 1.0
	 *
	 * @return array|null
	 */
	public function get_entry_inputs() {
		$inputs = array();
		foreach ( $this->inputs as $input ) {
			if ( in_array( $input['id'], array( $this->id . '.6', $this->id . '.1', $this->id . '.4' ), true ) ) {
				$inputs[] = $input;
			}
		}

		return $inputs;
	}

	/**
	 * Get the value in entry details.
	 *
	 * @since 1.0
	 *
	 * @param string|array $value    The field value.
	 * @param string       $currency The entry currency code.
	 * @param bool|false   $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param string       $format   The format requested for the location the merge is being used. Possible values: html, text or url.
	 * @param string       $media    The location where the value will be displayed. Possible values: screen or email.
	 *
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		if ( is_array( $value ) ) {
			$card_number = trim( rgget( $this->id . '.1', $value ) );
			$card_type   = trim( rgget( $this->id . '.4', $value ) );
			$method      = trim( rgget( $this->id . '.6', $value ) );
			$method      = gf_mollie()->get_method_label( $method );

			$separator   = $format === 'html' ? '<br/>' : "\n";

			if ( $method === 'Credit card' ) {
                return empty( $card_number ) ? $method : $card_type . $separator . $card_number;
            } else {
			    return $method;
            }
		}

		return '';
	}

	/**
	 * Get the value when saving to an entry.
	 *
	 * @since 1.0
	 *
	 * @param string $value      The value to be saved.
	 * @param array  $form       The Form Object currently being processed.
	 * @param string $input_name The input name used when accessing the $_POST.
	 * @param int    $lead_id    The ID of the Entry currently being processed.
	 * @param array  $lead       The Entry Object currently being processed.
	 *
	 * @return array|string
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		list( $input_token, $field_id_token, $input_id ) = rgexplode( '_', $input_name, 3 );
		if ( $input_id === '6' ) {
			$value = rgpost( "input_{$field_id_token}_6" );
		} else {
			$value = '';
		}

		return $this->sanitize_entry_value( $value, $form['id'] );
	}

	/**
	 * Remove the duplicate admin button.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_admin_buttons() {
		add_filter( 'gform_duplicate_field_link', '__return_empty_string' );

		$admin_buttons = parent::get_admin_buttons();

		remove_filter( 'gform_duplicate_field_link', '__return_empty_string' );

		return $admin_buttons;
	}

	/**
	 * Create the field specific settings UI.
	 *
	 * @since 1.0
	 *
	 * @param int $position The position.
	 */
	public static function default_payment_method_standard_settings( $position ) {
		if ( $position === 1350 ) {
			if ( count( gf_mollie()->get_methods() ) > 1 ) {
				?>
				<li class="mollie_default_payment_method field_setting">
                <label for="field_mollie_default_payment_method" class="section_label">
					<?php esc_html_e( 'Default Payment Method', 'gravityformsmollie' ); ?>
					<?php gform_tooltip( 'mollie_default_payment_method' ) ?>
                </label>
                <select id="field_mollie_default_payment_method" class="field_mollie_default_payment_method">
                    <?php echo self::get_payment_method_dropdown(); ?>
                </select>
				</li>
			<?php } ?>
			<?php
		}
	}

	/**
	 * Add tooltips for our custom setting sections.
	 *
	 * @since 1.0
	 *
	 * @param array $tooltips The tooltips.
	 *
	 * @return array
	 */
	public static function add_tooltips( $tooltips ) {
		$tooltips['mollie_default_payment_method'] = '<h6>' . esc_html__( 'Default Payment Method', 'gravityformsmollie' ) . '</h6>' . esc_html__( 'Set the default payment method. The supported payment methods are in sync with your Mollie account and match the Gravity Forms Currency Setting.', 'gravityformsmollie' );

		return $tooltips;
	}

	/**
     * Get the payment method dropdown in the Default Payment Method settings.
     *
     * @since 1.0
     *
	 * @param string $selected    The selected method.
	 * @param string $placeholder The placeholder.
	 *
	 * @return string
	 */
	public static function get_payment_method_dropdown( $selected = '', $placeholder = '' ) {
		$str     = '';
		$choices = self::get_choices( $selected );
		foreach ( $choices as $choice ) {
			$text = rgar( $choice, 'text' );
			if ( empty( $text ) ) {
				$text = $placeholder;
			}
			$selected = strtolower( esc_attr( rgar( $choice, 'value' ) ) ) == $selected ? "selected='selected'" : '';
			$str .= "<option value='" . esc_attr( rgar( $choice, 'value' ) ) . "' $selected>" . esc_html( $text ) . '</option>';
		}

		return $str;
	}

	/**
	 * Overwrite the parent method to avoid the field upgrade from the credit card field class.
	 *
	 * @since 1.0
	 */
	public function post_convert_field() {
		GF_Field::post_convert_field();
	}
}

try {

	GF_Fields::register( new GF_Field_Mollie() );

} catch ( Exception $e ) {

	gf_mollie()->log_error( 'Unable to register Mollie field; ' . $e->getMessage() );

}
