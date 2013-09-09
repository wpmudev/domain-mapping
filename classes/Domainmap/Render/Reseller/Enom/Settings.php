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
 * eNom reseller settings template.
 *
 * @category Domainmap
 * @package Render
 * @subpackage Reseller
 *
 * @since 4.0.0
 */
class Domainmap_Render_Reseller_Enom_Settings extends Domainmap_Render {

	/**
	 * Renders template.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 * @global ProSites $psts The instance of ProSites plugin class.
	 */
	protected function _to_html() {
		global $psts;

		$pwd = str_shuffle( (string)$this->pwd );
		$pwd_hash = sha1( $pwd );

		?><div id="domainmapping-enom-header">
			<div id="domainmapping-enom-logo"></div>
		</div>

		<?php if ( $this->valid === false ) : ?>
		<div class="domainmapping-info domainmapping-info-error">
			<?php _e( 'Looks like your credentials are invalid. Please, enter valid credentials and resave the form.', 'domainmap' ) ?>
		</div>
		<?php endif; ?>

		<?php if ( $this->gateway == Domainmap_Reseller_Enom::GATEWAY_ENOM ) : ?>
		<div class="domainmapping-info domainmapping-info-warning"><?php
			_e( 'You use eNom credit card processing service. Pay attention that this service is available only to resellers who have entered into a credit card processing agreement with eNom.', 'domainmap' )
		?></div>
		<?php endif; ?>

		<div class="domainmapping-info"><?php
			printf(
				__( 'Keep in mind that to start using eNom API you have to add your server IP address in the live environment. Go to %s, click "Launch the Support Center" button and submit a new ticket. In the new ticket set "Add IP" subject, type the IP address(es) you wish to add and select API category.', 'domainmap' ),
				'<a href="http://www.enom.com/help/" target="_blank">eNom Help Center</a>'
			)
		?></div>

		<div>
			<h4 class="domainmapping-block-header"><?php _e( 'Enter your account id and password:', 'domainmap' ) ?></h4>
			<div>
				<label for="enom-uid" class="domainmapping-label"><?php _e( 'Account id:', 'domainmap' ) ?></label>
				<input type="text" id="enom-uid" class="regular-text" name="map_reseller_enom_uid" value="<?php echo esc_attr( $this->uid ) ?>" autocomplete="off">
			</div>
			<div>
				<label for="enom-pwd" class="domainmapping-label"><?php _e( 'Password:', 'domainmap' ) ?></label>
				<input type="password" id="enom-pwd" class="regular-text" name="map_reseller_enom_pwd" value="<?php echo esc_attr( $pwd ) ?>" autocomplete="off">
				<input type="hidden" name="map_reseller_enom_pwd_hash" value="<?php echo $pwd_hash ?>">
			</div>

			<?php if ( $psts && in_array( 'ProSites_Gateway_PayPalExpressPro', $psts->get_setting( 'gateways_enabled' ) ) ) : ?>
			<h4 class="domainmapping-block-header"><?php _e( 'Select payment gateway:', 'domainmap' ) ?></h4>
			<ul>
				<?php foreach ( $this->gateways as $key => $label ) : ?>
				<li>
					<label>
						<input type="radio" class="domainmapping-radio" name="map_reseller_enom_payment" value="<?php echo esc_attr( $key ) ?>"<?php checked( $key, $this->gateway )  ?>>
						<?php echo esc_html( $label ) ?>
					</label>
				</li>
				<?php endforeach; ?>
			</ul>
			<?php endif; ?>
		</div><?php
	}

}