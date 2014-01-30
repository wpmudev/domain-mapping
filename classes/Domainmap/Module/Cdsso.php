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
 * The module responsible for cross domain single sign on.
 *
 * @category Domainmap
 * @package Module
 *
 * @since 4.0.2
 */
class Domainmap_Module_Cdsso extends Domainmap_Module {

	const NAME = __CLASS__;

	const ACTION_SETUP_CDSSO    = 'setup-cdsso';
	const ACTION_AUTHORIZE_USER = 'authorize-user';

	/**
	 * Constructor.
	 *
	 * @since 4.0.2
	 *
	 * @access public
	 * @param Domainmap_Plugin $plugin The instance of the plugin class.
	 */
	public function __construct( Domainmap_Plugin $plugin ) {
		parent::__construct( $plugin );

		$this->_add_action( 'wp_head', 'add_auth_script', 0 );
		$this->_add_action( 'plugins_loaded', 'authorize_user' );

		$this->_add_ajax_action( self::ACTION_SETUP_CDSSO, 'setup_cdsso', true, true );
	}

	/**
	 * Adds authorization script to the current page header.
	 *
	 * @since 4.1.2
	 * @action wp_head 0
	 *
	 * @access public
	 */
	public function add_auth_script() {
		if ( is_user_logged_in() || get_current_blog_id() == 1 || filter_input( INPUT_GET, 'action' ) == self::ACTION_AUTHORIZE_USER ) {
			return;
		}

		switch_to_blog( 1 );
		$url = add_query_arg( array( 'action' => self::ACTION_SETUP_CDSSO ), admin_url( 'admin-ajax.php' ) );
		echo '<script type="text/javascript" src="', $url, '"></script>';
		restore_current_blog();
	}

	/**
	 * Setups CDSSO for logged in user.
	 *
	 * @since 4.1.2
	 * @action wp_ajax_setup-cdsso
	 * @action wp_ajax_nopriv_setup-cdsso
	 *
	 * @access public
	 */
	public function setup_cdsso() {
		header( 'Content-Type: text/javascript' );

		if ( !is_user_logged_in() || empty( $_SERVER['HTTP_REFERER'] ) ) {
			exit;
		}

		$url = add_query_arg( array(
			'action' => self::ACTION_AUTHORIZE_USER,
			'auth'   => wp_generate_auth_cookie( get_current_user_id(), time() + MINUTE_IN_SECONDS ),
		), $_SERVER['HTTP_REFERER'] );

		echo 'window.location = "', $url, '";';
		exit;
	}

	/**
	 * Authorizes current user and redirects back to the original page.
	 *
	 * @since 4.1.2
	 * @action plugins_loaded
	 *
	 * @access public
	 */
	public function authorize_user() {
		if ( filter_input( INPUT_GET, 'action' ) == self::ACTION_AUTHORIZE_USER ) {
			$user_id = wp_validate_auth_cookie( filter_input( INPUT_GET, 'auth' ), 'auth' );
			if ( $user_id ) {
				wp_set_auth_cookie( $user_id );
				wp_redirect( add_query_arg( array( 'action' => false, 'auth' => false ) ) );
				exit;
			} else {
				wp_die( __( "Incorrect or out of date login key", 'domainmap' ) );
			}
		}
	}

}