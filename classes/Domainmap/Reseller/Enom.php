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
 * eNom reseller API class.
 *
 * @category Domainmap
 * @package Reseller
 *
 * @since 4.0.0
 */
class Domainmap_Reseller_Enom extends Domainmap_Reseller {

	/**
	 * Returns reseller title.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 */
	public function get_title() {
		return 'eNom';
	}

	/**
	 * Renders reseller options.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access public
	 */
	public function render_options() {
		$options = Domainmap_Plugin::instance()->get_options();
		$options = isset( $options['map_reseller']['enom'] ) ? $options['map_reseller']['enom'] : array();

		?><p>
			<?php _e( 'In case to use eNom provider, please, enter your account id and password in the fields below.', 'domainmap' ) ?>
		</p>
		<div>
			<label for="enom-uid" class="domainmapping-label"><?php _e( 'Account id:', 'domainmap' ) ?></label>
			<input type="text" id="enom-uid" class="regular-text" name="map_reseller[enom][uid]" value="<?php echo isset( $options['uid'] ) ? esc_attr( $options['uid'] ) : '' ?>" autocomplete="off">
		</div>
		<div>
			<label for="enom-pwd" class="domainmapping-label"><?php _e( 'Password:', 'domainmap' ) ?></label>
			<input type="password" id="enom-pwd" class="regular-text" name="map_reseller[enom][pwd]" value="<?php echo isset( $options['pwd'] ) ? esc_attr( $options['pwd'] ) : '' ?>" autocomplete="off">
		</div><?php
	}

}