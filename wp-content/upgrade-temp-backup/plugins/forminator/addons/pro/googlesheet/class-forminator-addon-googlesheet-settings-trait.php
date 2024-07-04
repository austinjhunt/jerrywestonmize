<?php
/**
 * Trait for common methods for Googlesheet settings classes
 *
 * @since 1.30
 * @package Googlesheet Integration
 */

/**
 * Trait Forminator_Googlesheet_Settings_Trait
 */
trait Forminator_Googlesheet_Settings_Trait {

	/**
	 * Google Sheets Module Settings wizard
	 *
	 * @since 1.0 Google Sheets Integration
	 * @return array
	 */
	public function module_settings_wizards() {
		// numerical array steps.
		return array(
			array(
				'callback'     => array( $this, 'pick_name' ),
				'is_completed' => array( $this, 'setup_name_is_completed' ),
			),
			array(
				'callback'     => array( $this, 'setup_sheet' ),
				'is_completed' => array( $this, 'setup_sheet_is_completed' ),
			),
		);
	}

	/**
	 * Setup Connection Name
	 *
	 * @since 1.0 Google Sheets Integration
	 * @param array $submitted_data Submitted data.
	 * @return array
	 */
	public function pick_name( $submitted_data ) {
		$template = forminator_addon_googlesheet_dir() . 'views/module-settings/pick-name.php';

		$multi_id = $this->generate_multi_id();
		if ( isset( $submitted_data['multi_id'] ) ) {
			$multi_id = $submitted_data['multi_id'];
		}

		$template_params = array(
			'name'       => $this->get_multi_id_settings( $multi_id, 'name' ),
			'file_id'    => $this->get_multi_id_settings( $multi_id, 'file_id' ),
			'name_error' => '',
			'multi_id'   => $multi_id,
		);

		unset( $submitted_data['multi_id'] );

		$is_submit  = ! empty( $submitted_data );
		$has_errors = false;
		if ( $is_submit ) {
			$name                    = isset( $submitted_data['name'] ) ? $submitted_data['name'] : '';
			$template_params['name'] = $name;

			try {
				if ( empty( $name ) ) {
					throw new Forminator_Integration_Exception( esc_html__( 'Please pick valid name', 'forminator' ) );
				}

				$time_added = $this->get_multi_id_settings( $multi_id, 'time_added', time() );
				$this->save_multi_id_setting_values(
					$multi_id,
					array(
						'name'       => $name,
						'time_added' => $time_added,
					)
				);

			} catch ( Forminator_Integration_Exception $e ) {
				$template_params['name_error'] = $e->getMessage();
				$has_errors                    = true;
			}
		}

		$buttons = array();
		if ( $this->setup_name_is_completed( array( 'multi_id' => $multi_id ) ) ) {
			$buttons['disconnect']['markup'] = Forminator_Integration::get_button_markup(
				esc_html__( 'Deactivate', 'forminator' ),
				'sui-button-ghost sui-tooltip sui-tooltip-top-center forminator-addon-form-disconnect',
				esc_html__( 'Deactivate Google Sheets Integration from this module.', 'forminator' )
			);
		}

		$buttons['next']['markup'] = '<div class="sui-actions-right">' .
			Forminator_Integration::get_button_markup( esc_html__( 'Next', 'forminator' ), 'forminator-addon-next' ) .
			'</div>';

		return array(
			'html'       => Forminator_Integration::get_template( $template, $template_params ),
			'buttons'    => $buttons,
			'redirect'   => false,
			'has_errors' => $has_errors,
		);
	}

