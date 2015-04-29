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
	 * @const key for url get param
	 *
	 * @since 4.3.0
	 *
	 */
	const BYPASS = "bypass";
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
	 * Determines whether we need to force ssl frontend of original domain
	 *
	 * @since 4.3.1
	 *
	 * @var bool|mixed
	 */
	private static $_force_front_ssl = false;

	/**
	 * Determines whether we need to force ssl admin of original domain
	 *
	 * @since 4.3.1
	 *
	 * @var bool|mixed
	 */
	private static $_force_admin_ssl = false;

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

		self::$_force_front_ssl = $this->_plugin->get_option("map_force_frontend_ssl");
		self::$_force_admin_ssl = $this->_plugin->get_option("map_force_admin_ssl");

		$this->_add_action( 'template_redirect',       'redirect_front_area', 10 );
		$this->_add_action( 'template_redirect',       'force_page_exclusion', 11 );
		$this->_add_action( 'template_redirect',       'force_schema', 12 );
		$this->_add_action( 'admin_init',              'force_admin_scheme', 12 );
		$this->_add_action( 'login_init',              'force_admin_scheme', 12 );
		$this->_add_action( 'admin_init',              'redirect_admin_area' );
		$this->_add_action( 'login_init',              'redirect_login_area' );
		$this->_add_action( 'wp_logout',               'redirect_logged_out' );
		$this->_add_action( 'customize_controls_init', 'set_customizer_flag' );

		$this->_add_filter("page_link",                 'exclude_page_links', 10, 3);
		$this->_add_filter("page_link",                 'ssl_force_page_links', 11, 3);
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
		$this->_add_filter("preview_post_link", "post_preview_link_from_original_domain_to_mapped_domain", 10, 2);
		$this->_add_filter( 'customize_allowed_urls', "customizer_allowed_urls" );
		$this->_add_filter( 'logout_url', "filter_logout_url", 10, 2 );

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
	 * @param bool $force_ssl
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
	 * @param bool $is_front is it related to frontend.
	 */
	private function _redirect_to_area( $redirect_to, $force_ssl = false, $is_front = true ) {

		/**
		 * Don't map if this page is exluded from mapping
		 */
		global $post;

		if( isset( $post ) && $this->is_excluded_by_id( $post->ID ) ) return;

		if( $this->is_excluded_by_request() ) return;

		switch ( $redirect_to ) {
			case 'mapped':
				$this->redirect_to_mapped_domain( $force_ssl, $is_front );
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
		$this->_redirect_to_area( $this->_plugin->get_option( 'map_admindomain' ), $force_ssl, false );
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

		if(  filter_input( INPUT_GET, 'dm' ) ===  self::BYPASS || filter_input( INPUT_GET, 'action' ) === "logout" || filter_input( INPUT_GET, 'loggedout' ) === "true"  ) return;

		if ( filter_input( INPUT_GET, 'action' ) != 'postpass' ) {
			$force_ssl = $this->_get_current_mapping_type( 'map_admindomain' ) === 'original'  ? $this->_plugin->get_option("map_force_admin_ssl") : false;
			$this->_redirect_to_area( $this->_plugin->get_option( 'map_logindomain' ), $force_ssl, false );
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

		if(  filter_input( INPUT_GET, 'dm' ) ===  self::BYPASS ) return;

		$redirect_to = $this->_get_frontend_redirect_type();
		$force_ssl = false;
		if ( filter_input( INPUT_POST, 'wp_customize', FILTER_VALIDATE_BOOLEAN ) ) {
			if ( $this->_get_current_mapping_type( 'map_admindomain' ) == 'original' ) {
				$redirect_to = 'original';
				$force_ssl = $this->_plugin->get_option("map_force_frontend_ssl");
			}
		}

		/**
		 * Filter if it should proceed with redirecting
		 *
		 * @since 4.1.0
		 * @param bool $is_ssl
		 */
		if( apply_filters( "dm_prevent_redirection_for_ssl", is_ssl() && $redirect_to !== "mapped"  ) ) return;

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

		return apply_filters("dm_current_mapping_type", $mapping, $option);
	}


	/**
	 * Redirects to mapped domain.
	 *
	 * @since 4.0.3
	 *
	 * @param bool $force_ssl
	 * @param bool $is_front is it related to frontend
	 * @access public
	 *
	 * @return void
	 */
	public function redirect_to_mapped_domain( $force_ssl = false, $is_front = true ) {
		global $current_blog, $current_site;

		/**
		 * do not redirect if headers were sent or site is not permitted to use domain mapping
		 */
		if ( headers_sent() || !$this->_plugin->is_site_permitted()  ) {
			return;
		}



		$mapped_domain = $this->_get_mapped_domain(false, $is_front);
		// do not redirect if there is no mapped domain
		if ( !$mapped_domain ) {
			return;
		}


		$map_check_health = $this->_plugin->get_option("map_check_domain_health");
		if( $map_check_health ){
			// Don't map if mapped domain is not healthy
			$health =  get_site_transient( "domainmapping-{$mapped_domain}-health" );
			if( $health !== "1"){
				if( !$this->set_valid_transient($mapped_domain)  ) return true;
			}
		}

		$protocol_bool = $this->is_original_domain() ? self::force_ssl_on_mapped_domain() : is_ssl();
		$protocol = $protocol_bool || $force_ssl ? 'https://' : 'http://';
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
	 * @param int|bool $blog_id The id of a blog to get mapped domain for.
	 * @param bool $is_front is it related to frontend
	 * @return string|boolean Mapped domain on success, otherwise FALSE.
	 */
	private function _get_mapped_domain( $blog_id = false, $is_front = true ) {
		// use current blog id if $blog_id is empty
		if ( !$blog_id ) {
			$blog_id = get_current_blog_id();
		}

		// if we have already found mapped domain, then return it
		if ( isset( self::$_mapped_domains[$blog_id] ) ) {
			return self::$_mapped_domains[$blog_id];
		}

		$domain = '';
		if ( $is_front && $this->_get_frontend_redirect_type() == 'user'  ) {
			$domain = is_admin() && $this->is_original_domain() ? $domain : $_SERVER['HTTP_HOST'];
		} else {
			// fetch mapped domain
			$domain = $this->_fetch_mapped_domain( $blog_id );
		}

		// save mapped domain into local cache
		self::$_mapped_domains[$blog_id] = !empty( $domain ) ? $domain : false;

		return apply_filters("dm_mapped_domain", $domain, $blog_id, $is_front);
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
		return array_map( 'urldecode', (array) parse_url( preg_replace_callback( '%[^:/?#&=\.]+%usD', __CLASS__ . '::_parse_mb_url_urlencode', $url ) ) );
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

		$url = $scheme . str_replace("//", "/", $user . $pass . $host . $port . $path . $query . $fragment );
		return apply_filters("dm_built_url", $url, $components) ;
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
	 * @param $url
	 * @param bool $path
	 * @param bool $orig_scheme
	 * @param bool $blog_id
	 *
	 * @return string
	 */
	public function swap_mapped_url( $url, $path = false, $orig_scheme = false, $blog_id = false ) {
		global $current_site, $current_blog;
		// do not swap URL if customizer is running
		if ( $this->_suppress_swapping ) {
			return $url;
		}

		// parse current url
		$components = self::_parse_mb_url( $url );

		if ( empty( $components['host'] ) || $this->is_excluded_by_url( $url ) ) {
			return apply_filters("dm_swap_mapped_url", $url, $path, $orig_scheme, $blog_id);
		}


		// find mapped domain
		$mapped_domain = $this->_get_mapped_domain( $blog_id );
		if ( !$mapped_domain || $components['host'] == $mapped_domain ) {
			return apply_filters("dm_swap_mapped_url", $url, $path, $orig_scheme, $blog_id);
		}

		$components['host'] = $mapped_domain;
		$components['path'] = "/" . $path;

		return apply_filters("dm_swap_mapped_url", self::_build_url( $components ), $path, $orig_scheme, $blog_id);
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
		global $current_site, $current_blog;

		// do not swap URL if customizer is running or front end redirection is disabled
		if ( $this->_suppress_swapping ) {
			return apply_filters("dm_swap_root_url", $url);
		}

		$domain = $this->_get_mapped_domain();
		if ( !$domain ){
			return apply_filters("dm_swap_root_url", $url);
		}

		$protocol = 'http://';
		if ( self::force_ssl_on_mapped_domain( $domain ) && is_ssl() ) {
			$protocol = 'https://';
		}

		$destination = untrailingslashit( $protocol . $domain  . $current_site->path );

		if ( $this->is_excluded_by_url( $url ) ) {
			$_url = $current_site->domain . $current_blog->path .$current_site->path;
			$destination = untrailingslashit( $protocol .  str_replace("//", "/", $_url) );
		}

		return apply_filters("dm_swap_root_url", $destination);
	}

	/**
	 * Retrieves original domain from the given mapped_url
	 *
	 * @since 4.1.3
	 * @access public
	 *
	 * @uses self::unswap_url()
	 *
	 * @param $url
	 * @param bool $blog_id
	 * @param bool $include_path
	 * @return string
	 */
	public function unswap_mapped_url( $url, $blog_id = false, $include_path = true ) {
		return self::unswap_url( $url, $blog_id, $include_path );
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
	 * @param int|bool|null $blog_id The blog ID to which current URL is related to.
	 * @param bool $include_path whether to include the url path
	 * @return string Unswapped URL.
	 */
	public static function unswap_url( $url, $blog_id = false, $include_path = true ){
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

		$url_components['scheme'] = self::$_force_front_ssl ? 'https' : 'http';

		if ( strpos($url, "/wp-admin") !== false && strpos($url, $include_path) !== false ) {
			$url_components['scheme'] = self::$_force_admin_ssl ? 'https' : 'http';
		}

		$url_components['host'] = $orig_components['host'];

		$orig_path = isset( $orig_components['path'] ) ? $orig_components['path'] : '';
		$url_path = isset( $url_components['path'] ) && $include_path ? $url_components['path'] : '';

		$url_components['path'] = str_replace( '//', '/', $current_site->path . $orig_path . $url_path );
		$unwapped_url = self::_build_url( $url_components );

		return apply_filters("dm_unswaped_url", $unwapped_url, $url, $blog_id, $include_path ) ;
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
		global $post;

		$post_id = isset( $post ) ? $post->ID : null;

		if( filter_input( INPUT_GET, 'dm' ) ===  self::BYPASS ) return;

		do_action("dm_before_force_schema");

		$current_url = $this->_http->getHostInfo("http") . $this->_http->getUrl();
		$current_url = apply_filters("dm_force_schema_current_url", $current_url);
		$current_url_secure = $this->_http->getHostInfo("https") . $this->_http->getUrl();
		$current_url_secure = apply_filters("dm_force_schema_current_secure_url", $current_url_secure);
		$force_schema = apply_filters("dm_force_schema", true, $current_url, $current_url_secure);

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
		 * Force single page
		 */
		if( !is_admin() && ( $this->is_ssl_forced_by_id( $post_id ) || $this->is_ssl_forced_by_request() ) && !is_ssl() ){
			wp_redirect( $current_url_secure  );
			exit();
		}elseif(  $this->is_mapped_domain() && self::force_ssl_on_mapped_domain() !== 2 && !( $this->is_ssl_forced_by_id( $post_id ) || $this->is_ssl_forced_by_request() ) ){
			/**
			 * Force mapped domains
			 */
			if( self::force_ssl_on_mapped_domain() === 1 && !is_ssl()  ){ // force https
				wp_redirect( $current_url_secure  );
				exit();
			}elseif( self::force_ssl_on_mapped_domain() === 0 && is_ssl() ){ //force http
				wp_redirect( $current_url);
				exit();
			}
		}




	}

	/**
	 * Forces scheme in admin|login of original domain
	 *
	 * @since 4.2
	 *
	 * @uses force_ssl_admin
	 * @uses force_ssl_login
	 * @uses wp_redirect
	 */
	function force_admin_scheme(){
		do_action("dm_before_force_admin_schema");
		$force_admin_schema = apply_filters("dm_force_admin_schema", true,  $this->_http->getUrl());
		if( $force_admin_schema && $this->is_original_domain() && !is_ssl() && $this->_plugin->get_option("map_force_admin_ssl") && ( is_admin() || $this->is_login() ) ){
			$current_url_secure = $this->_http->getHostInfo("https") . $this->_http->getUrl();
			wp_redirect( $current_url_secure );
		}
	}

	/**
	 * Removes mapping record from db when a site is deleted
	 *
	 * Since 4.2.0
	 * @param $blog_id
	 * @param $drop
	 */
	function on_delete_blog( $blog_id, $drop){
		$this->_wpdb->delete(DOMAINMAP_TABLE_MAP, array( "blog_id" => $blog_id ) , array( "%d" ) );
	}


	/**
	 * Makes sure post preview is shown even if the admin uses original or entered domain and the frontend is supposed
	 * to use mapped domain
	 *
	 * @since 4.3.0
	 *
	 * @param $url
	 * @param $post
	 *
	 * @return string
	 */
	function post_preview_link_from_original_domain_to_mapped_domain($url, $post = null){
		$url_fragments = parse_url( $url );
		$hostinfo = $url_fragments['scheme'] . "://" . $url_fragments['host'];
		if( $hostinfo !== $this->_http->hostInfo ){
			return esc_url_raw( add_query_arg(array("dm" => self::BYPASS ),   set_url_scheme($this->unswap_mapped_url( $url  ), is_ssl() ) ) );
		}

		return $url;
	}

	/**
	 * Adds mapped domain to customizer's allowed urls so
	 * @param $allowed_urls
	 *
	 * @return array
	 */
	function customizer_allowed_urls( $allowed_urls ){

		if( self::$_mapped_domains === array() ) return $allowed_urls;

		$mapped_urls = array();

		foreach( self::$_mapped_domains as $domain ){
			if(  !empty( $domain ) ){
				$mapped_urls[] = "http://" . $domain;
				$mapped_urls[] = "https://" . $domain;
			}
		}

		return array_merge($allowed_urls, $mapped_urls);
	}

	/**
	 * Returns excluded pages
	 *
	 * @since 4.3.0
	 *
	 * @param bool $return_array weather it should return array or or string of comma separated ids
	 *
	 * @return array|mixed|void
	 */
	public static function get_excluded_pages( $return_array = false ){
		$excluded_pages = get_option( "dm_excluded_pages", "");
		if( $return_array ){
			return $excluded_pages === "" ? array() :  array_map("intval", array_map("trim", explode(",", $excluded_pages)) );
		}

		$excluded_pages = $excluded_pages === "" ? false : $excluded_pages;
		return apply_filters("dm_excluded_pages", $excluded_pages);
	}

	/**
	 * Returns excluded page urls
	 *
	 * @since 4.3.0
	 *
	 * @param bool $return_array weather it should return array or or string of comma separated ids
	 *
	 * @return array|mixed|void
	 */
	public static function get_excluded_page_urls( $return_array = false ){
		global $current_blog;
		$excluded_page_urls = trim( get_option( "dm_excluded_page_urls", "") );

		if( empty(  $excluded_page_urls   ) ) return $return_array ? array() : "";

		if( $return_array ){
			if( $excluded_page_urls === "" )
				return array();

			$urls = array_map("trim", explode(",", $excluded_page_urls));

			$parseds = array_map("parse_url", $urls);
			$paths = array();

			foreach( $parseds as $parsed ){
				if( isset( $parsed['path'] ) ){
					$path =  ltrim( untrailingslashit( str_replace("//", "/", $parsed['path']) ), '/\\' );
					$replacee = ltrim( $current_blog->path, '/\\');
					$paths[] = str_replace($replacee, "", $path);
				}

			}
			return $paths;
		}

		return apply_filters("dm_excluded_pages_url", $excluded_page_urls, $return_array);
	}

	/**
	 * Returns excluded page urls
	 *
	 * @since 4.3.0
	 *
	 * @param bool $return_array weather it should return array or or string of comma separated ids
	 *
	 * @return array|mixed|void
	 */
	public static function get_ssl_forced_page_urls( $return_array = false ){
		global $current_blog;
		$ssl_forced_page_urls =  trim( get_option( "dm_ssl_forced_page_urls", "") );

		if( empty(  $ssl_forced_page_urls   ) ) return $return_array ? array() : "";

		if( $return_array ){

			$urls = array_map("trim", explode(",", $ssl_forced_page_urls));
			$parseds = array_map("parse_url", $urls);
			$paths = array();
			foreach( $parseds as $parsed ){
				if( isset( $parsed['path'] ) ){
					$path =  ltrim( untrailingslashit( str_replace("//", "/", $parsed['path']) ), '/\\' );
					$replacee = ltrim( $current_blog->path, '/\\');
					$paths[] = str_replace($replacee, "", $path);
				}

			}
			return $paths;
		}

		return apply_filters("dm_ssl_forced_page_urls", $ssl_forced_page_urls, $return_array);
	}

	/**
	 * Returns ssl forced pages
	 *
	 * @since 4.3.0
	 *
	 * @param bool $return_array weather it should return array or or string of comma separated ids
	 *
	 * @return array|mixed|void
	 */
	public static function get_ssl_forced_pages( $return_array = false ){
		$forced_pages = get_option( "dm_ssl_forced_pages", "");
		if( $return_array ){
			$forced_pages =  $forced_pages == "" ? array() :  array_map("intval", array_map("trim", explode(",", $forced_pages)) );
		}

		return apply_filters("dm_ssl_forced_pages", $forced_pages, $return_array);
	}

	/**
	 * Checks to see if the given page should be excluded from mapping
	 *
	 * @since 4.3.0
	 *
	 * @param $post_id int | null
	 *
	 * @return bool
	 */
	function is_excluded_by_id( $post_id ){
		if( is_null( $post_id ) ) return apply_filters("dm_is_excluded_by_id", false);
		return apply_filters("dm_is_excluded_by_url", in_array( $post_id, self::get_excluded_pages( true )  ), $post_id);
	}

	/**
	 * Checks if the given url is ( should be ) excluded
	 *
	 * @since 4.3.0
	 *
	 * @param $url
	 *
	 * @return bool
	 */
	function is_excluded_by_url( $url ){
		$excluded_ids =  self::get_excluded_pages( true );

		if( empty( $url ) || !$excluded_ids ) return false;

		$permalink_structure = get_option("permalink_structure");
		$comps = parse_url( $url );
		if( empty( $permalink_structure ) )
		{
			if( isset( $comps['query'] ) && $query = $comps['query'] ){
				foreach( $excluded_ids as $excluded_id ){
					if( $query === "page_id=" . $excluded_id ) return apply_filters("dm_is_excluded_by_url", true, $url);
				}
			}

			return apply_filters("dm_is_excluded_by_url", false, $url);
		}



		if( isset( $comps['path'] ) && $path = $comps['path'] )
		{
			foreach( $excluded_ids as $excluded_id ){
				$post = get_post( $excluded_id );

				if( strrpos( $path, $post->post_name ) ) return apply_filters("dm_is_excluded_by_url", true, $url);
			}
		}


		return apply_filters("dm_is_excluded_by_url", false, $url);
	}


	function is_excluded_by_request(){
		global $wp;

		if( !isset( $wp ) || !isset( $wp->request ) ) return apply_filters("dm_is_excluded_by_request", false);
		return apply_filters("dm_is_excluded_by_request", in_array( $wp->request, $this->get_excluded_page_urls(true) ));
	}

	function is_ssl_forced_by_request(){
		global $wp;

		if( !isset($wp) || !isset( $wp->request ) ) return apply_filters("dm_is_ssl_forced_by_request", false);
		return apply_filters("dm_is_ssl_forced_by_request", in_array( $wp->request, $this->get_ssl_forced_page_urls(true) ));
	}

	/**
	 * Excludes page permalinks
	 *
	 * @since 4.3.0
	 *
	 * @param $permalink
	 * @param $post_id
	 * @param $leavename
	 *
	 * @return string
	 */
	function exclude_page_links( $permalink, $post_id, $leavename  ){

		$exclude = apply_filters("dm_exclude_page_links", true);

		if(!$exclude || empty($post_id) || $this->is_original_domain( $permalink ) ) return $permalink;


		if( $this->is_excluded_by_id( $post_id) ){
			return $this->unswap_url( $permalink );
		}
		return $permalink;
	}

	/**
	 * Forces excluded pages to land on the main domain
	 *
	 * @since 4.3.0
	 */
	function force_page_exclusion(){
		global $post;
		$post_id = isset( $post ) ? $post->ID : null;

		if( $this->is_mapped_domain()  &&  ( $this->is_excluded_by_id( $post_id ) || $this->is_excluded_by_request() ) ){
			$current_url = is_ssl() ? $this->_http->getHostInfo("https") . $this->_http->getUrl() : $this->_http->getHostInfo("http") . $this->_http->getUrl();
			$current_url = $this->unswap_url( $current_url );
			wp_redirect( $current_url );
			die;
		}
	}

	/**
	 * Checks to see if the given page should be forced to https
	 *
	 * @since 4.3.0
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	function is_ssl_forced_by_id( $post_id ){
		if( is_null( $post_id ) ) return apply_filters("dm_is_ssl_forced_by_id", false);
		return apply_filters("dm_is_ssl_forced_by_id",  in_array( $post_id, self::get_ssl_forced_pages( true )  ), $post_id );
	}


	/**
	 * SSL force page permalinks
	 *
	 * @since 4.3.0
	 *
	 * @param $permalink
	 * @param $post_id
	 * @param $leavename
	 *
	 * @return string
	 */
	function ssl_force_page_links( $permalink, $post_id, $leavename  ){

		if( empty( $post_id )) return $permalink;

		if( $this->is_ssl_forced_by_id( $post_id ) ){
			$permalink = set_url_scheme( $permalink, "https" ) ;
		}
		return apply_filters("dm_ssl_force_page_links", $permalink, $post_id, $leavename);
	}

	/**
	 * Fetches mapped domain from the db
	 *
	 * @since 4.3.1
	 * @param $blog_id
	 *
	 * @return null|string
	 */
	private function _fetch_mapped_domain( $blog_id ) {
		$errors = $this->_wpdb->suppress_errors();
		$sql    = domain_map::allow_multiple()
			? sprintf( "SELECT domain FROM %s WHERE blog_id = %d ORDER BY is_primary DESC, id ASC LIMIT 1", DOMAINMAP_TABLE_MAP, $blog_id )
			: sprintf( "SELECT domain FROM %s WHERE blog_id = %d ORDER BY id ASC LIMIT 1", DOMAINMAP_TABLE_MAP, $blog_id );
		$domain = $this->_wpdb->get_var( $sql );
		$this->_wpdb->suppress_errors( $errors );

		return apply_filters("dm_fetch_mapped_domain", $domain, $blog_id);
	}

	/**
	 * Force logout url to logout the site on current domain
	 *
	 * @param $logout_url
	 * @param $redirect
	 *
	 * @since 4.3.1
	 * @return string
	 */
	function filter_logout_url( $logout_url, $redirect  ){
		if( $this->is_mapped_domain() ){
			$logout_url = $this->swap_mapped_url( $logout_url, "wp-login.php" );
		}
		return apply_filters("dm_filter_logout_url", $logout_url, $redirect);
	}

	/**
	 * After logout redirects user to mapped or original domain depending on options settings.
	 *
	 * @since 4.3.1
	 * @action wp_logout
	 * @access public
	 */
	public function redirect_logged_out() {
		$force_ssl = $this->_get_current_mapping_type( 'map_admindomain' ) === 'original'  ? $this->_plugin->get_option("map_force_admin_ssl") : false;
		$this->_redirect_to_area( $this->_plugin->get_option( 'map_logindomain' ), $force_ssl, false );
	}
}