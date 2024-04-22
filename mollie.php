<?php
/**
 * Plugin Name: Gravity Forms Mollie Add-On
 * Plugin URI: https://gravityforms.com
 * Description: Integrates Gravity Forms with Mollie, enabling end users to purchase goods and services through Gravity Forms.
 * Version: 1.5.0
 * Author: Gravity Forms
 * Author URI: https://gravityforms.com
 * License: GPL-3.0+
 * Text Domain: gravityformsmollie
 * Domain Path: /languages
 *
 * ------------------------------------------------------------------------
 * Copyright 2020-2023 Rocketgenius, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses.
 */

// Defines the current version of the Gravity Forms Mollie Add-On.
define( 'GF_MOLLIE_VERSION', '1.5.0' );

// After GF is loaded, load the add-on.
add_action( 'gform_loaded', array( 'GF_Mollie_Bootstrap', 'load_addon' ), 5 );

/**
 * Loads the Gravity Forms Mollie Add-On.
 *
 * Includes the main class and registers it with GFAddOn.
 *
 * @since 1.0
 */
class GF_Mollie_Bootstrap {

	/**
	 * If the Payment Add-On Framework exists, Mollie Add-On is loaded.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	public static function load_addon() {

		if ( ! method_exists( 'GFForms', 'include_payment_addon_framework' ) ) {
			return;
		}

		// Requires the class file.
		require_once plugin_dir_path( __FILE__ ) . '/class-gf-mollie.php';

		// Registers the class name with GFAddOn.
		GFAddOn::register( 'GF_Mollie' );

		if ( ! GF_Fields::exists( 'mollie' ) ) {

			// Require the Payment Method field file.
			require_once 'includes/class-gf-field-mollie.php';

		}
	}
}

/**
 * Returns an instance of the GF_Mollie class
 *
 * @since 1.0
 *
 * @return GF_Mollie An instance of the GF_Mollie class
 */
function gf_mollie() {
	return GF_Mollie::get_instance();
}
