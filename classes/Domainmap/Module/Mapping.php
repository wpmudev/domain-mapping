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
	 * Constructor.
	 *
	 * @since 4.0.3
	 *
	 * @access public
	 * @param Domainmap_Plugin $plugin The current plugin.
	 */
	public function __construct( Domainmap_Plugin $plugin ) {
		parent::__construct( $plugin );
		$this->_add_action( 'template_redirect', 'redirect_to_mapped_domain' );
	}

	/**
	 * Redirects to mapped domain.
	 *
	 * @since 4.0.3
	 * @action template_redirect
	 *
	 * @access public
	 */
	public function redirect_to_mapped_domain() {
		global $current_blog, $current_site;

		// do not redirect if headers were sent
		if ( headers_sent() || !$this->_plugin->is_site_permitted() ) {
			return;
		}

		// do redirect
		$protocol = is_ssl() ? 'https://' : 'http://';
		$url = $this->_get_mapped_url();
		if ( $url && $url != untrailingslashit( $protocol . $current_blog->domain . $current_site->path ) ) {
			// strip out any subdirectory blog names
			$request = str_replace( "/a" . $current_blog->path, "/", "/a" . $_SERVER['REQUEST_URI'] );
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
	 * Returns mapped url for current domain.
	 *
	 * @since 4.0.3
	 *
	 * @access private
	 * @global object $current_site Current site information object.
	 * @return string|boolean Mapped URL on success, otherwise FALSE.
	 */
	private function _get_mapped_url() {
		global $current_site;

		$suppress_errors = $this->_wpdb->suppress_errors();

		$protocol = defined( 'DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN' ) && filter_var( DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN, FILTER_VALIDATE_BOOLEAN ) && is_ssl()
			? 'https://'
			: 'http://';

		$domain = defined( 'DOMAINMAPPING_ALLOWMULTI' ) && filter_var( DOMAINMAPPING_ALLOWMULTI, FILTER_VALIDATE_BOOLEAN )
			? $this->_get_mapped_domain_with_multi()
			: $this->_get_mapped_domain_without_multi();

		$this->_wpdb->suppress_errors( $suppress_errors );

		return !empty( $domain )
			? untrailingslashit( $protocol . $domain . $current_site->path )
			: false;
	}

	/**
	 * Returns proper domain name mapped to this blog in case when multi mapping is enabled.
	 *
	 * @since 4.0.3
	 *
	 * @access private
	 * @return string Proper domain name if mapping exists, otherwise FALSE.
	 */
	private function _get_mapped_domain_with_multi() {
		$domains = $this->_wpdb->get_results( sprintf( "SELECT domain, is_primary FROM %s WHERE blog_id = %d ORDER BY id ASC", DOMAINMAP_TABLE_MAP, $this->_wpdb->blogid ) );
		if ( empty( $domains ) ) {
			// we don't have any mapped domains, so return FALSE
			return false;
		}

		$primaries = wp_list_filter( $domains, array( 'is_primary' => 1 ) );
		if ( empty( $primaries ) ) {
			// no primary domain is selected, so return FALSE
			return false;
		}

		// check if we have current host in the list of primaries, if not, then return first primary domain
		return count( wp_list_filter( $primaries, array( 'domain' => $_SERVER['HTTP_HOST'] ) ) ) == 0
			? current( $primaries )->domain
			: false;
	}

	/**
	 * Returns proper domain name mapped to this blog in case when multi mapping is disabled.
	 *
	 * @since 4.0.3
	 *
	 * @access private
	 * @return string Proper domain name if mapping exists, otherwise FALSE.
	 */
	private function _get_mapped_domain_without_multi() {
		$results = $this->_wpdb->get_results( sprintf( "SELECT domain FROM %s WHERE blog_id = %d ORDER BY id ASC", DOMAINMAP_TABLE_MAP, $this->_wpdb->blogid ) );
		if ( empty( $results ) ) {
			// we don't have any mapped domains, so return FALSE
			return false;
		}

		if ( count( wp_list_filter( $results, array( 'domain' => $_SERVER['HTTP_HOST'] ) ) ) > 0 ) {
			// current HTTP HOST is mapped to current blog, so we don't need to redirect
			return false;
		}

		// current host is not mapped, then return first mapped domain
		return $results[0]->domain;
	}

}