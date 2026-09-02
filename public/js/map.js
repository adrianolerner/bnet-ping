let map;
let onlineCluster;
let offlineCluster;
let markers = {}; // Still useful to track marker instances by ID
let allMapHosts = [];
let refreshInterval;

// Chave da CARTO carregada dinamicamente
let CARTO_API_KEY = '';

document.addEventListener('DOMContentLoaded', async () => {
    try {
        const res = await fetch('?page=api/map-config');
        const data = await res.json();
        CARTO_API_KEY = data.carto_api_key || '';
    } catch (err) {
        console.error('Erro ao carregar configurações do mapa:', err);
    }

    initMap();
    setupFilters();
    fetchMapHosts();

    // Auto refresh every 15 seconds
    refreshInterval = setInterval(fetchMapHosts, 15000);
});

function initMap() {
    // Default center (can be customized) or fallback to roughly Brazil center
    map = L.map('map').setView([-15.7801, -47.9292], 4);

    // Use CartoDB Dark Matter tiles with API Key
    L.tileLayer(`https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png?key=${CARTO_API_KEY}`, {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);

    // Create custom panes to handle z-index priority (offline over online)
    map.createPane('onlinePane');
    map.getPane('onlinePane').style.zIndex = 500;
    map.createPane('offlinePane');
    map.getPane('offlinePane').style.zIndex = 600;

    // Initialize marker clusters
    onlineCluster = L.markerClusterGroup({
        clusterPane: 'onlinePane',
        maxClusterRadius: 50,
        iconCreateFunction: function (cluster) {
            const html = `<div style="background-color: rgba(16, 185, 129, 0.6); border-radius: 50%; border: 2px solid #10b981; color: white; font-weight: bold; text-align: center; box-shadow: 0 0 10px rgba(16, 185, 129, 0.8); display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; font-family: 'Inter', sans-serif;"><span>${cluster.getChildCount()}</span></div>`;
            return L.divIcon({
                html: html,
                className: 'marker-cluster',
                iconSize: L.point(40, 40)
            });
        }
    });

    offlineCluster = L.markerClusterGroup({
        clusterPane: 'offlinePane',
        maxClusterRadius: 50,
        iconCreateFunction: function (cluster) {
            const html = `<div style="background-color: rgba(239, 68, 68, 0.6); border-radius: 50%; border: 2px solid #ef4444; color: white; font-weight: bold; text-align: center; box-shadow: 0 0 10px rgba(239, 68, 68, 0.8); display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; font-family: 'Inter', sans-serif; animation: pulseRed 2s infinite;"><span>${cluster.getChildCount()}</span></div>`;
            return L.divIcon({
                html: html,
                className: 'marker-cluster',
                iconSize: L.point(40, 40)
            });
        }
    });

    map.addLayer(onlineCluster);
    map.addLayer(offlineCluster);
}

function setupFilters() {
    const searchInput = document.getElementById('mapSearchInput');
    const statusFilter = document.getElementById('mapStatusFilter');

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', applyFilters);
    }
}

function fetchMapHosts() {
    const iconSpan = document.getElementById('mapRefreshIcon');
    if (iconSpan) iconSpan.style.opacity = '0.5';

    fetch('?page=api/hosts')
        .then(res => res.json())
        .then(data => {
            if (iconSpan) iconSpan.style.opacity = '1';
            allMapHosts = data;
            applyFilters();
        })
        .catch(err => {
            console.error('Error fetching hosts for map:', err);
            if (iconSpan) iconSpan.style.opacity = '1';
        });
}

function applyFilters() {
    const searchInput = document.getElementById('mapSearchInput');
    const statusFilter = document.getElementById('mapStatusFilter');

    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const statusVal = statusFilter ? statusFilter.value : 'all';

    const filteredHosts = allMapHosts.filter(host => {
        const matchesSearch = host.name.toLowerCase().includes(searchTerm) || host.ip.toLowerCase().includes(searchTerm);
        const matchesStatus = statusVal === 'all' || host.status === statusVal;
        return matchesSearch && matchesStatus;
    });

    updateMarkers(filteredHosts);
}

function updateMarkers(hosts) {
    let bounds = [];

    // Clear clusters
    onlineCluster.clearLayers();
    offlineCluster.clearLayers();
    markers = {}; // Reset marker tracking

    hosts.forEach(host => {
        // Hide inactive hosts or those without online/offline status
        const isActive = (host.active == 1 || host.active === '1' || host.active === true);
        const isStatusValid = (host.status === 'online' || host.status === 'offline');

        if (!isActive || !isStatusValid || !host.latitude || !host.longitude) {
            return;
        }

        const lat = parseFloat(host.latitude);
        const lng = parseFloat(host.longitude);

        if (isNaN(lat) || isNaN(lng)) {
            return;
        }

        bounds.push([lat, lng]);

        let color = '#9ca3af'; // unknown grey
        let glowClass = '';
        let targetPane = 'onlinePane';
        let targetCluster = onlineCluster;

        if (host.status === 'online') {
            color = '#10b981'; // bright green
            glowClass = 'pulse-green';
            targetPane = 'onlinePane';
            targetCluster = onlineCluster;
        } else if (host.status === 'offline') {
            color = '#ef4444'; // bright red
            glowClass = 'pulse-red';
            targetPane = 'offlinePane';
            targetCluster = offlineCluster;
        }

        let pulseAnim = '';
        if (glowClass === 'pulse-green') pulseAnim = 'animation: pulseGreen 2s infinite;';
        if (glowClass === 'pulse-red') pulseAnim = 'animation: pulseRed 2s infinite;';

        const markerHtml = `
            <div class="${glowClass}" style="background-color: ${color}; width: 16px; height: 16px; border-radius: 50%; border: 2px solid #fff; ${pulseAnim}"></div>
        `;

        const customIcon = L.divIcon({
            className: 'custom-div-icon',
            html: markerHtml,
            iconSize: [20, 20],
            iconAnchor: [10, 10]
        });

        const viewDetailsText = window.i18n && window.i18n.map_view_details ? window.i18n.map_view_details : 'View Details';

        const popupContent = `
            <div style="font-family: 'Inter', sans-serif; color: #333;">
                <h4 style="margin: 0 0 5px 0; border-bottom: 1px solid #ccc; padding-bottom: 3px;">${escapeHtml(host.name)}</h4>
                <p style="margin: 2px 0; font-size: 0.9em;"><strong>IP:</strong> ${escapeHtml(host.ip)}</p>
                <p style="margin: 2px 0; font-size: 0.9em;"><strong>Status:</strong> <span style="color: ${color}; font-weight: bold;">${host.status.toUpperCase()}</span></p>
                <a href="?page=host&id=${host.id}" style="display: inline-block; margin-top: 5px; color: #3b82f6; text-decoration: none; font-size: 0.9em;">${viewDetailsText}</a>
            </div>
        `;

        // Create new marker assigned to the correct pane
        const marker = L.marker([lat, lng], {
            icon: customIcon,
            pane: targetPane
        });
        marker.bindPopup(popupContent);
        marker.bindTooltip(escapeHtml(host.name), {
            permanent: true,
            direction: 'top',
            offset: [0, -10],
            className: 'host-tooltip'
        });

        targetCluster.addLayer(marker);
        markers[host.id] = marker;
    });

    // Optionally fit bounds on first load if we have markers and bounds haven't been fitted yet
    if (bounds.length > 0 && !window.mapFitted) {
        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 15 });
        window.mapFitted = true;
    }
}

function mapRefresh() {
    fetchMapHosts();
}

function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}