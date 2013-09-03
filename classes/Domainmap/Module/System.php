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
 * The module responsible for system tasks.
 *
 * @category Domainmap
 * @package Module
 *
 * @since 4.0.0
 */
class Domainmap_Module_System extends Domainmap_Module {

	const NAME = __CLASS__;

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
		$this->_check_sunrise();
	}

	/**
	 * Checks sunrise.php file availability.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _check_sunrise() {
		if ( defined( 'SUNRISE' ) ) {
			$dest = WP_CONTENT_DIR . '/sunrise.php';
			$source = DOMAINMAP_ABSPATH . '/sunrise.php';
			if ( !file_exists( $dest ) && is_writable( WP_CONTENT_DIR ) && is_readable( $source ) ) {
				@copy( $source, $dest );
			}
		}
	}

}