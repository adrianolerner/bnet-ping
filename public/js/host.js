document.addEventListener('DOMContentLoaded', async () => {
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = '#2e344e';

    const fetchMetrics = async () => {
        try {
            const res = await fetch(`?page=api/metrics&id=${hostId}&period=${period}`);
            return await res.json();
        } catch (e) {
            console.error('Error fetching metrics', e);
            return [];
        }
    };

    const data = await fetchMetrics();
    
    if (!data || data.length === 0) {
        document.querySelector('.charts-container').innerHTML = `<div class="empty-state text-muted">${window.i18n.no_data}</div>`;
        return;
    }

    const labels = data.map(d => {
        const date = new Date(d.timestamp.replace(' ', 'T'));
        return date.toLocaleString();
    });

    const createChart = (ctxId, label, datasets, yAxisTitle) => {
        const ctx = document.getElementById(ctxId).getContext('2d');
        return new Chart(ctx, {
            type: 'line',
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    title: { display: true, text: label, color: '#e2e8f0', font: { size: 16 } },
                    legend: { position: 'bottom' }
                },
                scales: {
                    x: { ticks: { maxTicksLimit: 10 } },
                    y: { title: { display: true, text: yAxisTitle }, beginAtZero: true }
                }
            }
        });
    };

    createChart('latencyChart', window.i18n.js_latency_ms || 'Latency (ms)', [
        { label: 'Avg', data: data.map(d => d.avg_ms), borderColor: '#6366f1', backgroundColor: 'rgba(99, 102, 241, 0.1)', fill: true, tension: 0.4 },
        { label: 'Max', data: data.map(d => d.max_ms), borderColor: '#ef4444', borderDash: [5, 5], tension: 0.4, hidden: true },
        { label: 'Min', data: data.map(d => d.min_ms), borderColor: '#10b981', borderDash: [5, 5], tension: 0.4, hidden: true }
    ], 'ms');

    createChart('packetLossChart', window.i18n.js_packet_loss || 'Packet Loss (%)', [
        { label: 'Loss %', data: data.map(d => d.packet_loss), borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.1)', fill: true, tension: 0.4 }
    ], '%');

    createChart('jitterChart', window.i18n.js_jitter_ms || 'Jitter (ms)', [
        { label: 'Jitter', data: data.map(d => d.jitter), borderColor: '#f59e0b', backgroundColor: 'rgba(245, 158, 11, 0.1)', fill: true, tension: 0.4 }
    ], 'ms');

    let isArchivedView = false;

    // Fetch and render downtime history
    const fetchHistory = async () => {
        try {
            const endpoint = isArchivedView ? `?page=api/history-archived&id=${hostId}` : `?page=api/history&id=${hostId}`;
            const res = await fetch(endpoint);
            return await res.json();
        } catch (e) {
            console.error('Error fetching history', e);
            return [];
        }
    };

    let historyData = [];
    let currentFilteredData = [];
    let currentSort = { column: 'date', order: 'desc' };
    let currentPage = 1;
    const itemsPerPage = 20;

    function formatDuration(ms) {
        if (ms < 0) return '0s';
        const seconds = Math.floor((ms / 1000) % 60);
        const minutes = Math.floor((ms / (1000 * 60)) % 60);
        const hours = Math.floor((ms / (1000 * 60 * 60)) % 24);
        const days = Math.floor(ms / (1000 * 60 * 60 * 24));
        
        let parts = [];
        if (days > 0) parts.push(days + 'd');
        if (hours > 0) parts.push(hours + 'h');
        if (minutes > 0) parts.push(minutes + 'm');
        if (seconds > 0 || parts.length === 0) parts.push(seconds + 's');
        
        return parts.join(' ');
    }

    const renderTable = (data) => {
        const tbody = document.querySelector('#hostHistoryTable tbody');
        const pagination = document.getElementById('hostHistoryPagination');
        
        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" class="text-muted text-center">${window.i18n.no_data}</td></tr>`;
            if(pagination) pagination.innerHTML = '';
            return;
        }

        const totalPages = Math.ceil(data.length / itemsPerPage);
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * itemsPerPage;
        const pageData = data.slice(start, start + itemsPerPage);
        
        tbody.innerHTML = '';
        pageData.forEach(item => {
            const startDate = new Date(item.start_time.replace(' ', 'T'));
            const startStr = startDate.toLocaleString();
            let endStr = 'NOW';
            let durationStr = '-';
            
            if (item.end_time) {
                const endDate = new Date(item.end_time.replace(' ', 'T'));
                endStr = endDate.toLocaleString();
                durationStr = formatDuration(endDate - startDate);
            } else {
                durationStr = formatDuration(new Date() - startDate);
                endStr = `<span class="status-badge status-offline">${window.i18n.now}</span>`;
            }
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${startStr}</td>
                <td>${endStr}</td>
                <td>${durationStr}</td>
            `;
            tbody.appendChild(tr);
        });

        if (pagination) {
            let html = '';
            if (totalPages > 1) {
                html += `<button class="btn btn-primary btn-sm" ${currentPage === 1 ? 'disabled' : ''} onclick="window.changeHostPage(${currentPage - 1})">${window.i18n.js_prev || 'Prev'}</button>`;
                html += `<span class="text-muted" style="font-size: 0.9rem;">${window.i18n.js_page || 'Page'} ${currentPage} ${window.i18n.js_of || 'of'} ${totalPages}</span>`;
                html += `<button class="btn btn-primary btn-sm" ${currentPage === totalPages ? 'disabled' : ''} onclick="window.changeHostPage(${currentPage + 1})">${window.i18n.js_next || 'Next'}</button>`;
            }
            pagination.innerHTML = html;
        }
    };

    window.changeHostPage = (p) => {
        currentPage = p;
        renderTable(currentFilteredData);
    };

    const sortData = (data) => {
        return data.sort((a, b) => {
            let valA = a.start_time;
            let valB = b.start_time;
            
            if (valA < valB) return currentSort.order === 'asc' ? -1 : 1;
            if (valA > valB) return currentSort.order === 'asc' ? 1 : -1;
            return 0;
        });
    };

    const filterAndRender = () => {
        const searchInput = document.getElementById('hostHistorySearch');
        let filtered = historyData;
        if (searchInput && searchInput.value) {
            const term = searchInput.value.toLowerCase();
            filtered = historyData.filter(item => {
                const dateStr = new Date(item.start_time.replace(' ', 'T')).toLocaleString().toLowerCase();
                return dateStr.includes(term);
            });
        }
        currentFilteredData = sortData([...filtered]);
        currentPage = 1;
        renderTable(currentFilteredData);
    };

    historyData = await fetchHistory();
    filterAndRender();

    const searchInput = document.getElementById('hostHistorySearch');
    if (searchInput) {
        searchInput.addEventListener('input', filterAndRender);
    }

    const toggleBtn = document.getElementById('toggleArchiveHostBtn');
    const title = document.getElementById('hostHistoryTitle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', async () => {
            isArchivedView = !isArchivedView;
            if (isArchivedView) {
                toggleBtn.textContent = window.i18n.btn_recent_downtimes || 'Back to Recent Downtimes';
                title.textContent = window.i18n.history_archived_title || 'Archived Downtimes (> 30 days)';
            } else {
                toggleBtn.textContent = window.i18n.btn_old_downtimes || 'Old Downtimes (> 30 days)';
                title.textContent = window.i18n.host_history_title || 'Downtime History';
            }
            historyData = await fetchHistory();
            filterAndRender();
        });
    }

    const headers = document.querySelectorAll('#hostHistoryTable th[data-sort]');
    headers.forEach(th => {
        th.addEventListener('click', () => {
            currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
            filterAndRender();
        });
    });
});
