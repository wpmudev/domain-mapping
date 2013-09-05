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
 * Renders purchase domain tab page.
 *
 * @category Domainmap
 * @package Render
 * @subpackage Site
 *
 * @since 4.0.0
 */
class Domainmap_Render_Site_Purchase extends Domainmap_Render_Site {

	/**
	 * Renders tab content.
	 *
	 * @since 4.0.0
	 * @uses wp_enqueue_script() To enqueue jQuery payment library.
	 *
	 * @access protected
	 */
	protected function _render_tab() {
		wp_enqueue_script( 'jquery-payment' );
		$tlds = $this->reseller->get_tld_list();

		?><p class="domainmapping-info"><?php
			_e( 'If you want to buy an unique domain name and map it to your site, then you can do it on this page. Check whether desired domain name is available, and if it is, just fill in payment details and purchase it. New domain will be bought and mapped to your site. All necessary DNS records will be setup automatically.', 'domainmap' )
		?></p>

		<div id="domainmapping-box-check-domain" class="domainmapping-box">
			<h3><?php _e( 'Step 1: Check domain availability', 'domainmap' ) ?></h3>
			<div class="domainmapping-domains-wrapper domainmapping-box-content domainmapping-form">
				<div class="domainmapping-locker"></div>
				<form id="domainmapping-check-domain-form" action="<?php echo admin_url( 'admin-ajax.php' ) ?>" method="post">
					<?php wp_nonce_field( 'domainmapping_check_domain', 'nonce' ) ?>
					<input type="hidden" name="action" value="domainmapping_check_domain">
					<input type="text" class="domainmapping-input-prefix" readonly disabled value="http://">
					<div class="domainmapping-controls-wrapper">
						<input type="text" class="domainmapping-input-domain" autofocus name="sld">
						<select name="tld" class="domainmapping-select-domain">
							<?php foreach ( $tlds as $tld ) : ?>
							<option<?php selected( $tld, 'com' ) ?> value="<?php echo esc_attr( $tld ) ?>">.<?php echo esc_html( $tld ) ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<input type="text" class="domainmapping-input-sufix" readonly disabled value="">
					<button type="submit" class="button-primary button domainmapping-button"><i class="icon-search"></i> <?php _e( 'Check domain', 'domainmap' ) ?></button>
					<div class="domainmapping-clear"></div>
				</form>

				<div class="domainmapping-form-results"></div>
			</div>
		</div><?php
	}

}