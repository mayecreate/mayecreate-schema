<?php
/**
 * Plugin Name:       MayeCreate Schema
 * Description:       Connected schema.org markup (JSON-LD) for MayeCreate client sites. Fill in Settings → Schema and the plugin handles the rest.
 * Version:           1.7.0
 * Author:            MayeCreate Design
 * Author URI:        https://mayecreate.com
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Text Domain:       mayecreate-schema
 *
 * Requires Advanced Custom Fields PRO (for the settings page and repeaters).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MCS_VERSION', '1.7.0' );
define( 'MCS_DIR', plugin_dir_path( __FILE__ ) );

require_once MCS_DIR . 'includes/helpers.php';
require_once MCS_DIR . 'includes/fields.php';
require_once MCS_DIR . 'includes/graph.php';
require_once MCS_DIR . 'includes/output.php';
require_once MCS_DIR . 'includes/compat.php';
