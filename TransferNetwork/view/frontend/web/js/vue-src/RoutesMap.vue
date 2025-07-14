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
                    <div class="map-legend-list-item rdc-replenishment">
                        {{ t('RDC to TLC Replenishment Only') }}
                    </div>
                </div>
                <div class="map-legend-list-column">
                    <div class="map-legend-list-item tlc-transfer">
                        {{ t('TLC to TLC Transfer') }}
                    </div>
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
                        {{ t('Show / Hide Non-TH Locations') }}
                    </span>
                    <input v-model="showNonThLocations" type="checkbox">
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
        defaultLocations: {
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
            directionsService: null,
            routesProcessed: 0,
            totalRoutes: 0,
            showNonThLocations: true,
            locations: []
        };
    },
    created () {
        this.locations = _.map(this.defaultLocations, (location) => {
            location.address = '814 44TH ST NW STE 102 AUBURN, WA98001-1754 253-856-1800';
            location.openingHours = [
                {
                    weekDay: 'Monday - Friday',
                    time: '07:30 AM - 05:00 PM'
                },
                {
                    weekDay: 'Saturday',
                    time: '07:30 AM - 01:00 PM'
                }
            ];
            location.cutoff = {
                transferToPrimary: 'From 134 Portland',
                days: 1,
                time: '04:00 PM'
            };
            return location;
        });
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
            this.locations.forEach(location => {
                const marker = new google.maps.Marker({
                    position: { lat: location.lat, lng: location.lng },
                    map: this.map,
                    title: location.name,
                    icon: this.getMarkerIcon(location)
                });

                // Store custom info on marker
                marker.cluster = location.cluster;
                marker.isTirehub = location.isTirehub;

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
            let fillColor = '#ffffff';
            const strokeColor = '#333333';
            let scale = 8;

            // Set colors based on cluster type
            switch (location.cluster) {
            case 'RDC':
                fillColor = '#e74c3c';
                scale = 12;
                break;
            case '500 Cluster':
                fillColor = '#95a5a6';
                break;
            case '501 Cluster':
                fillColor = '#3498db';
                break;
            case '502 Cluster':
                fillColor = '#f1c40f';
                break;
            case 'No RDC':
            default:
                fillColor = '#ffffff';
                break;
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
                        <div class="info-window-content-hours">
                            <div class="hours-header">${this.t('Opening Hours:')}</div>
                            ${location.openingHours.map(hours => `
                                <div class="hours-row">
                                    <span class="weekday">${hours.weekDay}</span>
                                    <span class="time">${hours.time}</span>
                                </div>
                            `).join('')}
                        </div>
                        ${location.cutoff ? `
                            <div class="info-window-content-cutoff">
                                <div class="cutoff-header">
                                    <div>Transfer To Primary</div>
                                    <div>Days</div>
                                    <div>Cutoff</div>
                                </div>
                                <div class="cutoff-info">
                                    <div class="cutoff-info-label">${location.cutoff.transferToPrimary}</div>
                                    <div>${location.cutoff.days}</div>
                                    <div>${location.cutoff.time}</div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
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
                strokeWeight: route.weight
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

            // Also toggle related routes
            this.polylines.forEach(polyline => {
                const fromMarker = this.markers[polyline.fromId];
                const toMarker = this.markers[polyline.toId];

                if ((fromMarker && !fromMarker.isTirehub) || (toMarker && !toMarker.isTirehub)) {
                    polyline.setVisible(visible);
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
