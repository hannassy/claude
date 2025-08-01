<template>
    <div class="map-container">
        <div v-if="loading" class="map-loading">
            <div class="map-loading-spinner" />
            <p>Loading map...</p>
        </div>

        <div id="map" ref="map" />

        <div class="map-legend">
            <div class="map-legend-title">{{ t('Map Legend:') }}</div>

            <div class="map-legend-list">
                <div class="map-legend-list-column">
                    <div class="map-legend-list-item rdc-inventory">
                        {{ t('RDC to TLC with RDC Inventory Visibility') }}
                    </div>
                    <div class="map-legend-list-item tlc-transfer">
                        {{ t('TLC to TLC Transfer') }}
                    </div>
                </div>
                <div class="map-legend-list-column">
                    <div class="map-legend-list-item port">
                        {{ t('Inbound Outbound Port') }}
                    </div>
                    <div class="map-legend-list-item manufacturer">
                        {{ t('Manufacturer DC') }}
                    </div>
                </div>
            </div>

            <div class="map-legend-action">
                <label class="checkbox-custom">
                    <span class="checkbox-custom-label">
                        {{ t('Show Non-TH Locations') }}
                    </span>
                    <input v-model="showNonThLocations" type="checkbox">
                    <span class="checkbox-custom-checkmark checkbox-custom-checkmark-arrow" />
                </label>
                <label class="checkbox-custom">
                    <span class="checkbox-custom-label">
                        {{ t('Show TLC Numbers') }}
                    </span>
                    <input v-model="showTlsNumbers" type="checkbox">
                    <span class="checkbox-custom-checkmark checkbox-custom-checkmark-arrow" />
                </label>
            </div>
        </div>
    </div>
</template>

<script>
import _ from 'lodash';

