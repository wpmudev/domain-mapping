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
 * @since 4.2.0
 */
class Domainmap_Render_Reseller_WHMCS_Settings extends Domainmap_Render {

    private static function _reseller(){
        return new Domainmap_Reseller_WHMCS();
    }
	/**
	 * Renders eNom settings notifications.
	 *
	 * @since 4.2.0
	 *
	 * @access private
	 */
	private function _render_notifications() {
		?><div id="domainmapping-whmcs-header">
			<div id="domainmapping-whmcs-logo"></div>
		</div>

		<div class="domainmapping-info">
            <?php _e( 'Keep in mind that to start using WHMCS API you have to white list and authorize your IP address. Go to "My Account" > General Settings > Security tab and add your IP to the list under "API IP Access Restriction"' , 'domainmap' ); ?></div>
        <?php if( !$this->valid ): ?>
		<div class="domainmapping-info">
			<b><?php esc_html_e( 'Signup for a WHMCS account.', 'domainmap' ) ?></b><br>
			<a target="_blank" href="http://www.whmcs.com/order-now/"><?php esc_html_e( 'Register new WHMCS account', 'domainmap' ) ?></a>.
            </div>
            <?php
            endif;
	}

	/**
	 * Renders account credentials settings.
	 *
	 * @since 4.2.0
	 *
	 * @access private
	 */
	private function _render_account_settings() {
		// it is a bad habit to show raw password even if we use password input field
		// so lets shuffle it and render in the password field
		$pwd = str_shuffle( (string) $this->pwd );
		// we save shuffle hash to see on POST if the password was changed by an user
		$pwd_hash = md5( $pwd );
		?><h4 class="domainmapping-block-header"><?php _e( 'Account credentials:', 'domainmap' ) ?></h4>

		<?php if ( empty($this->uid) || empty($this->pwd) ) : ?>
			<div class="domainmapping-info domainmapping-info-error">
				<p><?php _e( "Maybe: Warning you haven't entered any API credentials, the purchase domain tab will not show till you've successfully saved your API credentials", 'domainmap' ) ?></p>
			</div>
		<?php endif; ?>

		<?php if ( $this->valid === false ) : ?>
		<div class="domainmapping-info domainmapping-info-error">
			<p><?php _e( 'Looks like your credentials are invalid. Please, check the errors sent by WHMCS server:', 'domainmap' ) ?></p>
			<?php if ( is_wp_error( $this->errors ) ) : ?>
				<ul>
					<li>
						<b><?php echo implode( '</b></li><li><b>', array_map( 'esc_html', $this->errors->get_error_messages() ) ) ?></b>
					</li>
				</ul>
			<?php endif; ?>
		</div>
		<?php endif; ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="whmcs-api" class="domainmapping-label"><?php _e( 'WHMCS url:', 'domainmap' ) ?></label>
                    </th>
                    <td>
                        <input type="text" id="whmcs-api" class="regular-text" name="map_reseller_whmcs_api" value="<?php echo esc_attr( $this->api ) ?>" autocomplete="off">
                        <p class="description">Url to your installation of WHMCS</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="whmcs-uid" class="domainmapping-label"><?php _e( 'Username:', 'domainmap' ) ?></label>
                    </th>
                    <td>
                        <input type="text" id="whmcs-uid" class="regular-text" name="map_reseller_whmcs_uid" value="<?php echo esc_attr( $this->uid ) ?>" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="whmcs-pwd" class="domainmapping-label"><?php _e( 'Password:', 'domainmap' ) ?></label>
                    </th>
                    <td>
                        <input type="password" id="whmcs-pwd" class="regular-text" name="map_reseller_whmcs_pwd" value="<?php echo esc_attr( $pwd ) ?>" autocomplete="off">
                    </td>
                </tr>
            </tbody>
        </table>
			<input type="hidden" name="map_reseller_whmcs_pwd_hash" value="<?php echo $pwd_hash ?>">
		<?php
	}

