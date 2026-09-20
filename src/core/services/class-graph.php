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
 * Provides a visualization bridge and chart builders
 * to render Vega-Lite specifications in the console.
 *
 * @package ExpressionLab
 */
final class Graph implements ServiceInterface {

	/**
	 * Default schema definition URL for Vega-Lite v6 specifications.
	 *
	 * When the `$schema` key is omitted in a custom specification payload,
	 * Graph injects this URL to validate the specification structure.
	 *
	 * @var string
	 */
	const DEFAULT_SCHEMA = 'https://vega.github.io/schema/vega-lite/v6.json';

	/**
	 * Default responsive container width setting.
	 *
	 * Instructs the visualization engine to expand to the width of the parent container element.
	 *
	 * @var string
	 */
	const DEFAULT_WIDTH = 'container';

	/**
	 * Relative path to the bundled TopoJSON World Atlas countries dataset (1:110m resolution).
	 *
	 * Used as the geographic feature source for global choropleth maps.
	 *
	 * @var string
	 */
	const COUNTRIES = 'countries-110m.json';

	/**
	 * Relative path to the bundled TopoJSON World Atlas land masses dataset (1:110m resolution).
	 *
	 * Used as the background outline for continental landmasses.
	 *
	 * @var string
	 */
	const LAND = 'land-110m.json';

	/**
	 * Relative path to the bundled TopoJSON US Atlas states dataset (1:10m resolution).
	 *
	 * Used as the geographic feature source for United States choropleth maps.
	 *
	 * @var string
	 */
	const USA_STATES = 'states-10m.json';

	/**
	 * Primary blue hex color code (#3858e9).
	 *
	 * Default mark fill color for single-series bar, line, scatter, and area visualizations.
	 *
	 * @var string
	 */
	const COLOR_BLUE = '#3858e9';

	/**
	 * Material Red hex color code (#e53935).
	 *
	 * Mark color for warning states, error metrics, and high-intensity accents.
	 *
	 * @var string
	 */
	const COLOR_RED = '#e53935';

	/**
	 * Material Green hex color code (#43a047).
	 *
	 * Mark color for success states, growth trends, and positive metrics.
	 *
	 * @var string
	 */
	const COLOR_GREEN = '#43a047';

	/**
	 * Material Purple hex color code (#8e24aa).
	 *
	 * Mark color for secondary series and categorical accents.
	 *
	 * @var string
	 */
	const COLOR_PURPLE = '#8e24aa';

	/**
	 * Material Orange hex color code (#fb8c00).
	 *
	 * Mark color for alert thresholds and notice metrics.
	 *
	 * @var string
	 */
	const COLOR_ORANGE = '#fb8c00';

	/**
	 * Material Cyan hex color code (#00acc1).
	 *
	 * Mark color for secondary continuous series and neutral metrics.
	 *
	 * @var string
	 */
	const COLOR_CYAN = '#00acc1';

	/**
	 * Material Gray hex color code (#78909c).
	 *
	 * Mark color for baseline data, background tracks, and inactive states.
	 *
	 * @var string
	 */
	const COLOR_GRAY = '#78909c';

	/**
	 * Material Dark Blue Gray hex color code (#263238).
	 *
	 * Mark color for high-contrast strokes, text overlays, and dark elements.
	 *
	 * @var string
	 */
	const COLOR_DARK = '#263238';

	/**
	 * Reds sequential color scheme identifier.
	 *
	 * Maps increasing numeric magnitudes to a gradient of light to deep red hues.
	 *
	 * @var string
	 */
	const SCHEME_REDS = 'reds';

	/**
	 * Blues sequential color scheme identifier.
	 *
	 * Maps increasing numeric magnitudes to a gradient of light to deep blue hues.
	 *
	 * @var string
	 */
	const SCHEME_BLUES = 'blues';

	/**
	 * Greens sequential color scheme identifier.
	 *
	 * Maps increasing numeric magnitudes to a gradient of light to deep green hues.
	 *
	 * @var string
	 */
	const SCHEME_GREENS = 'greens';

	/**
	 * Purples sequential color scheme identifier.
	 *
	 * Maps increasing numeric magnitudes to a gradient of light to deep purple hues.
	 *
	 * @var string
	 */
	const SCHEME_PURPLES = 'purples';

	/**
	 * Oranges sequential color scheme identifier.
	 *
	 * Maps increasing numeric magnitudes to a gradient of light to deep orange hues.
	 *
	 * @var string
	 */
	const SCHEME_ORANGES = 'oranges';

	/**
	 * Viridis uniform color gradient scheme identifier.
	 *
	 * Multi-hue sequential gradient from dark purple through teal to yellow.
	 *
	 * @var string
	 */
	const SCHEME_VIRIDIS = 'viridis';

	/**
	 * Inferno uniform color gradient scheme identifier.
	 *
	 * Multi-hue sequential gradient from black through red and orange to yellow.
	 *
	 * @var string
	 */
	const SCHEME_INFERNO = 'inferno';

	/**
	 * Magma uniform color gradient scheme identifier.
	 *
	 * Multi-hue sequential gradient from black through purple and pink to pale white.
	 *
	 * @var string
	 */
	const SCHEME_MAGMA = 'magma';

