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
 * Mapped domains table.
 *
 * @category Domainmap
 * @package Table
 * @subpackage MappedDomains
 *
 * @since 4.2.0
 */

class Domainmap_Table_MappedDomains_Listing extends Domainmap_Table {

    function __construct( $args = array()  ){
        parent::__construct( array_merge( array(
            'search_box_label' => __( 'Search mapped domains' ),
            'single'           => 'domain',
            'plural'           => 'domains',
            'ajax'             => false,
            'search_box'       => true
        ), $args ) );
    }

    /**
     * Get a list of all, hidden and sortable columns, with filter applied
     *
     * @since 4.2.0
     * @access protected
     *
     * @return array
     */
    function get_column_info() {
        if ( isset( $this->_column_headers ) )
            return $this->_column_headers;
        $columns = get_column_headers( $this->screen );
        $hidden = get_hidden_columns( $this->screen );

        $sortable_columns = $this->get_sortable_columns();
        /**
         * Filter the list table sortable columns for a specific screen.
         *
         * The dynamic portion of the hook name, $this->screen->id, refers
         * to the ID of the current screen, usually a string.
         *
         * @since 4.2.0
         *
         * @param array $sortable_columns An array of sortable columns.
         */
        $_sortable = apply_filters( "manage_{$this->screen->id}_sortable_columns", $sortable_columns );

        $sortable = array();
        foreach ( $_sortable as $id => $data ) {
            if ( empty( $data ) )
                continue;

            $data = (array) $data;
            if ( !isset( $data[1] ) )
                $data[1] = false;

            $sortable[$id] = $data;
        }

        $this->_column_headers = array( $columns, $hidden, $sortable );
        return $this->_column_headers;
    }


    /**
     * Returns table columns.
     *
     * @since 4.2.0
     *
     * @return array The array of table columns to display.
     */
    public function get_columns() {
        $cols =  array(
            'site_id'    => __( 'Site ID', 'domainmap' ),
            'mapped_domain'    => __( 'Mapped Domain', 'domainmap' ),
            'domain'    => __( 'Original Address', 'domainmap' ),
            "health" => __( 'Health Status', 'domainmap' ),
            'dns' => __( 'DNS Configuration', 'domainmap' ),
            'primary'    => __( 'Primary', 'domainmap' ),
            'active'    => __( 'Active', 'domainmap' ),
            'actions'    => __( 'Actions', 'domainmap' ),
        );

        if( !filter_var( DOMAINMAPPING_ALLOWMULTI, FILTER_VALIDATE_BOOLEAN ) ){
            unset( $cols["primary"] ) ;
        }

        return $cols;
    }