export default {
    props: {
        locations: {
            type: Array
        },
        routes: {
            type: Array
        }
    },
    data () {
        return {
            map: null,
            loading: false,
            markers: {},
            polylines: [],
            infoWindows: [],
            markerLabels: [],
            directionsService: null,
            routesProcessed: 0,
            totalRoutes: 0,
            showNonThLocations: true,
            showTlsNumbers: true
        };
    },
    mounted () {
        if (!(window && window.google && window.google.maps)) {
            return;
        }

        this.loading = true;

        this.$nextTick(() => {
            var mapElement = this.$refs.map;

            this.initializeMap(mapElement);

            // Add all location markers
            this.createMarkers();

            // Draw all routes
            this.drawRoutes();
        });
    },
    watch: {
        showNonThLocations (visible) {
            this.toggleHhLocations(visible);
        },
        showTlsNumbers (visible) {
            this.toggleTlsNumbers(visible);
        }
    },
    methods: {
        initializeMap () {
            // Initialize map centered on United States
            this.map = new google.maps.Map(document.getElementById('map'), {
                zoom: 5,
                center: { lat: 39.8283, lng: -98.5795 },
                zoomControl: true,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false

            });

            // Initialize Directions service if available
            if (window.google && window.google.maps && window.google.maps.DirectionsService) {
                this.directionsService = new google.maps.DirectionsService();
            }
        },
        createMarkers () {
            this.locations.forEach((location, index) => {
                const marker = new google.maps.Marker({
                    position: { lat: location.lat, lng: location.lng },
                    map: this.map,
                    title: location.name,
                    icon: this.getMarkerIcon(location)
                });

                // Store custom info on marker
                marker.cluster = location.cluster;
                marker.isTirehub = location.isTirehub;

                // Create TLC Number labels
                this.createMarkerLabel(marker, location.id, location.isTirehub);

                // Create info window
                const infoWindow = new google.maps.InfoWindow({
                    content: this.createInfoWindowContent(location)
                });

                marker.addListener('click', () => {
                    // Close all other info windows
                    this.infoWindows.forEach(iw => iw.close());
                    infoWindow.open(this.map, marker);
                });

                this.markers[location.id] = marker;
                this.infoWindows.push(infoWindow);
            });
        },

        getMarkerIcon (location) {
            // Use location.icon if present
            if (location.icon) {
                return {
                    url: location.icon,
                    scaledSize: new google.maps.Size(24, 24),
                    origin: new google.maps.Point(0, 0),
                    anchor: new google.maps.Point(12, 12)
                };
            }

            // Fallback to current marker icon logic
            const fillColor = location.color || '#ffffff';
            const strokeColor = '#333333';
            let scale = 8;

            // Set scale based on cluster type
            const locationType = _.get(location, 'type', 'tlc');
            if (_.toLower(locationType) === 'rdc') {
                scale = 12;
            }

            return {
                path: google.maps.SymbolPath.CIRCLE,
                fillColor: fillColor,
                fillOpacity: 1,
                strokeColor: strokeColor,
                strokeWeight: 2,
                scale: scale
            };
        },

        createInfoWindowContent (location) {
            return `
                <div class="info-window">
                    <div class="info-window-name">${location.name}</div>
                    <div class="info-window-content">
                        <div class="info-window-content-address">${location.address}</div>
                        ${location.openingHours ? `
                        <div class="info-window-content-hours">
                            <div class="hours-header">${this.t('Opening Hours:')}</div>
                            ${location.openingHours.map(hours => `
                                <div class="hours-row">
                                    <span class="weekday">${hours.weekDay}</span>
                                    <span class="time">${hours.time}</span>
                                </div>
                            `).join('')}
                        </div>
                        ` : ''}
                        ${location.cutoff.length ? `
                            <div class="info-window-content-cutoff">
                                <div class="cutoff-header">
                                    <div>Transfers From</div>
                                    <div>Days</div>
                                    <div>Cutoff</div>
                                </div>
                                ${location.cutoff.map(cutoff => `
                                    <div class="cutoff-info">
                                        <div class="cutoff-info-label">${cutoff.to}</div>
                                        <div>${cutoff.days}</div>
                                        <div>${cutoff.time}</div>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        },

        createMarkerLabel (marker, text, isTirehub) {
            // Create custom overlay for the label positioned below marker
            const LabelOverlay = function (position, text, map) {
                this.position = position;
                this.text = text;
                this.map = map;
                this.div = null;
                this.setMap(map);
            };

            LabelOverlay.prototype = new google.maps.OverlayView();

            LabelOverlay.prototype.onAdd = function() {
                const div = document.createElement('div');
                div.className = 'marker-label';
                div.textContent = this.text;

                this.div = div;
                const panes = this.getPanes();
                panes.overlayMouseTarget.appendChild(div);
            };

            LabelOverlay.prototype.draw = function () {
                const overlayProjection = this.getProjection();
                const position = overlayProjection.fromLatLngToDivPixel(this.position);

                if (this.div) {
                    this.div.style.left = (position.x - 16) + 'px';
                    this.div.style.top = (position.y + 15) + 'px';
                }
            };

            LabelOverlay.prototype.onRemove = function() {
                if (this.div) {
                    this.div.parentNode.removeChild(this.div);
                    this.div = null;
                }
            };

            // Create and store the overlay
            const overlay = new LabelOverlay(marker.getPosition(), text, this.map);
            overlay.isTirehub = isTirehub;
            this.markerLabels.push(overlay);
        },

        drawRoutes () {
            if (!this.routes || this.routes.length === 0) {
                this.loading = false;
                return;
            }

            // Initialize route tracking
            this.routesProcessed = 0;
            this.totalRoutes = this.routes.length;

            // Process routes sequentially to avoid rate limiting
            this.processRoutesSequentially(0);
        },

        processRoutesSequentially (index) {
            if (index >= this.routes.length) {
                this.checkLoadingComplete();
                return;
            }

            const route = this.routes[index];
            const fromLocation = _.find(this.locations, ['id', route.from]);
            const toLocation = _.find(this.locations, ['id', route.to]);

            if (fromLocation && toLocation) {
                this.drawSingleRoute(route, fromLocation, toLocation, () => {
                    this.routesProcessed++;
                    this.checkLoadingComplete();

                    // Process next route after a small delay to avoid rate limiting
                    setTimeout(() => {
                        this.processRoutesSequentially(index + 1);
                    }, 100);
                });
            } else {
                // Skip to next route if locations not found
                this.routesProcessed++;
                this.checkLoadingComplete();
                this.processRoutesSequentially(index + 1);
            }
        },

        drawSingleRoute (route, fromLocation, toLocation, callback) {
            // Try to use Directions API for realistic highway routes
            if (this.directionsService) {
                const request = {
                    origin: { lat: fromLocation.lat, lng: fromLocation.lng },
                    destination: { lat: toLocation.lat, lng: toLocation.lng },
                    travelMode: google.maps.TravelMode.DRIVING,
                    unitSystem: google.maps.UnitSystem.IMPERIAL,
                    avoidHighways: false,
                    avoidTolls: false
                };

                this.directionsService.route(request, (result, status) => {
                    if (status === google.maps.DirectionsStatus.OK) {
                        this.renderDirectionsRoute(result, route);
                    } else {
                        console.warn('Directions API failed for route', route.from, 'to', route.to, '- Status:', status);
                        this.renderDirectRoute(route, fromLocation, toLocation);
                    }
                    callback();
                });
            } else {
                // Fallback to direct route if Directions API not available
                this.renderDirectRoute(route, fromLocation, toLocation);
                callback();
            }
        },

        renderDirectionsRoute (directionsResult, route) {
            const path = directionsResult.routes[0].overview_path;

            const polyline = new google.maps.Polyline({
                path: path,
                geodesic: false,
                strokeColor: route.color,
                strokeOpacity: 0.7,
                strokeWeight: 2
            });

            // Store route info on polyline
            polyline.routeType = route.type;
            polyline.fromId = route.from;
            polyline.toId = route.to;
            polyline.isDirectionsRoute = true;

            polyline.setMap(this.map);
            this.polylines.push(polyline);
        },

        renderDirectRoute (route, fromLocation, toLocation) {
            const path = [
                { lat: fromLocation.lat, lng: fromLocation.lng },
                { lat: toLocation.lat, lng: toLocation.lng }
            ];

            const polyline = new google.maps.Polyline({
                path: path,
                geodesic: false,
                strokeColor: route.color,
                strokeOpacity: 0.7,
                strokeWeight: route.weight
            });

            // Store route info on polyline
            polyline.routeType = route.type;
            polyline.fromId = route.from;
            polyline.toId = route.to;
            polyline.isDirectionsRoute = false;

            polyline.setMap(this.map);
            this.polylines.push(polyline);
        },

        toggleHhLocations (visible) {
            Object.values(this.markers).forEach(marker => {
                if (!marker.isTirehub) {
                    marker.setVisible(visible);
                }
            });

            // Toggle marker labels for non-Tirehub locations
            this.markerLabels.forEach(overlay => {
                if (!overlay.isTirehub) {
                    if (visible) {
                        overlay.setMap(this.map);
                    } else {
                        overlay.setMap(null);
                    }
                }
            });

            // Also toggle related routes
            this.polylines.forEach(polyline => {
                const fromMarker = this.markers[polyline.fromId];
                const toMarker = this.markers[polyline.toId];

                if ((fromMarker && !fromMarker.isTirehub) || (toMarker && !toMarker.isTirehub)) {
                    polyline.setVisible(visible);
                }
            });
        },

        toggleTlsNumbers (visible) {
            this.markerLabels.forEach(overlay => {
                if (visible) {
                    overlay.setMap(this.map);
                } else {
                    overlay.setMap(null);
                }
            });
        },

        checkLoadingComplete () {
            if (this.routesProcessed >= this.totalRoutes) {
                this.loading = false;
            }
        }
    }
};
</script>
