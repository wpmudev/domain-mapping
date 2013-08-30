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
 * Abstract reseller class implements all routine stuff required for reseller API works.
 *
 * @category Domainmap
 * @package Reseller
 *
 * @since 4.0.0
 * @abstract
 */
abstract class Domainmap_Reseller {

	/**
	 * Returns reseller internal id.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access protected
	 */
	protected abstract function _get_reseller_id();

	/**
	 * Returns reseller title.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 * @return string The title of reseller provider.
	 */
	public abstract function get_title();

	/**
	 * Renders reseller options.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 */
	public abstract function render_options();

	/**
	 * Saves reseller options.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 * @param array $options The array of plugin options.
	 */
	public abstract function save_options( &$options );

	/**
	 * Determines whether reseller API connected properly or not.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 * @return boolean TRUE if API connected properly, otherwise FALSE.
	 */
	public abstract function is_valid();

	/**
	 * Fetches and returns TLD list accepted by reseller.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access protected
	 * @return array The array of TLD accepted by reseller.
	 */
	protected abstract function _get_tld_list();

	/**
	 * Returns TLD list accepted by reseller.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return array The array of TLD accepted by reseller.
	 */
	public function get_tld_list() {
		$transient = 'reseller-' . $this->_get_reseller_id() . '-tlds';
		$tlds = get_transient( $transient );
		if ( is_array( $tlds ) && !empty( $tlds ) ) {
			return $tlds;
		}

		$tlds = $this->_get_tld_list();
		sort( $tlds, SORT_STRING );
		set_transient( $transient, $tlds, DAY_IN_SECONDS );

		return $tlds;
	}

	/**
	 * Checks domain availability.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access protected
	 * @param string $tld The top level domain.
	 * @param string $sld The second level domain.
	 * @return boolean TRUE if domain is available to puchase, otherwise FALSE.
	 */
	protected abstract function _check_domain( $tld, $sld );

	/**
	 * Check whether a domain is available for purchasing.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $tld The top level domain.
	 * @param string $sld The second level domain.
	 * @return boolean TRUE if domain is available to puchase, otherwise FALSE.
	 */
	public function check_domain( $tld, $sld ) {
		$transient = sprintf( 'reseller-%s-check-%s.%s', $this->_get_reseller_id(), $sld, $tld );
		$available = get_transient( $transient );
		if ( $available !== false ) {
			return $available;
		}

		$available = $this->_check_domain( $tld, $sld );
		set_transient( $transient, $available ? 1 : 0, HOUR_IN_SECONDS );

		return $available;
	}

	/**
	 * Fetches and returns TLD price.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 * @param string $tld The top level domain.
	 * @return float The price for the TLD.
	 */
	protected abstract function _get_tld_price( $tld );

	/**
	 * Returns TLD price.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $tld The top level domain.
	 * @return float The price for the TLD.
	 */
	public function get_tld_price( $tld ) {
		$transient = sprintf( 'reseller-%s-%s-price', $this->_get_reseller_id(), $tld );
		$price = get_transient( $transient );
		if ( $price != false ) {
			return $price;
		}

		$price = $this->_get_tld_price( $tld );
		set_transient( $transient, $price, DAY_IN_SECONDS );

		return $price;
	}

}