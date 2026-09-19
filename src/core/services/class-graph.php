<?php
/**
 * This file is part of the Expression Lab plugin.
 *
 * (c) Anderson Salas <github@andersonsalas.com>
 *
 * See the LICENSE file for license information.
 *
 * @package ExpressionLab
 */

namespace ExpressionLab\Core\Services;

use ExpressionLab\Core\Interfaces\ServiceInterface;
use ExpressionLab\Core\LanguageEngine;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct script access allowed' );
}

/**
 * Graph Service.
 *
 * Provides a generic visualization bridge and high-level ergonomic builders
 * to render Vega-Lite charts directly in the console.
 *
 * @package ExpressionLab
 */
final class Graph implements ServiceInterface {

	/**
	 * Default Vega-Lite schema definition URL.
	 *
	 * @var string
	 */
	const DEFAULT_SCHEMA = 'https://vega.github.io/schema/vega-lite/v6.json';

	/**
	 * Default visualization container width.
	 *
	 * @var string
	 */
	const DEFAULT_WIDTH = 'container';

	/**
	 * Pre-bundled TopoJSON World Atlas Countries dataset (1:110m scale).
	 *
	 * @var string
	 */
	const COUNTRIES = 'countries-110m.json';

	/**
	 * Pre-bundled TopoJSON World Atlas Land masses dataset (1:110m scale).
	 *
	 * @var string
	 */
	const LAND = 'land-110m.json';

	/**
	 * Pre-bundled TopoJSON US Atlas States dataset (1:10m scale).
	 *
	 * @var string
	 */
	const USA_STATES = 'states-10m.json';

	/**
	 * Default blue color.
	 *
	 * @var string
	 */
	const COLOR_BLUE = '#3858e9';

	/**
	 * Material Design Red.
	 *
	 * @var string
	 */
	const COLOR_RED = '#e53935';

	/**
	 * Material Design Green.
	 *
	 * @var string
	 */
	const COLOR_GREEN = '#43a047';

	/**
	 * Material Design Purple.
	 *
	 * @var string
	 */
	const COLOR_PURPLE = '#8e24aa';

	/**
	 * Material Design Orange.
	 *
	 * @var string
	 */
	const COLOR_ORANGE = '#fb8c00';

	/**
	 * Material Design Cyan.
	 *
	 * @var string
	 */
	const COLOR_CYAN = '#00acc1';

	/**
	 * Material Design Gray.
	 *
	 * @var string
	 */
	const COLOR_GRAY = '#78909c';

	/**
	 * Material Design Dark Blue Gray.
	 *
	 * @var string
	 */
	const COLOR_DARK = '#263238';

	/**
	 * Vega-Lite Reds sequential color scheme.
	 *
	 * @var string
	 */
	const SCHEME_REDS = 'reds';

	/**
	 * Vega-Lite Blues sequential color scheme.
	 *
	 * @var string
	 */
	const SCHEME_BLUES = 'blues';

	/**
	 * Vega-Lite Greens sequential color scheme.
	 *
	 * @var string
	 */
	const SCHEME_GREENS = 'greens';

	/**
	 * Vega-Lite Purples sequential color scheme.
	 *
	 * @var string
	 */
	const SCHEME_PURPLES = 'purples';

	/**
	 * Vega-Lite Oranges sequential color scheme.
	 *
	 * @var string
	 */
	const SCHEME_ORANGES = 'oranges';

	/**
	 * Vega-Lite Viridis perceptually uniform color scheme.
	 *
	 * @var string
	 */
	const SCHEME_VIRIDIS = 'viridis';

	/**
	 * Vega-Lite Inferno color scheme.
	 *
	 * @var string
	 */
	const SCHEME_INFERNO = 'inferno';

	/**
	 * Vega-Lite Magma color scheme.
	 *
	 * @var string
	 */
	const SCHEME_MAGMA = 'magma';

	/**
	 * Vega-Lite Category10 discrete qualitative scheme.
	 *
	 * @var string
	 */
	const SCHEME_CATEGORY10 = 'category10';

	/**
	 * Vega-Lite Tableau10 discrete qualitative scheme.
	 *
	 * @var string
	 */
	const SCHEME_TABLEAU10 = 'tableau10';

	/**
	 * Magic getter to expose class constants as object properties in ELScript runtime.
	 *
	 * @param string $name Property name.
	 * @return mixed Constant value if defined, null otherwise.
	 */
	public function __get( string $name ) {
		if ( defined( "self::$name" ) ) {
			return constant( "self::$name" );
		}
		return null;
	}

	/**
	 * US States and territories lookup dictionary (postal code => [fips, name]).
	 *
	 * @var array<string, array{fips: string, name: string}>
	 */
	private static array $us_states = array(
		'AL' => array(
			'fips' => '01',
			'name' => 'Alabama',
		),
		'AK' => array(
			'fips' => '02',
			'name' => 'Alaska',
		),
		'AZ' => array(
			'fips' => '04',
			'name' => 'Arizona',
		),
		'AR' => array(
			'fips' => '05',
			'name' => 'Arkansas',
		),
		'CA' => array(
			'fips' => '06',
			'name' => 'California',
		),
		'CO' => array(
			'fips' => '08',
			'name' => 'Colorado',
		),
		'CT' => array(
			'fips' => '09',
			'name' => 'Connecticut',
		),
		'DE' => array(
			'fips' => '10',
			'name' => 'Delaware',
		),
		'DC' => array(
			'fips' => '11',
			'name' => 'District of Columbia',
		),
		'FL' => array(
			'fips' => '12',
			'name' => 'Florida',
		),
		'GA' => array(
			'fips' => '13',
			'name' => 'Georgia',
		),
		'HI' => array(
			'fips' => '15',
			'name' => 'Hawaii',
		),
		'ID' => array(
			'fips' => '16',
			'name' => 'Idaho',
		),
		'IL' => array(
			'fips' => '17',
			'name' => 'Illinois',
		),
		'IN' => array(
			'fips' => '18',
			'name' => 'Indiana',
		),
		'IA' => array(
			'fips' => '19',
			'name' => 'Iowa',
		),
		'KS' => array(
			'fips' => '20',
			'name' => 'Kansas',
		),
		'KY' => array(
			'fips' => '21',
			'name' => 'Kentucky',
		),
		'LA' => array(
			'fips' => '22',
			'name' => 'Louisiana',
		),
		'ME' => array(
			'fips' => '23',
			'name' => 'Maine',
		),
		'MD' => array(
			'fips' => '24',
			'name' => 'Maryland',
		),
		'MA' => array(
			'fips' => '25',
			'name' => 'Massachusetts',
		),
		'MI' => array(
			'fips' => '26',
			'name' => 'Michigan',
		),
		'MN' => array(
			'fips' => '27',
			'name' => 'Minnesota',
		),
		'MS' => array(
			'fips' => '28',
			'name' => 'Mississippi',
		),
		'MO' => array(
			'fips' => '29',
			'name' => 'Missouri',
		),
		'MT' => array(
			'fips' => '30',
			'name' => 'Montana',
		),
		'NE' => array(
			'fips' => '31',
			'name' => 'Nebraska',
		),
		'NV' => array(
			'fips' => '32',
			'name' => 'Nevada',
		),
		'NH' => array(
			'fips' => '33',
			'name' => 'New Hampshire',
		),
		'NJ' => array(
			'fips' => '34',
			'name' => 'New Jersey',
		),
		'NM' => array(
			'fips' => '35',
			'name' => 'New Mexico',
		),
		'NY' => array(
			'fips' => '36',
			'name' => 'New York',
		),
		'NC' => array(
			'fips' => '37',
			'name' => 'North Carolina',
		),
		'ND' => array(
			'fips' => '38',
			'name' => 'North Dakota',
		),
		'OH' => array(
			'fips' => '39',
			'name' => 'Ohio',
		),
		'OK' => array(
			'fips' => '40',
			'name' => 'Oklahoma',
		),
		'OR' => array(
			'fips' => '41',
			'name' => 'Oregon',
		),
		'PA' => array(
			'fips' => '42',
			'name' => 'Pennsylvania',
		),
		'RI' => array(
			'fips' => '44',
			'name' => 'Rhode Island',
		),
		'SC' => array(
			'fips' => '45',
			'name' => 'South Carolina',
		),
		'SD' => array(
			'fips' => '46',
			'name' => 'South Dakota',
		),
		'TN' => array(
			'fips' => '47',
			'name' => 'Tennessee',
		),
		'TX' => array(
			'fips' => '48',
			'name' => 'Texas',
		),
		'UT' => array(
			'fips' => '49',
			'name' => 'Utah',
		),
		'VT' => array(
			'fips' => '50',
			'name' => 'Vermont',
		),
		'VA' => array(
			'fips' => '51',
			'name' => 'Virginia',
		),
		'WA' => array(
			'fips' => '53',
			'name' => 'Washington',
		),
		'WV' => array(
			'fips' => '54',
			'name' => 'West Virginia',
		),
		'WI' => array(
			'fips' => '55',
			'name' => 'Wisconsin',
		),
		'WY' => array(
			'fips' => '56',
			'name' => 'Wyoming',
		),
		'AS' => array(
			'fips' => '60',
			'name' => 'American Samoa',
		),
		'GU' => array(
			'fips' => '66',
			'name' => 'Guam',
		),
		'MP' => array(
			'fips' => '69',
			'name' => 'Northern Mariana Islands',
		),
		'PR' => array(
			'fips' => '72',
			'name' => 'Puerto Rico',
		),
		'VI' => array(
			'fips' => '78',
			'name' => 'Virgin Islands',
		),
	);

