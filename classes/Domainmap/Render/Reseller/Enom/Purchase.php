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
 * eNom credit card processing form template class.
 *
 * @since 4.0.0
 * @category Domainmap
 * @package Render
 * @subpackage Reseller
 */
class Domainmap_Render_Reseller_Enom_Purchase extends Domainmap_Render_Purchase {

	/**
	 * Renders purchase form.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 */
	protected function _render_page() {
		$cancel = filter_input( INPUT_GET, 'cancel', FILTER_VALIDATE_URL );

		?><div id="domainmapping-box-purchase-domain" class="domainmapping-box">
			<h3><?php _e( 'Purchase domain', 'domainmap' ) ?></h3>
			<div class="domainmapping-domains-wrapper domainmapping-box-content domainmapping-form">
				<div class="domainmapping-locker"></div>
				<form id="domainmapping-purchase-domain-form" method="post">
					<input type="hidden" name="sld" value="<?php echo esc_attr( $this->sld ) ?>">
					<input type="hidden" name="tld" value="<?php echo esc_attr( $this->tld ) ?>">
					<input type="hidden" id="card_type" name="card_type">

					<p class="domainmapping-info"><?php printf(
						__( 'You are about to purchase domain name %s and pay %s for 1 year of usage. Please, fill in the form below and click on purchase button. Pay attention that all fields marked with red asterisk are required and has to be filled with appropriate information.', 'domainmap' ),
						'<b>' . esc_html( strtoupper( $this->domain ) ) . '</b>',
						'<b>' . esc_html( $this->price ) . '</b>'
					) ?></p>

					<?php if ( is_wp_error( $this->errors ) ) : ?>
						<?php foreach ( $this->errors->get_error_messages() as $error ) : ?>
							<p class="domainmapping-info domainmapping-info-error"><?php echo esc_html( $error ) ?></p>
						<?php endforeach; ?>
					<?php endif; ?>

					<?php $this->_render_card_fields() ?>
					<?php $this->_render_billing_fields() ?>
					<?php $this->_render_registrant_fields() ?>
					<?php $this->_render_extra_fields() ?>

					<div class="domainmapping-form-buttons">
						<?php if ( $cancel ) : ?>
						<a class="button domainmapping-button domainmapping-push-right" href="<?php echo esc_url( $cancel ) ?>"><?php _e( 'Cancel', 'domainmap' ) ?></a>
						<?php endif; ?>
						<button type="submit" class="button button-primary domainmapping-button"><i class="icon-shopping-cart"></i> <?php _e( 'Purchase domain', 'domainmap' ) ?></button>
					</div>
					<div class="domainmapping-clear"></div>
				</form>
			</div>
		</div><?php
	}

