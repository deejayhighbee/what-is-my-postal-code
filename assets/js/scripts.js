document.addEventListener('DOMContentLoaded', function() {
    const gpsBtn = document.getElementById('wimpc-gps-btn');
    const form = document.getElementById('wimpc-form');
    const results = document.getElementById('wimpc-results');
    const addressEl = document.getElementById('wimpc-address');
    const postalCodeEl = document.getElementById('wimpc-postal-code');
    const countryEl = document.getElementById('wimpc-country-name');
    const latEl = document.getElementById('wimpc-lat');
    const lonEl = document.getElementById('wimpc-lon');
    const mapEl = document.getElementById('wimpc-map');
    const notification = document.getElementById('wimpc-notification');
    let map;
    let marker;

    // Helper: add tile layer with a slight delay
    function addTileLayer() {
        setTimeout(() => {
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 20,  // explicitly set maxZoom for higher zoom levels if needed
                attribution: '© OpenStreetMap'
            }).addTo(map);
            // Immediately recalc map size after adding the layer
            map.invalidateSize();
        }, 0);
    }

    // Initialize or update the map with given latitude and longitude
    function initMap(lat, lon) {
        // Ensure the map container is visible and styled properly
        mapEl.classList.add('show');

        if (map) {
            map.setView([lat, lon], 13);
            if (marker) {
                marker.setLatLng([lat, lon]);
            } else {
                marker = L.marker([lat, lon], { draggable: true }).addTo(map);
                addMarkerDragEvent();
            }
        } else {
            map = L.map('wimpc-map').setView([lat, lon], 13);
            addTileLayer();
            marker = L.marker([lat, lon], { draggable: true }).addTo(map);
            addMarkerDragEvent();

            // Once the map is ready, force a size recalculation
            map.whenReady(function() {
                map.invalidateSize();
            });
        }

        // Also call invalidateSize after a short delay in case of layout changes
        setTimeout(function() {
            if (map) map.invalidateSize();
        }, 300);
    }

    // Add event listener for marker drag to update displayed coordinates and refetch location details
    function addMarkerDragEvent() {
        if (marker) {
            marker.on('dragend', function(e) {
                const newLatLng = e.target.getLatLng();
                const newLat = newLatLng.lat;
                const newLon = newLatLng.lng;

                // Update coordinate displays
                latEl.textContent = newLat.toFixed(6);
                lonEl.textContent = newLon.toFixed(6);

                // Fetch updated location details
                fetchLocation({ latitude: newLat, longitude: newLon });
            });
        }
    }

    // Fetch location data from Nominatim (reverse lookup for coordinates or manual search)
    function fetchLocation(query, country = '') {
        let url = '';
        if (typeof query === 'object') {
            const lat = query.latitude;
            const lon = query.longitude;
            url = `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lon}&addressdetails=1`;
        } else {
            url = `https://nominatim.openstreetmap.org/search?format=jsonv2&q=${encodeURIComponent(query)}&countrycodes=${country}&addressdetails=1&limit=1`;
        }
    
        // Show loading animation
        showLoading(true);
    
        fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            if (Array.isArray(data) && data.length > 0) {
                displayResults(data[0]);
            } else if (typeof data === 'object' && data.address) {
                displayResults(data);
            } else {
                alert('Location not found. Please try again.');
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Error fetching location:', error);
            alert('An error occurred while fetching the location.');
        });
    }

    // Update the display with location details and initialize/update the map
    function displayResults(data) {
        addressEl.textContent = data.display_name || 'N/A';
        postalCodeEl.textContent = (data.address && data.address.postcode) ? data.address.postcode : 'N/A';
        countryEl.textContent = (data.address && data.address.country) ? data.address.country : 'N/A';
        latEl.textContent = data.lat || 'N/A';
        lonEl.textContent = data.lon || 'N/A';

        // Initialize or update the map with the new coordinates
        initMap(data.lat, data.lon);

        // Reveal the results container and scroll smoothly into view
        results.classList.remove('hidden');
        results.scrollIntoView({ behavior: 'smooth' });

        // Optionally show a notification
        if (notification) {
            notification.classList.add('show');
        }
    }

    // Show or hide a loading indicator by injecting a spinner into the button text
    function showLoading(isLoading) {
        if (isLoading) {
            // For the GPS button
            if (gpsBtn) {
                gpsBtn.innerHTML = 'Loading <span class="spinner"></span>';
            }
            // For the form submit button
            const submitBtn = form.querySelector('.wimpc-btn[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = 'Loading <span class="spinner"></span>';
            }
        } else {
            if (gpsBtn) {
                gpsBtn.textContent = 'Use My Current Location';
            }
            const submitBtn = form.querySelector('.wimpc-btn[type="submit"]');
            if (submitBtn) {
                submitBtn.textContent = 'Find Postal Code';
            }
        }
    }

    // Get current GPS location when the GPS button is clicked
    gpsBtn.addEventListener('click', function() {
        // Immediately show the spinner
        showLoading(true);
        
        // Allow the browser to render the spinner before starting geolocation
        setTimeout(function() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const coords = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    };
                    fetchLocation(coords);
                }, function(error) {
                    showLoading(false);
                    console.error('Geolocation error:', error);
                    alert('Unable to retrieve your location.');
                });
            } else {
                showLoading(false);
                alert('Geolocation is not supported by your browser.');
            }
        }, 0); // 0ms delay gives the browser a chance to update the UI
    });

    // Handle manual form submission for location search
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const country = document.getElementById('wimpc-country').value;
        const location = document.getElementById('wimpc-location').value.trim();

        if (country === '' || location === '') {
            alert('Please select a country and enter a location.');
            return;
        }

        fetchLocation(location, country);
    });

    // Handle notification close button if present
    const closeBtn = notification ? notification.querySelector('.close-btn') : null;
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            notification.classList.remove('show');
        });
    }

    // Add a listener for window resize events to force a map resize recalculation
    window.addEventListener('resize', function() {
        if (map) {
            map.invalidateSize();
        }
    });
});
