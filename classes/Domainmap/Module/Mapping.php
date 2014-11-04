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
 * The module responsible for mapping domains.
 *
 * @category Domainmap
 * @package Module
 *
 * @since 4.0.3
 */
class Domainmap_Module_Mapping extends Domainmap_Module {

	const NAME = __CLASS__;

	/**
	 * The array of mapped domains.
	 *
	 * @since 4.1.0
	 *
	 * @access private
	 * @var array
	 */
	private static $_mapped_domains = array();

	/**
	 * The array of original domains.
	 *
	 * @since 4.1.0
	 *
	 * @access private
	 * @var array
	 */
	private static $_original_domains = array();

	/**
	 * Determines whether we need to suppress swapping or not.
	 *
	 * @since 4.1.0
	 *
	 * @access private
	 * @var boolean
	 */
	private $_suppress_swapping = false;

	/**
	 * Determines whether we need to force protocol on mapped domain or not.
	 *
	 * @since 4.1.0
	 *
	 * @access private
	 * @var boolean
	 */
	private static $_force_protocol = false;

	/**
	 * Constructor.
	 *
	 * @since 4.0.3
	 *
	 * @access public
	 * @param Domainmap_Plugin $plugin The current plugin.
	 */
	public function __construct( Domainmap_Plugin $plugin ) {
		parent::__construct( $plugin );

		self::$_force_protocol = defined( 'DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN' ) && filter_var( DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN, FILTER_VALIDATE_BOOLEAN );
		$this->_add_action( 'plugins_loaded', 'force_schema' );
//		add_action('plugins_loaded', '');
		$this->_add_action( 'template_redirect',       'redirect_front_area' );
		$this->_add_action( 'admin_init',              'redirect_admin_area' );
		$this->_add_action( 'login_init',              'redirect_login_area' );
		$this->_add_action( 'customize_controls_init', 'set_customizer_flag' );
		// URLs swapping
		$this->_add_filter( 'unswap_url', 'unswap_mapped_url' );
		if ( defined( 'DOMAIN_MAPPING' ) && filter_var( DOMAIN_MAPPING, FILTER_VALIDATE_BOOLEAN ) ) {
			$this->_add_filter( 'pre_option_siteurl', 'swap_root_url' );
			$this->_add_filter( 'pre_option_home',    'swap_root_url' );
			$this->_add_filter( 'home_url',           'swap_mapped_url', 10, 4 );
			$this->_add_filter( 'site_url',           'swap_mapped_url', 10, 4 );
			$this->_add_filter( 'includes_url',       'swap_mapped_url', 10, 2 );
			$this->_add_filter( 'content_url',        'swap_mapped_url', 10, 2 );
			$this->_add_filter( 'plugins_url',        'swap_mapped_url', 10, 3 );
		} elseif ( is_admin() ) {
			$this->_add_filter( 'home_url',           'swap_mapped_url', 10, 4 );
			$this->_add_filter( 'pre_option_home',    'swap_root_url' );
		}

		$this->_add_action("delete_blog", "on_delete_blog", 10, 2);
	}

	/**
	 * Retrieves frontend redirect type
	 *
	 * @since 4.0.3
	 * @return string redirect type: mapped, user, original
	 */
	private function _get_frontend_redirect_type() {
		return get_option( 'domainmap_frontend_mapping', 'mapped' );
	}

	/**
	 * Redirects to original domain.
	 *
	 * @since 4.1.0
	 *
	 * @access public
	 * @global object $current_blog Current blog object.
	 * @global object $current_site Current site object.
	 * @param book $force_ssl
	 */
	public function redirect_to_orig_domain( $force_ssl ) {
		global $current_blog, $current_site;

		// don't redirect AJAX requests
		if ( defined( 'DOING_AJAX' ) ) {
			return;
		}

		$protocol = is_ssl() || $force_ssl ? 'https://' : 'http://';

		$swapping = $this->_suppress_swapping;
		$this->_suppress_swapping = true;
		$url = get_option( 'siteurl' );
		$this->_suppress_swapping = $swapping;

		if ( $url && $url != untrailingslashit( $protocol . $current_blog->domain . $current_site->path ) ) {
			// strip out any subdirectory blog names
			$request = str_replace( "/a{$current_blog->path}", "/", "/a{$_SERVER['REQUEST_URI']}" );
			if ( $request != $_SERVER['REQUEST_URI'] ) {
				header( "HTTP/1.1 301 Moved Permanently", true, 301 );
				header( "Location: " . $url . $request, true, 301 );
			} else {
				header( "HTTP/1.1 301 Moved Permanently", true, 301 );
				header( "Location: " . $url . $_SERVER['REQUEST_URI'], true, 301 );
			}
			exit;
		}
	}

