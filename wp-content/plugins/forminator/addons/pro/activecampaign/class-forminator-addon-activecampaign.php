<?php

require_once dirname( __FILE__ ) . '/lib/class-forminator-addon-activecampaign-wp-api.php';

/**
 * Class Forminator_Activecampaign
 * Activecampaign Integration Main Class
 *
 * @since 1.0 Activecampaign Integration
 */
final class Forminator_Activecampaign extends Forminator_Integration {

	/**
	 * @var self|null
	 */
	protected static $instance = null;

	protected $_slug                   = 'activecampaign';
	protected $_version                = FORMINATOR_ADDON_ACTIVECAMPAIGN_VERSION;
	protected $_min_forminator_version = '1.1';
	protected $_short_title            = 'ActiveCampaign';
	protected $_title                  = 'ActiveCampaign';
	protected $_position               = 8;

	/**
	 * @var Forminator_Activecampaign_Wp_Api|null
	 */
	private static $api = null;

	public $connected_account = null;

	/**
	 * Forminator_Activecampaign constructor.
	 *
	 * @since 1.0 Activecampaign Integration
	 */
	public function __construct() {
		// late init to allow translation.
		$this->_description                = esc_html__( 'Get awesome by your form.', 'forminator' );

		$this->is_multi_global = true;
	}

	/**
	 * Override settings available,
	 *
	 * @since 1.0 Activecampaign Integration
	 * @return bool
	 */
	public function is_settings_available() {
		return true;
	}

	/**
	 * Flag enable delete contact before delete entries
	 *
	 * Its disabled by default
	 *
	 * @since 1.0 Activecampaign Integration
	 * @return bool
	 */
	public static function is_enable_delete_contact() {
		$enable_delete_contact = false;
		if ( defined( 'FORMINATOR_ADDON_ACTIVECAMPAIGN_ENABLE_DELETE_CONTACT' ) && FORMINATOR_ADDON_ACTIVECAMPAIGN_ENABLE_DELETE_CONTACT ) {
			$enable_delete_contact = true;
		}

		/**
		 * Filter Flag enable delete contact before delete entries
		 *
		 * @since  1.2
		 *
		 * @params bool $enable_delete_contact
		 */
		$enable_delete_contact = apply_filters( 'forminator_addon_activecampaign_delete_contact', $enable_delete_contact );

		return $enable_delete_contact;
	}

	/**
	 * Allow multiple connection on one form
	 *
	 * @since 1.0 Activecampaign Integration
	 * @return bool
	 */
	public function is_allow_multi_on_form() {
		return true;
	}

	/**
	 * Setting wizard of Active Campaign
	 *
	 * @since 1.0 Activecampaign Integration
	 * @return array
	 */
	public function settings_wizards() {
		return array(
			array(
				'callback'     => array( $this, 'setup_api' ),
				'is_completed' => array( $this, 'is_authorized' ),
			),
		);
	}


	/**
	 * Set up API Wizard
	 *
	 * @since 1.0 Active Campaign Integration
	 *
	 * @param     $submitted_data
	 *
	 * @param int $form_id
	 *
	 * @return array
	 */
	public function setup_api( $submitted_data, $form_id = 0 ) {
		$settings_values  = $this->get_settings_values();
		$template         = forminator_addon_activecampaign_dir() . 'views/settings/setup-api.php';
		$template_params  = array(
			'identifier'    => '',
			'error_message' => '',
			'api_url'       => '',
			'api_url_error' => '',
			'api_key'       => '',
			'api_key_error' => '',
		);
		$has_errors       = false;
		$show_success     = false;
		$buttons          = array();
		$is_submit        = ! empty( $submitted_data );

		foreach ( $template_params as $key => $value ) {
			if ( isset( $submitted_data[ $key ] ) ) {
				$template_params[ $key ] = $submitted_data[ $key ];
			} elseif ( isset( $settings_values[ $key ] ) ) {
				$template_params[ $key ] = $settings_values[ $key ];
			}
		}

		if ( $is_submit ) {
			$api_url = isset( $submitted_data['api_url'] ) ? trim( $submitted_data['api_url'] ) : '';
			$api_key = isset( $submitted_data['api_key'] ) ? $submitted_data['api_key'] : '';
			$identifier = isset( $submitted_data['identifier'] ) ? $submitted_data['identifier'] : '';

			try {
				$api_url = $this->validate_api_url( $api_url );
			} catch ( Forminator_Integration_Exception $e ) {
				$template_params['api_url_error'] = $e->getMessage();
				$has_errors                       = true;
			}

			try {
				$api_key = $this->validate_api_key( $api_key );
			} catch ( Forminator_Integration_Exception $e ) {
				$template_params['api_key_error'] = $e->getMessage();
				$has_errors                       = true;
			}

			if ( ! $has_errors ) {
				// validate api.
				try {

					$this->validate_api( $api_url, $api_key );

					if ( ! forminator_addon_is_active( $this->_slug ) ) {
						$activated = Forminator_Integration_Loader::get_instance()->activate_addon( $this->_slug );
						if ( ! $activated ) {
							throw new Forminator_Integration_Exception( Forminator_Integration_Loader::get_instance()->get_last_error_message() );
						}
					}

					$settings_values = array(
						'api_url' => $api_url,
						'api_key' => $api_key,
						'identifier' => $identifier,
					);
					$this->save_settings_values( $settings_values );

					// no form_id its on global settings.
					if ( empty( $form_id ) ) {
						$show_success = true;
					}
				} catch ( Forminator_Integration_Exception $e ) {
					$template_params['error_message'] = $this->connection_failed();
					$template_params['api_key_error'] = esc_html__( 'Please enter a valid ActiveCampaign API Key', 'forminator' );
					$template_params['api_url_error'] = esc_html__( 'Please enter a valid ActiveCampaign API URL', 'forminator' );
					$has_errors                       = true;
				}
			}
		}

		if ( $show_success ) {
			$html = $this->success_authorize();
		} else {
			if ( $this->is_connected() ) {
				$buttons['disconnect'] = array(
					'markup' => self::get_button_markup( esc_html__( 'Disconnect', 'forminator' ), 'sui-button-ghost forminator-addon-disconnect' ),
				);
				$buttons['submit']     = array(
					'markup' => '<div class="sui-actions-right">' .
								self::get_button_markup( esc_html__( 'Save', 'forminator' ), 'forminator-addon-connect' ) .
								'</div>',
				);
			} else {
				$buttons['submit'] = array(
					'markup' => '<div class="sui-actions-right">' .
								self::get_button_markup( esc_html__( 'CONNECT', 'forminator' ), 'forminator-addon-connect' ) .
								'</div>',
				);
			}
			$html = self::get_template( $template, $template_params );
		}

		return array(
			'html'       => $html,
			'buttons'    => $buttons,
			'redirect'   => false,
			'has_errors' => $has_errors,
		);
	}