	/**
	 * ISO 3166-1 country lookup table: Alpha-2 => array( numeric_id, name, alpha-3 ).
	 *
	 * @var array<string, array{0: string, 1: string, 2: string}>
	 */
	private static array $countries = array(
		'AD' => array( '020', 'Andorra', 'AND' ),
		'AE' => array( '784', 'United Arab Emirates', 'ARE' ),
		'AF' => array( '004', 'Afghanistan', 'AFG' ),
		'AG' => array( '028', 'Antigua and Barbuda', 'ATG' ),
		'AI' => array( '660', 'Anguilla', 'AIA' ),
		'AL' => array( '008', 'Albania', 'ALB' ),
		'AM' => array( '051', 'Armenia', 'ARM' ),
		'AO' => array( '024', 'Angola', 'AGO' ),
		'AQ' => array( '010', 'Antarctica', 'ATA' ),
		'AR' => array( '032', 'Argentina', 'ARG' ),
		'AS' => array( '016', 'American Samoa', 'ASM' ),
		'AT' => array( '040', 'Austria', 'AUT' ),
		'AU' => array( '036', 'Australia', 'AUS' ),
		'AW' => array( '533', 'Aruba', 'ABW' ),
		'AX' => array( '248', 'Åland Islands', 'ALA' ),
		'AZ' => array( '031', 'Azerbaijan', 'AZE' ),
		'BA' => array( '070', 'Bosnia and Herzegovina', 'BIH' ),
		'BB' => array( '052', 'Barbados', 'BRB' ),
		'BD' => array( '050', 'Bangladesh', 'BGD' ),
		'BE' => array( '056', 'Belgium', 'BEL' ),
		'BF' => array( '854', 'Burkina Faso', 'BFA' ),
		'BG' => array( '100', 'Bulgaria', 'BGR' ),
		'BH' => array( '048', 'Bahrain', 'BHR' ),
		'BI' => array( '108', 'Burundi', 'BDI' ),
		'BJ' => array( '204', 'Benin', 'BEN' ),
		'BL' => array( '652', 'Saint Barthélemy', 'BLM' ),
		'BM' => array( '060', 'Bermuda', 'BMU' ),
		'BN' => array( '096', 'Brunei', 'BRN' ),
		'BO' => array( '068', 'Bolivia', 'BOL' ),
		'BQ' => array( '535', 'Bonaire, Sint Eustatius and Saba', 'BES' ),
		'BR' => array( '076', 'Brazil', 'BRA' ),
		'BS' => array( '044', 'Bahamas', 'BHS' ),
		'BT' => array( '064', 'Bhutan', 'BTN' ),
		'BV' => array( '074', 'Bouvet Island', 'BVT' ),
		'BW' => array( '072', 'Botswana', 'BWA' ),
		'BY' => array( '112', 'Belarus', 'BLR' ),
		'BZ' => array( '084', 'Belize', 'BLZ' ),
		'CA' => array( '124', 'Canada', 'CAN' ),
		'CC' => array( '166', 'Cocos (Keeling) Islands', 'CCK' ),
		'CD' => array( '180', 'Dem. Rep. Congo', 'COD' ),
		'CF' => array( '140', 'Central African Republic', 'CAF' ),
		'CG' => array( '178', 'Congo', 'COG' ),
		'CH' => array( '756', 'Switzerland', 'CHE' ),
		'CI' => array( '384', 'Cote d\'Ivoire', 'CIV' ),
		'CK' => array( '184', 'Cook Islands', 'COK' ),
		'CL' => array( '152', 'Chile', 'CHL' ),
		'CM' => array( '120', 'Cameroon', 'CMR' ),
		'CN' => array( '156', 'China', 'CHN' ),
		'CO' => array( '170', 'Colombia', 'COL' ),
		'CR' => array( '188', 'Costa Rica', 'CRI' ),
		'CU' => array( '192', 'Cuba', 'CUB' ),
		'CV' => array( '132', 'Cabo Verde', 'CPV' ),
		'CW' => array( '531', 'Curaçao', 'CUW' ),
		'CX' => array( '162', 'Christmas Island', 'CXR' ),
		'CY' => array( '196', 'Cyprus', 'CYP' ),
		'CZ' => array( '203', 'Czechia', 'CZE' ),
		'DE' => array( '276', 'Germany', 'DEU' ),
		'DJ' => array( '262', 'Djibouti', 'DJI' ),
		'DK' => array( '208', 'Denmark', 'DNK' ),
		'DM' => array( '212', 'Dominica', 'DMA' ),
		'DO' => array( '214', 'Dominican Republic', 'DOM' ),
		'DZ' => array( '012', 'Algeria', 'DZA' ),
		'EC' => array( '218', 'Ecuador', 'ECU' ),
		'EE' => array( '233', 'Estonia', 'EST' ),
		'EG' => array( '818', 'Egypt', 'EGY' ),
		'EH' => array( '732', 'Western Sahara', 'ESH' ),
		'ER' => array( '232', 'Eritrea', 'ERI' ),
		'ES' => array( '724', 'Spain', 'ESP' ),
		'ET' => array( '231', 'Ethiopia', 'ETH' ),
		'FI' => array( '246', 'Finland', 'FIN' ),
		'FJ' => array( '242', 'Fiji', 'FJI' ),
		'FK' => array( '238', 'Falkland Islands', 'FLK' ),
		'FM' => array( '583', 'Micronesia', 'FSM' ),
		'FO' => array( '234', 'Faroe Islands', 'FRO' ),
		'FR' => array( '250', 'France', 'FRA' ),
		'GA' => array( '266', 'Gabon', 'GAB' ),
		'GB' => array( '826', 'United Kingdom', 'GBR' ),
		'GD' => array( '308', 'Grenada', 'GRD' ),
		'GE' => array( '268', 'Georgia', 'GEO' ),
		'GF' => array( '254', 'French Guiana', 'GUF' ),
		'GG' => array( '831', 'Guernsey', 'GGY' ),
		'GH' => array( '288', 'Ghana', 'GHA' ),
		'GI' => array( '292', 'Gibraltar', 'GIB' ),
		'GL' => array( '304', 'Greenland', 'GRL' ),
		'GM' => array( '270', 'Gambia', 'GMB' ),
		'GN' => array( '324', 'Guinea', 'GIN' ),
		'GP' => array( '312', 'Guadeloupe', 'GLP' ),
		'GQ' => array( '226', 'Equatorial Guinea', 'GNQ' ),
		'GR' => array( '300', 'Greece', 'GRC' ),
		'GS' => array( '239', 'South Georgia and the South Sandwich Islands', 'SGS' ),
		'GT' => array( '320', 'Guatemala', 'GTM' ),
		'GU' => array( '316', 'Guam', 'GUM' ),
		'GW' => array( '624', 'Guinea-Bissau', 'GNB' ),
		'GY' => array( '328', 'Guyana', 'GUY' ),
		'HK' => array( '344', 'Hong Kong', 'HKG' ),
		'HM' => array( '334', 'Heard Island and McDonald Islands', 'HMD' ),
		'HN' => array( '340', 'Honduras', 'HND' ),
		'HR' => array( '191', 'Croatia', 'HRV' ),
		'HT' => array( '332', 'Haiti', 'HTI' ),
		'HU' => array( '348', 'Hungary', 'HUN' ),
		'ID' => array( '360', 'Indonesia', 'IDN' ),
		'IE' => array( '372', 'Ireland', 'IRL' ),
		'IL' => array( '376', 'Israel', 'ISR' ),
		'IM' => array( '833', 'Isle of Man', 'IMN' ),
		'IN' => array( '356', 'India', 'IND' ),
		'IO' => array( '086', 'British Indian Ocean Territory', 'IOT' ),
		'IQ' => array( '368', 'Iraq', 'IRQ' ),
		'IR' => array( '364', 'Iran', 'IRN' ),
		'IS' => array( '352', 'Iceland', 'ISL' ),
		'IT' => array( '380', 'Italy', 'ITA' ),
		'JE' => array( '832', 'Jersey', 'JEY' ),
		'JM' => array( '388', 'Jamaica', 'JAM' ),
		'JO' => array( '400', 'Jordan', 'JOR' ),
		'JP' => array( '392', 'Japan', 'JPN' ),
		'KE' => array( '404', 'Kenya', 'KEN' ),
		'KG' => array( '417', 'Kyrgyzstan', 'KGZ' ),
		'KH' => array( '116', 'Cambodia', 'KHM' ),
		'KI' => array( '296', 'Kiribati', 'KIR' ),
		'KM' => array( '174', 'Comoros', 'COM' ),
		'KN' => array( '659', 'Saint Kitts and Nevis', 'KNA' ),
		'KP' => array( '408', 'North Korea', 'PRK' ),
		'KR' => array( '410', 'South Korea', 'KOR' ),
		'KW' => array( '414', 'Kuwait', 'KWT' ),
		'KY' => array( '136', 'Cayman Islands', 'CYM' ),
		'KZ' => array( '398', 'Kazakhstan', 'KAZ' ),
		'LA' => array( '418', 'Laos', 'LAO' ),
		'LB' => array( '422', 'Lebanon', 'LBN' ),
		'LC' => array( '662', 'Saint Lucia', 'LCA' ),
		'LI' => array( '438', 'Liechtenstein', 'LIE' ),
		'LK' => array( '144', 'Sri Lanka', 'LKA' ),
		'LR' => array( '430', 'Liberia', 'LBR' ),
		'LS' => array( '426', 'Lesotho', 'LSO' ),
		'LT' => array( '440', 'Lithuania', 'LTU' ),
		'LU' => array( '442', 'Luxembourg', 'LUX' ),
		'LV' => array( '428', 'Latvia', 'LVA' ),
		'LY' => array( '434', 'Libya', 'LBY' ),
		'MA' => array( '504', 'Morocco', 'MAR' ),
		'MC' => array( '492', 'Monaco', 'MCO' ),
		'MD' => array( '498', 'Moldova', 'MDA' ),
		'ME' => array( '499', 'Montenegro', 'MNE' ),
		'MF' => array( '663', 'Saint Martin (French part)', 'MAF' ),
		'MG' => array( '450', 'Madagascar', 'MDG' ),
		'MH' => array( '584', 'Marshall Islands', 'MHL' ),
		'MK' => array( '807', 'North Macedonia', 'MKD' ),
		'ML' => array( '466', 'Mali', 'MLI' ),
		'MM' => array( '104', 'Myanmar', 'MMR' ),
		'MN' => array( '496', 'Mongolia', 'MNG' ),
		'MO' => array( '446', 'Macao', 'MAC' ),
		'MP' => array( '580', 'Northern Mariana Islands', 'MNP' ),
		'MQ' => array( '474', 'Martinique', 'MTQ' ),
		'MR' => array( '478', 'Mauritania', 'MRT' ),
		'MS' => array( '500', 'Montserrat', 'MSR' ),
		'MT' => array( '470', 'Malta', 'MLT' ),
		'MU' => array( '480', 'Mauritius', 'MUS' ),
		'MV' => array( '462', 'Maldives', 'MDV' ),
		'MW' => array( '454', 'Malawi', 'MWI' ),
		'MX' => array( '484', 'Mexico', 'MEX' ),
		'MY' => array( '458', 'Malaysia', 'MYS' ),
		'MZ' => array( '508', 'Mozambique', 'MOZ' ),
		'NA' => array( '516', 'Namibia', 'NAM' ),
		'NC' => array( '540', 'New Caledonia', 'NCL' ),
		'NE' => array( '562', 'Niger', 'NER' ),
		'NF' => array( '574', 'Norfolk Island', 'NFK' ),
		'NG' => array( '566', 'Nigeria', 'NGA' ),
		'NI' => array( '558', 'Nicaragua', 'NIC' ),
		'NL' => array( '528', 'Netherlands', 'NLD' ),
		'NO' => array( '578', 'Norway', 'NOR' ),
		'NP' => array( '524', 'Nepal', 'NPL' ),
		'NR' => array( '520', 'Nauru', 'NRU' ),
		'NU' => array( '570', 'Niue', 'NIU' ),
		'NZ' => array( '554', 'New Zealand', 'NZL' ),
		'OM' => array( '512', 'Oman', 'OMN' ),
		'PA' => array( '591', 'Panama', 'PAN' ),
		'PE' => array( '604', 'Peru', 'PER' ),
		'PF' => array( '258', 'French Polynesia', 'PYF' ),
		'PG' => array( '598', 'Papua New Guinea', 'PNG' ),
		'PH' => array( '608', 'Philippines', 'PHL' ),
		'PK' => array( '586', 'Pakistan', 'PAK' ),
		'PL' => array( '616', 'Poland', 'POL' ),
		'PM' => array( '666', 'Saint Pierre and Miquelon', 'SPM' ),
		'PN' => array( '612', 'Pitcairn', 'PCN' ),
		'PR' => array( '630', 'Puerto Rico', 'PRI' ),
		'PS' => array( '275', 'Palestine', 'PSE' ),
		'PT' => array( '620', 'Portugal', 'PRT' ),
		'PW' => array( '585', 'Palau', 'PLW' ),
		'PY' => array( '600', 'Paraguay', 'PRY' ),
		'QA' => array( '634', 'Qatar', 'QAT' ),
		'RE' => array( '638', 'Réunion', 'REU' ),
		'RO' => array( '642', 'Romania', 'ROU' ),
		'RS' => array( '688', 'Serbia', 'SRB' ),
		'RU' => array( '643', 'Russia', 'RUS' ),
		'RW' => array( '646', 'Rwanda', 'RWA' ),
		'SA' => array( '682', 'Saudi Arabia', 'SAU' ),
		'SB' => array( '090', 'Solomon Islands', 'SLB' ),
		'SC' => array( '690', 'Seychelles', 'SYC' ),
		'SD' => array( '729', 'Sudan', 'SDN' ),
		'SE' => array( '752', 'Sweden', 'SWE' ),
		'SG' => array( '702', 'Singapore', 'SGP' ),
		'SH' => array( '654', 'Saint Helena, Ascension and Tristan da Cunha', 'SHN' ),
		'SI' => array( '705', 'Slovenia', 'SVN' ),
		'SJ' => array( '744', 'Svalbard and Jan Mayen', 'SJM' ),
		'SK' => array( '703', 'Slovakia', 'SVK' ),
		'SL' => array( '694', 'Sierra Leone', 'SLE' ),
		'SM' => array( '674', 'San Marino', 'SMR' ),
		'SN' => array( '686', 'Senegal', 'SEN' ),
		'SO' => array( '706', 'Somalia', 'SOM' ),
		'SR' => array( '740', 'Suriname', 'SUR' ),
		'SS' => array( '728', 'South Sudan', 'SSD' ),
		'ST' => array( '678', 'Sao Tome and Principe', 'STP' ),
		'SV' => array( '222', 'El Salvador', 'SLV' ),
		'SX' => array( '534', 'Sint Maarten (Dutch part)', 'SXM' ),
		'SY' => array( '760', 'Syria', 'SYR' ),
		'SZ' => array( '748', 'Eswatini', 'SWZ' ),
		'TC' => array( '796', 'Turks and Caicos Islands', 'TCA' ),
		'TD' => array( '148', 'Chad', 'TCD' ),
		'TF' => array( '260', 'French Southern Territories', 'ATF' ),
		'TG' => array( '768', 'Togo', 'TGO' ),
		'TH' => array( '764', 'Thailand', 'THA' ),
		'TJ' => array( '762', 'Tajikistan', 'TJK' ),
		'TK' => array( '772', 'Tokelau', 'TKL' ),
		'TL' => array( '626', 'Timor-Leste', 'TLS' ),
		'TM' => array( '795', 'Turkmenistan', 'TKM' ),
		'TN' => array( '788', 'Tunisia', 'TUN' ),
		'TO' => array( '776', 'Tonga', 'TON' ),
		'TR' => array( '792', 'Turkey', 'TUR' ),
		'TT' => array( '780', 'Trinidad and Tobago', 'TTO' ),
		'TV' => array( '798', 'Tuvalu', 'TUV' ),
		'TW' => array( '158', 'Taiwan', 'TWN' ),
		'TZ' => array( '834', 'Tanzania', 'TZA' ),
		'UA' => array( '804', 'Ukraine', 'UKR' ),
		'UG' => array( '800', 'Uganda', 'UGA' ),
		'UM' => array( '581', 'United States Minor Outlying Islands', 'UMI' ),
		'US' => array( '840', 'United States', 'USA' ),
		'UY' => array( '858', 'Uruguay', 'URY' ),
		'UZ' => array( '860', 'Uzbekistan', 'UZB' ),
		'VA' => array( '336', 'Holy See', 'VAT' ),
		'VC' => array( '670', 'Saint Vincent and the Grenadines', 'VCT' ),
		'VE' => array( '862', 'Venezuela', 'VEN' ),
		'VG' => array( '092', 'Virgin Islands (British)', 'VGB' ),
		'VI' => array( '850', 'Virgin Islands (U.S.)', 'VIR' ),
		'VN' => array( '704', 'Vietnam', 'VNM' ),
		'VU' => array( '548', 'Vanuatu', 'VUT' ),
		'WF' => array( '876', 'Wallis and Futuna', 'WLF' ),
		'WS' => array( '882', 'Samoa', 'WSM' ),
		'YE' => array( '887', 'Yemen', 'YEM' ),
		'YT' => array( '175', 'Mayotte', 'MYT' ),
		'ZA' => array( '710', 'South Africa', 'ZAF' ),
		'ZM' => array( '894', 'Zambia', 'ZMB' ),
		'ZW' => array( '716', 'Zimbabwe', 'ZWE' ),
	);

