<?php
/**
 * Plugin Name: OC Logistics Dashboard
 * Plugin URI: https://onlinestore.co.il/
 * Description: Interactive map-based operations dashboard for WooCommerce orders with shipping slots
 * Version: 1.0.0
 * Author: Original Concepts
 * Author URI: https://onlinestore.co.il/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: oc-logistics-dashboard
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Current plugin version.
 */
define( 'OC_LOGISTICS_DASHBOARD_VERSION', '1.0.0' );
define( 'OC_LOGISTICS_DASHBOARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'OC_LOGISTICS_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function ocld_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'ocld_woocommerce_missing_notice' );
        return false;
    }
    return true;
}

/**
 * Admin notice if WooCommerce is missing
 */
function ocld_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e( 'OC Logistics Dashboard requires WooCommerce to be installed and active.', 'oc-logistics-dashboard' ); ?></p>
    </div>
    <?php
}

/**
 * Check if OC Shipping plugin is active (optional but recommended)
 */
function ocld_check_oc_shipping() {
    // Check if the shipping plugin's main class exists
    if ( ! class_exists( 'OC_Woo_Shipping' ) ) {
        add_action( 'admin_notices', 'ocld_shipping_recommended_notice' );
        return false;
    }
    return true;
}

/**
 * Admin notice recommending OC Shipping plugin
 */
function ocld_shipping_recommended_notice() {
    ?>
    <div class="notice notice-warning">
        <p><?php _e( 'OC Logistics Dashboard works best with OC Woo Shipping plugin for full functionality.', 'oc-logistics-dashboard' ); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function ocld_init() {
    // Check dependencies
    if ( ! ocld_check_woocommerce() ) {
        return;
    }

    // Optional check for shipping plugin
    ocld_check_oc_shipping();

    // Load the main plugin class
    require_once OC_LOGISTICS_DASHBOARD_PATH . 'includes/class-oc-logistics-dashboard.php';

    // Initialize
    $plugin = new OC_Logistics_Dashboard();
    $plugin->run();
}
add_action( 'plugins_loaded', 'ocld_init' );

/**
 * Activation hook
 */
function ocld_activate() {
    // Check WooCommerce on activation
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( __( 'Please install and activate WooCommerce before activating OC Logistics Dashboard.', 'oc-logistics-dashboard' ) );
    }

    // Set default options
    add_option( 'ocld_default_map_center', json_encode( [ 'lat' => 32.0853, 'lng' => 34.7818 ] ) ); // Tel Aviv
    add_option( 'ocld_default_map_zoom', 12 );

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ocld_activate' );

/**
 * Deactivation hook
 */
function ocld_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ocld_deactivate' );