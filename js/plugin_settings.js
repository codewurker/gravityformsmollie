/* global jQuery, ajaxurl, gform_mollie_pluginsettings_strings */
/* eslint-disable camelcase, no-var */

window.GFMollieSettings = null;
window.setting_mollie = gform_mollie_pluginsettings_strings;

( function( $ ) {
	var GFMollieSettings = function() {
		var self = this;
		var isLegacy = 'true' === gform_mollie_pluginsettings_strings.is_legacy;
		var prefixes = {
			input: isLegacy ? '_gaddon_setting_' : '_gform_setting_',
			field: isLegacy ? 'gaddon-setting-row-' : 'gform-setting-',
		};

		var saveButton = $( '#gform-settings-save' );

		self.ui = {
			buttons: {
				connect: $( '#gform_mollie_connect_button' ),
				disconnect: $( '#gform_mollie_disconnect_button' ),
				save: saveButton,
			},
			mode: $( '[name="' + prefixes.input + 'mode"]' ),
			profileID: $( '#profile_id' ),
			saveContainer: isLegacy ? saveButton.parent().parent() : saveButton.parent(),
			selectedModeValue: $( '[name="' + prefixes.input + 'mode"]:checked' ).val(),
		};

		this.init = function() {
			this.bindSettingsChange();
			this.bindDisconnect();
		};

		this.bindSettingsChange = function() {
			self.ui.mode.on( 'change', function() {
				self.ui.selectedModeValue = $( this ).val();

				if ( self.ui.buttons.disconnect.length ) {
					self.ui.saveContainer.fadeIn();
				}
			} );

			self.ui.buttons.connect.on( 'click', function( e ) {
				e.preventDefault();

				window.location.href = $( this ).attr( 'href' ) + '&mode=' + self.ui.selectedModeValue;
			} );

			self.ui.profileID.on( 'change', function() {
				self.ui.saveContainer.fadeIn();
			} );
		};

		this.bindDisconnect = function() {
			// Disconnect from Mollie.
			self.ui.buttons.disconnect.on( 'click', function( e ) {
				// Prevent default event.
				e.preventDefault();

				// Get confirmation from user.
				/* eslint-disable no-alert */
				if ( ! window.confirm( gform_mollie_pluginsettings_strings.disconnect ) ) {
					return;
				}

				// Set disabled state on button.
				self.ui.buttons.disconnect.attr( 'disabled', 'disabled' );

				// Send request to disconnect.
				$.post( {
					url: ajaxurl,
					dataType: 'json',
					data: {
						action: 'gfmollie_deauthorize',
						nonce: gform_mollie_pluginsettings_strings.ajax_nonce,
					},
					success: function( response ) {
						if ( response.success ) {
							window.location.reload();
						} else {
							window.alert( response.error );
						}

						self.ui.buttons.disconnect.removeAttr( 'disabled' );
					},
					fail: function( response ) {
						window.alert( response.error );
					},
				} );
			} );
		};

		this.init();
	};

	$( document ).ready( GFMollieSettings );
} )( jQuery );