	/**
	 * ISO 3166-1 country name aliases and common variations => Alpha-2.
	 *
	 * @var array<string, string>
	 */
	private static array $country_aliases = array(
		'bolivia, plurinational state of'         => 'BO',
		'bosnia and herz.'                        => 'BA',
		'brunei darussalam'                       => 'BN',
		'central african rep.'                    => 'CF',
		'congo, democratic republic of the'       => 'CD',
		'cote d\'ivoire'                          => 'CI',
		'czech republic'                          => 'CZ',
		'côte d\'ivoire'                          => 'CI',
		'dem. rep. congo'                         => 'CD',
		'dominican rep.'                          => 'DO',
		'eq. guinea'                              => 'GQ',
		'falkland is.'                            => 'FK',
		'falkland islands (malvinas)'             => 'FK',
		'fr. s. antarctic lands'                  => 'TF',
		'great britain'                           => 'GB',
		'iran, islamic republic of'               => 'IR',
		'ivory coast'                             => 'CI',
		'korea, democratic people\'s republic of' => 'KP',
		'korea, north'                            => 'KP',
		'korea, republic of'                      => 'KR',
		'korea, south'                            => 'KR',
		'lao people\'s democratic republic'       => 'LA',
		'macedonia'                               => 'MK',
		'micronesia, federated states of'         => 'FM',
		'moldova, republic of'                    => 'MD',
		'netherlands, kingdom of the'             => 'NL',
		'north korea'                             => 'KP',
		'palestine, state of'                     => 'PS',
		'republic of korea'                       => 'KR',
		'russian federation'                      => 'RU',
		's. sudan'                                => 'SS',
		'solomon is.'                             => 'SB',
		'south korea'                             => 'KR',
		'syrian arab republic'                    => 'SY',
		'taiwan, province of china'               => 'TW',
		'tanzania, united republic of'            => 'TZ',
		'türkiye'                                 => 'TR',
		'u.k.'                                    => 'GB',
		'u.s.'                                    => 'US',
		'u.s.a.'                                  => 'US',
		'uae'                                     => 'AE',
		'uk'                                      => 'GB',
		'united kingdom of great britain and northern ireland' => 'GB',
		'united states of america'                => 'US',
		'usa'                                     => 'US',
		'venezuela, bolivarian republic of'       => 'VE',
		'viet nam'                                => 'VN',
		'w. sahara'                               => 'EH',
	);

	/**
	 * Normalizes arbitrary tabular input into a list of associative records.
	 *
	 * @param mixed $data Raw input data (array of records, associative map, object, or JSON string).
	 * @return array List of associative arrays.
	 * @throws \InvalidArgumentException If data cannot be normalized or is empty.
	 */
	private function normalize_records( $data ): array {
		if ( is_string( $data ) ) {
			$trimmed = trim( $data );
			if ( '' === $trimmed ) {
				throw new \InvalidArgumentException( 'Dataset cannot be empty.' );
			}
			$decoded = json_decode( $trimmed, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				throw new \InvalidArgumentException( esc_html( 'Invalid JSON data provided: ' . json_last_error_msg() ) );
			}
			$data = $decoded;
		} elseif ( is_object( $data ) ) {
			$data = json_decode( wp_json_encode( $data ), true );
		}

		if ( ! is_array( $data ) || empty( $data ) ) {
			throw new \InvalidArgumentException( 'Dataset cannot be empty.' );
		}

		// If associative map (e.g. ['Draft' => 12, 'Published' => 50]), convert to key-value rows.
		if ( ! array_is_list( $data ) ) {
			$first_val = reset( $data );
			if ( ! is_array( $first_val ) && ! is_object( $first_val ) ) {
				$rows = array();
				foreach ( $data as $key => $val ) {
					$rows[] = array(
						'category' => (string) $key,
						'value'    => $val,
					);
				}
				return $rows;
			}
		}

		$records = array();
		foreach ( $data as $row ) {
			if ( is_object( $row ) ) {
				$records[] = json_decode( wp_json_encode( $row ), true );
			} elseif ( is_array( $row ) ) {
				$records[] = $row;
			} elseif ( is_int( $row ) || is_float( $row ) || ( is_string( $row ) && is_numeric( $row ) ) ) {
				$records[] = array( 'value' => is_numeric( $row ) ? $row + 0 : $row );
			}
		}

		if ( empty( $records ) ) {
			throw new \InvalidArgumentException( 'Dataset contains no valid records.' );
		}

		return $records;
	}

