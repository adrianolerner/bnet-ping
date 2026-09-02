document.addEventListener('DOMContentLoaded', async () => {
    let isArchivedView = false;

    const fetchHistory = async () => {
        try {
            const endpoint = isArchivedView ? `?page=api/history-archived` : `?page=api/history`;
            const res = await fetch(endpoint);
            return await res.json();
        } catch (e) {
            console.error('Error fetching global history', e);
            return [];
        }
    };

    let historyData = [];
    let currentFilteredData = [];
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
        const tbody = document.querySelector('#globalHistoryTable tbody');
        const pagination = document.getElementById('globalHistoryPagination');
        
        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-muted text-center">${window.i18n.no_data}</td></tr>`;
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
                <td>${item.name}</td>
                <td class="text-muted">${item.ip}</td>
            `;
            tbody.appendChild(tr);
        });

        if (pagination) {
            let html = '';
            if (totalPages > 1) {
                html += `<button class="btn btn-primary btn-sm" ${currentPage === 1 ? 'disabled' : ''} onclick="window.changeGlobalPage(${currentPage - 1})">${window.i18n.js_prev || 'Prev'}</button>`;
                html += `<span class="text-muted" style="font-size: 0.9rem;">${window.i18n.js_page || 'Page'} ${currentPage} ${window.i18n.js_of || 'of'} ${totalPages}</span>`;
                html += `<button class="btn btn-primary btn-sm" ${currentPage === totalPages ? 'disabled' : ''} onclick="window.changeGlobalPage(${currentPage + 1})">${window.i18n.js_next || 'Next'}</button>`;
            }
            pagination.innerHTML = html;
        }
    };

    window.changeGlobalPage = (p) => {
        currentPage = p;
        renderTable(currentFilteredData);
    };

    let currentSort = { column: 'date', order: 'desc' };

    const sortData = (data) => {
        return data.sort((a, b) => {
            let valA, valB;
            switch(currentSort.column) {
                case 'name':
                    valA = a.name.toLowerCase();
                    valB = b.name.toLowerCase();
                    break;
                case 'ip':
                    valA = a.ip;
                    valB = b.ip;
                    break;
                case 'date':
                default:
                    valA = a.start_time;
                    valB = b.start_time;
                    break;
            }
            if (valA < valB) return currentSort.order === 'asc' ? -1 : 1;
            if (valA > valB) return currentSort.order === 'asc' ? 1 : -1;
            return 0;
        });
    };

    const filterAndRender = () => {
        const searchInput = document.getElementById('historySearch');
        let filtered = historyData;
        if (searchInput && searchInput.value) {
            const term = searchInput.value.toLowerCase();
            filtered = historyData.filter(item => {
                const dateStr = new Date(item.start_time.replace(' ', 'T')).toLocaleString().toLowerCase();
                return item.name.toLowerCase().includes(term) || 
                       item.ip.toLowerCase().includes(term) ||
                       dateStr.includes(term);
            });
        }
        currentFilteredData = sortData([...filtered]);
        currentPage = 1;
        renderTable(currentFilteredData);
    };

    historyData = await fetchHistory();
    filterAndRender();

    const searchInput = document.getElementById('historySearch');
    if (searchInput) {
        searchInput.addEventListener('input', filterAndRender);
    }

    const toggleBtn = document.getElementById('toggleArchiveBtn');
    const title = document.getElementById('globalHistoryTitle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', async () => {
            isArchivedView = !isArchivedView;
            if (isArchivedView) {
                toggleBtn.textContent = window.i18n.btn_recent_downtimes || 'Back to Recent Downtimes';
                title.textContent = window.i18n.history_archived_title || 'Archived Downtimes (> 30 days)';
            } else {
                toggleBtn.textContent = window.i18n.btn_old_downtimes || 'Old Downtimes (> 30 days)';
                title.textContent = window.i18n.history_title || 'Global Downtime History';
            }
            historyData = await fetchHistory();
            filterAndRender();
        });
    }

    const headers = document.querySelectorAll('#globalHistoryTable th[data-sort]');
    headers.forEach(th => {
        th.addEventListener('click', () => {
            const col = th.getAttribute('data-sort');
            if (currentSort.column === col) {
                currentSort.order = currentSort.order === 'asc' ? 'desc' : 'asc';
            } else {
                currentSort.column = col;
                currentSort.order = 'asc';
            }
            filterAndRender();
        });
    });
});
