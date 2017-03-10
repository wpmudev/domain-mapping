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
class Domainmap_Render_Reseller_Enom_Purchase extends Domainmap_Render_Reseller_Iframe {

	/**
	 * Renders purchase form.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 */
	protected function _render_page() {
		$cancel = filter_input( INPUT_GET, 'cancel', FILTER_VALIDATE_URL );

		?>
        <div id="wpwrap">
        <div id="domainmapping-content" class="domainmapping-tab domainmapping-iframe">
        <div id="domainmapping-iframe-content">
        <div id="domainmapping-box-iframe" class="domainmapping-box">
			<h3><?php _e( 'Purchase domain', 'domainmap' ) ?></h3>
			<div class="domainmapping-domains-wrapper domainmapping-box-content domainmapping-form">
				<div class="domainmapping-locker"></div>
				<form id="domainmapping-iframe-form" method="post">
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
						<button type="submit" class="button button-primary domainmapping-button"><i class="icon-shopping-cart icon-white"></i> <?php _e( 'Purchase domain', 'domainmap' ) ?></button>
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
	 * Renders credit card fields.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function _render_card_fields() {
		?><h4><i class="icon-list-alt"></i> <?php _e( 'Credit Card', 'domainmap' ) ?></h4>

		<p class="domainmapping-info">
			<?php _e( 'Supported card types are:', 'domainmap' ) ?> <b><?php echo implode( '</b>, <b>', (array)$this->cardtypes ) ?></b>
		</p>

		<p>
			<label for="card_number" class="domainmapping-label"><?php _e( 'Card Number:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="card_number" required name="card_number" maxlength="19" x-autocompletetype="cc-number" placeholder="0000 0000 0000 0000" value="<?php echo esc_attr( filter_input( INPUT_POST, 'card_number' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter credit card number.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="card_expiration" class="domainmapping-label"><?php _e( 'Card Expiration:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="card_expiration" required name="card_expiration" x-autocompletetype="cc-exp" placeholder="mm / yy" value="<?php echo esc_attr( filter_input( INPUT_POST, 'card_expiration' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter credit card expiration date.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="card_cvv2" class="domainmapping-label"><?php _e( 'CVV2:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="card_cvv2" required name="card_cvv2" maxlength="4" autocomplete="off" placeholder="xxx" value="<?php echo esc_attr( filter_input( INPUT_POST, 'card_cvv2' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter credit card security code.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="card_cardholder" class="domainmapping-label"><?php _e( "Cardholder's Name:", 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="card_cardholder" required name="card_cardholder" maxlength="60" x-autocompletetype="cc-name" placeholder="<?php echo esc_attr( $this->cardholder ) ?>" value="<?php echo esc_attr( filter_input( INPUT_POST, 'card_cardholder' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( "Enter cardholder's name.", 'domainmap' ) ?></span>
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

		?><h4><i class="icon-home"></i> <?php _e( 'Billing Information', 'domainmap' ) ?></h4>

		<p>
			<label for="billing_address" class="domainmapping-label"><?php _e( 'Address:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="billing_address" required name="billing_address" maxlength="60" x-autocompletetype="address-line1" value="<?php echo esc_attr( filter_input( INPUT_POST, 'billing_address' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( "Enter credit card billing address. The maximum length is 60 characters.", 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="billing_city" class="domainmapping-label"><?php _e( 'City:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="billing_city" required name="billing_city" maxlength="60" x-autocompletetype="city" value="<?php echo esc_attr( filter_input( INPUT_POST, 'billing_city' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( "Enter credit card billing city. The maximum length is 60 characters.", 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="billing_zip" class="domainmapping-label"><?php _e( 'Zip/Postal Code:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="billing_zip" required name="billing_zip" maxlength="15" x-autocompletetype="postal-code" value="<?php echo esc_attr( filter_input( INPUT_POST, 'billing_zip' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( "Enter credit card billing zip or postal code. The maximum length is 15 characters.", 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="billing_state" class="domainmapping-label"><?php _e( 'State/Province:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="billing_state" required name="billing_state" maxlength="60" x-autocompletetype="administrative-area" value="<?php echo esc_attr( filter_input( INPUT_POST, 'billing_state' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( "Enter credit card billing state or province. The maximum length is 60 characters.", 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="billing_country" class="domainmapping-label"><?php _e( 'Country:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<select id="billing_country" required name="billing_country">
				<option></option>
				<?php foreach ( $this->countries as $code => $country ) : ?>
				<option value="<?php echo esc_attr( $code ) ?>"<?php selected( $code, $billing_country ) ?>><?php echo esc_html( $country ) ?></option>
				<?php endforeach; ?>
			</select>
			<span class="domainmapping-descr"><?php esc_html_e( "Select credit card billing country.", 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="billing_phone" class="domainmapping-label"><?php _e( 'Phone:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="billing_phone" required name="billing_phone" maxlength="15" x-autocompletetype="tel" value="<?php echo esc_attr( filter_input( INPUT_POST, 'billing_phone' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( "Enter credit card billing phone number. Required format is +CountryCode.PhoneNumber, where CountryCode and PhoneNumber use only numeric characters. The maximum length is 15 characters.", 'domainmap' ) ?></span>
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
			<input type="text" id="registrant_first_name" required name="registrant_first_name" maxlength="60" x-autocompletetype="given-name" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_first_name' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant first name. The maximum length is 60 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_last_name" class="domainmapping-label"><?php _e( 'Last Name:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_last_name" required name="registrant_last_name" maxlength="60" x-autocompletetype="family-name" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_last_name' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant last name. The maximum length is 60 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_organization" class="domainmapping-label"><?php _e( 'Organization Name:', 'domainmap' ) ?></label>
			<input type="text" id="registrant_organization" name="registrant_organization" maxlength="60" x-autocompletetype="org" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_organization' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant organization name, this field is optional. The maximum length is 60 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_job_title" class="domainmapping-label"><?php _e( 'Job Title:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_job_title" required name="registrant_job_title" maxlength="60" x-autocompletetype="organization-title" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_job_title' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant job title. The maximum length is 60 characters.', 'domainmap' ) ?></span>
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
			<input type="text" id="registrant_zip" required name="registrant_zip" maxlength="15" x-autocompletetype="postal-code" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_zip' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant zip or postal code. The maximum length is 16 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_state" class="domainmapping-label"><?php _e( 'State/Province:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_state" required name="registrant_state" maxlength="60" x-autocompletetype="administrative-area" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_state' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant state or province. The maximum length is 60 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_country" class="domainmapping-label"><?php _e( 'Country:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<select id="registrant_country" required name="registrant_country" x-autocompletetype="country-name">
				<option></option>
				<?php foreach ( $this->countries as $code => $country ) : ?>
				<option value="<?php echo esc_attr( $code ) ?>"<?php selected( $code, $registrant_country ) ?>><?php echo esc_html( $country ) ?></option>
				<?php endforeach; ?>
			</select>
			<span class="domainmapping-descr"><?php esc_html_e( 'Select registrant country.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_email" class="domainmapping-label"><?php _e( 'Email:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="email" id="registrant_email" required name="registrant_email" maxlength="128" x-autocompletetype="email" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_email' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter email address for Whois. The maximum length is 128 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_phone" class="domainmapping-label"><?php _e( 'Phone:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
			<input type="text" id="registrant_phone" required name="registrant_phone" maxlength="20" x-autocompletetype="tel" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_phone' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant phone number. Required format is +CountryCode.PhoneNumber, where CountryCode and PhoneNumber use only numeric characters. The maximum length is 20 characters.', 'domainmap' ) ?></span>
		</p>

		<p>
			<label for="registrant_fax" class="domainmapping-label"><?php _e( 'Fax:', 'domainmap' ) ?></label>
			<input type="text" id="registrant_fax" name="registrant_fax" maxlength="20" x-autocompletetype="fax" value="<?php echo esc_attr( filter_input( INPUT_POST, 'registrant_fax' ) ) ?>">
			<span class="domainmapping-descr"><?php esc_html_e( 'Enter registrant fax number, this field is optional. Required format is +CountryCode.PhoneNumber, where CountryCode and PhoneNumber use only numeric characters. The maximum length is 20 characters.', 'domainmap' ) ?></span>
		</p><?php
	}

	/**
	 * Compares extra attributes by attribute ID.
	 *
	 * @since 4.1.0
	 *
	 * @access public
	 * @param array $a The array of first attribute to compare.
	 * @param array $b The array of second attribute to compare.
	 * @return int 0 if IDs equal, -1 if $b ID is more then $a ID, otherwise 1
	 */
	public function sort_ext_attributes( $a, $b ) {
		if ( !isset( $a['ID'] ) || !isset( $b['ID'] ) || $a['ID'] == $b['ID'] ) {
			return 0;
		}

		return (int)$a['ID'] < (int)$b['ID'] ? -1 : 1;
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

		$attributes = $this->ext_attributes;
		usort( $attributes, array( $this, 'sort_ext_attributes' ) );

		?><h4><i class="icon-asterisk"></i> <?php _e( 'Additional Registrar Information', 'domainmap' ) ?></h4><?php

		foreach ( $attributes as $attribute ) :
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
					<ul class="domainmapping-descr">
						<?php foreach ( $attribute['Options'] as $option ) : ?>
							<?php if ( !empty( $option['Description'] ) ) : ?>
								<li>
									<b><?php echo esc_html( $option['Title'] ) ?></b> - <?php echo esc_html( $option['Description'] ) ?>
								</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
				<input type="text" id="extendedettributes_<?php echo $e_att_name ?>" name="ExtendedAttributes[<?php echo $e_att_name ?>]"<?php echo $required ?> value="<?php echo esc_attr( $value ) ?>">
				<?php endif; ?>
			</p><?php
		endforeach;
	}

}