	/**
	 * Redirects to mapped or original domain.
	 *
	 * @since 4.1.0
	 *
	 * @access private
	 * @param string $redirect_to The direction to redirect to.
	 * @param bool $force_ssl
	 */
	private function _redirect_to_area( $redirect_to, $force_ssl = false ) {
		switch ( $redirect_to ) {
			case 'mapped':
				$this->redirect_to_mapped_domain( $force_ssl );
				break;
			case 'original':
				if ( defined( 'DOMAIN_MAPPING' ) ) {
					$this->redirect_to_orig_domain( $force_ssl );
				}
				break;
		}
	}

	/**
	 * Redirects admin area to mapped or original domain depending on options settings.
	 *
	 * @since 4.1.0
	 * @action admin_init
	 *
	 * @access public
	 */
	public function redirect_admin_area() {
		$force_ssl = $this->_get_current_mapping_type( 'map_admindomain' ) === 'original' ?  $this->_plugin->get_option("map_force_admin_ssl") : false;
		$this->_redirect_to_area( $this->_plugin->get_option( 'map_admindomain' ), $force_ssl );
	}

	/**
	 * Redirects login area to mapped or original domain depending on options settings.
	 *
	 * @since 4.1.0
	 * @action login_init
	 *
	 * @access public
	 */
	public function redirect_login_area() {
		if ( filter_input( INPUT_GET, 'action' ) != 'postpass' ) {
			$force_ssl = $this->_get_current_mapping_type( 'map_admindomain' ) === 'original'  ? $this->_plugin->get_option("map_force_admin_ssl") : false;
			$this->_redirect_to_area( $this->_plugin->get_option( 'map_logindomain' ), $force_ssl );
		}
	}

	/**
	 * Redirects frontend to mapped or original.
	 *
	 * @since 4.1.0
	 * @action template_redirect
	 *
	 * @access public
	 */
	public function redirect_front_area() {
		/**
		 * Filter if it should proceed with redirecting
		 *
		 * @since 4.1.0
		 * @param bool $is_ssl
		 */
		if( apply_filters( "dm_prevent_redirection_for_ssl", is_ssl() ) ) return;

		$redirect_to = $this->_get_frontend_redirect_type();
		$force_ssl = false;
		if ( filter_input( INPUT_POST, 'wp_customize', FILTER_VALIDATE_BOOLEAN ) ) {
			if ( $this->_get_current_mapping_type( 'map_admindomain' ) == 'original' ) {
				$redirect_to = 'original';
				$force_ssl = $this->_plugin->get_option("map_force_frontend_ssl");
			}
		}

		if ( $redirect_to != 'user' ) {
			$this->_redirect_to_area( $redirect_to, $force_ssl);
		}
	}

	/**
	 * Sets customizer flag which determines to not map URLs.
	 *
	 * @since 4.1.0
	 * @action customize_controls_init
	 *
	 * @access public
	 */
	public function set_customizer_flag() {
		$this->_suppress_swapping = $this->_get_current_mapping_type( 'map_admindomain' ) == 'original';
	}

