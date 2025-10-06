<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    OC_Logistics_Dashboard
 * @subpackage OC_Logistics_Dashboard/includes
 */

class OC_Logistics_Dashboard {

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->plugin_name = 'oc-logistics-dashboard';
        $this->version = OC_LOGISTICS_DASHBOARD_VERSION;

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_api_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {
        /**
         * The class responsible for defining all actions in the admin area.
         */
        require_once OC_LOGISTICS_DASHBOARD_PATH . 'includes/class-ocld-admin.php';

        /**
         * The class responsible for defining the REST API endpoints.
         */
        require_once OC_LOGISTICS_DASHBOARD_PATH . 'includes/class-ocld-rest-api.php';

        /**
         * The class responsible for data retrieval and processing.
         */
        require_once OC_LOGISTICS_DASHBOARD_PATH . 'includes/class-ocld-data-handler.php';
    }

    /**
     * Register all hooks related to the admin area functionality.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $admin = new OCLD_Admin( $this->get_plugin_name(), $this->get_version() );

        // Add menu page
        add_action( 'admin_menu', array( $admin, 'add_menu_page' ) );

        // Enqueue styles and scripts
        add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_scripts' ) );
    }

    /**
     * Register all hooks related to the REST API.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_api_hooks() {
        $api = new OCLD_REST_API();

        // Register REST API routes
        add_action( 'rest_api_init', array( $api, 'register_routes' ) );
    }

    /**
     * Run the loader to execute all hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        // Plugin is loaded and ready
    }

    /**
     * The name of the plugin used to uniquely identify it.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}