	/**
	 * Detects nominal and quantitative fields from the first record.
	 *
	 * @param array $records Non-empty list of records.
	 * @param array $options Options array with optional `'x'` and `'y'` overrides.
	 * @return array Associative array with `'x_field'`, `'y_field'`, `'x_type'`, `'y_type'`.
	 */
	private function detect_xy_fields( array $records, array $options ): array {
		$first = $records[0];
		$keys  = array_keys( $first );

		$nominal_key      = null;
		$quantitative_key = null;

		foreach ( $first as $key => $val ) {
			if ( null === $quantitative_key && ( is_int( $val ) || is_float( $val ) || ( is_string( $val ) && is_numeric( $val ) ) ) ) {
				$quantitative_key = $key;
			} elseif ( null === $nominal_key && is_string( $val ) ) {
				$nominal_key = $key;
			}
		}

		$x_field = isset( $options['x'] ) ? (string) $options['x'] : ( $nominal_key ?? $keys[0] );
		$y_field = isset( $options['y'] ) ? (string) $options['y'] : ( $quantitative_key ?? ( $keys[1] ?? $keys[0] ) );

		$x_type = isset( $options['x_type'] ) ? (string) $options['x_type'] : ( ( is_numeric( $first[ $x_field ] ?? null ) ) ? 'quantitative' : 'nominal' );
		$y_type = isset( $options['y_type'] ) ? (string) $options['y_type'] : ( ( is_numeric( $first[ $y_field ] ?? null ) ) ? 'quantitative' : 'nominal' );

		return array(
			'x_field' => $x_field,
			'y_field' => $y_field,
			'x_type'  => $x_type,
			'y_type'  => $y_type,
		);
	}

	/**
	 * Resolves a US state identifier (code, name, or FIPS) into standardized FIPS, code, and full name.
	 *
	 * @param mixed $val State postal abbreviation (\`'CA'\`), full name (\`'California'\`), or FIPS code (6, \`'06'\`).
	 * @return array{fips: string, name: string, code: string}|null Resolved metadata, or `null` if unrecognized.
	 */
	private function resolve_us_state( $val ): ?array {
		if ( null === $val || '' === $val ) {
			return null;
		}

		if ( is_int( $val ) || ( is_string( $val ) && ctype_digit( trim( $val ) ) ) ) {
			$fips = sprintf( '%02d', (int) $val );
			foreach ( self::$us_states as $code => $info ) {
				if ( $info['fips'] === $fips ) {
					return array(
						'fips' => $fips,
						'name' => $info['name'],
						'code' => $code,
					);
				}
			}
			return null;
		}

		if ( is_string( $val ) ) {
			$trimmed = trim( $val );
			$upper   = strtoupper( $trimmed );
			if ( isset( self::$us_states[ $upper ] ) ) {
				return array(
					'fips' => self::$us_states[ $upper ]['fips'],
					'name' => self::$us_states[ $upper ]['name'],
					'code' => $upper,
				);
			}

			$lower = strtolower( $trimmed );
			foreach ( self::$us_states as $code => $info ) {
				if ( strtolower( $info['name'] ) === $lower ) {
					return array(
						'fips' => $info['fips'],
						'name' => $info['name'],
						'code' => $code,
					);
				}
			}
		}

		return null;
	}

	/**
	 * Resolves a country identifier into standardized TopoJSON ID, Alpha-2 code, Alpha-3 code, and full country name.
	 *
	 * Accepts ISO 3166-1 Alpha-2 code (`'US'`, `'ES'`), Alpha-3 code (`'USA'`, `'ESP'`),
	 * numeric ISO/TopoJSON ID (840, 724, `'840'`, `'032'`), or common country names (`'United States'`, `'Russia'`, `'Spain'`).
	 *
	 * @param mixed $val Country code, numeric ID, or country name.
	 * @return array{id: string, name: string, alpha2: string, alpha3: string}|null Resolved metadata, or `null` if unrecognized.
	 */
	private function resolve_country( $val ): ?array {
		if ( null === $val || '' === $val ) {
			return null;
		}

		if ( is_int( $val ) || ( is_string( $val ) && ctype_digit( trim( $val ) ) ) ) {
			$numeric_id = sprintf( '%03d', (int) $val );
			foreach ( self::$countries as $alpha2 => $info ) {
				if ( $info[0] === $numeric_id ) {
					return array(
						'id'     => $info[0],
						'name'   => $info[1],
						'alpha2' => $alpha2,
						'alpha3' => $info[2],
					);
				}
			}
			return null;
		}

		if ( is_string( $val ) ) {
			$trimmed = trim( $val );
			$upper   = strtoupper( $trimmed );

			if ( isset( self::$countries[ $upper ] ) ) {
				return array(
					'id'     => self::$countries[ $upper ][0],
					'name'   => self::$countries[ $upper ][1],
					'alpha2' => $upper,
					'alpha3' => self::$countries[ $upper ][2],
				);
			}

			if ( 3 === strlen( $upper ) ) {
				foreach ( self::$countries as $alpha2 => $info ) {
					if ( $info[2] === $upper ) {
						return array(
							'id'     => $info[0],
							'name'   => $info[1],
							'alpha2' => $alpha2,
							'alpha3' => $info[2],
						);
					}
				}
			}

			$lower = strtolower( $trimmed );
			if ( isset( self::$country_aliases[ $lower ] ) ) {
				$alpha2 = self::$country_aliases[ $lower ];
				if ( isset( self::$countries[ $alpha2 ] ) ) {
					return array(
						'id'     => self::$countries[ $alpha2 ][0],
						'name'   => self::$countries[ $alpha2 ][1],
						'alpha2' => $alpha2,
						'alpha3' => self::$countries[ $alpha2 ][2],
					);
				}
			}

			foreach ( self::$countries as $alpha2 => $info ) {
				if ( strtolower( $info[1] ) === $lower ) {
					return array(
						'id'     => $info[0],
						'name'   => $info[1],
						'alpha2' => $alpha2,
						'alpha3' => $info[2],
					);
				}
			}
		}

		return null;
	}

	/**
	 * Renders an arbitrary Vega-Lite visualization in the Expression Lab console.
	 *
	 * Accepts an associative array, a generic object, or a valid JSON string
	 * representing a complete Vega-Lite specification.
	 *
	 * TODO: Documentation and examples.
	 *
	 * @see https://vega.github.io/vega-lite/docs/
	 *
	 * @param array|object|string $payload Vega-Lite specification object or JSON string.
	 * @param string              $title   Optional tab title for the visualization. Default `'Graph'`.
	 * @return void
	 * @throws \InvalidArgumentException If the payload is empty, invalid, or cannot be parsed as a Vega-Lite spec.
	 */
	public function render( $payload, string $title = 'Graph' ): void {
		LanguageEngine::get()->tick();

		if ( is_string( $payload ) ) {
			$trimmed = trim( $payload );
			if ( '' === $trimmed ) {
				throw new \InvalidArgumentException( 'Graph.render() expects a non-empty Vega-Lite specification payload.' );
			}

			$decoded = json_decode( $trimmed, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				throw new \InvalidArgumentException( esc_html( 'Invalid JSON payload provided to Graph.render(): ' . json_last_error_msg() ) );
			}

			$spec = $decoded;
		} elseif ( is_object( $payload ) ) {
			$encoded = wp_json_encode( $payload );
			$spec    = json_decode( $encoded, true );
		} elseif ( is_array( $payload ) ) {
			$spec = $payload;
		} else {
			throw new \InvalidArgumentException( 'Graph.render() expects an associative array, object, or valid JSON string representing a Vega-Lite specification.' );
		}

		if ( empty( $spec ) || ! is_array( $spec ) ) {
			throw new \InvalidArgumentException( 'Graph.render() expects a non-empty Vega-Lite specification payload.' );
		}

		if ( array_is_list( $spec ) ) {
			throw new \InvalidArgumentException( 'Graph.render() expects an associative specification object, not an indexed list.' );
		}

		// Inject sensible defaults if not explicitly provided.
		if ( ! isset( $spec['$schema'] ) || empty( $spec['$schema'] ) ) {
			$spec['$schema'] = self::DEFAULT_SCHEMA;
		}

		if ( ! isset( $spec['width'] ) ) {
			$spec['width'] = self::DEFAULT_WIDTH;
		}

		// Resolve title.
		$resolved_title = $title;
		if ( 'Graph' === $title ) {
			if ( ! empty( $spec['title'] ) && is_string( $spec['title'] ) ) {
				$resolved_title = $spec['title'];
			} elseif ( ! empty( $spec['description'] ) && is_string( $spec['description'] ) ) {
				$resolved_title = $spec['description'];
			}
		}

		LanguageEngine::get()->begin_visualization_group()->add_visualization(
			array(
				'type'  => 'graph',
				'title' => $resolved_title,
				'data'  => $spec,
			)
		);
	}

	/**
	 * Renders an ergonomic bar chart with automatic field inference.
	 *
	 * TODO: Documentation and examples.
	 *
	 * @param mixed $data    List of associative records or key-value map.
	 * @param array $options Optional chart settings: `'title'`, `'x'`, `'y'`, `'color'`, `'color_value'`, `'horizontal'`, `'height'`.
	 * @return void
	 */
	public function bars( $data, array $options = array() ): void {
		$records = $this->normalize_records( $data );
		$fields  = $this->detect_xy_fields( $records, $options );

		$title      = isset( $options['title'] ) ? (string) $options['title'] : 'Bar Chart';
		$horizontal = ! empty( $options['horizontal'] );
		$height     = isset( $options['height'] ) ? (int) $options['height'] : 360;

		$mark = array(
			'type'            => 'bar',
			'cornerRadiusEnd' => 4,
			'tooltip'         => true,
		);

		if ( ! empty( $options['color_value'] ) ) {
			$mark['color'] = (string) $options['color_value'];
		} elseif ( empty( $options['color'] ) ) {
			$mark['color'] = self::COLOR_BLUE;
		}

		$encoding = array();

		if ( $horizontal ) {
			$encoding['x'] = array(
				'field' => $fields['y_field'],
				'type'  => 'quantitative',
				'axis'  => array( 'title' => ucwords( str_replace( '_', ' ', $fields['y_field'] ) ) ),
			);
			$encoding['y'] = array(
				'field' => $fields['x_field'],
				'type'  => $fields['x_type'],
				'axis'  => array( 'title' => ucwords( str_replace( '_', ' ', $fields['x_field'] ) ) ),
			);
			if ( ! empty( $options['sort'] ) ) {
				$encoding['y']['sort'] = $options['sort'];
			}
		} else {
			$encoding['x'] = array(
				'field' => $fields['x_field'],
				'type'  => $fields['x_type'],
				'axis'  => array(
					'title'      => ucwords( str_replace( '_', ' ', $fields['x_field'] ) ),
					'labelAngle' => isset( $options['label_angle'] ) ? (int) $options['label_angle'] : -45,
				),
			);
			$encoding['y'] = array(
				'field' => $fields['y_field'],
				'type'  => 'quantitative',
				'axis'  => array( 'title' => ucwords( str_replace( '_', ' ', $fields['y_field'] ) ) ),
			);
			if ( ! empty( $options['sort'] ) ) {
				$encoding['x']['sort'] = $options['sort'];
			}
		}

		if ( ! empty( $options['color'] ) ) {
			$encoding['color'] = array(
				'field'  => (string) $options['color'],
				'type'   => 'nominal',
				'legend' => array( 'title' => ucwords( str_replace( '_', ' ', (string) $options['color'] ) ) ),
			);
		}

		$spec = array(
			'title'    => $title,
			'height'   => $height,
			'data'     => array( 'values' => $records ),
			'mark'     => $mark,
			'encoding' => $encoding,
		);

		$this->render( $spec, $title );
	}

