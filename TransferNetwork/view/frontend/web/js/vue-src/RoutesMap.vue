<template>
    <div class="map-container">
        <div class="loading" id="loading">
            <div class="loading-spinner"></div>
            <p>Loading map...</p>
        </div>

        <div id="map" ref="map"></div>
    </div>
</template>

<script>
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
            markers: {},
            polylines: [],
            infoWindows: []
        };
    },
    mounted () {
        if (!(window && window.google && window.google.maps)) {
            return;
        }

        this.$nextTick(() => {
            var mapElement = this.$refs.map;

            this.initializeMap(mapElement);

            // Add all location markers
            this.createMarkers();

            // Draw all routes
            this.drawRoutes();

            // Set up event listeners
            // this.setupEventListeners();

            // Hide loading
            document.getElementById('loading').style.display = 'none';
        });
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
        },
        createMarkers () {
            this.locations.forEach(location => {
                const marker = new google.maps.Marker({
                    position: { lat: location.lat, lng: location.lng },
                    map: this.map,
                    title: location.name,
                    icon: this.getMarkerIcon(location)
                });

                // Store cluster info on marker
                marker.cluster = location.cluster;

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

            if (location.type === 'RDC') {
                return {
                    path: google.maps.SymbolPath.CIRCLE,
                    fillColor: fillColor,
                    fillOpacity: 1,
                    strokeColor: strokeColor,
                    strokeWeight: 3,
                    scale: scale
                };
            }

            return {
                path: google.maps.SymbolPath.CIRCLE,
                fillColor: fillColor,
                fillOpacity: 0.9,
                strokeColor: strokeColor,
                strokeWeight: 2,
                scale: scale
            };
        },

        createInfoWindowContent (location) {
            return `
                <div class="info-window">
                    <h4>${location.name}</h4>
                    <p><strong>ID:</strong> ${location.id}</p>
                    <p><strong>Type:</strong> ${location.type || 'TLC'}</p>
                    <p><strong>Cluster:</strong> ${location.cluster}</p>
                    <p><strong>Coordinates:</strong> ${location.lat.toFixed(4)}, ${location.lng.toFixed(4)}</p>
                </div>
            `;
        },

        drawRoutes () {
            // Then draw location connections (orange/red thin lines)
            this.routes.forEach(route => {
                const fromLocation = this.locations.find(loc => loc.id === route.from);
                const toLocation = this.locations.find(loc => loc.id === route.to);

                if (fromLocation && toLocation) {
                    // For RDC connections, create curved paths that follow highways
                    let path = [];

                    path = [fromLocation, toLocation];

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

                    polyline.setMap(this.map);
                    this.polylines.push(polyline);
                }
            });
        }
    }
};
</script>
