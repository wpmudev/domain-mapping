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
			_default_wp_die_handler( __( 'The reseller doesn\'t support account registration.', 'domainmap' ) );
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

    
}