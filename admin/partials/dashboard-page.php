<?php
/**
 * The admin dashboard page template.
 *
 * @since      1.0.0
 * @package    OC_Logistics_Dashboard
 * @subpackage OC_Logistics_Dashboard/admin/partials
 */

// Prevent direct access
if ( ! defined( 'WPINC' ) ) {
    die;
}
?>

<div class="wrap ocld-dashboard">

    <!-- Header -->
    <div class="ocld-header">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <p class="description">
            <?php _e( 'Interactive map view of all orders with shipping slots', 'oc-logistics-dashboard' ); ?>
        </p>
    </div>

    <!-- Toolbar / Filters -->
    <div class="ocld-toolbar">
        <div class="ocld-toolbar-left">

            <!-- Date Picker -->
            <div class="ocld-filter">
                <label for="ocld-date-filter">
                    <?php _e( 'Date:', 'oc-logistics-dashboard' ); ?>
                </label>
                <input
                    type="date"
                    id="ocld-date-filter"
                    class="ocld-date-input"
                    value="<?php echo date('Y-m-d'); ?>"
                />
            </div>

            <!-- Group Filter -->
            <div class="ocld-filter">
                <label for="ocld-group-filter">
                    <?php _e( 'Group:', 'oc-logistics-dashboard' ); ?>
                </label>
                <select id="ocld-group-filter" class="ocld-select">
                    <option value=""><?php _e( 'All Groups', 'oc-logistics-dashboard' ); ?></option>
                    <!-- Will be populated by JavaScript -->
                </select>
            </div>

            <!-- Status Filter -->
            <div class="ocld-filter">
                <label for="ocld-status-filter">
                    <?php _e( 'Status:', 'oc-logistics-dashboard' ); ?>
                </label>
                <select id="ocld-status-filter" class="ocld-select">
                    <option value=""><?php _e( 'All Statuses', 'oc-logistics-dashboard' ); ?></option>
                    <option value="pending"><?php _e( 'Pending', 'oc-logistics-dashboard' ); ?></option>
                    <option value="processing"><?php _e( 'Processing', 'oc-logistics-dashboard' ); ?></option>
                    <option value="completed"><?php _e( 'Completed', 'oc-logistics-dashboard' ); ?></option>
                </select>
            </div>

            <!-- Refresh Button -->
            <button type="button" id="ocld-refresh-btn" class="button">
                <span class="dashicons dashicons-update"></span>
                <?php _e( 'Refresh', 'oc-logistics-dashboard' ); ?>
            </button>

        </div>

        <div class="ocld-toolbar-right">

            <!-- View Toggle -->
            <div class="ocld-view-toggle">
                <button type="button" class="button ocld-view-btn active" data-view="map">
                    <span class="dashicons dashicons-location"></span>
                    <?php _e( 'Map View', 'oc-logistics-dashboard' ); ?>
                </button>
                <button type="button" class="button ocld-view-btn" data-view="list">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php _e( 'List View', 'oc-logistics-dashboard' ); ?>
                </button>
            </div>

        </div>
    </div>

    <!-- Main Content Area -->
    <div class="ocld-content">

        <!-- Map Container -->
        <div class="ocld-map-container" id="ocld-map-view">
            <div id="ocld-map" style="width: 100%; height: 100%;"></div>

            <!-- Loading Overlay -->
            <div class="ocld-loading-overlay" id="ocld-loading">
                <div class="ocld-spinner"></div>
                <p><?php _e( 'Loading map...', 'oc-logistics-dashboard' ); ?></p>
            </div>
        </div>

        <!-- Sidebar Panel -->
        <div class="ocld-sidebar" id="ocld-sidebar">

            <!-- Sidebar Header -->
            <div class="ocld-sidebar-header">
                <h2 id="ocld-sidebar-title">
                    <?php _e( 'Orders', 'oc-logistics-dashboard' ); ?>
                </h2>
                <button type="button" class="ocld-sidebar-close" id="ocld-sidebar-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>

            <!-- Sidebar Content -->
            <div class="ocld-sidebar-content" id="ocld-sidebar-content">
                <!-- Will be populated by JavaScript -->
                <div class="ocld-sidebar-placeholder">
                    <span class="dashicons dashicons-location-alt"></span>
                    <p><?php _e( 'Click on a polygon or pin to view details', 'oc-logistics-dashboard' ); ?></p>
                </div>
            </div>

            <!-- Sidebar Footer -->
            <div class="ocld-sidebar-footer" id="ocld-sidebar-footer" style="display: none;">
                <button type="button" class="button button-primary" id="ocld-print-btn">
                    <span class="dashicons dashicons-media-document"></span>
                    <?php _e( 'Print Picking List', 'oc-logistics-dashboard' ); ?>
                </button>
            </div>

        </div>

    </div>

    <!-- Stats Bar (Bottom) -->
    <div class="ocld-stats-bar" id="ocld-stats-bar">
        <div class="ocld-stat">
            <span class="ocld-stat-label"><?php _e( 'Total Orders:', 'oc-logistics-dashboard' ); ?></span>
            <span class="ocld-stat-value" id="ocld-stat-orders">0</span>
        </div>
        <div class="ocld-stat">
            <span class="ocld-stat-label"><?php _e( 'Total Weight:', 'oc-logistics-dashboard' ); ?></span>
            <span class="ocld-stat-value" id="ocld-stat-weight">0 kg</span>
        </div>
        <div class="ocld-stat">
            <span class="ocld-stat-label"><?php _e( 'Active Slots:', 'oc-logistics-dashboard' ); ?></span>
            <span class="ocld-stat-value" id="ocld-stat-slots">0</span>
        </div>
        <div class="ocld-stat">
            <span class="ocld-stat-label"><?php _e( 'Groups:', 'oc-logistics-dashboard' ); ?></span>
            <span class="ocld-stat-value" id="ocld-stat-groups">0</span>
        </div>
    </div>

</div>