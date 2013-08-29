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
	 * Returns reseller title.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
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

}