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
        <div class="ocld-sidebar">
            <div class="ocld-sidebar-header">
                <h3 class="ocld-sidebar-title">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    אזורי משלוח
                </h3>
                <div class="ocld-sidebar-subtitle">בחר אזור לצפייה בהזמנות</div>
            </div>
            <div class="ocld-sidebar-content">
                <ul class="ocld-groups-list" id="ocld-groups-list">
                    <!-- Groups will be populated here -->
                </ul>
            </div>
        </div>

        <!-- Stats Bar - Products Summary -->
        <div class="ocld-stats-bar">
            <div class="ocld-stats-header">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <h3 class="ocld-stats-title">רשימת מוצרים להכנה</h3>
            </div>
            <div class="ocld-products-summary" id="ocld-products-summary">
                <!-- Products summary will be populated here -->
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