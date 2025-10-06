<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @since      1.0.0
 * @package    OC_Logistics_Dashboard
 * @subpackage OC_Logistics_Dashboard/includes
 */

class OCLD_Admin {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Add menu page under WooCommerce menu.
     *
     * @since    1.0.0
     */
    public function add_menu_page() {
        add_submenu_page(
            'woocommerce',                              // Parent slug (WooCommerce menu)
            __( 'Logistics Dashboard', 'oc-logistics-dashboard' ), // Page title
            __( 'Logistics', 'oc-logistics-dashboard' ),           // Menu title
            'manage_woocommerce',                       // Capability
            'oc-logistics-dashboard',                   // Menu slug
            array( $this, 'display_dashboard_page' ),   // Callback function
            30                                          // Position (after Orders)
        );
    }

    /**
     * Render the dashboard page.
     *
     * @since    1.0.0
     */
    public function display_dashboard_page() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'oc-logistics-dashboard' ) );
        }

        // Include the dashboard template
        require_once OC_LOGISTICS_DASHBOARD_PATH . 'admin/partials/dashboard-page.php';
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles( $hook ) {
        // Only load on our plugin page
        if ( 'woocommerce_page_oc-logistics-dashboard' !== $hook ) {
            return;
        }

        // Main admin styles
        wp_enqueue_style(
            $this->plugin_name,
            OC_LOGISTICS_DASHBOARD_URL . 'admin/css/ocld-admin.css',
            array(),
            $this->version,
            'all'
        );

        // Select2 for filters (if needed later)
        wp_enqueue_style( 'select2' );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts( $hook ) {
        // Only load on our plugin page
        if ( 'woocommerce_page_oc-logistics-dashboard' !== $hook ) {
            return;
        }

        // jQuery (already included in WordPress)
        // wp_enqueue_script( 'jquery' ); // Not needed, already loaded

        // Google Maps API
        $google_maps_api_key = get_option( 'ocws_common_google_maps_api_key', '' );

        if ( empty( $google_maps_api_key ) ) {
            // Show admin notice if API key is missing
            add_action( 'admin_notices', array( $this, 'google_maps_api_key_missing_notice' ) );
        } else {
            wp_enqueue_script(
                'google-maps',
                'https://maps.googleapis.com/maps/api/js?key=' . $google_maps_api_key . '&libraries=places,geometry,drawing',
                array(),
                null,
                true
            );
        }

        // MarkerClusterer library
        wp_enqueue_script(
            'markerclusterer',
            'https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js',
            array(),
            null,
            true
        );

        // Main dashboard script
        wp_enqueue_script(
            $this->plugin_name,
            OC_LOGISTICS_DASHBOARD_URL . 'admin/js/ocld-map.js',
            array( 'jquery', 'google-maps' ),
            $this->version,
            true
        );

        // Localize script with data
        wp_localize_script(
            $this->plugin_name,
            'ocldData',
            array(
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'restUrl'       => rest_url( 'oc-logistics/v1/' ),
                'restNonce'     => wp_create_nonce( 'wp_rest' ),
                'mapCenter'     => json_decode( get_option( 'ocld_default_map_center', '{"lat":32.0853,"lng":34.7818}' ) ),
                'mapZoom'       => intval( get_option( 'ocld_default_map_zoom', 12 ) ),
                'strings'       => array(
                    'loading'           => __( 'Loading...', 'oc-logistics-dashboard' ),
                    'noOrders'          => __( 'No orders found', 'oc-logistics-dashboard' ),
                    'ordersCount'       => __( 'orders', 'oc-logistics-dashboard' ),
                    'totalWeight'       => __( 'Total weight', 'oc-logistics-dashboard' ),
                    'viewOrder'         => __( 'View Order', 'oc-logistics-dashboard' ),
                    'navigate'          => __( 'Navigate', 'oc-logistics-dashboard' ),
                    'pickingList'       => __( 'Picking List', 'oc-logistics-dashboard' ),
                    'printAll'          => __( 'Print All', 'oc-logistics-dashboard' ),
                    'error'             => __( 'Error loading data', 'oc-logistics-dashboard' ),
                )
            )
        );

        // Select2 for filters (if needed later)
        wp_enqueue_script( 'select2' );
    }

    /**
     * Admin notice if Google Maps API key is missing.
     *
     * @since    1.0.0
     */
    public function google_maps_api_key_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php _e( 'OC Logistics Dashboard: Google Maps API key is missing.', 'oc-logistics-dashboard' ); ?>
                <?php if ( class_exists( 'OC_Woo_Shipping' ) ) : ?>
                    <?php _e( 'Please set it in the OC Shipping settings.', 'oc-logistics-dashboard' ); ?>
                <?php else : ?>
                    <?php _e( 'Please install OC Shipping plugin or add the API key manually.', 'oc-logistics-dashboard' ); ?>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
}