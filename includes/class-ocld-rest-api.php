<?php
/**
 * REST API endpoints for the dashboard.
 *
 * @since      1.0.0
 * @package    OC_Logistics_Dashboard
 * @subpackage OC_Logistics_Dashboard/includes
 */

class OCLD_REST_API {

    /**
     * The namespace for the REST API.
     *
     * @since    1.0.0
     * @var      string
     */
    private $namespace = 'oc-logistics/v1';

    /**
     * Register REST API routes.
     *
     * @since    1.0.0
     */
    public function register_routes() {

        // GET /oc-logistics/v1/orders
        register_rest_route( $this->namespace, '/orders', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_orders' ),
            'permission_callback' => array( $this, 'check_permission' ),
            'args'                => array(
                'date' => array(
                    'required'          => false,
                    'default'           => date('Y-m-d'),
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array( $this, 'validate_date' ),
                ),
                'group_id' => array(
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ),
                'status' => array(
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // GET /oc-logistics/v1/groups
        register_rest_route( $this->namespace, '/groups', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_groups' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ));

        // GET /oc-logistics/v1/polygons
        register_rest_route( $this->namespace, '/polygons', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_polygons' ),
            'permission_callback' => array( $this, 'check_permission' ),
        ));
    }

    /**
     * Check if user has permission to access the API.
     *
     * @since    1.0.0
     * @return   bool
     */
    public function check_permission() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Validate date parameter.
     *
     * @since    1.0.0
     * @param    string    $param
     * @return   bool
     */
    public function validate_date( $param ) {
        $date = \DateTime::createFromFormat( 'Y-m-d', $param );
        return $date && $date->format( 'Y-m-d' ) === $param;
    }

    /**
     * Get orders endpoint.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request
     * @return   WP_REST_Response
     */
    public function get_orders( $request ) {
        $date     = $request->get_param( 'date' );
        $group_id = $request->get_param( 'group_id' );
        $status   = $request->get_param( 'status' );

        // Get data handler
        $data_handler = new OCLD_Data_Handler();

        // Get orders
        $orders = $data_handler->get_orders_by_date( $date, $group_id, $status );

        // Get polygons
        $polygons = $data_handler->get_polygons();

        // Get groups
        $groups = $data_handler->get_groups();

        // Calculate stats
        $stats = $data_handler->calculate_stats( $orders );

        // Build response
        $response = array(
            'success'  => true,
            'orders'   => $orders,
            'polygons' => $polygons,
            'groups'   => $groups,
            'stats'    => $stats,
            'date'     => $date,
        );

        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Get groups endpoint.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request
     * @return   WP_REST_Response
     */
    public function get_groups( $request ) {
        $data_handler = new OCLD_Data_Handler();
        $groups = $data_handler->get_groups();

        return new WP_REST_Response( array(
            'success' => true,
            'groups'  => $groups,
        ), 200 );
    }

    /**
     * Get polygons endpoint.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request
     * @return   WP_REST_Response
     */
    public function get_polygons( $request ) {
        $data_handler = new OCLD_Data_Handler();
        $polygons = $data_handler->get_polygons();

        return new WP_REST_Response( array(
            'success'  => true,
            'polygons' => $polygons,
        ), 200 );
    }
}