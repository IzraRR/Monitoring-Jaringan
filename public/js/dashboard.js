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
        this.initChart();
        this.loadThresholdsFromLocalStorage();
        this.updateThresholdDisplay();
        this.updateAlertMuteDisplay();
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
        }

        this.handleTrafficThresholdAlert(data, isOffline);
    }

    updateElement(id, callback) {
        const el = document.getElementById(id);
        if (el) callback(el);
    }

    async loadRealtimeStats() {
        try {
            const response = await fetch(this.realtimeEndpoint, {
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

    showThresholdModal() {
        this.isModalOpen = true;

        Swal.fire({
            title: 'Atur Alert Threshold Traffic',
            icon: 'info',
            html: `
                <div style="text-align: left;">
                    <label class="form-label fw-bold mb-2 d-block">RX (Download) Threshold (Mbps):</label>
                    <input type="number" id="swal-rx-threshold" class="form-control mb-3" 
                           placeholder="0 = Nonaktif" min="0" step="1" 
                           aria-label="RX Threshold in Mbps">
                    
                    <label class="form-label fw-bold mb-2 d-block">TX (Upload) Threshold (Mbps):</label>
                    <input type="number" id="swal-tx-threshold" class="form-control" 
                           placeholder="0 = Nonaktif" min="0" step="1"
                           aria-label="TX Threshold in Mbps">
                    
                    <small class="text-muted d-block mt-3">
                        <strong>Tips:</strong> Masukkan 0 untuk menonaktifkan threshold. 
                        Alert akan muncul jika traffic melebihi batas yang ditetapkan.
                    </small>
                </div>
            `,
            confirmButtonText: 'Simpan',
            cancelButtonText: 'Batal',
            showCancelButton: true,
            didClose: () => {
                this.isModalOpen = false;
            },
            didOpen: () => {
                document.getElementById('swal-rx-threshold').value = 
                    this.maxRxBps > 0 ? this.maxRxBps / 1000000 : '';
                document.getElementById('swal-tx-threshold').value = 
                    this.maxTxBps > 0 ? this.maxTxBps / 1000000 : '';
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const rxMbps = parseFloat(document.getElementById('swal-rx-threshold').value) || 0;
                const txMbps = parseFloat(document.getElementById('swal-tx-threshold').value) || 0;
                
                this.maxRxBps = rxMbps > 0 ? rxMbps * 1000000 : 0;
                this.maxTxBps = txMbps > 0 ? txMbps * 1000000 : 0;
                
                this.updateThresholdDisplay();
                this.saveThresholdsToLocalStorage();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Threshold berhasil diatur',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
            }
        });
    }

    updateThresholdDisplay() {
        const infoEl = document.getElementById('current-threshold-info');
        if (!infoEl) return;
        
        if (this.maxRxBps === 0 && this.maxTxBps === 0) {
            infoEl.textContent = 'Off';
            infoEl.className = 'badge bg-secondary align-self-center small';
        } else {
            let displayText = '';
            if (this.maxRxBps > 0) displayText += `RX: ${(this.maxRxBps / 1000000).toFixed(0)}M`;
            if (this.maxTxBps > 0) displayText += (displayText ? ' / ' : '') + `TX: ${(this.maxTxBps / 1000000).toFixed(0)}M`;
            infoEl.textContent = displayText;
            infoEl.className = 'badge bg-danger align-self-center small';
        }
    }

    bindEventListeners() {
        const setThresholdBtn = document.getElementById('btn-set-threshold');
        if (setThresholdBtn) {
            setThresholdBtn.addEventListener('click', () => this.showThresholdModal());
        }

        window.TrafficAlertUtils?.bindMuteToggle?.('#btn-toggle-alert-mute');
    }

    startPolling() {
        this.loadRealtimeStats();
        setInterval(() => this.loadRealtimeStats(), this.pollingInterval);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (typeof realtimeEndpoint !== 'undefined') {
        window.dashboardMonitor = new DashboardMonitor(realtimeEndpoint);
    }
});
