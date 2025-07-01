/**
 * Path: app/code/Tirehub/TransferNetwork/view/frontend/web/js/map.js
 */
define([
    'jquery'
], function ($) {
    'use strict';

    function TransferNetworkMap(config) {
        this.config = config;
        this.map = null;
        this.markers = [];
        this.polylines = [];
        this.locations = [];
        this.relations = [];
    }

    TransferNetworkMap.prototype = {
        init: function () {
            var self = this;

            // Wait for Google Maps API to be ready
            if (typeof google !== 'undefined' && google.maps) {
                this.initMap();
            } else {
                window.transferNetworkMapReady = function () {
                    self.initMap();
                };
            }
        },

        initMap: function () {
            var self = this;

            // Initialize the map
            this.map = new google.maps.Map(document.getElementById('google-map'), {
                center: this.config.center,
                zoom: this.config.zoom,
                mapTypeId: google.maps.MapTypeId.ROADMAP
            });

            // Load data and display markers and polylines
            this.loadData().then(function () {
                self.displayLocations();
                self.displayRelations();
                self.hideLoading();
            }).catch(function (error) {
                console.error('Error loading transfer network data:', error);
                self.showError();
            });
        },

        loadData: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                $.ajax({
                    url: self.config.ajaxUrl,
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            self.locations = response.locations || [];
                            self.relations = response.relations || [];
                            resolve();
                        } else {
                            reject(new Error(response.message || 'Failed to load data'));
                        }
                    },
                    error: function (xhr, status, error) {
                        reject(new Error('AJAX request failed: ' + error));
                    }
                });
            });
        },

        displayLocations: function () {
            var self = this;

            this.locations.forEach(function (location) {
                var marker = new google.maps.Marker({
                    position: {
                        lat: location.latitude,
                        lng: location.longitude
                    },
                    map: self.map,
                    title: location.name,
                    icon: {
                        url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(self.createMarkerSVG()),
                        scaledSize: new google.maps.Size(30, 30),
                        anchor: new google.maps.Point(15, 15)
                    }
                });

                // Add info window
                var infoWindow = new google.maps.InfoWindow({
                    content: '<div class="marker-info"><h4>' + location.name + '</h4>' +
                        '<p>Lat: ' + location.latitude + '</p>' +
                        '<p>Lng: ' + location.longitude + '</p></div>'
                });

                marker.addListener('click', function () {
                    // Close all other info windows
                    self.markers.forEach(function (markerData) {
                        if (markerData.infoWindow) {
                            markerData.infoWindow.close();
                        }
                    });

                    infoWindow.open(self.map, marker);
                });

                self.markers.push({
                    marker: marker,
                    infoWindow: infoWindow,
                    location: location
                });
            });

            // Auto-fit map to show all markers
            if (this.markers.length > 0) {
                this.fitMapToMarkers();
            }
        },

        displayRelations: function () {
            var self = this;

            this.relations.forEach(function (relation) {
                var polyline = new google.maps.Polyline({
                    path: [
                        {
                            lat: relation.from.latitude,
                            lng: relation.from.longitude
                        },
                        {
                            lat: relation.to.latitude,
                            lng: relation.to.longitude
                        }
                    ],
                    geodesic: true,
                    strokeColor: '#FF0000',
                    strokeOpacity: 0.8,
                    strokeWeight: 3,
                    map: self.map
                });

                // Add click listener for polyline
                polyline.addListener('click', function (event) {
                    var infoWindow = new google.maps.InfoWindow({
                        content: '<div class="polyline-info">' +
                            '<h4>Connection</h4>' +
                            '<p>From: ' + relation.from.name + '</p>' +
                            '<p>To: ' + relation.to.name + '</p>' +
                            '</div>',
                        position: event.latLng
                    });

                    // Close other info windows
                    self.markers.forEach(function (markerData) {
                        if (markerData.infoWindow) {
                            markerData.infoWindow.close();
                        }
                    });

                    infoWindow.open(self.map);
                });

                self.polylines.push({
                    polyline: polyline,
                    relation: relation
                });
            });
        },

        createMarkerSVG: function () {
            return '<svg width="30" height="30" viewBox="0 0 30 30" xmlns="http://www.w3.org/2000/svg">' +
                '<circle cx="15" cy="15" r="12" fill="#4285F4" stroke="#FFFFFF" stroke-width="2"/>' +
                '<circle cx="15" cy="15" r="5" fill="#FFFFFF"/>' +
                '</svg>';
        },

        fitMapToMarkers: function () {
            if (this.markers.length === 0) return;

            var bounds = new google.maps.LatLngBounds();

            this.markers.forEach(function (markerData) {
                bounds.extend(markerData.marker.getPosition());
            });

            this.map.fitBounds(bounds);

            // Ensure minimum zoom level
            google.maps.event.addListenerOnce(this.map, 'bounds_changed', function () {
                if (this.getZoom() > 15) {
                    this.setZoom(15);
                }
            });
        },

        hideLoading: function () {
            $('#loading-message').hide();
            $('#map-container').show();
        },

        showError: function () {
            $('#loading-message').hide();
            $('#error-message').show();
        },

        // Public methods for external control
        addLocation: function (location) {
            this.locations.push(location);
            this.displayLocations();
        },

        addRelation: function (relation) {
            this.relations.push(relation);
            this.displayRelations();
        },

        clearMap: function () {
            // Clear markers
            this.markers.forEach(function (markerData) {
                markerData.marker.setMap(null);
                if (markerData.infoWindow) {
                    markerData.infoWindow.close();
                }
            });
            this.markers = [];

            // Clear polylines
            this.polylines.forEach(function (polylineData) {
                polylineData.polyline.setMap(null);
            });
            this.polylines = [];
        },

        refreshMap: function () {
            var self = this;
            this.clearMap();

            this.loadData().then(function () {
                self.displayLocations();
                self.displayRelations();
            }).catch(function (error) {
                console.error('Error refreshing map:', error);
            });
        }
    };

    return TransferNetworkMap;
});
