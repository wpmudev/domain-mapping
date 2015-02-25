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
 * Base class for site options tabbed pages.
 *
 * @category Domainmap
 * @package Render
 *
 * @since 4.0.0
 * @abstract
 */
abstract class Domainmap_Render_Site extends Domainmap_Render_Tabbed {

	/**
	 * Renders page header.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 */
	protected function _render_header() {
		?><div class="icon32" id="icon-tools"><br/></div>
		<h2><?php _e( 'Domain Mapping', 'domainmap' ) ?></h2><?php

		if ( filter_input( INPUT_GET, 'saved', FILTER_VALIDATE_BOOLEAN ) ) :
			echo '<div id="message" class="updated fade">', __( 'Options updated.', domain_map::Text_Domain ), '</div>';
		endif;
	}

}