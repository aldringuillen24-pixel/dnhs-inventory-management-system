const chartColors = ['#10b981', '#0ea5e9', '#f59e0b', '#8b5cf6', '#ef4444', '#64748b'];

export const initCustodianDashboard = () => {
    const categoryElement = document.querySelector('#custodianCategoryChart');
    const requestElement = document.querySelector('#custodianRequestChart');

    if (categoryElement) {
        const categoryChart = new ApexCharts(categoryElement, {
            series: JSON.parse(categoryElement.dataset.values || '[]'),
            labels: JSON.parse(categoryElement.dataset.labels || '[]'),
            colors: chartColors,
            chart: { type: 'donut', height: 280, fontFamily: 'Outfit, sans-serif', toolbar: { show: false } },
            dataLabels: { enabled: false },
            legend: { position: 'bottom', fontFamily: 'Outfit, sans-serif' },
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
            chart: { type: 'bar', height: 280, fontFamily: 'Outfit, sans-serif', toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 5, columnWidth: '45%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: JSON.parse(requestElement.dataset.labels || '[]') },
            yaxis: { min: 0, forceNiceScale: true },
            grid: { borderColor: '#e5e7eb' },
        });
        requestChart.render();
    }
};

export default initCustodianDashboard;