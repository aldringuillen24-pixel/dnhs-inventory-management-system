const chartColors = ['#0f766e', '#2563eb', '#d97706', '#7c3aed', '#dc2626', '#64748b'];

export const initCustodianDashboard = () => {
    const categoryElement = document.querySelector('#custodianCategoryChart');
    const requestElement = document.querySelector('#custodianRequestChart');

    if (categoryElement) {
        const categoryChart = new ApexCharts(categoryElement, {
            series: JSON.parse(categoryElement.dataset.values || '[]'),
            labels: JSON.parse(categoryElement.dataset.labels || '[]'),
            colors: chartColors,
            chart: { type: 'donut', height: 250, fontFamily: 'Outfit, sans-serif', toolbar: { show: false } },
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontFamily: 'Outfit, sans-serif', fontSize: '12px', markers: { width: 8, height: 8, radius: 2 } },
            stroke: { width: 0 },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Units' } } } } },
            tooltip: { y: { formatter: value => `${value} units` } },
        });
        categoryChart.render();
    }

    if (requestElement) {
        const requestChart = new ApexCharts(requestElement, {
            series: [{ name: 'Requests', data: JSON.parse(requestElement.dataset.values || '[]') }],
            colors: ['#0ea5e9'],
            chart: { type: 'donut', height: 250, fontFamily: 'Outfit, sans-serif', toolbar: { show: false } },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Requests' } } } } },
            dataLabels: { enabled: false },
            labels: JSON.parse(requestElement.dataset.labels || '[]'),
            legend: { position: 'bottom', fontFamily: 'Outfit, sans-serif', fontSize: '12px', markers: { width: 8, height: 8, radius: 2 } },
            stroke: { width: 0 },
            tooltip: { y: { formatter: value => `${value} requests` } },
        });
        requestChart.render();
    }
};

export default initCustodianDashboard;