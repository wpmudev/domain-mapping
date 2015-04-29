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
 * WHMCS client account registration form template class.
 *
 * @since 4.2.0
 * @category Domainmap
 * @package Render
 * @subpackage Reseller
 */
class Domainmap_Render_Reseller_WHMCS_Register extends Domainmap_Render_Reseller_Iframe {

	/**
	 * Render registration form template content.
	 *
	 * @since 4.2.0
	 *
	 * @access protected
	 */
	protected function _render_page() {
		?>
        <div id="domainmapping-content" class="domainmapping-tab domainmapping-iframe">
        <div id="domainmapping-iframe-content">
        <div id="domainmapping-box-iframe" class="domainmapping-box">
			<h3><?php _e( 'Register new WHMCS client', 'domainmap' ) ?></h3>
			<div class="domainmapping-domains-wrapper domainmapping-box-content domainmapping-form">
				<div class="domainmapping-locker"></div>
				<form class="domainmapping-iframe-form" method="post" id="dm-whmcs-client-registration-form">

					<p class="domainmapping-info"><?php esc_html_e( 'You are about to register for a new WHMCS client account. Please, fill in the form below and click on the register button. Pay attention that all fields marked with a red asterisk are required and must be filled with appropriate information.', 'domainmap' ) ?></p>

					<?php if ( is_wp_error( $this->errors ) ) : ?>
						<?php foreach ( $this->errors->get_error_messages() as $error ) : ?>
							<p class="domainmapping-info domainmapping-info-error"><?php echo esc_html( $error ) ?></p>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php $this->_render_account_fields() ?>
					<?php $this->_render_registrant_fields() ?>

					<div class="domainmapping-form-buttons">
						<button class="button domainmapping-button domainmapping-push-right" id="dm_whmcs_registeration_cancel"><?php _e( 'Cancel', 'domainmap' ) ?></button>
						<button type="submit" class="button button-primary domainmapping-button" id="dm_whmcs_registeration_submit"><i class="icon-ok icon-white"></i> <?php _e( 'Register account', 'domainmap' ) ?></button>
					</div>
					<div class="domainmapping-clear"></div>
				</form>
			</div>
		</div>
		</div>
		</div>
    <?php
	}

