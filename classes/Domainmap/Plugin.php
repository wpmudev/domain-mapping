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
 * The core plugin class.
 *
 * @category Domainmap
 *
 * @since 4.0.0
 */
class Domainmap_Plugin {

	const NAME    = 'domainmap';
	const VERSION = '4.0.0';

	/**
	 * Singletone instance of the plugin.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @var Domainmap_Plugin
	 */
	private static $_instance = null;

	/**
	 * The plugin's options.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @var array
	 */
	private $_options = null;

	/**
	 * Whether current site is permitted to map domains or not.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @var boolean
	 */
	private $_permitted = null;

	/**
	 * The array of registered modules.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @var array
	 */
	private $_modules = array();

	/**
	 * The array of reseller objects.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @var array
	 */
	private $_resellers = null;

	/**
	 * Private constructor.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function __construct() {}

	/**
	 * Private clone method.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 */
	private function __clone() {}

	/**
	 * Returns singletone instance of the plugin.
	 *
	 * @since 4.0.0
	 *
	 * @static
	 * @access public
	 * @return Domainmap_Plugin
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new Domainmap_Plugin();
		}

		return self::$_instance;
	}

	/**
	 * Returns a module if it was registered before. Otherwise NULL.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $name The name of the module to return.
	 * @return Domainmap_Module|null Returns a module if it was registered or NULL.
	 */
	public function get_module( $name ) {
		return isset( $this->_modules[$name] ) ? $this->_modules[$name] : null;
	}

	/**
	 * Determines whether the module has been registered or not.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $name The name of a module to check.
	 * @return boolean TRUE if the module has been registered. Otherwise FALSE.
	 */
	public function has_module( $name ) {
		return isset( $this->_modules[$name] );
	}

	/**
	 * Register new module in the plugin.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $module The name of the module to use in the plugin.
	 */
	public function set_module( $class ) {
		$this->_modules[$class] = new $class( $this );
	}

	/**
	 * Returns array of plugin's options.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return array The array of options.
	 */
	public function get_options() {
		if ( is_null( $this->_options ) ) {
			$this->_options = (array)get_site_option( 'domain_mapping', array() );
			if ( empty( $this->_options ) ) {
				$this->_options['map_ipaddress'] = get_site_option( 'map_ipaddress' );
				$this->_options['map_supporteronly'] = get_site_option( 'map_supporteronly', '0' );
				$this->_options['map_admindomain'] = get_site_option( 'map_admindomain', 'user' );
				$this->_options['map_logindomain'] = get_site_option( 'map_logindomain', 'user' );
				$this->_options['map_reseller'] = array();

				update_site_option('domain_mapping', $this->_options);
			}
		}

		return $this->_options;
	}

	/**
	 * Determines whether current site is permitted to map domains or not.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return boolean TRUE if site permitted to map domains, otherwise FALSE.
	 */
	public function is_site_permitted() {
		if ( !is_null( $this->_permitted ) ) {
			return $this->_permitted;
		}

		$options = $this->get_options();
		$this->_permitted = true;
		if ( function_exists( 'is_pro_site' ) && !empty( $options['map_supporteronly'] ) ) {
			// We have a pro-site option set and the pro-site plugin exists
			$levels = (array)get_site_option( 'psts_levels' );
			if( !is_array( $options['map_supporteronly'] ) && !empty( $levels ) && $options['map_supporteronly'] == '1' ) {
				$options['map_supporteronly'] = array( key( $levels ) );
			}

			$this->_permitted = false;
			foreach ( (array)$options['map_supporteronly'] as $level ) {
				if( is_pro_site( false, $level ) ) {
					$this->_permitted = true;
				}
			}
		}

		return $this->_permitted;
	}

	/**
	 * Returns array of resellers.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return array The array of resellers.
	 */
	public function get_resellers() {
		if ( is_null( $this->_resellers ) ) {
			$this->_resellers = array();
			$resellers = apply_filters( 'domainmapping_resellers', array() );
			foreach ( $resellers as $reseller ) {
				if ( is_object( $reseller ) && is_a( $reseller, 'Domainmap_Reseller' ) ) {
					$this->_resellers[dechex( crc32( get_class( $reseller ) ) )] = $reseller;
				}
			}
		}

		return $this->_resellers;
	}

	/**
	 * Returns active reseller instance.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return Domainmap_Reseller The reseller instance or NULL.
	 */
	public function get_reseller() {
		$options = $this->get_options();
		if ( empty( $options['map_reseller'] ) ) {
			return null;
		}

		$resellers = $this->get_resellers();
		return is_string( $options['map_reseller'] ) && array_key_exists( $options['map_reseller'], $resellers )
			? $resellers[$options['map_reseller']]
			: null;
	}

