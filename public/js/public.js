document.addEventListener('DOMContentLoaded', () => {
    const publicGrid = document.getElementById('publicGrid');
    const sortSelect = document.getElementById('sortSelect');
    const enableAudioBtn = document.getElementById('enableAudioBtn');
    const clock = document.getElementById('clock');

    const tvModeBtn = document.getElementById('tvModeBtn');

    let hostsData = [];
    let audioEnabled = localStorage.getItem('audioEnabled') === 'true';
    let tvModeEnabled = localStorage.getItem('tvModeEnabled') === 'true' || localStorage.getItem('tvModeEnabled') === null; // Default true
    let previousOfflineIds = new Set();
    let isFirstLoad = true;

    if (audioEnabled) {
        enableAudioBtn.style.display = 'none';
    }

    function updateTvModeBtn() {
        if (tvModeBtn) {
            tvModeBtn.textContent = (window.i18n.tv_view || 'TV Mode') + ': ' + (tvModeEnabled ? 'ON' : 'OFF');
        }
    }
    updateTvModeBtn();

    if (tvModeBtn) {
        tvModeBtn.addEventListener('click', () => {
            tvModeEnabled = !tvModeEnabled;
            localStorage.setItem('tvModeEnabled', tvModeEnabled ? 'true' : 'false');
            updateTvModeBtn();
            filterAndRender();
        });
    }

    function playBeep(type = 'normal') {
        if (!audioEnabled) return;

        let customPath = null;
        if (type === 'critical' && window.appSettings && window.appSettings.audio_critical) {
            customPath = window.appSettings.audio_critical;
        } else if (type === 'normal' && window.appSettings && window.appSettings.audio_normal) {
            customPath = window.appSettings.audio_normal;
        }

        if (customPath) {
            const customAudio = new Audio(customPath);
            customAudio.play().catch(e => {
                console.error("Custom audio playback failed", e);
                fallbackBeep(type);
            });
        } else {
            fallbackBeep(type);
        }
    }

    function fallbackBeep(type) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gainNode = ctx.createGain();

            if (type === 'critical') {
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(300, ctx.currentTime);
                osc.frequency.linearRampToValueAtTime(800, ctx.currentTime + 0.2);
                osc.frequency.linearRampToValueAtTime(300, ctx.currentTime + 0.4);
                osc.frequency.linearRampToValueAtTime(800, ctx.currentTime + 0.6);

                gainNode.gain.setValueAtTime(0.2, ctx.currentTime);
                gainNode.gain.linearRampToValueAtTime(0.2, ctx.currentTime + 0.8);
                gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 1.0);

                osc.connect(gainNode);
                gainNode.connect(ctx.destination);

                osc.start();
                osc.stop(ctx.currentTime + 1.0);
            } else {
                osc.type = 'square';
                osc.frequency.setValueAtTime(440, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.1);

                gainNode.gain.setValueAtTime(0.1, ctx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);

                osc.connect(gainNode);
                gainNode.connect(ctx.destination);

                osc.start();
                osc.stop(ctx.currentTime + 0.5);
            }
        } catch (err) {
            console.error("Fallback audio playback failed", err);
        }
    }

    enableAudioBtn.addEventListener('click', () => {
        audioEnabled = true;
        localStorage.setItem('audioEnabled', 'true');
        enableAudioBtn.style.display = 'none';
        playBeep(); // test beep to unlock audio context
    });

    function escapeHtml(unsafe) {
        return (unsafe || '').toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function updateClock() {
        const now = new Date();
        clock.textContent = now.toLocaleDateString() + ' ' + now.toLocaleTimeString();
    }
    setInterval(updateClock, 1000);
    updateClock();

    let isFetchingHosts = false;
    async function fetchHosts() {
        if (isFetchingHosts) return;
        isFetchingHosts = true;
        try {
            const res = await fetch('?page=api/hosts-public');
            if (!res.ok) throw new Error('HTTP ' + res.status);
            hostsData = await res.json();

            checkForNewOfflines(hostsData);
            filterAndRender();
        } catch (e) {
            console.error('Failed to fetch public hosts', e);
        } finally {
            isFetchingHosts = false;
        }
    }

    function checkForNewOfflines(hosts) {
        const activeHosts = hosts.filter(h => (h.active === undefined || h.active != 0));
        const currentOfflineIds = new Set(
            activeHosts.filter(h => h.status === 'offline').map(h => h.id)
        );

        const isCritical = currentOfflineIds.size > (activeHosts.length / 2) && activeHosts.length > 0;

        if (!isFirstLoad) {
            let newOfflineDetected = false;
            let newOfflines = [];
            for (let id of currentOfflineIds) {
                if (!previousOfflineIds.has(id)) {
                    newOfflineDetected = true;
                    newOfflines.push(hosts.find(h => h.id === id));
                }
            }
            if (newOfflineDetected) {
                if (isCritical) {
                    playBeep('critical');
                    showCriticalAlert(newOfflines);
                } else {
                    playBeep('normal');
                    showOfflineAlert(newOfflines);
                }
            }
        }

        previousOfflineIds = currentOfflineIds;
        isFirstLoad = false;
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
            } else if (sortBy === 'status') {
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
        const sorted = sortHosts([...hostsData]);

        publicGrid.innerHTML = '';
        if (sorted.length === 0) {
            publicGrid.innerHTML = `<div class="text-muted col-span-full" style="text-align:center; padding: 2rem;">${window.i18n.no_data}</div>`;
            return;
        }

        let sortedHostsCount = sorted.length;
        let onlineCount = 0;
        let offlineCount = 0;

        sorted.forEach(host => {
            const isInactive = (host.active !== undefined && host.active == 0);
            const statusClass = isInactive ? 'status-unknown' : `status-${escapeHtml(host.status)}`;

            if (!isInactive) {
                if (host.status === 'online') onlineCount++;
                if (host.status === 'offline') offlineCount++;
            }
            const card = document.createElement('div');

            card.className = `host-card public-card ${statusClass}-bg`;
            card.style.display = 'flex';
            card.style.flexDirection = 'column';
            card.style.justifyContent = 'center';
            card.style.alignItems = 'center';
            card.style.containerType = 'size';

            if (isInactive) {
                card.style.opacity = '0.5';
            }

            let icon = '⚪';
            if (!isInactive) {
                if (host.status === 'online') icon = '🟢';
                if (host.status === 'offline') icon = '🔴';
            } else {
                icon = '⚫'; // Disabled icon
            }

            const isPortrait = window.innerWidth < window.innerHeight;

            if (tvModeEnabled) {
                const textFontSize = isPortrait ? '16cqmin' : '10cqmin';
                const iconFontSize = isPortrait ? '30cqmin' : '25cqmin';
                card.innerHTML = `
                    <div class="public-card-icon" style="font-size: ${iconFontSize}; line-height: normal; margin-bottom: 2cqmin; padding: 2cqmin 0;">${icon}</div>
                    <div class="host-card-title public-card-title" style="font-size: ${textFontSize}; text-align: center; word-break: break-word; padding: 0 5cqmin; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.2;">${escapeHtml(host.name)}</div>
                `;
            } else {
                card.innerHTML = `
                    <div class="public-card-icon" style="font-size: 1.5rem; line-height: normal; margin-bottom: 0.5rem;">${icon}</div>
                    <div class="host-card-title public-card-title" style="font-size: 0.9rem; text-align: center; word-break: break-word; padding: 0 0.5rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.2;">${escapeHtml(host.name)}</div>
                `;
            }

            card.style.cursor = 'pointer';
            card.onclick = () => showHostModal(host);

            publicGrid.appendChild(card);
        });

        const summaryEl = document.getElementById('hostSummary');
        if (summaryEl) {
            summaryEl.innerHTML = `<span style="color: var(--success);">${onlineCount} Online</span> | <span style="color: var(--danger);">${offlineCount} Offline</span>`;
        }

        updateGridLayout(sortedHostsCount);
    }

    function updateGridLayout(count) {
        const grid = document.getElementById('publicGrid');
        if (!grid || count === 0) return;

        const W = grid.clientWidth;
        const H = grid.clientHeight;
        const gap = 6;

        const isPortrait = window.innerWidth < window.innerHeight;

        if (!tvModeEnabled) {
            grid.style.overflowY = 'auto';
            if (isPortrait) {
                grid.style.gridTemplateColumns = 'repeat(3, 1fr)';
                grid.style.gridTemplateRows = 'none';
                grid.style.gridAutoRows = '24vw';
            } else {
                grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(120px, 1fr))';
                grid.style.gridTemplateRows = 'none';
                grid.style.gridAutoRows = '120px';
            }
            return;
        }

        if (isPortrait) {
            grid.style.overflowY = 'auto';
            grid.style.gridTemplateColumns = 'repeat(5, 1fr)';
            grid.style.gridTemplateRows = 'none';
            grid.style.gridAutoRows = '24vw';
            return;
        }

        grid.style.overflowY = 'hidden';
        grid.style.gridAutoRows = 'auto';

        let bestC = 1;
        let bestR = count;
        let bestDiff = Infinity;
        const targetRatio = 4 / 3;

        for (let c = 1; c <= count; c++) {
            const r = Math.ceil(count / c);
            const cw = (W - (c - 1) * gap) / c;
            const ch = (H - (r - 1) * gap) / r;
            if (cw <= 0 || ch <= 0) continue;

            const ratio = cw / ch;
            // Using logarithmic difference for better symmetry in ratio differences
            const diff = Math.abs(Math.log(ratio / targetRatio));
            if (diff < bestDiff) {
                bestDiff = diff;
                bestC = c;
                bestR = r;
            }
        }

        grid.style.gridTemplateColumns = `repeat(${bestC}, 1fr)`;
        grid.style.gridTemplateRows = `repeat(${bestR}, 1fr)`;
    }

    window.addEventListener('resize', () => {
        const count = document.querySelectorAll('.public-card').length;
        if (count > 0) updateGridLayout(count);
    });

    function showOfflineAlert(hosts) {
        let alertContainer = document.getElementById('offlineAlertContainer');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'offlineAlertContainer';
            alertContainer.style.position = 'fixed';
            alertContainer.style.top = '20px';
            alertContainer.style.left = '50%';
            alertContainer.style.transform = 'translateX(-50%)';
            alertContainer.style.zIndex = '9999';
            alertContainer.style.display = 'flex';
            alertContainer.style.flexDirection = 'column';
            alertContainer.style.gap = '10px';
            document.body.appendChild(alertContainer);
        }

        if (!document.getElementById('alertAnimations')) {
            const style = document.createElement('style');
            style.id = 'alertAnimations';
            style.innerHTML = `
                @keyframes fadeInDown {
                    from { opacity: 0; transform: translateY(-20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                @keyframes fadeOutUp {
                    from { opacity: 1; transform: translateY(0); }
                    to { opacity: 0; transform: translateY(-20px); }
                }
            `;
            document.head.appendChild(style);
        }

        hosts.forEach(host => {
            const alertBox = document.createElement('div');
            alertBox.style.background = '#ef4444';
            alertBox.style.color = 'white';
            alertBox.style.padding = '15px 25px';
            alertBox.style.borderRadius = '8px';
            alertBox.style.boxShadow = '0 10px 25px rgba(239, 68, 68, 0.4)';
            alertBox.style.fontSize = '1.2rem';
            alertBox.style.fontWeight = 'bold';
            alertBox.style.textAlign = 'center';
            alertBox.style.animation = 'fadeInDown 0.3s ease-out forwards';
            alertBox.className = 'offline-toast-item';
            alertBox.innerHTML = `${window.i18n.js_host_offline_alert} ${escapeHtml(host.name)}`;

            alertContainer.appendChild(alertBox);

            const toasts = alertContainer.querySelectorAll('.offline-toast-item');
            if (toasts.length > 3) {
                // Remove the oldest toasts if we exceed 3
                for (let i = 0; i < toasts.length - 3; i++) {
                    const oldest = toasts[i];
                    if (oldest.parentNode && !oldest.classList.contains('fading-out')) {
                        oldest.classList.add('fading-out');
                        oldest.style.animation = 'fadeOutUp 0.3s ease-in forwards';
                        setTimeout(() => {
                            if (oldest.parentNode) oldest.parentNode.removeChild(oldest);
                        }, 300);
                    }
                }
            }

            setTimeout(() => {
                alertBox.style.animation = 'fadeOutUp 0.3s ease-in forwards';
                setTimeout(() => {
                    if (alertBox.parentNode) {
                        alertBox.parentNode.removeChild(alertBox);
                    }
                }, 300);
            }, 10000);
        });
    }

    function showCriticalAlert(hosts) {
        let alertContainer = document.getElementById('offlineAlertContainer');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'offlineAlertContainer';
            alertContainer.style.position = 'fixed';
            alertContainer.style.top = '20px';
            alertContainer.style.left = '50%';
            alertContainer.style.transform = 'translateX(-50%)';
            alertContainer.style.zIndex = '9999';
            alertContainer.style.display = 'flex';
            alertContainer.style.flexDirection = 'column';
            alertContainer.style.gap = '10px';
            document.body.appendChild(alertContainer);
        }

        const alertBox = document.createElement('div');
        alertBox.style.background = '#991b1b';
        alertBox.style.color = '#fecaca';
        alertBox.style.border = '2px solid #ef4444';
        alertBox.style.padding = '25px 40px';
        alertBox.style.borderRadius = '12px';
        alertBox.style.boxShadow = '0 10px 40px rgba(153, 27, 27, 0.6)';
        alertBox.style.fontSize = '2rem';
        alertBox.style.fontWeight = '900';
        alertBox.style.textAlign = 'center';
        alertBox.style.textTransform = 'uppercase';
        alertBox.style.animation = 'fadeInDown 0.3s ease-out forwards';

        let text = window.i18n.js_critical_offline_alert;
        alertBox.innerHTML = `⚠️ ${text} ⚠️`;

        alertContainer.appendChild(alertBox);

        setTimeout(() => {
            alertBox.style.animation = 'fadeOutUp 0.3s ease-in forwards';
            setTimeout(() => {
                if (alertBox.parentNode) {
                    alertBox.parentNode.removeChild(alertBox);
                }
            }, 300);
        }, 15000);
    }

    function formatDuration(dateString) {
        if (!dateString) return '-';
        const past = new Date(dateString.replace(' ', 'T'));
        const now = new Date();
        const diffMs = now - past;
        if (diffMs < 0 || isNaN(diffMs)) return '0m';

        const diffMins = Math.floor(diffMs / 60000);
        if (diffMins < 60) return `${diffMins}m`;

        const hours = Math.floor(diffMins / 60);
        const mins = diffMins % 60;
        if (hours < 24) {
            return `${hours}h ${mins}m`;
        }

        const days = Math.floor(hours / 24);
        const remainingHours = hours % 24;
        return `${days}d ${remainingHours}h`;
    }

    window.showHostModal = function (host) {
        let modalOverlay = document.getElementById('tvModalOverlay');
        if (!modalOverlay) {
            modalOverlay = document.createElement('div');
            modalOverlay.id = 'tvModalOverlay';
            modalOverlay.className = 'tv-modal-overlay';

            modalOverlay.addEventListener('click', (e) => {
                if (e.target === modalOverlay) {
                    modalOverlay.classList.remove('show');
                }
            });
            document.body.appendChild(modalOverlay);
        }

        const isInactive = (host.active !== undefined && host.active == 0);
        let statusText = (host.status || 'unknown').toUpperCase();
        let statusColor = 'var(--text)';
        if (isInactive) {
            statusText = 'DISABLED';
            statusColor = 'var(--text-muted)';
        } else if (host.status === 'online') {
            statusColor = 'var(--success)';
        } else if (host.status === 'offline') {
            statusColor = 'var(--danger)';
        }

        const durationStr = formatDuration(host.last_status_change);

        modalOverlay.innerHTML = `
            <div class="tv-modal-content">
                <div class="tv-modal-title">${escapeHtml(host.name)}</div>
                <div class="tv-modal-status" style="color: ${statusColor};">
                    ${window.i18n.tv_status || 'Status'}: <strong>${statusText}</strong>
                </div>
                <div class="tv-modal-duration">
                    ${window.i18n.tv_duration || 'Duration'}: <strong>${durationStr}</strong>
                </div>
                <button class="btn btn-secondary" style="margin-top: 1rem;" onclick="document.getElementById('tvModalOverlay').classList.remove('show')">${window.i18n.tv_close || 'Close'}</button>
            </div>
        `;

        setTimeout(() => {
            modalOverlay.classList.add('show');
        }, 10);
    };

    // ===================================================
    // PUBLIC ANALYTICS & TAB NAVIGATION
    // ===================================================
    const tabBtnDashboard = document.getElementById('tabBtnDashboard');
    const tabBtnAnalytics = document.getElementById('tabBtnAnalytics');
    const publicDashboardView = document.getElementById('publicDashboardView');
    const publicAnalyticsView = document.getElementById('publicAnalyticsView');
    const dashboardControls = document.getElementById('dashboardControls');

    let activeTab = localStorage.getItem('public_active_tab') || 'dashboard';
    let analyticsData = null;
    let statusPieChartInstance = null;
    let topOfflineBarChartInstance = null;
    let historyLineChartInstance = null;
    let currentTableFilter = 'ongoing';
    let currentTableSearch = '';

    let isFetchingAnalytics = false;
    async function fetchAnalytics() {
        if (isFetchingAnalytics) return;
        isFetchingAnalytics = true;
        try {
            const res = await fetch('?page=api/analytics-public');
            if (!res.ok) throw new Error('HTTP ' + res.status);
            analyticsData = await res.json();
            renderAnalytics(analyticsData);
        } catch (err) {
            console.error('Failed to fetch analytics data:', err);
        } finally {
            isFetchingAnalytics = false;
        }
    }

    function switchTab(tabName) {
        activeTab = tabName;
        localStorage.setItem('public_active_tab', tabName);

        if (tabName === 'dashboard') {
            if (tabBtnDashboard) tabBtnDashboard.classList.add('active');
            if (tabBtnAnalytics) tabBtnAnalytics.classList.remove('active');
            if (publicDashboardView) publicDashboardView.style.display = 'block';
            if (publicAnalyticsView) publicAnalyticsView.style.display = 'none';
            if (dashboardControls) dashboardControls.style.display = 'flex';
            if (hostsData.length > 0) {
                filterAndRender();
            }
        } else {
            if (tabBtnDashboard) tabBtnDashboard.classList.remove('active');
            if (tabBtnAnalytics) tabBtnAnalytics.classList.add('active');
            if (publicDashboardView) publicDashboardView.style.display = 'none';
            if (publicAnalyticsView) publicAnalyticsView.style.display = 'flex';
            if (dashboardControls) dashboardControls.style.display = 'none';
            fetchAnalytics();
        }
    }

    if (tabBtnDashboard && tabBtnAnalytics) {
        tabBtnDashboard.addEventListener('click', () => switchTab('dashboard'));
        tabBtnAnalytics.addEventListener('click', () => switchTab('analytics'));

        if (window.location.hash === '#analytics' || activeTab === 'analytics') {
            switchTab('analytics');
        } else {
            switchTab('dashboard');
        }
    }

    function renderAnalytics(data) {
        if (!data) return;
        renderStatusPieChart(data.status_breakdown);
        renderTopOfflineBarChart(data.top_offline_hosts);
        renderHistoryLineChart(data.daily_history_7d || data.daily_history_30d);
        renderOfflineTable(data);
    }

    function renderStatusPieChart(breakdown) {
        const ctx = document.getElementById('statusPieChart');
        if (!ctx || !breakdown) return;

        const totalEl = document.getElementById('statusTotalHosts');
        if (totalEl) totalEl.textContent = breakdown.total;

        const cntOnline = document.getElementById('cntOnline');
        const cntOffline = document.getElementById('cntOffline');
        const cntDisabled = document.getElementById('cntDisabled');
        const cntUnknown = document.getElementById('cntUnknown');
        if (cntOnline) cntOnline.textContent = breakdown.online;
        if (cntOffline) cntOffline.textContent = breakdown.offline;
        if (cntDisabled) cntDisabled.textContent = breakdown.disabled;
        if (cntUnknown) cntUnknown.textContent = breakdown.unknown;

        const chartLabels = [
            window.i18n.chart_status_online || 'Online',
            window.i18n.chart_status_offline || 'Offline',
            window.i18n.chart_status_disabled || 'Desativados',
            window.i18n.chart_status_unknown || 'Desconhecidos'
        ];
        const chartData = [
            breakdown.online,
            breakdown.offline,
            breakdown.disabled,
            breakdown.unknown
        ];
        const chartColors = ['#10b981', '#ef4444', '#64748b', '#f59e0b'];

        if (statusPieChartInstance) {
            statusPieChartInstance.data.labels = chartLabels;
            statusPieChartInstance.data.datasets[0].data = chartData;
            statusPieChartInstance.update('none');
        } else {
            statusPieChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        data: chartData,
                        backgroundColor: chartColors,
                        borderColor: '#1a1d2d',
                        borderWidth: 2,
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleColor: '#f8fafc',
                            bodyColor: '#cbd5e1',
                            borderColor: '#334155',
                            borderWidth: 1,
                            padding: 10,
                            callbacks: {
                                label: function (context) {
                                    const val = context.raw || 0;
                                    const total = breakdown.total || 1;
                                    const pct = ((val / total) * 100).toFixed(1);
                                    return ` ${context.label}: ${val} (${pct}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    function renderTopOfflineBarChart(topHosts) {
        const ctx = document.getElementById('topOfflineBarChart');
        if (!ctx) return;

        const hostsList = Array.isArray(topHosts) ? topHosts : [];
        const labels = hostsList.map(h => h.name);
        const values = hostsList.map(h => h.offline_count);

        if (topOfflineBarChartInstance) {
            topOfflineBarChartInstance.data.labels = labels;
            topOfflineBarChartInstance.data.datasets[0].data = values;
            topOfflineBarChartInstance.options.scales.y.ticks.autoSkip = false;
            topOfflineBarChartInstance.update('none');
        } else {
            topOfflineBarChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: window.i18n.chart_top10_subtitle || 'Alertas registrados',
                        data: values,
                        backgroundColor: '#f43f5e',
                        borderRadius: 3,
                        borderSkipped: false,
                        barThickness: 6,
                        maxBarThickness: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleColor: '#f8fafc',
                            bodyColor: '#cbd5e1',
                            borderColor: '#334155',
                            borderWidth: 1,
                            padding: 10,
                            callbacks: {
                                title: items => items[0].label,
                                label: context => ` ${window.i18n.chart_top10_subtitle || 'Alertas offline'}: ${context.raw}`
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0,
                                color: '#94a3b8',
                                font: { family: 'Inter', size: 10 }
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.05)'
                            }
                        },
                        y: {
                            ticks: {
                                autoSkip: false,
                                color: '#cbd5e1',
                                font: { family: 'Inter', size: 9 },
                                padding: 2,
                                callback: function (val) {
                                    const label = this.getLabelForValue(val) || '';
                                    return label.length > 20 ? label.substring(0, 18) + '…' : label;
                                }
                            },
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    }

    function renderHistoryLineChart(history) {
        const ctx = document.getElementById('historyLineChart');
        if (!ctx) return;

        const historyList = Array.isArray(history) ? history : [];
        const labels = historyList.map(d => d.label);
        const onlineVals = historyList.map(d => d.online);
        const offlineVals = historyList.map(d => d.offline);

        if (historyLineChartInstance) {
            historyLineChartInstance.data.labels = labels;
            historyLineChartInstance.data.datasets[0].data = onlineVals;
            historyLineChartInstance.data.datasets[1].data = offlineVals;
            historyLineChartInstance.update('none');
        } else {
            historyLineChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: window.i18n.chart_legend_online || 'Hosts Online',
                            data: onlineVals,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2.2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 3.5,
                            pointHoverRadius: 6
                        },
                        {
                            label: window.i18n.chart_legend_offline || 'Hosts com Queda',
                            data: offlineVals,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.12)',
                            borderWidth: 2.2,
                            tension: 0.3,
                            fill: true,
                            pointRadius: 3.5,
                            pointHoverRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#cbd5e1',
                                font: { family: 'Inter', size: 11 },
                                boxWidth: 10,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: '#1e293b',
                            titleColor: '#f8fafc',
                            bodyColor: '#cbd5e1',
                            borderColor: '#334155',
                            borderWidth: 1,
                            padding: 10,
                            callbacks: {
                                title: function (items) {
                                    const idx = items[0].dataIndex;
                                    return historyList[idx] ? `${historyList[idx].date} (${historyList[idx].label})` : items[0].label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: '#94a3b8',
                                font: { family: 'Inter', size: 11 },
                                maxRotation: 0,
                                autoSkip: false
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.04)'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: '#94a3b8',
                                font: { family: 'Inter', size: 11 },
                                precision: 0
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.05)'
                            }
                        }
                    }
                }
            });
        }
    }

    function formatDurationFromSeconds(totalSec) {
        if (totalSec < 0 || isNaN(totalSec)) return '0s';
        const days = Math.floor(totalSec / 86400);
        const hours = Math.floor((totalSec % 86400) / 3600);
        const mins = Math.floor((totalSec % 3600) / 60);
        const secs = totalSec % 60;

        if (days > 0) return `${days}d ${hours}h ${mins}m`;
        if (hours > 0) return `${hours}h ${mins}m ${secs}s`;
        if (mins > 0) return `${mins}m ${secs}s`;
        return `${secs}s`;
    }

    function formatDateTime(str) {
        if (!str) return '-';
        // Handle YYYY-MM-DD HH:mm:ss format
        const parts = str.split(' ');
        if (parts.length === 2) {
            const dateParts = parts[0].split('-');
            if (dateParts.length === 3) {
                return `${dateParts[2]}/${dateParts[1]}/${dateParts[0]} ${parts[1]}`;
            }
        }
        return str;
    }

    window.openHostModalFromEvent = function (hostId, hostName) {
        let foundHost = hostsData.find(h => h.id == hostId);
        if (!foundHost) {
            foundHost = {
                id: hostId,
                name: hostName,
                status: 'offline',
                active: 1
            };
        }
        showHostModal(foundHost);
    };

    function renderOfflineTable(data) {
        const tableBody = document.getElementById('offlineEventsTableBody');
        const emptyDiv = document.getElementById('offlineTableEmpty');
        const counterBadge = document.getElementById('offlineCounterBadge');
        const countFilterOngoing = document.getElementById('countFilterOngoing');
        const countFilterAll = document.getElementById('countFilterAll');

        const ongoingList = (data && Array.isArray(data.ongoing_events)) ? data.ongoing_events : [];
        const recentList = (data && Array.isArray(data.recent_events)) ? data.recent_events : [];

        if (counterBadge) counterBadge.textContent = ongoingList.length;
        if (countFilterOngoing) countFilterOngoing.textContent = ongoingList.length;
        if (countFilterAll) countFilterAll.textContent = recentList.length;

        let source = (currentTableFilter === 'ongoing') ? ongoingList : recentList;

        if (currentTableSearch) {
            source = source.filter(ev => (ev.name || '').toLowerCase().includes(currentTableSearch));
        }

        if (!tableBody) return;
        tableBody.innerHTML = '';

        if (source.length === 0) {
            if (emptyDiv) emptyDiv.style.display = 'block';
            return;
        }

        if (emptyDiv) emptyDiv.style.display = 'none';

        source.forEach(ev => {
            const tr = document.createElement('tr');
            const isOngoing = (ev.end_time === null || ev.end_time === '' || ev.status === 'offline');
            const statusBadge = isOngoing
                ? `<span class="event-badge event-badge-offline"><span class="dot" style="background:#ef4444; width:6px; height:6px; border-radius:50%; display:inline-block;"></span> OFFLINE</span>`
                : `<span class="event-badge event-badge-online"><span class="dot" style="background:#10b981; width:6px; height:6px; border-radius:50%; display:inline-block;"></span> ONLINE</span>`;

            const stateText = isOngoing
                ? `<span class="event-state-ongoing">⚠️ ${window.i18n.table_state_ongoing || 'Queda em Andamento'}</span>`
                : `<span class="event-state-resolved">✓ ${window.i18n.table_state_resolved || 'Restabelecido'}</span>`;

            const durationFormatted = formatDurationFromSeconds(ev.duration_seconds || 0);

            const safeName = encodeURIComponent(ev.name || '');
            const safeHostId = parseInt(ev.host_id, 10) || 0;

            tr.innerHTML = `
                <td>${statusBadge}</td>
                <td>
                    <span class="event-host-name" onclick="openHostModalFromEvent(${safeHostId}, decodeURIComponent('${safeName}'))">${escapeHtml(ev.name)}</span>
                </td>
                <td><span class="event-time">${escapeHtml(formatDateTime(ev.start_time))}</span></td>
                <td>
                    <span class="event-duration" data-start-time="${escapeHtml(ev.start_time)}" data-end-time="${escapeHtml(ev.end_time || '')}">${durationFormatted}</span>
                </td>
                <td>${stateText}</td>
                <td style="text-align: center;">
                    <button type="button" class="btn-table-action" title="${window.i18n.tv_host_details || 'Detalhes'}" onclick="openHostModalFromEvent(${safeHostId}, decodeURIComponent('${safeName}'))">
                        🔍 ${window.i18n.table_col_action || 'Detalhes'}
                    </button>
                </td>
            `;
            tableBody.appendChild(tr);
        });
    }

    // Live duration ticker every 1 second
    setInterval(() => {
        if (activeTab !== 'analytics') return;
        const durationEls = document.querySelectorAll('.event-duration');
        const now = new Date();
        durationEls.forEach(el => {
            const endTime = el.getAttribute('data-end-time');
            if (endTime && endTime !== '') return; // resolved event, duration fixed
            const startTimeStr = el.getAttribute('data-start-time');
            if (!startTimeStr) return;
            const past = new Date(startTimeStr.replace(' ', 'T'));
            const diffSec = Math.floor((now - past) / 1000);
            el.textContent = formatDurationFromSeconds(diffSec);
        });
    }, 1000);

    // Filter pill buttons
    const btnFilterOngoing = document.getElementById('btnFilterOngoing');
    const btnFilterAll = document.getElementById('btnFilterAll');
    if (btnFilterOngoing && btnFilterAll) {
        btnFilterOngoing.addEventListener('click', () => {
            currentTableFilter = 'ongoing';
            btnFilterOngoing.classList.add('active');
            btnFilterAll.classList.remove('active');
            if (analyticsData) renderOfflineTable(analyticsData);
        });
        btnFilterAll.addEventListener('click', () => {
            currentTableFilter = 'all';
            btnFilterAll.classList.add('active');
            btnFilterOngoing.classList.remove('active');
            if (analyticsData) renderOfflineTable(analyticsData);
        });
    }

    // Search filter input
    const offlineTableSearch = document.getElementById('offlineTableSearch');
    if (offlineTableSearch) {
        offlineTableSearch.addEventListener('input', (e) => {
            currentTableSearch = e.target.value.trim().toLowerCase();
            if (analyticsData) renderOfflineTable(analyticsData);
        });
    }

    // Auto-fetch analytics every 15 seconds when analytics tab is open
    setInterval(() => {
        if (activeTab === 'analytics') {
            fetchAnalytics();
        }
    }, 15000);

    sortSelect.addEventListener('change', filterAndRender);

    fetchHosts();
    setInterval(fetchHosts, 5000);
});

