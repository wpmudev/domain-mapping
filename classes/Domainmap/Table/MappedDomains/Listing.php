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
 * @since 4.1.5
 */
class Domainmap_Table_MappedDomains_Listing extends Domainmap_Table {

    /**
     * Returns site id column data to display.
     *
     * @since 4.1.5
     *
     * @param array $item The array of row data.
     * @return string The site id.
     */
    public function column_site_id( $item ) {
        echo $item->blog_id;
    }

    /**
     * Returns mapped column data to display.
     *
     * @since 4.1.5
     *
     * @param array $item The array of row data.
     * @return string The mapped domain name linking to blog's home page.
     */
    public function column_mapped_domain( $item ) {
        global $current_site;
        $suffix = $current_site->path != '/' ? $current_site->path : '';
        printf( '<a href="http://%1$s%2$s">%1$s%2$s</a>', Domainmap_Punycode::decode( $item->mapped_domain ), $suffix );
    }

    /**
     * Returns domain column data to display.
     *
     * @since 4.1.5
     *
     * @param array $item The array of row data.
     * @return string The domain name linking to blog's setting page.
     */
    public function column_domain( $item ) {

        ?>
        <a href="<?php echo admin_url("network/site-info.php?id={$item->blog_id}") ?>"><?php echo $item->domain; ?></a>
        <?php 
    }
    /**
     * Returns primary column data to display.
     *
     * @since 4.1.5
     *
     * @param array $item The array of row data.
     * @return string Yes/No .
     */
    public function column_primary( $item ) {
        echo $item->primary ? __("Yes", "domainmap") : __("No", "domainmap");
    }

    /**
     * Returns active column data to display.
     *
     * @since 4.1.5
     *
     * @param array $item The array of row data.
     * @return string Yes/No .
     */
    public function column_active( $item ) {
        echo $item->active ? __("Yes", "domainmap") : __("No", "domainmap");
    }


    public function column_health($item){
        $url = add_query_arg( array(
            'action' => Domainmap_Plugin::ACTION_HEALTH_CHECK,
            'nonce'  => wp_create_nonce( Domainmap_Plugin::ACTION_HEALTH_CHECK ),
            'domain' => $item->mapped_domain,
        ), admin_url( 'admin-ajax.php' ) );

        $health = get_site_transient( "domainmapping-{$item->mapped_domain}-health" );
        $health_message = __( 'need revalidate', 'domainmap' );
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
     * Returns domain column data to display.
     *
     * @since 4.1.5
     *
     * @param array $item The array of row data.
     * @return string The domain name.
     */
    public function column_actions( $item ) {

        $remove_link = add_query_arg( array(
            'action' => Domainmap_Plugin::ACTION_UNMAP_DOMAIN,
            'nonce'  => wp_create_nonce( Domainmap_Plugin::ACTION_UNMAP_DOMAIN ),
            'domain' => $item->mapped_domain,
        ), admin_url( 'admin-ajax.php' ) );
        ?>
        <div class="domainmapping-domains">
            <a href="<?php echo admin_url("/tools.php?page=domainmapping&switch_to_blog=" . $item->blog_id ); ?>" title="<?php _e("Edit Settings", "domainmap"); ?>" class=" dashicons-before dashicons-admin-settings"></a>
            <a style="position: inherit"  data-href="<?php echo $remove_link; ?>"  title="<?php _e("Remove Mapping", "domainmap"); ?>" class="domainmapping-btn domainmapping-map-remove dashicons-before dashicons-trash"></a>
        </div>
        <?php
    }



    /**
     * Returns table columns.
     *
     * @since 4.1.5
     *
     * @return array The array of table columns to display.
     */
    public function get_columns() {
        $cols =  array(
            'site_id'    => __( 'Site ID', 'domainmap' ),
            'mapped_domain'    => __( 'Mapped Domain', 'domainmap' ),
            'domain'    => __( 'Domain', 'domainmap' ),
            "health" => __( 'Health Status', 'domainmap' ),
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
     * @since 4.1.5
     *
     * @global wpdb $wpdb The database connection.
     */
    public function prepare_items() {
        global $wpdb;

        parent::prepare_items();

        $per_page = 20;
        $offset = ( $this->get_pagenum() - 1 ) * $per_page;


        $q = $wpdb->prepare( "
			SELECT mapped.domain AS mapped_domain, blog.`blog_id`, blog.`domain`, mapped.`is_primary`, mapped.`active`, blog.`site_id`
			  FROM " . DOMAINMAP_TABLE_MAP . " AS mapped
			  LEFT JOIN {$wpdb->blogs} AS blog ON mapped.blog_id = blog.blog_id
			 ORDER BY blog.blog_id DESC
			    LIMIT {$per_page}
			    OFFSET {$offset}
			", null
        );
        $this->items = $wpdb->get_results( $q );
        $total_items = $wpdb->get_var( 'SELECT FOUND_ROWS()' );
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil( $total_items / $per_page )
        ) );
    }

    /**
     * Generates content for a single row of the table.
     *
     * @since 4.1.5
     *
     * @param arra $item The current item.
     */
    public function single_row( $item ) {
        echo '<tr class="domainmapping-mapped-domain-item-'. $item->domain. '">';
        $this->single_row_columns( $item );
        echo '</tr>';
    }

    /**
     * Extra controls to be displayed between bulk actions and pagination
     *
     * @since 4.1.5
     * @access protected
     */
    public function extra_tablenav( $which ) {
       
    }

    /**
     * Returns bulk actions
     *
     * @since 4.1.5
     * @access protected
     * @return array bulk actions
     */
    public function get_bulk_actions() {
        return array();
    }

}