	/**
	 * Renders an ergonomic line chart with optional multiple series.
	 *
	 * TODO: Documentation and examples.
	 *
	 * @param mixed $data    List of associative records.
	 * @param array $options Optional chart settings: `'title'`, `'x'`, `'y'`, `'color'`, `'color_value'`, `'height'`, `'points'`.
	 * @return void
	 */
	public function lines( $data, array $options = array() ): void {
		$records = $this->normalize_records( $data );
		$fields  = $this->detect_xy_fields( $records, $options );

		$title  = isset( $options['title'] ) ? (string) $options['title'] : 'Line Chart';
		$height = isset( $options['height'] ) ? (int) $options['height'] : 360;
		$points = ! isset( $options['points'] ) || ! empty( $options['points'] );

		$mark = array(
			'type'    => 'line',
			'point'   => $points,
			'tooltip' => true,
		);

		if ( ! empty( $options['color_value'] ) ) {
			$mark['color'] = (string) $options['color_value'];
		} elseif ( empty( $options['color'] ) ) {
			$mark['color'] = self::COLOR_BLUE;
		}

		$encoding = array(
			'x' => array(
				'field' => $fields['x_field'],
				'type'  => $fields['x_type'],
				'axis'  => array(
					'title'      => ucwords( str_replace( '_', ' ', $fields['x_field'] ) ),
					'labelAngle' => isset( $options['label_angle'] ) ? (int) $options['label_angle'] : -45,
				),
			),
			'y' => array(
				'field' => $fields['y_field'],
				'type'  => 'quantitative',
				'axis'  => array( 'title' => ucwords( str_replace( '_', ' ', $fields['y_field'] ) ) ),
			),
		);

		if ( array_key_exists( 'sort', $options ) ) {
			$encoding['x']['sort'] = $options['sort'];
		} elseif ( 'nominal' === $fields['x_type'] ) {
			$encoding['x']['sort'] = null;
		}

		if ( ! empty( $options['color'] ) ) {
			$encoding['color'] = array(
				'field'  => (string) $options['color'],
				'type'   => 'nominal',
				'legend' => array( 'title' => ucwords( str_replace( '_', ' ', (string) $options['color'] ) ) ),
			);
		}

		$spec = array(
			'title'    => $title,
			'height'   => $height,
			'data'     => array( 'values' => $records ),
			'mark'     => $mark,
			'encoding' => $encoding,
		);

		$this->render( $spec, $title );
	}

	/**
	 * Renders a pie or donut chart.
	 *
	 * TODO: Documentation and examples.
	 *
	 * @param mixed $data    List of associative records or key-value map.
	 * @param array $options Optional chart settings: `'title'`, `'category'`, `'value'`, `'donut'`, `'inner_radius'`, `'scheme'`, `'height'`.
	 * @return void
	 */
	public function pie( $data, array $options = array() ): void {
		$records = $this->normalize_records( $data );
		$fields  = $this->detect_xy_fields( $records, $options );

		$cat_field = isset( $options['category'] ) ? (string) $options['category'] : $fields['x_field'];
		$val_field = isset( $options['value'] ) ? (string) $options['value'] : $fields['y_field'];

		$title  = isset( $options['title'] ) ? (string) $options['title'] : ( ! empty( $options['donut'] ) ? 'Donut Chart' : 'Pie Chart' );
		$donut  = ! empty( $options['donut'] );
		$radius = $donut ? ( isset( $options['inner_radius'] ) ? (int) $options['inner_radius'] : 65 ) : 0;
		$height = isset( $options['height'] ) ? (int) $options['height'] : 360;
		$scheme = isset( $options['scheme'] ) ? (string) $options['scheme'] : self::SCHEME_TABLEAU10;

		$spec = array(
			'title'    => $title,
			'height'   => $height,
			'data'     => array( 'values' => $records ),
			'mark'     => array(
				'type'        => 'arc',
				'innerRadius' => $radius,
				'tooltip'     => true,
			),
			'encoding' => array(
				'theta' => array(
					'field' => $val_field,
					'type'  => 'quantitative',
					'stack' => true,
				),
				'color' => array(
					'field'  => $cat_field,
					'type'   => 'nominal',
					'scale'  => array( 'scheme' => $scheme ),
					'legend' => array( 'title' => ucwords( str_replace( '_', ' ', $cat_field ) ) ),
				),
			),
		);

		$this->render( $spec, $title );
	}

	/**
	 * Renders a complete offline choropleth World Map with base geography and heat layer.
	 *
	 * Automatically resolves ISO 3166-1 alpha-2 codes (`'US'`, `'ES'`), alpha-3 codes (`'USA'`, `'ESP'`),
	 * numeric TopoJSON IDs (840, 724, `'032'`), or common country names (`'United States'`, `'Russia'`)
	 * against pre-bundled TopoJSON geometries, generating tooltips and choropleth layers.
	 *
	 * TODO: Documentation and examples.
	 *
	 * @param mixed $data    List of associative records or dictionary with country identifiers and metrics.
	 * @param array $options Optional map settings: `'title'`, `'key'`, `'value'`, `'label'`, `'scheme'`, `'projection'`, `'height'`, `'legend_title'`.
	 * @return void
	 */
	public function worldmap( $data, array $options = array() ): void {
		$records = $this->normalize_records( $data );
		$first   = $records[0];

		// Auto-detect key field.
		$key_field = isset( $options['key'] ) ? (string) $options['key'] : null;
		if ( null === $key_field ) {
			$candidate_keys = array( 'country', 'country_code', 'code', 'iso', 'iso_code', 'alpha2', 'alpha_2', 'alpha3', 'alpha_3', 'id', 'country_name' );
			foreach ( $candidate_keys as $candidate ) {
				if ( array_key_exists( $candidate, $first ) ) {
					$key_field = $candidate;
					break;
				}
			}

			if ( null === $key_field ) {
				foreach ( $first as $k => $v ) {
					if ( null !== $this->resolve_country( $v ) ) {
						$key_field = $k;
						break;
					}
				}
			}

			if ( null === $key_field ) {
				$key_field = array_keys( $first )[0];
			}
		}

		// Auto-detect value metric field.
		$val_field = isset( $options['value'] ) ? (string) $options['value'] : null;
		if ( null === $val_field ) {
			foreach ( $first as $k => $v ) {
				if ( $k !== $key_field && 'id' !== strtolower( $k ) && ! str_ends_with( strtolower( $k ), '_id' ) && ( is_int( $v ) || is_float( $v ) || ( is_string( $v ) && is_numeric( $v ) ) ) ) {
					$val_field = $k;
					break;
				}
			}
			if ( null === $val_field ) {
				foreach ( $first as $k => $v ) {
					if ( $k !== $key_field && ( is_int( $v ) || is_float( $v ) || ( is_string( $v ) && is_numeric( $v ) ) ) ) {
						$val_field = $k;
						break;
					}
				}
			}
			$val_field = $val_field ?? ( array_keys( $first )[1] ?? $key_field );
		}

		// Auto-detect optional extra label field.
		$label_field = isset( $options['label'] ) ? (string) $options['label'] : null;
		if ( null === $label_field ) {
			foreach ( $first as $k => $v ) {
				if ( $k !== $key_field && $k !== $val_field && is_string( $v ) ) {
					$label_field = $k;
					break;
				}
			}
		}

		// Normalize each record with standardized numeric ISO 3166-1 TopoJSON ID and country metadata.
		$normalized_records = array();
		foreach ( $records as $row ) {
			$raw_key  = $row[ $key_field ] ?? null;
			$resolved = $this->resolve_country( $raw_key );

			if ( null !== $resolved ) {
				$row['id'] = $resolved['id'];
				if ( ! isset( $row['country_name'] ) ) {
					$row['country_name'] = $resolved['name'];
				}
				if ( ! isset( $row['country_code'] ) ) {
					$row['country_code'] = $resolved['alpha2'];
				}
			} elseif ( ! isset( $row['id'] ) ) {
				$row['id'] = is_numeric( $raw_key ) ? sprintf( '%03d', (int) $raw_key ) : (string) $raw_key;
			} else {
				$row['id'] = is_numeric( $row['id'] ) ? sprintf( '%03d', (int) $row['id'] ) : (string) $row['id'];
			}

			$normalized_records[] = $row;
		}

		$title      = isset( $options['title'] ) ? (string) $options['title'] : 'World Map';
		$scheme     = isset( $options['scheme'] ) ? (string) $options['scheme'] : self::SCHEME_BLUES;
		$height     = isset( $options['height'] ) ? (int) $options['height'] : 480;
		$projection = isset( $options['projection'] ) ? (string) $options['projection'] : 'equalEarth';

		$lookup_fields = array( $val_field );
		if ( isset( $normalized_records[0]['country_name'] ) && ! in_array( 'country_name', $lookup_fields, true ) ) {
			$lookup_fields[] = 'country_name';
		}
		if ( isset( $normalized_records[0]['country_code'] ) && ! in_array( 'country_code', $lookup_fields, true ) ) {
			$lookup_fields[] = 'country_code';
		}
		if ( ! empty( $label_field ) && ! in_array( $label_field, $lookup_fields, true ) ) {
			$lookup_fields[] = $label_field;
		}

		$tooltips = array();
		if ( in_array( 'country_name', $lookup_fields, true ) ) {
			$tooltips[] = array(
				'field' => 'country_name',
				'type'  => 'nominal',
				'title' => 'Country',
			);
		}
		if ( in_array( 'country_code', $lookup_fields, true ) ) {
			$tooltips[] = array(
				'field' => 'country_code',
				'type'  => 'nominal',
				'title' => 'Code',
			);
		}
		if ( ! empty( $label_field ) && ! in_array( $label_field, array( 'country_name', 'country_code', 'country' ), true ) ) {
			$tooltips[] = array(
				'field' => $label_field,
				'type'  => 'nominal',
				'title' => ucwords( str_replace( '_', ' ', $label_field ) ),
			);
		}
		$tooltips[] = array(
			'field' => $val_field,
			'type'  => 'quantitative',
			'title' => ucwords( str_replace( '_', ' ', $val_field ) ),
		);

		// Layer 1: Base world geometry in neutral gray.
		$base_layer = array(
			'data' => array(
				'url'    => self::COUNTRIES,
				'format' => array(
					'type'    => 'topojson',
					'feature' => 'countries',
				),
			),
			'mark' => array(
				'type'        => 'geoshape',
				'fill'        => '#eef0f3',
				'stroke'      => '#bebebe',
				'strokeWidth' => 0.6,
			),
		);

		// Layer 2: Filtered data choropleth layer.
		$data_layer = array(
			'data'      => array(
				'url'    => self::COUNTRIES,
				'format' => array(
					'type'    => 'topojson',
					'feature' => 'countries',
				),
			),
			'transform' => array(
				array(
					'lookup' => 'id',
					'from'   => array(
						'data'   => array( 'values' => $normalized_records ),
						'key'    => 'id',
						'fields' => $lookup_fields,
					),
				),
				array(
					'filter' => "isValid(datum['{$val_field}'])",
				),
			),
			'mark'      => array(
				'type'        => 'geoshape',
				'stroke'      => '#000000',
				'strokeWidth' => 0.5,
			),
			'encoding'  => array(
				'color'   => array(
					'field'  => $val_field,
					'type'   => 'quantitative',
					'scale'  => array(
						'scheme' => $scheme,
						'zero'   => false,
					),
					'legend' => array(
						'title'          => isset( $options['legend_title'] ) ? (string) $options['legend_title'] : ucwords( str_replace( '_', ' ', $val_field ) ),
						'orient'         => 'bottom-left',
						'gradientLength' => 180,
					),
				),
				'tooltip' => $tooltips,
			),
		);

		$spec = array(
			'title'      => $title,
			'height'     => $height,
			'projection' => array( 'type' => $projection ),
			'layer'      => array( $base_layer, $data_layer ),
		);

		$this->render( $spec, $title );
	}