	/**
	 * Returns the associated array of country codes and country names.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @return array The associated array of country codes and country names.
	 */
	public function get_countries() {
		return array(
			'AF' => "Afghanistan",
			'AX' => "Åland Islands",
			'AL' => "Albania",
			'DZ' => "Algeria",
			'AS' => "American Samoa",
			'AD' => "Andorra",
			'AO' => "Angola",
			'AI' => "Anguilla",
			'AQ' => "Antarctica",
			'AG' => "Antigua and Barbuda",
			'AR' => "Argentina",
			'AM' => "Armenia",
			'AW' => "Aruba",
			'AU' => "Australia",
			'AT' => "Austria",
			'AZ' => "Azerbaijan",
			'BS' => "Bahamas",
			'BH' => "Bahrain",
			'BD' => "Bangladesh",
			'BB' => "Barbados",
			'BY' => "Belarus",
			'BE' => "Belgium",
			'BZ' => "Belize",
			'BJ' => "Benin",
			'BM' => "Bermuda",
			'BT' => "Bhutan",
			'BO' => "Bolivia",
			'BA' => "Bosnia and Herzegovina",
			'BW' => "Botswana",
			'BV' => "Bouvet Island",
			'BR' => "Brazil",
			'IO' => "British Indian Ocean Territory",
			'BN' => "Brunei Darussalam",
			'BG' => "Bulgaria",
			'BF' => "Burkina Faso",
			'BI' => "Burundi",
			'KH' => "Cambodia",
			'CM' => "Cameroon",
			'CA' => "Canada",
			'CV' => "Cape Verde",
			'KY' => "Cayman Islands",
			'CF' => "Central African Republic",
			'TD' => "Chad",
			'CL' => "Chile",
			'CN' => "China",
			'CX' => "Christmas Island",
			'CC' => "Cocos (Keeling) Islands",
			'CO' => "Colombia",
			'KM' => "Comoros",
			'CG' => "Congo",
			'CD' => "Congo, The Democratic Republic of The",
			'CK' => "Cook Islands",
			'CR' => "Costa Rica",
			'CI' => "Cote D'ivoire",
			'HR' => "Croatia",
			'CU' => "Cuba",
			'CY' => "Cyprus",
			'CZ' => "Czech Republic",
			'DK' => "Denmark",
			'DJ' => "Djibouti",
			'DM' => "Dominica",
			'DO' => "Dominican Republic",
			'EC' => "Ecuador",
			'EG' => "Egypt",
			'SV' => "El Salvador",
			'GQ' => "Equatorial Guinea",
			'ER' => "Eritrea",
			'EE' => "Estonia",
			'ET' => "Ethiopia",
			'FK' => "Falkland Islands (Malvinas)",
			'FO' => "Faroe Islands",
			'FJ' => "Fiji",
			'FI' => "Finland",
			'FR' => "France",
			'GF' => "French Guiana",
			'PF' => "French Polynesia",
			'TF' => "French Southern Territories",
			'GA' => "Gabon",
			'GM' => "Gambia",
			'GE' => "Georgia",
			'DE' => "Germany",
			'GH' => "Ghana",
			'GI' => "Gibraltar",
			'GR' => "Greece",
			'GL' => "Greenland",
			'GD' => "Grenada",
			'GP' => "Guadeloupe",
			'GU' => "Guam",
			'GT' => "Guatemala",
			'GG' => "Guernsey",
			'GN' => "Guinea",
			'GW' => "Guinea-bissau",
			'GY' => "Guyana",
			'HT' => "Haiti",
			'HM' => "Heard Island and Mcdonald Islands",
			'VA' => "Holy See (Vatican City State)",
			'HN' => "Honduras",
			'HK' => "Hong Kong",
			'HU' => "Hungary",
			'IS' => "Iceland",
			'IN' => "India",
			'ID' => "Indonesia",
			'IR' => "Iran, Islamic Republic of",
			'IQ' => "Iraq",
			'IE' => "Ireland",
			'IM' => "Isle of Man",
			'IL' => "Israel",
			'IT' => "Italy",
			'JM' => "Jamaica",
			'JP' => "Japan",
			'JE' => "Jersey",
			'JO' => "Jordan",
			'KZ' => "Kazakhstan",
			'KE' => "Kenya",
			'KI' => "Kiribati",
			'KP' => "Korea, Democratic People's Republic of",
			'KR' => "Korea, Republic of",
			'KW' => "Kuwait",
			'KG' => "Kyrgyzstan",
			'LA' => "Lao People's Democratic Republic",
			'LV' => "Latvia",
			'LB' => "Lebanon",
			'LS' => "Lesotho",
			'LR' => "Liberia",
			'LY' => "Libyan Arab Jamahiriya",
			'LI' => "Liechtenstein",
			'LT' => "Lithuania",
			'LU' => "Luxembourg",
			'MO' => "Macao",
			'MK' => "Macedonia, The Former Yugoslav Republic of",
			'MG' => "Madagascar",
			'MW' => "Malawi",
			'MY' => "Malaysia",
			'MV' => "Maldives",
			'ML' => "Mali",
			'MT' => "Malta",
			'MH' => "Marshall Islands",
			'MQ' => "Martinique",
			'MR' => "Mauritania",
			'MU' => "Mauritius",
			'YT' => "Mayotte",
			'MX' => "Mexico",
			'FM' => "Micronesia, Federated States of",
			'MD' => "Moldova, Republic of",
			'MC' => "Monaco",
			'MN' => "Mongolia",
			'ME' => "Montenegro",
			'MS' => "Montserrat",
			'MA' => "Morocco",
			'MZ' => "Mozambique",
			'MM' => "Myanmar",
			'NA' => "Namibia",
			'NR' => "Nauru",
			'NP' => "Nepal",
			'NL' => "Netherlands",
			'AN' => "Netherlands Antilles",
			'NC' => "New Caledonia",
			'NZ' => "New Zealand",
			'NI' => "Nicaragua",
			'NE' => "Niger",
			'NG' => "Nigeria",
			'NU' => "Niue",
			'NF' => "Norfolk Island",
			'MP' => "Northern Mariana Islands",
			'NO' => "Norway",
			'OM' => "Oman",
			'PK' => "Pakistan",
			'PW' => "Palau",
			'PS' => "Palestinian Territory, Occupied",
			'PA' => "Panama",
			'PG' => "Papua New Guinea",
			'PY' => "Paraguay",
			'PE' => "Peru",
			'PH' => "Philippines",
			'PN' => "Pitcairn",
			'PL' => "Poland",
			'PT' => "Portugal",
			'PR' => "Puerto Rico",
			'QA' => "Qatar",
			'RE' => "Reunion",
			'RO' => "Romania",
			'RU' => "Russian Federation",
			'RW' => "Rwanda",
			'SH' => "Saint Helena",
			'KN' => "Saint Kitts and Nevis",
			'LC' => "Saint Lucia",
			'PM' => "Saint Pierre and Miquelon",
			'VC' => "Saint Vincent and The Grenadines",
			'WS' => "Samoa",
			'SM' => "San Marino",
			'ST' => "Sao Tome and Principe",
			'SA' => "Saudi Arabia",
			'SN' => "Senegal",
			'RS' => "Serbia",
			'SC' => "Seychelles",
			'SL' => "Sierra Leone",
			'SG' => "Singapore",
			'SK' => "Slovakia",
			'SI' => "Slovenia",
			'SB' => "Solomon Islands",
			'SO' => "Somalia",
			'ZA' => "South Africa",
			'GS' => "South Georgia and The South Sandwich Islands",
			'ES' => "Spain",
			'LK' => "Sri Lanka",
			'SD' => "Sudan",
			'SR' => "Suriname",
			'SJ' => "Svalbard and Jan Mayen",
			'SZ' => "Swaziland",
			'SE' => "Sweden",
			'CH' => "Switzerland",
			'SY' => "Syrian Arab Republic",
			'TW' => "Taiwan, Province of China",
			'TJ' => "Tajikistan",
			'TZ' => "Tanzania, United Republic of",
			'TH' => "Thailand",
			'TL' => "Timor-leste",
			'TG' => "Togo",
			'TK' => "Tokelau",
			'TO' => "Tonga",
			'TT' => "Trinidad and Tobago",
			'TN' => "Tunisia",
			'TR' => "Turkey",
			'TM' => "Turkmenistan",
			'TC' => "Turks and Caicos Islands",
			'TV' => "Tuvalu",
			'UG' => "Uganda",
			'UA' => "Ukraine",
			'AE' => "United Arab Emirates",
			'GB' => "United Kingdom",
			'US' => "United States",
			'UM' => "United States Minor Outlying Islands",
			'UY' => "Uruguay",
			'UZ' => "Uzbekistan",
			'VU' => "Vanuatu",
			'VE' => "Venezuela",
			'VN' => "Viet Nam",
			'VG' => "Virgin Islands, British",
			'VI' => "Virgin Islands, U.S.",
			'WF' => "Wallis and Futuna",
			'EH' => "Western Sahara",
			'YE' => "Yemen",
			'ZM' => "Zambia",
			'ZW' => "Zimbabwe",
		);
	}

}