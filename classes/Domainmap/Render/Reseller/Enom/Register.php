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
 * eNom account registration form template class.
 *
 * @since 4.1.0
 * @category Domainmap
 * @package Render
 * @subpackage Reseller
 */
class Domainmap_Render_Reseller_Enom_Register extends Domainmap_Render_Reseller_Iframe {

	/**
	 * Render registration form template content.
	 *
	 * @since 4.1.0
	 *
	 * @access protected
	 */
	protected function _render_page() {
        ?>
        <div id="wpwrap">
        <div id="domainmapping-content" class="domainmapping-tab domainmapping-iframe">
        <div id="domainmapping-iframe-content">
        <?php
		$backref = add_query_arg( array(
			'page' => 'domainmapping_options',
			'tab'  => 'reseller-options',
		), network_admin_url( 'settings.php', 'http' ) );

		?><div id="domainmapping-box-iframe" class="domainmapping-box">
			<h3><?php _e( 'Register new eNom account', 'domainmap' ) ?></h3>
			<div class="domainmapping-domains-wrapper domainmapping-box-content domainmapping-form">
				<div class="domainmapping-locker"></div>
				<form id="domainmapping-iframe-form" method="post">
					<input type="hidden" id="card_type" name="card_type">

					<p class="domainmapping-info"><?php esc_html_e( 'You are about to register for a new eNom account. Please, fill in the form below and click on the register button. Pay attention that all fields marked with a red asterisk are required and must be filled with appropriate information.', 'domainmap' ) ?></p>

					<?php if ( is_wp_error( $this->errors ) ) : ?>
						<?php foreach ( $this->errors->get_error_messages() as $error ) : ?>
							<p class="domainmapping-info domainmapping-info-error"><?php echo esc_html( $error ) ?></p>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php $this->_render_account_fields() ?>
					<?php $this->_render_registrant_fields() ?>

					<div class="domainmapping-form-buttons">
						<a class="button domainmapping-button domainmapping-push-right" href="<?php echo esc_url( $backref ) ?>"><?php _e( 'Cancel', 'domainmap' ) ?></a>
						<button type="submit" class="button button-primary domainmapping-button"><i class="icon-ok icon-white"></i> <?php _e( 'Register account', 'domainmap' ) ?></button>
					</div>
					<div class="domainmapping-clear"></div>
				</form>
			</div>
		</div>
		</div>
		</div>
		</div>
    <?php
	}

