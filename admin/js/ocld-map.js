/**
 * OC Logistics Dashboard - Map JavaScript
 *
 * @package    OC_Logistics_Dashboard
 * @since      1.0.0
 */

(function($) {
    'use strict';

    /**
     * Main Dashboard Object
     */
    const OCLD = {

        // Properties
        map: null,
        markers: [],
        polygons: [],
        infoWindow: null,
        markerClusterer: null,
        currentData: null,

        /**
         * Initialize
         */
        init: function() {
            console.log('OCLD: Initializing...');

            // Wait for Google Maps to load
            if (typeof google === 'undefined') {
                console.error('OCLD: Google Maps not loaded');
                this.showError('Google Maps API failed to load');
                return;
            }

            // Initialize map
            this.initMap();

            // Bind events
            this.bindEvents();

            // Load initial data
            this.loadData();
        },

        /**
         * Initialize Google Map
         */
        initMap: function() {
            console.log('OCLD: Initializing map...');

            const mapElement = document.getElementById('ocld-map');
            if (!mapElement) {
                console.error('OCLD: Map element not found');
                return;
            }

            // Map options
            const mapOptions = {
                center: ocldData.mapCenter, // From localized data
                zoom:  12,
                mapTypeId: google.maps.MapTypeId.ROADMAP,
                styles: this.getMapStyles(),
                streetViewControl: false,
                mapTypeControl: true,
                fullscreenControl: true,
                zoomControl: true
            };

            // Create map
            this.map = new google.maps.Map(mapElement, mapOptions);

            // Create info window
            this.infoWindow = new google.maps.InfoWindow();

            console.log('OCLD: Map initialized');
        },

        /**
         * Custom map styles (optional)
         */
        getMapStyles: function() {
            return [
                {
                    featureType: 'poi',
                    elementType: 'labels',
                    stylers: [{ visibility: 'off' }]
                }
            ];
        },

        /**
         * Bind UI events
         */
        bindEvents: function() {
            const self = this;

            // Refresh button
            $('#ocld-refresh-btn').on('click', function() {
                self.loadData();
            });

            // Date filter
            $('#ocld-date-filter').on('change', function() {
                self.loadData();
            });

            // Group filter
            $('#ocld-group-filter').on('change', function() {
                self.filterByGroup($(this).val());
            });

            // Status filter
            $('#ocld-status-filter').on('change', function() {
                self.filterByStatus($(this).val());
            });

            // Sidebar close
            $('#ocld-sidebar-close').on('click', function() {
                self.closeSidebar();
            });

            // Print button
            $('#ocld-print-btn').on('click', function() {
                self.printPickingList();
            });
        },

        /**
         * Load data from REST API
         */
        loadData: function() {
            const self = this;
            const date = $('#ocld-date-filter').val();

            console.log('OCLD: Loading data for date:', date);

            // Show loading
            this.showLoading();

            // API endpoint
            const apiUrl = ocldData.restUrl + 'orders?date=' + date;

            // AJAX request
            $.ajax({
                url: apiUrl,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', ocldData.restNonce);
                },
                success: function(response) {
                    console.log('OCLD: Data loaded:', response);
                    self.currentData = response;
                    self.renderData(response);
                    self.hideLoading();
                },
                error: function(xhr, status, error) {
                    console.error('OCLD: Error loading data:', error);
                    self.showError(ocldData.strings.error);
                    self.hideLoading();
                }
            });
        },

        /**
         * Render all data on map
         */
        renderData: function(data) {
            console.log('OCLD: Rendering data...');

            // Clear existing markers and polygons
            this.clearMap();

            // Render polygons (shipping groups)
            if (data.polygons && data.polygons.length > 0) {
                this.renderPolygons(data.polygons);
            }

            // Render order markers
            if (data.orders && data.orders.length > 0) {
                this.renderOrders(data.orders);
            }

            // Update stats
            this.updateStats(data.stats);

            // Populate group filter
            this.populateGroupFilter(data.groups);
        },

        /**
         * Render polygons on map
         */
            /**
             * Render polygons on map
             */
            renderPolygons: function(polygons) {
                const self = this;

                polygons.forEach(function(polygonData) {

                    // Parse coordinates
                    let parsedData = null;
                    try {
                        if (typeof polygonData.coordinates === 'string') {
                            parsedData = JSON.parse(polygonData.coordinates);
                        } else if (typeof polygonData.coordinates === 'object') {
                            parsedData = polygonData.coordinates;
                        } else {
                            console.error('OCLD: Unexpected coordinates format', polygonData.coordinates);
                            return;
                        }
                    } catch(e) {
                        console.error('OCLD: Invalid polygon coordinates', e);
                        return;
                    }

                    // Extract gm_shapes from parsed data
                    let paths = parsedData.gm_shapes || parsedData;

                    if (!Array.isArray(paths)) {
                        console.error('OCLD: gm_shapes is not an array', paths);
                        return;
                    }

                    // ✨ זה החלק החדש - המרה ל-numbers
                    paths = paths.map(function(path) {
                        return path.map(function(coord) {
                            return {
                                lat: parseFloat(coord.lat),
                                lng: parseFloat(coord.lng)
                            };
                        });
                    });


                    // Create polygon
                    const polygon = new google.maps.Polygon({
                        paths: paths,  // Now this is the correct format
                        strokeColor: polygonData.color || '#0073aa',
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: polygonData.color || '#0073aa',
                        fillOpacity: 0.15,
                        map: self.map,
                        clickable: true
                    });

                    // Store polygon with data
                    polygon.data = polygonData;
                    self.polygons.push(polygon);

                    // Click event
                    google.maps.event.addListener(polygon, 'click', function(event) {
                        self.onPolygonClick(polygon, event);
                    });

                    // Hover events
                    google.maps.event.addListener(polygon, 'mouseover', function() {
                        polygon.setOptions({
                            fillOpacity: 0.3,
                            strokeWeight: 3
                        });
                    });

                    google.maps.event.addListener(polygon, 'mouseout', function() {
                        polygon.setOptions({
                            fillOpacity: 0.15,
                            strokeWeight: 2
                        });
                    });
                });

                console.log('OCLD: Rendered', polygons.length, 'polygons');
            },

        /**
         * Render order markers on map
         */
        renderOrders: function(orders) {
            const self = this;

            orders.forEach(function(order) {
                // Check if order has coordinates
                if (!order.lat || !order.lng) {
                    console.warn('OCLD: Order', order.id, 'missing coordinates');
                    return;
                }

                // Create marker
                const marker = new google.maps.Marker({
                    position: { lat: parseFloat(order.lat), lng: parseFloat(order.lng) },
                    map: self.map,
                    title: 'Order #' + order.id,
                    icon: self.getMarkerIcon(order.status),
                    animation: google.maps.Animation.DROP
                });

                // Store order data
                marker.data = order;
                self.markers.push(marker);

                // Click event
                google.maps.event.addListener(marker, 'click', function() {
                    self.onMarkerClick(marker);
                });
            });

            console.log('OCLD: Rendered', orders.length, 'markers');

            // Create marker clusterer
            if (typeof markerClusterer !== 'undefined') {
                this.markerClusterer = new markerClusterer.MarkerClusterer({
                    map: this.map,
                    markers: this.markers
                });
            }
        },

        /**
         * Get marker icon based on order status
         */
        getMarkerIcon: function(status) {
            const colors = {
                'pending': '#ffb900',
                'processing': '#0073aa',
                'completed': '#46b450',
                'cancelled': '#dc3232'
            };

            const color = colors[status] || '#666666';

            return {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 8,
                fillColor: color,
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 2
            };
        },

        /**
         * Handle polygon click
         */
        onPolygonClick: function(polygon, event) {
            console.log('OCLD: Polygon clicked:', polygon.data);

            // Get orders in this polygon
            const ordersInPolygon = this.getOrdersInPolygon(polygon.data.group_id);

            // Show in sidebar
            this.showPolygonInfo(polygon.data, ordersInPolygon);

            // Optionally center map on polygon
            // this.map.panTo(event.latLng);
        },

        /**
         * Handle marker click
         */
        onMarkerClick: function(marker) {
            console.log('OCLD: Marker clicked:', marker.data);

            const order = marker.data;

            // Build info window content
            const content = `
                <div class="ocld-info-window">
                    <h3>Order #${order.id}</h3>
                    <p><strong>${order.customer_name}</strong></p>
                    <p>📍 ${order.address}</p>
                    <p>🕐 ${order.slot_formatted}</p>
                    <p><strong>סטטוס:</strong> ${this.getStatusLabel(order.status)}</p>
                    <div class="ocld-info-actions">
                        <a href="${order.edit_url}" class="button button-small" target="_blank">
                            ${ocldData.strings.viewOrder}
                        </a>
                        <button class="button button-small" onclick="OCLD.navigateToOrder(${order.lat}, ${order.lng})">
                            ${ocldData.strings.navigate}
                        </button>
                    </div>
                </div>
            `;

            // Show info window
            this.infoWindow.setContent(content);
            this.infoWindow.open(this.map, marker);
        },

        /**
         * Get orders within a polygon/group
         */
        getOrdersInPolygon: function(groupId) {
            if (!this.currentData || !this.currentData.orders) {
                return [];
            }

            // Get the polygon object
            const polygon = this.polygons.find(p => p.data.group_id == groupId);
            if (!polygon) {
                return [];
            }

            // Filter orders that are inside this polygon
            const self = this;
            return this.currentData.orders.filter(function(order) {
                // Skip orders without coordinates
                if (!order.lat || !order.lng) {
                    return false;
                }

                // Check if order is inside polygon
                const point = new google.maps.LatLng(parseFloat(order.lat), parseFloat(order.lng));
                return google.maps.geometry.poly.containsLocation(point, polygon);
            });
        },

        /**
         * Show polygon info in sidebar
         */
        showPolygonInfo: function(polygonData, orders) {
            // Update sidebar title
            $('#ocld-sidebar-title').text(polygonData.name);

            // Build content
            let content = `
                <div class="ocld-polygon-info">
                    <div class="ocld-polygon-stats">
                        <p><strong>📦 ${orders.length} הזמנות</strong></p>
                    </div>
                    <h3>רשימת הזמנות:</h3>
                    <div class="ocld-orders-list">
            `;

            if (orders.length === 0) {
                content += '<p>אין הזמנות באזור זה</p>';
            } else {
                orders.forEach(function(order) {
                    content += `
                        <div class="ocld-order-item">
                            <div class="ocld-order-header">
                                <strong>#${order.id}</strong>
                                <span class="ocld-order-status ocld-status-${order.status}">
                                    ${order.status}
                                </span>
                            </div>
                            <div class="ocld-order-details">
                                <p>${order.customer_name}</p>
                                <p class="ocld-order-address">📍 ${order.address}</p>
                                <p class="ocld-order-time">🕐 ${order.slot_formatted}</p>
                            </div>
                            <div class="ocld-order-actions">
                                <a href="${order.edit_url}" class="button button-small" target="_blank">
                                    פתח הזמנה
                                </a>
                            </div>
                        </div>
                    `;
                });
            }

            content += '</div></div>';

            // Update sidebar
            $('#ocld-sidebar-content').html(content);

            // Show footer with print button
            $('#ocld-sidebar-footer').show();

            // Open sidebar if closed
            $('#ocld-sidebar').removeClass('collapsed');
        },

        /**
         * Close sidebar
         */
        closeSidebar: function() {
            $('#ocld-sidebar').addClass('collapsed');
            $('#ocld-sidebar-footer').hide();
        },

        /**
         * Update stats bar
         */
        updateStats: function(stats) {
            if (!stats) return;

            $('#ocld-stat-orders').text(stats.total_orders || 0);
            $('#ocld-stat-weight').text((stats.total_weight || 0) + ' kg');
            $('#ocld-stat-slots').text(stats.active_slots || 0);
            $('#ocld-stat-groups').text(stats.active_groups || 0);
        },

        /**
         * Populate group filter dropdown
         */
        populateGroupFilter: function(groups) {
            const $select = $('#ocld-group-filter');
            $select.find('option:not(:first)').remove();

            if (groups && groups.length > 0) {
                groups.forEach(function(group) {
                    $select.append(
                        $('<option></option>')
                            .val(group.id)
                            .text(group.name)
                    );
                });
            }
        },

        /**
         * Filter markers by group
         */
        filterByGroup: function(groupId) {
            console.log('OCLD: Filtering by group:', groupId);

            this.markers.forEach(function(marker) {
                if (!groupId || marker.data.group_id == groupId) {
                    marker.setVisible(true);
                } else {
                    marker.setVisible(false);
                }
            });
        },

        /**
         * Filter markers by status
         */
        filterByStatus: function(status) {
            console.log('OCLD: Filtering by status:', status);

            this.markers.forEach(function(marker) {
                if (!status || marker.data.status == status) {
                    marker.setVisible(true);
                } else {
                    marker.setVisible(false);
                }
            });
        },

        /**
         * Clear all markers and polygons from map
         */
        clearMap: function() {
            // Clear markers
            this.markers.forEach(function(marker) {
                marker.setMap(null);
            });
            this.markers = [];

            // Clear clusterer
            if (this.markerClusterer) {
                this.markerClusterer.clearMarkers();
                this.markerClusterer = null;
            }

            // Clear polygons
            this.polygons.forEach(function(polygon) {
                polygon.setMap(null);
            });
            this.polygons = [];

            console.log('OCLD: Map cleared');
        },

        /**
         * Show loading overlay
         */
        showLoading: function() {
            $('#ocld-loading').removeClass('hidden');
        },

        /**
         * Hide loading overlay
         */
        hideLoading: function() {
            $('#ocld-loading').addClass('hidden');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            alert(message); // Simple for now, can be improved
        },

        /**
         * Get status label
         */
        getStatusLabel: function(status) {
            const labels = {
                'pending': 'ממתין',
                'processing': 'בטיפול',
                'completed': 'הושלם',
                'cancelled': 'בוטל'
            };
            return labels[status] || status;
        },

        /**
         * Navigate to order location
         */
        navigateToOrder: function(lat, lng) {
            const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
            window.open(url, '_blank');
        },

        /**
         * Print picking list
         */
        printPickingList: function() {
            window.print(); // Simple for now
        }

    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        // Wait a bit for Google Maps to fully load
        setTimeout(function() {
            OCLD.init();
        }, 100);
    });

    // Expose OCLD to global scope for inline onclick handlers
    window.OCLD = OCLD;

})(jQuery);