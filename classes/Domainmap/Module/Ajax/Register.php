<?php

// +----------------------------------------------------------------------+
// | Copyright Incsub (http://incsub.com/)                                |
// | Based on an original by Donncha (http://ocaoimh.ie/)                 |
// +----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License, version 2, as  |
// | published by the Free Software Foundation.                           |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to the Free Software          |
// | Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,               |
// | MA 02110-1301 USA                                                    |
// +----------------------------------------------------------------------+

/**
 * The module responsible for handling resellers accounts registrations.
 *
 * @category Domainmap
 * @package Module
 * @subpackage Ajax
 *
 * @since 4.1.0
 */
class Domainmap_Module_Ajax_Register extends Domainmap_Module_Ajax {

	const NAME = __CLASS__;

	/**
	 * Constructor.
	 *
	 * @since 4.1.0
	 *
	 * @access public
	 * @param Domainmap_Plugin $plugin The instance of the Domainap_Plugin class.
	 */
	public function __construct( Domainmap_Plugin $plugin ) {
		parent::__construct( $plugin );

		$this->_add_ajax_action( Domainmap_Plugin::ACTION_SHOW_REGISTRATION_FORM, 'render_registration_form' );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_SHOW_REGISTRATION_FORM, 'redirect_to_login_form', false, true );
		$this->_add_ajax_action( Domainmap_Reseller_WHMCS::ACTION_REGISTER_CLIENT, 'whmcs_register_client', true, false );
	}

	/**
	 * Checks SSL connection and user permissions before render or process
	 * registration form.
	 *
	 * @since 4.1.0
	 *
	 * @access private
	 * @param Domainmap_Reseller $reseller Current reseller.
	 */
	private function _check_ssl_and_security( $reseller ) {
		// check if user has permissions
		if ( !check_admin_referer( Domainmap_Plugin::ACTION_SHOW_REGISTRATION_FORM, 'nonce' ) || !current_user_can( 'manage_network_options' ) ) {
			status_header( 403 );
			exit;
		}

		// check if ssl connection is not used
		if ( $reseller->registration_over_ssl() && !is_ssl() ) {
			// ssl connection is not used, so if you logged in then redirect him
			// to https page, otherwise redirect him to login page
			$user_id = get_current_user_id();
			if ( $user_id ) {
				// propagate SSL auth cookie
				wp_set_auth_cookie( $user_id, true, true );

				// redirect to https version of this registration page
				wp_redirect( esc_url_raw( add_query_arg( array(
					'action'   => Domainmap_Plugin::ACTION_SHOW_REGISTRATION_FORM,
					'nonce'    => wp_create_nonce( Domainmap_Plugin::ACTION_SHOW_REGISTRATION_FORM ),
					'reseller' => filter_input( INPUT_GET, 'reseller' ),
				), admin_url( 'admin-ajax.php', 'https' ) ) ) );
				exit;
			} else {
				// redirect to login form
				$this->redirect_to_login_form();
			}
		}
	}

	/**
	 * Renders registration form.
	 *
	 * @since 4.1.0
	 *
	 * @access public
	 */
	public function render_registration_form() {
		// check reseller
		$reseller = filter_input( INPUT_GET, 'reseller' );
		$resellers = $this->_plugin->get_resellers();
		if ( !isset( $resellers[$reseller] ) ) {
			status_header( 404 );
			exit;
		}
		// check whether reseller supports accounts registration
		$reseller = $resellers[$reseller];
		if ( !$reseller->support_account_registration() ) {
			_default_wp_die_handler( __( "The reseller doesn't support account registration.", 'domainmap' ) );
		}

		// check ssl and security
		$this->_check_ssl_and_security( $reseller );

		// process post request
		if ( $_SERVER['REQUEST_METHOD'] == 'POST' && $reseller->regiser_account() ) {
			wp_redirect( esc_url_raw( add_query_arg( array(
				'page'       => 'domainmapping_options',
				'tab'        => 'reseller-options',
				'registered' => 'true',
			), network_admin_url( 'settings.php', 'http' ) ) ) );
			exit;
		}

        if( get_class($reseller) === "Domainmap_Reseller_WHMCS" ){
            call_user_func(array($reseller, 'render_registration_form'));
            wp_die();
        }

		define( 'IFRAME_REQUEST', true );

		// enqueue scripts
		wp_enqueue_script( 'jquery-payment' );
		wp_enqueue_script( 'domainmapping-admin' );

		// enqueue styles
		wp_enqueue_style( 'bootstrap-glyphs' );
		wp_enqueue_style( 'google-font-lato' );
		wp_enqueue_style( 'domainmapping-admin' );

		// render registration form
		wp_iframe( array( $reseller, 'render_registration_form' ) );
		wp_die();
	}

    private function _validate_whmcs_registration(){

    }
    function whmcs_register_client(){
        $data = $_POST['data'];
        $errors = new WP_Error();
        parse_str($data);
        $tld = $_POST['tld'];
        $sld = $_POST['sld'];
        /**
         * Validate
         */
        if( !is_email( $account_email ) ){
            $errors->add("email", __("Invalid email address"));
        }

        if( $account_password !== $account_password_confirm ){
            $errors->add("password", __("The passwords don't match"));
        }

        if( empty($account_password) ||  empty($account_password_confirm) ){
            $errors->add("password", __("Please provide a valid password"));
        }

        $object = Domainmap_Reseller_WHMCS::exec_command(Domainmap_Reseller_WHMCS::COMMAND_REGISTER_CLIENT, array(
            "firstname" => $registrant_first_name,
            "lastname" => $registrant_last_name,
            "companyname" => $registrant_organization,
            "email" => $account_email,
            "address1" => $registrant_address1,
            "city" => $registrant_city,
            "state" => $registrant_state,
            "postcode" => $registrant_zip,
            "country" => $registrant_country,
            "phonenumber" => $registrant_phone,
            "password2" => $account_password,
            "securityqid" => $account_question_type,
            "securityqans" => $account_question_answer
        ));

        if( count($errors->errors) > 0 ){
            wp_send_json_error( $errors->get_error_messages() );
        }
        if( !is_wp_error($object) ){
            $client_id_transient = sprintf( 'domainmap-%s-%s', get_current_user_id(), "whmcs_client_id" );
            set_site_transient( $client_id_transient, $object->clientid, HOUR_IN_SECONDS  );
            /**
             * @var $whmcs Domainmap_Reseller_WHMCS
             */
            $whmcs = $this->_plugin->get_reseller();

            wp_send_json_success( array(
                "html" =>  $whmcs->render_purchase_form( array(
                    "tld" => $tld,
                    "sld" => $sld,
                    "domain" => $sld . "." . $tld
                ) ),
                "clientid" =>  $object->clientid,
                "result" => $object->result
            ) );

        }else{
            wp_send_json_error( array(
                "message" => __("Error registering client account.", domain_map::Text_Domain),
                "errors" =>   $object->get_error_message()
            ) );
        }

        wp_die();
    }

}