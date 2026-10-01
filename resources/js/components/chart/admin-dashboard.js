export const initAdminDashboard = () => {
    const chartElements = document.querySelectorAll('#adminInventoryChart, #adminUserRoleChart, #adminAccountStatusChart, #adminMaintenanceStatusChart, #adminInventoryStatusChart, #adminAuditActivityChart, #adminPendingRequestChart, #adminInventoryConditionChart');
    if (!chartElements.length) return [];

    return [...chartElements].map(chartElement => {
        const labels = JSON.parse(chartElement.dataset.labels || '[]');
        const values = JSON.parse(chartElement.dataset.values || '[]').map(Number);
        const colors = JSON.parse(chartElement.dataset.colors || '[]');

        const chart = new ApexCharts(chartElement, {
        series: values,
        labels,
        colors: colors.length ? colors : ['#10b981', '#0ea5e9', '#f59e0b', '#8b5cf6', '#ef4444', '#64748b'],
        chart: {
            type: 'donut',
            height: 280,
            fontFamily: 'Outfit, sans-serif',
            toolbar: { show: false },
        },
        dataLabels: { enabled: false },
        legend: {
            position: 'bottom',
            fontFamily: 'Outfit, sans-serif',
        },
        stroke: { width: 0 },
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: true,
                        total: { show: true, label: 'Units tracked' },
                    },
                },
            },
        },
        tooltip: {
            y: { formatter: value => `${value} units` },
        },
        });

        chart.render();
        return chart;
    });
};

export default initAdminDashboard;
