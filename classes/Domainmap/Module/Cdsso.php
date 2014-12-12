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

	const ACTION_KEY = '__domainmap_action';

	const ACTION_SETUP_CDSSO    = 'domainmap-setup-cdsso';
	const ACTION_AUTHORIZE_USER = 'domainmap-authorize-user';
	const ACTION_PROPAGATE_USER = 'domainmap-propagate-user';
	const ACTION_LOGOUT_USER    = 'domainmap-logout-user';

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
	 * Determines whether we do logout process or not.
	 *
	 * @since 4.1.2
	 *
	 * @access private
	 * @var boolean
	 */
	private $_do_logout = false;

	/**
	 * Whether to load the sso scripts in footer
	 *
	 * @since 4.2.1
	 *
	 * @access private
	 * @var bool
	 */
	private $_load_in_footer = false;

	/**
	 * Whether to load the sso scripts asynchronously
	 *
	 * @since 4.2.1
	 *
	 * @access private
	 * @var bool
	 */
	private $_async = false;

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

		$this->_load_in_footer =  $plugin->get_option("map_crossautologin_infooter");
		$this->_async =  $plugin->get_option("map_crossautologin_async");

		$this->_add_filter( 'wp_redirect', 'add_logout_marker' );
		$this->_add_filter( 'login_redirect', 'set_interim_login', 10, 3 );
		$this->_add_filter( 'login_message', 'get_login_message' );
		$this->_add_filter( 'login_url', 'update_login_url', 10, 2 );



		$this->_add_action( $this->_load_in_footer ?  'wp_footer' :  'wp_head', 'add_auth_script', 0 );

		$this->_add_action( 'login_form_login', 'set_auth_script_for_login' );
		$this->_add_action( 'wp_head', 'add_logout_propagation_script', 0 );
		$this->_add_action( 'login_head', 'add_logout_propagation_script', 0 );
		$this->_add_action( 'login_footer', 'add_propagation_script' );
		$this->_add_action( 'wp_logout', 'set_logout_var' );
		$this->_add_action( 'plugins_loaded', 'authorize_user' );

		$this->_add_ajax_action( self::ACTION_SETUP_CDSSO, 'setup_cdsso', true, true );
		$this->_add_ajax_action( self::ACTION_PROPAGATE_USER, 'propagate_user', true, true );
		$this->_add_ajax_action( self::ACTION_LOGOUT_USER, 'logout_user', true, true );

	}

	/**
	 * Adds hook for login_head action if user tries to login.
	 *
	 * @since 4.1.2
	 * @action login_form_login
	 *
	 * @access public
	 */
	public function set_auth_script_for_login() {
		$this->_add_action( $this->_load_in_footer ? "login_footer" : 'login_head', 'add_auth_script', 0 );
	}

	/**
	 * Equalizes redirect_to domain name with login URL domain.
	 *
	 * @since 4.1.2.1
	 * @filter login_url 10 2
	 *
	 * @param string $login_url The login URL.
	 * @param string $redirect_to The redirect URL.
	 * @return string Updated login URL.
	 */
	public function update_login_url( $login_url, $redirect_to ) {
        if( empty( $redirect_to ) )
            return $login_url;

		$login_domain = parse_url( $login_url, PHP_URL_HOST );
		$redirect_domain = parse_url( $redirect_to, PHP_URL_HOST );
		if ( $login_domain != $redirect_domain ) {
			$redirect_to = str_replace( "://{$redirect_domain}", "://{$login_domain}", $redirect_to );
			$login_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $login_url );
		}

		return $login_url;
	}

	/**
	 * Sets logout var to determine logout process.
	 *
	 * @since 4.1.2
	 * @access wp_logout
	 *
	 * @access public
	 */
	public function set_logout_var() {
		$this->_do_logout = true;
	}

	/**
	 * Adds logout marker if need be.
	 *
	 * @since 4.1.2
	 * @filter wp_redirect
	 *
	 * @access public
	 * @param string $redirect_to The initial redirect URL.
	 * @return string Updated redirect URL.
	 */
	public function add_logout_marker( $redirect_to ) {
		if ( $this->_do_logout ) {
			$redirect_to = add_query_arg( self::ACTION_KEY, self::ACTION_LOGOUT_USER, $redirect_to );
		}

		return $redirect_to;
	}

	/**
	 * Adds logout propagation script if need be.
	 *
	 * @since 4.1.2
	 * @action wp_head 0
	 * @action login_head 0
	 *
	 * @access public
	 */
	public function add_logout_propagation_script() {
		if ( is_user_logged_in() || get_current_blog_id() == 1 || filter_input( INPUT_GET, self::ACTION_KEY ) != self::ACTION_LOGOUT_USER ) {
			return;
		}

		$url = add_query_arg( 'action', self::ACTION_LOGOUT_USER, $this->get_main_ajax_url() );
		$this->_add_script( $url );
	}

	/**
	 * Do logout from the main blog.
	 *
	 * @since 4.1.2
	 * @action wp_ajax_domainmap-logout-user
	 * @action wp_ajax_no_priv_domainmap-logout-user
	 *
	 * @access public
	 */
	public function logout_user() {
		header( "Content-Type: text/javascript; charset=" . get_bloginfo( 'charset' ) );

		if ( !is_user_logged_in() || empty( $_SERVER['HTTP_REFERER'] ) ) {
			header( "Vary: Accept-Encoding" ); // Handle proxies
			header( "Expires: " . gmdate( "D, d M Y H:i:s", time() + MINUTE_IN_SECONDS ) . " GMT" );
			exit;
		}

		wp_clear_auth_cookie();
		$url = add_query_arg( self::ACTION_KEY, false, $_SERVER['HTTP_REFERER'] );

		echo 'window.location = "', $url, '";';
		exit;
	}

	/**
	 * Sets interim login mode.
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
			$home = home_url( '/' );
			$current_domain = parse_url( $home, PHP_URL_HOST );
			$original_domain = parse_url( apply_filters( 'unswap_url', $home ), PHP_URL_HOST );
			if ( $current_domain != $original_domain || $this->is_subdomain() ) {
				$interim_login = $this->_do_propagation = true;
			}
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
	 * @param string $message The original message.
	 * @return string The new extended login message.
	 */
	public function get_login_message( $message ) {
		return $this->_do_propagation
			? '<p class="message">' . esc_html__( 'You have logged in successfully. You will be redirected to desired page during next 5 seconds.', 'domainmap' ) . '</p>'
			: $message;
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
		?>
		<script <?php echo $this->_async ? "async='true'" : ""; ?>  type="text/javascript">
			function domainmap_do_redirect() { window.location = "<?php echo $redirect_to ?>"; }
			setTimeout(domainmap_do_redirect, 5000);
		</script>

		<?php

		$url = add_query_arg( array(
			'action' => self::ACTION_PROPAGATE_USER,
			'auth'   => wp_generate_auth_cookie( $user->ID, time() + MINUTE_IN_SECONDS ),
		), $this->get_main_ajax_url() );

		$this->_add_script( $url );

	}

	/**
	 * Adds authorization script to the current page header.
	 *
	 * @since 4.1.2
	 * @action wp_head 0
	 * @action login_head 0
	 *
	 * @access public
	 */
	public function add_auth_script() {

		if (   is_user_logged_in() ||  1  === get_current_blog_id() || filter_input( INPUT_GET, self::ACTION_KEY ) == self::ACTION_AUTHORIZE_USER ) {
			return;
		}
		$url = add_query_arg( 'action', self::ACTION_SETUP_CDSSO, $this->get_main_ajax_url() );
		$this->_add_script( $url );
	}

	/**
	 * Setups CDSSO for logged in user.
	 *
	 * @since 4.1.2
	 * @action wp_ajax_domainmap-setup-cdsso
	 * @action wp_ajax_nopriv_domainmap-setup-cdsso
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
			self::ACTION_KEY => self::ACTION_AUTHORIZE_USER,
			'auth'           => wp_generate_auth_cookie( get_current_user_id(), time() + MINUTE_IN_SECONDS ),
		), $_SERVER['HTTP_REFERER'] );
		?>
		window.location.replace("<?php echo $url ?>");
		<?php
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
		if ( filter_input( INPUT_GET, self::ACTION_KEY ) == self::ACTION_AUTHORIZE_USER ) {
          $user_id = wp_validate_auth_cookie( filter_input( INPUT_GET, 'auth' ), 'auth' );
			if ( $user_id ) {
				wp_set_auth_cookie( $user_id );

				$redirect_to = in_array( $GLOBALS['pagenow'], array( 'wp-login.php' ) ) && filter_input( INPUT_GET, 'redirect_to', FILTER_VALIDATE_URL )
					? $_GET['redirect_to']
					: add_query_arg( array( self::ACTION_KEY => false, 'auth' => false ) );

				wp_redirect( $redirect_to );
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
	 * @action wp_ajax_domainmap-propagate-user
	 * @action wp_ajax_nopriv_domainmap-propagate-user
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

	/**
	 * Prints async javascript script
	 *
	 * @since 4.2.1
	 *
	 * @access private
	 * @param $url
	 */
	private function _add_script( $url )
	{
		if( $this->_async ):
		?>
		<script type="text/javascript">
			(function(d, t) {
				var g = d.createElement(t),
					s = d.getElementsByTagName(t)[0];
				g.src = '<?php echo $url ?>';
				g.async = true;
				s.parentNode.insertBefore(g, s);
			}(document, 'script'));
		</script>
		<?php
		else:
		?>
			<script type="text/javascript" src="<?php echo $url; ?>"></script>
		<?php
		endif;
	}


}