<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require login to access the map
require_login();

$page_title = "Crime Map | CrimeAlert";

// Get distinct crime types for the filter dropdown
try {
    $stmt = $pdo->query("SELECT DISTINCT crime_type FROM crime_reports ORDER BY crime_type");
    $crime_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching crime types: " . $e->getMessage());
    $crime_types = [];
}

require_once 'includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="bg-white p-4 rounded-xl shadow-md mb-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Live Crime Map</h1>
        
        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="crimeTypeFilter" class="block text-sm font-medium text-gray-700">Crime Type</label>
                <select id="crimeTypeFilter" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">All Types</option>
                    <?php foreach ($crime_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo ucfirst(htmlspecialchars($type)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="dateRangeFilter" class="block text-sm font-medium text-gray-700">Date Range</label>
                <select id="dateRangeFilter" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    <option value="">All Time</option>
                    <option value="24h">Last 24 Hours</option>
                    <option value="7d">Last 7 Days</option>
                    <option value="30d">Last 30 Days</option>
                </select>
            </div>
            <div class="self-end">
                <button id="applyFilters" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                    Apply Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Map Container -->
    <div id="map" class="h-[70vh] w-full rounded-xl shadow-md bg-gray-200">
        <div class="flex items-center justify-center h-full text-gray-500">Loading map...</div>
    </div>
</div>

<script>
let map;
let markers = [];
let currentInfoWindow = null;

// Crime Type Colors for markers
const crimeTypeColors = {
    'theft': 'blue',
    'assault': 'red',
    'burglary': 'purple',
    'vandalism': 'orange',
    'other': 'grey'
};

// **FIX:** Attach the initMap function to the window object to make it globally available
window.initMap = function() {
    const initialLocation = { lat: 23.8103, lng: 90.4125 }; // Default to Dhaka
    map = new google.maps.Map(document.getElementById('map'), {
        center: initialLocation,
        zoom: 12,
        mapTypeControl: false,
        streetViewControl: false,
    });

    // Try to get user's current location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(position => {
            map.setCenter({
                lat: position.coords.latitude,
                lng: position.coords.longitude
            });
        }, () => {
            // Handle case where user denies location access
            console.log("User denied geolocation.");
        });
    }

    // Initial load of crime data
    loadCrimeData();

    // Add event listener for the filter button
    document.getElementById('applyFilters').addEventListener('click', loadCrimeData);
}

// Function to fetch crime data from the API and add markers
async function loadCrimeData() {
    clearMarkers();
    
    const crimeType = document.getElementById('crimeTypeFilter').value;
    const dateRange = document.getElementById('dateRangeFilter').value;

    // Construct API URL with query parameters
    const apiUrl = `api/get_crimes.php?crime_type=${crimeType}&date_range=${dateRange}`;

    try {
        const response = await fetch(apiUrl);
        if (!response.ok) {
            throw new Error(`Network response was not ok, status: ${response.status}`);
        }
        const crimes = await response.json();

        if (crimes.length === 0) {
            console.log("No crime data to display for the selected filters.");
            // Optionally, show a message to the user on the map
            document.getElementById('map').innerHTML = '<div class="flex items-center justify-center h-full text-gray-500">No reports found for the selected filters.</div>';
            return;
        }

        // Add a new marker for each crime report
        crimes.forEach(crime => {
            if (crime.latitude && crime.longitude) {
                addMarker(crime);
            }
        });

    } catch (error) {
        console.error('Error loading crime data:', error);
        alert('Could not load crime data. Please try again later.');
    }
}

// Function to add a single marker to the map
function addMarker(crime) {
    const position = { lat: parseFloat(crime.latitude), lng: parseFloat(crime.longitude) };
    
    const markerColor = crimeTypeColors[crime.crime_type.toLowerCase()] || 'grey';
    const markerIcon = {
        path: google.maps.SymbolPath.CIRCLE,
        fillColor: markerColor,
        fillOpacity: 0.8,
        scale: 8,
        strokeColor: 'white',
        strokeWeight: 2,
    };

    const marker = new google.maps.Marker({
        position: position,
        map: map,
        title: crime.title,
        icon: markerIcon
    });

    // Content for the info window
    const contentString = `
        <div class="p-2 max-w-xs">
            <h3 class="font-bold text-lg mb-1">${crime.title}</h3>
            <span class="inline-block bg-gray-200 rounded-full px-3 py-1 text-sm font-semibold text-gray-700 mr-2 mb-2">
                #${crime.crime_type}
            </span>
            <p class="text-gray-700 text-base">${crime.description.substring(0, 100)}...</p>
            <p class="text-gray-500 text-sm mt-2">Reported on: ${new Date(crime.created_at).toLocaleString()}</p>
            <a href="view-report.php?id=${crime.id}" class="text-indigo-600 hover:text-indigo-800 font-semibold mt-2 inline-block">View Details</a>
        </div>
    `;

    const infowindow = new google.maps.InfoWindow({
        content: contentString,
    });

    marker.addListener('click', () => {
        if (currentInfoWindow) {
            currentInfoWindow.close();
        }
        infowindow.open(map, marker);
        currentInfoWindow = infowindow;
    });

    markers.push(marker);
}

// Function to remove all markers from the map
function clearMarkers() {
    for (let i = 0; i < markers.length; i++) {
        markers[i].setMap(null);
    }
    markers = [];
    if (currentInfoWindow) {
        currentInfoWindow.close();
        currentInfoWindow = null;
    }
}
</script>

<!-- Google Maps API Script -->
<!-- IMPORTANT: Replace YOUR_API_KEY with your actual Google Maps API key -->
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDKxtYZuM7mDLDWULANqwI8kuChg4V_n7M&callback=initMap" async defer></script>


<?php
require_once 'includes/footer.php';
?>
