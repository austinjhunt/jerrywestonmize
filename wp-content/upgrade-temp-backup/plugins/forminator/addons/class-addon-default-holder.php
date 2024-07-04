<?php


/**
 * Class Forminator_Integration_Default_Holder
 * Placeholder for nonexistent PRO Integration
 *
 * @since 1.1
 */
class Forminator_Integration_Default_Holder extends Forminator_Integration {

	protected static $instance = null;

	protected $_slug                   = '';
	protected $_version                = '1.0';
	protected $_min_forminator_version = PHP_INT_MAX; // make it un-activable.
	protected $_short_title            = '';
	protected $_title                  = '';
	protected $_url                    = '';

	/**
	 * Dynamically set fields form array
	 *
	 * @since 1.1
	 *
	 * @param $properties
	 *
	 * @return $this
	 */
	public function from_array( $properties ) {
		foreach ( $properties as $field => $value ) {
			if ( property_exists( $this, $field ) ) {
				$this->$field = $value;
			}
		}

		return $this;
	}

	/**
	 * Mark non existent integration as not connected always
	 *
	 * @since 1.1
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
	 * Mark non existent integration as form not connected always
	 *
	 * @since 1.1
	 * @param int    $module_id Form ID.
	 * @param string $module_slug Module type.
	 * @param bool   $check_lead Check is lead connected or not.
	 * @return bool
	 */
	public function is_module_connected( $module_id, $module_slug = 'form', $check_lead = false ) {
		return false;
	}

	/**
	 * Make this not activable
	 *
	 * @since 1.1
	 * @return bool
	 */
	public function check_is_activable() {
		return false;
	}
}
