<?php

/**
 * Gravity Forms Mollie Add-On
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2009 - 2020, Rocketgenius
 */

defined( 'ABSPATH' ) || die();

// Include the Gravity Forms Payment Add-On Framework.
GFForms::include_payment_addon_framework();

/**
 * Class GF_Mollie
 *
 * Primary class to manage the Mollie Add-On.
 *
 * @since 1.0
 */
class GF_Mollie extends GFPaymentAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since 1.0
	 *
	 * @var GF_Mollie $_instance If available, contains an instance of this class
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Gravity Forms Mollie Add-On.
	 *
	 * @since 1.0
	 *
	 * @var string $_version Contains the version, defined in mollie.php.
	 */
	protected $_version = GF_MOLLIE_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since 1.0
	 *
	 * @var string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = '2.0';

	/**
	 * Defines the plugin slug.
	 *
	 * @since 1.0
	 *
	 * @var string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformsmollie';

	/**
	 * Defines the main plugin file.
	 *
	 * @since 1.0
	 *
	 * @var string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformsmollie/mollie.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since 1.0
	 *
	 * @var string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this Add-On can be found.
	 *
	 * @since 1.0
	 *
	 * @var string The URL of the Add-On.
	 */
	protected $_url = 'https://www.gravityforms.com';

	/**
	 * Defines the title of this Add-On.
	 *
	 * @since 1.0
	 *
	 * @var string $_title The title of the Add-On.
	 */
	protected $_title = 'Gravity Forms Mollie Add-On';

	/**
	 * Defines the short title of the Add-On.
	 *
	 * @since 1.0
	 *
	 * @var string $_short_title The short title.
	 */
	protected $_short_title = 'Mollie';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since 1.0
	 *
	 * @var bool $_enable_rg_autoupgrade true
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since 1.0
	 *
	 * @var string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_mollie';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since 1.0
	 *
	 * @var string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_mollie';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since 1.0
	 *
	 * @var string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_mollie_uninstall';

	/**
	 * Defines the capabilities needed for the Mollie Add-On
	 *
	 * @since 1.0
	 *
	 * @var array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_mollie', 'gravityforms_mollie_uninstall' );

	/**
	 * Enable callbacks/webhooks/IPN, so the appropriate database table will be created.
	 *
	 * @since 1.0
	 *
	 * @var bool True if the Add-On supports callbacks. Otherwise, false.
	 */
	protected $_supports_callbacks = true;

	/**
	 * If true, feeds w/ conditional logic will evaluated on the frontend and a JS event will be triggered when the feed
	 * is applicable and inapplicable.
	 *
	 * @since 1.0
	 * @access protected
	 *
	 * @var bool
	 */
	protected $_supports_frontend_feeds = true;

	protected $_enable_theme_layer = true;


	/**
	 * Contains an instance of the Mollie API library, if available.
	 *
	 * @since 1.0
	 *
	 * @var null|false|GF_Mollie_API $api If available, contains an instance of the Mollie API library.
	 */
	protected $api = null;

	/**
	 * Get an instance of this class.
	 *
	 * @since 1.0
	 *
	 * @return object GF_Mollie
	 */
	public static function get_instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new GF_Mollie();
		}

		return self::$_instance;
	}

	/**
	 * Add action to maybe show thank you page.
	 *
	 * @since 1.0
	 */
	public function pre_init() {
		// For form confirmation redirection, this must be called in `wp`,
		// or confirmation redirect to a page would throw PHP fatal error.
		// Run before calling parent method. We don't want to run anything else before displaying thank you page.
		add_action( 'wp', array( $this, 'maybe_thankyou_page' ), 5 );

		parent::pre_init();
	}

	/**
	 * Initialize API on init of Add-On.
	 *
	 * @since 1.0
	 */
	public function init() {

		parent::init();

		add_action( 'gform_field_standard_settings', array( 'GF_Field_Mollie', 'default_payment_method_standard_settings' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );

		add_filter( 'gform_tooltips', array( 'GF_Field_Mollie', 'add_tooltips' ) );
		add_filter( 'gform_field_css_class', array( $this, 'filter_gform_field_css_class' ), 10, 3 );

	}

	/**
	 * Add filters for frontend.
	 *
	 * @since 1.0
	 */
	public function init_frontend() {

		// Add javascript to frontend to construct Mollie Components object.
		add_filter( 'gform_register_init_scripts', array( $this, 'register_init_scripts' ), 10, 3 );

		// Add Mollie Components Card Token to form as hidden field if in GET request.
		add_filter( 'gform_form_tag', array( $this, 'add_mollie_components_card_token' ), 10, 2 );

		parent::init_frontend();

	}

	/**
	 * Register admin initialization hooks.
	 *
	 * @since  1.0
	 */
	public function init_admin() {

		parent::init_admin();

		add_action( 'admin_init', array( $this, 'maybe_update_auth_tokens' ) );
		// Add a Mollie feed if the saved form has the Mollie field.
		add_filter( 'gform_after_save_form', array( $this, 'maybe_add_feed' ), 10, 2 );
	}

	/**
	 * Add Ajax callback.
	 *
	 * @since 1.0
	 */
	public function init_ajax() {

		parent::init_ajax();

		// Ajax callback to disconnect Mollie account.
		add_action( 'wp_ajax_gfmollie_deauthorize', array( $this, 'ajax_deauthorize' ) );

	}

	/**
	 * Initialize Mollie API.
	 *
	 * @since 1.0
	 *
	 * @return boolean true if api initialized.
	 */
	public function initialize_api() {

		// If the API is already initialized, return true.
		if ( ! is_null( $this->api ) ) {
			return is_object( $this->api );
		}

		$auth = $this->get_plugin_setting( 'auth_token' );

		// tokens in settings?
		if ( rgblank( $auth ) ) {
			return false;
		}

		// Load the API library file if necessary.
		if ( ! class_exists( 'GF_Mollie_API' ) ) {
			require_once 'includes/class-gf-mollie-api.php';
		}

		// Log that we're testing the API credentials.
		$this->log_debug( __METHOD__ . '(): Validating API credentials.' );

		$mollie_api = new GF_Mollie_API( $auth );

		$time_created = intval( rgar( $auth, 'time_created' ) );
		$expires_in   = intval( rgar( $auth, 'expires_in' ) );

		// Has the access token expired?
		if ( time() > $time_created + $expires_in ) {

			// Log that access token has expired.
			$this->log_debug( __METHOD__ . '(): API access token has expired, start refreshing.' );

			$auth_token = $mollie_api->refresh_token();

			if ( is_wp_error( $auth_token ) ) {

				// Log error.
				$this->log_error( __METHOD__ . '(): API access token failed to be refreshed; ' . $auth_token->get_error_message() );
				$this->api = false;

				return false;

			}

			// Test access token.
			$profiles = $mollie_api->get_profiles();
			if ( is_wp_error( $profiles ) ) {

				// Log that test failed.
				$this->log_error( __METHOD__ . '(): API credentials are invalid; ' . $profiles->get_error_message() );
				$this->api = false;

				return false;
			}

			// Get current plugin settings.
			$settings = $this->get_plugin_settings();

			// Add tokens.
			$settings['auth_token'] = array(
				'access_token'  => rgar( $auth_token, 'access_token' ),
				'refresh_token' => rgar( $auth_token, 'refresh_token' ),
				'expires_in'    => rgar( $auth_token, 'expires_in' ),
				'time_created'  => time(),
			);
			// Clear cached methods to refresh them when the next request to get the methods is made.
			if ( rgar( $settings, 'methods' ) ) {
				$settings['methods'][ $this->is_test_mode() ? 'test' : 'live' ] = array();
			}
			$this->update_plugin_settings( $settings );

			// Log success.
			$this->log_debug( __METHOD__ . '(): API access token has been refreshed.' );
		}

		$this->api = $mollie_api;

		return true;
	}

	/**
	 * An array of styles to enqueue.
	 *
	 * @since 1.3
	 *
	 * @param $form
	 * @param $ajax
	 * @param $settings
	 * @param $block_settings
	 *
	 * @return array|\string[][]
	 */
	public function theme_layer_styles( $form, $ajax, $settings, $block_settings = array() ) {
		$theme_slug = \GFFormDisplay::get_form_theme_slug( $form );

		if ( $theme_slug !== 'orbital' ) {
			return array();
		}

		$base_url = plugins_url( '', __FILE__ );

		return array(
			'foundation' => array(
				array( 'gravity_forms_mollie_theme_foundation', "$base_url/assets/css/dist/theme-foundation.css" ),
			),
			'framework' => array(
				array( 'gravity_forms_mollie_theme_framework', "$base_url/assets/css/dist/theme-framework.css" ),
			),
		);
	}

	/**
	 * Styles to pass to the Stripe JS widget as part of its CSS properties object.
	 *
	 * @since 1.3
	 *
	 * @param $form_id
	 * @param $settings
	 * @param $block_settings
	 *
	 * @return array
	 */
	public function theme_layer_third_party_styles( $form_id, $settings, $block_settings ) {
		$default_settings = \GFForms::get_service_container()->get( \Gravity_Forms\Gravity_Forms\Form_Display\GF_Form_Display_Service_Provider::BLOCK_STYLES_DEFAULTS );
		$applied_settings = wp_parse_args( $block_settings, $default_settings );

		if ( $applied_settings['theme'] !== 'orbital' && ! $this->is_conversational_form( \GFAPI::get_form( $form_id ) ) ) {
			return array();
		}

		/*
        NOTE:
        The Theme Framework CSS API properties with the "--gform-theme" prefix are deprecated, and
        the CSS API properties with the "--gf" prefix are the updated properties.

        Deprecated version (core): 2.8
        End of support version (core): 2.9
        Deprecated version (mollie): 1.4.1
        */
		if ( version_compare( GFForms::$version, '2.8.0-beta-1', '<' ) ) {
			return array(
				'base'    => array(
					'backgroundColor' => 'transparent',
					'color'           => '--gform-theme-control-color',
					'fontSize'        => '--gform-theme-control-font-size',
					'fontWeight'      => '--gform-theme-control-font-weight',
					'letterSpacing'   => '--gform-theme-control-letter-spacing',
					'lineHeight'      => '--gform-theme-control-line-height',
					'::placeholder'   => array(
						'color'         => '--gform-theme-control-placeholder-color',
						'fontSize'      => '--gform-theme-control-placeholder-font-size',
						'fontWeight'    => '--gform-theme-control-placeholder-font-weight',
						'letterSpacing' => '--gform-theme-control-placeholder-letter-spacing',
					),
				),
				'invalid' => array(
					'color' => '--gform-theme-control-color-error',
				),
			);
		} else {
			return array(
				'base'    => array(
					'backgroundColor' => 'transparent',
					'color'           => '--gf-ctrl-color',
					'fontSize'        => '--gf-ctrl-font-size',
					'fontWeight'      => '--gf-ctrl-font-weight',
					'letterSpacing'   => '--gf-ctrl-letter-spacing',
					'lineHeight'      => '--gf-ctrl-line-height',
					'::placeholder'   => array(
						'color'         => '--gf-ctrl-placeholder-color',
						'fontSize'      => '--gf-ctrl-placeholder-font-size',
						'fontWeight'    => '--gf-ctrl-placeholder-font-weight',
						'letterSpacing' => '--gf-ctrl-placeholder-letter-spacing',
					),
				),
				'invalid' => array(
					'color' => '--gf-ctrl-color-error',
				),
			);
		}
	}


	/**
	 * Enqueues the assets needed for the field to work in the block editor.
	 *
	 * @sicne 1.3
	 *
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		wp_enqueue_style( 'gfmollie-block-editor-framework', $this->get_base_url() . '/assets/css/dist/theme-framework.css', array(), $this->_version );
		wp_enqueue_style( 'gfmollie-block-editor-theme', $this->get_base_url() . '/assets/css/dist/theme.css', array(), $this->_version );
		wp_enqueue_style( 'gfmollie-block-editor-admin', $this->get_base_url() . '/assets/css/dist/admin.css', array(), $this->_version );
	}
	/**
	 * Enqueue stylesheets.
	 *
	 * @since 1.0
	 *
	 * @return array Styles to be enqueued
	 */
	public function styles() {

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$styles = array(
			array(
				'handle'    => 'gforms_mollie_frontend',
				'src'       => $this->get_base_url() . "/assets/css/dist/theme{$min}.css",
				'version'   => $this->_version,
				'in_footer' => false,
				'enqueue' => array(
					array( $this, 'frontend_style_callback' ),
				),
			),
			array(
				'handle'  => 'gform_mollie_pluginsettings',
				'src'     => $this->get_base_url() . "/assets/css/dist/admin{$min}.css",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->get_slug(),
					),
					array( 'query' => 'page=gf_edit_forms' ),
				),
			),
		);

		return array_merge( parent::styles(), $styles );

	}

	/**
	 * Return the scripts which should be enqueued.
	 *
	 * @since 1.0
	 *
	 * @return array Scripts to be enqueued.
	 */
	public function scripts() {

		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$field_name = GF_Fields::get( 'mollie' )->get_form_editor_field_title();

		$scripts = array(
			array(
				'handle'    => 'gform_mollie_vendor',
				'src'       => 'https://js.mollie.com/v1/mollie.js',
				'version'   => $this->_version,
				'deps'      => array(),
				'in_footer' => false,
				'enqueue'   => array(
					array( $this, 'vendor_script_callback' ),
				),
			),
			array(
				'handle'    => 'gform_mollie_components',
				'src'       => $this->get_base_url() . "/js/frontend{$min}.js",
				'version'   => $this->_version,
				'deps'      => array( 'jquery', 'wp-a11y' ),
				'in_footer' => false,
				'enqueue'   => array(
					array( $this, 'frontend_script_callback' ),
				),
				'strings'   => array(
					'locale'      => get_locale(),
					'errorprefix' => wp_strip_all_tags( esc_html__( 'Your credit card payment can not be processed right now', 'gravityformsmollie' ) ),
				),
			),
			array(
				'handle'  => 'gform_mollie_pluginsettings',
				'deps'    => array( 'jquery' ),
				'src'     => $this->get_base_url() . "/js/plugin_settings{$min}.js",
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->get_slug(),
					),
				),
				'strings' => array(
					/* translators: Confirmation question displayed when user clicks button to disconnect Mollie */
					'disconnect' => wp_strip_all_tags( __( 'Are you sure you want to disconnect Mollie?', 'gravityformsmollie' ) ),
					'ajax_nonce' => wp_create_nonce( 'gf_mollie_ajax' ),
					'is_legacy'  => ! $this->is_gravityforms_supported( '2.5-beta' ) ? 'true' : 'false',
				),
			),
			array(
				'handle'  => 'gform_mollie_form_editor',
				'deps'    => array( 'jquery', 'gform_mollie_vendor' ),
				'src'     => $this->get_base_url() . "/js/form_editor{$min}.js",
				'version' => $this->_version,
				'enqueue' => array(
					array( 'admin_page' => array( 'form_editor' ) ),
				),
				'strings' => array(
					'initialize_api'       => $this->is_form_editor() && $this->initialize_api() && $this->get_methods(),
					'payment_method_label' => wp_strip_all_tags( __( 'Payment Method', 'gravityformsmollie' ) ),
					/* translators: The GF field name */
					'only_one_field'       => wp_strip_all_tags( sprintf( __( 'Only one %s field can be added to the form', 'gravityformsmollie' ), $field_name ) ),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Add data for frontpage form script.
	 *
	 * @since 1.0
	 *
	 * @param array $form         Form object.
	 * @param array $field_values Current field values. Not used.
	 * @param bool  $is_ajax      If form is being submitted via AJAX.
	 */
	public function register_init_scripts( $form, $field_values, $is_ajax ) {

		if ( ! $this->frontend_script_callback( $form ) ) {
			return;
		}

		// Get testmode for feed.
		$test_mode = $this->is_test_mode();

		$cc_field = $this->get_mollie_field( $form );

		$args = array(
			'formId'    => $form['id'],
			'pageInstance'   => isset( $form['page_instance'] ) ? $form['page_instance'] : 0,
			'isAjax'    => $is_ajax,
			'profileId' => ( $this->is_creditcard_supported() ) ? $this->get_website_profile_id() : '',
			'testMode'  => $test_mode,
			'ccFieldId' => $cc_field->id,
			'cardStyle' => array(), // this can be modified by the gform_mollie_components_object filter to styling CC inputs.
			'feeds'     => array(),
		);

		$payment_method_field = $this->get_mollie_field( $form );
		if ( false !== $payment_method_field ) {
			$args['pmfieldPage'] = $payment_method_field['pageNumber'];
		}

		// Get feed data.
		$feeds = $this->get_feeds( $form['id'] );
		foreach ( $feeds as $feed ) {
			if ( rgar( $feed, 'is_active' ) === '0' ) {
				continue;
			}

			$feed_settings = array(
				'feedId' => $feed['id'],
			);

			$args['feeds'][] = $feed_settings;
		}

		/**
		 * Modify Mollie Components object when displaying Mollie Field.
		 *
		 * @since 1.0
		 *
		 * @param array $args    Mollie components object.
		 * @param int   $form_id Current form ID.
		 */
		$args   = apply_filters( 'gform_mollie_components_object', $args, $form['id'] );
		$script = 'new GFMollie( ' . wp_json_encode( $args, JSON_FORCE_OBJECT ) . ' );';

		// Add Mollie Components script to form scripts.
		GFFormDisplay::add_init_script( $form['id'], 'mollie', GFFormDisplay::ON_PAGE_RENDER, $script );

	}

	/**
	 * Add the card Token (generated by Mollie Components) to the form. Only applies to multi-page forms.
	 *
	 * @since 1.0
	 *
	 * @param string $form_tag The opening <form> tag.
	 * @param object $form     The current Form.
	 *
	 * @return string $content HTML formatted content for form tag.
	 */
	public function add_mollie_components_card_token( $form_tag, $form ) {

		if ( rgpost( 'cardToken' ) ) {
			$form_tag .= "\n" . '<input type=\'hidden\' name=\'cardToken\' value=\'' . rgpost( 'cardToken' ) . '\' />';
		}
		return $form_tag;

	}





	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 1.1
	 *
	 * @return string
	 */
	public function get_menu_icon() {

		return file_get_contents( $this->get_base_path() . '/images/menu-icon.svg' );

	}
	/**
	 * Render plugin settings page, maybe save access token.
	 *
	 * @since 1.0
	 */
	public function plugin_settings_page() {

		// If error is provided, display message.
		if ( rgget( 'error' ) ) {

			// Add error message.
			GFCommon::add_error_message( esc_html__( 'Unable to authenticate with Mollie.', 'gravityformsmollie' ) );

		}

		parent::plugin_settings_page();

	}

	/**
	 * When users are redirected back to the website after finishing the onboarding, get the auth tokens.
	 *
	 * @since 1.0
	 */
	public function maybe_update_auth_tokens() {
		// If authorization state and code are provided, attempt to create an access token.
		if ( rgget( 'code' ) && rgget( 'state' ) && $this->is_plugin_settings( $this->_slug ) ) {

			// Get current plugin settings.
			$settings      = $this->get_plugin_settings();
			$access_token  = rgars( $settings, 'auth_token/access_token', '' );
			$state_matches = rgget( 'state' ) === rgar( $settings, 'mollie_state' );

			// If page is refreshed with code in URL, do not exchange again for tokens.
			if ( ( '' === $access_token ) && $state_matches ) {

				if ( ! $this->exchange_code_for_access_token( rgget( 'code' ) ) ) {

					// Add error message.
					GFCommon::add_error_message( esc_html__( 'Authentication with Mollie was not successful.', 'gravityformsmollie' ) );

					return;

				}

				wp_redirect( add_query_arg( array(
					'page'    => 'gf_settings',
					'subview' => $this->get_slug()
				), admin_url( 'admin.php' ) ) );
				exit();
			}
		}
	}

	/**
	 * Add some data that aren't registered as setting fields when updating plugin settings.
	 *
	 * @since 1.0
	 *
	 * @param array $settings Plugin settings to be saved.
	 */
	public function update_plugin_settings( $settings ) {
		if ( $this->is_save_postback() ) {
			$_settings = $this->get_plugin_settings();

			foreach ( $_settings as $key => $value ) {
				if ( rgempty( $key, $settings ) && ! empty( $value ) ) {
					$settings[ $key ] = $value;
				}
			}
		}

		parent::update_plugin_settings( $settings );
	}

	/**
	 * Setup plugin settings fields.
	 *
	 * @since 1.0
	 *
	 * @return array Fields for plugin settings screen.
	 */
	public function plugin_settings_fields() {

		return array(
			array(
				'title'       => esc_html__( 'Mollie Account', 'gravityformsmollie' ),
				'description' => sprintf(
					'<p>%s</p>',
					sprintf(
						/* translators: General description of Mollie */
						esc_html__( 'Mollie helps businesses of all sizes to sell and build more efficiently with a solid but easy-to-use payment solution. Start growing your business today with effortless payments. If you don\'t have a Mollie account, you can %1$ssign up for one here.%2$s', 'gravityformsmollie' ),
						'<a href="https://www.mollie.com/" target="_blank">',
						'</a>'
					)
				),
				'fields'      => $this->api_settings_fields(),
			),
		);

	}

	/**
	 * Define the settings which appear in the Mollie API section.
	 *
	 * @since 1.0
	 *
	 * @return array The API settings fields.
	 */
	public function api_settings_fields() {
		$connected_to_api = $this->initialize_api();

		$mode_field = array(
			'name'          => 'mode',
			'type'          => 'radio',
			'required'      => true,
			'label'         => esc_html__( 'Mode', 'gravityformsmollie' ),
			'choices'       => $this->get_mode_field_choices(),
			'default_value' => 'live',
			'horizontal'    => true,
			'tooltip'       => sprintf(
				'<h6>%s</h6>%s',
				esc_html__( 'Test mode', 'gravityformsmollie' ),
				esc_html__( 'Use this setting to test your connection to Mollie. Pending payment methods in your profile are available in test mode.', 'gravityformsmollie' )
			),
		);

		$oauth_button_field = array(
			'name'  => 'auth_button',
			'label' => '',
			'type'  => 'oauth_connect_button',
		);

		if ( ! $connected_to_api ) {
			return array( $mode_field, $oauth_button_field );
		}

		$profile_id_field = array(
			'name'                => 'profile_id',
			'type'                => 'select',
			'required'            => true,
			'label'               => esc_html__( 'Website Profile', 'gravityformsmollie' ),
			'choices'             => $this->get_profile_id_field_choices(),
			'validation_callback' => array( $this, 'validate_profile_has_methods' ),
			'tooltip'             => sprintf(
				'<h6>%s</h6>%s',
				esc_html__( 'Website Profile', 'gravityformsmollie' ),
				esc_html__( 'Select your Website profile.', 'gravityformsmollie' )
			),
		);

		return array( $mode_field, $oauth_button_field, $profile_id_field );
	}

	/**
	 * Get choices for Website profile setting.
	 *
	 * @since 1.0
	 *
	 * @return array Choices for Website profile setting.
	 */
	public function get_profile_id_field_choices() {

		$profile_data = array(
			array(
				'label' => esc_html__( 'Select a Profile', 'gravityformsmollie' ),
				'value' => '',
			),
		);

		// If API not available, return default array.
		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): Unable to get profiles because API is not initialized.' );

			return $profile_data;
		}

		$profiles = $this->api->get_profiles();

		if ( is_wp_error( $profiles ) ) {
			$this->log_error( __METHOD__ . '(): Unable to get profiles; ' . $profiles->get_error_message() );
			return $profile_data;
		}

		foreach ( $profiles as $profile ) {

			array_push(
				$profile_data,
				array(
					'value' => esc_attr( rgar( $profile, 'id' ) ),
					'label' => sprintf( '%s (%s)', esc_html( rgar( $profile, 'name' ) ), esc_html( rgar( $profile, 'website' ) ) ),
				)
			);

		}

		return $profile_data;
	}

	/**
	 * Get choices for Mollie mode setting.
	 *
	 * @since 1.0
	 *
	 * @return array Choices for Mollie mode setting.
	 */
	public function get_mode_field_choices() {
		return array(
			array(
				'label' => esc_html__( 'Live', 'gravityformsmollie' ),
				'value' => 'live',
			),
			array(
				'label' => esc_html__( 'Test', 'gravityformsmollie' ),
				'value' => 'test',
			),
		);
	}

	/**
	 * Generate HTML for the button to start the OAuth process, or to disconnect a Mollie account.
	 *
	 * @since 1.0
	 *
	 * @param array $field Field settings.
	 * @param bool  $echo  Display field. Defaults to true.
	 *
	 * @return string HTML for the button to start the OAuth process, or to disconnect a Mollie account.
	 */
	public function settings_oauth_connect_button( $field, $echo = true ) {

		// Check if Mollie API is available.
		if ( ! $this->initialize_api() ) {
			if ( ! is_ssl() ) {
				$settings_url = admin_url( 'admin.php?page=gf_settings&subview=' . $this->get_slug(), 'https' );
				$alert_class = $this->is_gravityforms_supported( '2.5-beta' ) ? 'alert gforms_note_error' : 'alert_red';
				ob_start();
				?>
				<div class="<?php echo esc_attr( $alert_class ); ?>">
					<h4><?php esc_html_e( 'SSL Certificate Required', 'gravityformsmollie' ) ?></h4>
					<?php
					printf( esc_html__( 'Make sure you have an SSL certificate installed and enabled, then %1$sclick here to continue%2$s.', 'gravityformsmollie' ), '<a href="' . $settings_url . '">', '</a>' );
					?>
				</div>
				<?php
                $html = ob_get_clean();
			} else {
				// Mollie API not initialized, display OAuth connect button.
				$settings_url = rawurlencode(
					add_query_arg(
						array(
							'page'    => 'gf_settings',
							'subview' => $this->get_slug(),
						),
						admin_url( 'admin.php' )
					)
				);

				// Generate a random string and store it in the Add-On settings, it will be returned with the redirect.
				$settings = $this->get_plugin_settings();

				if ( rgempty( 'mollie_state', $settings ) ) {
					$settings = array();
					$settings['mollie_state'] = wp_generate_password( 24, false );

					// Save the settings with the new value for mollie_state.
					$this->update_plugin_settings( $settings );

				}

				$mollie_state = rgar( $settings, 'mollie_state' );

				// Load the API library file if necessary.
				if ( ! class_exists( 'GF_Mollie_API' ) ) {
					require_once 'includes/class-gf-mollie-api.php';
				}

				$oauth_url = add_query_arg(
					array(
						'state'       => $mollie_state,
						'redirect_to' => $settings_url,
						'license'     => GFCommon::get_key(),
					),
					GF_Mollie_API::get_gravity_api_url()
				);

				$connect_button = '<svg width="200" height="45" viewBox="0 0 200 45" fill="none" xmlns="http://www.w3.org/2000/svg">
<title>' . esc_html__( 'Click here to connect to Mollie', 'gravityformsmollie' ) . '</title>
<path d="M196 0H4C1.79086 0 0 1.79086 0 4V41C0 43.2091 1.79086 45 4 45H196C198.209 45 200 43.2091 200 41V4C200 1.79086 198.209 0 196 0Z" class="bg"/>
<path d="M54 23.2364C54 19.8182 56.1979 17.4727 59.1525 17.4727C61.7647 17.4727 63.3681 19.0182 63.7824 21.2545H61.4945C61.1161 20.2364 60.4316 19.5455 59.1525 19.5455C57.3869 19.5455 56.342 21.1091 56.342 23.2364C56.342 25.3455 57.3869 26.9273 59.1525 26.9273C60.4316 26.9273 61.1161 26.2364 61.4945 25.2H63.7824C63.3681 27.4545 61.7647 29 59.1525 29C56.1979 29 54 26.6545 54 23.2364ZM72.6281 24.7636C72.6281 27.2545 71.0247 29 68.6827 29C66.3407 29 64.7373 27.2545 64.7373 24.7636C64.7373 22.2545 66.3407 20.5091 68.6827 20.5091C71.0247 20.5091 72.6281 22.2545 72.6281 24.7636ZM70.4122 24.7636C70.4122 23.4 69.7996 22.4182 68.6827 22.4182C67.5657 22.4182 66.9532 23.4 66.9532 24.7636C66.9532 26.1091 67.5657 27.0909 68.6827 27.0909C69.7996 27.0909 70.4122 26.1091 70.4122 24.7636ZM74.0513 28.8182V20.6909H76.2852V21.4364C76.7176 20.9273 77.5103 20.5091 78.4471 20.5091C80.2667 20.5091 81.2936 21.7091 81.2936 23.5091V28.8182H79.0596V23.9818C79.0596 23.1273 78.6273 22.5455 77.7445 22.5455C77.0599 22.5455 76.4654 22.9636 76.2852 23.6727V28.8182H74.0513ZM83.1492 28.8182V20.6909H85.3831V21.4364C85.8155 20.9273 86.6082 20.5091 87.545 20.5091C89.3645 20.5091 90.3914 21.7091 90.3914 23.5091V28.8182H88.1575V23.9818C88.1575 23.1273 87.7251 22.5455 86.8424 22.5455C86.1578 22.5455 85.5633 22.9636 85.3831 23.6727V28.8182H83.1492ZM97.1473 26.1636H99.2191C98.9308 27.8909 97.6697 29 95.652 29C93.3099 29 91.7426 27.2727 91.7426 24.7636C91.7426 22.3091 93.364 20.5091 95.6159 20.5091C97.976 20.5091 99.2731 22.1636 99.2731 24.5455V25.2364H93.9045C93.9765 26.4545 94.6611 27.1818 95.652 27.1818C96.4086 27.1818 96.9671 26.8545 97.1473 26.1636ZM95.634 22.3455C94.7692 22.3455 94.1567 22.8727 93.9585 23.8727H97.0752C97.0572 23.0182 96.5528 22.3455 95.634 22.3455ZM100.192 24.7636C100.192 22.2364 101.777 20.5091 104.065 20.5091C106.083 20.5091 107.362 21.7091 107.596 23.4364H105.398C105.272 22.8182 104.786 22.4364 104.065 22.4364C103.038 22.4364 102.408 23.3818 102.408 24.7636C102.408 26.1273 103.038 27.0727 104.065 27.0727C104.786 27.0727 105.272 26.6909 105.398 26.0727H107.596C107.362 27.8182 106.083 29 104.065 29C101.777 29 100.192 27.2727 100.192 24.7636ZM109.452 26.3636V22.5091H108.227V20.6909H109.452V18.4909H111.668V20.6909H113.433V22.5091H111.668V26.0727C111.668 26.7091 112.01 27 112.587 27C112.929 27 113.307 26.8909 113.559 26.7273V28.7273C113.253 28.8909 112.713 28.9818 112.118 28.9818C110.479 28.9818 109.452 28.1636 109.452 26.3636ZM123.468 28.8182H121.306L118.261 20.6909H120.604L122.459 26.0909L124.279 20.6909H126.531L123.468 28.8182ZM129.972 20.6909V28.8182H127.738V20.6909H129.972ZM130.17 18.3455C130.17 19.1091 129.557 19.6364 128.855 19.6364C128.134 19.6364 127.521 19.1091 127.521 18.3455C127.521 17.6 128.134 17.0545 128.855 17.0545C129.557 17.0545 130.17 17.6 130.17 18.3455ZM138.529 28.8182H136.385V28.3818C136.115 28.6545 135.304 28.9636 134.421 28.9636C132.8 28.9636 131.431 28.0182 131.431 26.3273C131.431 24.7818 132.8 23.7273 134.548 23.7273C135.25 23.7273 136.061 23.9636 136.385 24.2V23.6C136.385 22.9091 135.971 22.3455 135.07 22.3455C134.439 22.3455 134.061 22.6364 133.881 23.1091H131.755C131.989 21.6364 133.304 20.5091 135.142 20.5091C137.286 20.5091 138.529 21.6909 138.529 23.6364V28.8182ZM136.385 26.6V26.0182C136.187 25.5818 135.574 25.3091 134.926 25.3091C134.223 25.3091 133.503 25.6182 133.503 26.3091C133.503 27.0182 134.223 27.3091 134.926 27.3091C135.574 27.3091 136.187 27.0364 136.385 26.6ZM150.726 28.8182H149.158L146.42 21.5273V28.8182H144.258V17.6545H146.996L149.951 25.5273L152.887 17.6545H155.59V28.8182H153.41V21.5273L150.726 28.8182ZM165.012 24.7636C165.012 27.2545 163.408 29 161.066 29C158.724 29 157.121 27.2545 157.121 24.7636C157.121 22.2545 158.724 20.5091 161.066 20.5091C163.408 20.5091 165.012 22.2545 165.012 24.7636ZM162.796 24.7636C162.796 23.4 162.183 22.4182 161.066 22.4182C159.95 22.4182 159.337 23.4 159.337 24.7636C159.337 26.1091 159.95 27.0909 161.066 27.0909C162.183 27.0909 162.796 26.1091 162.796 24.7636ZM168.669 17V28.8182H166.435V17H168.669ZM172.849 17V28.8182H170.615V17H172.849ZM177.028 20.6909V28.8182H174.794V20.6909H177.028ZM177.226 18.3455C177.226 19.1091 176.614 19.6364 175.911 19.6364C175.191 19.6364 174.578 19.1091 174.578 18.3455C174.578 17.6 175.191 17.0545 175.911 17.0545C176.614 17.0545 177.226 17.6 177.226 18.3455ZM183.874 26.1636H185.946C185.658 27.8909 184.397 29 182.379 29C180.037 29 178.469 27.2727 178.469 24.7636C178.469 22.3091 180.091 20.5091 182.343 20.5091C184.703 20.5091 186 22.1636 186 24.5455V25.2364H180.631C180.703 26.4545 181.388 27.1818 182.379 27.1818C183.136 27.1818 183.694 26.8545 183.874 26.1636ZM182.361 22.3455C181.496 22.3455 180.884 22.8727 180.685 23.8727H183.802C183.784 23.0182 183.28 22.3455 182.361 22.3455Z" fill="white"/>
<path d="M32.4673 14C30.3794 14 28.3995 14.8559 26.9775 16.3694C25.5556 14.8649 23.5846 14 21.5147 14C17.3749 14 14 17.3694 14 21.5135V31H18V21.5135C18 19.7928 19.4628 18.3243 21.1277 18.1531C21.2447 18.1441 21.3617 18.1351 21.4697 18.1351C23.3416 18.1351 24.991 19.6396 25 21.5135V31H29.0204V21.5135C29.0204 19.8019 30.4244 18.3153 32.0983 18.1441C32.2153 18.1351 32.3323 18.1261 32.4403 18.1261C34.3122 18.1261 35.991 19.6486 36 21.5135V31H40V21.5135C40 19.6127 39.298 17.9009 38.0291 16.4865C36.7601 15.0631 35.0232 14.1892 33.1333 14.027C32.9083 14.009 32.6922 14 32.4673 14Z" fill="white"/>
</svg>';

				$html = sprintf(
					'<a href="%1$s" id="gform_mollie_connect_button">%2$s</a>',
					$oauth_url,
					/* translators: SVG button connect Mollie account */
					$connect_button
				);
			}
		} else {
			$html = sprintf(
				'<a href="#" class="button" id="gform_mollie_disconnect_button">%1$s</a>',
				/* translators: text on button to disconnect Mollie account */
				esc_html__( 'Disconnect', 'gravityformsmollie' )
			);

			// API available? Then add status of connected account.
			if ( $this->initialize_api() ) {

				$onboarding = $this->api->get_onboarding_status();

				if ( is_wp_error( $onboarding ) ) {
					$this->log_error( __METHOD__ . '(): Unable to get onboarding status; ' . $onboarding->get_error_message() );
				} else {

					$html = sprintf(
						/* translators: 1: Open strong tag 2: Account name 3: Account status 4: Close strong tag  */
						esc_html__( 'Connected to Mollie as %1$s%2$s%4$s, your account status is %1$s%3$s%4$s.' ),
						'<strong>',
						rgar( $onboarding, 'name' ),
						rgar( $onboarding, 'status' ),
						'</strong>'
					) . $html;

				}
			}
		}

		$html = sprintf( '<p class="connected_to_mollie_text">%s</p>', $html );

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	/**
	 * Validate profile setting (show error when no payment methods have been set for a Website profile).
	 *
	 * @since 1.0
	 *
	 * @param array  $field         The field object.
	 * @param string $field_setting The field value.
	 */
	public function validate_profile_has_methods( $field, $field_setting ) {

		// If in test mode, return.
		if ( $this->is_test_mode() ) {
			return;
		}

		// Check API is available.
		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): Unable to check profile because API is not initialized.' );

			$this->set_field_error(
				$field,
				esc_html__( 'Unable to check your profile setting, Mollie is unreachable.', 'gravityformsmollie' )
			);
		}

		// Fetch enabled payment methods for the selected profile.
		// Do not pass testmode, as this will return all methods.
		$methods = $this->api->get_methods( $field_setting );

		if ( is_wp_error( $methods ) || empty( $methods ) ) {

			if ( is_wp_error( $methods ) ) {
				/* translators: %s is the error code in the wp_error object */
				$message = sprintf( esc_html__( 'Unable to get payment methods. Error Code: %s', 'gravityformsmollie' ), $methods->get_error_code() );
				$this->log_error( __METHOD__ . '(): Unable to get active payment methods; ' . $methods->get_error_message() );
			} else {
				$message = esc_html__( 'This profile has no active payment methods. Please add at least one method in your Mollie account.', 'gravityformsmollie' );
				$this->log_error( __METHOD__ . '(): Website profile has no active payment methods.' );
			}

			$this->set_field_error(
				$field,
				$message
			);
		}
	}





	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @since 1.0
	 *
	 * @return array The feed settings fields.
	 */
	public function feed_settings_fields() {

		// Get default payment feed settings fields.
		$default_settings = parent::feed_settings_fields();

		// Remove Subscription.
		$transaction_type = $this->get_field( 'transactionType', $default_settings );
		$choices          = $transaction_type['choices'];
		foreach ( $choices as $key => $choice ) {
			if ( $choice['value'] === 'subscription' ) {
				unset( $choices[ $key ] );
			}
		}
		$transaction_type['choices'] = $choices;

		$default_settings = $this->replace_field( 'transactionType', $transaction_type, $default_settings );

		// Adding the name mapping.
		$billing_info = parent::get_field( 'billingInformation', $default_settings );
		array_unshift(
			$billing_info['field_map'],
			array(
				'name'     => 'first_name',
				'label'    => esc_html__( 'First Name', 'gravityformsmollie' ),
				'required' => false,

			),
			array(
				'name'     => 'last_name',
				'label'    => esc_html__( 'Last Name', 'gravityformsmollie' ),
				'required' => false,

			)
		);
		$billing_info['tooltip'] = sprintf(
			/* translators: 1: Open h6 tag 2: Close h6 tag 3: Open p tag 4: Close p tag 5: Open link tag 6: Close link tag */
			esc_html__( '%1$sBilling Information%2$s%3$sMap your Form Fields to the available listed fields.%4$s%3$sMapping the billing information here could send the customer data to your Mollie account, and make Credit Card payments PSD2 and SCA compliant. %5$sLearn more here%6$s.%4$s', 'gravityformsmollie' ),
			'<h6>',
			'</h6>',
			'<p>',
			'</p>',
			'<a href="https://docs.gravityforms.com/creating-feed-for-mollie-add-on/" target="_blank">',
			'</a>'
		);
		$default_settings        = $this->replace_field( 'billingInformation', $billing_info, $default_settings );

		return $default_settings;
	}

	/**
	 * Override to prevent the feed creation UI from being rendered when Add-On setup is not complete.
	 *
	 * @since 1.0
	 *
	 * @return boolean Return true when Mollie connection is active, and Add-On settings have been completed.
	 */
	public function can_create_feed() {

		$connected_to_api = $this->initialize_api();

		$settings = $this->get_plugin_settings();
		if ( false === $settings ) {
			return false;
		}

		$mode_is_set         = false !== rgar( $settings, 'mode', false );
		$profile_is_selected = false !== rgar( $settings, 'profile_id', false );

		return $connected_to_api && $mode_is_set && $profile_is_selected && $this->has_mollie_field();
	}

	/**
	 * Get the require Mollie field message.
	 *
	 * @since 1.0.
	 *
	 * @return false|string
	 */
	public function feed_list_message() {
		if ( ! $this->has_mollie_field() ) {
			return $this->requires_mollie_field_message();
		}

		return GFFeedAddOn::feed_list_message();
	}

	/**
	 * Display the requiring Mollie field message.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function requires_mollie_field_message() {
		$url = add_query_arg( array( 'view' => null, 'subview' => null ) );

		return sprintf( esc_html__( "You must add a Mollie field to your form before creating a feed. Let's go %sadd one%s!", 'gravityformsmollie' ), "<a href='" . esc_url( $url ) . "'>", '</a>' );
	}

	/**
	 * Unset the default example option.
	 *
	 * @since 1.0
	 *
	 * @return array
	 */
	public function option_choices() {

		return array();
	}





	// # FORM SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Add supported notification events.
	 *
	 * @since 1.0
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return array|false The supported notification events. False if feed cannot be found within $form.
	 */
	public function supported_notification_events( $form ) {

		// If this form does not have a Mollie feed, return false.
		if ( ! $this->has_feed( $form['id'] ) ) {
			return false;
		}

		// Return Mollie notification events.
		return array(
			'complete_payment' => esc_html__( 'Payment Completed', 'gravityformsmollie' ),
			'refund_payment'   => esc_html__( 'Payment Refunded', 'gravityformsmollie' ),
			'fail_payment'     => esc_html__( 'Payment Failed', 'gravityformsmollie' ),
		);

	}





	// # SUBMISSION PROCESS ------------------------------------------------------------------------------------------------

	/**
	 * Add integration code to the payment processor in order to authorize a credit card with or
	 * without capturing payment.
	 *
	 * This method is executed during the form validation process and allows the form submission process to fail with a
	 * validation error if there is anything wrong with the payment/authorization. This method is only supported by
	 * single payments. For subscriptions or recurring payments, use the GFPaymentAddOn::subscribe() method.
	 *
	 * @since 1.0
	 *
	 * @param array $feed            Current configured payment feed.
	 * @param array $submission_data Contains form field data submitted by the user as well as payment information
	 *                               (i.e. payment amount, setup fee, line items, etc...).
	 * @param array $form            The Form Object.
	 * @param array $entry           The Entry Object. NOTE: the entry hasn't been saved to the database at this point,
	 *                               so this $entry object does not have the 'ID' property and is only a memory
	 *                               representation of the entry.
	 *
	 * @return array {
	 *     Return an $authorization array.
	 *
	 *     @type bool   $is_authorized  True if the payment is authorized. Otherwise, false.
	 *     @type string $error_message  The error message, if present.
	 *     @type string $transaction_id The transaction ID.
	 *     @type array  $captured_payment {
	 *         If payment is captured, an additional array is created.
	 *
	 *         @type bool   $is_success     If the payment capture is successful.
	 *         @type string $error_message  The error message, if any.
	 *         @type string $transaction_id The transaction ID of the captured payment.
	 *         @type int    $amount         The amount of the captured payment, if successful.
	 *     }
	 * }
	 */
	public function authorize( $feed, $submission_data, $form, $entry ) {

		// Check API is available.
		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): Unable to create payment because API is not initialized.' );

			return $this->authorization_error( esc_html__( 'Unable to create payment because API is not initialized.', 'gravityformsmollie' ) );
		}

		// Get Website Profile.
		$profile_id = $this->get_website_profile_id();

		// Convert payment amount to format XXXX.XX .
		$payment_amount = $this->get_amount_formatted( rgar( $submission_data, 'payment_amount' ), rgar( $entry, 'currency' ) );

		// Get currency.
		$currency = rgar( $entry, 'currency' );

		// use entry id as order id.
		$payment_details = array(
			'amount'      => array(
				'currency' => $currency,
				'value'    => $payment_amount,
			),
			'locale'      => get_locale(),
			'description' => $this->get_payment_description( $entry, $submission_data, $feed ),
			// Entry id (first argument of function) is not know yet. This value is updated in $this->capture() .
			'redirectUrl' => $this->get_return_url( 0, $form['id'], $feed['id'] ),
			'profileId'   => $profile_id,
			'testmode'    => $this->is_test_mode(),
		);

		$email = rgar( $submission_data, 'email', false );

		// Payment method set?
		$postparameter = false;
		$mollie_field  = $this->get_mollie_field( $form );
		if ( $mollie_field ) {
			$postparameter = 'input_' . $mollie_field->id . '_6';
		}

		if ( '' !== rgpost( $postparameter ) ) {
			$payment_details['method'] = rgpost( $postparameter );
		}

		// Documentation for specific payment methods: https://docs.mollie.com/reference/v2/payments-api/create-payment#payment-method-specific-parameters .
		switch ( $payment_details['method'] ) {
			case 'banktransfer':
			case 'przelewy24':
				if ( false !== $email ) {
					$payment_details['billingEmail'] = $email;
				}
				break;
			case 'creditcard':
				// Mollie card token set? If so, it was added by Mollie Components API to the form.
				// It's not in $submission_data as GF doesn't know about the hidden field.
				$card_token = rgpost( 'cardToken' );
				if ( $card_token ) {
					$payment_details['cardToken'] = $card_token;
				}
				break;
		}

		// Add billing address info.
		$_payment_details = $this->maybe_add_address_info( $entry, $feed, $payment_details, 'billingAddress' );
		if ( $_payment_details === $payment_details ) {
			// If no address info mapped, use the Payments API.
			$payment = $this->api->create_payment( $payment_details );
		} else {
			$payment_details = $_payment_details;

			$payment_details['orderNumber'] = time() . uniqid();

			$payment_details['lines'] = array();

			foreach ( $submission_data['line_items'] as $item ) {
				$price = $this->get_amount_formatted( $item['unit_price'], $currency );

				$line = array(
					'name'        => $item['name'] . ' ' . $item['description'],
					'quantity'    => intval( $item['quantity'] ),
					'unitPrice'   => array(
						'currency' => $currency,
						'value'    => $price,
					),
					'totalAmount' => array(
						'currency' => $currency,
						'value'    => $this->get_amount_formatted( $item['unit_price'] * $item['quantity'], $currency ),
					),
					'vatRate'     => '0.00',
					'vatAmount'   => array(
						'currency' => $currency,
						'value'    => '0.00',
					),
				);

				if ( rgar( $item, 'is_shipping' ) && $item['is_shipping'] === 1 ) {
					$line['type'] = 'shipping_fee';
				}

				$payment_details['lines'][] = $line;
			}

			$discounts = rgar( $submission_data, 'discounts' );

			if ( is_array( $discounts ) ) {
				foreach ( $discounts as $discount ) {
					// get_amount_formatted() can only handle positive number, so we prepend the negative symbol later.
					$unit_price = abs( $discount['unit_price'] );
					$price      = $this->get_amount_formatted( $unit_price, $currency );

					$payment_details['lines'][] = array(
						'name'        => $discount['name'] . ' ' . $discount['description'],
						'type'        => 'discount',
						'quantity'    => intval( $discount['quantity'] ),
						'unitPrice'   => array(
							'currency' => $currency,
							'value'    => '-' . $price,
						),
						'totalAmount' => array(
							'currency' => $currency,
							'value'    => '-' . $this->get_amount_formatted( $unit_price * $discount['quantity'], $currency ),
						),
						'vatRate'     => '0.00',
						'vatAmount'   => array(
							'currency' => $currency,
							'value'    => '0.00',
						),
					);
				}
			}

			// Unset some keys that do not exist in the Orders API.
			unset( $payment_details['description'] );
			unset( $payment_details['billingEmail'] );
			if ( isset( $payment_details['cardToken'] ) ) {
				$payment_details['payment']['cardToken'] = $payment_details['cardToken'];
				unset( $payment_details['cardToken'] );
			}

			$payment = $this->api->create_payment( $payment_details, 'orders' );
		}

		if ( is_wp_error( $payment ) ) {

			$this->log_error( __METHOD__ . '(): Unable to create payment; ' . $payment->get_error_message() . '; payment details sent: ' . print_r( $payment_details, 1 ) );

			// Mollie API returned error.
			return $this->authorization_error( $payment->get_error_message() );

		}

		if ( rgar( $payment, 'resource' ) === 'order' ) {
			// Store both payment and order IDs and separate them later in capture().
			$transaction_id = rgars( $payment, '_embedded/payments/0/id' ) . '||' . rgar( $payment, 'id' );
		} else {
			$transaction_id = rgar( $payment, 'id' );
		}

		if ( ! $this->is_payment_status( $payment, 'paid' ) ) {
			return array(
				'is_authorized'   => true,
				'transaction_id'  => $transaction_id,
				'payment_pending' => true,
			);
		}

		return array(
			'is_authorized'  => true,
			'transaction_id' => $transaction_id,
		);

	}

	/**
	 * Override function to first check if payment is pending. If it is not, mark entry as authorized and create note.
	 *
	 * @since 1.0
	 *
	 * @param array $entry  Entry data.
	 * @param array $action Authorization data.
	 *
	 * @return bool always true.
	 */
	public function complete_authorization( &$entry, $action ) {
		if ( true === rgar( $action, 'payment_pending', false ) ) {
			// Do not complete authorization at this stage since users haven't paid.
			$this->log_debug( __METHOD__ . '(): Mollie payment has been created, but the payment hasn\'t been authorized yet. Mark it as processing.' );
			GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Processing' );
			// Save transaction_id so later on we can use it in the maybe_thankyou_page() or webhook.
			GFAPI::update_entry_property( $entry['id'], 'transaction_id', $action['transaction_id'] );

			return true;
		}

		return parent::complete_authorization( $entry, $action );
	}

	/**
	 * Capture a single payment that has been authorized via the authorize() method.
	 *
	 * @since 1.0
	 *
	 * @param array $authorization   Contains the result of the authorize() function.
	 * @param array $feed            Current configured payment feed.
	 * @param array $submission_data Contains form field data submitted by the user as well as payment information.
	 *                               (i.e. payment amount, setup fee, line items, etc...).
	 * @param array $form            Current form array containing all form settings.
	 * @param array $entry           Current entry array containing entry information (i.e data submitted by users).
	 *
	 * @return array {
	 *     Return an array with the information about the captured payment in the following format:
	 *
	 *     @type bool   $is_success     If the payment capture is successful.
	 *     @type string $error_message  The error message, if any.
	 *     @type string $transaction_id The transaction ID of the captured payment.
	 *     @type int    $amount         The amount of the captured payment, if successful.
	 *     @type string $payment_method The card issuer.
	 * }
	 */
	public function capture( $authorization, $feed, $submission_data, $form, $entry ) {
		// If API instance is not initialized, return error.
		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): Unable to check if payment is paid because API is not initialized.' );

			// Return error.
			/* translators: Error message shown when Add-On cannot connect to Mollie. */
			return $this->authorization_error( esc_html__( 'Connection to Mollie cannot be initialized.', 'gravityformsmollie' ) );
		}

		// Separate the payment and order ID.
		$transaction_id = rgar( $authorization, 'transaction_id' );
		list( $transaction_id, $order_id ) = array_pad( explode( '||', $transaction_id ), 2, '' );
		// We need to modify $this->authorization so the transaction_id can be exposed to `complete_authorization()`.
		$authorization['transaction_id'] = $transaction_id;
		$this->authorization             = $authorization;

		$payment = $this->api->get_mollie_payment( $transaction_id, $this->is_test_mode() );

		if ( is_wp_error( $payment ) ) {

			// Log that payment could not be fetched.
			$this->log_error( __METHOD__ . '(): Unable to find payment; ' . $payment->get_error_message() );

			// Return error.
			/* translators: Error message shown when information about the undergoing payment cannot be read from Mollie. */
			return $this->authorization_error( esc_html__( 'The status of your payment cannot be read.', 'gravityformsmollie' ) );
		}

		$this->log_debug( __METHOD__ . '(): Update payment details to include entry id and update return URL and webhook URL' );

		// Now that entry has been saved and the entry ID is know, set details for the payment.
		$is_paid     = $this->is_payment_status( $payment, 'paid' );
		$return_url  = $is_paid ? '' : $this->get_return_url( $entry['id'], $form['id'], $feed['id'] );
		$webhook_url = $is_paid ? '' : $this->get_webhook_url( $entry['id'] );

		// Update the payment.
		$payment_updated = $this->api->update_mollie_payment(
			rgar( $payment, 'id' ),
			$description = $this->get_payment_description( $entry, $submission_data, $feed ),
			$return_url,
			$webhook_url,
			array( 'entry_id' => $entry['id'] ),
			$this->is_test_mode()
		);

		if ( is_wp_error( $payment_updated ) && ! $is_paid ) {
			// Log that payment could not be updated.
			$this->log_error( __METHOD__ . '(): Unable to update payment; ' . $payment_updated->get_error_message() );

			// Return error.
			/* translators: Error message, information about the payment cannot be updated in Mollie. */
			return $this->authorization_error( esc_html__( 'The status of your payment cannot be updated.', 'gravityformsmollie' ) );
		}

		// Update the order (if has one).
		if ( strstr( $order_id, 'ord_' ) ) {
			$payment_updated = $this->api->update_mollie_payment(
				$order_id,
				'',
				$return_url,
				$webhook_url,
				array( 'entry_id' => $entry['id'] ),
				$this->is_test_mode()
			);

			if ( is_wp_error( $payment_updated ) && ! $is_paid ) {
				// Log that order could not be updated.
				$this->log_error( __METHOD__ . '(): Unable to update order; ' . $payment_updated->get_error_message() );

				/* translators: Error message, information about the order cannot be updated in Mollie. */
				return $this->authorization_error( esc_html__( 'The status of your order cannot be updated.', 'gravityformsmollie' ) );
			}
		}

		// Check if payment is paid (credit card token accepted and no redirect necessary).
		if ( $is_paid ) {

			$payment_method = $this->get_method_label( rgar( $payment, 'method' ) );

			$result = array(
				'is_success'     => true,
				'transaction_id' => rgar( $payment, 'id' ),
				'amount'         => floatval( rgars( $payment, 'amount/value' ) ),
				'payment_method' => $payment_method,
			);

			if ( $payment_method === 'Credit card' ) {
				$result['card_number'] = 'XXXXXXXXXXXX' . rgars( $payment, 'details/cardNumber' );
				$result['card_label']  = rgars( $payment, 'details/cardLabel' );
			}

			return $result;
		}

		// Set redirect url. As we already have the payment object here, we do not override redirect_url() .
		$this->redirect_url = rgars( $payment_updated, '_links/checkout/href', '' );

		// Return empty array as payment has not been fulfilled.
		return array();

	}

	/**
	 * Update the entry value for .1 (card number) and .4 (card type/label) for the Mollie field.
	 *
	 * @since  1.0
	 *
	 * @param array $authorization   The payment authorization details.
	 * @param array $feed            The Feed Object.
	 * @param array $submission_data The form submission data.
	 * @param array $form            The Form Object.
	 * @param array $entry           The Entry Object.
	 *
	 * @return array The Entry Object.
	 */
	public function process_capture( $authorization, $feed, $submission_data, $form, $entry ) {
		if ( $card_number = rgar( $authorization['captured_payment'], 'card_number' ) ) {
			$mollie_field                      = $this->get_mollie_field( $form );
			$entry[ $mollie_field->id . '.1' ] = $card_number;
			$entry[ $mollie_field->id . '.4' ] = rgar( $authorization['captured_payment'], 'card_label' );
		}

		return parent::process_capture( $authorization, $feed, $submission_data, $form, $entry );
	}

	/**
	 * Get return URL, used by Mollie to return customer to merchant site
	 *
	 * @since 1.0
	 *
	 * @param int $entry_id Entry ID.
	 * @param int $form_id Form ID.
	 * @param int $feed_id Feed ID.
	 *
	 * @return string Return URL, used by Mollie to return customer to merchant site.
	 */
	public function get_return_url( $entry_id, $form_id, $feed_id ) {

		$page_url    = GFCommon::is_ssl() ? 'https://' : 'http://';
		$server_name = rgar( $_SERVER, 'SERVER_NAME' );
		$request_uri = rgar( $_SERVER, 'REQUEST_URI' );
		$server_port = intval( rgar( $_SERVER, 'SERVER_PORT' ) );

		if ( 443 !== $server_port && 80 !== $server_port ) {
			$page_url .= $server_name . ':' . $server_port . $request_uri;
		} else {
			$page_url .= $server_name . $request_uri;
		}

		$ids_query  = "ids={$form_id}|{$feed_id}|{$entry_id}";
		$ids_query .= '&hash=' . wp_hash( $ids_query );

		$page_url = add_query_arg(
			array( 'gf_mollie_result' => base64_encode( $ids_query ) ),
			$page_url
		);

		return $page_url;
	}

	/**
	 * Gets the payment validation result.
	 *
	 * @since 1.0
	 *
	 * @param array $validation_result    Contains the form validation results.
	 * @param array $authorization_result Contains the form authorization results.
	 *
	 * @return array The validation result for the credit card field.
	 */
	public function get_validation_result( $validation_result, $authorization_result ) {

		if ( empty( $authorization_result['error_message'] ) ) {
			return $validation_result;
		}

		$credit_card_page = 0;
		foreach ( $validation_result['form']['fields'] as &$field ) {
			if ( $field->type === 'mollie' ) {
				$field->failed_validation  = true;
				$field->validation_message = $authorization_result['error_message'];
				$credit_card_page          = $field['pageNumber'];
				break;
			}
		}

		$validation_result['credit_card_page'] = $credit_card_page;
		$validation_result['is_valid']         = false;

		return $validation_result;

	}

	/**
	 * Display the thank you page when there's a gf_mollie_result URL param.
	 *
	 * @since 1.0
	 */
	public function maybe_thankyou_page() {

		if ( ! $this->is_gravityforms_supported() ) {
			return;
		}

		// string was base64 encoded in $this->get_return_url().
		$str = rgget( 'gf_mollie_result' );
		if ( $str ) {
			$str = base64_decode( $str );

			parse_str( $str, $query );
			if ( wp_hash( 'ids=' . $query['ids'] ) === $query['hash'] ) {
				list( $form_id, $feed_id, $entry_id ) = explode( '|', $query['ids'] );

				$entry = GFAPI::get_entry( $entry_id );
				$form  = GFAPI::get_form( $form_id );

				// Fulfill delayed payments feeds.
				if ( method_exists( $this, 'trigger_payment_delayed_feeds' ) ) {
					$feed = $this->get_feed( $feed_id );

					$transaction_id = rgar( $entry, 'transaction_id' );
					$payment        = $this->get_mollie_payment( $transaction_id, $entry_id );
					$mollie_field   = $this->get_mollie_field( $form );

					// SCA credit cards need to update card data here.
					if ( rgar( $entry, $mollie_field->id . '.6' ) === 'creditcard' ) {
						$payment_method = rgars( $payment, 'details/cardLabel' );
						GFAPI::update_entry_property( $entry_id, 'payment_method', $payment_method );
						GFAPI::update_entry_field( $entry_id, $mollie_field->id . '.1', 'XXXXXXXXXXXX' . rgars( $payment, 'details/cardNumber' ) );
						GFAPI::update_entry_field( $entry_id, $mollie_field->id . '.4', $payment_method );
					}

					// Payment exists and status is paid.
					if ( ! is_wp_error( $payment ) && $this->is_payment_status( $payment, 'paid' ) ) {
						$this->trigger_payment_delayed_feeds( $transaction_id, $feed, $entry, $form );
					}
				}

				if ( ! class_exists( 'GFFormDisplay' ) ) {
					require_once GFCommon::get_base_path() . '/form_display.php';
				}

				$confirmation = GFFormDisplay::handle_confirmation( $form, $entry, false );

				if ( is_array( $confirmation ) && isset( $confirmation['redirect'] ) ) {
					header( "Location: {$confirmation['redirect']}" );
					exit;
				}

				GFFormDisplay::$submission[ $form_id ] = array(
					'is_confirmation'      => true,
					'confirmation_message' => $confirmation,
					'form'                 => $form,
					'lead'                 => $entry,
				);
			}
		}
	}




	// # WEBHOOKS ------------------------------------------------------------------------------------------------------

	/**
	 * Get webhook URL, Mollie uses this URL to report on payment status changes.
	 *
	 * @since 1.0
	 *
	 * @param string $entry_id GF Entry id.
	 *
	 * @return string Mollie uses this URL to report on payment status changes.
	 */
	public function get_webhook_url( $entry_id ) {

		$protocol = GFCommon::is_ssl() ? 'https' : 'http';

		return add_query_arg(
			array(
				'entry_id' => $entry_id,
				'callback' => $this->get_slug(),
			),
			home_url( '/', $protocol )
		);

	}

	/**
	 * Process the webhook requests from Mollie, based on transaction ID and entry ID. If these match, get payment status and update entry.
	 *
	 * @since 1.0
	 *
	 * @return array|WP_Error callback result, see https://docs.gravityforms.com/gfpaymentaddon/#callback- .
	 */
	public function callback() {

		// Transaction ID is in POST data.
		$transaction_id = $this->get_webhook_transaction_id();

		// Entry ID is a GET parameter.
		$entry_id = rgget( 'entry_id' );

		// Try to load payment data.
		$payment = $this->get_mollie_payment( $transaction_id, $entry_id );

		if ( false === $payment ) {
			$this->log_error( __METHOD__ . "() payment not found for entry {$entry_id} and transaction {$transaction_id}." );
			/* translators: error in webhook data */
			return new WP_Error( 'invalid_request', sprintf( __( 'Payment not found', 'gravityformsmollie' ) ) );
		}

		// The webhook is from an Order, try to get the payment_id (which is the transaction_id we stored).
		if ( rgar( $payment, 'resource' ) === 'order' && $payment_id = $this->get_payment_id_from_order( $payment, $entry_id ) ) {
			$transaction_id = $payment_id;
		}

		$action = $this->process_webhook_data( $payment, $transaction_id, $entry_id );

		// Custom a event id because Mollie doesn't have one.
		if ( false !== $action ) {

			// Fulfill delayed payments feeds.
			if ( method_exists( $this, 'trigger_payment_delayed_feeds' ) && $entry_id ) {
				$this->mollie_delayed_feeds( $transaction_id, $entry_id, $action );
			}

			$action['id'] = $transaction_id . '_' . $action['type'];

			// To make partial refunds can all be recorded, append the amount to the id.
			if ( $action['type'] === 'refund_payment' && rgar( $action, 'note' ) ) {
				$action['id'] .= '_' . $action['amount'];
			}
		}

		return $action;
	}

	/**
	 * Trigger methods that occur when payment delayed feeds are active
	 *
	 * @since 1.2
	 *
	 * @param string  $transaction_id id of Mollie transaction.
	 * @param integer $entry_id       id of GF Entry.
	 * @param array   $action         action returned by the mollie webhook.
	 */
	public function mollie_delayed_feeds( $transaction_id, $entry_id, $action ) {

		$entry = GFAPI::get_entry( $entry_id );

		if ( $entry ) {
			$form = GFAPI::get_form( $entry['form_id'] );
			$feed = ( $form ) ? $this->get_payment_feed( $entry, $form ) : null;
		}

		$payment      = $this->get_mollie_payment( $transaction_id, $entry_id );
		$mollie_field = $this->get_mollie_field( $form );

		if ( $action['payment_status'] === 'Paid' ) {
			$this->trigger_payment_delayed_feeds( $transaction_id, $feed, $entry, $form );
		}

		// SCA credit cards need to update card data here.
		if ( rgar( $entry, $mollie_field->id . '.6' ) === 'creditcard' ) {
			$payment_method = rgars( $payment, 'details/cardLabel' );
			GFAPI::update_entry_property( $entry_id, 'payment_method', $payment_method );
			GFAPI::update_entry_field( $entry_id, $mollie_field->id . '.1', 'XXXXXXXXXXXX' . rgars( $payment, 'details/cardNumber' ) );
			GFAPI::update_entry_field( $entry_id, $mollie_field->id . '.4', $payment_method );
		}
	}

	/**
	 * Based on payment and entry, return array with callback result.
	 *
	 * @since 1.0
	 *
	 * @param array   $payment        Mollie Payment.
	 * @param string  $transaction_id id of Mollie transaction.
	 * @param integer $entry_id       id of GF Entry.
	 *
	 * @return array|boolean Return a callback action, or false if the callback data is invalid.
	 */
	private function process_webhook_data( $payment, $transaction_id, $entry_id ) {
		$payment_method = $this->get_method_label( rgar( $payment, 'method' ) );
		if ( $payment_method === 'Credit card' ) {
			$payment_method = rgars( $payment, 'details/cardLabel' );
		}

		$action = array(
			'entry_id'         => $entry_id,
			'transaction_id'   => $transaction_id,
			'transaction_type' => 'product',
			'amount'           => rgars( $payment, 'amount/value' ),
			'payment_method'   => $payment_method,
		);

		if ( $this->is_payment_status( $payment, 'paid' ) && ! $this->is_payment_status( $payment, 'refund' ) && ! $this->is_payment_status( $payment, 'chargeback' ) ) {
			$action['payment_status'] = 'Paid';
			$action['type']           = 'complete_payment';
			return $action;
		}

		if ( $this->is_payment_status( $payment, 'open' ) || $this->is_payment_status( $payment, 'pending' ) ) {
			$action['payment_status'] = 'Pending';
			$action['type']           = 'add_pending_payment';
			return $action;
		}

		if ( $this->is_payment_status( $payment, 'failed' ) ) {
			$action['payment_status'] = 'Failed';
			$action['type']           = 'fail_payment';
			return $action;
		}

		if ( $this->is_payment_status( $payment, 'expired' ) ) {
			$action['payment_status'] = 'Expired';
			$action['type']           = 'void_authorization';
			return $action;
		}

		if ( $this->is_payment_status( $payment, 'canceled' ) ) {
			$action['payment_status'] = 'Cancelled';
			$action['type']           = 'void_authorization';
			return $action;
		}

		if ( $this->is_payment_status( $payment, 'refund' ) ) {
			$action['payment_status'] = 'Refunded';
			$action['type']           = 'refund_payment';

			// Check if it's a partial refund.
			if ( $action['amount'] !== rgars( $payment, 'amountRefunded/value' ) ) {
				$currency        = rgars( $payment, 'amountRefunded/currency' );
				$refunded_amount = rgars( $payment, 'amountRefunded/value' );

				// Only tracking partial refunds until the refunded amount >= the captured amount.
				if ( GFCommon::to_number( $action['amount'], $currency ) <= GFCommon::to_number( $refunded_amount, $currency ) ) {
					return $action;
				}

				$action['amount'] = $refunded_amount;
				$action           = $this->maybe_add_action_amount_formatted( $action, $currency );

				// Set up a special note to address this is the partial refund and it's the total refunded so far.
				// In case a single payment is refunded partially for several times.
				$action['note'] = sprintf( esc_html__( 'Payment has been partially refunded. The total amount refunded so far: %s. Transaction Id: %s.', 'gravityformsmollie' ), $action['amount_formatted'], $action['transaction_id'] );
			}

			return $action;
		}

		if ( $this->is_payment_status( $payment, 'chargeback' ) ) {
			$action['payment_status'] = 'Reversed';
			$action['type']           = 'refund_payment';
			return $action;
		}

		// When arriving here, status has not changed.
		return false;
	}

	/**
	 * Get transaction id from POST data.
	 *
	 * @since 1.0
	 *
	 * @return string|false
	 */
	private function get_webhook_transaction_id() {

		$body = file_get_contents( 'php://input' );

		if ( false === $body ) {
			return false;
		}

		if ( 0 !== strpos( $body, 'id=' ) ) {
			return false;
		}

		$transaction_id = str_replace( 'id=', '', $body );

		return ( '' !== $transaction_id ) ? $transaction_id : false;
	}





	// # MOLLIE API FUNCTIONS ------------------------------------------------------------------------------------------

	/**
	 * Exchange code for access token and refresh token.
	 *
	 * @since 1.0
	 *
	 * @param string $code code provided by Mollie API to exchange for access token.
	 *
	 * @return boolean true if tokens successfully saved.
	 */
	private function exchange_code_for_access_token( $code = '' ) {

		// Load the API library file if necessary.
		if ( ! class_exists( 'GF_Mollie_API' ) ) {
			require_once 'includes/class-gf-mollie-api.php';
		}

		$redirect_url = GF_Mollie_API::get_gravity_api_url( '/code' );

		$response = wp_remote_post(
			$redirect_url,
			array( 'body' => array( 'code' => $code ) )
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( __METHOD__ . '(): Exchange of code for tokens returned error: ' . $response->get_error_message() );

			return false;
		}

		// Save new access token.
		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		if ( ! $response_data ) {
			$this->log_error( __METHOD__ . '(): Request to exchange code for tokens returned no data.' );

			return false;
		}

		if ( ! rgempty( 'error', $response_data ) ) {
			$this->log_error( __METHOD__ . '(): Request to exchange code for tokens returned error: ' . $response_data['error_description'] );

			return false;
		}

		// If access and refresh token are provided, store in settings.
		if ( ! rgempty( 'access_token', $response_data ) && ! rgempty( 'refresh_token', $response_data ) ) {

			$settings = $this->get_plugin_settings();

			$auth = array(
				'access_token'  => rgar( $response_data, 'access_token' ),
				'refresh_token' => rgar( $response_data, 'refresh_token' ),
				'expires_in'    => rgar( $response_data, 'expires_in' ),
				'time_created'  => time(),
			);

			// Get the organization id.
			$mollie       = new GF_Mollie_API( $auth );
			$organization = $mollie->get_organization();

			if ( is_wp_error( $organization ) ) {
				$this->log_error( __METHOD__ . '(): Unable to retrieve organization info; ' . $organization->get_error_message() );
				return false;
			}

			$settings['mode']            = rgget( 'mode' );
			$settings['auth_token']      = $auth;
			$settings['organization_id'] = is_array( $organization ) ? rgar( $organization, 'id' ) : null;
			// mollie_state was for one time use only.
			unset( $settings['mollie_state'] );

			// Save plugin settings.
			$this->update_plugin_settings( $settings );

			return true;
		}

		// Gravity API returned no tokens.
		$this->log_error( __METHOD__ . '(): Request to exchange code for tokens returned no tokens.' );

		return false;
	}

	/**
	 * Retrieve Mollie payment status
	 *
	 * @since 1.0
	 *
	 * @param string $transaction_id Payment ID (generated by Mollie).
	 * @param string $entry_id       Entry ID (generated by GF).
	 *
	 * @return array|WP_Error|false payment data.
	 */
	public function get_mollie_payment( $transaction_id, $entry_id ) {

		// If API is not available, return false.
		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): Unable to get Mollie payment because API is not initialized.' );

			return false;
		}

		// Is the payment ID for this entry ID?
		$entry = GFAPI::get_entry( $entry_id );

		if ( strstr( $transaction_id, 'ord_' ) ) {

			// Get Mollie payment.
			$order = $this->api->get_mollie_payment( $transaction_id, $this->is_test_mode(), 'orders' );

			// If payment could not be found, exit.
			if ( is_wp_error( $order ) ) {
				$this->log_error( __METHOD__ . '(): Unable to find payment "' . $transaction_id .'"; ' . $order->get_error_message() );
				return false;
			}

			$transaction_id = $this->get_payment_id_from_order( $order, $entry_id );
		}


		if ( is_wp_error( $entry ) || $transaction_id !== $entry['transaction_id'] ) {
			$this->log_error( __METHOD__ . '(): entry id and transaction id do not match.' );
			return false;
		}

		return ( isset( $order ) ) ? $order : $this->api->get_mollie_payment( $transaction_id, $this->is_test_mode() );
	}

	/**
	 * Get list of payment methods for current Mollie user.
	 *
	 * @since 1.0
	 *
	 * @return array payment methods.
	 */
	public function get_methods() {
		static $methods;

		if ( ! empty( $methods ) ) {
			return $methods;
		}

		$profile_id = $this->get_website_profile_id();
		if ( is_null( $profile_id ) ) {
			return array();
		}

		$is_test_mode = $this->is_test_mode();
		$mode         = $is_test_mode ? 'test' : 'live';
		$cache_key    = $this->get_slug() . '_methods_' . $mode . '_' . $profile_id;
		$methods      = GFCache::get( $cache_key );
		if ( ! empty( $methods ) ) {
			return $methods;
		}

		// If API not available return empty array.
		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): Unable to get methods because API is not initialized.' );
			return array();
		}

		$this->log_debug( __METHOD__ . sprintf( '(): Getting %s mode methods for profile %s.', $mode, $profile_id ) );
		$methods = $this->api->get_methods( $profile_id, $is_test_mode, GFCommon::get_currency() );

		if ( is_wp_error( $methods ) || ! is_array( $methods ) ) {
			$this->log_error( __METHOD__ . '(): Unable to get payment methods; ' . ( is_wp_error( $methods ) ? $methods->get_error_message() : var_export( $methods, true ) ) );
			$methods = array(); // Reset static $methods variable.

			return array();
		}

		/**
		 * Filter the Mollie payment methods.
		 *
		 * @since 1.0
		 *
		 * @param array The payment methods.
		 */
		$methods = apply_filters( 'gform_mollie_payment_methods', $methods );

		GFCache::set( $cache_key, $methods, true, HOUR_IN_SECONDS );

		return $methods;
	}

	/**
	 * Get payment method label.
	 *
	 * @since 1.0
	 *
	 * @param string $payment_method The payment method.
	 *
	 * @return string
	 */
	public function get_method_label( $payment_method ) {
		$methods = $this->get_methods();
		foreach ( $methods as $method ) {
			if ( rgar( $method, 'id' ) === $payment_method ) {
				return rgar( $method, 'description' );
			}
		}

		return $payment_method;
	}




	// # HELPER FUNCTIONS -------------------------------------------------------------------------------------------------

	/**
	 * Get profile id, it is set in the Add-On settings.
	 *
	 * @since 1.0
	 *
	 * @return string|null Website Profile Id.
	 */
	public function get_website_profile_id() {

		return $this->get_plugin_setting( 'profile_id' );

	}

	/**
	 * Get test mode, it is set in the Add-On settings.
	 *
	 * @since 1.0
	 *
	 * @return boolean true if in test mode
	 */
	public function is_test_mode() {
		return 'test' === ( $this->is_save_postback() ? $this->get_setting( 'mode' ) : $this->get_plugin_setting( 'mode' ) );
	}

	/**
	 * Return the description to be used with the Mollie payment.
	 *
	 * @since 1.0
	 *
	 * @param array $entry           The entry object currently being processed.
	 * @param array $submission_data The customer and transaction data.
	 * @param array $feed            The feed object currently being processed.
	 *
	 * @return string Payment description.
	 */
	public function get_payment_description( $entry, $submission_data, $feed ) {
		$strings = array();

		if ( $entry['id'] ) {
			$strings['entry_id'] = sprintf( 'Entry ID: %d', $entry['id'] );
		}

		// Charge description format:
		// Entry ID: 123, Products: Product A, Product B, Product C .
		$strings['products'] = sprintf(
			/* translators: Description of order, displayed in your Mollie dashboard and in confirmation emails. */
			_n( 'Product: %s', 'Products: %s', count( $submission_data['line_items'] ), 'gravityformsmollie' ),
			implode( ', ', wp_list_pluck( $submission_data['line_items'], 'name' ) )
		);

		$description = implode( ', ', $strings );

		/**
		 * Allow the payment description to be overridden.
		 *
		 * @since 1.0
		 *
		 * @param string $description     The payment description.
		 * @param array  $strings         Contains the Entry ID and Products. The array which was imploded to create the description.
		 * @param array  $entry           The entry object currently being processed.
		 * @param array  $submission_data The customer and transaction data.
		 * @param array  $feed            The feed object currently being processed.
		 */
		return apply_filters( 'gform_mollie_payment_description', $description, $strings, $entry, $submission_data, $feed );
	}

	/**
	 * Revoke refresh token and remove tokens from Settings. Then send JSON error object or { 'success' => true } .
	 *
	 * @since 1.0
	 */
	public function ajax_deauthorize() {
		check_ajax_referer( 'gf_mollie_ajax', 'nonce' );

		if ( ! GFCommon::current_user_can_any( $this->_capabilities_settings_page ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Access denied.', 'gravityformsmollie' ) ) );
		}

		// If API not available return empty array.
		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): Unable to get methods because API is not initialized.' );
			wp_send_json_error( array( 'message' => esc_html__( 'Unable to deauthorize because API could not be initialized.', 'gravityformsmollie' ) ) );
		}

		$result = $this->api->revoke_refresh_token();

		if ( is_wp_error( $result ) ) {
			$this->log_error( __METHOD__ . '(): Unable to revoke refresh token; ' . $result->get_error_message() );
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Call parent method to prevent adding back of tokens.
		parent::update_plugin_settings( array( 'mode' => $this->get_plugin_setting( 'mode' ) ) );

		wp_send_json_success();
	}

	/**
	 * Check if the fronted scripts should be enqueued.
	 *
	 * @since  1.0
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return bool If the script should be enqueued.
	 */
	public function frontend_script_callback( $form ) {

		return $form && $this->has_mollie_field( $form ) && $this->has_feed( $form['id'] ) && $this->initialize_api();

	}

	/**
	 * Parse Feed meta data and maybe add address info to payment details.
	 *
	 * @since 1.0
	 *
	 * @param array  $entry           The entry data.
	 * @param array  $feed            The feed object.
	 * @param array  $payment_details The payment details.
	 * @param string $key             The key to use when adding to payment details.
	 *
	 * @return array payment details.
	 */
	private function maybe_add_address_info( $entry, $feed, $payment_details, $key = 'shippingAddress' ) {

		if ( empty( $key ) ) {
			return $payment_details;
		}

		$form    = GFAPI::get_form( $entry['form_id'] );
		$address = array();

		$address['givenName'] = $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'billingInformation_first_name' ) );
		if ( empty( $address['givenName'] ) ) {
			// First Name is a required value.
			return $payment_details;
		}

		$address['familyName'] = $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'billingInformation_last_name' ) );
		if ( empty( $address['familyName'] ) ) {
			// Last Name is a required value.
			return $payment_details;
		}

		$address['streetAndNumber'] = $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'billingInformation_address' ) );
		if ( empty( $address['streetAndNumber'] ) ) {
			// Street and number is a required value.
			return $payment_details;
		}

		$address['postalCode'] = $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'billingInformation_zip' ) );
		if ( empty( $address['postalCode'] ) ) {
			// Postal code is a required value.
			return $payment_details;
		}

		$address['city'] = $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'billingInformation_city' ) );
		if ( empty( $address['city'] ) ) {
			// City is a required value.
			return $payment_details;
		}

		$country_name = $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'billingInformation_country' ) );
		if ( empty( $country_name ) ) {
			// Country is a required value.
			return $payment_details;
		}
		// Get country code for country name.
		$address['country'] = class_exists( 'GF_Field_Address' ) ? GF_Fields::get( 'address' )->get_country_code( $country_name ) : GFCommon::get_country_code( $country_name );

		$address['email'] = $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'billingInformation_email' ) );
		if ( empty( $address['email'] ) ) {
			// Email is a required value.
			return $payment_details;
		}

		if ( $address2 = $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'billingInformation_address2' ) ) && ! empty( $address2 ) ) {
			$address['streetAdditional'] = $address2;
		}

		if ( $state = $this->get_field_value( $form, $entry, rgar( $feed['meta'], 'billingInformation_state' ) ) && ! empty( $state ) ) {
			$address['region'] = $state;
		}

		$payment_details[ $key ] = $address;

		return $payment_details;
	}

	/**
	 * Format payment amount for GF database
	 *
	 * @since 1.0
	 *
	 * @param float  $payment_amount The amount to convert to GF amount string.
	 * @param string $currency       The currency.
	 *
	 * @return string amount in XXXX.XX format.
	 */
	public function get_amount_formatted( $payment_amount, $currency = null ) {
		if ( empty( $currency ) ) {
			$currency = GFCommon::get_currency();
		}

		$currency = $this->get_currency( $currency );

		if ( ! $currency->is_zero_decimal() ) {
			// Do not set a thousand separator because Mollie wouldn't take it.
			$payment_amount = number_format( $payment_amount, 2, '.', '' );
		}

		return strval( $payment_amount );
	}

	/**
	 * Check if the form has an active Mollie feed and a Payment Method field is added
	 *
	 * @since 1.0
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return bool True if form has an active Mollie feed and a Payment Method field has been added to the form.
	 */
	public function frontend_style_callback( $form ) {
		return $form && ( $this->has_mollie_field( $form ) );
	}

	/**
	 * Check if form has Payment Method field.
	 *
	 * @since 1.0
	 *
	 * @param array $form The Form object.
	 *
	 * @return bool True if form has Mollie Payment Method field.
	 */
	public function has_mollie_field( $form = null ) {
		if ( is_null( $form ) ) {
			$form = $this->get_current_form();
		}

		$field = $this->get_mollie_field( $form );

		return false !== $field;
	}

	/**
	 * Get Payment Method field for form.
	 *
	 * @since 1.0
	 *
	 * @param array $form The Form object.
	 *
	 * @return GF_Field Field or false.
	 */
	public function get_mollie_field( $form ) {
		$fields = GFAPI::get_fields_by_type( $form, array( 'mollie' ) );

		return reset( $fields );
	}

	/**
	 * If Credit Card is one of the supported payment methods.
	 *
	 * @since 1.0
	 *
	 * @return bool
	 */
	public function is_creditcard_supported() {
		$methods = $this->get_methods();

		foreach ( $methods as $method ) {
			if ( rgar( $method, 'id' ) === 'creditcard' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Decides if the vendor script should be enqueued or not.
	 *
	 * @since 2.0
	 *
	 * @param array|null $form The current form being processed or null if no form exists.
	 *
	 * @return bool
	 */
	public function vendor_script_callback( $form ) {
		return is_array( $form ) && $this->is_creditcard_supported();
	}

	/**
	 * Return true if the payment status matched.
	 *
	 * @since 1.0
	 *
	 * @param array  $payment        The Mollie payment object.
	 * @param string $payment_status The payment status.
	 *
	 * @return bool
	 */
	private function is_payment_status( $payment, $payment_status ) {
		$result = false;

		switch ( $payment_status ) {
			case 'paid':
				$result = ! rgempty( 'paidAt', $payment );
				break;
			case 'refund':
				$refunds = rgars( $payment, '_links/refunds' );
				$result  = ! empty( $refunds );
				break;
			case 'chargeback':
				$chargebacks = rgars( $payment, '_links/chargebacks' );
				$result      = ! empty( $chargebacks );
				break;
			default:
				$result = $payment_status === rgar( $payment, 'status' );
		}

		return $result;
	}

	/**
	 * Add credit card warning CSS class for the Mollie field.
	 *
	 * @since 1.0
	 *
	 * @param string   $css_class CSS classes.
	 * @param GF_Field $field Field object.
	 * @param array    $form Form array.
	 *
	 * @return string
	 */
	public function filter_gform_field_css_class( $css_class, $field, $form ) {
		if ( GFFormsModel::get_input_type( $field ) === 'mollie' ) {
			$css_class .= ' gfield_mollie';

			if ( ! GFCommon::is_ssl() ) {
				$css_class .= ' gfield_creditcard_warning';
			}
		}

		return $css_class;
	}

	/**
	 * Filter the GF_Field_Mollie object after it is created.
	 *
	 * @since  1.0
	 *
	 * @param array $form_meta The form meta.
	 * @param bool  $is_new    Returns true if this is a new form.
	 */
	public function maybe_add_feed( $form_meta, $is_new ) {
		if ( $is_new ) {
			return;
		}

		if ( $this->has_mollie_field( $form_meta ) ) {
			$field = $this->get_mollie_field( $form_meta );

			$feeds = $this->get_feeds( $field->formId );
			// Only activate the feed if there's only one.
			if ( count( $feeds ) === 1 ) {
				if ( ! $feeds[0]['is_active'] ) {
					$this->update_feed_active( $feeds[0]['id'], 1 );
				}
			} elseif ( ! $feeds ) {
				// Add a new Mollie feed.
				$name_field    = GFFormsModel::get_fields_by_type( $form_meta, array( 'name' ) );
				$email_field   = GFFormsModel::get_fields_by_type( $form_meta, array( 'email' ) );
				$address_field = GFFormsModel::get_fields_by_type( $form_meta, array( 'address' ) );

				$feed = array(
					'feedName'                                => $this->get_short_title() . ' Feed 1',
					'transactionType'                         => 'product',
					'paymentAmount'                           => 'form_total',
					'feed_condition_conditional_logic'        => '0',
					'feed_condition_conditional_logic_object' => array(),
				);

				if ( ! empty( $name_field ) ) {
					$feed['billingInformation_first_name'] = $name_field[0]->id . '.3';
					$feed['billingInformation_last_name']  = $name_field[0]->id . '.6';
				}

				if ( ! empty( $email_field ) ) {
					$feed['billingInformation_email'] = $email_field[0]->id;
				}

				if ( ! empty( $address_field ) ) {
					$feed['billingInformation_address']  = $address_field[0]->id . '.1';
					$feed['billingInformation_address2'] = $address_field[0]->id . '.2';
					$feed['billingInformation_city']     = $address_field[0]->id . '.3';
					$feed['billingInformation_state']    = $address_field[0]->id . '.4';
					$feed['billingInformation_zip']      = $address_field[0]->id . '.5';
					$feed['billingInformation_country']  = $address_field[0]->id . '.6';
				}

				GFAPI::add_feed( $field->formId, $feed, $this->get_slug() );
			}
		}
	}

	/**
	 * Target of gform_before_delete_field hook. Sets relevant payment feeds to inactive when the Mollie field is deleted.
	 *
	 * @since 1.0
	 *
	 * @param int $form_id ID of the form being edited.
	 * @param int $field_id ID of the field being deleted.
	 */
	public function before_delete_field( $form_id, $field_id ) {
		parent::before_delete_field( $form_id, $field_id );

		$form = GFAPI::get_form( $form_id );
		if ( $this->has_mollie_field( $form ) ) {
			$field = $this->get_mollie_field( $form );

			if ( is_object( $field ) && $field->id == $field_id ) {
				$feeds = $this->get_feeds( $form_id );
				foreach ( $feeds as $feed ) {
					if ( $feed['is_active'] ) {
						$this->update_feed_active( $feed['id'], 0 );
					}
				}
			}
		}
	}

	/**
	 * Get post payment actions config.
	 *
	 * @since 1.0
	 *
	 * @param string $feed_slug The feed slug.
	 *
	 * @return array
	 */
	public function get_post_payment_actions_config( $feed_slug ) {
		return array(
			'position' => 'before',
			'setting'  => 'conditionalLogic',
		);
	}

	/**
	 * Get the payment ID from an order object.
	 *
	 * @since 1.0
	 *
	 * @param array $order    The Mollie Order object.
	 * @param int   $entry_id The entry ID.
	 *
	 * @return bool|string
	 */
	public function get_payment_id_from_order( $order, $entry_id ) {
		static $payment_id;

		if ( isset( $payment_id ) ) {
			return $payment_id;
		}

		$orderNumber = rgar( $order, 'orderNumber' );
		if ( $orderNumber === $entry_id ) {
			$entry = GFAPI::get_entry( $entry_id );

			$payment_id = rgar( $entry, 'transaction_id' );

			return $payment_id;
		}

		return false;
	}

	/**
	 * Check if the current form view is a conversational form.
	 *
	 * @since 1.4.0
	 *
	 * @param array $form The form array
	 *
	 * @return bool True if the current form view is a conversational form.
	 */
	public function is_conversational_form( $form ) {
		global $wp;

		$slug = $this->get_requested_slug();

		if ( ! empty( $form['gf_theme_layers']['enable'] ) &&
			! empty( $form['gf_theme_layers']['form_full_screen_slug'] ) &&
			$form['gf_theme_layers']['form_full_screen_slug'] === $slug ) {

			return true;

		}

		return false;

	}

	/**
	 * Check if the site is using plain permalinks.
	 *
	 * @since 1.4
	 *
	 * @return bool
	 */
	public function is_plain_permalinks() {
		return get_option( 'permalink_structure' ) == '';
	}

	/**
	 * Get the slug of the requested conversational form.
	 *
	 * @param $vars
	 * @return mixed|void
	 * @since 1.4
	 */
	public function get_requested_slug() {
		global $wp;

		if ( $this->is_plain_permalinks() && isset( $wp->query_vars[ $this->query_var ] ) ) {
			return strtolower( $wp->query_vars[ $this->query_var ] );
		} else {
			return strtolower( $wp->request );
		}
	}

}
