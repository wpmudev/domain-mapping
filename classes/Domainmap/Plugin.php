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
	const VERSION = '4.4.0.8';
	const SUNRISE = '1.0.3.1';

	const ACTION_CHECK_DOMAIN_AVAILABILITY  = 'domainmapping_check_domain';
	const ACTION_SHOW_REGISTRATION_FORM     = 'domainmapping_show_registration_form';
	const ACTION_SHOW_PURCHASE_FORM         = 'domainmapping_show_purchase_form';
	const ACTION_PAYPAL_DO_EXPRESS_CHECKOUT = 'domainmapping_do_express_checkout';
	const ACTION_PAYPAL_PURCHASE            = 'domainmapping_purchase_with_paypal';
	const ACTION_MAP_DOMAIN                 = 'domainmapping_map_domain';
	const ACTION_CHANGE_FRONTEND_REDIRECT   = 'domainmapping_change_domain_redirect';
	const ACTION_UNMAP_DOMAIN               = 'domainmapping_unmap_domain';
	const ACTION_SELECT_PRIMARY_DOMAIN      = 'domainmapping_select_primary_domain';
	const ACTION_DESELECT_PRIMARY_DOMAIN    = 'domainmapping_deselect_primary_domain';
	const ACTION_HEALTH_CHECK               = 'domainmapping_check_health';
	const ACTION_HEARTBEAT_CHECK            = 'domainmapping_heartbeat_check';
	const ACTION_CDSSO_LOGIN                = 'domainmapping_cdsso_login';
	const ACTION_CDSSO_LOGOUT               = 'domainmapping_cdsso_logout';
	const ACTION_CDSSO_PROPAGATE            = 'domainmapping_cdsso_propagate';
	const ACTION_TOGGLE_SCHEME              = 'domainmapping_toggle_scheme';
	const SCHEME_HTTP                       = 0;
	const SCHEME_HTTPS                      = 1;

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
				$this->_options['map_reseller_log'] = Domainmap_Reseller::LOG_LEVEL_DISABLED;
				$this->_options['map_crossautologin'] = 0;
				$this->_options['map_crossautologin_infooter'] = 0;
				$this->_options['map_crossautologin_async'] = 0;
				$this->_options['map_verifydomain'] = 1;
				$this->_options['map_check_domain_health'] = 0;
				$this->_options['map_force_admin_ssl'] = 0;
				$this->_options['map_force_frontend_ssl'] = 0;
				$this->_options['map_instructions'] = '';
				$this->_options['map_allow_excluded_urls'] = 1;
				$this->_options['map_allow_excluded_pages'] = 1;
				$this->_options['dm_prohibited_domains'] = "";
				$this->_options['map_allow_forced_urls'] = 1;
				$this->_options['map_allow_forced_pages'] = 1;
				$this->_options['map_allow_multiple'] = 0;

				update_site_option('domain_mapping', $this->_options);
			}
		}

		return apply_filters("dm_get_option",  $this->_options);
	}

	/**
	 * Returns option value if it exists, otherwise default value.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param string $option The option name to return.
	 * @param mixed $default The default value to return if an option doesn't exist.
	 * @return mixed The option value if it exists, otherwise default value.
	 */
	public function get_option( $option, $default = false ) {
		$options = $this->get_options();
		$opt = array_key_exists( $option, $options ) ? $options[$option] : $default;

		return apply_filters("dm_get_option", $opt);
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

		return apply_filters("dm_is_site_permitted", $this->_permitted);
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
            /**
             * Filter domain mapping resellers
             *
             * @since 4.0.0
             * @param array $resellers
             */
            $resellers = apply_filters( 'domainmapping_resellers', array() );
			foreach ( $resellers as $reseller ) {
				if ( is_object( $reseller ) && is_a( $reseller, 'Domainmap_Reseller' ) ) {
					$this->_resellers[Domainmap_Reseller::encode_reseller_class( get_class( $reseller ) )] = $reseller;
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
			'BR' => "Brazil",
			'IO' => "British Indian Ocean Territory",
			'BN' => "Brunei",
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
			'CX' => "Christmas Islands",
			'CC' => "Cocos (Keeling) Islands",
			'CO' => "Colombia",
			'KM' => "Comoros",
			'CG' => "Congo",
			'CD' => "Congo, Democratic Republic of",
			'CK' => "Cook Island",
			'CR' => "Costa Rica",
			'CI' => "Cote d'lvoire",
			'HR' => "Croatia",
			'CW' => "Curacao",
			'CY' => "Cyprus",
			'CZ' => "Czech Republic",
			'DK' => "Denmark",
			'DJ' => "Djibouti",
			'DM' => "Dominica",
			'DO' => "Dominican Republic",
			'TP' => "East Timor",
			'EG' => "Egypt",
			'SV' => "El Salvador",
			'EC' => "Ecuador",
			'EK' => "Equatorial Guinea",
			'ER' => "Eritrea",
			'EE' => "Estonia",
			'ET' => "Ethiopia",
			'FK' => "Falkland Islands",
			'FO' => "Faroe Islands",
			'FM' => "Federated States of Micronesia",
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
			'GN' => "Guinea",
			'GW' => "Guinea-Bissau",
			'GY' => "Guyana",
			'HT' => "Haiti",
			'HM' => "Heard and Macdonald Islands",
			'HN' => "Honduras",
			'HK' => "Hong Kong",
			'HU' => "Hungary",
			'IS' => "Iceland",
			'IN' => "India",
			'ID' => "Indonesia",
			'IQ' => "Iraq",
			'IE' => "Ireland",
			'IL' => "Israel",
			'IT' => "Italy",
			'JM' => "Jamaica",
			'JP' => "Japan",
			'JO' => "Jordan",
			'KZ' => "Kazakhstan",
			'KE' => "Kenya",
			'KI' => "Kiribati",
			'KP' => "Korea, North",
			'KR' => "Korea, South",
			'KW' => "Kuwait",
			'KG' => "Kyrgyzstan",
			'LA' => "Laos",
			'LV' => "Latvia",
			'LB' => "Lebanon",
			'LS' => "Lesotho",
			'LR' => "Liberia",
			'LY' => "Libya",
			'LI' => "Liechtenstein",
			'LT' => "Lithuania",
			'LU' => "Luxembourg",
			'MO' => "Macau",
			'MK' => "Macedonia (Rep. of Fmr Yugoslav)",
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
			'FX' => "Metropolitan France",
			'MX' => "Mexico",
			'MD' => "Moldova",
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
			'RU' => "Russia",
			'RW' => "Rwanda",
			'GS' => "S. Georgia and S. Sandwich Islands",
			'WS' => "Samoa",
			'SM' => "San Marino",
			'ST' => "Sao Tome and Principe",
			'SA' => "Saudi Arabia",
			'SN' => "Senegal",
			'RS' => "Serbia, Republic of",
			'SC' => "Seychelles",
			'SL' => "Sierra Leone",
			'SG' => "Singapore",
			'MF' => "Sint Maarten",
			'SK' => "Slovakia",
			'SI' => "Slovenia",
			'SB' => "Solomon Islands",
			'SO' => "Somalia",
			'ZA' => "South Africa",
			'ES' => "Spain",
			'LK' => "Sri Lanka",
			'SH' => "St Helena",
			'KN' => "St Kitts and Nevis",
			'LC' => "St Lucia",
			'PM' => "St Pierre and Miquelon",
			'VC' => "St Vincent and the Grenadines",
			'SR' => "Suriname",
			'SJ' => "Svalbard and Jan Mayen Islands",
			'SZ' => "Swaziland",
			'SE' => "Sweden",
			'CH' => "Switzerland",
			'SY' => "Syria",
			'TW' => "Taiwan",
			'TJ' => "Tajikistan",
			'TZ' => "Tanzania",
			'TH' => "Thailand",
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
			'VA' => "Vatican City",
			'VE' => "Venezuela",
			'VN' => "Vietnam",
			'VG' => "Virgin Islands - British",
			'VI' => "Virgin Islands - US",
			'WF' => "Wallis and Futuna Islands",
			'EH' => "Western Sahara",
			'YE' => "Yemen",
			'ZR' => "Zaire",
			'ZM' => "Zambia",
			'ZW' => "Zimbabwe",
		);
	}

	function is_prohibited_domain( $domain, $check_subdomains = true ){
		$probibited_domains = $this->get_option("map_prohibited_domains");
		$probibited_domains = empty( $probibited_domains ) ?  array() : explode(",", $probibited_domains )  ;

		if( !count( $probibited_domains ) ) return false;

		$probibited_domains = array_map('trim',$probibited_domains);
		if( $check_subdomains ){
			foreach( $probibited_domains  as $probibited_domain){
				if( $domain === $probibited_domain || strpos( $domain, "." . $probibited_domain ) !== false  )
					return true;
			}
		}
		$is_prohibited = in_array($domain, $probibited_domains);
		return apply_filters("dm_is_domain_prohibited", $is_prohibited, $probibited_domains);
	}

}