	/**
	 * Renders a thematic US States choropleth map using TopoJSON geometries and Albers USA projection.
	 *
	 * Automatically resolves state abbreviations (`'CA'`, `'NY'`), state names (`'California'`, `'Texas'`),
	 * or numeric FIPS codes (`6`, `'06'`, `48`) into standard TopoJSON IDs, generating tooltips and
	 * seamless geographic layering.
	 *
	 * TODO: Documentation and examples.
	 *
	 * @param mixed $data    List of associative records or dictionary with state identifiers and metrics.
	 * @param array $options Optional map settings: `'title'`, `'key'`, `'value'`, `'label'`, `'scheme'`, `'projection'`, `'height'`, `'legend_title'`.
	 * @return void
	 */
	public function usa( $data, array $options = array() ): void {
		$records = $this->normalize_records( $data );
		$first   = $records[0];

		// Auto-detect key field.
		$key_field = isset( $options['key'] ) ? (string) $options['key'] : null;
		if ( null === $key_field ) {
			$candidate_keys = array( 'state', 'state_code', 'code', 'fips', 'id', 'state_name' );
			foreach ( $candidate_keys as $candidate ) {
				if ( array_key_exists( $candidate, $first ) ) {
					$key_field = $candidate;
					break;
				}
			}

			if ( null === $key_field ) {
				foreach ( $first as $k => $v ) {
					if ( null !== $this->resolve_us_state( $v ) ) {
						$key_field = $k;
						break;
					}
				}
			}

			if ( null === $key_field ) {
				$key_field = array_keys( $first )[0];
			}
		}

		// Auto-detect value metric field.
		$val_field = isset( $options['value'] ) ? (string) $options['value'] : null;
		if ( null === $val_field ) {
			foreach ( $first as $k => $v ) {
				if ( $k !== $key_field && ( is_int( $v ) || is_float( $v ) || ( is_string( $v ) && is_numeric( $v ) ) ) ) {
					$val_field = $k;
					break;
				}
			}
			$val_field = $val_field ?? ( array_keys( $first )[1] ?? $key_field );
		}

		// Auto-detect optional extra label field.
		$label_field = isset( $options['label'] ) ? (string) $options['label'] : null;
		if ( null === $label_field ) {
			foreach ( $first as $k => $v ) {
				if ( $k !== $key_field && $k !== $val_field && is_string( $v ) ) {
					$label_field = $k;
					break;
				}
			}
		}

		// Normalize each record with standardized FIPS id and state metadata.
		$normalized_records = array();
		foreach ( $records as $row ) {
			$raw_key  = $row[ $key_field ] ?? null;
			$resolved = $this->resolve_us_state( $raw_key );

			if ( null !== $resolved ) {
				$row['id'] = $resolved['fips'];
				if ( ! isset( $row['state_name'] ) ) {
					$row['state_name'] = $resolved['name'];
				}
				if ( ! isset( $row['state_code'] ) ) {
					$row['state_code'] = $resolved['code'];
				}
			} elseif ( ! isset( $row['id'] ) ) {
				$row['id'] = is_numeric( $raw_key ) ? sprintf( '%02d', (int) $raw_key ) : (string) $raw_key;
			}

			$normalized_records[] = $row;
		}

		$title      = isset( $options['title'] ) ? (string) $options['title'] : 'US State Map';
		$scheme     = isset( $options['scheme'] ) ? (string) $options['scheme'] : self::SCHEME_BLUES;
		$height     = isset( $options['height'] ) ? (int) $options['height'] : 450;
		$projection = isset( $options['projection'] ) ? (string) $options['projection'] : 'albersUsa';

		$lookup_fields = array( $val_field );
		if ( isset( $normalized_records[0]['state_name'] ) && ! in_array( 'state_name', $lookup_fields, true ) ) {
			$lookup_fields[] = 'state_name';
		}
		if ( isset( $normalized_records[0]['state_code'] ) && ! in_array( 'state_code', $lookup_fields, true ) ) {
			$lookup_fields[] = 'state_code';
		}
		if ( ! empty( $label_field ) && ! in_array( $label_field, $lookup_fields, true ) ) {
			$lookup_fields[] = $label_field;
		}

		$tooltips = array();
		if ( in_array( 'state_name', $lookup_fields, true ) ) {
			$tooltips[] = array(
				'field' => 'state_name',
				'type'  => 'nominal',
				'title' => 'State',
			);
		}
		if ( in_array( 'state_code', $lookup_fields, true ) ) {
			$tooltips[] = array(
				'field' => 'state_code',
				'type'  => 'nominal',
				'title' => 'Code',
			);
		}
		if ( ! empty( $label_field ) && ! in_array( $label_field, array( 'state_name', 'state_code' ), true ) ) {
			$tooltips[] = array(
				'field' => $label_field,
				'type'  => 'nominal',
				'title' => ucwords( str_replace( '_', ' ', $label_field ) ),
			);
		}
		$tooltips[] = array(
			'field' => $val_field,
			'type'  => 'quantitative',
			'title' => ucwords( str_replace( '_', ' ', $val_field ) ),
		);

		// Layer 1: Base US geography in neutral gray.
		$base_layer = array(
			'data' => array(
				'url'    => self::USA_STATES,
				'format' => array(
					'type'    => 'topojson',
					'feature' => 'states',
				),
			),
			'mark' => array(
				'type'        => 'geoshape',
				'fill'        => '#eef0f3',
				'stroke'      => '#bebebe',
				'strokeWidth' => 0.6,
			),
		);

		// Layer 2: Filtered data choropleth layer.
		$data_layer = array(
			'data'      => array(
				'url'    => self::USA_STATES,
				'format' => array(
					'type'    => 'topojson',
					'feature' => 'states',
				),
			),
			'transform' => array(
				array(
					'lookup' => 'id',
					'from'   => array(
						'data'   => array( 'values' => $normalized_records ),
						'key'    => 'id',
						'fields' => $lookup_fields,
					),
				),
				array(
					'filter' => "isValid(datum['{$val_field}'])",
				),
			),
			'mark'      => array(
				'type'        => 'geoshape',
				'stroke'      => '#000000',
				'strokeWidth' => 0.5,
			),
			'encoding'  => array(
				'color'   => array(
					'field'  => $val_field,
					'type'   => 'quantitative',
					'scale'  => array(
						'scheme' => $scheme,
						'zero'   => false,
					),
					'legend' => array(
						'title'          => isset( $options['legend_title'] ) ? (string) $options['legend_title'] : ucwords( str_replace( '_', ' ', $val_field ) ),
						'orient'         => 'bottom-left',
						'gradientLength' => 180,
					),
				),
				'tooltip' => $tooltips,
			),
		);

		$spec = array(
			'title'      => $title,
			'height'     => $height,
			'projection' => array( 'type' => $projection ),
			'layer'      => array( $base_layer, $data_layer ),
		);

		$this->render( $spec, $title );
	}

	/**
	 * Renders a boxplot chart to visualize distributions and statistical quartiles.
	 *
	 * TODO: Documentation and examples.
	 *
	 * @param mixed $data    List of associative records.
	 * @param array $options Optional chart settings: `'title'`, `'x'`, `'y'`, `'color'`, `'height'`.
	 * @return void
	 */
	public function boxplot( $data, array $options = array() ): void {
		$records = $this->normalize_records( $data );
		$fields  = $this->detect_xy_fields( $records, $options );

		$title  = isset( $options['title'] ) ? (string) $options['title'] : 'Boxplot';
		$height = isset( $options['height'] ) ? (int) $options['height'] : 360;

		$encoding = array(
			'x' => array(
				'field' => $fields['x_field'],
				'type'  => $fields['x_type'],
				'axis'  => array(
					'title'      => ucwords( str_replace( '_', ' ', $fields['x_field'] ) ),
					'labelAngle' => isset( $options['label_angle'] ) ? (int) $options['label_angle'] : -45,
				),
			),
			'y' => array(
				'field' => $fields['y_field'],
				'type'  => 'quantitative',
				'axis'  => array( 'title' => ucwords( str_replace( '_', ' ', $fields['y_field'] ) ) ),
			),
		);

		$box_color = ! empty( $options['color_value'] ) ? (string) $options['color_value'] : self::COLOR_BLUE;

		$mark = array(
			'type'    => 'boxplot',
			'extent'  => 'min-max',
			'box'     => array( 'fill' => $box_color ),
			'rule'    => array( 'color' => 'black' ),
			'tooltip' => true,
		);

		if ( ! empty( $options['color'] ) ) {
			$color_opt = (string) $options['color'];
			if ( array_key_exists( $color_opt, $records[0] ) ) {
				$encoding['color'] = array(
					'field'  => $color_opt,
					'type'   => 'nominal',
					'legend' => array( 'title' => ucwords( str_replace( '_', ' ', $color_opt ) ) ),
				);
				unset( $mark['box'] );
			} else {
				$mark['box'] = array( 'fill' => $color_opt );
			}
		}

		$spec = array(
			'title'    => $title,
			'height'   => $height,
			'data'     => array( 'values' => $records ),
			'mark'     => $mark,
			'encoding' => $encoding,
		);

		$this->render( $spec, $title );
	}

