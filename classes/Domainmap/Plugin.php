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
 * The core plugin class.
 *
 * @category Domainmap
 *
 * @since 4.0.0
 */
class Domainmap_Plugin {

	const NAME    = 'domainmap';
	const VERSION = '4.0.0';

	/**
	 * Singletone instance of the plugin.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @var Domainmap_Plugin
	 */
	private static $_instance = null;

	/**
	 * The plugin's options.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @var array
	 */
	private $_options = null;

	/**
	 * Whether current site is permitted to map domains or not.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @var boolean
	 */
	private $_permitted = null;

	/**
	 * The array of registered modules.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @var array
	 */
	private $_modules = array();

	/**
	 * The array of reseller objects.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @var array
	 */
	private $_resellers = null;

	/**
	 * Private constructor.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function __construct() {}

	/**
	 * Private clone method.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function __clone() {}

	/**
	 * Returns singletone instance of the plugin.
	 *
	 * @since 4.0.0
	 *
	 * @static
	 * @access public
	 * @return Domainmap_Plugin
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new Domainmap_Plugin();
		}

		return self::$_instance;
	}

	/**
	 * Returns a module if it was registered before. Otherwise NULL.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $name The name of the module to return.
	 * @return Domainmap_Module|null Returns a module if it was registered or NULL.
	 */
	public function get_module( $name ) {
		return isset( $this->_modules[$name] ) ? $this->_modules[$name] : null;
	}

	/**
	 * Determines whether the module has been registered or not.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $name The name of a module to check.
	 * @return boolean TRUE if the module has been registered. Otherwise FALSE.
	 */
	public function has_module( $name ) {
		return isset( $this->_modules[$name] );
	}

	/**
	 * Register new module in the plugin.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $module The name of the module to use in the plugin.
	 */
	public function set_module( $class ) {
		$this->_modules[$class] = new $class( $this );
	}

	/**
	 * Returns array of plugin's options.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return array The array of options.
	 */
	public function get_options() {
		if ( is_null( $this->_options ) ) {
			$this->_options = (array)get_site_option( 'domain_mapping', array() );
			if ( empty( $this->_options ) ) {
				$this->_options['map_ipaddress'] = get_site_option( 'map_ipaddress' );
				$this->_options['map_supporteronly'] = get_site_option( 'map_supporteronly', '0' );
				$this->_options['map_admindomain'] = get_site_option( 'map_admindomain', 'user' );
				$this->_options['map_logindomain'] = get_site_option( 'map_logindomain', 'user' );
				$this->_options['map_reseller'] = array();

				update_site_option('domain_mapping', $this->_options);
			}
		}

		return $this->_options;
	}

	/**
	 * Determines whether current site is permitted to map domains or not.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return boolean TRUE if site permitted to map domains, otherwise FALSE.
	 */
	public function is_site_permitted() {
		if ( !is_null( $this->_permitted ) ) {
			return $this->_permitted;
		}

		$options = $this->get_options();
		$this->_permitted = true;
		if ( function_exists( 'is_pro_site' ) && !empty( $options['map_supporteronly'] ) ) {
			// We have a pro-site option set and the pro-site plugin exists
			$levels = (array)get_site_option( 'psts_levels' );
			if( !is_array( $options['map_supporteronly'] ) && !empty( $levels ) && $options['map_supporteronly'] == '1' ) {
				$options['map_supporteronly'] = array( key( $levels ) );
			}

			$this->_permitted = false;
			foreach ( (array)$options['map_supporteronly'] as $level ) {
				if( is_pro_site( false, $level ) ) {
					$this->_permitted = true;
				}
			}
		}

		return $this->_permitted;
	}

	/**
	 * Returns array of resellers.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return array The array of resellers.
	 */
	public function get_resellers() {
		if ( is_null( $this->_resellers ) ) {
			$this->_resellers = array();
			$resellers = apply_filters( 'domainmapping_resellers', array() );
			foreach ( $resellers as $reseller ) {
				if ( is_object( $reseller ) && is_a( $reseller, 'Domainmap_Reseller' ) ) {
					$this->_resellers[dechex( crc32( get_class( $reseller ) ) )] = $reseller;
				}
			}
		}

		return $this->_resellers;
	}

}