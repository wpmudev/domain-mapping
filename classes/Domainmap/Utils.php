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
     * The instance of wpdb class.
     *
     * @since 4.0.0
     *
     * @access protected
     * @var wpdb
     */
    protected $_wpdb = null;

    /**
     * @var CHttpRequest instance
     */
    private $_http;

    /**
     * The array of mapped domains.
     *
     * @since 4.1.0
     *
     * @access private
     * @var array
     */
    private static $_mapped_domains = array();

    /**
     * The array of mapped domains.
     *
     * @since 4.4.2.2
     *
     * @access private
     * @var array
     */
    private static $_mapped_primary_domains = array();

    /**
     * The array of original domains.
     *
     * @since 4.1.0
     *
     * @access private
     * @var array
     */
    private static $_original_domains = array();

    /**
     * Original domain
     *
     * @var string
     */
    private static $_original_domain;

    /**
     * Stores schemes for various domains
     *
     * @var array
     */
    private static $_schemes = array();

    function __construct()
    {
        global $wpdb;

        $this->_wpdb = $wpdb;
        $this->_http = new CHttpRequest();
        $this->_http->init();

        if( array() === self::$_mapped_domains )
            $this->_set_mapped_domains();

        return $this;
    }

    /**
     * Fills up $_mapped_domains array
     *
     * @since 4.4.2.1
     */
    private function _set_mapped_domains(){
        $results = $this->_wpdb->get_results( "SELECT blog_id, domain, is_primary  FROM " . DOMAINMAP_TABLE_MAP );
        foreach( $results as $result ){
            self::$_mapped_domains[ $result->blog_id ] = $result->domain;
            if( $result->is_primary  )
                self::$_mapped_primary_domains[ $result->blog_id ] = $result->domain;
        }
    }

    /**
     * Returns mapped domains
     *
     * @since 4.4.2.1
     * @return array|null|object
     */
    public function get_mapped_domains(){
        return self::$_mapped_domains;
    }

    /**
     * Returns primary mapped domains
     *
     * @since 4.4.2.1
     * @return array|null|object
     */
    public function get_mapped_primary_domains(){
        return self::$_mapped_primary_domains;
    }

    /**
     * Returns original domain
     *
     * @param bool $with_www
     * @return mixed|string
     */
    public function get_original_domain( $with_www = false ){
        if( self::$_original_domain ){
            $original_domain = self::$_original_domain;
        }else{
            $home = network_home_url( '/' );
            $original_domain = parse_url( $home, PHP_URL_HOST );
            self::$_original_domain = $original_domain;
        }
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

    public function get_admin_scheme( $url = null ){
        if( is_null( $url ) )
            return  Domainmap_Plugin::instance()->get_option("map_force_admin_ssl") ? "https" : null;
        else
            return $this->is_original_domain( $url ) && Domainmap_Plugin::instance()->get_option("map_force_admin_ssl") ? "https" : null;
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
        global $dm_mapped;
        $_parsed = parse_url( $domain, PHP_URL_HOST );
        $domain = $_parsed ? $_parsed : $domain;
        $current_domain = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : $_SERVER['HTTP_HOST'];
        $domain = $domain === "" ? $current_domain  : $domain;
        $domain = str_replace("www.", "", $domain );

        if( $this->is_original_domain( $domain ) && is_object( $dm_mapped ) ) return $dm_mapped->scheme;


        if( is_object( $dm_mapped )  && $dm_mapped->domain === $domain ){ // use from the global dm_domain
            $force_ssl_on_mapped_domain = (int) $dm_mapped->scheme;
        }else{

            if( !isset( self::$_schemes[ $domain  ] ) ){
                $force_ssl_on_mapped_domain = self::$_schemes[ $domain ] = (int) $this->_wpdb->get_var( $this->_wpdb->prepare("SELECT `scheme` FROM `" . DOMAINMAP_TABLE_MAP . "` WHERE `domain`=%s", $domain) );
            }else{
                $force_ssl_on_mapped_domain = self::$_schemes[ $domain ];
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
        if( !empty( $domain ) && in_array( $domain, self::$_mapped_domains )  ) return true;
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
        $domain = empty( $domain ) ? $this->_http->hostinfo : "http://" . str_replace(array("http://", "https://"), "", $domain);

        $domain = parse_url( $domain , PHP_URL_HOST );
        $domain = str_replace("www.", "", $domain);
        if( in_array( $domain, self::$_original_domains ) ) return apply_filters("dm_is_original_domain", true, $domain);
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

        $is_original_domain = $domain === $this->get_original_domain()
            || strpos($domain, "." . $this->get_original_domain())
            || $domain === str_replace("www.", "", $this->get_original_domain() );
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

    /**
     * Retrieves frontend redirect type
     *
     * @since 4.0.3
     * @return string redirect type: mapped, user, original
     */
    public function get_frontend_redirect_type() {
        return get_option( 'domainmap_frontend_mapping', 'mapped' );
    }

    /**
     * Fetches mapped domain from the db
     *
     * @since 4.3.1
     * @param $blog_id
     *
     * @return null|string
     */
    public function _fetch_mapped_domain( $blog_id ) {
        $errors = $this->_wpdb->suppress_errors();

        $sql    = domain_map::allow_multiple()
            ? sprintf( "SELECT domain, is_primary FROM %s WHERE blog_id = %d ORDER BY is_primary DESC, id ASC LIMIT 1", DOMAINMAP_TABLE_MAP, $blog_id )
            : sprintf( "SELECT domain, is_primary FROM %s WHERE blog_id = %d ORDER BY id ASC LIMIT 1", DOMAINMAP_TABLE_MAP, $blog_id );
        $domain = $this->_wpdb->get_row( $sql, OBJECT );

        $this->_wpdb->suppress_errors( $errors );

        return apply_filters("dm_fetch_mapped_domain", $domain, $blog_id);
    }

    /**
     * Returns mapped domain for current blog.
     *
     * @since 4.0.3
     *
     * @access private
     * @param int|bool $blog_id The id of a blog to get mapped domain for.
     * @param bool $consider_front_redirect_type is it related to frontend
     * @return string|boolean Mapped domain on success, otherwise FALSE.
     */
    public function get_mapped_domain( $blog_id = false, $consider_front_redirect_type = true ) {
        // use current blog id if $blog_id is empty
        if ( !$blog_id ) {
            $blog_id = get_current_blog_id();
        }

        // if we have already found mapped domain, then return it
        if ( isset( self::$_mapped_primary_domains[$blog_id] ) ) {
            return self::$_mapped_primary_domains[$blog_id];
        }

        // if we have already found mapped domain, then return it
        if ( isset( self::$_mapped_domains[$blog_id] ) ) {
            return self::$_mapped_domains[$blog_id];
        }

        $domain = '';

        if ( $consider_front_redirect_type && $this->get_frontend_redirect_type() == 'user'  ) {
            $domain = is_admin() && $this->is_original_domain() ? $domain : $_SERVER['HTTP_HOST'];
        } else {
            // fetch mapped domain
            $fetched_domain = $this->_fetch_mapped_domain( $blog_id );

            $domain = isset( $fetched_domain->domain ) ? $fetched_domain->domain : false;
            $is_primary = isset( $fetched_domain->is_primary ) ? $fetched_domain->is_primary : false;
        }

        // save mapped domain into local cache
        if( $is_primary )
            self::$_mapped_primary_domains[$blog_id] = $domain;
        else
            self::$_mapped_domains[$blog_id] = $domain;

        return apply_filters("dm_mapped_domain", $domain, $blog_id, $consider_front_redirect_type);
    }

    /**
     * Encodes URL component. This method is used in preg_replace_callback call.
     *
     * @since 4.1.0
     * @see self::_parse_mb_url()
     *
     * @static
     * @access private
     * @param array $matches The array of matched elements.
     * @return string
     */
    private static function _parse_mb_url_urlencode( $matches ) {
        return urlencode( $matches[0] );
    }

    /**
     * Parses a URL and returns an associative array containing any of the
     * various components of the URL that are present. This implementation
     * supports UTF-8 URLs and parses them properly.
     *
     * @since 4.1.0
     * @link http://www.php.net/manual/en/function.parse-url.php#108787
     *
     * @static
     * @access private
     * @param string $url The URL to parse.
     * @return array The array of URL components.
     */
    public function parse_mb_url( $url ) {
        return array_map( 'urldecode', (array) parse_url( preg_replace_callback( '%[^:/?#&=\.]+%usD', __CLASS__ . '::_parse_mb_url_urlencode', $url ) ) );
    }

    /**
     * Builds URL from components received after parsing a URL.
     *
     * @since 4.1.0
     *
     * @static
     * @access private
     * @param array $components The array of URL components.
     * @return string Built URL.
     */
    public function build_url( $components ) {
        $scheme = isset( $components['scheme'] ) ? $components['scheme'] . '://' : '';
        $host = isset( $components['host'] ) ? $components['host'] : '';
        $port = isset( $components['port'] ) ? ':' . $components['port'] : '';

        $user = isset( $components['user'] ) ? $components['user'] : '';
        $pass = isset( $components['pass'] ) ? ':' . $components['pass'] : '';
        $pass = $user || $pass ? $pass . '@' : '';

        $path = isset( $components['path'] ) ? $components['path'] : '';
        $query = isset( $components['query'] ) ? '?' . $components['query'] : '';
        $fragment = isset( $components['fragment'] ) ? '#' . $components['fragment'] : '';

        $url = $scheme . str_replace("//", "/", $user . $pass . $host . $port . $path . $query . $fragment );
        return apply_filters("dm_built_url", $url, $components) ;
    }

    /**
     * Unswaps URL to use original domain.
     *
     * @since 4.1.0
     * @filter unswap_url
     *
     * @access public
     * @global object $current_site Current site object.
     * @param string $url Current URL to unswap.
     * @param int|bool|null $blog_id The blog ID to which current URL is related to.
     * @param bool $include_path whether to include the url path
     * @return string Unswapped URL.
     */
    public function unswap_url( $url, $blog_id = false, $include_path = true ){
        global $current_site;

        // if no blog id is passed, then take current one
        if ( !$blog_id ) {
            $blog_id = get_current_blog_id();
        }

        // check if we have already found original domain for the blog
        if ( !array_key_exists( $blog_id, self::$_original_domains ) ) {
            self::$_original_domains[$blog_id] = $this->_wpdb->get_var( sprintf(
                "SELECT option_value FROM %s WHERE option_name = 'siteurl'",
                $this->_wpdb->options
            ) );
        }

        if ( empty( self::$_original_domains[$blog_id] ) ) {
            return $url;
        }

        $url_components = $this->parse_mb_url( $url );
        $orig_components = $this->parse_mb_url( self::$_original_domains[$blog_id] );


        $url_components['host'] = $orig_components['host'];

        $orig_path = isset( $orig_components['path'] ) ? $orig_components['path'] : '';
        $url_path = isset( $url_components['path'] ) && $include_path ? $url_components['path'] : '';

        $url_components['path'] = str_replace( '//', '/', $current_site->path . $orig_path . $url_path );
        $unwapped_url = $this->build_url( $url_components );

        return apply_filters("dm_unswaped_url", $unwapped_url, $url, $blog_id, $include_path ) ;
    }

    /**
     * Swaps URL from original to mapped one.
     *
     * @since 4.1.0
     * @filter home_url 10 4
     * @filter site_url 10 4
     * @filter includes_url 10 2
     * @filter content_url 10 2
     * @filter plugins_url 10 3
     *
     * @param $url
     * @param bool $path
     * @param bool $orig_scheme
     * @param bool $blog_id
     * @param bool $consider_front_redirect_type
     *
     * @return string
     */
    public function swap_to_mapped_url( $url, $path = false, $orig_scheme = false, $blog_id = false, $consider_front_redirect_type = true ) {

        // parse current url
        $components = $this->parse_mb_url( $url );

        if ( empty( $components['host'] ) ) {
            return apply_filters("dm_swap_mapped_url", $url, $path, $orig_scheme, $blog_id);
        }



        // find mapped domain
        $mapped_domain = $this->get_mapped_domain( $blog_id, $consider_front_redirect_type );

        if ( !$mapped_domain || $components['host'] == $mapped_domain ) {
            return apply_filters("dm_swap_mapped_url", $url, $path, $orig_scheme, $blog_id);
        }

        $components['host'] = $mapped_domain;
        $components['path'] = "/" . $path;

        return apply_filters("dm_swap_mapped_url", $this->build_url( $components ), $path, $orig_scheme, $blog_id);
    }
}