	public function is_authorized() {
		$setting_values = $this->get_settings_values();

		// check api_key and and api_url set up.
		return isset( $setting_values['api_key'] ) && $setting_values['api_key'] && isset( $setting_values['api_url'] ) && ! empty( $setting_values['api_url'] );
	}

	/**
	 * Validate API URL
	 *
	 * @since 1.0 Active Campaign
	 *
	 * @param string $api_url
	 *
	 * @return string
	 * @throws Forminator_Integration_Exception
	 */
	public function validate_api_url( $api_url ) {
		if ( empty( $api_url ) ) {
			throw new Forminator_Integration_Exception( esc_html__( 'Please enter a valid ActiveCampaign API URL', 'forminator' ) );
		}

		$api_url = wp_http_validate_url( $api_url );
		if ( false === $api_url ) {
			throw new Forminator_Integration_Exception( esc_html__( 'Please enter a valid ActiveCampaign API URL', 'forminator' ) );
		}

		return $api_url;
	}

	/**
	 * Validate API Key
	 *
	 * @since 1.0 Active Campaign
	 *
	 * @param string $api_key
	 *
	 * @return string
	 * @throws Forminator_Integration_Exception
	 */
	public function validate_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			throw new Forminator_Integration_Exception( esc_html__( 'Please enter a valid ActiveCampaign API Key', 'forminator' ) );
		}

		return $api_key;
	}

	/**
	 * Validate API
	 *
	 * @since 1.0 Active Campaign Integration
	 *
	 * @param $api_url
	 * @param $api_key
	 *
	 * @throws Forminator_Integration_Exception
	 */
	public function validate_api( $api_url, $api_key ) {
		$api             = $this->get_api( $api_url, $api_key );
		$account_request = $api->get_account();

		if ( ! isset( $account_request->account ) || empty( $account_request->account ) ) {
			throw new Forminator_Integration_Exception( esc_html__( 'Failed to get ActiveCampaign account info.', 'forminator' ) );
		}

		$this->connected_account = $account_request->account;
	}

	/**
	 * Get API Instance
	 *
	 * @since 1.0 Active Campaign Integration
	 *
	 * @param null $api_url
	 * @param null $api_key
	 *
	 * @return Forminator_Activecampaign_Wp_Api
	 * @throws Forminator_Integration_Exception
	 */
	public function get_api( $api_url = null, $api_key = null ) {
		if ( is_null( $api_key ) || is_null( $api_url ) ) {
			$setting_values = $this->get_settings_values();
			$api_key        = '';
			$api_url        = '';
			if ( isset( $setting_values['api_url'] ) ) {
				$api_url = $setting_values['api_url'];
			}

			if ( isset( $setting_values['api_key'] ) ) {
				$api_key = $setting_values['api_key'];
			}
		}
		$api = new Forminator_Activecampaign_Wp_Api( $api_url, $api_key );

		return $api;
	}

	public function before_save_settings_values( $values ) {
		if ( ! empty( $this->connected_account ) ) {
			$values['connected_account'] = $this->connected_account;
		}

		return $values;
	}

	/**
	 * Allow multiple connection on one quiz
	 *
	 * @since 1.6.1
	 * @return bool
	 */
	public function is_allow_multi_on_quiz() {
		return true;
	}
}
