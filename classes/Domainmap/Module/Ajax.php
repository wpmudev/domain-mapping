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
 * The general module for handling AJAX requests.
 *
 * @category Domainmap
 * @package Module
 * @subpackage Ajax
 *
 * @since 4.0.0
 */
class Domainmap_Module_Ajax extends Domainmap_Module {

	/**
	 * Validates domain name.
	 *
	 * @since 4.0.0
	 * @link http://stackoverflow.com/questions/1755144/how-to-validate-domain-name-in-php/4694816#4694816
	 *
	 * @static
	 * @access protected
	 * @param string $domain The domain name to validate.
	 * @return boolean TRUE if domain name is valid, false if it's invalid and 0 if it's prohibited.
	 */
    protected function _validate_domain_name( $domain, $mapping = false) {
        $is_valid = true;
        $map_verifydomain = $this->_plugin->get_option("map_verifydomain");
        if( !$map_verifydomain ) $is_valid = true;

	    $is_valid = $this->_plugin->is_prohibited_domain( $domain ) ? 0 : true;

	    if( $this->_plugin->get_option("map_disallow_subdomain") && $is_valid ){
		    $is_valid = $is_valid && strpos( $domain, "." . $this->utils()->get_original_domain() ) === false;
	    }

	    if( !$is_valid ){
		    return apply_filters('dm_validate_domain_name', $is_valid, $domain, $mapping);
	    }

        $domain = Domainmap_Punycode::encode($domain);

        /**
         * If it's a mapping, check if mapped domain is similar to original domain or www.originaldomain
         */

		if( $mapping && in_array( $domain, array( $this->utils()->get_original_domain(), $this->utils()->get_original_domain( true ) ) ) ) $is_valid = false;
        $is_valid = preg_match( "/^([A-Za-z0-9](-*[A-Za-z0-9])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain ) //valid chars check
            && preg_match( "/^.{1,253}$/", $domain ) //overall length check
            && preg_match( "/^[^\.]{1,63}(\.[^\.]{2,63})+$/", $domain ) //length of each label
        ;

        return apply_filters('dm_validate_domain_name', $is_valid, $domain, $mapping);
    }

	/**
	 * Checks user permissions and block AJAX request if they don't match.
	 *
	 * @since 4.0.0
	 * @uses status_header() To set response HTTP code.
	 * @uses check_admin_referer() To avoid security exploits.
	 * @uses current_user_can() To check user permissions.
	 *
	 * @static
	 * @access protected
	 * @param string $ajax_action Current action name.
	 * @param string $credentials The capabilities, which an user has to have.
	 */
	protected static function _check_premissions( $ajax_action, $credentials = 'manage_options' ) {
		// check if request has been made via jQuery
		if ( empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) || strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) != 'xmlhttprequest' ) {
			status_header( 404 );
			exit;
		}

		// check if user has permissions
		if ( !check_admin_referer( $ajax_action, 'nonce' ) || !current_user_can( $credentials ) ) {
			status_header( 403 );
			exit;
		}
	}

	/**
	 * Redirects user to login form if he is not logged in.
	 *
	 * @since 4.1.0
	 *
	 * @access public
	 */
	public function redirect_to_login_form() {

		wp_redirect( wp_login_url( esc_url_raw( add_query_arg() ) ) );
		exit;
	}



}