	/**
	 * Renders a scatter / bubble plot to visualize correlations and distributions between two continuous variables.
	 *
	 * TODO: Documentation and examples.
	 *
	 * @param mixed $data    List of associative records.
	 * @param array $options Optional chart settings: 'title', 'x', 'y', 'color', 'size', 'opacity', 'zero', 'height'.
	 * @return void
	 */
	public function scatter( $data, array $options = array() ): void {
		$records = $this->normalize_records( $data );
		$first   = $records[0];

		$title   = isset( $options['title'] ) ? (string) $options['title'] : 'Scatter Plot';
		$height  = isset( $options['height'] ) ? (int) $options['height'] : 360;
		$opacity = isset( $options['opacity'] ) ? (float) $options['opacity'] : 0.7;
		$zero    = isset( $options['zero'] ) ? (bool) $options['zero'] : false;

		// Detect numeric fields for X and Y.
		$numeric_fields = array();
		$string_fields  = array();
		foreach ( $first as $k => $v ) {
			if ( is_int( $v ) || is_float( $v ) || ( is_string( $v ) && is_numeric( $v ) ) ) {
				$numeric_fields[] = $k;
			} elseif ( is_string( $v ) ) {
				$string_fields[] = $k;
			}
		}

		$x_field = isset( $options['x'] ) ? (string) $options['x'] : ( $numeric_fields[0] ?? array_keys( $first )[0] );
		$y_field = isset( $options['y'] ) ? (string) $options['y'] : ( $numeric_fields[1] ?? ( $numeric_fields[0] ?? ( array_keys( $first )[1] ?? $x_field ) ) );

		$mark = array(
			'type'    => 'point',
			'filled'  => true,
			'opacity' => $opacity,
		);

		if ( isset( $options['size'] ) && ( is_int( $options['size'] ) || is_float( $options['size'] ) ) ) {
			$mark['size'] = $options['size'];
		}

		$encoding = array(
			'x' => array(
				'field' => $x_field,
				'type'  => 'quantitative',
				'scale' => array( 'zero' => $zero ),
				'axis'  => array(
					'title'      => ucwords( str_replace( '_', ' ', $x_field ) ),
					'labelAngle' => isset( $options['label_angle'] ) ? (int) $options['label_angle'] : -45,
				),
			),
			'y' => array(
				'field' => $y_field,
				'type'  => 'quantitative',
				'scale' => array( 'zero' => $zero ),
				'axis'  => array( 'title' => ucwords( str_replace( '_', ' ', $y_field ) ) ),
			),
		);

		// Color encoding: field or fixed literal.
		if ( ! empty( $options['color'] ) ) {
			$color_opt = (string) $options['color'];
			if ( array_key_exists( $color_opt, $first ) ) {
				$encoding['color'] = array(
					'field'  => $color_opt,
					'type'   => is_numeric( $first[ $color_opt ] ?? null ) ? 'quantitative' : 'nominal',
					'legend' => array( 'title' => ucwords( str_replace( '_', ' ', $color_opt ) ) ),
				);
			} else {
				$mark['color'] = $color_opt;
			}
		} elseif ( ! empty( $string_fields ) && ! in_array( $string_fields[0], array( $x_field, $y_field ), true ) ) {
			$encoding['color'] = array(
				'field'  => $string_fields[0],
				'type'   => 'nominal',
				'legend' => array( 'title' => ucwords( str_replace( '_', ' ', $string_fields[0] ) ) ),
			);
		}

		// Size encoding (bubble chart).
		if ( isset( $options['size'] ) && is_string( $options['size'] ) && array_key_exists( $options['size'], $first ) ) {
			$encoding['size'] = array(
				'field'  => $options['size'],
				'type'   => 'quantitative',
				'legend' => array( 'title' => ucwords( str_replace( '_', ' ', $options['size'] ) ) ),
			);
		}

		// Tooltips.
		$tooltip_fields = array( $x_field, $y_field );
		if ( isset( $encoding['color']['field'] ) && ! in_array( $encoding['color']['field'], $tooltip_fields, true ) ) {
			$tooltip_fields[] = $encoding['color']['field'];
		}
		if ( isset( $encoding['size']['field'] ) && ! in_array( $encoding['size']['field'], $tooltip_fields, true ) ) {
			$tooltip_fields[] = $encoding['size']['field'];
		}
		foreach ( $string_fields as $sf ) {
			if ( ! in_array( $sf, $tooltip_fields, true ) ) {
				$tooltip_fields[] = $sf;
			}
		}

		$tooltips = array();
		foreach ( $tooltip_fields as $tf ) {
			$tooltips[] = array(
				'field' => $tf,
				'type'  => is_numeric( $first[ $tf ] ?? null ) ? 'quantitative' : 'nominal',
				'title' => ucwords( str_replace( '_', ' ', $tf ) ),
			);
		}
		$encoding['tooltip'] = $tooltips;

		$spec = array(
			'title'    => $title,
			'height'   => $height,
			'data'     => array( 'values' => $records ),
			'mark'     => $mark,
			'encoding' => $encoding,
		);

		$this->render( $spec, $title );
	}

	/**
	 * Renders a 2D intensity heatmap / matrix chart.
	 *
	 * TODO: Documentation and examples.
	 *
	 * @param mixed $data    List of associative records.
	 * @param array $options Optional chart settings: `'title'`, `'x'`, `'y'`, `'value'`, `'color'`, `'scheme'`, `'height'`, `'stroke'`.
	 * @return void
	 */
	public function heatmap( $data, array $options = array() ): void {
		$records = $this->normalize_records( $data );
		$first   = $records[0];

		$title  = isset( $options['title'] ) ? (string) $options['title'] : 'Heatmap';
		$height = isset( $options['height'] ) ? (int) $options['height'] : 360;
		$scheme = isset( $options['scheme'] ) ? (string) $options['scheme'] : self::SCHEME_BLUES;
		$stroke = isset( $options['stroke'] ) ? (string) $options['stroke'] : '#ffffff';

		$numeric_fields = array();
		foreach ( $first as $k => $v ) {
			if ( is_int( $v ) || is_float( $v ) || ( is_string( $v ) && is_numeric( $v ) ) ) {
				$numeric_fields[] = $k;
			}
		}

		$last_num  = end( $numeric_fields );
		$val_field = isset( $options['value'] ) ? (string) $options['value'] : ( isset( $options['color'] ) ? (string) $options['color'] : ( false !== $last_num ? $last_num : ( array_keys( $first )[2] ?? array_keys( $first )[0] ) ) );

		$non_val_fields = array();
		foreach ( array_keys( $first ) as $k ) {
			if ( $k !== $val_field ) {
				$non_val_fields[] = $k;
			}
		}

		$x_field = isset( $options['x'] ) ? (string) $options['x'] : null;
		$y_field = isset( $options['y'] ) ? (string) $options['y'] : null;

		if ( null === $x_field || null === $y_field ) {
			$x_candidates = array( 'hour', 'time', 'minute', 'month', 'date', 'col', 'column', 'x' );
			$y_candidates = array( 'day', 'day_of_week', 'weekday', 'row', 'y' );

			if ( null === $x_field ) {
				foreach ( $non_val_fields as $f ) {
					if ( in_array( strtolower( $f ), $x_candidates, true ) ) {
						$x_field = $f;
						break;
					}
				}
			}

			if ( null === $y_field ) {
				foreach ( $non_val_fields as $f ) {
					if ( in_array( strtolower( $f ), $y_candidates, true ) && $f !== $x_field ) {
						$y_field = $f;
						break;
					}
				}
			}

			if ( null === $x_field ) {
				$x_field = $non_val_fields[0] ?? array_keys( $first )[0];
			}
			if ( null === $y_field ) {
				$y_field = ( ( $non_val_fields[0] ?? '' ) === $x_field ) ? ( $non_val_fields[1] ?? $x_field ) : ( $non_val_fields[0] ?? $x_field );
			}
		}

		$x_type = ( is_int( $first[ $x_field ] ?? null ) && ! str_contains( strtolower( $x_field ), 'id' ) ) ? 'ordinal' : 'nominal';
		$y_type = 'nominal';

		$spec = array(
			'title'    => $title,
			'height'   => $height,
			'data'     => array( 'values' => $records ),
			'mark'     => array(
				'type'        => 'rect',
				'stroke'      => $stroke,
				'strokeWidth' => 1,
			),
			'encoding' => array(
				'x'       => array(
					'field' => $x_field,
					'type'  => $x_type,
					'axis'  => array(
						'title'      => ucwords( str_replace( '_', ' ', $x_field ) ),
						'labelAngle' => isset( $options['label_angle'] ) ? (int) $options['label_angle'] : -45,
					),
				),
				'y'       => array(
					'field' => $y_field,
					'type'  => $y_type,
					'axis'  => array( 'title' => ucwords( str_replace( '_', ' ', $y_field ) ) ),
				),
				'color'   => array(
					'field'  => $val_field,
					'type'   => 'quantitative',
					'scale'  => array( 'scheme' => $scheme ),
					'legend' => array(
						'title' => isset( $options['legend_title'] ) ? (string) $options['legend_title'] : ucwords( str_replace( '_', ' ', $val_field ) ),
					),
				),
				'tooltip' => array(
					array(
						'field' => $x_field,
						'type'  => $x_type,
						'title' => ucwords( str_replace( '_', ' ', $x_field ) ),
					),
					array(
						'field' => $y_field,
						'type'  => $y_type,
						'title' => ucwords( str_replace( '_', ' ', $y_field ) ),
					),
					array(
						'field' => $val_field,
						'type'  => 'quantitative',
						'title' => ucwords( str_replace( '_', ' ', $val_field ) ),
					),
				),
			),
		);

		$this->render( $spec, $title );
	}

