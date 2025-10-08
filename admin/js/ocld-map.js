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

            this.clearMap();

            if (data.polygons && data.polygons.length > 0) {
                this.renderPolygons(data.polygons);
            }

            if (data.orders && data.orders.length > 0) {
                this.renderOrders(data.orders);
            }

            if (data.groups && data.groups.length > 0) {
                this.renderGroups(data.groups);
            }

            // Initial products summary (all orders)
            this.renderProductsSummary(data.orders);
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
                if (!order.lat || !order.lng) {
                    console.warn('OCLD: Order ' + order.id + ' missing coordinates');
                    return;
                }

                const position = {
                    lat: parseFloat(order.lat),
                    lng: parseFloat(order.lng)
                };

                // Create marker
                const marker = new google.maps.Marker({
                    position: position,
                    map: self.map,
                    title: order.customer_name,
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 8,
                        fillColor: self.getStatusColor(order.status),
                        fillOpacity: 0.9,
                        strokeColor: '#ffffff',
                        strokeWeight: 2
                    }
                });

                // Create info window content
                const infoContent = self.createOrderInfoWindow(order);

                const infoWindow = new google.maps.InfoWindow({
                    content: infoContent
                });

                marker.addListener('click', function() {
                    // Close other info windows
                    self.markers.forEach(function(m) {
                        if (m.infoWindow) {
                            m.infoWindow.close();
                        }
                    });

                    infoWindow.open(self.map, marker);
                });

                marker.infoWindow = infoWindow;
                self.markers.push(marker);
            });

            console.log('OCLD: Rendered ' + self.markers.length + ' markers');
        },

            createOrderInfoWindow: function(order) {
                // Status badge
                const statusClass = 'ocld-status-' + order.status;
                const statusText = order.status.replace('-', ' ').toUpperCase();

                // Build products list
                let productsHTML = '';
                let totalWeight = 0;
                let hasWeightedItems = false;

                if (order.items && order.items.length > 0) {
                    productsHTML = '<div class="ocld-products-list">';
                    order.items.forEach(function(item) {
                        const itemWeight = parseFloat(item.weight) || 0;
                        if (itemWeight > 0) {
                            totalWeight += itemWeight;
                            hasWeightedItems = true;
                        }

                        productsHTML += `
                <div class="ocld-product-item">
                    <div class="ocld-product-info">
                        <div class="ocld-product-name">${item.name}</div>
                        <div class="ocld-product-qty">כמות: ${item.quantity}</div>
                    </div>
                    <div class="ocld-product-weight">${item.display}</div>
                </div>
            `;
                    });
                    productsHTML += '</div>';

                    // Show total weight only if there are weighted items
                    if (hasWeightedItems) {
                        productsHTML += `
                <div class="ocld-products-summary">
                    <span class="ocld-summary-label">סה"כ משקל:</span>
                    <span class="ocld-summary-value">${totalWeight.toFixed(3)} ק"ג</span>
                </div>
            `;
                    }
                }

                return `
        <div class="ocld-info-window">
            <div class="ocld-info-header">
                <div class="ocld-info-order-id">הזמנה #${order.id}</div>
                <div class="ocld-info-customer">${order.customer_name}</div>
                <div class="ocld-info-address">${order.address}, ${order.city}</div>
            </div>
            <div class="ocld-info-body">
                <div class="ocld-info-meta">
                    <div class="ocld-info-meta-item">
                        <div class="ocld-info-meta-label">סטטוס</div>
                        <div class="ocld-info-meta-value">
                            <span class="ocld-status-badge ${statusClass}">${statusText}</span>
                        </div>
                    </div>
                    <div class="ocld-info-meta-item">
                        <div class="ocld-info-meta-label">סכום</div>
                        <div class="ocld-info-meta-value">₪${order.total}</div>
                    </div>
                </div>
                
                ${order.slot_formatted ? `
                    <div class="ocld-info-meta-item" style="margin-bottom: 12px;">
                        <div class="ocld-info-meta-label">זמן משלוח</div>
                        <div class="ocld-info-meta-value">${order.slot_formatted}</div>
                    </div>
                ` : ''}
                
                <div class="ocld-info-section">
                    <div class="ocld-info-label">מוצרים להכנה</div>
                    ${productsHTML}
                </div>
                
                <div class="ocld-info-actions">
                    <a href="${order.edit_url}" target="_blank" class="ocld-edit-btn">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        ערוך הזמנה
                    </a>
                </div>
            </div>
        </div>
    `;
            },

        getStatusColor: function(status) {
            const colors = {
                'pending': '#f59e0b',
                'processing': '#3b82f6',
                'completed': '#10b981',
                'on-hold': '#ef4444',
                'cancelled': '#6b7280'
            };
            return colors[status] || '#6b7280';
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


        renderGroups: function(groups) {
            const self = this;
            const groupsList = document.getElementById('ocld-groups-list');

            if (!groupsList) return;

            groupsList.innerHTML = '';

            // Find first group with orders for default selection
            let defaultGroupId = null;

            groups.forEach(function(group) {
                const ordersInGroup = self.currentData.orders.filter(function(order) {
                    return order.group_id == group.id;
                });

                if (!defaultGroupId && ordersInGroup.length > 0) {
                    defaultGroupId = group.id;
                }

                const li = document.createElement('li');
                li.className = 'ocld-group-item';
                li.dataset.groupId = group.id;

                li.innerHTML = `
            <a class="ocld-group-link">
                <div class="ocld-group-info">
                    <div class="ocld-group-name">${group.name}</div>
                    <div class="ocld-group-meta">${ordersInGroup.length} הזמנות</div>
                </div>
                <div class="ocld-group-badge">${ordersInGroup.length}</div>
            </a>
        `;

                li.addEventListener('click', function() {
                    // Remove active from all
                    document.querySelectorAll('.ocld-group-item').forEach(function(item) {
                        item.classList.remove('active');
                    });

                    // Add active to clicked
                    li.classList.add('active');

                    // Show group details
                    self.showGroupOrders(group.id);
                });

                groupsList.appendChild(li);
            });

            // Select default group
            if (defaultGroupId) {
                const defaultItem = groupsList.querySelector(`[data-group-id="${defaultGroupId}"]`);
                if (defaultItem) {
                    defaultItem.click();
                }
            }
        },

        renderProductsSummary: function(orders) {
            const summaryContainer = document.getElementById('ocld-products-summary');

            if (!summaryContainer) return;

            // Aggregate products
            const productsMap = {};
            let totalWeight = 0;

            orders.forEach(function(order) {
                if (!order.items) return;

                order.items.forEach(function(item) {
                    const key = item.name;
                    const weight = parseFloat(item.weight) || 0;
                    const quantity = parseFloat(item.quantity) || 0;

                    if (!productsMap[key]) {
                        productsMap[key] = {
                            name: item.name,
                            weight: 0,
                            quantity: 0,
                            is_weighted: item.is_weighted
                        };
                    }

                    productsMap[key].weight += weight;
                    productsMap[key].quantity += quantity;

                    if (weight > 0) {
                        totalWeight += weight;
                    }
                });
            });

            // Convert to array and sort
            const products = Object.values(productsMap).sort(function(a, b) {
                return b.weight - a.weight;
            });

            // Render
            if (products.length === 0) {
                summaryContainer.innerHTML = `
            <div class="ocld-empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
                <div class="ocld-empty-state-text">אין מוצרים להצגה</div>
            </div>
        `;
                return;
            }

            let html = '';

            products.forEach(function(product) {
                let displayValue;
                if (product.is_weighted && product.weight > 0) {
                    displayValue = product.weight.toFixed(3) + ' ק"ג';
                } else {
                    displayValue = Math.round(product.quantity) + ' יח\'';
                }

                html += `
            <div class="ocld-product-summary-item">
                <div class="ocld-product-summary-name">${product.name}</div>
                <div class="ocld-product-summary-weight">${displayValue}</div>
            </div>
        `;
            });

            // Add total
            if (totalWeight > 0) {
                html += `
            <div class="ocld-products-summary-total">
                <div class="ocld-summary-total-label">סה"כ משקל:</div>
                <div class="ocld-summary-total-value">${totalWeight.toFixed(3)} ק"ג</div>
            </div>
        `;
            }

            summaryContainer.innerHTML = html;
        },

        showGroupOrders: function(groupId) {
            const ordersInGroup = this.getOrdersInPolygon(groupId);
            this.renderProductsSummary(ordersInGroup);

            // Optional: Zoom to polygon
            const polygon = this.polygons.find(p => p.data.group_id == groupId);
            if (polygon) {
                const bounds = new google.maps.LatLngBounds();
                polygon.getPath().forEach(function(latLng) {
                    bounds.extend(latLng);
                });
                this.map.fitBounds(bounds);
            }
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