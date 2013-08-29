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
 * The module responsible for setup plugin environment.
 *
 * @category Domainmap
 * @package Module
 *
 * @since 4.0.0
 */
class Domainmap_Module_Setup extends Domainmap_Module {

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

		$this->_add_action( 'plugins_loaded', 'loadTextDomain' );
		$this->_add_filter( 'domainmapping_resellers', 'setup_resellers' );

		// load the WPMUDEV dashboard notification library
		$notices = new WPMUDEV_Dashboard_Notice();
	}

	/**
	 * Loads plugin text domain.
	 *
	 * @since 4.0.0
	 * @action plugins_loaded
	 * @uses load_textdomain() To load translations for the plugin.
	 *
	 * @access public
	 */
	public function loadTextDomain() {
		$locale = apply_filters( 'domainmap_locale', get_locale() );
		$mofile = DOMAINMAP_ABSPATH . "/languages/domainmap-{$locale}.mo";
		if ( file_exists( $mofile ) ) {
			load_textdomain( 'domainmap', $mofile );
		}
	}

	/**
	 * Setups resellers.
	 *
	 * @since 4.0.0
	 * @filter domainmapping_resellers
	 *
	 * @access public
	 * @param array $resellers The array of resellers.
	 * @return array Updated array of resellers.
	 */
	public function setup_resellers( $resellers ) {
		$resellers[] = new Domainmap_Reseller_Enom();
		return $resellers;
	}

}