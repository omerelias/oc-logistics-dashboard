<?php
/**
 * Data Handler - retrieves and processes data.
 *
 * @since      1.0.0
 * @package    OC_Logistics_Dashboard
 * @subpackage OC_Logistics_Dashboard/includes
 */

class OCLD_Data_Handler {

    /**
     * Get orders by date.
     *
     * @since    1.0.0
     * @param    string    $date        Date in Y-m-d format
     * @param    int       $group_id    Optional group filter
     * @param    string    $status      Optional status filter
     * @return   array
     */
    public function get_orders_by_date( $date, $group_id = null, $status = null ) {
        global $wpdb;

        // Convert date format
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
        $date_formatted = $date_obj ? $date_obj->format( 'd/m/Y' ) : $date;

        error_log( 'OCLD: Searching for delivery date: ' . $date_formatted );

        // Get order IDs with matching delivery date
        $order_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT post_id 
         FROM {$wpdb->postmeta} 
         WHERE meta_key = 'ocws_shipping_info_date' 
         AND meta_value = %s",
            $date_formatted
        ) );

        error_log( 'OCLD: Found order IDs: ' . implode( ', ', $order_ids ) );

        if ( empty( $order_ids ) ) {
            return array();
        }

        // Build status array
        $statuses = $status ? array( 'wc-' . $status ) : array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' );

        // Get ALL orders with correct statuses
        $args = array(
            'limit'   => -1,
            'type'    => 'shop_order',
            'status'  => $statuses,
            'orderby' => 'ID',
            'order'   => 'DESC',
        );

        $all_orders = wc_get_orders( $args );

        // Filter manually by order IDs
        $orders = array_filter( $all_orders, function( $order ) use ( $order_ids ) {
            return in_array( $order->get_id(), $order_ids );
        });

        error_log( 'OCLD: After manual ID filter: ' . count( $orders ) . ' orders' );

        // Filter by group if specified
        if ( $group_id ) {
            $orders = array_filter( $orders, function( $order ) use ( $group_id ) {
                $order_group = $order->get_meta( '_ocws_shipping_group' );
                return ! empty( $order_group ) && $order_group == $group_id;
            });
            error_log( 'OCLD: After group filter: ' . count( $orders ) . ' orders' );
        }

        // Format orders
        $formatted_orders = array();
        foreach ( $orders as $order ) {
            $formatted_orders[] = $this->format_order( $order );
        }

        error_log( 'OCLD: Returning ' . count( $formatted_orders ) . ' formatted orders' );

        return $formatted_orders;
    }
    /**
     * Format order data for API response.
     *
     * @since    1.0.0
     * @param    WC_Order    $order
     * @return   array
     */
    private function format_order( $order ) {
        // Get shipping info
        $shipping_info = $order->get_meta( '_ocws_shipping_info' );

        // Get coordinates
        $coords = $order->get_meta( '_billing_address_coords' );
        $lat = null;
        $lng = null;

        if ( $coords ) {
            // Try JSON format first
            if ( is_string( $coords ) && strpos( $coords, '{' ) === 0 ) {
                $coords_array = json_decode( $coords, true );
                if ( isset( $coords_array['lat'] ) ) {
                    $lat = $coords_array['lat'];
                    $lng = $coords_array['lng'];
                }
            }
            // Try (lat, lng) format
            elseif ( is_string( $coords ) && preg_match( '/\(([-\d.]+),\s*([-\d.]+)\)/', $coords, $matches ) ) {
                $lat = floatval( $matches[1] );
                $lng = floatval( $matches[2] );
            }
            // Already an array
            elseif ( is_array( $coords ) && isset( $coords['lat'] ) ) {
                $lat = $coords['lat'];
                $lng = $coords['lng'];
            }
        }

        // Get group ID
        $group_id = $order->get_meta( '_ocws_shipping_group' );

        // Calculate total weight
        $total_weight = $this->calculate_order_weight( $order );

        // Build formatted order
        $formatted = array(
            'id'             => $order->get_id(),
            'customer_name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'address'        => $order->get_billing_address_1(),
            'city'           => $order->get_billing_city(),
            'lat'            => $lat,
            'lng'            => $lng,
            'status'         => $order->get_status(),
            'total'          => $order->get_total(),
            'weight'         => $total_weight,
            'group_id'       => $group_id,
            'slot_formatted' => isset( $shipping_info['formatted'] ) ? $shipping_info['formatted'] : '',
            'date'           => isset( $shipping_info['date'] ) ? $shipping_info['date'] : '',
            'slot_start'     => isset( $shipping_info['slot_start'] ) ? $shipping_info['slot_start'] : '',
            'slot_end'       => isset( $shipping_info['slot_end'] ) ? $shipping_info['slot_end'] : '',
            'edit_url'       => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
            'items'          => $this->format_order_items( $order ),
        );

        return $formatted;
    }
    /**
     * Calculate total weight of order.
     *
     * @since    1.0.0
     * @param    WC_Order    $order
     * @return   float
     */
    private function calculate_order_weight( $order ) {
        $total_weight = 0;

        foreach ( $order->get_items() as $item ) {
            // Check if item has weight data from Sale Units plugin
            $quantity_in_weight = $item->get_meta( '_ocwsu_quantity_in_weight_units' );
            $weight_unit = $item->get_meta( '_ocwsu_product_weight_units' );

            if ( $quantity_in_weight ) {
                // Convert to kg if needed
                if ( $weight_unit === 'grams' ) {
                    $total_weight += floatval( $quantity_in_weight ) / 1000;
                } else {
                    $total_weight += floatval( $quantity_in_weight );
                }
            } else {
                // Fallback to product weight
                $product = $item->get_product();
                if ( $product && $product->get_weight() ) {
                    $total_weight += floatval( $product->get_weight() ) * $item->get_quantity();
                }
            }
        }

        return round( $total_weight, 2 );
    }

    /**
     * Format order items.
     *
     * @since    1.0.0
     * @param    WC_Order    $order
     * @return   array
     */
    private function format_order_items( $order ) {
        $items = array();

        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $quantity = $item->get_quantity();

            // Check if product is sold by weight
            $is_weighted = false;
            $weight_display = '';

            if ( $product ) {
                // Check if using your weight plugin
                $unit = $product->get_meta( '_ocwsu_unit' );
                $is_weighted = ( $unit === 'kg' || $unit === 'gram' );

                if ( $is_weighted ) {
                    // For weighted products, quantity IS the weight
                    if ( $unit === 'gram' ) {
                        $weight_kg = $quantity / 1000;
                        $weight_display = number_format( $weight_kg, 3 ) . ' ק"ג';
                    } else {
                        $weight_display = number_format( $quantity, 3 ) . ' ק"ג';
                    }
                } else {
                    // For regular products, show quantity as units
                    $weight_display = number_format( $quantity, 0 ) . ' יח\'';
                }
            }

            $items[] = array(
                'name'        => $item->get_name(),
                'quantity'    => $quantity,
                'weight'      => $is_weighted ? $quantity : 0,
                'display'     => $weight_display,
                'is_weighted' => $is_weighted,
            );
        }

        return $items;
    }
    /**
     * Get shipping groups.
     *
     * @since    1.0.0
     * @return   array
     */
    public function get_groups() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'oc_woo_shipping_groups';

        // Check if table exists
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
            return array();
        }

        $results = $wpdb->get_results(
            "SELECT group_id as id, group_name as name, is_enabled 
             FROM {$table_name} 
             WHERE is_enabled = 1 
             ORDER BY group_order ASC"
        );

        return $results ? $results : array();
    }

    /**
     * Get shipping polygons.
     *
     * @since    1.0.0
     * @return   array
     */
    public function get_polygons() {
        global $wpdb;

        $groups_table = $wpdb->prefix . 'oc_woo_shipping_groups';
        $locations_table = $wpdb->prefix . 'oc_woo_shipping_locations';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$locations_table}'" ) != $locations_table ) {
            return array();
        }

        $results = $wpdb->get_results(
            "SELECT 
            l.location_id,
            l.location_code,
            l.location_name,
            l.gm_shapes as coordinates,
            l.group_id,
            g.group_name,
            g.group_order
         FROM {$locations_table} l
         INNER JOIN {$groups_table} g ON l.group_id = g.group_id
         WHERE l.is_enabled = 1 
         AND l.location_type = 'polygon'
         AND l.gm_shapes IS NOT NULL
         AND g.is_enabled = 1
         ORDER BY g.group_order ASC, l.location_order ASC"
        );

        $colors = array( '#0073aa', '#46b450', '#ffb900', '#dc3232', '#826eb4', '#00a0d2' );
        $formatted = array();

        foreach ( $results as $index => $result ) {
            $coordinates = null;

            // Try unserialize first
            if ( is_serialized( $result->coordinates ) ) {
                $unserialized = maybe_unserialize( $result->coordinates );
                if ( is_array( $unserialized ) || is_object( $unserialized ) ) {
                    $coordinates = wp_json_encode( $unserialized );
                }
            }
            // Already JSON
            elseif ( $this->is_json( $result->coordinates ) ) {
                $coordinates = $result->coordinates;
            }
            // Last resort: try to use as-is
            else {
                $coordinates = $result->coordinates;
            }

            // Skip invalid
            if ( ! $coordinates ) {
                error_log( 'OCLD: Skipping polygon ' . $result->location_code . ' - invalid coordinates' );
                continue;
            }

            $formatted[] = array(
                'id'          => $result->location_id,
                'code'        => $result->location_code,
                'name'        => $result->location_name,
                'group_id'    => $result->group_id,
                'group_name'  => $result->group_name,
                'coordinates' => $coordinates,
                'color'       => $colors[ $result->group_order % count( $colors ) ],
            );
        }

        return $formatted;
    }
    /**
     * Check if string is valid JSON.
     *
     * @since    1.0.0
     * @param    string    $string
     * @return   bool
     */
    private function is_json( $string ) {
        if ( ! is_string( $string ) ) {
            return false;
        }

        json_decode( $string );
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Calculate statistics.
     *
     * @since    1.0.0
     * @param    array    $orders
     * @return   array
     */
    public function calculate_stats( $orders ) {
        $total_orders = count( $orders );
        $total_weight = 0;
        $slots = array();
        $groups = array();

        foreach ( $orders as $order ) {
            $total_weight += $order['weight'];

            // Count unique slots
            $slot_key = $order['date'] . '_' . $order['slot_start'];
            $slots[ $slot_key ] = true;

            // Count unique groups
            if ( $order['group_id'] ) {
                $groups[ $order['group_id'] ] = true;
            }
        }

        return array(
            'total_orders'  => $total_orders,
            'total_weight'  => round( $total_weight, 2 ),
            'active_slots'  => count( $slots ),
            'active_groups' => count( $groups ),
        );
    }
}