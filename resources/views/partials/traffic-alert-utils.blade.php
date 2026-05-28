<script>
    window.TrafficAlertUtils = window.TrafficAlertUtils || (function () {
        const THRESHOLD_STORAGE_KEY = 'trafficThresholds';
        const ALERT_MUTE_STORAGE_KEY = 'trafficAlertMuted';
        const ALERT_DEBOUNCE_MS = 5000;
        const ALERT_PERSIST_MS = 10 * 60 * 1000;

        let lastAlertTime = 0;

        function loadThresholds() {
            try {
                const raw = localStorage.getItem(THRESHOLD_STORAGE_KEY);
                if (!raw) {
                    return { maxRxBps: 0, maxTxBps: 0 };
                }

                const parsed = JSON.parse(raw) || {};
                return {
                    maxRxBps: Number(parsed.maxRxBps) || 0,
                    maxTxBps: Number(parsed.maxTxBps) || 0,
                };
            } catch (e) {
                return { maxRxBps: 0, maxTxBps: 0 };
            }
        }

        function saveThresholds(maxRxBps, maxTxBps) {
            try {
                localStorage.setItem(THRESHOLD_STORAGE_KEY, JSON.stringify({
                    maxRxBps: Number(maxRxBps) || 0,
                    maxTxBps: Number(maxTxBps) || 0,
                }));
            } catch (e) {
                console.warn('Gagal menyimpan thresholds ke localStorage', e);
            }
        }

        function loadAlertMuteState() {
            try {
                return localStorage.getItem(ALERT_MUTE_STORAGE_KEY) === '1';
            } catch (e) {
                return false;
            }
        }

        function saveAlertMuteState(isMuted) {
            try {
                if (isMuted) {
                    localStorage.setItem(ALERT_MUTE_STORAGE_KEY, '1');
                } else {
                    localStorage.removeItem(ALERT_MUTE_STORAGE_KEY);
                }
            } catch (e) {
                console.warn('Gagal menyimpan status silent alert ke localStorage', e);
            }
        }

        function toggleAlertMuteState() {
            const nextState = !loadAlertMuteState();
            saveAlertMuteState(nextState);
            return nextState;
        }

        function formatTrafficValue(value) {
            if (!Number.isFinite(value)) {
                return '0';
            }

            if (value >= 1000000) {
                return (value / 1000000).toFixed(2) + ' Mbps';
            }

            if (value >= 1000) {
                return (value / 1000).toFixed(2) + ' Kbps';
            }

            return Math.round(value) + ' bps';
        }

        function buildTrafficAlertMessage(data, rxExceed, txExceed, thresholds) {
            let alertMsg = '⚠️ Traffic Alert!\n\n';

            if (rxExceed) {
                alertMsg += `RX: ${formatTrafficValue(data.rx_bps)} (Threshold: ${(thresholds.maxRxBps / 1000000).toFixed(0)} Mbps)\n`;
            }

            if (txExceed) {
                alertMsg += `TX: ${formatTrafficValue(data.tx_bps)} (Threshold: ${(thresholds.maxTxBps / 1000000).toFixed(0)} Mbps)`;
            }

            return alertMsg;
        }

        function persistTrafficAlert(alertData) {
            try {
                localStorage.setItem('trafficAlert', JSON.stringify(alertData));
            } catch (e) {
                console.warn('Gagal menyimpan alert ke localStorage', e);
            }
        }

        function showTrafficWarningToast(data, rxExceed, txExceed) {
            if (typeof Swal === 'undefined') {
                return;
            }

            Swal.fire({
                icon: 'warning',
                title: 'Traffic Berlebih!',
                html: `
                    <div>${(rxExceed ? `RX: ${formatTrafficValue(data.rx_bps)}` : '') + (txExceed ? (rxExceed ? ' / ' : '') + `TX: ${formatTrafficValue(data.tx_bps)}` : '')}</div>
                `,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                showCloseButton: true,
                timer: 5000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.style.cursor = 'pointer';
                    // Set cursor ke pointer pada elemen teks internal SweetAlert agar hand cursor muncul di seluruh area toast
                    toast.querySelectorAll('.swal2-title, .swal2-html-container, .swal2-content').forEach(el => {
                        el.style.cursor = 'pointer';
                    });
                    
                    toast.addEventListener('click', (e) => {
                        // Jangan redirect jika yang diklik adalah tombol close (x)
                        if (e.target.closest('.swal2-close')) {
                            return;
                        }
                        
                        const dashboardUrl = "{{ route('dashboard') }}";
                        // Hanya redirect jika tidak sedang berada di halaman dashboard
                        if (window.location.href.split('?')[0] !== dashboardUrl.split('?')[0]) {
                            window.location.href = dashboardUrl;
                        }
                    });
                }
            });
        }

        function shouldNotify(thresholds, isMuted, data, isOffline) {
            if (isOffline || isMuted || (thresholds.maxRxBps <= 0 && thresholds.maxTxBps <= 0)) {
                return false;
            }

            const rxExceed = thresholds.maxRxBps > 0 && Number(data.rx_bps || 0) > thresholds.maxRxBps;
            const txExceed = thresholds.maxTxBps > 0 && Number(data.tx_bps || 0) > thresholds.maxTxBps;

            return {
                rxExceed,
                txExceed,
                hit: rxExceed || txExceed,
            };
        }

        function handleTrafficThresholdAlert(data, isOffline) {
            const thresholds = loadThresholds();
            const muteState = loadAlertMuteState();
            const status = shouldNotify(thresholds, muteState, data, isOffline);

            if (!status || !status.hit) {
                return;
            }

            const currentTime = Date.now();
            if ((currentTime - lastAlertTime) <= ALERT_DEBOUNCE_MS) {
                return;
            }

            const alertMsg = buildTrafficAlertMessage(data, status.rxExceed, status.txExceed, thresholds);
            const alertData = {
                time: currentTime,
                rxExceed: !!status.rxExceed,
                txExceed: !!status.txExceed,
                rx_bps: data.rx_bps || 0,
                tx_bps: data.tx_bps || 0,
                maxRxBps: thresholds.maxRxBps || 0,
                maxTxBps: thresholds.maxTxBps || 0,
                message: alertMsg,
                expiresAt: currentTime + ALERT_PERSIST_MS,
            };

            persistTrafficAlert(alertData);
            showTrafficWarningToast(data, status.rxExceed, status.txExceed);
            lastAlertTime = currentTime;
        }

        function updateAlertMuteDisplay() {
            const muteStatusEl = document.getElementById('alert-mute-status');
            const muteButton = document.getElementById('btn-toggle-alert-mute');
            const isMuted = loadAlertMuteState();

            if (muteStatusEl) {
                muteStatusEl.textContent = isMuted ? 'Silent Aktif' : 'Notifikasi Aktif';
                muteStatusEl.className = isMuted ? 'badge bg-warning text-dark small' : 'badge bg-success small';
            }

            if (muteButton) {
                muteButton.innerHTML = isMuted
                    ? '<i class="bi bi-bell"></i> Unsilent'
                    : '<i class="bi bi-bell-slash"></i> Silent';
                muteButton.className = isMuted
                    ? 'btn btn-sm btn-warning flex-grow-1'
                    : 'btn btn-sm btn-outline-secondary flex-grow-1';
            }
        }

        function bindMuteToggle(buttonSelector) {
            const button = document.querySelector(buttonSelector);
            if (!button) {
                return;
            }

            button.addEventListener('click', function () {
                const isMuted = toggleAlertMuteState();
                updateAlertMuteDisplay();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: isMuted ? 'warning' : 'success',
                        title: isMuted ? 'Peringatan disenyapkan (Muted)' : 'Peringatan diaktifkan kembali',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2500,
                        timerProgressBar: true
                    });
                }
            });
        }

        function initGlobalPolling(realtimeEndpoint) {
            let lastGlobalAlertTime = 0;

            async function pollTrafficAlert() {
                try {
                    const thresholds = loadThresholds();
                    if ((thresholds.maxRxBps <= 0 && thresholds.maxTxBps <= 0) || loadAlertMuteState()) {
                        return;
                    }

                    const response = await fetch(realtimeEndpoint, { headers: { 'Accept': 'application/json' } });
                    const data = await response.json();

                    if (!data || data.status === 'offline') {
                        return;
                    }

                    const status = shouldNotify(thresholds, false, data, false);
                    if (!status || !status.hit) {
                        return;
                    }

                    const currentTime = Date.now();
                    if ((currentTime - lastGlobalAlertTime) <= ALERT_DEBOUNCE_MS) {
                        return;
                    }

                    lastGlobalAlertTime = currentTime;
                    showTrafficWarningToast(data, status.rxExceed, status.txExceed);
                } catch (error) {
                    console.warn('Global dashboard alert polling gagal', error);
                }
            }

            pollTrafficAlert();
            setInterval(pollTrafficAlert, 1000);
        }

        return {
            loadThresholds,
            saveThresholds,
            loadAlertMuteState,
            saveAlertMuteState,
            toggleAlertMuteState,
            formatTrafficValue,
            buildTrafficAlertMessage,
            persistTrafficAlert,
            showTrafficWarningToast,
            handleTrafficThresholdAlert,
            updateAlertMuteDisplay,
            bindMuteToggle,
            initGlobalPolling,
        };
    })();
</script>