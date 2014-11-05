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
include_once  dirname(__FILE__) . "../../Vendor/CHttpRequest.php";
/**
 * Base class for all modules. Implements routine methods required by all modules.
 *
 * @category Domainmap
 * @package Module
 *
 * @since 4.0.0
 */
class Domainmap_Module {

	/**
	 * The instance of wpdb class.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 * @var wpdb
	 */
	protected $_wpdb = null;

	/**
	 * The plugin instance.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 * @var Domainmap_Plugin
	 */
	protected $_plugin = null;

	/**
	 * CHttpRequest class instance
	 *
	 * @since 4.2.0.4
	 *
	 * @var CHttpRequest
	 */
	protected $_http;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @global wpdb $wpdb Current database connection.
	 *
	 * @access public
	 * @param Domainmap_Plugin $plugin The instance of the plugin.
	 */
	public function __construct( Domainmap_Plugin $plugin ) {
		global $wpdb;

		$this->_wpdb = $wpdb;
		$this->_plugin = $plugin;
		$this->_http = new CHttpRequest();
		$this->_http->init();
	}

	/**
	 * Registers an action hook.
	 *
	 * @since 4.0.0
	 * @uses add_action() To register action hook.
	 *
	 * @access protected
	 * @param string $tag The name of the action to which the $method is hooked.
	 * @param string $method The name of the method to be called.
	 * @param int $priority optional. Used to specify the order in which the functions associated with a particular action are executed (default: 10). Lower numbers correspond with earlier execution, and functions with the same priority are executed in the order in which they were added to the action.
	 * @param int $accepted_args optional. The number of arguments the function accept (default 1).
	 * @return Domainmap_Module
	 */
	protected function _add_action( $tag, $method = '', $priority = 10, $accepted_args = 1 ) {
		add_action( $tag, array( $this, empty( $method ) ? $tag : $method ), $priority, $accepted_args );
		return $this;
	}

	/**
	 * Registers AJAX action hook.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $tag The name of the AJAX action to which the $method is hooked.
	 * @param string $method Optional. The name of the method to be called. If the name of the method is not provided, tag name will be used as method name.
	 * @param boolean $private Optional. Determines if we should register hook for logged in users.
	 * @param boolean $public Optional. Determines if we should register hook for not logged in users.
	 * @return Domainmap_Module
	 */
	protected function _add_ajax_action( $tag, $method = '', $private = true, $public = false ) {
		if ( $private ) {
			$this->_add_action( 'wp_ajax_' . $tag, $method );
		}

		if ( $public ) {
			$this->_add_action( 'wp_ajax_nopriv_' . $tag, $method );
		}

		return $this;
	}

	/**
	 * Registers a filter hook.
	 *
	 * @since 4.0.0
	 * @uses add_filter() To register filter hook.
	 *
	 * @access protected
	 * @param string $tag The name of the filter to hook the $method to.
	 * @param type $method The name of the method to be called when the filter is applied.
	 * @param int $priority optional. Used to specify the order in which the functions associated with a particular action are executed (default: 10). Lower numbers correspond with earlier execution, and functions with the same priority are executed in the order in which they were added to the action.
	 * @param int $accepted_args optional. The number of arguments the function accept (default 1).
	 * @return Domainmap_Module
	 */
	protected function _add_filter( $tag, $method = '', $priority = 10, $accepted_args = 1 ) {
		add_filter( $tag, array( $this, empty( $method ) ? $tag : $method ), $priority, $accepted_args );
		return $this;
	}

	/**
	 * Registers a hook for shortcode tag.
	 *
	 * @since 4.0.0
	 * @uses add_shortcode() To register shortcode hook.
	 *
	 * @access protected
	 * @param string $tag Shortcode tag to be searched in post content.
	 * @param string $method Hook to run when shortcode is found.
	 * @return Domainmap_Module
	 */
	protected function _add_shortcode( $tag, $method ) {
		add_shortcode( $tag, array( $this, $method ) );
		return $this;
	}


	protected function get_original_domain( $with_www = false ){
		$home = network_home_url( '/' );
		$original_domain = parse_url( $home, PHP_URL_HOST );
		return $with_www ? "www." . $original_domain : $original_domain ;
	}
	/**
	 * Checks if current site resides in original domain
	 *
	 * @since 4.2.0
	 *
	 * @return bool true if it's original domain, false if not
	 */
	protected function is_original_domain(){
		$current_domain = parse_url( $this->_http->hostinfo  , PHP_URL_HOST );
		return $current_domain === $this->get_original_domain() || strpos($current_domain, "." . $this->get_original_domain());
	}

	/**
	 * Checks if current site resides in mapped domain
	 *
	 * @since 4.2.0
	 *
	 * @return bool
	 */
	protected function is_mapped_domain(){
		return !$this->is_original_domain();
	}

	/**
	 * Checks if current page is login page
	 *
	 * @since 4.2.0
	 *
	 * @return bool
	 */
	protected function is_login(){
		return in_array( $GLOBALS['pagenow'], array( 'wp-login.php', 'wp-register.php' ));
	}

	/**
	 * Checks if give domain should be forced to use https
	 *
	 * @since 4.2.0
	 *
	 * @param string $domain
	 * @return bool
	 */
	public static function force_ssl_on_mapped_domain( $domain = "" ){
		global $wpdb;
		$current_domain = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'];
		$domain = $domain === "" ? $current_domain  : $domain;
		return (int) $wpdb->get_var( $wpdb->prepare("SELECT `scheme` FROM `" . DOMAINMAP_TABLE_MAP . "` WHERE `domain`=%s", $domain) );
	}

	/**
	 * Checks if server supports ssl
	 *
	 * @since 4.2.0.4
	 * @return bool
	 */
	protected  function server_supports_ssl(){
		$request = wp_remote_head(  $this->_http->getHostInfo("https") );
		if( is_wp_error( $request ) ){
			if( isset( $request->errors['http_request_failed'] ) && isset( $request->errors['http_request_failed'][0] ) && $request->errors['http_request_failed'][0] === "SSL: certificate verification failed (result: 5)")
				return true;

			return false;
		}else{
			return true;
		}

	}

	/**
	 * Checks if current domain is a subdomain
	 *
	 * @since 4.2.0.4
	 * @return bool
	 */
	protected function is_subdomain(){
		$network_domain =  parse_url( network_home_url(), PHP_URL_HOST );
		return  (bool) str_replace( $network_domain, "", $_SERVER['HTTP_HOST']);
	}

	/**
	 * Returns ajax url based on the main domain
	 *
	 * @since 4.2.0.4
	 * @return mixed
	 */
	protected function get_main_ajax_url(){
		return  $this->_replace_last_occurence('network/', '', network_admin_url( 'admin-ajax.php' ) );
	}

	/**
	 * Replaces last occurence of string with $replace string
	 *
	 * @since 4.2.0.5
	 *
	 * @param $search
	 * @param $replace
	 * @param $string
	 *
	 * @return mixed
	 */
	private function _replace_last_occurence($search, $replace, $string)
	{
		$pos = strrpos($string, $search);

		if($pos !== false)
			$string = substr_replace($string, $replace, $pos, strlen($search));

		return $string;
	}
}