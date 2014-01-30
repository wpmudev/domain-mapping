<?php

// +----------------------------------------------------------------------+
// | Copyright Incsub (http://incsub.com/)                                |
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
	const ACTION_PROPAGATE_USER = 'propagate-user';

	/**
	 * Determines whether we need to propagate user to the original blog or not.
	 *
	 * @since 4.1.2
	 *
	 * @access private
	 * @var boolean
	 */
	private $_do_propagation = false;

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

		$this->_add_filter( 'login_redirect', 'set_interim_login', 10, 3 );
		$this->_add_filter( 'login_message', 'get_login_message' );

		$this->_add_action( 'wp_head', 'add_auth_script', 0 );
		$this->_add_action( 'login_footer', 'add_propagation_script' );
		$this->_add_action( 'plugins_loaded', 'authorize_user' );

		$this->_add_ajax_action( self::ACTION_SETUP_CDSSO, 'setup_cdsso', true, true );
		$this->_add_ajax_action( self::ACTION_PROPAGATE_USER, 'propagate_user', true, true );
	}

	/**
	 * Sets internim login mode.
	 *
	 * @since 4.1.2
	 * @filter login_redirect 10 3
	 *
	 * @access public
	 * @global boolean $interim_login Determines whether to show interim login page or not.
	 * @param string $redirect_to The redirection URL.
	 * @param string $requested_redirect_to The initial redirection URL.
	 * @param WP_User|WP_Error $user The user or error object.
	 * @return string The income redirection URL.
	 */
	public function set_interim_login( $redirect_to, $requested_redirect_to, $user ) {
		global $interim_login;
		if ( is_a( $user, 'WP_User' ) && get_current_blog_id() != 1 ) {
			$interim_login = true;
			$this->_do_propagation = true;
		}

		return $redirect_to;
	}

	/**
	 * Updates login message for interim login page.
	 *
	 * @since 4.1.2
	 * @filter login_message
	 *
	 * @access public
	 * @return string The new extended login message.
	 */
	public function get_login_message() {
		if ( !$this->_do_propagation ) {
			return;
		}

		return '<p class="message">' . esc_html__( 'You have logged in successfully. You will be redirected to desired page during next 5 seconds.', 'domainmap' ) . '</p>';
	}

	/**
	 * Adds propagation scripts at interim login page after successfull login.
	 *
	 * @since 4.1.2
	 * @access login_footer
	 *
	 * @access public
	 * @global string $redirect_to The redirection URL.
	 * @global WP_User $user Current user.
	 */
	public function add_propagation_script() {
		global $redirect_to, $user;

		if ( !$this->_do_propagation ) {
			return;
		}

		if ( ( empty( $redirect_to ) || $redirect_to == 'wp-admin/' || $redirect_to == admin_url() ) ) {
			// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
			if ( is_multisite() && !get_active_blog_for_user( $user->ID ) && !is_super_admin( $user->ID ) ) {
				$redirect_to = user_admin_url();
			} elseif ( is_multisite() && !$user->has_cap( 'read' ) ) {
				$redirect_to = get_dashboard_url( $user->ID );
			} elseif ( !$user->has_cap( 'edit_posts' ) ) {
				$redirect_to = admin_url( 'profile.php' );
			}
		}

		echo '<script type="text/javascript">';
			echo 'function domainmap_do_redirect() { window.location = "', $redirect_to, '"; };';
			echo 'setTimeout(domainmap_do_redirect, 5000);';
		echo '</script>';


		switch_to_blog( 1 );
		$url = add_query_arg( array(
			'action' => self::ACTION_PROPAGATE_USER,
			'auth'   => wp_generate_auth_cookie( $user->ID, time() + MINUTE_IN_SECONDS ),
		), admin_url( 'admin-ajax.php' ) );
		restore_current_blog();

		echo '<script type="text/javascript" src="', $url, '"></script>';
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
		$url = add_query_arg( 'action', self::ACTION_SETUP_CDSSO, admin_url( 'admin-ajax.php' ) );
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
		header( "Content-Type: text/javascript; charset=" . get_bloginfo( 'charset' ) );

		if ( !is_user_logged_in() || empty( $_SERVER['HTTP_REFERER'] ) ) {
			header( "Vary: Accept-Encoding" ); // Handle proxies
			header( "Expires: " . gmdate( "D, d M Y H:i:s", time() + MINUTE_IN_SECONDS ) . " GMT" );
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

	/**
	 * Propagates user to the network root block.
	 *
	 * @since 4.1.2
	 * @action wp_ajax_propagate-user
	 * @action wp_ajax_nopriv_propagate-user
	 *
	 * @access public
	 */
	public function propagate_user() {
		header( "Content-Type: text/javascript; charset=" . get_bloginfo( 'charset' ) );

		if ( get_current_blog_id() == 1 ) {
			$user_id = wp_validate_auth_cookie( filter_input( INPUT_GET, 'auth' ), 'auth' );
			if ( $user_id ) {
				wp_set_auth_cookie( $user_id );
				echo 'if (typeof domainmap_do_redirect === "function") domainmap_do_redirect();';
				exit;
			}
		}

		exit;
	}

}