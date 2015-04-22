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
 * Base class for network options tabbed pages.
 *
 * @category Domainmap
 * @package Render
 *
 * @since 4.0.0
 * @abstract
 */
abstract class Domainmap_Render_Network extends Domainmap_Render_Tabbed {

	protected $_nonce_action;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param array $tabs The array of tabs.
	 * @param string $active The id of active tab.
	 * @param string $nonce_action The nonce action.
	 * @param array $data Additional data required for rendering.
	 */
	public function __construct( $tabs, $active, $nonce_action = false, $data = array() ) {
		parent::__construct( $tabs, $active, $data );
		$this->_nonce_action = $nonce_action;
	}

	/**
	 * Renders page header.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 */
	protected function _render_header() {
		echo '<h2>', __( 'Domain Mapping', 'domainmap' ), '</h2>';
	}

	/**
	 * Renders template.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 */
	protected function _to_html() {
		?><form action="<?php echo esc_url_raw( add_query_arg( 'noheader', 'true' ) ) ?>" method="post">
			<?php if ( $this->_nonce_action ) : ?>
				<?php wp_nonce_field( $this->_nonce_action ) ?>
			<?php endif; ?>
			<?php parent::_to_html() ?>
		</form><?php
	}

}