    /**
     * Renders domain pricing.
     *
     * @sine 4.2.0
     *
     * @access private
     */
    private function _render_domain_pricing() {

        $this->tlds = !$this->tlds ? array( array(
            "tld" => ".com",
            "price" => array(
                0 => "5.00"
            )
        ) ) : $this->tlds;

        $prices_vals = array_values($this->tlds);
        $prices_count =  count( $prices_vals[0]['price'] );
        $hide_delete_row = count($this->tlds) === 1;
        $hide_delete_col = $prices_count === 1;
        ?><h4 class="domainmapping-block-header"><?php _e( 'Define domain pricing:', 'domainmap' ) ?></h4>

        <div>
            <table class="wp-list-table widefat dm_whmcs_tlds">
                <thead>
                    <tr>
                        <th scope="col">
                            TLD
                        </th>

                    <?php for ($i = 0 ; $i < $prices_count ; $i++): ?>
                        <th scope="col">
                            <?php printf(__("<span class='dm_year_count'>%d</span> Year(s)"), $i+1 ); ?>

                            <button <?php echo $hide_delete_col ? "disabled='disabled'" : ""; ?> class="dashicons-before <?php echo $hide_delete_col ? "domainmapping-hidden" : ""; ?> dashicons-trash dm_whmcs_tlds_remove_col"></button>

                        </th>
                    <?php endfor ?>
                    <th scope="col" id="dm_whmcs_tlds_add_col" class="inaactive_cell">
                        <a href="#0" class="dashicons-before dashicons-plus"></a>
                    </th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 0 ; foreach ( $this->tlds as $tld ):  ?>
                    <tr>

                        <td>
                            <input type="text" name="dm_whmcs_tld[<?php echo $i; ?>][tld]" value="<?php echo $tld['tld']; ?>" />
                        </td>
                            <?php
                            $prices = $tld['price'];
                            $pi = 0;
                            foreach ( $prices as $price ): ?>
                        <td>
                            <input type="text" class="dm_whmcs_price_cell" name="dm_whmcs_tld[<?php echo $i ?>][price][<?php echo $pi ?>]" value="<?php echo $price; ?>" />
                        </td>
                            <?php $pi++; endforeach; ?>
                        <th  class="inaactive_cell">

                            <button <?php echo $hide_delete_row ? "disabled='disabled'" : ""; ?> class="dashicons-before dashicons-trash dm_whmcs_tlds_remove_row <?php echo $hide_delete_row ? "domainmapping-hidden" : ""; ?>"></button>
                        </td>
                    </tr>
                <?php $i++; endforeach; ?>
                    <tr id="dm_whmcs_tlds_add_row" class="inactive_row">
                        <td>
                            <a href="#0" class="dashicons-before dashicons-plus"></a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

	/**
	 * Renders payment gateways settings.
	 *
	 * @sine 4.2.0
	 *
	 * @access private
	 */
	private function _render_payment_settings() {
		?><h4 class="domainmapping-block-header"><?php _e( 'Select payment gateway:', 'domainmap' ) ?></h4>


		<div>
            <?php if( count( $this->gateways ) > 0 ): ?>
			<ul>
				<?php foreach ( $this->gateways as $key => $label ) : ?>
				<li>
					<label>
						<input type="radio" class="domainmapping-radio" name="map_reseller_whmcs_payment" value="<?php echo esc_attr( $key ) ?>"<?php checked( $key, $this->gateway )  ?>>
						<?php echo esc_html( $label ) ?>
					</label>
				</li>
				<?php endforeach; ?>
			</ul>
            <?php else: ?>
            <div class="domainmapping-info domainmapping-info-error">
                <?php _e("Please make sure you have correctly entered your login credentials and save again to retrieve your payment methods."); ?>
            </div>
            <?php endif; ?>
		</div><?php
	}


    /**
     * Renders payment gateways settings.
     *
     * @sine 4.2.0
     *
     * @access private
     */
    private function _render_registration_settings() {
        ?><h4 class="domainmapping-block-header"><?php _e( 'Client registration:', 'domainmap' ) ?></h4>
        <div>
            <label for="whmcs-client-registration" id="whmcs-client-registration-label">
                <input type="checkbox" id="whmcs-client-registration" <?php checked(  $this->enable_registration, true ); ?>  name="map_reseller_whmcs_client_registration">
                <?php _e( 'Enable client registration', 'domainmap' ) ?>
            </label>
        </div>

       <?php
    }

	private function _render_currency_settings(){
		$currencies = DM_Currencies::get_currency_list();
		$current = $this->_reseller()->get_currency();
		?><h4 class="domainmapping-block-header"><?php _e( 'Currency:', 'domainmap' ) ?></h4>
		<div>
			<label for="map_reseller_currency" id="whmcs_currency_label">
				<select name="map_reseller_currency" id="map_reseller_currency">
					<?php foreach($currencies as $key => $currency): ?>
						<option value="<?php echo $key ?>" <?php selected($current, $key); ?> ><?php echo $currency[0]; ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>

	<?php
	}

	/**
	 * Renders template.
	 *
	 * @since 4.2.0
	 *
	 * @access protected
	 */
	protected function _to_html() {
		$this->_render_notifications();
		$this->_render_account_settings();
        $this->_render_registration_settings();
        $this->_render_domain_pricing();
		$this->_render_currency_settings();
		$this->_render_payment_settings();
	}

}