	/**
	 * Renders a statistical frequency histogram with client-side automatic binning.
	 *
	 * Accepts either a flat array of numbers (e.g. latency readings) or records.
	 *
	 * TODO: Documentation and examples.
	 *
	 * @param mixed $data    List of numbers or associative records.
	 * @param array $options Optional chart settings: `'title'`, `'field'`, `'x'`, `'bins'`, `'maxbins'`, `'step'`, `'color'`, `'height'`.
	 * @return void
	 */
	public function histogram( $data, array $options = array() ): void {
		$records = $this->normalize_records( $data );
		$first   = $records[0];

		$title  = isset( $options['title'] ) ? (string) $options['title'] : 'Histogram';
		$height = isset( $options['height'] ) ? (int) $options['height'] : 360;

		$val_field = isset( $options['field'] ) ? (string) $options['field'] : ( isset( $options['x'] ) ? (string) $options['x'] : null );
		if ( null === $val_field ) {
			foreach ( $first as $k => $v ) {
				if ( is_int( $v ) || is_float( $v ) || ( is_string( $v ) && is_numeric( $v ) ) ) {
					$val_field = $k;
					break;
				}
			}
			$val_field = $val_field ?? array_keys( $first )[0];
		}

		$bin_config = array();
		if ( isset( $options['maxbins'] ) ) {
			$bin_config['maxbins'] = (int) $options['maxbins'];
		} elseif ( isset( $options['step'] ) ) {
			$bin_config['step'] = (float) $options['step'];
		} else {
			$bin_config['maxbins'] = isset( $options['bins'] ) ? (int) $options['bins'] : 20;
		}

		$mark = array(
			'type' => 'bar',
		);

		$encoding = array(
			'x'       => array(
				'field' => $val_field,
				'type'  => 'quantitative',
				'bin'   => $bin_config,
				'axis'  => array(
					'title'      => ucwords( str_replace( '_', ' ', $val_field ) ),
					'labelAngle' => isset( $options['label_angle'] ) ? (int) $options['label_angle'] : -45,
				),
			),
			'y'       => array(
				'aggregate' => 'count',
				'type'      => 'quantitative',
				'axis'      => array( 'title' => 'Count' ),
			),
			'tooltip' => array(
				array(
					'field' => $val_field,
					'bin'   => true,
					'type'  => 'quantitative',
					'title' => ucwords( str_replace( '_', ' ', $val_field ) ),
				),
				array(
					'aggregate' => 'count',
					'type'      => 'quantitative',
					'title'     => 'Count',
				),
			),
		);

		if ( ! empty( $options['color'] ) ) {
			$color_opt = (string) $options['color'];
			if ( array_key_exists( $color_opt, $first ) ) {
				$encoding['color'] = array(
					'field'  => $color_opt,
					'type'   => 'nominal',
					'legend' => array( 'title' => ucwords( str_replace( '_', ' ', $color_opt ) ) ),
				);
			} else {
				$mark['color'] = $color_opt;
			}
		} else {
			$mark['color'] = self::COLOR_BLUE;
		}

		$spec = array(
			'title'    => $title,
			'height'   => $height,
			'data'     => array( 'values' => $records ),
			'mark'     => $mark,
			'encoding' => $encoding,
		);

		$this->render( $spec, $title );
	}

	/**
	 * Renders an area chart to visualize continuous volumes, bandwidth, and stacked time-series.
	 *
	 * Supports standard stacked areas, 100% normalized areas, and organic Streamgraphs.
	 *
	 * TODO: Documentation and examples.
	 *
	 * @param mixed $data    List of associative records.
	 * @param array $options Optional chart settings: `'title'`, `'x'`, `'y'`, `'color'`, `'stream'`, `'normalize'`, `'curve'`, `'opacity'`, `'height'`.
	 * @return void
	 */
	public function area( $data, array $options = array() ): void {
		$records = $this->normalize_records( $data );
		$fields  = $this->detect_xy_fields( $records, $options );

		$title     = isset( $options['title'] ) ? (string) $options['title'] : 'Area Chart';
		$height    = isset( $options['height'] ) ? (int) $options['height'] : 360;
		$stream    = ! empty( $options['stream'] );
		$normalize = ! empty( $options['normalize'] );
		$opacity   = isset( $options['opacity'] ) ? (float) $options['opacity'] : 0.6;
		$curve     = $stream ? 'basis' : ( isset( $options['curve'] ) ? (string) $options['curve'] : ( isset( $options['interpolate'] ) ? (string) $options['interpolate'] : 'monotone' ) );

		$mark = array(
			'type'        => 'area',
			'line'        => true,
			'interpolate' => $curve,
			'opacity'     => $opacity,
		);

		$y_encoding = array(
			'field' => $fields['y_field'],
			'type'  => 'quantitative',
		);

		if ( $stream ) {
			$y_encoding['stack'] = 'center';
			$y_encoding['axis']  = null;
		} elseif ( $normalize ) {
			$y_encoding['stack'] = 'normalize';
			$y_encoding['axis']  = array(
				'title'  => ucwords( str_replace( '_', ' ', $fields['y_field'] ) ) . ' (%)',
				'format' => '.0%',
			);
		} else {
			$y_encoding['axis'] = array( 'title' => ucwords( str_replace( '_', ' ', $fields['y_field'] ) ) );
		}

		$encoding = array(
			'x'       => array(
				'field' => $fields['x_field'],
				'type'  => $fields['x_type'],
				'axis'  => array(
					'title'      => ucwords( str_replace( '_', ' ', $fields['x_field'] ) ),
					'labelAngle' => isset( $options['label_angle'] ) ? (int) $options['label_angle'] : -45,
				),
			),
			'y'       => $y_encoding,
			'tooltip' => true,
		);

		if ( ! empty( $options['color'] ) ) {
			$color_opt = (string) $options['color'];
			if ( array_key_exists( $color_opt, $records[0] ) ) {
				$encoding['color'] = array(
					'field'  => $color_opt,
					'type'   => 'nominal',
					'legend' => array( 'title' => ucwords( str_replace( '_', ' ', $color_opt ) ) ),
				);
			} else {
				$mark['color'] = $color_opt;
			}
		} else {
			$mark['color'] = self::COLOR_BLUE;
		}

		$spec = array(
			'title'    => $title,
			'height'   => $height,
			'data'     => array( 'values' => $records ),
			'mark'     => $mark,
			'encoding' => $encoding,
		);

		$this->render( $spec, $title );
	}

	/**
	 * Renders a timeline / interval Gantt chart for events, tasks, profiler traces, and job execution intervals.
	 *
	 * TODO: Documentation and examples.
	 *
	 * @param mixed $data    List of associative records with task, start, and end definitions.
	 * @param array $options Optional chart settings: `'title'`, `'task'`, `'y'`, `'start'`, `'x'`, `'end'`, `'x2'`, `'color'`, `'height'`.
	 * @return void
	 */
	public function timeline( $data, array $options = array() ): void {
		$records = $this->normalize_records( $data );
		$first   = $records[0];

		$title = isset( $options['title'] ) ? (string) $options['title'] : 'Timeline';

		// Detect task/lane field (Y axis).
		$task_field = isset( $options['task'] ) ? (string) $options['task'] : ( isset( $options['y'] ) ? (string) $options['y'] : null );
		if ( null === $task_field ) {
			$candidate_tasks = array( 'task', 'name', 'event', 'operation', 'label', 'job', 'action', 'title' );
			foreach ( $candidate_tasks as $cand ) {
				if ( array_key_exists( $cand, $first ) ) {
					$task_field = $cand;
					break;
				}
			}
			if ( null === $task_field ) {
				foreach ( $first as $k => $v ) {
					if ( is_string( $v ) ) {
						$task_field = $k;
						break;
					}
				}
				$task_field = $task_field ?? array_keys( $first )[0];
			}
		}

		// Detect start field (X axis).
		$start_field = isset( $options['start'] ) ? (string) $options['start'] : ( isset( $options['x'] ) ? (string) $options['x'] : null );
		if ( null === $start_field ) {
			$candidate_starts = array( 'start', 'start_time', 'begin', 'from', 'started_at' );
			foreach ( $candidate_starts as $cand ) {
				if ( array_key_exists( $cand, $first ) ) {
					$start_field = $cand;
					break;
				}
			}
			if ( null === $start_field ) {
				foreach ( $first as $k => $v ) {
					if ( $k !== $task_field && ( is_numeric( $v ) || is_string( $v ) ) ) {
						$start_field = $k;
						break;
					}
				}
				$start_field = $start_field ?? ( array_keys( $first )[1] ?? $task_field );
			}
		}

		// Detect end field (X2 axis).
		$end_field = isset( $options['end'] ) ? (string) $options['end'] : ( isset( $options['x2'] ) ? (string) $options['x2'] : null );
		if ( null === $end_field ) {
			$candidate_ends = array( 'end', 'end_time', 'finish', 'to', 'finished_at' );
			foreach ( $candidate_ends as $cand ) {
				if ( array_key_exists( $cand, $first ) ) {
					$end_field = $cand;
					break;
				}
			}
			if ( null === $end_field ) {
				foreach ( $first as $k => $v ) {
					if ( $k !== $task_field && $k !== $start_field ) {
						$end_field = $k;
						break;
					}
				}
				$end_field = $end_field ?? ( array_keys( $first )[2] ?? $start_field );
			}
		}

		$start_is_num = is_numeric( $first[ $start_field ] ?? null );
		$x_type       = $start_is_num ? 'quantitative' : 'temporal';

		$calc_height = max( 240, count( $records ) * 32 );
		$height      = isset( $options['height'] ) ? (int) $options['height'] : min( 600, $calc_height );

		$mark = array(
			'type'         => 'bar',
			'cornerRadius' => 4,
			'height'       => 18,
		);

		$encoding = array(
			'y'       => array(
				'field' => $task_field,
				'type'  => 'nominal',
				'axis'  => array( 'title' => ucwords( str_replace( '_', ' ', $task_field ) ) ),
			),
			'x'       => array(
				'field' => $start_field,
				'type'  => $x_type,
				'axis'  => array(
					'title'      => 'Timeline',
					'labelAngle' => isset( $options['label_angle'] ) ? (int) $options['label_angle'] : -45,
				),
			),
			'x2'      => array(
				'field' => $end_field,
			),
			'tooltip' => true,
		);

		if ( ! empty( $options['color'] ) ) {
			$color_opt = (string) $options['color'];
			if ( array_key_exists( $color_opt, $first ) ) {
				$encoding['color'] = array(
					'field'  => $color_opt,
					'type'   => 'nominal',
					'legend' => array( 'title' => ucwords( str_replace( '_', ' ', $color_opt ) ) ),
				);
			} else {
				$mark['color'] = $color_opt;
			}
		} else {
			$mark['color'] = self::COLOR_BLUE;
		}

		$spec = array(
			'title'    => $title,
			'height'   => $height,
			'data'     => array( 'values' => $records ),
			'mark'     => $mark,
			'encoding' => $encoding,
		);

		$this->render( $spec, $title );
	}
}
