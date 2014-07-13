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
 * Class that wraps Punycode class for easier use of it's public methods.
 *
 * @category Domainmap
 * @package Ponycode
 *
 * @since 4.2
 */
class Domainmap_Punycode {

    /**
     * Decode a Punycode domain name to its Unicode counterpart
     *
     * @uses Punycode::decode
     * @since 4.2
     *
     * @param $domain
     * @return string decoded domain
     */
    public static function decode( $domain ){
        $cls = new Punycode();
        return $cls->decode( $domain );
    }

    /**
     * Encode a domain to its Punycode version
     *
     * @uses Punycode:encode
     * @since 4.2
     *
     * @param $domain
     * @return string
     */
    public static function encode( $domain ){
        $cls = new Punycode();
        return $cls->encode( $domain );
    }
} 