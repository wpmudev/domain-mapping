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
 * The module responsible for general admin tasks.
 *
 * @category Domainmap
 * @package Module
 *
 * @since 4.0.0
 */
class Domainmap_Module_Admin extends Domainmap_Module {

	const NAME = __CLASS__;

	/**
	 * The array of mapped domain.
	 *
	 * @since 4.0.0
	 * @access private
	 * @var array
	 */
	private $_mapped_domains = null;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param Domainmap_Plugin $plugin The instance of the plugin class.
	 */
	public function __construct( Domainmap_Plugin $plugin ) {
		parent::__construct( $plugin );

		$this->_add_action( 'manage_sites_custom_column', 'render_mapped_domain_column', 1, 2 );
		$this->_add_action( 'delete_blog', 'delete_blog_mappings', 1, 2 );
		$this->_add_action( 'domainmapping_delete_blog_mappings', 'delete_blog_mappings', 1, 2 );

		$this->_add_filter( 'wpmu_blogs_columns', 'register_mapped_domain_column' );
	}

	/**
	 * Registers "Mapped Domain" column for network sites table.
	 *
	 * @since 4.0.0
	 * @filter wpmu_blogs_columns
	 *
	 * @access public
	 * @param array $columns The array of already registered columns.
	 * @return array Modified array of columns.
	 */
	public function register_mapped_domain_column( $columns ) {
		return array_merge (
			array_splice( $columns, 0, 2 ),
			array( 'domainmap' => __( 'Mapped Domain', 'domainmap' ) ),
			$columns
		);
	}

	/**
	 * Renders "Mapped Domain" column data.
	 *
	 * @since 4.0.0
	 * @action manage_sites_custom_column
	 *
	 * @access public
	 * @global stdClass $current_site The current site object.
	 * @param string $column The column name to render.
	 * @param int $blog_id The blog ID for which to render column data.
	 */
	public function render_mapped_domain_column( $column, $blog_id ) {
		global $current_site;

		if ( $column != 'domainmap' ) {
			return;
		}

		// fetch mapped domains, if they haven't been fetched yet
		if ( is_null( $this->_mapped_domains ) ) {
			$this->_mapped_domains = array();
			$suffix = $current_site->path != '/' ? $current_site->path : '';
			$results = $this->_wpdb->get_results( "SELECT blog_id, domain, scheme FROM " . DOMAINMAP_TABLE_MAP );
			foreach ( $results as $result ) {
				if ( !isset( $this->_mapped_domains[$result->blog_id] ) ) {
					$this->_mapped_domains[$result->blog_id] = array();
				}
				$scheme = ($result->scheme == 1) ? 'https' : 'http';
				$this->_mapped_domains[$result->blog_id][] = sprintf( '<a href="%3$s://%1$s%2$s">%1$s%2$s</a>', Domainmap_Punycode::decode( $result->domain ), $suffix, $scheme );
			}
		}

		// render mapped domains
		if ( isset( $this->_mapped_domains[$blog_id] ) ) {
			echo implode( '<br>', $this->_mapped_domains[$blog_id] );
		}
	}

	/**
	 * Deletes mapped domains for removed blog.
	 *
	 * @since 4.0.0
	 * @action delete_blog
	 *
	 * @access public
	 * @param int $blog_id The blog id, which is going to be removed.
	 * @param boolean $drop Determines whether the blog has to be deleted permanently (db tables will be removed).
	 */
	public function delete_blog_mappings( $blog_id, $drop ) {
		if ( $blog_id && $drop ) {
			$this->_wpdb->delete( DOMAINMAP_TABLE_MAP, array( 'blog_id' => $blog_id ), array( '%d' ) );
		}
	}

}