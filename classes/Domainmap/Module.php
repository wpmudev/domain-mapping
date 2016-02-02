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
class Domainmap_Module extends domain_map{

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

		$this->_add_action("domainmapping_delete_mapped_domain", "delete_mapped_domain");
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



	/**
	 * Validates health status of a domain.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @param string $domain The domain name to validate.
	 * @return boolean TRUE if the domain name works, otherwise FALSE.
	 */
	protected function _validate_health_status( $domain ) {
		$check = sha1( time() );

		switch_to_blog( 1 );
        $scheme = self::utils()->get_mapped_domain_scheme( $domain );
		$ajax_url =  $scheme ?  set_url_scheme( admin_url( 'admin-ajax.php' ), $scheme ) : set_url_scheme( admin_url( 'admin-ajax.php' ), "http" );
		$ajax_url = str_replace( parse_url( $ajax_url, PHP_URL_HOST ), $domain, $ajax_url );
		restore_current_blog();
		$response = wp_remote_request( esc_url_raw( add_query_arg( array(
			'action' => Domainmap_Plugin::ACTION_HEARTBEAT_CHECK,
			'check'  => $check,
		), $ajax_url )), array( 'sslverify' => false ) );

		$status = !is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 && preg_replace('/\W*/', '', wp_remote_retrieve_body( $response ) ) == $check ? 1 : 0;
		$this->set_valid_transient( $domain, $status );
		return $status;
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
			if( isset( $request->errors['http_request_failed'] ) && isset( $request->errors['http_request_failed'][0] ) && ( strpos($request->errors['http_request_failed'][0], "SSL") !== false || strpos($request->errors['http_request_failed'][0], "ssl") !== false) )
				return true;

			return false;
		}else{
			return true;
		}

	}

	/**
	 * Returns ajax url based on the main domain
	 *
	 * @since 4.2.0.4
	 * @param string $scheme The scheme to use. Default is 'admin', which obeys force_ssl_admin() and is_ssl(). 'http' or 'https' can be passed to force those schemes.
	 * @return mixed
	 */
	protected function get_main_ajax_url( $scheme = 'admin'  ){
		return  $this->_replace_last_occurrence('network/', '', network_admin_url( 'admin-ajax.php', $scheme ) );
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
	private function _replace_last_occurrence($search, $replace, $string)
	{
		$pos = strrpos($string, $search);

		if($pos !== false)
			$string = substr_replace($string, $replace, $pos, strlen($search));

		return $string;
	}


	/**
	 * Checks to see if domain is valid, then sets appropriate transient and returns validity boolean
	 *
	 * @since 4.3.0
	 * @param $domain
	 * @param $status bool to set as domain's health status
	 *
	 * @return bool
	 */
	protected function set_valid_transient( $domain, $status = null ) {
        $valid = $status;
		if( is_null( $status ) ) {
			$valid = $this->_validate_health_status( $domain );
		}
		set_site_transient( "domainmapping-{$domain}-health", $valid, $valid ? 4 * WEEK_IN_SECONDS  : 10 * MINUTE_IN_SECONDS );

		return $valid;
	}


	/**
	 * Deletes a map domain
	 *
	 * @param $domain
	 * @return bool
	 */
	public function delete_mapped_domain($domain ){
		$result  = (bool) $this->_wpdb->delete( DOMAINMAP_TABLE_MAP, array( 'domain' => $domain ), array( '%s' ) );
		delete_transient( "domainmapping-{$domain}-health" );
		return $result;
	}
}