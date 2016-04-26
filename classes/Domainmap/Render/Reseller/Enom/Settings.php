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
	 * Renders eNom settings notifications.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _render_notifications() {
		?><div id="domainmapping-enom-header">
			<div id="domainmapping-enom-logo"></div>
		</div>

		<div class="domainmapping-info"><?php
			printf(
				__( 'Keep in mind that to start using eNom API you have to add your server IP address in the live environment. Go to %s, click "Launch the Support Center" button and submit a new ticket. In the new ticket set "Add IP" subject, type the IP address(es) you wish to add and select API category.', 'domainmap' ),
				'<a href="http://www.enom.com/help/" target="_blank">eNom Help Center</a>'
			)
		?></div>

		<div class="domainmapping-info">
			<b><?php esc_html_e( 'Signup for a free eNom sub-reseller account.', 'domainmap' ) ?></b><br>
			<?php esc_html_e( 'By signing up here as a sub-reseller you will avoid the high setup fees of direct accounts. You can of course switch to a direct eNom account later and change the credentials here to that.', 'domainmap' ) ?>
			<a href="<?php echo esc_url( $this->register_link ) ?>"><?php esc_html_e( 'Register new eNom account', 'domainmap' ) ?></a>.
		</div><?php
	}

	/**
	 * Renders account credentials settings.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _render_account_settings() {
		// it is a bad habit to show raw password even if we use password input field
		// so lets suffle it and render in the password field
		$pwd = str_shuffle( (string)$this->pwd );
		// we save shuffle hash to see on POST if the password was changed by an user
		$pwd_hash = sha1( $pwd );

		?><h4 class="domainmapping-block-header"><?php _e( 'Account credentials:', 'domainmap' ) ?></h4>

		<?php if ( empty($this->uid) || empty($this->pwd) ) : ?>
			<div class="domainmapping-info domainmapping-info-error">
				<p><?php _e( "Warning: you haven't entered any API credentials, the purchase domain tab will not show till you've successfully saved your API credentials", 'domainmap' ) ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $this->valid === false ) : ?>
		<div class="domainmapping-info domainmapping-info-error">
			<p><?php _e( 'Looks like your credentials are invalid. Please, check the errors sent by eNom server:', 'domainmap' ) ?></p>
			<?php if ( is_wp_error( $this->errors ) ) : ?>
				<ul>
					<li>
						<b><?php echo implode( '</b></li><li><b>', array_map( 'esc_html', $this->errors->get_error_messages() ) ) ?></b>
					</li>
				</ul>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<div>
			<label for="enom-uid" class="domainmapping-label"><?php _e( 'Account id:', 'domainmap' ) ?></label>
			<input type="text" id="enom-uid" class="regular-text" name="map_reseller_enom_uid" value="<?php echo esc_attr( $this->uid ) ?>" autocomplete="off">
		</div>
		<div>
			<label for="enom-pwd" class="domainmapping-label"><?php _e( 'Password:', 'domainmap' ) ?></label>
			<input type="password" id="enom-pwd" class="regular-text" name="map_reseller_enom_pwd" value="<?php echo esc_attr( $pwd ) ?>" autocomplete="off">
			<input type="hidden" name="map_reseller_enom_pwd_hash" value="<?php echo $pwd_hash ?>">
		</div><?php
	}

	/**
	 * Renders payment gateways settings.
	 *
	 * @sine 4.0.0
	 *
	 * @access private
	 */
	private function _render_payment_settings() {
		?><h4 class="domainmapping-block-header"><?php _e( 'Select payment gateway:', 'domainmap' ) ?></h4>

		<?php if ( $this->gateway == Domainmap_Reseller_Enom::GATEWAY_ENOM ) : ?>
		<div class="domainmapping-info domainmapping-info-warning"><?php
			_e( 'You use eNom credit card processing service. Pay attention that this service is available only to resellers who have entered into a credit card processing agreement with eNom. Additionally you must configure SSL support on your server to enable these payments.', 'domainmap' )
		?></div>
		<?php endif; ?>

		<div>
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
		</div><?php
	}

	/**
	 * Renders environment settings.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _render_environment_settings() {
		$environemnts = array(
			Domainmap_Reseller_Enom::ENVIRONMENT_TEST       => __( 'Test environment', 'domainmap' ),
			Domainmap_Reseller_Enom::ENVIRONMENT_PRODUCTION => __( 'Production environment', 'domainmap' ),
		);

		?><h4 class="domainmapping-block-header"><?php _e( 'Select environment:', 'domainmap' ) ?></h4>
		<div>
			<p><?php _e( 'Select an environment which you want to use. Use test environment to test your reseller account and production one when you will be ready to sell domains to your users.', 'domainmap' ) ?></p>
			<ul class="domainmapping-compressed-list"><?php
				foreach ( $environemnts as $environment => $label ) :
					?><li>
						<label>
							<input type="radio" class="domainmapping-radio" name="map_reseller_enom_environment" value="<?php echo $environment ?>"<?php checked( $environment, $this->environment ) ?>>
							<?php echo $label ?>
						</label>
					</li><?php
				endforeach;
			?></ul>
		</div><?php
	}

	/**
	 * Renders SSL verification settings.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _render_sslverification_settings() {
		$selected = isset( $this->sslverification ) ? $this->sslverification : 1;

		$options = array(
			1 => __( 'Enable SSL verification', 'domainmap' ),
			0 => __( 'Disable SSL verification', 'domainmap' ),
		);

		?><h4 class="domainmapping-block-header"><?php _e( 'SSL Certificate Verification:', 'domainmap' ) ?></h4>
		<div>
			<p><?php
				printf(
					__( 'If you have a list of %scertificate authorities%s installed on your web server, then it is strongly recommended to enable SSL certificate verification (for communications between eNom and your server) for security reasons.', 'domainmap' ),
					'<a href="http://en.wikipedia.org/wiki/Certificate_authority" target="_blank">',
					'</a>'
				)
			?></p>
			<ul class="domainmapping-compressed-list"><?php
				foreach ( $options as $key => $label ) :
					?><li>
						<label>
							<input type="radio" class="domainmapping-radio" name="map_reseller_enom_sslverification" value="<?php echo $key ?>"<?php checked( $key, $selected ) ?>>
							<?php echo $label ?>
						</label>
					</li><?php
				endforeach;
			?></ul>
		</div><?php
	}

	/**
	 * Renders template.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 */
	protected function _to_html() {
		$this->_render_notifications();
		$this->_render_account_settings();
		$this->_render_environment_settings();
		$this->_render_sslverification_settings();
		$this->_render_payment_settings();
	}

}