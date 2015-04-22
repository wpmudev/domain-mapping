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
 * Base class for tabbed pages.
 *
 * @category Domainmap
 * @package Render
 *
 * @since 4.0.0
 * @abstract
 */
abstract class Domainmap_Render_Tabbed extends Domainmap_Render {

	protected $_tabs;
	protected $_active_tab;
	protected $_nonce_action;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param array $tabs The array of tabs.
	 * @param string $active The id of active tab.
	 * @param array $data Additional data required for rendering.
	 */
	public function __construct( $tabs, $active, $data = array() ) {
		parent::__construct( $data );
		$this->_tabs = $tabs;
		$this->_active_tab = $active;
	}

	/**
	 * Renders template.
	 *
	 * @since 4.0.0
	 *
	 * @access protected
	 */
	protected function _to_html() {
		$baseurl = esc_url_raw( add_query_arg( 'page', filter_input( INPUT_GET, 'page' ), current( explode( '?', $_SERVER['REQUEST_URI'] ) ) ) );

		?><div id="domainmapping-content" class="wrap">
			<?php $this->_render_header() ?>

			<div class="domainmapping-tab-switch">
				<ul>
					<?php foreach ( $this->_tabs as $tab => $label ) : ?>
					<li>
						<a<?php echo $this->_active_tab == $tab ? ' class="active"' : '' ?> href="<?php echo esc_url( add_query_arg( 'tab', $tab, $baseurl ) ) ?>">
							<?php echo $label ?>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
				<div class="domainmapping-clear"></div>
			</div>

			<div id="<?php echo $this->_active_tab ?>-tab" class="domainmapping-tab"><?php
				$this->_render_tab()
			?></div>
		</div><?php
	}

	/**
	 * Renders page header.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access protected
	 */
	protected abstract function _render_header();

	/**
	 * Renders tab content.
	 *
	 * @since 4.0.0
	 *
	 * @abstract
	 * @access protected
	 */
	protected abstract function _render_tab();

}