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
 * @since 4.2.0
 * @category Domainmap
 * @package Render
 * @subpackage Reseller
 */
class Domainmap_Render_Reseller_WHMCS_Purchase extends Domainmap_Render_Reseller_Iframe {

	/**
	 * Renders purchase form.
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
			<h3><?php _e( 'Order domain', 'domainmap' ) ?></h3>
			<div class="domainmapping-domains-wrapper domainmapping-box-content domainmapping-form">
				<div class="domainmapping-locker"></div>
				<form id="domainmapping-whmcs-order-form" method="post">
					<input type="hidden" name="sld" value="<?php echo esc_attr( $this->sld ) ?>">
					<input type="hidden" name="tld" value="<?php echo esc_attr( $this->tld ) ?>">

					<p class="domainmapping-info"><?php printf(
						__( 'You are about to order domain name %s for the selected period. Please, confirm the period below and click on order button.', 'domainmap' ),
						'<b>' . esc_html( strtoupper( $this->domain ) ) . '</b>',
						'<b>' . esc_html( $this->price ) . '</b>'
					) ?></p>

					<?php if ( is_wp_error( $this->errors ) ) : ?>
						<?php foreach ( $this->errors->get_error_messages() as $error ) : ?>
							<p class="domainmapping-info domainmapping-info-error"><?php echo esc_html( $error ) ?></p>
						<?php endforeach; ?>
					<?php endif; ?>
                    <?php $this->_render_domain_pricing() ?>
                    <?php $this->_render_payment_method() ?>
					<div class="domainmapping-form-buttons">
						<button class="button domainmapping-button domainmapping-push-right" id="dm-whmcs-domain-order-cancel"><?php _e( 'Cancel', 'domainmap' ) ?></button>
						<button type="submit" class="button button-primary domainmapping-button" id="dm-whmcs-domain-order-order"><i class="icon-shopping-cart icon-white"></i> <?php _e( 'Order domain', 'domainmap' ) ?></button>
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
   * Renders pricing options
   *
   * @since 4.2.0
   *
   * @access private
   */
    private function _render_domain_pricing(){
        /**
         * @var  $whmcs Domainmap_Reseller_WHMCS
         */
        $pricing = Domainmap_Reseller_WHMCS::get_domain_pricing();

        foreach( $pricing as $p ){
            if( $p["tld"] === "." . $this->tld  && !empty( $p['price'] ) ){
                $pricing = $p['price'];
            }
        }
        $currency = Domainmap_Plugin::instance()->get_reseller()->get_currency_symbol();
        ?>
        <p>
            <label for="dm_whmcs_domain_period" class="domainmapping-label"><?php _e( 'Domain Period:', 'domainmap' ) ?> <span class="domainmapping-field-required">*</span></label>
            <select  name="dm_whmcs_domain_period" id="dm_whmcs_domain_period">
            <?php $year = 1; foreach( $pricing as $key => $price ):?>
                <option <?php selected("0", $key); ?> value="<?php echo $year?>"> <?php printf( __("%s Year - %s%s", domain_map::Text_Domain), $year , $currency, $price );  ?> </option>
            <?php $year++; endforeach;?>
            </select>
        </p>
        <?php
    }

  /**
   * Renders payment method
   *
   * @since 4.2.0
   *
   * @access private
   */
    private function _render_payment_method(){
        /**
         * @var $whmcs Domainmap_Reseller_WHMCS
         */
        $whmcs = Domainmap_Plugin::instance()->get_reseller();

        if(  $whmcs->get_gateway()  ):
        ?>
        <p>
            <label for="dm_whmcs_domain_period" class="domainmapping-label"><?php _e( 'Payment method:', 'domainmap' ) ?></label>
            <strong><?php  echo $whmcs->get_gateway( null, true); ?></strong>
        </p>
        <?php
        endif;
    }


}