	/**
	 * Returns current mapping type.
	 *
	 * @since 4.1.0
	 *
	 * @access private
	 * @param string $option The option name to check.
	 * @return string Mapping type.
	 */
	private function _get_current_mapping_type( $option ) {
		$mapping = $this->_plugin->get_option( $option );
		if ( $mapping != 'original' && $mapping != 'mapped' ) {
			$original = $this->_wpdb->get_var( sprintf(
				"SELECT option_value FROM %s WHERE option_name = 'siteurl'",
				$this->_wpdb->options
			) );

			if ( $original ) {
				$components = self::_parse_mb_url( $original );
				$mapping = isset( $components['host'] ) && $_SERVER['HTTP_HOST'] == $components['host']
					? 'original'
					: 'mapped';
			}
		}

		return $mapping;
	}

	/**
	 * Redirects to mapped domain.
	 *
	 * @since 4.0.3
	 *
	 * @param bool $force_ssl
	 * @access public
	 */
	public function redirect_to_mapped_domain( $force_ssl = false ) {
		global $current_blog, $current_site;

		// do not redirect if headers were sent or site is not permitted to use
		// domain mapping
		if ( headers_sent() || !$this->_plugin->is_site_permitted() ) {
			return;
		}

		// do not redirect if there is no mapped domain
		$mapped_domain = $this->_get_mapped_domain();
		if ( !$mapped_domain ) {
			return;
		}

		$protocol = is_ssl() || $force_ssl ? 'https://' : 'http://';
		$current_url = untrailingslashit( $protocol . $current_blog->domain . $current_site->path );
		$mapped_url = untrailingslashit( $protocol . $mapped_domain . $current_site->path );

		if ( strtolower( $mapped_url ) != strtolower( $current_url ) ) {
			// strip out any subdirectory blog names
			$request = str_replace( "/a" . $current_blog->path, "/", "/a" . $_SERVER['REQUEST_URI'] );
			if ( $request != $_SERVER['REQUEST_URI'] ) {
				header( "HTTP/1.1 301 Moved Permanently", true, 301 );
				header( "Location: " . $mapped_url . $request, true, 301 );
			} else {
				header( "HTTP/1.1 301 Moved Permanently", true, 301 );
				header( "Location: " . $mapped_url . $_SERVER['REQUEST_URI'], true, 301 );
			}
			exit;
		}
	}

	/**
	 * Returns mapped domain for current blog.
	 *
	 * @since 4.0.3
	 *
	 * @access private
	 * @param int $blog_id The id of a blog to get mapped domain for.
	 * @return string|boolean Mapped domain on success, otherwise FALSE.
	 */
	private function _get_mapped_domain( $blog_id = false ) {
		// use current blog id if $blog_id is empty
		if ( !$blog_id ) {
			$blog_id = get_current_blog_id();
		}

		// if we have already found mapped domain, then return it
		if ( isset( self::$_mapped_domains[$blog_id] ) ) {
			return self::$_mapped_domains[$blog_id];
		}

		$domain = '';
		if ( $this->_get_frontend_redirect_type() == 'user' ) {
			$domain = $_SERVER['HTTP_HOST'];
		} else {
			// fetch mapped domain
			$errors = $this->_wpdb->suppress_errors();
			$sql = filter_var( DOMAINMAPPING_ALLOWMULTI, FILTER_VALIDATE_BOOLEAN )
				? sprintf( "SELECT domain FROM %s WHERE blog_id = %d ORDER BY is_primary DESC, id ASC LIMIT 1", DOMAINMAP_TABLE_MAP, $blog_id )
				: sprintf( "SELECT domain FROM %s WHERE blog_id = %d ORDER BY id ASC LIMIT 1", DOMAINMAP_TABLE_MAP, $blog_id );
			$domain = $this->_wpdb->get_var( $sql );
			$this->_wpdb->suppress_errors( $errors );
		}

		// save mapped domain into local cache
		self::$_mapped_domains[$blog_id] = !empty( $domain ) ? $domain : false;

		return $domain;
	}

	/**
	 * Encodes URL component. This method is used in preg_replace_callback call.
	 *
	 * @since 4.1.0
	 * @see self::_parse_mb_url()
	 *
	 * @static
	 * @access private
	 * @param array $matches The array of matched elements.
	 * @return string
	 */
	private static function _parse_mb_url_urlencode( $matches ) {
		return urlencode( $matches[0] );
	}

