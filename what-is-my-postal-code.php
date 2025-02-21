<?php
/*
Plugin Name: What is my Postal Code
Description: Retrieves and displays the user's postal code based on GPS location or manual search using the Nominatim OpenStreetMap API. Includes full location details and an interactive map with initial continent markers.
Version: 1.1
Author: Ayodeji
License: GPL2
Text Domain: http://postalcode.pro
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WhatIsMyPostalCode {
    public function __construct() {
        
        // Register shortcode
        add_shortcode('what_is_my_postal_code', array($this, 'render_shortcode'));

        // Register AJAX handlers
        add_action('wp_ajax_wimpc_get_continents', array($this, 'get_continents'));
        add_action('wp_ajax_nopriv_wimpc_get_continents', array($this, 'get_continents'));

        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
    }

    /**
     * Enqueue Front-End CSS & JS
     */
    public function enqueue_assets() {
        static $assets_loaded = false;

        if ($assets_loaded) {
            return;
        }

        $assets_loaded = true;

        // Plugin directory URL
        $plugin_url = plugin_dir_url(__FILE__);

        // Leaflet CSS
        wp_enqueue_style(
            'leaflet-css',
            $plugin_url . 'assets/css/leaflet.css',
            array(),
            '1.1'
        );

        // Plugin CSS
        wp_enqueue_style(
            'wimpc-styles',
            $plugin_url . 'assets/css/styles.css',
            array('leaflet-css'),
            '1.1'
        );

        // Leaflet JS
        wp_enqueue_script(
            'leaflet-js',
            $plugin_url . 'assets/js/leaflet.js',
            array(),
            '1.1',
            true
        );

        // Plugin JS
        wp_enqueue_script(
            'wimpc-scripts',
            $plugin_url . 'assets/js/scripts.js',
            array('jquery', 'leaflet-js'),
            '1.1',
            true
        );

        // Localize script to pass AJAX URL and nonce
        wp_localize_script('wimpc-scripts', 'wimpc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wimpc_nonce')
        ));
    }

    /**
     * Render the shortcode content
     */
    public function render_shortcode() {
        // Enqueue Scripts and Styles only when shortcode is used
        $this->enqueue_assets();

        ob_start();
        ?>
        <div class="wimpc-container">
              <ul class="circles">
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
                <li></li>
              </ul>
            <h2>What is my Postal Code?</h2>
            <div class="wimpc-options">
                <button id="wimpc-gps-btn" class="wimpc-btn">Use My Current Location</button>
                <div class="wimpc-separator">OR</div>
                <form id="wimpc-form">
                    <select id="wimpc-country" required>
                        <option value="">Select Country</option>
                        <!-- Country options populated via PHP -->
                        <?php echo $this->get_country_options(); ?>
                    </select>
                    <input type="text" id="wimpc-location" placeholder="Enter your location" required>
                    <button type="submit" class="wimpc-btn">Find Postal Code</button>
                </form>
            </div>
            <div id="wimpc-results" class="wimpc-results hidden">
                <div class="wimpc-details">
                    <h3>Location Details:</h3>
                    <p><strong>Address:</strong> <span id="wimpc-address"></span></p>
                    <p><strong>Postal Code:</strong> <span id="wimpc-postal-code"></span></p>
                    <p><strong>Country:</strong> <span id="wimpc-country-name"></span></p>
                    <p><strong>Latitude:</strong> <span id="wimpc-lat"></span></p>
                    <p><strong>Longitude:</strong> <span id="wimpc-lon"></span></p>
                </div>
                <!-- Notification Container -->
                <div id="wimpc-notification" class="wimpc-notification hidden">
                    <button class="close-btn" aria-label="Close Notification">&times;</button>
                    You can move the map marker to target a more precise location.
                </div>
                <div id="wimpc-map" class="wimpc-map"></div>
            </div>
            <!-- Initial Continents Map -->
            <div id="wimpc-initial-map" class="wimpc-map hidden"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate country options for the select dropdown
     * You can expand the list or fetch dynamically if needed
     */
    private function get_country_options() {
        $countries = array(
            "AF" => "Afghanistan",
            "AX" => "Åland Islands",
            "AL" => "Albania",
            "DZ" => "Algeria",
            "AS" => "American Samoa",
            "AD" => "Andorra",
            "AO" => "Angola",
            "AI" => "Anguilla",
            "AQ" => "Antarctica",
            "AG" => "Antigua and Barbuda",
            "AR" => "Argentina",
            "AM" => "Armenia",
            "AW" => "Aruba",
            "AU" => "Australia",
            "AT" => "Austria",
            "AZ" => "Azerbaijan",
            "BS" => "Bahamas",
            "BH" => "Bahrain",
            "BD" => "Bangladesh",
            "BB" => "Barbados",
            "BY" => "Belarus",
            "BE" => "Belgium",
            "BZ" => "Belize",
            "BJ" => "Benin",
            "BM" => "Bermuda",
            "BT" => "Bhutan",
            "BO" => "Bolivia",
            "BQ" => "Bonaire, Sint Eustatius and Saba",
            "BA" => "Bosnia and Herzegovina",
            "BW" => "Botswana",
            "BV" => "Bouvet Island",
            "BR" => "Brazil",
            "IO" => "British Indian Ocean Territory",
            "BN" => "Brunei Darussalam",
            "BG" => "Bulgaria",
            "BF" => "Burkina Faso",
            "BI" => "Burundi",
            "CV" => "Cabo Verde",
            "KH" => "Cambodia",
            "CM" => "Cameroon",
            "CA" => "Canada",
            "KY" => "Cayman Islands",
            "CF" => "Central African Republic",
            "TD" => "Chad",
            "CL" => "Chile",
            "CN" => "China",
            "CX" => "Christmas Island",
            "CC" => "Cocos (Keeling) Islands",
            "CO" => "Colombia",
            "KM" => "Comoros",
            "CG" => "Congo",
            "CD" => "Congo, Democratic Republic of the",
            "CK" => "Cook Islands",
            "CR" => "Costa Rica",
            "CI" => "Côte d'Ivoire",
            "HR" => "Croatia",
            "CU" => "Cuba",
            "CW" => "Curaçao",
            "CY" => "Cyprus",
            "CZ" => "Czechia",
            "DK" => "Denmark",
            "DJ" => "Djibouti",
            "DM" => "Dominica",
            "DO" => "Dominican Republic",
            "EC" => "Ecuador",
            "EG" => "Egypt",
            "SV" => "El Salvador",
            "GQ" => "Equatorial Guinea",
            "ER" => "Eritrea",
            "EE" => "Estonia",
            "SZ" => "Eswatini",
            "ET" => "Ethiopia",
            "FK" => "Falkland Islands (Malvinas)",
            "FO" => "Faroe Islands",
            "FJ" => "Fiji",
            "FI" => "Finland",
            "FR" => "France",
            "GF" => "French Guiana",
            "PF" => "French Polynesia",
            "TF" => "French Southern Territories",
            "GA" => "Gabon",
            "GM" => "Gambia",
            "GE" => "Georgia",
            "DE" => "Germany",
            "GH" => "Ghana",
            "GI" => "Gibraltar",
            "GR" => "Greece",
            "GL" => "Greenland",
            "GD" => "Grenada",
            "GP" => "Guadeloupe",
            "GU" => "Guam",
            "GT" => "Guatemala",
            "GG" => "Guernsey",
            "GN" => "Guinea",
            "GW" => "Guinea-Bissau",
            "GY" => "Guyana",
            "HT" => "Haiti",
            "HM" => "Heard Island and McDonald Islands",
            "VA" => "Holy See",
            "HN" => "Honduras",
            "HK" => "Hong Kong",
            "HU" => "Hungary",
            "IS" => "Iceland",
            "IN" => "India",
            "ID" => "Indonesia",
            "IR" => "Iran",
            "IQ" => "Iraq",
            "IE" => "Ireland",
            "IM" => "Isle of Man",
            "IL" => "Israel",
            "IT" => "Italy",
            "JM" => "Jamaica",
            "JP" => "Japan",
            "JE" => "Jersey",
            "JO" => "Jordan",
            "KZ" => "Kazakhstan",
            "KE" => "Kenya",
            "KI" => "Kiribati",
            "KP" => "Korea, Democratic People's Republic of",
            "KR" => "Korea, Republic of",
            "KW" => "Kuwait",
            "KG" => "Kyrgyzstan",
            "LA" => "Lao People's Democratic Republic",
            "LV" => "Latvia",
            "LB" => "Lebanon",
            "LS" => "Lesotho",
            "LR" => "Liberia",
            "LY" => "Libya",
            "LI" => "Liechtenstein",
            "LT" => "Lithuania",
            "LU" => "Luxembourg",
            "MO" => "Macao",
            "MG" => "Madagascar",
            "MW" => "Malawi",
            "MY" => "Malaysia",
            "MV" => "Maldives",
            "ML" => "Mali",
            "MT" => "Malta",
            "MH" => "Marshall Islands",
            "MQ" => "Martinique",
            "MR" => "Mauritania",
            "MU" => "Mauritius",
            "YT" => "Mayotte",
            "MX" => "Mexico",
            "FM" => "Micronesia",
            "MD" => "Moldova",
            "MC" => "Monaco",
            "MN" => "Mongolia",
            "ME" => "Montenegro",
            "MS" => "Montserrat",
            "MA" => "Morocco",
            "MZ" => "Mozambique",
            "MM" => "Myanmar",
            "NA" => "Namibia",
            "NR" => "Nauru",
            "NP" => "Nepal",
            "NL" => "Netherlands",
            "NC" => "New Caledonia",
            "NZ" => "New Zealand",
            "NI" => "Nicaragua",
            "NE" => "Niger",
            "NG" => "Nigeria",
            "NU" => "Niue",
            "NF" => "Norfolk Island",
            "MK" => "North Macedonia",
            "MP" => "Northern Mariana Islands",
            "NO" => "Norway",
            "OM" => "Oman",
            "PK" => "Pakistan",
            "PW" => "Palau",
            "PS" => "Palestine",
            "PA" => "Panama",
            "PG" => "Papua New Guinea",
            "PY" => "Paraguay",
            "PE" => "Peru",
            "PH" => "Philippines",
            "PN" => "Pitcairn",
            "PL" => "Poland",
            "PT" => "Portugal",
            "PR" => "Puerto Rico",
            "QA" => "Qatar",
            "RE" => "Réunion",
            "RO" => "Romania",
            "RU" => "Russian Federation",
            "RW" => "Rwanda",
            "BL" => "Saint Barthélemy",
            "SH" => "Saint Helena, Ascension and Tristan da Cunha",
            "KN" => "Saint Kitts and Nevis",
            "LC" => "Saint Lucia",
            "MF" => "Saint Martin (French part)",
            "PM" => "Saint Pierre and Miquelon",
            "VC" => "Saint Vincent and the Grenadines",
            "WS" => "Samoa",
            "SM" => "San Marino",
            "ST" => "Sao Tome and Principe",
            "SA" => "Saudi Arabia",
            "SN" => "Senegal",
            "RS" => "Serbia",
            "SC" => "Seychelles",
            "SL" => "Sierra Leone",
            "SG" => "Singapore",
            "SX" => "Sint Maarten (Dutch part)",
            "SK" => "Slovakia",
            "SI" => "Slovenia",
            "SB" => "Solomon Islands",
            "SO" => "Somalia",
            "ZA" => "South Africa",
            "GS" => "South Georgia and the South Sandwich Islands",
            "SS" => "South Sudan",
            "ES" => "Spain",
            "LK" => "Sri Lanka",
            "SD" => "Sudan",
            "SR" => "Suriname",
            "SJ" => "Svalbard and Jan Mayen",
            "SE" => "Sweden",
            "CH" => "Switzerland",
            "SY" => "Syrian Arab Republic",
            "TW" => "Taiwan",
            "TJ" => "Tajikistan",
            "TZ" => "Tanzania",
            "TH" => "Thailand",
            "TL" => "Timor-Leste",
            "TG" => "Togo",
            "TK" => "Tokelau",
            "TO" => "Tonga",
            "TT" => "Trinidad and Tobago",
            "TN" => "Tunisia",
            "TR" => "Turkey",
            "TM" => "Turkmenistan",
            "TC" => "Turks and Caicos Islands",
            "TV" => "Tuvalu",
            "UG" => "Uganda",
            "UA" => "Ukraine",
            "AE" => "United Arab Emirates",
            "GB" => "United Kingdom",
            "US" => "United States",
            "UM" => "United States Minor Outlying Islands",
            "UY" => "Uruguay",
            "UZ" => "Uzbekistan",
            "VU" => "Vanuatu",
            "VE" => "Venezuela",
            "VN" => "Viet Nam",
            "VG" => "Virgin Islands, British",
            "VI" => "Virgin Islands, U.S.",
            "WF" => "Wallis and Futuna",
            "EH" => "Western Sahara",
            "YE" => "Yemen",
            "ZM" => "Zambia",
            "ZW" => "Zimbabwe"
        );

        $options = '';
        foreach ($countries as $code => $name) {
            $options .= '<option value="' . esc_attr(strtolower($code)) . '">' . esc_html($name) . '</option>';
        }
        return $options;
    }
}

new WhatIsMyPostalCode();
