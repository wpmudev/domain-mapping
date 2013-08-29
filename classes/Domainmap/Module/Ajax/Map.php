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
 * The module responsible for handling AJAX requests sent at domain mapping page.
 *
 * @category Domainmap
 * @package Module
 * @subpackage Ajax
 *
 * @since 4.0.0
 */
class Domainmap_Module_Ajax_Map extends Domainmap_Module {

	const NAME = __CLASS__;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param Domainmap_Plugin $plugin The instance of the Domainap_Plugin class.
	 */
	public function __construct( Domainmap_Plugin $plugin ) {
		parent::__construct( $plugin );

		// add ajax actions
		$this->_add_ajax_action( 'domainmapping_map_domain', 'map_domain' );
		$this->_add_ajax_action( 'domainmapping_unmap_domain', 'unmap_domain' );
		$this->_add_ajax_action( 'domainmapping_check_health', 'check_health_status', true, true );
		$this->_add_ajax_action( 'domainmapping_heartbeat_check', 'check_heartbeat', false, true );
	}

	/**
	 * Validates domain name.
	 *
	 * @since 4.0.0
	 * @link http://stackoverflow.com/questions/1755144/how-to-validate-domain-name-in-php/4694816#4694816
	 *
	 * @static
	 * @access private
	 * @param string $domain The domain name to validate.
	 * @return boolean TRUE if domain name is valid, otherwise FALSE.
	 */
	private static function _validate_domain_name( $domain ) {
		return preg_match( "/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain ) //valid chars check
			&& preg_match( "/^.{1,253}$/", $domain ) //overall length check
			&& preg_match( "/^[^\.]{2,63}(\.[^\.]{2,63})+$/", $domain ); //length of each label
	}

	/**
	 * Checks user permissions and block AJAX request if they don't match.
	 *
	 * @since 4.0.0
	 * @uses status_header() To set response HTTP code.
	 *
	 * @static
	 * @access private
	 * @param type $ajax_action
	 * @param type $credentials
	 */
	private static function _check_premissions( $ajax_action, $credentials = 'manage_options' ) {
		// check if request has been made via jQuery
		if ( empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) || strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) != 'xmlhttprequest' ) {
			status_header( 404 );
			exit;
		}

		// check if user has permissions
		if ( !check_admin_referer( $ajax_action, 'nonce' ) || !current_user_can( $credentials ) ) {
			status_header( 403 );
			exit;
		}
	}

	/**
	 * Returns count of mapped domains for current blog.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @global domain_map $dm_map The instance of the domain_map class.
	 * @return type
	 */
	private function _get_domains_count() {
		global $dm_map;

		return $this->_wpdb->get_var( sprintf(
			"SELECT COUNT(*) FROM %s WHERE blog_id = %d",
			$dm_map->dmtable,
			$this->_wpdb->blogid
		) );
	}

	/**
	 * Maps new domain.
	 *
	 * @since 4.0.0
	 * @uses check_admin_referer() To avoid security exploits.
	 * @uses current_user_can() To check user permissions.
	 *
	 * @access public
	 * @global domain_map $dm_map The instance of the domain_map class.
	 */
	public function map_domain() {
		global $dm_map;

		self::_check_premissions( 'domainmapping_map_domain' );

		$message = $hide_form = false;
		$domain = strtolower( trim( filter_input( INPUT_POST, 'domain' ) ) );
		if ( self::_validate_domain_name( $domain ) ) {

			// check if mapped domains are 0 or multi domains are enabled
			$count = $this->_get_domains_count();
			$allowmulti = defined( 'DOMAINMAPPING_ALLOWMULTI' );
			if ( $count == 0 || $allowmulti ) {

				// check if domain has not been mapped
				$escaped_domain = esc_sql( $domain );
				$blog = $this->_wpdb->get_row( sprintf( "SELECT blog_id FROM %s WHERE domain = '%s' AND path = '/'", $this->_wpdb->blogs, $escaped_domain ) );
				$map = $this->_wpdb->get_row( sprintf( "SELECT blog_id FROM %s WHERE domain = '%s'", $dm_map->dmtable, $escaped_domain ) );

				if( is_null( $blog ) && is_null( $map ) ) {
					$this->_wpdb->insert( $dm_map->dmtable, array(
						'blog_id' => $this->_wpdb->blogid,
						'domain'  => $domain,
						'active'  => 1,
					), array( '%d', '%s', '%d' ) );

					// fire the action when a new domain is added
					do_action( 'domainmapping_added_domain', $domain, $this->_wpdb->blogid );

					// send success response
					ob_start();
					Domainmap_Render_Page_Site::render_mapping_row( $domain );
					wp_send_json_success( array(
						'html'      => ob_get_clean(),
						'hide_form' => !$allowmulti,
					) );
				} else {
					$message = __( 'Domain is already mapped.', 'domainmap' );
				}
			} else {
				$message = __( 'Multiple domains are not allowed.', 'domainmap' );
				$hide_form = true;
			}
		} else {
			$message = __( 'Domain name is invalid.', 'domainmap' );
		}

		wp_send_json_error( array(
			'message'   => $message,
			'hide_form' => $hide_form,
		) );
	}

	/**
	 * Unmaps domain.
	 *
	 * @since 4.0.0
	 * @uses check_admin_referer() To avoid security exploits.
	 * @uses current_user_can() To check user permissions.
	 *
	 * @access public
	 * @global domain_map $dm_map The instance of the domain_map class.
	 */
	public function unmap_domain() {
		global $dm_map;

		self::_check_premissions( 'domainmapping_unmap_domain' );

		$show_form = false;
		$domain = strtolower( trim( filter_input( INPUT_GET, 'domain' ) ) );
		if ( self::_validate_domain_name( $domain ) ) {
			$this->_wpdb->delete( $dm_map->dmtable, array( 'domain' => $domain ), array( '%s' ) );
			delete_transient( "domainmapping-{$domain}-health" );

			// check if we need to show form
			$show_form = $this->_get_domains_count() == 0 || defined( 'DOMAINMAPPING_ALLOWMULTI' );

			// fire the action when a domain is removed
			do_action( 'domainmapping_deleted_domain', $domain, $this->_wpdb->blogid );
		}

		wp_send_json_success( array( 'show_form' => $show_form ) );
	}

	/**
	 * Checks domain health status.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function check_health_status() {
		self::_check_premissions( 'domainmapping_check_health' );

		$domain = strtolower( trim( filter_input( INPUT_GET, 'domain' ) ) );
		if ( !self::_validate_domain_name( $domain ) ) {
			wp_send_json_error();
		}

		$check = sha1( time() );
		$ajax_url = admin_url( 'admin-ajax.php' );
		$ajax_url = str_replace( parse_url( $ajax_url, PHP_URL_HOST ), $domain, $ajax_url );
		$request = wp_remote_request( add_query_arg( array(
			'action' => 'domainmapping_heartbeat_check',
			'check'  => $check,
		), $ajax_url ) );

		$valid = !is_wp_error( $request ) && isset( $request['response']['code'] ) && $request['response']['code'] == 200 && isset( $request['body'] ) && $request['body'] == $check ? 1 : 0;
		set_transient( "domainmapping-{$domain}-health", $valid, WEEK_IN_SECONDS );

		ob_start();
		Domainmap_Render_Page_Site::render_health_column( $domain );
		wp_send_json_success( array(
			'html' => ob_get_clean(),
		) );
	}

	/**
	 * Checks heartbeat of the domain.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function check_heartbeat() {
		echo filter_input( INPUT_GET, 'check' );
		exit;
	}

}