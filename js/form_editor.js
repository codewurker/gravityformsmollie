window.GFMollieFormEditor = null;

(function ($) {
    window.GFMollieFormEditor = function () {
        var self = this;

        self.init = function () {
            self.hooks();

            self.bindLoadFieldSettings();
        };

        self.hooks = function () {
            gform.addAction('gform_post_load_field_settings', function ( arr ) {
                var field = arr[0];

                if (field['type'] === 'mollie') {
                    $('.sub_labels_setting').addClass('mollie');

                    // Hide #field_settings when the api is not initialized.
                    // This is called right after the settings are shown. So that makes it feel like there's no settings.
                    if (gform_mollie_form_editor_strings.initialize_api === '') {
                        HideSettings('field_settings');
                    }

                    $('#field_mollie_default_payment_method').val(field['defaultPaymentMethod']).on('change', function () {
                        var newMethod = $(this).val();

                        $('.ginput_mollie_payment_method select').val(newMethod);
                        // Update the default payment method in the global form variable.
                        SetFieldProperty('defaultPaymentMethod', newMethod);
                    });

                    // Remove JCB card option.
                    $('.credit_card_setting ul li').each(function (i, elem) {
                        var val = $(elem).children('input').val();
                        if ($.inArray(val, ['jcb', 'discover']) !== -1) {
                            $(elem).hide();
                        }
                    });
                } else {
                    $('.sub_labels_setting').removeClass('mollie');
                }
            });

            gform.addFilter('gform_form_editor_can_field_be_added', function (result, type) {
                if (type === 'mollie') {
                    if (GetFieldsByType(['mollie']).length > 0) {
                        alert(gform_mollie_form_editor_strings.only_one_field);

                        result = false;
                    }
                }

                return result;
            });
        };

        self.bindLoadFieldSettings = function () {
            $(document).bind('gform_load_field_settings', function (event, field, form) {
                if (field['type'] === 'mollie') {
                    // Set up supported credit cards.
                    if (!field.creditCards || field.creditCards.length <= 0)
                        field.creditCards = ['amex', 'visa', 'discover', 'mastercard'];

                    for (i in field.creditCards) {
                        if (!field.creditCards.hasOwnProperty(i))
                            continue;

                        $('#field_credit_card_' + field.creditCards[i]).prop('checked', true);
                    }
                } else {
                    // Restore JCB card option.
                    $('.credit_card_setting ul li:hidden').show();
                }
            });
        };

        self.init();
    };

    $(document).ready(GFMollieFormEditor);
})(jQuery);