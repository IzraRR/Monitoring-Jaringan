/**
 * Dashboard Real-time Monitoring
 * Handles traffic chart updates and MikroTik status monitoring
 */

class DashboardMonitor {
    constructor(realtimeEndpoint) {
        this.realtimeEndpoint = realtimeEndpoint;
        this.chart = null;
        this.maxRxBps = 0;
        this.maxTxBps = 0;
        this.isModalOpen = false;
        this.pollingInterval = 3000; // 3 seconds (optimized from 1 second)
        this.maxDataPoints = 20;
        
        this.init();
    }

    init() {
        this.loadThresholdsFromLocalStorage();
        this.updateThresholdDisplay();
        this.updateAlertMuteDisplay();
        this.restoreSelectedPelanggan();

        const selectPelanggan = document.getElementById('select-pelanggan');
        this.currentPelangganId = selectPelanggan ? selectPelanggan.value : '';

        this.initChart();
        this.initBusinessCharts();
        this.bindEventListeners();
        this.startPolling();
    }

    initChart() {
        const ctx = document.getElementById('trafficChart');
        if (!ctx) return;

        this.chart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'RX (Mbps)',
                        borderColor: '#0f172a',
                        backgroundColor: 'rgba(15, 23, 42, 0.08)',
                        borderWidth: 2,
                        data: [],
                        tension: 0.1,
                        pointRadius: 2
                    },
                    {
                        label: 'TX (Mbps)',
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.08)',
                        borderWidth: 2,
                        data: [],
                        tension: 0.1,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000) return value.toFixed(2) + ' Gbps';
                                return Number(value).toFixed(2) + ' Mbps';
                            }
                        }
                    },
                    x: { grid: { display: false } }
                },
                plugins: {
                    legend: { position: 'top', align: 'start' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const v = context.raw;
                                return context.dataset.label + ': ' + Number(v).toFixed(2) + ' Mbps';
                            }
                        }
                    }
                }
            }
        });

        this.loadChartHistoryFromLocalStorage();
    }

    saveChartHistoryToLocalStorage() {
        if (!this.chart) return;
        const key = `traffic_chart_history_${this.currentPelangganId || 'all'}`;
        const history = {
            labels: this.chart.data.labels,
            rx: this.chart.data.datasets[0].data,
            tx: this.chart.data.datasets[1].data
        };
        localStorage.setItem(key, JSON.stringify(history));
    }

    loadChartHistoryFromLocalStorage() {
        if (!this.chart) return;
        const key = `traffic_chart_history_${this.currentPelangganId || 'all'}`;
        try {
            const saved = localStorage.getItem(key);
            if (saved) {
                const history = JSON.parse(saved);
                if (history && Array.isArray(history.labels) && Array.isArray(history.rx) && Array.isArray(history.tx)) {
                    this.chart.data.labels = history.labels;
                    this.chart.data.datasets[0].data = history.rx;
                    this.chart.data.datasets[1].data = history.tx;
                    this.chart.update('none');
                    return;
                }
            }
        } catch (e) {
            console.error('Gagal memuat riwayat grafik dari localStorage:', e);
        }
        // Fallback jika tidak ada data tersimpan
        this.chart.data.labels = [];
        this.chart.data.datasets[0].data = [];
        this.chart.data.datasets[1].data = [];
        this.chart.update('none');
    }

    updateTrafficChart(snapshot) {
        if (!this.chart) return;

        const timeLabel = new Date().toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });

        this.chart.data.labels.push(timeLabel);
        this.chart.data.datasets[0].data.push((snapshot.rx_bps ?? 0) / 1000000);
        this.chart.data.datasets[1].data.push((snapshot.tx_bps ?? 0) / 1000000);

        if (this.chart.data.labels.length > this.maxDataPoints) {
            this.chart.data.labels.shift();
            this.chart.data.datasets[0].data.shift();
            this.chart.data.datasets[1].data.shift();
        }

        this.chart.update('none');
        this.saveChartHistoryToLocalStorage();
    }

    updateRealtimeCards(data) {
        const isOffline = data.status === 'offline';
        const connected = !!data.connected;

        // Update status indicator
        this.updateElement('mikrotik-status-dot', (el) => {
            el.classList.remove('text-success', 'text-danger');
            el.classList.add(connected && !isOffline ? 'text-success' : 'text-danger');
        });

        this.updateElement('mikrotik-status-text', (el) => {
            el.textContent = connected && !isOffline ? 'TERHUBUNG' : 'OFFLINE';
        });

        // Update MikroTik info
        this.updateElement('mikrotik-identity', (el) => {
            el.textContent = isOffline ? 'Offline' : (data.identity ?? '-');
        });

        this.updateElement('mikrotik-uptime', (el) => {
            el.textContent = isOffline ? 'Offline' : (data.uptime ?? '-');
        });

        this.updateElement('mikrotik-cpu-load', (el) => {
            el.textContent = isOffline ? 'Offline' : 
                (data.cpu_load !== null && data.cpu_load !== undefined ? `${data.cpu_load}%` : '-');
        });

        this.updateElement('mikrotik-hotspot-active', (el) => {
            el.textContent = isOffline ? '0' : Number(data.hotspot_active ?? 0).toLocaleString('id-ID');
        });

        this.updateElement('mikrotik-pppoe-active', (el) => {
            el.textContent = isOffline ? '0' : Number(data.pppoe_active ?? 0).toLocaleString('id-ID');
        });

        this.updateElement('mikrotik-interface-name', (el) => {
            el.textContent = isOffline ? '-' : (data.interface_name ?? '-');
        });

        this.updateElement('mikrotik-offline-message', (el) => {
            el.classList.toggle('d-none', !isOffline);
        });

        if (!isOffline) {
            this.updateTrafficChart(data);
        } else {
            this.updateTrafficChart({ rx_bps: 0, tx_bps: 0 });
        }

        this.handleTrafficThresholdAlert(data, isOffline);
    }

    updateElement(id, callback) {
        const el = document.getElementById(id);
        if (el) callback(el);
    }

    async loadRealtimeStats() {
        try {
            let url = this.realtimeEndpoint;
            const selectPelanggan = document.getElementById('select-pelanggan');
            if (selectPelanggan && selectPelanggan.value) {
                const urlObj = new URL(url, window.location.origin);
                urlObj.searchParams.set('id_pelanggan', selectPelanggan.value);
                url = urlObj.pathname + urlObj.search;
            }

            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) throw new Error('Network response was not ok');

            const payload = await response.json();
            this.updateRealtimeCards(payload);
        } catch (error) {
            console.error('Gagal memuat statistik realtime dashboard:', error);
            this.updateRealtimeCards({
                status: 'offline',
                connected: false,
                identity: 'Offline',
                uptime: 'Offline',
                cpu_load: null,
                hotspot_active: 0,
                pppoe_active: 0,
                interface_name: '-',
                rx_bps: 0,
                tx_bps: 0,
            });
        }
    }

    loadThresholdsFromLocalStorage() {
        const thresholds = window.TrafficAlertUtils?.loadThresholds?.() || { maxRxBps: 0, maxTxBps: 0 };
        this.maxRxBps = Number(thresholds.maxRxBps) || 0;
        this.maxTxBps = Number(thresholds.maxTxBps) || 0;
    }

    restoreSelectedPelanggan() {
        const selectPelanggan = document.getElementById('select-pelanggan');
        if (selectPelanggan) {
            const savedValue = localStorage.getItem('dashboard_selected_pelanggan');
            if (savedValue !== null) {
                const optionExists = Array.from(selectPelanggan.options).some(option => option.value === savedValue);
                if (optionExists) {
                    selectPelanggan.value = savedValue;
                }
            }
        }
    }

    saveThresholdsToLocalStorage() {
        window.TrafficAlertUtils?.saveThresholds?.(this.maxRxBps, this.maxTxBps);
    }

    updateAlertMuteDisplay() {
        window.TrafficAlertUtils?.updateAlertMuteDisplay?.();
    }

    toggleAlertMuteState() {
        window.TrafficAlertUtils?.toggleAlertMuteState?.();
        this.updateAlertMuteDisplay();
    }

    handleTrafficThresholdAlert(data, isOffline) {
        window.TrafficAlertUtils?.handleTrafficThresholdAlert?.(data, isOffline);
    }

    updateThresholdDisplay() {
        const rxInput = document.getElementById('input-rx-threshold');
        const txInput = document.getElementById('input-tx-threshold');
        if (rxInput) {
            rxInput.value = this.maxRxBps > 0 ? (this.maxRxBps / 1000000) : '';
        }
        if (txInput) {
            txInput.value = this.maxTxBps > 0 ? (this.maxTxBps / 1000000) : '';
        }
    }

    bindEventListeners() {
        const form = document.getElementById('threshold-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                
                const rxInput = document.getElementById('input-rx-threshold');
                const txInput = document.getElementById('input-tx-threshold');
                const rxMbps = parseFloat(rxInput?.value) || 0;
                const txMbps = parseFloat(txInput?.value) || 0;
                
                this.maxRxBps = rxMbps > 0 ? rxMbps * 1000000 : 0;
                this.maxTxBps = txMbps > 0 ? txMbps * 1000000 : 0;
                
                this.saveThresholdsToLocalStorage();
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Threshold berhasil disimpan',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                }
            });
        }

        const selectPelanggan = document.getElementById('select-pelanggan');
        if (selectPelanggan) {
            selectPelanggan.addEventListener('change', () => {
                localStorage.setItem('dashboard_selected_pelanggan', selectPelanggan.value);
                
                // Simpan riwayat grafik pelanggan lama
                this.saveChartHistoryToLocalStorage();
                
                // Update currentPelangganId ke nilai baru
                this.currentPelangganId = selectPelanggan.value;
                
                // Muat riwayat grafik milik pelanggan baru
                this.loadChartHistoryFromLocalStorage();
                
                // Muat status/kartu realtime secara instan
                this.loadRealtimeStats();
            });
        }

        window.TrafficAlertUtils?.bindMuteToggle?.('#btn-toggle-alert-mute');
    }

    startPolling() {
        if (this.pollTimeout) {
            clearTimeout(this.pollTimeout);
        }

        const poll = async () => {
            const startTime = Date.now();
            try {
                await this.loadRealtimeStats();
            } catch (err) {
                console.error('Error selama pemrosesan polling:', err);
            } finally {
                const selectPelanggan = document.getElementById('select-pelanggan');
                // Jika pelanggan disaring, poll lebih cepat (1.5 detik) untuk menangkap perubahan speedtest yang singkat
                const baseInterval = (selectPelanggan && selectPelanggan.value) ? 1500 : this.pollingInterval;
                
                // Hitung sisa waktu delay setelah dikurangi durasi eksekusi API (drift correction)
                const elapsed = Date.now() - startTime;
                const nextDelay = Math.max(50, baseInterval - elapsed); // Minimal jeda 50ms untuk menghindari loop tanpa batas
                
                this.pollTimeout = setTimeout(poll, nextDelay);
            }
        };

        poll();
    }

    initBusinessCharts() {
        if (typeof businessStatsData === 'undefined') return;

        const financialCtx = document.getElementById('financialChart');
        if (financialCtx) {
            new Chart(financialCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: businessStatsData.financial_labels || [],
                    datasets: [
                        {
                            label: 'Realisasi Pemasukan (Rp)',
                            data: businessStatsData.financial_realisasi || [],
                            backgroundColor: 'rgba(16, 185, 129, 0.85)',
                            borderColor: '#10b981',
                            borderWidth: 1,
                            borderRadius: 4
                        },
                        {
                            label: 'Piutang / Belum Bayar (Rp)',
                            data: businessStatsData.financial_piutang || [],
                            backgroundColor: 'rgba(239, 68, 68, 0.85)',
                            borderColor: '#ef4444',
                            borderWidth: 1,
                            borderRadius: 4
                        },
                        {
                            label: 'Proyeksi MRR (Rp)',
                            data: businessStatsData.financial_mrr || [],
                            type: 'line',
                            borderColor: '#0f172a',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            tension: 0.1,
                            pointRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    if (value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + 'M';
                                    if (value >= 1000) return 'Rp ' + (value / 1000).toFixed(0) + 'k';
                                    return 'Rp ' + value;
                                }
                            }
                        },
                        x: { grid: { display: false } }
                    },
                    plugins: {
                        legend: { position: 'top', align: 'start' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const v = context.raw;
                                    return context.dataset.label + ': Rp ' + Number(v).toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });
        }

        const customerCtx = document.getElementById('customerChart');
        if (customerCtx) {
            new Chart(customerCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Pelanggan Aktif', 'Pelanggan Churn / Expired'],
                    datasets: [
                        {
                            data: [
                                businessStatsData.active_count || 0,
                                businessStatsData.churned_count || 0
                            ],
                            backgroundColor: [
                                '#10b981',
                                '#94a3b8'
                            ],
                            borderWidth: 0,
                            hoverOffset: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const v = context.raw;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const pct = total > 0 ? ((v / total) * 100).toFixed(1) : 0;
                                    return context.label + ': ' + v + ' (' + pct + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof realtimeEndpoint !== 'undefined') {
        window.dashboardMonitor = new DashboardMonitor(realtimeEndpoint);
    }
});
