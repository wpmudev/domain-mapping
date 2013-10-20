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
	 * The array of mapped urls.
	 *
	 * @since 4.0.3
	 *
	 * @static
	 * @access private
	 * @var array
	 */
	private static $_mapped_urls = array();

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

		if ( !isset( self::$_mapped_urls[$this->_wpdb->blogid] ) ) {
			$errors_suppression = $this->_wpdb->suppress_errors();

			$query = $this->_wpdb->prepare( "SELECT domain FROM " . DOMAINMAP_TABLE_MAP . " WHERE blog_id = %d AND domain = %s AND is_primary = 1 LIMIT 1", $_SERVER['HTTP_HOST'], $this->_wpdb->blogid );
			$domain = $this->_wpdb->get_var( $query );
			if ( empty( $domain ) ) {
				$query = $this->_wpdb->prepare( "SELECT domain FROM " . DOMAINMAP_TABLE_MAP . " WHERE blog_id = %d ORDER BY is_primary DESC, id ASC LIMIT 1", $this->_wpdb->blogid );
				$domain = $this->_wpdb->get_var( $query );
			}

			$this->_wpdb->suppress_errors( $errors_suppression );

			$protocol = 'http://';
			if ( defined( 'DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN' ) && DM_FORCE_PROTOCOL_ON_MAPPED_DOMAIN && is_ssl() ) {
				$protocol = 'https://';
			}

			self::$_mapped_urls[$this->_wpdb->blogid] = !empty( $domain )
				? untrailingslashit( $protocol . $domain . $current_site->path )
				: false;
		}

		return self::$_mapped_urls[$this->_wpdb->blogid];
	}

}