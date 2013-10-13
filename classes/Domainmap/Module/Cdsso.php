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

	const KEY_DISABLE_CDSSO   = 'domainmapping_disable_cdsso';
	const KEY_AUTH_CDSSO      = 'domainmapping_auth_cdsso';
	const KEY_PROPAGATE_CDSSO = 'domainmapping_cdsso_propagate';

	/**
	 * CDSSO key
	 *
	 * @since 4.0.2
	 *
	 * @access private
	 * @var string
	 */
	private $_cdsso;

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

		// create CDSSO key, if it couldn't be done then don't activate the module
		$this->_cdsso = $this->_get_cdsso_key();
		if ( !$this->_cdsso ) {
			return;
		}

		$this->_add_action( 'plugins_loaded', 'check_authentication' );
		$this->_add_action( 'wp_login', 'set_cdsso_propagation', 10, 2 );

		if ( filter_input( INPUT_COOKIE, self::KEY_PROPAGATE_CDSSO ) ) {
			$this->_add_action( 'wp_enqueue_scripts', 'propagate_cdsso' );
			$this->_add_action( 'admin_enqueue_scripts', 'propagate_cdsso' );
			$this->_add_action( 'login_enqueue_scripts', 'propagate_cdsso' );
		}

		$this->_add_ajax_action( Domainmap_Plugin::ACTION_CDSSO_LOGIN, 'authorize_user', true, false );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_CDSSO_LOGIN, 'send_back_user', false, true );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_CDSSO_PROPAGATE, 'propagate_user', true, true );
	}

	/**
	 * Builds CDSSO key for current user.
	 *
	 * @since 4.0.2
	 *
	 * @access private
	 * @return string|boolean CDSSO key on success, otherwise FALSE.
	 */
	private function _get_cdsso_key() {
		if ( !isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}

		$flag = !WP_DEBUG ? FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE : null;
		$keys = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( array_filter( array_map( 'trim', explode( ',', $_SERVER[$key] ) ) ) as $ip ) {
					if ( filter_var( $ip, FILTER_VALIDATE_IP, $flag ) !== false ) {
						return 'domainmapping_cdsso_' . hash_hmac( 'sha256', $ip . $_SERVER['HTTP_USER_AGENT'], AUTH_SALT );
					}
				}
			}
		}

		return false;
	}

	/**
	 * Sets value for specific cookie.
	 *
	 * @since 4.0.2
	 *
	 * @access private
	 * @param string $cookie_name The name of the cookie to set.
	 * @param string $cookie_value The value of the cookie to set.
	 * @param int $expire The time when the cookie will be expired. Use 0 to set session cookie.
	 */
	private function _set_cookie( $cookie_name, $cookie_value, $expire = 0 ) {
		$secure = is_ssl();

		setcookie( $cookie_name, $cookie_value, $expire, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, $secure );
		setcookie( $cookie_name, $cookie_value, $expire, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure );
		setcookie( $cookie_name, $cookie_value, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
		if ( COOKIEPATH != SITECOOKIEPATH ) {
			setcookie( $cookie_name, $cookie_value, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure );
		}
	}

	/**
	 * Unsets cookie.
	 *
	 * @since 4.0.2
	 *
	 * @access private
	 * @param string $cookie_name The name of the cookie to unset.
	 */
	private function _unset_cookie( $cookie_name ) {
		$secure = is_ssl();
		$expire = time() - YEAR_IN_SECONDS;

		setcookie( $cookie_name, '', $expire, PLUGINS_COOKIE_PATH, COOKIE_DOMAIN, $secure );
		setcookie( $cookie_name, '', $expire, ADMIN_COOKIE_PATH, COOKIE_DOMAIN, $secure );
		setcookie( $cookie_name, '', $expire, COOKIEPATH, COOKIE_DOMAIN, $secure );
		if ( COOKIEPATH != SITECOOKIEPATH ) {
			setcookie( $cookie_name, '', $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure );
		}
	}

	/**
	 * Returns admin ajax URL for main site.
	 *
	 * @since 4.0.2
	 *
	 * @access private
	 * @return string The admin ajax URL for main site.
	 */
	private function _get_ajax_url() {
		$ajax_url = admin_url( 'admin-ajax.php' );
		if ( $this->_wpdb->blogid != 1 ) {
			switch_to_blog( 1 );
			$ajax_url = admin_url( 'admin-ajax.php' );
			restore_current_blog();
		}

		return $ajax_url;
	}

	/**
	 * Sets CDSSO propagation needs.
	 *
	 * @since 4.0.2
	 * @action wp_login
	 *
	 * @access public
	 * @param string $user_login Current user login name.
	 * @param WP_User $user Current user object.
	 */
	public function set_cdsso_propagation( $user_login, WP_User $user ) {
		if ( headers_sent() || $this->_wpdb->blogid == 1 ) {
			return;
		}

		$value = hash_hmac( 'sha256', $user_login . time(), AUTH_SALT );
		$this->_set_cookie( self::KEY_PROPAGATE_CDSSO, $value );
		update_user_meta( $user->ID, $this->_cdsso, $value );
	}

	/**
	 * Propagate CDSSO on the main site.
	 *
	 * @since 4.0.2
	 * @action wp_enqueue_scripts
	 * @access admin_enqueue_scripts
	 * @action login_enqueue_scripts
	 *
	 * @access public
	 */
	public function propagate_cdsso() {
		wp_enqueue_style( 'domainmapping-cdsso', add_query_arg( array(
			'action' => Domainmap_Plugin::ACTION_CDSSO_PROPAGATE,
			'item'   => $_COOKIE[self::KEY_PROPAGATE_CDSSO],
		), $this->_get_ajax_url() ), null, null );

		$this->_unset_cookie( self::KEY_PROPAGATE_CDSSO );
	}

	/**
	 * Checks user authentication and complete authorization if need be.
	 *
	 * @since 4.0.2
	 * @action plugins_loaded
	 *
	 * @access public
	 */
	public function check_authentication() {
		$doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$disable_cdsso = filter_input( INPUT_COOKIE, self::KEY_DISABLE_CDSSO, FILTER_VALIDATE_BOOLEAN );
		if ( is_user_logged_in() || $doing_ajax || $disable_cdsso || $this->_wpdb->blogid == 1 ) {
			return;
		}

		// disable CDSSO for current sesion if user is not logged in at main site
		if ( filter_input( INPUT_GET, self::KEY_DISABLE_CDSSO, FILTER_VALIDATE_BOOLEAN ) ) {
			$this->_set_cookie( self::KEY_DISABLE_CDSSO, 1 );
			wp_redirect( add_query_arg( self::KEY_DISABLE_CDSSO, false ) );
			exit;
		}

		// authenticate current user
		if ( filter_input( INPUT_GET, self::KEY_AUTH_CDSSO, FILTER_VALIDATE_BOOLEAN ) ) {
			$found = false;
			$key = trim( filter_input( INPUT_GET, 'user' ) );
			$value = trim( filter_input( INPUT_GET, 'salt' ) );
			if ( $key && $value ) {
				$query = new WP_User_Query( array(
					'meta_key'   => $key,
					'meta_value' => $value,
				) );

				if ( count( $query->results ) == 1 && is_user_member_of_blog( $query->results[0]->ID ) ) {
					$found = true;
					wp_set_auth_cookie( $query->results[0]->ID );
					delete_user_meta( $query->results[0]->ID, $key );
				}
			}

			if ( !$found ) {
				$this->_set_cookie( self::KEY_DISABLE_CDSSO, 1 );
			}

			wp_redirect( add_query_arg( array(
				self::KEY_AUTH_CDSSO => false,
				'user'               => false,
				'salt'               => false,
			) ) );
			exit;
		}

		// redirect to main site and try to get credentials
		wp_redirect( add_query_arg( array(
			'action'  => Domainmap_Plugin::ACTION_CDSSO_LOGIN,
			'blog_id' => $this->_wpdb->blogid,
			'backref' => urlencode( sprintf( '%s://%s%s', is_ssl() ? 'https' : 'http', $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ),
		), $this->_get_ajax_url() ) );
		exit;
	}

	/**
	 * Authorizes user and send back to entered blog to complete authorization.
	 *
	 * @since 4.0.2
	 *
	 * @access public
	 */
	public function authorize_user() {
		$user_id = get_current_user_id();
		if ( !is_user_member_of_blog( $user_id, filter_input( INPUT_GET, 'blog_id' ) ) ) {
			$this->send_back_user();
		}

		$key = hash( 'sha256', time() );
		$value = hash( 'sha1', time() . AUTH_SALT );
		add_user_meta( $user_id, $key, $value );

		wp_redirect( add_query_arg( array(
			self::KEY_AUTH_CDSSO => 'true',
			'user'               => $key,
			'salt'               => $value,
		), filter_input( INPUT_GET, 'backref', FILTER_VALIDATE_URL ) ) );
		exit;
	}

	/**
	 * User is not logged in and can't be authorized. Send back to entered domain.
	 *
	 * @since 4.0.2
	 *
	 * @access public
	 */
	public function send_back_user() {
		wp_redirect( add_query_arg( self::KEY_DISABLE_CDSSO, 'true', filter_input( INPUT_GET, 'backref', FILTER_VALIDATE_URL ) ) );
		exit;
	}

	/**
	 * Propagate user login on main site.
	 *
	 * @since 4.0.2
	 *
	 * @access public
	 */
	public function propagate_user() {
		$query = new WP_User_Query( array(
			'meta_key'   => $this->_cdsso,
			'meta_value' => filter_input( INPUT_GET, 'item' ),
		) );

		if ( count( $query->results ) == 1 && is_user_member_of_blog( $query->results[0]->ID ) ) {
			wp_set_auth_cookie( $query->results[0]->ID );
			delete_user_meta( $query->results[0]->ID, $this->_cdsso );
		}

		header( "Content-type: text/css" );
		echo "/* Sometimes me think what is love, and then me think love is what last cookie is for. Me give up the last cookie for you. */";
		exit;
	}

}