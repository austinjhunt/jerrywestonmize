<?php

final class Forminator_Integration_Simple extends Forminator_Integration {

	/**
	 * Use this trait to mark this addon as PRO
	 */
	protected $_slug                   = 'simple';
	protected $_version                = FORMINATOR_ADDON_SIMPLE_VERSION;
	protected $_min_forminator_version = '1.1';
	protected $_short_title            = 'simple';
	protected $_title                  = 'Simple';

	public function __construct() {
		// late init to allow translation.
		$this->_description                = esc_html__( 'Make your form Simple-able', 'forminator' );
	}

	/**
	 * Flag for check if and addon connected (global settings suchs as api key complete)
	 *
	 * @return bool
	 */
	public function is_connected() {
		return false;
	}

	/**
	 * Authorized Callback
	 *
	 * @return bool
	 */
	public function is_authorized() {
		return false;
	}

	/**
	 * Flag for check if and addon connected to a form(form settings such as list name completed)
	 *
	 * @param int    $module_id Form ID.
	 * @param string $module_slug Module type.
	 * @param bool   $check_lead Check is lead connected or not.
	 * @return bool
	 */
	public function is_module_connected( $module_id, $module_slug = 'form', $check_lead = false ) {
		return false;
	}
}
