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
class Domainmap_Module_Ajax_Map extends Domainmap_Module_Ajax {

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
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_MAP_DOMAIN, 'map_domain' );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_UNMAP_DOMAIN, 'unmap_domain' );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_HEALTH_CHECK, 'check_health_status', true, true );
		$this->_add_ajax_action( Domainmap_Plugin::ACTION_HEARTBEAT_CHECK, 'check_heartbeat', false, true );
	}

	/**
	 * Returns count of mapped domains for current blog.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @return int The count of already mapped domains.
	 */
	private function _get_domains_count() {
		return $this->_wpdb->get_var( 'SELECT COUNT(*) FROM ' . DOMAINMAP_TABLE_MAP . ' WHERE blog_id = ' . intval( $this->_wpdb->blogid ) );
	}

	/**
	 * Maps new domain.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function map_domain() {
		self::_check_premissions( Domainmap_Plugin::ACTION_MAP_DOMAIN );

		$message = $hide_form = false;
		$domain = strtolower( trim( filter_input( INPUT_POST, 'domain' ) ) );
		if ( self::_validate_domain_name( $domain ) ) {

			// check if mapped domains are 0 or multi domains are enabled
			$count = $this->_get_domains_count();
			$allowmulti = defined( 'DOMAINMAPPING_ALLOWMULTI' );
			if ( $count == 0 || $allowmulti ) {

				// check if domain has not been mapped
				$blog = $this->_wpdb->get_row( $this->_wpdb->prepare( "SELECT blog_id FROM {$this->_wpdb->blogs} WHERE domain = %s AND path = '/'", $domain ) );
				$map = $this->_wpdb->get_row( $this->_wpdb->prepare( "SELECT blog_id FROM " . DOMAINMAP_TABLE_MAP . " WHERE domain = %s", $domain ) );

				if ( is_null( $blog ) && is_null( $map ) ) {
					$this->_wpdb->insert( DOMAINMAP_TABLE_MAP, array(
						'blog_id' => $this->_wpdb->blogid,
						'domain'  => $domain,
						'active'  => 1,
					), array( '%d', '%s', '%d' ) );

					if ( $this->_validate_health_status( $domain ) ) {
						// fire the action when a new domain is added
						do_action( 'domainmapping_added_domain', $domain, $this->_wpdb->blogid );

						// send success response
						ob_start();
						Domainmap_Render_Site_Map::render_mapping_row( $domain );
						wp_send_json_success( array(
							'html'      => ob_get_clean(),
							'hide_form' => !$allowmulti,
						) );
					} else {
						$this->_wpdb->delete( DOMAINMAP_TABLE_MAP, array( 'domain' => $domain ), array( '%s' ) );
						$message = sprintf(
							'<b>%s</b><br><small>%s</small>',
							__( 'Domain name is unavailable to access.', 'domainmap' ),
							__( "We can't access the new domain. If you've just purchased it, please, wait while it will be propagated. If it is already propagated, please, check your DNS records to be sure that the domain is configured correctly.", 'domainmap' )
						);
					}
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
	 */
	public function unmap_domain() {
		self::_check_premissions( Domainmap_Plugin::ACTION_UNMAP_DOMAIN );

		$show_form = false;
		$domain = strtolower( trim( filter_input( INPUT_GET, 'domain' ) ) );
		if ( self::_validate_domain_name( $domain ) ) {
			$this->_wpdb->delete( DOMAINMAP_TABLE_MAP, array( 'domain' => $domain ), array( '%s' ) );
			delete_transient( "domainmapping-{$domain}-health" );

			// check if we need to show form
			$show_form = $this->_get_domains_count() == 0 || defined( 'DOMAINMAPPING_ALLOWMULTI' );

			// fire the action when a domain is removed
			do_action( 'domainmapping_deleted_domain', $domain, $this->_wpdb->blogid );
		}

		wp_send_json_success( array( 'show_form' => $show_form ) );
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
	private function _validate_health_status( $domain ) {
		$check = sha1( time() );
		$ajax_url = admin_url( 'admin-ajax.php' );
		$ajax_url = str_replace( parse_url( $ajax_url, PHP_URL_HOST ), $domain, $ajax_url );

		$response = wp_remote_request( add_query_arg( array(
			'action' => Domainmap_Plugin::ACTION_HEARTBEAT_CHECK,
			'check'  => $check,
		), $ajax_url ) );

		return !is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) == 200 && wp_remote_retrieve_body( $response ) == $check ? 1 : 0;
	}

	/**
	 * Checks domain health status.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 */
	public function check_health_status() {
		self::_check_premissions( Domainmap_Plugin::ACTION_HEALTH_CHECK );

		$domain = strtolower( trim( filter_input( INPUT_GET, 'domain' ) ) );
		if ( !self::_validate_domain_name( $domain ) ) {
			wp_send_json_error();
		}

		$valid = $this->_validate_health_status( $domain );
		set_site_transient( "domainmapping-{$domain}-health", $valid, WEEK_IN_SECONDS );

		ob_start();
		Domainmap_Render_Site_Map::render_health_column( $domain );
		wp_send_json_success( array( 'html' => ob_get_clean() ) );
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