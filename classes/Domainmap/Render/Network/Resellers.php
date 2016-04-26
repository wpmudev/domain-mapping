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
 * Base class for tabbed paged.
 *
 * @category Domainmap
 * @package Render
 * @subpackage Network
 *
 * @since 4.0.0
 */
class Domainmap_Render_Network_Resellers extends Domainmap_Render_Network {

	/**
	 * Renders page header.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 */
	protected function _render_header() {
		parent::_render_header();

		if ( filter_input( INPUT_GET, 'saved', FILTER_VALIDATE_BOOLEAN ) ) :
			echo '<div id="message" class="updated fade">', __( 'Options updated.', 'domainmap' ), '</div>';
		endif;

		if ( filter_input( INPUT_GET, 'registered', FILTER_VALIDATE_BOOLEAN ) ) :
			echo '<div id="message" class="updated fade">', __( 'Account was registered successfully.', 'domainmap' ), '</div>';
		endif;
	}

	/**
	 * Renders tab content.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 */
	protected function _render_tab() {
		$selected = false;

		$log_levels = array(
			Domainmap_Reseller::LOG_LEVEL_ALL      => __( 'All requests', 'domainmap' ),
			Domainmap_Reseller::LOG_LEVEL_ERRORS   => __( 'Failed requests', 'domainmap' ),
			Domainmap_Reseller::LOG_LEVEL_DISABLED => __( 'Disable login', 'domainmap' ),
		);

		?><div>
			<h4 class="domainmapping-block-header"><?php _e( 'Select reseller API requests log level:', 'domainmap' ) ?></h4>

			<ul><?php
				foreach ( $log_levels as $level => $label ) :
					?><li>
						<label>
							<input type="radio" class="domainmapping-radio" name="map_reseller_log"<?php checked( $level, (int)$this->map_reseller_log ) ?> value="<?php echo esc_attr( $level ) ?>">
							<?php echo esc_html( $label ) ?>
						</label>
					</li><?php
				endforeach;
			?></ul>
		</div>

		<div>
			<h4 class="domainmapping-block-header"><?php _e( 'Reseller provider:', 'domainmap' ) ?></h4>

			<p><?php
				esc_html_e( "Want to sell domains to your users? Select reseller provider and you will be able to register an account (if you do not yet have a domain reseller account) and setup an ability to purchase domains via the dashboard in your network site's admin.", 'domainmap' )
			?></p>

			<ul class="domainmapping-resellers-switch"><?php
				foreach ( $this->resellers as $hash => $reseller ) :
				?><li>
					<label>
						<input type="radio" class="domainmapping-reseller-switch domainmapping-radio" name="map_reseller"<?php checked((int) $hash, (int) $this->map_reseller ) ?> value="<?php echo esc_attr( $hash ) ?>">
						<?php echo esc_html( $reseller->get_title() ) ?>
						<?php $selected = $hash == $this->map_reseller ? $hash : $selected ?>
					</label>
				</li><?php
				endforeach;

				?><li>
					<label>
						<input type="radio" class="domainmapping-reseller-switch domainmapping-radio" name="map_reseller"<?php checked( empty( $selected ) ) ?>>
						<?php _e( "Don't use any", 'domainmap' ) ?>
					</label>
				</li>
			</ul>
		</div><?php

		foreach ( $this->resellers as $hash => $reseller ) :
			?><div id="reseller-<?php echo $hash ?>" class="domainmapping-reseller-settings<?php echo $selected == $hash ? ' active' :'' ?>">
				<?php $reseller->render_options() ?>
			</div><?php
		endforeach;

		?><p class="submit">
			<button type="submit" class="button button-primary domainmapping-button">
				<i class="icon-ok icon-white"></i> <?php _e( 'Save Changes', 'domainmap' ) ?>
			</button>
		</p><?php
	}

}