	/**
	 * Renders account fields.
	 *
	 * @since 4.2.0
	 *
	 * @access private
	 */
	private function _render_account_fields() {
		?>
		<h4><i class="icon-user"></i> <?php _e( 'Account Information', 'domainmap' ) ?></h4>

        <p>
            <label for="account_email" class="domainmapping-label"><?php _e( 'Email:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
            <input type="email" id="account_email" required name="account_email" maxlength="128" value="<?php echo esc_attr( filter_input( INPUT_POST, 'account_email' ) ) ?>">
            <span class="domainmapping-descr"><?php  esc_html_e( 'Email address to contact you about your domain name account which will be used to login to your account as well. The maximum length is 128 characters.', 'domainmap' ) ?></span>
            <span class="domainmapping-info-error domainmapping-registration-error domainmapping-hidden" id="account_email_err">
                <?php _e("Email can't be blank", domain_map::Text_Domain); ?>
            </span>
        </p>

		<p>
			<label for="account_password" class="domainmapping-label"><?php _e( 'Password:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="password" id="account_password" required name="account_password" maxlength="20">
			<span class="domainmapping-descr"><?php  esc_html_e( 'Permitted characters are letters, numbers, hyphen, and underscore. The maximum length is 20 characters.', 'domainmap' ) ?></span>
            <span class="domainmapping-info-error  domainmapping-registration-error domainmapping-hidden" id="account_password_err">
                <?php _e("Password can't be blank", domain_map::Text_Domain); ?>
            </span>
		</p>

		<p>
			<label for="account_password_confirm" class="domainmapping-label"><?php _e( 'Confirm Password:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="password" id="account_password_confirm" required name="account_password_confirm" maxlength="20">
			<span class="domainmapping-descr"><?php  esc_html_e( 'Confirm your password by entering it again. Permitted characters and maximum length are the same.', 'domainmap' ) ?></span>
            <span class="domainmapping-info-error domainmapping-registration-error domainmapping-hidden" id="account_password_confirm_err">
                <?php _e("Please enter password confirmation", domain_map::Text_Domain); ?>
            </span>
            <span class="domainmapping-info-error  domainmapping-registration-error domainmapping-hidden" id="account_password_match_err">
                <?php _e("Passwords don't match", domain_map::Text_Domain); ?>
            </span>
		</p>

		<?php
	}

	/**
	 * Renders registrant information fields.
	 *
	 * @since 4.2.0
	 *
	 * @access private
	 */
	private function _render_registrant_fields() {
		$registrant_country = filter_input( INPUT_POST, 'registrant_country' );

		?><h4><i class="icon-user"></i> <?php _e( 'Registrant Information', 'domainmap' ) ?></h4>

		<p>
			<label for="registrant_first_name" class="domainmapping-label"><?php _e( 'First Name:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_first_name" required name="registrant_first_name" maxlength="60" x-autocompletetype="given-name" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_first_name' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant first name. The maximum length is 60 characters.', 'domainmap' ) ?></span>
            <span class="domainmapping-info-error domainmapping-registration-error domainmapping-hidden" id="registrant_first_name_err">
                <?php _e("First name can't be empty", domain_map::Text_Domain); ?>
            </span>
		</p>

		<p>
			<label for="registrant_last_name" class="domainmapping-label"><?php _e( 'Last Name:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_last_name" required name="registrant_last_name" maxlength="60" x-autocompletetype="family-name" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_last_name' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant last name. The maximum length is 60 characters.', 'domainmap' ) ?></span>
            <span class="domainmapping-info-error domainmapping-registration-error domainmapping-hidden" id="registrant_last_name_err">
                <?php _e("Last name can't be empty", domain_map::Text_Domain); ?>
            </span>
		</p>


        <p>
            <label for="registrant_organization" class="domainmapping-label"><?php _e( 'Organization Name:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
            <input type="text" id="registrant_organization" required name="registrant_organization" maxlength="60" x-autocompletetype="org" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_organization' ) ) ?>">
            <span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant organization name. The maximum length is 60 characters.', 'domainmap' ) ?></span>
               <span class="domainmapping-info-error domainmapping-registration-error domainmapping-hidden" id="registrant_organization_err">
                <?php _e("Organization can't be blank", domain_map::Text_Domain); ?>
            </span>
        </p>


		<p>
			<label for="registrant_address1" class="domainmapping-label"><?php _e( 'Address:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_address1" required name="registrant_address1" maxlength="60" x-autocompletetype="address-line1" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_address1' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant address. The maximum length is 60 characters.', 'domainmap' ) ?></span>
            <span class="domainmapping-info-error domainmapping-registration-error domainmapping-hidden" id="registrant_address1_err">
                <?php _e("Address can't be empty", domain_map::Text_Domain); ?>
            </span>
		</p>


		<p>
			<label for="registrant_city" class="domainmapping-label"><?php _e( 'City:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_city" required name="registrant_city" maxlength="60" x-autocompletetype="city" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_city' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant city. The maximum length is 60 characters.', 'domainmap' ) ?></span>
            <span class="domainmapping-info-error domainmapping-registration-error domainmapping-hidden" id="registrant_city_err">
                <?php _e("City can't be blank", domain_map::Text_Domain); ?>
            </span>
		</p>

		<p>
			<label for="registrant_zip" class="domainmapping-label"><?php _e( 'Zip/Postal Code:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_zip" required name="registrant_zip" maxlength="16" x-autocompletetype="postal-code" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_zip' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant zip or postal code. The maximum length is 16 characters.', 'domainmap' ) ?></span>
            <span class="domainmapping-info-error domainmapping-registration-error domainmapping-hidden" id="registrant_zip_err">
                <?php _e("Please enter zip/postal code", domain_map::Text_Domain); ?>
            </span>
		</p>

		<p>
			<label for="registrant_state" class="domainmapping-label"><?php _e( 'State/Province:', 'domainmap' ) ?><span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_state" required name="registrant_state" maxlength="60" x-autocompletetype="administrative-area" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_state' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant state or province, this field is optional. The maximum length is 60 characters.', 'domainmap' ) ?></span>
            <span class="domainmapping-info-error domainmapping-registration-error domainmapping-hidden" id="registrant_state_err">
                <?php _e("State can't be blank", domain_map::Text_Domain); ?>
            </span>
		</p>

		<p>
			<label for="registrant_country" class="domainmapping-label"><?php _e( 'Country:', 'domainmap' ) ?><span class="domainmapping-field-required">*</span></label>
			<select id="registrant_country" required name="registrant_country" x-autocompletetype="country-name">
				<option></option>
				<?php foreach ( $this->countries as $code => $country ) : ?>
				<option value="<?php echo esc_attr( $code ) ?>"<?php selected( $code, $registrant_country ) ?>><?php echo esc_html( $country ) ?></option>
				<?php endforeach; ?>
			</select>
			<span class="domainmapping-descr"><?php esc_html_e( 'Select registrant country, this field is optional.', 'domainmap' ) ?></span>
            <span class="domainmapping-info-error domainmapping-registration-error domainmapping-hidden" id="registrant_country_err">
                <?php _e("Country can't be blank", domain_map::Text_Domain); ?>
            </span>
		</p>

		<p>
			<label for="registrant_phone" class="domainmapping-label"><?php _e( 'Phone:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_phone" required name="registrant_phone" maxlength="17" x-autocompletetype="tel" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_phone' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant phone number. Required format is +CountryCode.PhoneNumber, where CountryCode and PhoneNumber use only numeric characters. The maximum length is 17 characters.', 'domainmap' ) ?></span>
            <span class="domainmapping-info-error domainmapping-registration-error domainmapping-hidden" id="registrant_phone_err">
                <?php _e("Phone number can't be blank", domain_map::Text_Domain); ?>
            </span>
		</p>

		<?php
	}

}