	/**
	 * Renders credit card fields.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _render_card_fields() {
		?><h4><i class="icon-credit-card"></i> <?php _e( 'Credit Card', 'domainmap' ) ?></h4>

		<p class="domainmapping-info">
			<?php _e( 'Supported card types are:', 'domainmap' ) ?> <b><?php echo implode( '</b>, <b>', (array)$this->cardtypes ) ?></b>
		</p>

		<p>
			<label for="card_number" class="domainmapping-label"><?php _e( 'Card Number:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="card_number" required name="card_number" maxlength="19" x-autocompletetype="cc-number" placeholder="0000 0000 0000 0000" value="<?php echo esc_attr( filter_input( INPUT_POST, 'card_number' ) ) ?>">
		</p>

		<p>
			<label for="card_expiration" class="domainmapping-label"><?php _e( 'Card Expiration:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="card_expiration" required name="card_expiration" x-autocompletetype="cc-exp" placeholder="mm / yy" value="<?php echo esc_attr( filter_input( INPUT_POST, 'card_expiration' ) ) ?>">
		</p>

		<p>
			<label for="card_cvv2" class="domainmapping-label"><?php _e( 'CVV2:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="card_cvv2" required name="card_cvv2" maxlength="4" autocomplete="off" placeholder="xxx" value="<?php echo esc_attr( filter_input( INPUT_POST, 'card_cvv2' ) ) ?>">
		</p>

		<p>
			<label for="card_cardholder" class="domainmapping-label"><?php _e( "Cardholder's Name:", 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="card_cardholder" required name="card_cardholder" maxlength="60" x-autocompletetype="cc-name" placeholder="<?php echo esc_attr( $this->cardholder ) ?>" value="<?php echo esc_attr( filter_input( INPUT_POST, 'card_cardholder' ) ) ?>">
		</p><?php
	}

	/**
	 * Renders billing fields.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _render_billing_fields() {
		$billing_country = filter_input( INPUT_POST, 'billing_country' );

		?><h4><i class="icon-building"></i> <?php _e( 'Billing Information', 'domainmap' ) ?></h4>

		<p>
			<label for="billing_address" class="domainmapping-label"><?php _e( 'Address:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="billing_address" required name="billing_address" maxlength="60" x-autocompletetype="address-line1" value="<?php echo esc_attr( filter_input( INPUT_POST, 'billing_address' ) ) ?>">
		</p>

		<p>
			<label for="billing_city" class="domainmapping-label"><?php _e( 'City:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="billing_city" required name="billing_city" maxlength="60" x-autocompletetype="city" value="<?php echo esc_attr( filter_input( INPUT_POST, 'billing_city' ) ) ?>">
		</p>

		<p>
			<label for="billing_state" class="domainmapping-label"><?php _e( 'State/Province:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="billing_state" required name="billing_state" maxlength="60" x-autocompletetype="administrative-area" value="<?php echo esc_attr( filter_input( INPUT_POST, 'billing_state' ) ) ?>">
		</p>

		<p>
			<label for="billing_zip" class="domainmapping-label"><?php _e( 'Zip/Postal Code:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="billing_zip" required name="billing_zip" maxlength="15" x-autocompletetype="postal-code" value="<?php echo esc_attr( filter_input( INPUT_POST, 'billing_zip' ) ) ?>">
		</p>

		<p>
			<label for="billing_phone" class="domainmapping-label"><?php _e( 'Phone:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="billing_phone" required name="billing_phone" maxlength="15" x-autocompletetype="tel" value="<?php echo esc_attr( filter_input( INPUT_POST, 'billing_phone' ) ) ?>">
		</p>

		<p>
			<label for="billing_country" class="domainmapping-label"><?php _e( 'Country:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<select id="billing_country" required name="billing_country">
				<option></option>
				<?php foreach ( $this->countries as $code => $country ) : ?>
				<option value="<?php echo esc_attr( $code ) ?>"<?php selected( $code, $billing_country ) ?>><?php echo esc_html( $country ) ?></option>
				<?php endforeach; ?>
			</select>
		</p><?php
	}

	/**
	 * Renders registrant information fields.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _render_registrant_fields() {
		$registrant_country = filter_input( INPUT_POST, 'registrant_country' );

		?><h4><i class="icon-user"></i> <?php _e( 'Registrant Information', 'domainmap' ) ?></h4>

		<p>
			<label for="registrant_first_name" class="domainmapping-label"><?php _e( 'First Name:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_first_name" required name="registrant_first_name" maxlength="60" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_first_name' ) ) ?>">
		</p>

		<p>
			<label for="registrant_last_name" class="domainmapping-label"><?php _e( 'Last Name:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_last_name" required name="registrant_last_name" maxlength="60" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_last_name' ) ) ?>">
		</p>

		<p>
			<label for="registrant_organization" class="domainmapping-label"><?php _e( 'Organization Name:', 'domainmap' ) ?></label>
			<input type="text" id="registrant_organization" name="registrant_organization" maxlength="60" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_organization' ) ) ?>">
		</p>

		<p>
			<label for="registrant_job_title" class="domainmapping-label"><?php _e( 'Job Title:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_job_title" name="registrant_job_title" maxlength="60" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_job_title' ) ) ?>">
		</p>

		<p>
			<label for="registrant_address1" class="domainmapping-label"><?php _e( 'Address:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_address1" required name="registrant_address1" maxlength="60" x-autocompletetype="address-line1" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_address1' ) ) ?>">
		</p>

		<p>
			<label for="registrant_address2" class="domainmapping-label"><?php _e( 'Alternative Address:', 'domainmap' ) ?></label>
			<input type="text" id="registrant_address2" name="registrant_address2" maxlength="60" x-autocompletetype="address-line2" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_address2' ) ) ?>">
		</p>

		<p>
			<label for="registrant_city" class="domainmapping-label"><?php _e( 'City:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_city" required name="registrant_city" maxlength="60" x-autocompletetype="city" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_city' ) ) ?>">
		</p>

		<p>
			<label for="registrant_state" class="domainmapping-label"><?php _e( 'State/Province:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_state" required name="registrant_state" maxlength="60" x-autocompletetype="administrative-area" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_state' ) ) ?>">
		</p>

		<p>
			<label for="registrant_zip" class="domainmapping-label"><?php _e( 'Zip/Postal Code:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_zip" required name="registrant_zip" maxlength="15" x-autocompletetype="postal-code" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_zip' ) ) ?>">
		</p>

		<p>
			<label for="registrant_country" class="domainmapping-label"><?php _e( 'Country:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<select id="registrant_country" required name="registrant_country">
				<option></option>
				<?php foreach ( $this->countries as $code => $country ) : ?>
				<option value="<?php echo esc_attr( $code ) ?>"<?php selected( $code, $registrant_country ) ?>><?php echo esc_html( $country ) ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="registrant_email" class="domainmapping-label"><?php _e( 'Email:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="email" id="registrant_email" required name="registrant_email" maxlength="128" x-autocompletetype="email" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_email' ) ) ?>">
		</p>

		<p>
			<label for="registrant_phone" class="domainmapping-label"><?php _e( 'Phone:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_phone" required name="registrant_phone" maxlength="20" x-autocompletetype="tel" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_phone' ) ) ?>">
		</p>

		<p>
			<label for="registrant_fax" class="domainmapping-label"><?php _e( 'Fax:', 'domainmap' ) ?></label>
			<input type="text" id="registrant_fax" name="registrant_fax" maxlength="20" x-autocompletetype="fax" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_fax' ) ) ?>">
		</p><?php
	}

	/**
	 * Renders extra attributes fields.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _render_extra_fields() {
		if ( empty( $this->ext_attributes ) ) {
			return;
		}

		?><h4><i class="icon-asterisk"></i> <?php _e( 'Additional Registrar Information', 'domainmap' ) ?></h4><?php

		foreach ( $this->ext_attributes as $attribute ) :
			$e_att_name = esc_attr( $attribute['Name'] );
			$isset = isset( $_POST['ExtendedAttributes'][$e_att_name] );
			$value = $isset ? $_POST['ExtendedAttributes'][$e_att_name] : '';

			$required = $required_ast = '';
			if ( $attribute['Required'] > 0 ) :
				$required = ' required';
				$required_ast = ' <span class="domainmapping-field-required">*</span>';
			endif;

			?><p>
				<label for="extendedettributes_<?php echo $e_att_name ?>" class="domainmapping-label">
					<?php echo esc_html( $attribute['Description'] ) ?>:<?php echo $required_ast ?>
				</label>

				<?php if ( !empty( $attribute['Options'] ) ) : ?>
					<select id="extendedettributes_<?php echo $e_att_name ?>" name="ExtendedAttributes[<?php echo $e_att_name ?>]"<?php echo $required ?>>
						<?php foreach ( $attribute['Options'] as $option ) : ?>
						<option value="<?php echo esc_attr( $option['Value'] ) ?>"<?php echo $isset ? selected( $option['Value'], $value, false ) : '' ?>>
							<?php echo esc_html( $option['Title'] ) ?>
						</option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
				<input type="text" id="extendedettributes_<?php echo $e_att_name ?>" name="ExtendedAttributes[<?php echo $e_att_name ?>]"<?php echo $required ?> value="<?php echo esc_attr( $value ) ?>">
				<?php endif; ?>
			</p><?php
		endforeach;
	}

}