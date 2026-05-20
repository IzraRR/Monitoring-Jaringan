<script>
    document.addEventListener('DOMContentLoaded', function () {
        const dashboardChartEl = document.getElementById('trafficChart');
        const realtimeEndpoint = @json(route('dashboard.realtime-stats'));

        if (dashboardChartEl) {
            return;
        }
        window.TrafficAlertUtils?.initGlobalPolling?.(realtimeEndpoint);
    });
</script>