	/**
	 * Setup Contact List
	 *
	 * @since 1.0 Google Sheets Integration
	 * @param array $submitted_data Submitted data.
	 * @return array
	 * @throws Exception
	 */
	public function setup_sheet( $submitted_data ) {
		$template = forminator_addon_googlesheet_dir() . 'views/module-settings/setup-sheet.php';

		if ( ! isset( $submitted_data['multi_id'] ) ) {
			return $this->get_force_closed_wizard();
		}

		$multi_id = $submitted_data['multi_id'];
		unset( $submitted_data['multi_id'] );

		$template_params = array(
			'folder_id'      => $this->get_multi_id_settings( $multi_id, 'folder_id' ),
			'file_name'      => $this->get_multi_id_settings( $multi_id, 'file_name' ),
			'spreadsheet_id' => $this->get_multi_id_settings( $multi_id, 'spreadsheet_id' ),
			'file_id'        => $this->get_multi_id_settings( $multi_id, 'file_id' ),
			'error_message'  => '',
			'multi_id'       => $multi_id,
		);

		$is_submit    = ! empty( $submitted_data );
		$has_errors   = false;
		$notification = array();
		$is_close     = false;

		if ( $is_submit ) {
			$folder_id                    = isset( $submitted_data['folder_id'] ) ? $submitted_data['folder_id'] : '';
			$template_params['folder_id'] = $folder_id;
			$file_name                    = isset( $submitted_data['file_name'] ) ? $submitted_data['file_name'] : '';
			$template_params['file_name'] = $file_name;

			try {
				$input_exceptions = new Forminator_Integration_Settings_Exception();
				if ( empty( $file_name ) ) {
					$input_exceptions->add_input_exception( esc_html__( 'Please put valid Spreadsheet name', 'forminator' ), 'file_name_error' );
				}

				$google_client = $this->addon->get_google_client();
				$google_client->setAccessToken( $this->addon->get_client_access_token() );

				if ( ! empty( $folder_id ) ) {
					$drive = new Forminator_Google_Service_Drive( $google_client );
					try {
						$folder = $drive->files->get( $folder_id, array( 'supportsAllDrives' => true ) );
						// its from API var.
						// phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar
						if ( Forminator_Googlesheet::MIME_TYPE_GOOGLE_DRIVE_FOLDER !== $folder->mimeType ) {
							$input_exceptions->add_input_exception( esc_html__( 'This is not a folder, please use a valid Folder ID.', 'forminator' ), 'folder_id_error' );
						}
					} catch ( Forminator_Google_Exception $google_exception ) {
						// catch 404.
						if ( false !== stripos( $google_exception->getMessage(), 'File not found' ) ) {
							$input_exceptions->add_input_exception( esc_html__( 'Folder not found, please put Folder ID.', 'forminator' ), 'folder_id_error' );
						} else {
							throw $google_exception;
						}
					}
				}

				if ( $input_exceptions->input_exceptions_is_available() ) {
					throw $input_exceptions;
				}

				$file = new Forminator_Google_Service_Drive_DriveFile();
				$file->setMimeType( Forminator_Googlesheet::MIME_TYPE_GOOGLE_SPREADSHEET );
				$file->setName( $file_name );

				if ( ! empty( $folder_id ) ) {
					$file->setParents( array( $folder_id ) );
				}

				$drive     = new Forminator_Google_Service_Drive( $google_client );
				$new_sheet = $drive->files->create( $file, array( 'supportsAllDrives' => true ) );

				$this->save_multi_id_setting_values(
					$multi_id,
					array(
						'folder_id' => $folder_id,
						'file_name' => $file_name,
						'file_id'   => $new_sheet->getId(),
					)
				);

				$notification = array(
					'type' => 'success',
					'text' => '<strong>' . $this->addon->get_title() . '</strong> ' . esc_html__( 'Successfully created spreadsheet and connected to your module', 'forminator' ),
				);
				$is_close     = true;

			} catch ( Forminator_Integration_Settings_Exception $e ) {
				$input_errors    = $e->get_input_exceptions();
				$template_params = array_merge( $template_params, $input_errors );
				$has_errors      = true;
			} catch ( Forminator_Integration_Exception $e ) {
				$template_params['error_message'] = $e->getMessage();
				$has_errors                       = true;
			} catch ( Forminator_Google_Exception $e ) {
				$template_params['error_message'] = $e->getMessage();
				$has_errors                       = true;
			}
		}

		$buttons = array();
		if ( $this->setup_name_is_completed( array( 'multi_id' => $multi_id ) ) ) {
			$buttons['disconnect']['markup'] = Forminator_Integration::get_button_markup(
				esc_html__( 'Deactivate', 'forminator' ),
				'sui-button-ghost sui-tooltip sui-tooltip-top-center forminator-addon-form-disconnect',
				esc_html__( 'Deactivate Google Sheets Integration from this module.', 'forminator' )
			);
		}

		$buttons['next']['markup'] = '<div class="sui-actions-right">' .
			Forminator_Integration::get_button_markup( esc_html__( 'Create', 'forminator' ), 'forminator-addon-next' ) .
			'</div>';

		return array(
			'html'         => Forminator_Integration::get_template( $template, $template_params ),
			'buttons'      => $buttons,
			'redirect'     => false,
			'has_errors'   => $has_errors,
			'has_back'     => true,
			'notification' => $notification,
			'is_close'     => $is_close,
			'size'         => 'normal',
		);
	}

	/**
	 * Check if select contact list completed
	 *
	 * @since 1.0 Google Sheets Integration
	 * @param array $submitted_data Submitted data.
	 * @return bool
	 */
	public function setup_sheet_is_completed( $submitted_data ) {
		return $this->if_properties_exist( $submitted_data, array( 'file_name', 'file_id' ) );
	}
}