	/**
	 * Parses a URL and returns an associative array containing any of the
	 * various components of the URL that are present. This implementation
	 * supports UTF-8 URLs and parses them properly.
	 *
	 * @since 4.1.0
	 * @link http://www.php.net/manual/en/function.parse-url.php#108787
	 *
	 * @static
	 * @access private
	 * @param string $url The URL to parse.
	 * @return array The array of URL components.
	 */
	private static function _parse_mb_url( $url ) {
		return array_map( 'urldecode', parse_url( preg_replace_callback( '%[^:/?#&=\.]+%usD', __CLASS__ . '::_parse_mb_url_urlencode', $url ) ) );
	}

	/**
	 * Builds URL from components received after parsing a URL.
	 *
	 * @since 4.1.0
	 *
	 * @static
	 * @access private
	 * @param array $components The array of URL components.
	 * @return string Built URL.
	 */
	private static function _build_url( $components ) {
		$scheme = isset( $components['scheme'] ) ? $components['scheme'] . '://' : '';
		$host = isset( $components['host'] ) ? $components['host'] : '';
		$port = isset( $components['port'] ) ? ':' . $components['port'] : '';

		$user = isset( $components['user'] ) ? $components['user'] : '';
		$pass = isset( $components['pass'] ) ? ':' . $components['pass'] : '';
		$pass = $user || $pass ? $pass . '@' : '';

		$path = isset( $components['path'] ) ? $components['path'] : '';
		$query = isset( $components['query'] ) ? '?' . $components['query'] : '';
		$fragment = isset( $components['fragment'] ) ? '#' . $components['fragment'] : '';
		return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
	}

	/**
	 * Swaps URL from original to mapped one.
	 *
	 * @since 4.1.0
	 * @filter home_url 10 4
	 * @filter site_url 10 4
	 * @filter includes_url 10 2
	 * @filter content_url 10 2
	 * @filter plugins_url 10 3
	 *
	 * @access public
	 * @param string $url Current URL to swap.
	 * @param string $path The path related to domain.
	 * @param type $orig_scheme The scheme to use 'http', 'https', or 'relative'.
	 * @param string $blog_id The blog ID to which URL is related to.
	 */
	public function swap_mapped_url( $url, $path = false, $orig_scheme = false, $blog_id = false ) {
		// do not swap URL if customizer is running
		if ( $this->_suppress_swapping ) {
			return $url;
		}

		// parse current url
		$components = self::_parse_mb_url( $url );
		if ( empty( $components['host'] ) ) {
			return $url;
		}

		// find mapped domain
		$mapped_domain = $this->_get_mapped_domain( $blog_id );
		if ( !$mapped_domain || $components['host'] == $mapped_domain ) {
			return $url;
		}

		$components['host'] = $mapped_domain;

		return self::_build_url( $components );
	}


	/**
	 * Returns swapped root URL.
	 *
	 * @since 4.1.0
	 * @filter pre_option_home
	 * @filter pre_option_siteurl
	 *
	 * @access public
	 * @global object $current_site The current site object.
	 * @param string $url The current root URL.
	 * @return string Swapped root URL on success, otherwise inital value.
	 */
	public function swap_root_url( $url ) {
		global $current_site;

		// do not swap URL if customizer is running or front end redirection is disabled
		if ( $this->_suppress_swapping ) {
			return $url;
		}

		$domain = $this->_get_mapped_domain();
		if ( !$domain ) {
			return $url;
		}

		$protocol = 'http://';
		if ( self::$_force_protocol && is_ssl() ) {
			$protocol = 'https://';
		}

		return untrailingslashit( $protocol . $domain . $current_site->path );
	}

