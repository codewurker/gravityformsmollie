/**
 * Front-end Script
 */

window.GFMollie = null;

gform.extensions = gform.extensions || {};
gform.extensions.styles = gform.extensions.styles || {};
gform.extensions.styles.gravityformsmollie = gform.extensions.styles.gravityformsmollie || {};

(function ($) {

    GFMollie = function (args) {

        for (var prop in args) {
            if (args.hasOwnProperty(prop)) {
                this[prop] = args[prop];
            }
        }

        var that = this;

        this.mollie = null;
        this.form = null;
        this.ccfields = null;
        this.activeFeed = null;
        this.feedActivated = false;
        this.cardHasError = {};

        // Setup the elements for Mollie components.
        this.cardHolder = this.cardNumber = this.cardNumberInput = this.expiryDate = this.verificationCode = null;

		this.cardStyle = this.cardStyle || {};

		gform.extensions.styles.gravityformsmollie[ this.formId ] = gform.extensions.styles.gravityformsmollie[ this.formId ] || {};

		this.componentStyles = gform.extensions.styles.gravityformsmollie[ this.formId ][ this.pageInstance ] || {};
		this.setComponentStyleValue = function( key, value, themeFrameworkStyles, manualElement ) {
			let resolvedValue = '';

			// If the value provided is a custom property let's begin
			if ( value.indexOf( '--' ) === 0 ) {
				const computedValue = themeFrameworkStyles.getPropertyValue( value );

				// If we have a computed end value from the custom property, let's use that
				if ( computedValue ) {
                    // Clean up and remove any whitespace derived from CSS formatting when using getPropertyValue.
                    // CSS supports white space inside of rgb/rgba parentheses, however Mollie seems to fail out on them.
                    if ( ( key === 'backgroundColor' || key === 'color' ) && ( computedValue.includes( 'rgba( ' ) || computedValue.includes( 'rgb( ' ) ) ) {
                        resolvedValue = computedValue.includes( 'rgba( ' ) ? computedValue.replace( 'rgba( ', 'rgba(' ) : computedValue.replace( 'rgb( ', 'rgb(' );
                    } else {
                        resolvedValue = computedValue;
                    }
				}
				// Otherwise, let's use a provided element or the form wrapper
				// along with the key to nab the computed end value for the CSS property
				else {
					const selector = manualElement ? getComputedStyle( manualElement ) : themeFrameworkStyles;
					const resolvedKey = key === 'fontSmoothing' ? '-webkit-font-smoothing' : key;
					resolvedValue = selector.getPropertyValue( resolvedKey.replace( /([a-z])([A-Z])/g, '$1-$2' ).toLowerCase() );
				}
			}
			// Otherwise let's treat the provided value as the actual CSS value wanted
			else {
				resolvedValue = value;
			}

			return resolvedValue.trim();
		};

		this.setComponentStyles = function( obj, objKey, parentKey ) {
			// If our object doesn't have any styles specified, let's bail here
			if ( Object.keys( obj ).length === 0 ) {
				return;
			}

			// Grab the computed styles for the form, which the global CSS API and theme framework are scoped to
			const form = document.getElementById( 'gform_' + this.formId );
			const themeFrameworkStyles = getComputedStyle( form );

			// Grab the first form control in the form for fallback CSS property value computation
			const firstFormControl = form.querySelector( '.gfield input' )

			// Note, this currently only supports three levels deep of object nesting.
			Object.keys( obj ).forEach( ( key ) => {
				// Handling of keys that are objects with additional key/value pairs
				if ( typeof obj[ key ] === 'object' ) {

					// Create object for top level key
					if ( ! parentKey ) {
						that.cardStyle[ key ] = {};
					}

					// Create object for second level key
					if ( parentKey ) {
						that.cardStyle[ parentKey ][ key ] = {};
					}

					const objPath = parentKey ? parentKey : key;

					// Recursively pass each key's object through our method for continued processing
					this.setComponentStyles( obj[ key ], key, objPath );

					return;
				}

				// Handling of keys that are not objects and need their value to be set
				if ( typeof obj[ key ] !== 'object' ) {
					let value = '';
					// Handling of nested keys
					if ( parentKey ) {
						if ( objKey && objKey !== parentKey ) {
							// Setting value for a key three levels into the object
							value = this.setComponentStyleValue( key, that.componentStyles[ parentKey ][ objKey ][ key ], themeFrameworkStyles, firstFormControl );
							if ( value ) {
								that.cardStyle[ parentKey ][ objKey ][ key ] = value;
							}
						} else {
							// Setting value for a key two levels into the object
							value = this.setComponentStyleValue( key, that.componentStyles[ parentKey ][ key ], themeFrameworkStyles, firstFormControl );
							if ( value ) {
								that.cardStyle[ parentKey ][ key ] = value;
							}
						}
					} else {
						// Setting value for a key one level into the object
						value = this.setComponentStyleValue( key, that.componentStyles[ key ], themeFrameworkStyles, firstFormControl );
						if ( value ) {
							that.cardStyle[ key ] = value;
						}
					}
				}
			} );
		};

		this.setComponentStyles( that.componentStyles );

        this.init = function () {

            this.form = $('#gform_' + this.formId);
            this.ccfields = '#field_' + this.formId + '_' + this.ccFieldId;
            this.cardNumberInput = '#input_' + this.formId + '_' + this.ccFieldId + '_1';

            this.bindFormSubmit();

            if (!this.isPaymentFieldOnPage()) {
                return;
            }

            this.bindSetPaymentMethod();
            this.bindFrontendFeedsEvaluated();

        };

        this.bindFormSubmit = function() {
            this.form.on('submit', function (e) {

                that.clearError();

                if (!that.feedActivated ||
                    !that.isCreditCardMethodSelected(that.formId) ||
                    $('#gform_save_' + that.formId).val() === '1' ||
                    $(this).data('gfmolliesubmitting') ||
                    !that.isPaymentFieldOnPage()) {
                    return;
                }

                // Clear card token if click on the Previous button, when the credit card fields are on page.
                if (that.isGoPrevPage(that.formId)) {
                    that.clearCardToken(that.formId);
                    return;
                }

                // If mollieCardToken has been set, we're good to go.
                if (that.getCardToken(that.formId) && !that.isPaymentFieldOnPage()) {
                    // Field exists with value, do not create new token.
                    return;
                }

                e.preventDefault();

                if (!$(that.form).data('gfmolliesubmitting')) {
                    $(that.form).data('gfmolliesubmitting', true);
                }

                that.handleFormSubmit();

            });
        };

        this.bindFrontendFeedsEvaluated = function () {
            gform.addAction('gform_frontend_feeds_evaluated', function (feeds, formId) {
                if (formId !== that.formId) {
                    return;
                }

                that.feedActivated = false;

                for (var i = 0; i < Object.keys(feeds).length; i++) {
                    if (feeds[i].addonSlug === 'gravityformsmollie' && feeds[i].isActivated) {
                        that.feedActivated = true;

                        for (var j = 0; j < Object.keys(that.feeds).length; j++) {
                            if (that.feeds[j].feedId === feeds[i].feedId) {
                                that.activeFeed = that.feeds[j];

                                break;
                            }
                        }

                        break; // allow only one active feed.
                    }
                }

                if (!that.feedActivated) {
                    that.activeFeed = null;

                    that.unmountMollieComponents();

                    $('.gfield_mollie').hide();
                } else {
                    that.maybeShowMollieField(that.formId);
                    that.maybeShowCcFields(that.formId);
                }
            });
        };

        this.bindSetPaymentMethod = function() {
            // Initialize Mollie Components object.
            if (this.profileId !== '') {
                this.mollie = Mollie(
                    this.profileId,
                    {
                        locale: gform_mollie_components_strings.locale,
                        testmode: this.testMode,
                        loglevel: 1,
                    }
                );
            }

            $('.ginput_mollie_payment_method select').on('change', function() {
                that.clearError();
                that.maybeShowCcFields(that.formId);
            });
        }

        this.createMollieComponents = function () {
            if (this.profileId === '' || this.cardNumber !== null) {
                return;
            }

            var options = { styles: that.cardStyle };

            this.cardHolder = that.mollie.createComponent('cardHolder', options);
            this.cardNumber = that.mollie.createComponent('cardNumber', options);
            this.expiryDate = that.mollie.createComponent('expiryDate', options);
            this.verificationCode = that.mollie.createComponent('verificationCode', options);
        }

        this.mountMollieComponents = function () {
            if (this.profileId === '' || $(this.cardNumberInput).find('.mollie-component').length ) {
                return;
            }

            this.cardHolder.mount('#input_' + this.formId + '_' + this.ccFieldId + '_5');
            this.cardNumber.mount(this.cardNumberInput);
            this.expiryDate.mount('#input_' + this.formId + '_' + this.ccFieldId + '_2');
            this.verificationCode.mount('#input_' + this.formId + '_' + this.ccFieldId + '_3');

            this.cardNumber.addEventListener('focus', function (e) {
                wp.a11y.speak( $( '#field_' + that.formId + '_' + that.ccFieldId + '_supported_creditcards' ).text() );
                that.clearError();
            });

            this.cardNumber.addEventListener('change', this.handleComponentError);
            this.cardHolder.addEventListener('change', this.handleComponentError);
            this.expiryDate.addEventListener('change', this.handleComponentError);
            this.verificationCode.addEventListener('change', this.handleComponentError);
        }

        this.unmountMollieComponents = function () {
            if (! $(this.cardNumberInput).find('.mollie-component').length) {
                return;
            }

            this.cardNumber.unmount();
            this.cardHolder.unmount();
            this.expiryDate.unmount();
            this.verificationCode.unmount();

            this.hideCcFields(that.formId);
        }

        this.handleComponentError = function (e) {
            if ( e.error && e.touched ) {
                var error = { type: this.type, message: e.error };
                that.showError( error );
            } else {
                that.clearError();
                delete that.cardHasError[ this.type ];

                // We cached errors from different fields so once the current field's error is cleared,
                // we can keep displaying the remaining error from the other fields.
                if ( Object.keys( that.cardHasError ).length ) {
                    var cardFields = [ 'cardNumber', 'expiryDate', 'verificationCode', 'cardHolder' ];

                    for ( var i = 0; i < cardFields.length; i++ ) {
                        var key = cardFields[ i ];
                        if ( that.cardHasError.hasOwnProperty( key ) ) {
                            that.showError( { type: key, message: that.cardHasError[ key ] } );

                            return false;
                        }
                    }
                }
            }
        };

        this.handleFormSubmit = function () {
            gformAddSpinner(that.formId);

            that.mollie.createToken().then(function (result) {

                if (result.error) {
                    if (result.error.message) {
                        that.showError(result.error);
                    }
                    that.resetMollieStatus();
                    return;
                }

                that.addTokenInputToForm(result.token, that.formId);

                // Submit form to the server
                that.form.submit();
            });

        }

        this.paymentMethodSelected = function (formId) {
            var value = this.form.find('.ginput_mollie_payment_method select').val();

            // When the conditional logic enabled in the Mollie field, the payment method select value is null.
            if ( value === null ) {
                var selected = this.form.find('.ginput_mollie_payment_method select option[selected]');
                if ( selected.length ) {
                    this.form.find('.ginput_mollie_payment_method select').val( selected.val() );

                    value = selected.val();
                }
            }

            return value;
        }

        this.isCreditCardMethodSelected = function (formId) {
            return 'creditcard' === this.paymentMethodSelected(formId);
        }

		/**
		 * @function isConversationalForm
		 * @description Determines if we are on conversational form mode
		 *
		 * @since 1.4.0
		 *
		 * @returns {boolean}
		 */
		this.isConversationalForm = function () {
			return typeof gfcf_theme_config !== 'undefined' ? ( gfcf_theme_config !== null && typeof gfcf_theme_config.data !== 'undefined' ? gfcf_theme_config.data.is_conversational_form : undefined ) : false;
		}

        this.maybeShowMollieField = function (formId) {
            // Hide the Mollie field if it's just one payment method.
            if (this.form.find('.ginput_mollie_payment_method select option').length === 1) {
                if (this.isCreditCardMethodSelected(formId)) {
                    this.form.find('.ginput_mollie_payment_method').hide();
                } else {
                    this.form.find('.gfield_mollie').hide();
                }
            } else {
                this.form.find('.gfield_mollie').css( 'display', '' );
            }
        }

        this.maybeShowCcFields = function (formId) {
            if (this.isCreditCardMethodSelected(formId)) {
                this.showCcFields();

                this.createMollieComponents();
                this.mountMollieComponents();
            } else {
                this.hideCcFields();
            }

        }

        this.isGoPrevPage = function (formId) {
            var sourcePage = parseInt($('#gform_source_page_number_' + formId).val(), 10),
                targetPage = parseInt($('#gform_target_page_number_' + formId).val(), 10);

            return (sourcePage > targetPage && targetPage !== 0);
        }

        this.showCcFields = function () {
            this.form.find('.ginput_container_mollie_components').removeClass('gf_invisible');
        }

        this.hideCcFields = function () {
            this.form.find('.ginput_container_mollie_components').addClass('gf_invisible');
        }

        this.showError = function (error) {
            // Hide spinner.
            if ($('#gform_ajax_spinner_' + this.formId).length > 0) {
                $('#gform_ajax_spinner_' + this.formId).remove();
            }
            // Add field error class.
            $(this.ccfields).addClass('gfield_error');

            if (!$(this.ccfields).find('.validation_message').length) {
                $(this.ccfields).append('<div class="gfield_description gfield_validation_message validation_message"></div>');
            }

            var errorContainer = $(this.ccfields).find('.validation_message'),
                errorMsg = gform_mollie_components_strings.errorprefix;

            if ( error.message ) {
                errorMsg = errorMsg + ': ' + error.message;
            }

            that.cardHasError[ error.type ] = error.message ? error.message : '';

            errorContainer.text( errorMsg );
            wp.a11y.speak( errorMsg );
        }

        this.clearError = function () {
            $(this.ccfields).removeClass('gfield_error').find('.validation_message').remove();
        }

        this.addTokenInputToForm = function (token, formId) {

            // Add token to the form
            if (0 === $('#gform_' + formId + ' input[name=cardToken]').length) {
                var tokenInput = document.createElement('input');
                tokenInput.setAttribute('type', 'hidden');
                tokenInput.setAttribute('name', 'cardToken');
                this.form.append(tokenInput);
            }

            this.setCardToken(token, formId);
        }

        this.setCardToken = function (token, formId) {
            $('#gform_' + formId + ' input[name=cardToken]').val(token);
        }

        this.getCardToken = function (formId) {
            var el = $('#gform_' + formId + ' input[name=cardToken]');
            return 1 === el.length && el.val() !== '' ? el.val() : false;
        }

        this.clearCardToken = function (formId) {
            $('#gform_' + formId + ' input[name=cardToken]').val('');
        }

        this.isPaymentFieldOnPage = function () {

            var currentPage = this.getCurrentPageNumber();

            if (!$('#input_' + this.formId + '_' + this.ccFieldId + '_1').length) {
                return false;
            }

            // if current page is false or no credit card page number, assume this is not a multi-page form
            if (!this.pmfieldPage || !currentPage || this.isConversationalForm() )
                return true;

            return this.pmfieldPage == currentPage;
        };

        this.getCurrentPageNumber = function () {
            var currentPageInput = $('#gform_source_page_number_' + this.formId);
            return currentPageInput.length > 0 ? currentPageInput.val() : false;
        };

        this.isLastPage = function () {
            var targetPageInput = $('#gform_target_page_number_' + this.formId);
            if (targetPageInput.length > 0)
                return targetPageInput.val() === '0';

            return true;
        };

        this.resetMollieStatus = function () {
            $(this.form).data('gfmolliesubmitting', false);

            $('#gform_ajax_spinner_' + this.formId).remove();

            // must do this or the form cannot be submitted again
            if (this.isLastPage()) {
                window["gf_submitting_" + this.formId] = false;
            }

            this.clearCardToken(this.formId);
        };

        this.init();
    }

})(jQuery);
