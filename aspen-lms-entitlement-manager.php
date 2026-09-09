<?php
/**
 * Plugin Name: Aspen LearnDash Training Entitlement Manager
 * Description: Sells reusable, per-order-line LearnDash enrollment entitlements through WooCommerce.
 * Version: 1.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Aspen
 * Text Domain: aspen-learndash-entitlements
 * License: GPL-2.0-or-later
 * WC requires at least: 7.0
 * WC tested up to: 10.2
 */

defined( 'ABSPATH' ) || exit;

define( 'ASPEN_LDE_VERSION', '1.1.0' );
define( 'ASPEN_LDE_FILE', __FILE__ );
define( 'ASPEN_LDE_PATH', plugin_dir_path( __FILE__ ) );

require_once ASPEN_LDE_PATH . 'includes/class-database.php';
require_once ASPEN_LDE_PATH . 'includes/class-repository.php';
require_once ASPEN_LDE_PATH . 'includes/class-product-settings.php';
require_once ASPEN_LDE_PATH . 'includes/class-order-service.php';
require_once ASPEN_LDE_PATH . 'includes/class-learndash.php';
require_once ASPEN_LDE_PATH . 'includes/class-course-requirement-repository.php';
require_once ASPEN_LDE_PATH . 'includes/class-fluentcrm-adapter.php';
require_once ASPEN_LDE_PATH . 'includes/class-fluentcrm-access-gate.php';
require_once ASPEN_LDE_PATH . 'includes/class-course-access-settings.php';
require_once ASPEN_LDE_PATH . 'includes/class-redemption-service.php';
require_once ASPEN_LDE_PATH . 'includes/class-enrollment-controller.php';
require_once ASPEN_LDE_PATH . 'includes/class-account-controller.php';
require_once ASPEN_LDE_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'Aspen\\LearnDashEntitlements\\Database', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Aspen\\LearnDashEntitlements\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Aspen\\LearnDashEntitlements\\Plugin', 'instance' ) );
