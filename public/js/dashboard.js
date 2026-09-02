document.addEventListener('DOMContentLoaded', () => {
    const hostsGrid = document.getElementById('hostsGrid');
    const searchInput = document.getElementById('searchInput');
    const sortSelect = document.getElementById('sortSelect');
    let hostsData = [];

    // Security: Utility to escape HTML and prevent XSS
    function escapeHtml(unsafe) {
        return (unsafe || '').toString()
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }

    async function fetchHosts() {
        try {
            const res = await fetch('?page=api/hosts');
            hostsData = await res.json();
            filterAndRender();
        } catch (e) {
            console.error('Failed to fetch hosts', e);
        }
    }

    function sortHosts(hosts) {
        const sortBy = sortSelect.value;
        return hosts.sort((a, b) => {
            const aInactive = (a.active !== undefined && a.active == 0);
            const bInactive = (b.active !== undefined && b.active == 0);
            
            // Inactive hosts always go to the bottom
            if (aInactive && !bInactive) return 1;
            if (!aInactive && bInactive) return -1;

            if (sortBy === 'name') {
                return a.name.localeCompare(b.name);
            } else if (sortBy === 'ip') {
                return a.ip.localeCompare(b.ip);
            } else if (sortBy === 'status') {
                // Offline first, then unknown, then online
                const order = { 'offline': 1, 'unknown': 2, 'online': 3 };
                const aOrder = order[a.status] || 4;
                const bOrder = order[b.status] || 4;
                if (aOrder !== bOrder) return aOrder - bOrder;
                return a.name.localeCompare(b.name);
            }
            return 0;
        });
    }

    function filterAndRender() {
        const query = searchInput.value.toLowerCase();
        let filtered = hostsData.filter(h => 
            h.name.toLowerCase().includes(query) || 
            h.ip.toLowerCase().includes(query)
        );
        
        filtered = sortHosts(filtered);
        renderHosts(filtered);
    }

    function renderHosts(hosts) {
        hostsGrid.innerHTML = '';
        if (hosts.length === 0) {
            hostsGrid.innerHTML = `<div class="text-muted col-span-full">${window.i18n.no_data}</div>`;
            return;
        }
        hosts.forEach(host => {
            const isInactive = (host.active !== undefined && host.active == 0);
            const statusClass = isInactive ? 'status-unknown' : `status-${escapeHtml(host.status)}`;
            const displayStatus = isInactive ? window.i18n.unknown : (window.i18n[host.status] || escapeHtml(host.status)).toUpperCase();
            
            const card = document.createElement('a');
            card.href = `?page=host&id=${host.id}`;
            card.className = 'host-card';
            if (isInactive) {
                card.style.opacity = '0.6';
            }
            card.innerHTML = `
                <div class="host-card-header">
                    <div>
                        <div class="host-card-title">${escapeHtml(host.name)}</div>
                        <div class="host-card-ip">${escapeHtml(host.ip)}</div>
                    </div>
                    <span class="status-badge ${statusClass}">${displayStatus}</span>
                </div>
            `;
            hostsGrid.appendChild(card);
        });
    }

    searchInput.addEventListener('input', filterAndRender);
    sortSelect.addEventListener('change', filterAndRender);

    fetchHosts();
    setInterval(fetchHosts, 5000);
});
