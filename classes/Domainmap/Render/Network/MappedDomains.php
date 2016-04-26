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
 * Main class for mapped domains tab.
 *
 * @category Domainmap
 * @package Render
 * @subpackage MappedDomains
 *
 * @since 4.2.0
 */
class Domainmap_Render_Network_MappedDomains extends Domainmap_Render_Network {

    /**
     * @var $table Domainmap_Table
     */
    public $table = "";

    /**
     * Renders tab content.
     *
     * @since 4.2.0
     *
     * @access protected
     */
    protected function _render_tab() {
        $this->table->prepare_items();
        ?>
        <div id="domainmapping-mapped-domains-table">
        <?php
        $this->table->views();
        $this->table->search_box(__('Search mapped domains', domain_map::Text_Domain), "mapped_domain");
        $this->table->display();
        ?>
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
        ?><form action="" method="post">
        <?php if ( $this->_nonce_action ) : ?>
            <?php wp_nonce_field( $this->_nonce_action ) ?>
        <?php endif; ?>
        <?php parent::_to_html() ?>
        </form><?php
    }
}