    /**
     * Fetches records from database.
     *
     * @since 4.2.0
     *
     * @global wpdb $wpdb The database connection.
     */
    public function prepare_items() {
        global $wpdb;

        parent::prepare_items();

        $per_page = 20;
        $offset = ( $this->get_pagenum() - 1 ) * $per_page;

        $search_term = false;
        if ( isset( $_REQUEST['s'] ) && !empty( $_REQUEST['s'] )) {
            $search_term = "%" .  $wpdb->esc_like($_REQUEST['s'])  . "%";
        }

        $q = $wpdb->prepare( "
			SELECT SQL_CALC_FOUND_ROWS mapped.domain AS mapped_domain, blog.`blog_id`, blog.`domain`, mapped.`is_primary`,  mapped.`scheme`, mapped.`active`, blog.`site_id`
			  FROM " . DOMAINMAP_TABLE_MAP . " AS mapped
			  LEFT JOIN {$wpdb->blogs} AS blog ON mapped.blog_id = blog.blog_id
			 ORDER BY blog.blog_id DESC
			    LIMIT %d
			    OFFSET %d
			", $per_page, $offset
        );

        if( $search_term ){
            $q = $wpdb->prepare( "
			SELECT SQL_CALC_FOUND_ROWS mapped.domain AS mapped_domain, blog.`blog_id`, blog.`domain`, mapped.`is_primary`, mapped.`scheme`, mapped.`active`, blog.`site_id`
			  FROM " . DOMAINMAP_TABLE_MAP . " AS mapped
			  LEFT JOIN {$wpdb->blogs} AS blog ON mapped.blog_id = blog.blog_id
			  WHERE mapped.domain LIKE %s
			 ORDER BY blog.blog_id DESC
			    LIMIT %d
			    OFFSET %d
			", $search_term,  $per_page, $offset
            );
        }

        $this->items = $wpdb->get_results( $q, OBJECT );
        $total_items = $wpdb->get_var( 'SELECT FOUND_ROWS()' );
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil( $total_items / $per_page )
        ) );

    }


    /**
     * Returns site id column data to display.
     *
     * @since 4.2.0
     *
	 * @param object $item current row's record
     */
    public function column_site_id( $item ) {
        echo $item->blog_id;
    }

    /**
     * Returns mapped column data to display.
     *
     * @since 4.2
     *
	 * @param object $item current row's record
     */
    public function column_mapped_domain( $item ) {
        global $current_site;
        $suffix = $current_site->path != '/' ? $current_site->path : '';
        $scheme = $item->scheme ==  1 ? "https" : "http";
        printf( '<a class="domainmapping-mapped" href="%1$s://%2$s%3$s">%1$s://%2$s%3$s</a>', $scheme , Domainmap_Punycode::decode( $item->mapped_domain ), $suffix );
    }

    /**
     * Returns domain column data to display.
     *
     * @since 4.2.0
     *
	 * @param object $item current row's record
     */
    public function column_domain( $item ) {
        ?>
        <a href="<?php echo admin_url("network/site-info.php?id={$item->blog_id}") ?>"><?php echo get_site_url($item->blog_id); ?></a>
        <?php 
    }
    /**
     * Returns primary column data to display.
     *
     * @since 4.2.0
     *
	 * @param object $item current row's record
     */
    public function column_primary( $item ) {
        echo $item->is_primary ? __("Yes", "domainmap") : __("No", "domainmap");
    }

    /**
     * Renders active column data to display.
     *
     * @since 4.2
     *
	 * @param object $item current row's record
     */
    public function column_active( $item ) {
        echo $item->active ? __("Yes", "domainmap") : __("No", "domainmap");
    }


  /**
   * Renders health column
   *
   * @since 4.2.0
   *
   * @param $item current row's record
   */
    public function column_health($item){
        $url = add_query_arg( array(
            'action' => Domainmap_Plugin::ACTION_HEALTH_CHECK,
            'nonce'  => wp_create_nonce( Domainmap_Plugin::ACTION_HEALTH_CHECK ),
            'domain' => $item->mapped_domain,
        ), admin_url( 'admin-ajax.php' ) );

        $health = get_site_transient( "domainmapping-{$item->mapped_domain}-health" );
        $health_message = __( 'needs revalidation', 'domainmap' );
        $health_class = ' domainmapping-need-revalidate';
        if ( $health !== false ) {
            if ( $health ) {
                $health_class = ' domainmapping-valid-domain';
                $health_message = __( 'valid', 'domainmap' );
            } else {
                $health_class = ' domainmapping-invalid-domain';
                $health_message = __( 'invalid', 'domainmap' );
            }
        }

        ?>
        <div class="domainmapping-domains">
            <a class="domainmapping-map-state<?php echo $health_class ?>" href="<?php echo $url ?>" title="<?php _e( 'Refresh health status', 'domainmap' ) ?>">
                <?php echo $health_message ?>
            </a>
        </div>
        <?php
    }


  /**
   * Returns dns column
   *
   * @since 4.2.0
   *
   * @param $item current row's record
   * @return string
   */
    function column_dns( $item ) {
        global $dm_map;

        $_records = $dm_map->get_dns_config( ( object ) $item );
        $dns_config = "";

        foreach ( $_records as $_record ) {
            if (ip2long($_record['target']) == 0) {
                $_record['target'] .= ".";
            }
            $dns_config .= "<p>Host Name: <code>{$_record['host']}</code><br/>";
            $dns_config .= "Record Type: {$_record['type']}<br/>";
            $dns_config .= "Value: <code>{$_record['target']}</code>\n</p>";
        }

        return $dns_config;
    }

    /**
     * Renders domain column data to display.
     *
     * @since 4.2.0
	 * @param $item current row's record
     */

    public function column_actions( $item ) {

        $remove_link = add_query_arg( array(
            'action' => Domainmap_Plugin::ACTION_UNMAP_DOMAIN,
            'nonce'  => wp_create_nonce( Domainmap_Plugin::ACTION_UNMAP_DOMAIN ),
            'domain' => $item->mapped_domain,
        ), admin_url( 'admin-ajax.php' ) );
        $primary_class = $item->is_primary == 1 ? 'dashicons-star-filled' : 'dashicons-star-empty';
        $admin_ajax =  admin_url( 'admin-ajax.php' ) ;

        $select_primary = esc_url( add_query_arg( array(
            'action' => Domainmap_Plugin::ACTION_SELECT_PRIMARY_DOMAIN,
            'nonce'  => wp_create_nonce( Domainmap_Plugin::ACTION_SELECT_PRIMARY_DOMAIN ),
            'domain' =>  $item->mapped_domain,
        ), $admin_ajax ) );

        $deselect_primary = esc_url( add_query_arg( array(
            'action' => Domainmap_Plugin::ACTION_DESELECT_PRIMARY_DOMAIN,
            'nonce'  => wp_create_nonce( Domainmap_Plugin::ACTION_DESELECT_PRIMARY_DOMAIN ),
            'domain' =>  $item->mapped_domain,
        ), $admin_ajax ) );

      $toggle_scheme_link = esc_url( add_query_arg( array(
          'action' => Domainmap_Plugin::ACTION_TOGGLE_SCHEME,
          'nonce'  => wp_create_nonce( Domainmap_Plugin::ACTION_TOGGLE_SCHEME ),
          'domain' => $item->mapped_domain
      ), $admin_ajax) );
        ?>
        <div class="domainmapping-domains">
          <a class="domainmapping-map-toggle-scheme dashicons-before dashicons-admin-network" href="#" data-href="<?php echo esc_url( $toggle_scheme_link ) ?>" title="<?php _e( 'Toggle scheme', 'domainmap' ) ?>"></a>
          <?php if ( Domainmap_Render_Site_Map::_is_multi_enabled() ) : ?>
            <a style="position: inherit" class="domainmapping-map-primary dashicons-before <?php echo $primary_class ?>" href="#" data-select-href="<?php echo $select_primary ?>" data-deselect-href="<?php echo $deselect_primary ?>" title="<?php _e( 'Select as primary domain', 'domainmap' ) ?>"></a>
          <?php endif; ?>
            <a style="position: inherit"  data-href="<?php echo $remove_link; ?>"  title="<?php _e("Remove Mapping", "domainmap"); ?>" class="domainmapping-btn domainmapping-map-remove dashicons-before dashicons-trash"></a>
        </div>
        <?php
    }




    /**
     * Generates content for a single row of the table.
     *
     * @since 4.2.0
	 *
	 * @param $item current row's record
     */
    public function single_row( $item ) {
        echo '<tr class="domainmapping-mapped-domain-item-'. $item->domain. '">';
        $this->single_row_columns( $item );
        echo '</tr>';
    }

    /**
     * Returns associative array with the list of bulk actions available on this table.
     *
     * @since 4.2.0
     *
     * @access protected
     * @return array The associative array of bulk actions.
     */
    public function get_bulk_actions() {
        return array();
    }

}