	/**
	 * Unswaps URL to use original domain.
	 *
	 * @since 4.1.0
	 * @filter unswap_url
	 *
	 * @access public
	 * @global object $current_site Current site object.
	 * @param string $url Current URL to unswap.
	 * @param int $blog_id The blog ID to which current URL is related to.
	 * @param bool $include_path whether to include the url path
	 * @return string Unswapped URL.
	 */
	public function unswap_mapped_url( $url, $blog_id = false, $include_path = true ) {
		global $current_site, $wpdb;

		// if no blog id is passed, then take current one
		if ( !$blog_id ) {
			$blog_id = get_current_blog_id();
		}

		// check if we have already found original domain for the blog
		if ( !array_key_exists( $blog_id, self::$_original_domains ) ) {
			self::$_original_domains[$blog_id] = $wpdb->get_var( sprintf(
				"SELECT option_value FROM %s WHERE option_name = 'siteurl'",
				$wpdb->options
			) );
		}

		if ( empty( self::$_original_domains[$blog_id] ) ) {
			return $url;
		}

		$url_components = self::_parse_mb_url( $url );
		$orig_components = self::_parse_mb_url( self::$_original_domains[$blog_id] );

		if ( self::$_force_protocol ) {
			$url_components['scheme'] = is_ssl() ? 'htts' : 'http';
		}

		$url_components['host'] = $orig_components['host'];

		$orig_path = isset( $orig_components['path'] ) ? $orig_components['path'] : '';
		$url_path = isset( $url_components['path'] ) && $include_path ? $url_components['path'] : '';

		$url_components['path'] = str_replace( '//', '/', $current_site->path . $orig_path . $url_path );
		return self::_build_url( $url_components );
	}

	/**
	 * Retrieves original domain from the given mapped_url
	 *
	 * @since 4.1.3
	 * @access public
	 *
	 * @usces self::unswap_mapped_url()
	 *
	 * @param $mapped_url
	 * @param bool $blog_id
	 * @param bool $include_path
	 * @return string
	 */
	public static function unswap_url( $mapped_url, $blog_id = false, $include_path = true ){
		return self::unswap_mapped_url( $mapped_url, $blog_id, $include_path );
	}



	/**
	 * Forces ssl in different areas of the site based on user choice
	 *
	 * @since 4.2
	 *
	 * @uses force_ssl_admin
	 * @uses force_ssl_login
	 * @uses wp_redirect
	 */
	public function force_schema(){

		if( $this->is_original_domain() && !is_ssl()  ){
			/**
			 * Login and Admin pages
			 */

			force_ssl_admin( $this->_plugin->get_option("map_force_admin_ssl") );
			force_ssl_login( $this->_plugin->get_option("map_force_admin_ssl") );
		}

		$current_url = $this->_http->getHostInfo("http") . $this->_http->getUrl();
		$current_url_secure = $this->_http->getHostInfo("https") . $this->_http->getUrl();
		$force_schema = true;
		/**
		 * Filters if schema should be forced
		 *
		 * @since 4.2.0.4
		 *
		 * @param bool $force_schema
		 * @param bool $current_url current page http url
		 * @param bool $current_url_secure current page https url
		 */
		if( !apply_filters("dm_forcing_schema", $force_schema, $current_url, $current_url_secure) ) return;

		/**
		 * Force original domain
		 */
		if(  !$this->is_login() && !is_admin() && $this->is_original_domain()){

			// Force http
			if(  $this->_plugin->get_option("map_force_frontend_ssl") === 1  && is_ssl()  ){
				wp_redirect( $current_url );
				exit();
			}

			// Force https
			if(  $this->_plugin->get_option("map_force_frontend_ssl") === 2 &&  $this->is_original_domain() && !is_ssl()){
				wp_redirect( $current_url_secure  );
				exit();
			}

		}

		/**
		 * Force mapped domains
		 */
		if(  $this->is_mapped_domain() && self::force_ssl_on_mapped_domain() !== 2 ){
			if( self::force_ssl_on_mapped_domain() === 1 && !is_ssl()  ){ // force https
				wp_redirect( $current_url_secure  );
				exit();
			}elseif( self::force_ssl_on_mapped_domain() === 0 && is_ssl() ){ //force http
				wp_redirect( $current_url);
				exit();
			}
		}

	}

	function on_delete_blog( $blog_id, $drop){
		$this->_wpdb->delete(DOMAINMAP_TABLE_MAP, array( "blog_id" => $blog_id ) , array( "%d" ) );
	}

}