	/**
	 * Category10 discrete categorical color scheme identifier.
	 *
	 * Ten distinct hues designed for nominal data dimensions and independent series.
	 *
	 * @var string
	 */
	const SCHEME_CATEGORY10 = 'category10';

	/**
	 * Tableau10 discrete categorical color scheme identifier.
	 *
	 * Ten balanced hues suited for multi-series line charts, pie slices, and group comparisons.
	 *
	 * @var string
	 */
	const SCHEME_TABLEAU10 = 'tableau10';

	/**
	 * Resolves class constant values requested as object properties in ELScript runtime.
	 *
	 * @internal
	 *
	 * @param string $name Property name matching a defined class constant.
	 * @return mixed Constant value when defined; otherwise null.
	 */
	public function __get( string $name ) {
		if ( ! preg_match( '/^[A-Z][A-Z0-9_]*$/', $name ) ) {
			return null;
		}
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

		$color_key = isset( $options['color'] ) ? (string) $options['color'] : null;

		$numeric_keys = array();
		$string_keys  = array();

		foreach ( $first as $key => $val ) {
			if ( $key === $color_key ) {
				continue;
			}
			if ( is_int( $val ) || is_float( $val ) || ( is_string( $val ) && is_numeric( $val ) ) ) {
				$numeric_keys[] = $key;
			} elseif ( is_string( $val ) ) {
				$string_keys[] = $key;
			}
		}

		$fallback_keys = array_values( array_filter( $keys, fn( $k ) => $k !== $color_key ) );
		if ( empty( $fallback_keys ) ) {
			$fallback_keys = $keys;
		}

		$nominal_key      = $string_keys[0] ?? null;
		$quantitative_key = $numeric_keys[0] ?? null;

		// When no string key is found and multiple numeric keys exist, detect time/sequence candidates for X.
		if ( null === $nominal_key && count( $numeric_keys ) >= 2 ) {
			$x_candidates = array( 'hour', 'time', 'minute', 'month', 'date', 'year', 'day', 'step', 'epoch', 'timestamp', 'x' );
			foreach ( $numeric_keys as $num_key ) {
				if ( in_array( strtolower( $num_key ), $x_candidates, true ) ) {
					$nominal_key      = $num_key;
					$remaining        = array_values( array_filter( $numeric_keys, fn( $k ) => $k !== $num_key ) );
					$quantitative_key = $remaining[0] ?? $quantitative_key;
					break;
				}
			}
			if ( null === $nominal_key ) {
				$nominal_key      = $numeric_keys[0];
				$quantitative_key = $numeric_keys[1];
			}
		}

		$x_field = isset( $options['x'] ) ? (string) $options['x'] : ( $nominal_key ?? $fallback_keys[0] );
		$y_field = isset( $options['y'] ) ? (string) $options['y'] : ( $quantitative_key ?? ( $fallback_keys[1] ?? $fallback_keys[0] ) );

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
	 * Renders a Vega-Lite specification in the console visualization panel.
	 *
	 * Accepts an associative array, a generic object, or a JSON string.
	 * When the specification omits `$schema` or `width`, default values
	 * are injected to fit the console container.
	 *
	 * Submitting an indexed list or an invalid JSON string triggers an exception.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Graph.render({
	 *     '$schema': Graph.DEFAULT_SCHEMA,
	 *     'description': 'A simple bar chart',
	 *     'data': {
	 *         'values': [
	 *             { 'a': 'A', 'b': 28 },
	 *             { 'a': 'B', 'b': 55 },
	 *             { 'a': 'C', 'b': 43 }
	 *         ]
	 *     },
	 *     'mark': 'bar',
	 *     'encoding': {
	 *         'x': { 'field': 'a', 'type': 'nominal' },
	 *         'y': { 'field': 'b', 'type': 'quantitative' }
	 *     }
	 * }, 'Custom Vega-Lite Chart')
	 * ```
	 *
	 * @see https://vega.github.io/vega-lite/docs/
	 * @see https://expressionlab.io/docs/api-reference/graph#graphrender
	 *
	 * @param array|object|string $payload Vega-Lite specification structure or JSON string.
	 * @param string              $title   Tab title for the visualization panel. Default `'Graph'`.
	 * @return void
	 * @throws \InvalidArgumentException When the payload is empty, invalid JSON, or an indexed list.
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
	 * Renders a bar chart from tabular records or key-value pairs.
	 *
	 * Maps categorical dimensions to discrete positions and quantitative values
	 * to bar lengths. When field names are omitted in `options`, the method
	 * infers categorical labels and numeric metrics from the input structure.
	 *
	 * Setting `horizontal` to `true` places categorical labels along the vertical axis
	 * and extends bars along the horizontal plane. Providing a `color` field partitions
	 * bars into discrete color groups with an associated legend.
	 *
	 * ## Examples
	 *
	 * ### Tabular records with category grouping:
	 *
	 * ```elscript
	 * Graph.bars([
	 *     { 'role': 'Administrator', 'count': 4 },
	 *     { 'role': 'Editor', 'count': 12 },
	 *     { 'role': 'Subscriber', 'count': 158 }
	 * ], {
	 *     'title': 'User Accounts by Role',
	 *     'color_value': Graph.COLOR_PURPLE
	 * })
	 * ```
	 *
	 * ### Horizontal orientation with key-value map:
	 *
	 * ```elscript
	 * Graph.bars({
	 *     'Draft': 14,
	 *     'Pending': 6,
	 *     'Published': 89
	 * }, {
	 *     'title': 'Post Status Overview',
	 *     'horizontal': true
	 * })
	 * ```
	 *
	 * @see https://vega.github.io/vega-lite/docs/bar.html
	 * @see https://expressionlab.io/docs/api-reference/graph#graphbars
	 *
	 * @param mixed $data    List of associative records or key-value dictionary.
	 * @param array $options Optional chart settings:
	 *                       - `'title'` (string): Panel title. Default `'Bar Chart'`.
	 *                       - `'x'` (string): Field mapped to horizontal axis.
	 *                       - `'y'` (string): Field mapped to vertical axis.
	 *                       - `'color'` (string): Field used for categorical color encoding.
	 *                       - `'color_value'` (string): Fixed hex color for all bars. Default `Graph.COLOR_BLUE`.
	 *                       - `'horizontal'` (bool): Whether to invert axes for horizontal bars. Default `false`.
	 *                       - `'sort'` (string|array): Sorting order for categorical domain.
	 *                       - `'label_angle'` (int): Rotation angle in degrees for axis labels. Default `-45`.
	 *                       - `'height'` (int): Visualization height in pixels. Default `360`.
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
	 * Renders a line chart for continuous trends and time-series records.
	 *
	 * Connects sequential data points across an ordered horizontal axis.
	 * When the input dataset contains multiple series, providing the `color`
	 * option segments lines into distinct color paths and adds a category legend.
	 *
	 * Vertex points are rendered by default and can be suppressed by setting
	 * `points` to `false`.
	 *
	 * ## Examples
	 *
	 * ### Single continuous trend
	 *
	 * ```elscript
	 * Graph.lines([
	 *     { 'date': '2026-01', 'requests': 1420 },
	 *     { 'date': '2026-02', 'requests': 1890 },
	 *     { 'date': '2026-03', 'requests': 2400 }
	 * ], {
	 *     'title': 'Traffic Volume by Month',
	 *     'color_value': Graph.COLOR_GREEN
	 * })
	 * ```
	 *
	 * ### Multi-series comparison
	 *
	 * ```elscript
	 * Graph.lines([
	 *     { 'hour': '08:00', 'load': 24, 'server': 'web-01' },
	 *     { 'hour': '09:00', 'load': 48, 'server': 'web-01' },
	 *     { 'hour': '10:00', 'load': 68, 'server': 'web-01' },
	 *     { 'hour': '11:00', 'load': 52, 'server': 'web-01' },
	 *     { 'hour': '08:00', 'load': 18, 'server': 'web-02' },
	 *     { 'hour': '09:00', 'load': 35, 'server': 'web-02' },
	 *     { 'hour': '10:00', 'load': 82, 'server': 'web-02' },
	 *     { 'hour': '11:00', 'load': 74, 'server': 'web-02' }
	 * ], {
	 *     'title': 'Server Load Comparison',
	 *     'color': 'server'
	 * })
	 * ```
	 *
	 * @see https://vega.github.io/vega-lite/docs/line.html
	 * @see https://expressionlab.io/docs/api-reference/graph#graphlines
	 *
	 * @param mixed $data    List of associative records.
	 * @param array $options Optional chart settings:
	 *                       - `'title'` (string): Panel title. Default `'Line Chart'`.
	 *                       - `'x'` (string): Field mapped to horizontal axis.
	 *                       - `'y'` (string): Field mapped to vertical axis.
	 *                       - `'color'` (string): Field used for multi-series color grouping.
	 *                       - `'color_value'` (string): Fixed hex color for single series. Default `Graph.COLOR_BLUE`.
	 *                       - `'points'` (bool): Whether to draw circle markers on vertices. Default `true`.
	 *                       - `'sort'` (string|array): Order specification for horizontal axis.
	 *                       - `'label_angle'` (int): Rotation angle in degrees for axis labels. Default `-45`.
	 *                       - `'height'` (int): Visualization height in pixels. Default `360`.
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
			$color_opt = (string) $options['color'];
			if ( array_key_exists( $color_opt, $records[0] ) ) {
				$encoding['color'] = array(
					'field'  => $color_opt,
					'type'   => 'nominal',
					'legend' => array( 'title' => ucwords( str_replace( '_', ' ', $color_opt ) ) ),
				);
			} else {
				$mark['color']     = $color_opt;
				$encoding['color'] = array( 'value' => $color_opt );
			}
		} elseif ( ! empty( $options['color_value'] ) ) {
			$color_val         = (string) $options['color_value'];
			$mark['color']     = $color_val;
			$encoding['color'] = array( 'value' => $color_val );
		} else {
			$mark['color']     = self::COLOR_BLUE;
			$encoding['color'] = array( 'value' => self::COLOR_BLUE );
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
	 * Renders a pie or donut chart for proportional distributions.
	 *
	 * Computes angular arc spans proportional to metric values across categorical slices.
	 * Setting `donut` to `true` carves an inner circular cutout.
	 *
	 * Slices receive distinct hues from discrete color schemes such as `Graph.SCHEME_TABLEAU10`.
	 *
	 * ## Examples
	 *
	 * ### Standard pie chart from key-value dictionary
	 *
	 * ```elscript
	 * Graph.pie({
	 *     'Direct': 450,
	 *     'Search': 1200,
	 *     'Referral': 280,
	 *     'Social': 190
	 * }, {
	 *     'title': 'Traffic Acquisition Channels'
	 * })
	 * ```
	 *
	 * ### Donut chart with custom inner radius
	 *
	 * ```elscript
	 * Graph.pie([
	 *     { 'device': 'Mobile', 'share': 58 },
	 *     { 'device': 'Desktop', 'share': 36 },
	 *     { 'device': 'Tablet', 'share': 6 }
	 * ], {
	 *     'title': 'Device Breakdown',
	 *     'donut': true,
	 *     'inner_radius': 80
	 * })
	 * ```
	 *
	 * @see https://vega.github.io/vega-lite/docs/arc.html
	 * @see https://expressionlab.io/docs/api-reference/graph#graphpie
	 *
	 * @param mixed $data    List of associative records or key-value dictionary.
	 * @param array $options Optional chart settings:
	 *                       - `'title'` (string): Panel title. Default `'Pie Chart'` or `'Donut Chart'`.
	 *                       - `'category'` (string): Field containing slice labels.
	 *                       - `'value'` (string): Field containing slice quantities.
	 *                       - `'donut'` (bool): Whether to render an inner cutout hole. Default `false`.
	 *                       - `'inner_radius'` (int): Cutout radius in pixels when donut mode is active. Default `65`.
	 *                       - `'scheme'` (string): Vega-Lite color palette name. Default `Graph.SCHEME_TABLEAU10`.
	 *                       - `'height'` (int): Visualization height in pixels. Default `360`.
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
	 * Renders an offline choropleth World Map with base geography and metric heat layers.
	 *
	 * Projects country data onto global vector geometries using an Equal Earth projection.
	 * Standardizes ISO 3166-1 Alpha-2 codes (`'US'`, `'ES'`), Alpha-3 codes (`'USA'`, `'ESP'`),
	 * TopoJSON numeric identifiers (`840`, `724`, `'032'`), and country names (`'United States'`)
	 * into matching geometry keys.
	 *
	 * Injects full country names and ISO codes into interactive tooltips.
	 * Outputs generated by `IPLookup.to_country()` plug into this method without manual conversion.
	 *
	 * ## Examples
	 *
	 * ### Choropleth from key-value country codes
	 *
	 * ```elscript
	 * Graph.worldmap({
	 *     'US': 1930,
	 *     'ES': 420,
	 *     'DE': 890,
	 *     'BR': 610,
	 *     'JP': 750
	 * }, {
	 *     'title': 'Global Request Origins'
	 * })
	 * ```
	 *
	 * ### Tabular country records
	 *
	 * ```elscript
	 * Graph.worldmap([
	 *     { 'country': 'US', 'threats': 1420 },
	 *     { 'country': 'GB', 'threats': 830 },
	 *     { 'country': 'FR', 'threats': 560 }
	 * ], {
	 *     'title': 'Threat Distribution by Country',
	 *     'scheme': Graph.SCHEME_ORANGES
	 * })
	 * ```
	 *
	 * @see https://vega.github.io/vega-lite/docs/projection.html
	 * @see https://vega.github.io/vega-lite/docs/geoshape.html
	 * @see https://expressionlab.io/docs/api-reference/graph#graphworldmap
	 *
	 * @param mixed $data    List of associative records or key-value dictionary mapping country codes to values.
	 * @param array $options Optional map settings:
	 *                       - `'title'` (string): Panel title. Default `'World Map'`.
	 *                       - `'key'` (string): Field containing country codes, names, or numeric IDs.
	 *                       - `'value'` (string): Field containing metric quantities.
	 *                       - `'label'` (string): Additional metadata field shown in hover tooltips.
	 *                       - `'scheme'` (string): Vega-Lite color scheme for heat gradient. Default `Graph.SCHEME_BLUES`.
	 *                       - `'projection'` (string): Cartographic projection type. Default `'equalEarth'`.
	 *                       - `'height'` (int): Visualization height in pixels. Default `480`.
	 *                       - `'legend_title'` (string): Custom title for color scale legend.
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
			LanguageEngine::get()->tick();
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
					'filter' => 'isValid(datum[\'' . preg_replace( '/[^a-zA-Z0-9_]/', '', $val_field ) . '\'])',
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
	 * Renders a choropleth map of United States states and territories.
	 *
	 * Projects regional metrics onto US TopoJSON boundaries using an Albers USA composite projection.
	 * Standardizes two-letter postal codes (`'CA'`, `'TX'`), full state names (`'California'`),
	 * and numeric FIPS identifiers (`6`, `'06'`) into matching geometry keys.
	 *
	 * Binds metric values to sequential color gradients and enriches hover tooltips
	 * with state names and codes.
	 *
	 * ## Examples
	 *
	 * ### Map from postal abbreviations
	 *
	 * ```elscript
	 * Graph.usa({
	 *     'CA': 9500,
	 *     'TX': 6200,
	 *     'NY': 5800,
	 *     'FL': 4900,
	 *     'IL': 3400
	 * }, {
	 *     'title': 'Sales Volume by State',
	 *     'scheme': Graph.SCHEME_GREENS
	 * })
	 * ```
	 *
	 * ### Tabular state records
	 *
	 * ```elscript
	 * Graph.usa([
	 *     { 'state': 'California', 'active_users': 14200 },
	 *     { 'state': 'Washington', 'active_users': 8300 },
	 *     { 'state': 'Oregon',     'active_users': 5100 }
	 * ], {
	 *     'title': 'Pacific Coast User Base',
	 *     'scheme': Graph.SCHEME_PURPLES
	 * })
	 * ```
	 *
	 * @see https://vega.github.io/vega-lite/docs/projection.html
	 * @see https://vega.github.io/vega-lite/docs/geoshape.html
	 * @see https://expressionlab.io/docs/api-reference/graph#graphusa
	 *
	 * @param mixed $data    List of associative records or dictionary mapping state identifiers to values.
	 * @param array $options Optional map settings:
	 *                       - `'title'` (string): Panel title. Default `'US State Map'`.
	 *                       - `'key'` (string): Field containing state postal codes, names, or FIPS IDs.
	 *                       - `'value'` (string): Field containing metric values.
	 *                       - `'label'` (string): Additional metadata field shown in hover tooltips.
	 *                       - `'scheme'` (string): Vega-Lite color scheme for heat gradient. Default `Graph.SCHEME_BLUES`.
	 *                       - `'projection'` (string): Cartographic projection type. Default `'albersUsa'`.
	 *                       - `'height'` (int): Visualization height in pixels. Default `450`.
	 *                       - `'legend_title'` (string): Custom title for color scale legend.
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
			LanguageEngine::get()->tick();
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
					'filter' => 'isValid(datum[\'' . preg_replace( '/[^a-zA-Z0-9_]/', '', $val_field ) . '\'])',
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
	 * Renders a boxplot chart to depict data distributions and statistical quartiles.
	 *
	 * Computes median, lower quartile (Q1), upper quartile (Q3), and min-max whisker
	 * boundaries for quantitative variables across categories.
	 *
	 * When a `color` field is defined, boxes partition into categorical sub-groups
	 * with distinctive color fills.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Graph.boxplot([
	 *     { 'endpoint': '/api/posts', 'latency_ms': 42 },
	 *     { 'endpoint': '/api/posts', 'latency_ms': 58 },
	 *     { 'endpoint': '/api/posts', 'latency_ms': 120 },
	 *     { 'endpoint': '/api/users', 'latency_ms': 85 },
	 *     { 'endpoint': '/api/users', 'latency_ms': 92 },
	 *     { 'endpoint': '/api/users', 'latency_ms': 240 }
	 * ], {
	 *     'title': 'API Endpoint Latency Distribution',
	 *     'x': 'endpoint',
	 *     'y': 'latency_ms'
	 * })
	 * ```
	 *
	 * @see https://vega.github.io/vega-lite/docs/boxplot.html
	 * @see https://expressionlab.io/docs/api-reference/graph#graphboxplot
	 *
	 * @param mixed $data    List of associative records.
	 * @param array $options Optional chart settings:
	 *                       - `'title'` (string): Panel title. Default `'Boxplot'`.
	 *                       - `'x'` (string): Field mapped to horizontal axis (categorical dimension).
	 *                       - `'y'` (string): Field mapped to vertical axis (quantitative metric).
	 *                       - `'color'` (string): Field name for group colors, or literal color code.
	 *                       - `'color_value'` (string): Fixed hex color for box fill. Default `Graph.COLOR_BLUE`.
	 *                       - `'label_angle'` (int): Rotation angle in degrees for axis labels. Default `-45`.
	 *                       - `'height'` (int): Visualization height in pixels. Default `360`.
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
	 * Renders a scatter or bubble plot to analyze correlations between continuous variables.
	 *
	 * Positions individual markers along two quantitative axes.
	 * Binds optional dimensions to marker size, opacity, or categorical color grouping.
	 *
	 * When data clusters away from the origin, setting `zero` to `false` disables
	 * zero-baseline expansion to focus on the active observation range.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Graph.scatter([
	 *     { 'queries': 6,   'duration_ms': 18,  'rows_examined': 85,    'status': 'ok' },
	 *     { 'queries': 12,  'duration_ms': 28,  'rows_examined': 240,   'status': 'ok' },
	 *     { 'queries': 16,  'duration_ms': 35,  'rows_examined': 410,   'status': 'ok' },
	 *     { 'queries': 22,  'duration_ms': 52,  'rows_examined': 620,   'status': 'ok' },
	 *     { 'queries': 28,  'duration_ms': 48,  'rows_examined': 390,   'status': 'ok' },
	 *     { 'queries': 32,  'duration_ms': 70,  'rows_examined': 880,   'status': 'ok' },
	 *     { 'queries': 38,  'duration_ms': 88,  'rows_examined': 1250,  'status': 'ok' },
	 *     { 'queries': 45,  'duration_ms': 135, 'rows_examined': 2800,  'status': 'warning' },
	 *     { 'queries': 50,  'duration_ms': 160, 'rows_examined': 3400,  'status': 'warning' },
	 *     { 'queries': 58,  'duration_ms': 145, 'rows_examined': 3100,  'status': 'warning' },
	 *     { 'queries': 64,  'duration_ms': 195, 'rows_examined': 5400,  'status': 'warning' },
	 *     { 'queries': 72,  'duration_ms': 230, 'rows_examined': 6800,  'status': 'warning' },
	 *     { 'queries': 78,  'duration_ms': 210, 'rows_examined': 4900,  'status': 'warning' },
	 *     { 'queries': 84,  'duration_ms': 275, 'rows_examined': 8200,  'status': 'warning' },
	 *     { 'queries': 90,  'duration_ms': 360, 'rows_examined': 12500, 'status': 'critical' },
	 *     { 'queries': 98,  'duration_ms': 430, 'rows_examined': 17200, 'status': 'critical' },
	 *     { 'queries': 105, 'duration_ms': 490, 'rows_examined': 22000, 'status': 'critical' },
	 *     { 'queries': 116, 'duration_ms': 560, 'rows_examined': 31000, 'status': 'critical' },
	 *     { 'queries': 128, 'duration_ms': 640, 'rows_examined': 42000, 'status': 'critical' }
	 * ], {
	 *     'title': 'Database Queries vs Execution Duration',
	 *     'x': 'queries',
	 *     'y': 'duration_ms',
	 *     'color': 'status',
	 *     'size': 'rows_examined',
	 *     'opacity': 0.75,
	 *     'height': 380,
	 *     'zero': false
	 * })
	 * ```
	 *
	 * @see https://vega.github.io/vega-lite/docs/point.html
	 * @see https://expressionlab.io/docs/api-reference/graph#graphscatter
	 *
	 * @param mixed $data    List of associative records.
	 * @param array $options Optional chart settings:
	 *                       - `'title'` (string): Panel title. Default `'Scatter Plot'`.
	 *                       - `'x'` (string): Numeric field for horizontal coordinates.
	 *                       - `'y'` (string): Numeric field for vertical coordinates.
	 *                       - `'color'` (string): Field for color grouping, or literal hex color code.
	 *                       - `'size'` (string|int|float): Field for bubble size encoding or fixed marker point size in square pixels.
	 *                       - `'opacity'` (float): Marker opacity from 0.0 to 1.0. Default `0.7`.
	 *                       - `'zero'` (bool): Whether scale axes must include zero. Default `false`.
	 *                       - `'label_angle'` (int): Rotation angle in degrees for axis labels. Default `-45`.
	 *                       - `'height'` (int): Visualization height in pixels. Default `360`.
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
	 * Renders a two-dimensional matrix heatmap for joint categorical or temporal densities.
	 *
	 * Arranges records into a grid of discrete cells across horizontal and vertical coordinates.
	 * Maps cell fill colors to a third quantitative metric using sequential gradient scales.
	 *
	 * White cell borders separate adjacent data partitions.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Graph.heatmap([
	 *     { 'day': 'Mon', 'hour': 9,  'events': 50 },
	 *     { 'day': 'Mon', 'hour': 10, 'events': 320 },
	 *     { 'day': 'Mon', 'hour': 11, 'events': 25 },
	 *     { 'day': 'Mon', 'hour': 12, 'events': 250 },
	 *     { 'day': 'Mon', 'hour': 13, 'events': 195 },
	 *     { 'day': 'Mon', 'hour': 14, 'events': 331 },
	 *     { 'day': 'Mon', 'hour': 15, 'events': 80 },
	 *     { 'day': 'Tue', 'hour': 9,  'events': 190 },
	 *     { 'day': 'Tue', 'hour': 10, 'events': 78 },
	 *     { 'day': 'Tue', 'hour': 11, 'events': 22 },
	 *     { 'day': 'Tue', 'hour': 12, 'events': 25 },
	 *     { 'day': 'Tue', 'hour': 13, 'events': 250 },
	 *     { 'day': 'Tue', 'hour': 14, 'events': 410 },
	 *     { 'day': 'Tue', 'hour': 15, 'events': 295 },
	 *     { 'day': 'Wed', 'hour': 9,  'events': 190 },
	 *     { 'day': 'Wed', 'hour': 10, 'events': 46 },
	 *     { 'day': 'Wed', 'hour': 11, 'events': 101 },
	 *     { 'day': 'Wed', 'hour': 12, 'events': 610 },
	 *     { 'day': 'Wed', 'hour': 13, 'events': 710 },
	 *     { 'day': 'Wed', 'hour': 14, 'events': 155 },
	 *     { 'day': 'Wed', 'hour': 15, 'events': 25 }
	 * ], {
	 *     'title': 'System Events by Day and Hour',
	 *     'x': 'hour',
	 *     'y': 'day',
	 *     'value': 'events',
	 *     'height' : 180,
	 *     'scheme': Graph.SCHEME_PURPLES
	 * })
	 * ```
	 *
	 * @see https://vega.github.io/vega-lite/docs/
	 * @see https://expressionlab.io/docs/api-reference/graph#graphheatmap
	 *
	 * @param mixed $data    List of associative records.
	 * @param array $options Optional chart settings:
	 *                       - `'title'` (string): Panel title. Default `'Heatmap'`.
	 *                       - `'x'` (string): Field for horizontal column coordinates.
	 *                       - `'y'` (string): Field for vertical row coordinates.
	 *                       - `'value'` (string): Quantitative metric mapped to cell color.
	 *                       - `'color'` (string): Alias for `'value'` field selection.
	 *                       - `'scheme'` (string): Sequential color scheme name. Default `Graph.SCHEME_BLUES`.
	 *                       - `'stroke'` (string): Cell border stroke color. Default `'#ffffff'`.
	 *                       - `'legend_title'` (string): Custom title for color bar legend.
	 *                       - `'label_angle'` (int): Rotation angle in degrees for axis labels. Default `-45`.
	 *                       - `'height'` (int): Visualization height in pixels. Default `360`.
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
	 * Renders a statistical frequency histogram with interval binning.
	 *
	 * Groups continuous numeric observations into discrete interval bins
	 * and calculates observation frequencies per bin.
	 *
	 * Accepts either an array of numbers (such as latency measurements)
	 * or tabular records containing a target numeric column.
	 *
	 * ## Examples
	 *
	 * ### Histogram from numeric measurements
	 *
	 * ```elscript
	 * Graph.histogram([
	 *     12.4, 15.1, 18.3, 19.0, 21.2, 22.8, 23.1, 28.5, 34.2, 45.0
	 * ], {
	 *     'title': 'Query Duration Distribution',
	 *     'bins': 10,
	 *     'color': Graph.COLOR_CYAN
	 * })
	 * ```
	 *
	 * ### Tabular records with group coloring
	 *
	 * ```elscript
	 * Graph.histogram([
	 *     { 'duration': 45,  'status': '404' },
	 *     { 'duration': 60,  'status': '404' },
	 *     { 'duration': 80,  'status': '200' },
	 *     { 'duration': 95,  'status': '200' },
	 *     { 'duration': 110, 'status': '200' },
	 *     { 'duration': 125, 'status': '200' },
	 *     { 'duration': 130, 'status': '200' },
	 *     { 'duration': 145, 'status': '200' },
	 *     { 'duration': 160, 'status': '200' },
	 *     { 'duration': 175, 'status': '200' },
	 *     { 'duration': 190, 'status': '500' },
	 *     { 'duration': 210, 'status': '200' },
	 *     { 'duration': 220, 'status': '500' },
	 *     { 'duration': 260, 'status': '500' },
	 *     { 'duration': 310, 'status': '500' },
	 *     { 'duration': 350, 'status': '500' }
	 * ], {
	 *     'title': 'HTTP Latency by Response Status',
	 *     'field': 'duration',
	 *     'color': 'status',
	 *     'bins': 8
	 * })
	 * ```
	 *
	 * @see https://vega.github.io/vega-lite/docs/
	 * @see https://expressionlab.io/docs/api-reference/graph#graphhistogram
	 *
	 * @param mixed $data    List of scalar numbers or associative records.
	 * @param array $options Optional chart settings:
	 *                       - `'title'` (string): Panel title. Default `'Histogram'`.
	 *                       - `'field'` (string): Numeric field to partition into bins.
	 *                       - `'x'` (string): Alias for `'field'`.
	 *                       - `'bins'` (int): Maximum target bin count. Default `20`.
	 *                       - `'maxbins'` (int): Upper bound for bin divisions.
	 *                       - `'step'` (float): Fixed step width for each bin interval.
	 *                       - `'color'` (string): Field name for color grouping, or fixed color code.
	 *                       - `'label_angle'` (int): Rotation angle in degrees for axis labels. Default `-45`.
	 *                       - `'height'` (int): Visualization height in pixels. Default `360`.
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
	 * Renders an area chart for cumulative volumes, bandwidth, and stacked time-series.
	 *
	 * Fills the region between baseline coordinates and metric values across an ordered axis.
	 *
	 * Setting `stream` to `true` centers stacked series around a middle baseline to form
	 * a streamgraph. Setting `normalize` to `true` standardizes stack heights to 100%
	 * relative proportions.
	 *
	 * ## Examples
	 *
	 * ### Single volume area
	 *
	 * ```elscript
	 * Graph.area([
	 *     { 'timestamp': '10:00', 'bandwidth_mb': 120 },
	 *     { 'timestamp': '10:05', 'bandwidth_mb': 240 },
	 *     { 'timestamp': '10:10', 'bandwidth_mb': 180 }
	 * ], {
	 *     'title': 'Network Bandwidth Usage',
	 *     'color': Graph.COLOR_BLUE
	 * })
	 * ```
	 *
	 * ### Stacked multi-series infrastructure volume
	 *
	 * ```elscript
	 * Graph.area([
	 *     { 'date': '2026-09-01', 'traffic_gb': 320, 'tier': 'Media CDN' },
	 *     { 'date': '2026-09-01', 'traffic_gb': 180, 'tier': 'Web Application' },
	 *     { 'date': '2026-09-01', 'traffic_gb': 90,  'tier': 'REST API Gateway' },
	 *     { 'date': '2026-09-02', 'traffic_gb': 410, 'tier': 'Media CDN' },
	 *     { 'date': '2026-09-02', 'traffic_gb': 240, 'tier': 'Web Application' },
	 *     { 'date': '2026-09-02', 'traffic_gb': 130, 'tier': 'REST API Gateway' },
	 *     { 'date': '2026-09-03', 'traffic_gb': 490, 'tier': 'Media CDN' },
	 *     { 'date': '2026-09-03', 'traffic_gb': 290, 'tier': 'Web Application' },
	 *     { 'date': '2026-09-03', 'traffic_gb': 160, 'tier': 'REST API Gateway' },
	 *     { 'date': '2026-09-04', 'traffic_gb': 380, 'tier': 'Media CDN' },
	 *     { 'date': '2026-09-04', 'traffic_gb': 220, 'tier': 'Web Application' },
	 *     { 'date': '2026-09-04', 'traffic_gb': 110, 'tier': 'REST API Gateway' },
	 *     { 'date': '2026-09-05', 'traffic_gb': 530, 'tier': 'Media CDN' },
	 *     { 'date': '2026-09-05', 'traffic_gb': 310, 'tier': 'Web Application' },
	 *     { 'date': '2026-09-05', 'traffic_gb': 190, 'tier': 'REST API Gateway' },
	 *     { 'date': '2026-09-06', 'traffic_gb': 280, 'tier': 'Media CDN' },
	 *     { 'date': '2026-09-06', 'traffic_gb': 140, 'tier': 'Web Application' },
	 *     { 'date': '2026-09-06', 'traffic_gb': 70,  'tier': 'REST API Gateway' },
	 *     { 'date': '2026-09-07', 'traffic_gb': 260, 'tier': 'Media CDN' },
	 *     { 'date': '2026-09-07', 'traffic_gb': 120, 'tier': 'Web Application' },
	 *     { 'date': '2026-09-07', 'traffic_gb': 60,  'tier': 'REST API Gateway' }
	 * ], {
	 *     'title': 'Infrastructure Network Consumption (GB)',
	 *     'x': 'date',
	 *     'y': 'traffic_gb',
	 *     'color': 'tier'
	 * })
	 * ```
	 *
	 * @see https://vega.github.io/vega-lite/docs/
	 * @see https://expressionlab.io/docs/api-reference/graph#grapharea
	 *
	 * @param mixed $data    List of associative records.
	 * @param array $options Optional chart settings:
	 *                       - `'title'` (string): Panel title. Default `'Area Chart'`.
	 *                       - `'x'` (string): Field mapped to horizontal axis.
	 *                       - `'y'` (string): Field mapped to vertical axis.
	 *                       - `'color'` (string): Field for multi-series color grouping, or fixed color code.
	 *                       - `'stream'` (bool): Whether to center stack on zero for a streamgraph. Default `false`.
	 *                       - `'normalize'` (bool): Whether to scale stack to 100% percentages. Default `false`.
	 *                       - `'curve'` (string): Interpolation mode (`'monotone'`, `'basis'`, `'linear'`). Default `'monotone'`.
	 *                       - `'opacity'` (float): Fill opacity between 0.0 and 1.0. Default `0.6`.
	 *                       - `'label_angle'` (int): Rotation angle in degrees for axis labels. Default `-45`.
	 *                       - `'height'` (int): Visualization height in pixels. Default `360`.
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
				$mark['color']     = $color_opt;
				$encoding['color'] = array( 'value' => $color_opt );
			}
		} elseif ( ! empty( $options['color_value'] ) ) {
			$color_val         = (string) $options['color_value'];
			$mark['color']     = $color_val;
			$encoding['color'] = array( 'value' => $color_val );
		} else {
			$mark['color']     = self::COLOR_BLUE;
			$encoding['color'] = array( 'value' => self::COLOR_BLUE );
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
	 * Renders a horizontal interval Gantt timeline for execution intervals and scheduled events.
	 *
	 * Displays horizontal task bars spanning from a start coordinate to an end coordinate
	 * along a temporal or quantitative axis. Allocates individual operations to discrete
	 * rows along the vertical lane axis.
	 *
	 * Accepts timestamp strings or numeric millisecond offsets for boundary markers.
	 *
	 * Example:
	 *
	 * ```elscript
	 * Graph.timeline([
	 *     { 'task': 'Database Query',   'start': 0,  'end': 45 },
	 *     { 'task': 'Cache Resolution', 'start': 30, 'end': 85 },
	 *     { 'task': 'Template Render',  'start': 80, 'end': 190 }
	 * ], {
	 *     'title': 'Execution Profile Timeline'
	 * })
	 * ```
	 *
	 * @see https://vega.github.io/vega-lite/docs/
	 * @see https://expressionlab.io/docs/api-reference/graph#graphtimeline
	 *
	 * @param mixed $data    List of associative records containing task, start, and end definitions.
	 * @param array $options Optional chart settings:
	 *                       - `'title'` (string): Panel title. Default `'Timeline'`.
	 *                       - `'task'` (string): Field containing task or lane names (vertical axis).
	 *                       - `'start'` (string): Field containing start coordinates (horizontal axis).
	 *                       - `'end'` (string): Field containing end coordinates (horizontal extent).
	 *                       - `'color'` (string): Field for category coloring, or fixed color code.
	 *                       - `'label_angle'` (int): Rotation angle in degrees for axis labels. Default `-45`.
	 *                       - `'height'` (int): Visualization height in pixels. Default auto-calculated from record count.
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
