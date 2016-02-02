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
 * domain map utility class.
 *
 * @category Domainmap
 *
 * @since 4.4.2.0
 */
class Domainmap_Utils{

    /**
     * @var CHttpRequest instance
     */
    private $_http;

    function __construct()
    {
        $this->_http = new CHttpRequest();
        $this->_http->init();
        return $this;
    }

    /**
     * Returns original domain
     *
     * @param bool $with_www
     * @return mixed|string
     */
    public function get_original_domain( $with_www = false ){
        $home = network_home_url( '/' );
        $original_domain = parse_url( $home, PHP_URL_HOST );
        return $with_www ? "www." . $original_domain : $original_domain ;
    }

    /**
     * Imposes url scheme for mapped domains based on the settings
     *
     * @param $url
     * @return string
     */
    public function force_mapped_domain_url_scheme( $url ){
        switch( $this->force_ssl_on_mapped_domain( $url )  ){
            case 1:
                return set_url_scheme( $url, "https" );
                break;
            case 0:
                return set_url_scheme( $url, "http" );
                break;
            default:
                return $url;
        }
    }

    /**
     * Returns the forced scheme for the mapped domain
     *
     * @param string $domain
     * @return bool|string false when no scheme should be forced and https or http for the scheme
     */
    public function get_mapped_domain_scheme($domain = "" ){
        switch(  $this->force_ssl_on_mapped_domain( $domain ) ){
            case 0:
                $scheme = "http";
                break;
            case 1:
                $scheme = "https";
                break;
            default:
                $scheme = null;
                break;
        }

        return $scheme;
    }

    public function get_admin_scheme(){
        if( $this->is_original_domain() )
        return Domainmap_Plugin::instance()->get_option("map_force_admin_ssl") ? "https" : null;
    }

    /**
     * Swaps url scheme from http to https and vice versa
     *
     * @since 4.4.0.9
     * @param $url provided url
     * @return string
     */
    public function swap_url_scheme( $url ){
        $parsed_original_url = parse_url( $url );
        $alternative_scheme = null;
        if( isset( $parsed_original_url['scheme'] ) &&  $parsed_original_url['scheme'] === "https"  ){
            $alternative_scheme = "http";
        }elseif(  isset( $parsed_original_url['scheme'] ) &&  $parsed_original_url['scheme'] === "http" ){
            $alternative_scheme = "https";
        }

        return set_url_scheme( $url, $alternative_scheme );
    }

    /**
     * Checks if given domain should be forced to use https
     *
     * @since 4.2.0
     *
     * @param string $domain
     * @return bool
     */
    public function force_ssl_on_mapped_domain( $domain = "" ){
        global $wpdb, $dm_mapped;
        $_parsed = parse_url( $domain, PHP_URL_HOST );
        $domain = $_parsed ? $_parsed : $domain;
        $current_domain = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'];
        $domain = $domain === "" ? $current_domain  : $domain;
        $transient_key = domain_map::FORCE_SSL_KEY_PREFIX . $domain;

        if( is_object( $dm_mapped )  && $dm_mapped->domain === $domain ){ // use from the global dm_domain
            $force_ssl_on_mapped_domain = (int) $dm_mapped->scheme;
        }else{
            $force = get_transient( $transient_key );

            if( $force === false ){
                $force_ssl_on_mapped_domain = (int) $wpdb->get_var( $wpdb->prepare("SELECT `scheme` FROM `" . DOMAINMAP_TABLE_MAP . "` WHERE `domain`=%s", $domain) );
                set_transient($transient_key, $force_ssl_on_mapped_domain, 30 * MINUTE_IN_SECONDS);
            }else{
                $force_ssl_on_mapped_domain = $force;
            }
        }

        return apply_filters("dm_force_ssl_on_mapped_domain", $force_ssl_on_mapped_domain) ;
    }

    /**
     * Checks if current site resides in mapped domain
     *
     * @since 4.2.0
     *
     * @param null $domain
     *
     * @return bool
     */
    public function is_mapped_domain( $domain = null ){
        return !$this->is_original_domain( $domain );
    }

    /**
     * Checks if current page is login page
     *
     * @since 4.2.0
     *
     * @return bool
     */
    public function is_login(){
        global $pagenow;
        $needle = isset( $pagenow ) ? $pagenow : str_replace("/", "", $this->_http->getRequestUri() );
        $is_login = in_array( $needle, array( 'wp-login.php', 'wp-register.php' ) );
        return apply_filters("dm_is_login", $is_login, $needle, $pagenow) ;
    }

    /**
     * Checks to see if the passed $url is an admin url
     *
     * @param $url
     *
     * @return bool
     */
    public function is_admin_url( $url ){
        $parsed = parse_url( urldecode(  $url ) );

        return isset( $parsed['path'] ) ? strpos($parsed['path'], "/wp-admin") !== false : false;
    }

    /**
     * Checks if current site resides in original domain
     *
     * @since 4.2.0
     *
     * @param string $domain
     * @return bool true if it's original domain, false if not
     */
    public function is_original_domain( $domain = null ){

        $domain = parse_url( is_null( $domain ) ? $this->_http->hostinfo : $domain  , PHP_URL_HOST );

        /** MULTI DOMAINS INTEGRATION */
        if( class_exists( 'multi_domain' ) ){
            global $multi_dm;
            if( is_array( $multi_dm->domains ) ){
                foreach( $multi_dm->domains as $key => $domain_item){
                    if( $domain === $domain_item['domain_name'] || strpos($domain, "." . $domain_item['domain_name']) ){
                        return apply_filters("dm_is_original_domain", true, $domain);
                    }
                }
            }
        }

        $is_original_domain = $domain === $this->get_original_domain() || strpos($domain, "." . $this->get_original_domain());
        return apply_filters("dm_is_original_domain", $is_original_domain, $domain);
    }

    /**
     * Checks if $domain can be a domain
     *
     * @param $domain_name
     *
     * @since 4.4.0.3
     * @return bool
     */
    public function is_domain( $domain_name ){

        if( false === strpos($domain_name, ".") || empty( $domain_name ) ) return false;

        $domain_name = str_replace(array("http://", "www."), array("", ""), $domain_name);
        $domain_name = "http://" . $domain_name;
        return (bool) filter_var($domain_name, FILTER_VALIDATE_URL);
    }

    /**
     * Checks if current domain is a subdomain
     *
     * @since 4.2.0.4
     * @return bool
     */
    function is_subdomain(){
        $network_domain =  parse_url( network_home_url(), PHP_URL_HOST );
        return apply_filters("dm_is_subdomain",  (bool) str_replace( $network_domain, "", $_SERVER['HTTP_HOST']));
    }

    /**
     * Returns current domain
     *
     * @since 4.3.1
     * @return mixed
     */
    public function get_current_domain(){
        $home = home_url( '/' );
        return parse_url( $home, PHP_URL_HOST );
    }
}