	/**
	 * Renders account fields.
	 *
	 * @since 4.1.0
	 *
	 * @access private
	 */
	private function _render_account_fields() {
		$selected_question = filter_input( INPUT_POST, 'account_question_type' );
		$questions = array(
			'smaiden' => esc_html__( 'What is your mother\'s maiden name?', 'domainmap' ),
			'sbirth'  => esc_html__( 'What is your city of born?', 'domainmap' ),
			'ssocial' => esc_html__( 'What is your last 4 digits of SSN?', 'domainmap' ),
			'shigh'   => esc_html__( 'What is your high school?', 'domainmap' ),
			'fteach'  => esc_html__( 'What is your favorite teacher?', 'domainmap' ),
			'fvspot'  => esc_html__( 'What is your favorite vacation spot?', 'domainmap' ),
			'fpet'    => esc_html__( 'What is your favorite pet?', 'domainmap' ),
			'fmovie'  => esc_html__( 'What is your favorite movie?', 'domainmap' ),
			'fbook'   => esc_html__( 'What is your favorite book?', 'domainmap' ),
		);

		?><h4><i class="icon-user"></i> <?php _e( 'Account Information', 'domainmap' ) ?></h4>

		<p>
			<label for="account_login" class="domainmapping-label"><?php _e( 'Login:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="account_login" name="account_login" autofocus required x-autocompletetype="nickname" maxlength="20" value="<?php echo esc_attr( filter_input( INPUT_POST, 'account_login' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Permitted values are 6 to 20 characters in length; permitted characters include letters, numbers, hyphen, and underscore.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="account_password" class="domainmapping-label"><?php _e( 'Password:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="password" id="account_password" required name="account_password" maxlength="20">
			<span class="domainmapping-descr"><?php  esc_html_e( 'Permitted characters are letters, numbers, hyphen, and underscore. The maximum length is 20 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="account_password_confirm" class="domainmapping-label"><?php _e( 'Confirm Password:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="password" id="account_password_confirm" required name="account_password_confirm" maxlength="20">
			<span class="domainmapping-descr"><?php  esc_html_e( 'Confirm your password by entering it again. Permitted characters and maximum length are the same.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="account_email" class="domainmapping-label"><?php _e( 'Contact Email:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="email" id="account_email" required name="account_email" maxlength="128" value="<?php echo esc_attr( filter_input( INPUT_POST, 'account_email' ) ) ?>">
			<span class="domainmapping-descr"><?php  esc_html_e( 'Email address to contact you about your domain name account. The maximum length is 128 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="account_question_type" class="domainmapping-label"><?php _e( 'Security Question:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<select id="account_question_type" name="account_question_type" required>
				<?php foreach ( $questions as $code => $question ) : ?>
				<option value="<?php echo esc_attr( $code ) ?>"<?php selected( $code, $selected_question ) ?>><?php echo $question ?></option>
				<?php endforeach; ?>
			</select>
			<span class="domainmapping-descr"><?php  esc_html_e( 'Select your security question, which will be used for identity verification.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="account_question_answer" class="domainmapping-label"><?php _e( 'Security Answer:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="account_question_answer" required name="account_question_answer" maxlength="50" value="<?php echo esc_attr( filter_input( INPUT_POST, 'account_question_answer' ) ) ?>">
			<span class="domainmapping-descr"><?php  esc_html_e( 'Enter your answer to the security question. The maximum length is 50 characters.', 'domainmap' ) ?></span>
		</p><?php
	}

	/**
	 * Renders registrant information fields.
	 *
	 * @since 4.1.0
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
		</p>

		<p>
			<label for="registrant_last_name" class="domainmapping-label"><?php _e( 'Last Name:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_last_name" required name="registrant_last_name" maxlength="60" x-autocompletetype="family-name" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_last_name' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant last name. The maximum length is 60 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_organization" class="domainmapping-label"><?php _e( 'Organization Name:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_organization" name="registrant_organization" maxlength="60" x-autocompletetype="org" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_organization' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant organization name. The maximum length is 60 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_job_title" class="domainmapping-label"><?php _e( 'Job Title:', 'domainmap' ) ?></label>
			<input type="text" id="registrant_job_title" required name="registrant_job_title" maxlength="60" x-autocompletetype="organization-title" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_job_title' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant job title, this field is optional. The maximum length is 60 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_address1" class="domainmapping-label"><?php _e( 'Address:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_address1" required name="registrant_address1" maxlength="60" x-autocompletetype="address-line1" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_address1' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant address. The maximum length is 60 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_address2" class="domainmapping-label"><?php _e( 'Alternative Address:', 'domainmap' ) ?></label>
			<input type="text" id="registrant_address2" name="registrant_address2" maxlength="60" x-autocompletetype="address-line2" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_address2' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant alternative address, this field is optional. The maximum length is 60 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_city" class="domainmapping-label"><?php _e( 'City:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_city" required name="registrant_city" maxlength="60" x-autocompletetype="city" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_city' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant city. The maximum length is 60 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_zip" class="domainmapping-label"><?php _e( 'Zip/Postal Code:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_zip" required name="registrant_zip" maxlength="16" x-autocompletetype="postal-code" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_zip' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant zip or postal code. The maximum length is 16 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_state" class="domainmapping-label"><?php _e( 'State/Province:', 'domainmap' ) ?></label>
			<input type="text" id="registrant_state" name="registrant_state" maxlength="60" x-autocompletetype="administrative-area" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_state' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant state or province, this field is optional. The maximum length is 60 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_country" class="domainmapping-label"><?php _e( 'Country:', 'domainmap' ) ?></label>
			<select id="registrant_country" name="registrant_country" x-autocompletetype="country-name">
				<option></option>
				<?php foreach ( $this->countries as $code => $country ) : ?>
				<option value="<?php echo esc_attr( $code ) ?>"<?php selected( $code, $registrant_country ) ?>><?php echo esc_html( $country ) ?></option>
				<?php endforeach; ?>
			</select>
			<span class="domainmapping-descr"><?php esc_html_e( 'Select registrant country, this field is optional.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_email" class="domainmapping-label"><?php _e( 'Email:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="email" id="registrant_email" required name="registrant_email" maxlength="128" x-autocompletetype="email" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_email' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter email address for Whois. The maximum length is 128 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_phone" class="domainmapping-label"><?php _e( 'Phone:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_phone" required name="registrant_phone" maxlength="17" x-autocompletetype="tel" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_phone' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant phone number. Required format is +CountryCode.PhoneNumber, where CountryCode and PhoneNumber use only numeric characters. The maximum length is 17 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_fax" class="domainmapping-label"><?php _e( 'Fax:', 'domainmap' ) ?></label>
			<input type="text" id="registrant_fax" name="registrant_fax" maxlength="17" x-autocompletetype="fax" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_fax' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant fax number, this field is optional. Required format is +CountryCode.PhoneNumber, where CountryCode and PhoneNumber use only numeric characters. The maximum length is 17 characters.', 'domainmap' ) ?></span>
		</p><?php
	}

}